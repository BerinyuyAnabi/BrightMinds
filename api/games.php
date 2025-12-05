<?php
/**
 * Bright Minds - Games API
 * Handles game data and play sessions
 */

require_once '../includes/config.php';

header('Content-Type: application/json');

$db = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        handleList();
        break;
    case 'get':
        handleGet();
        break;
    case 'start':
        handleStart();
        break;
    case 'end':
        handleEnd();
        break;
    case 'award':
        handleAward();
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}


//Get list of all games

function handleList() {
    global $db;
    
    $childId = getCurrentChildId();
    
    $games = $db->select("
        SELECT g.*,
               COUNT(ps.sessionID) as times_played,
               MAX(ps.score) as best_score
        FROM games g
        LEFT JOIN play_sessions ps ON g.gameID = ps.activity_id 
            AND ps.activity_type = 'game'
            AND ps.childID = ?
        WHERE g.is_active = 1
        GROUP BY g.gameID
        ORDER BY g.gameID
    ", $childId ? [$childId] : [0]);
    
    jsonResponse([
        'success' => true,
        'games' => $games
    ]);
}


//Get specific game details

function handleGet() {
    global $db;
    
    $gameId = intval($_GET['id'] ?? 0);
    
    if (!$gameId) {
        jsonResponse(['success' => false, 'message' => 'Game ID required'], 400);
    }
    
    $game = $db->selectOne("SELECT * FROM games WHERE gameID = ? AND is_active = 1", [$gameId]);
    
    if (!$game) {
        jsonResponse(['success' => false, 'message' => 'Game not found'], 404);
    }
    
    jsonResponse([
        'success' => true,
        'game' => $game
    ]);
}

// Start a game session

function handleStart() {
    global $db;
    
    requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $gameId = intval($input['gameId'] ?? 0);
    $childId = getCurrentChildId();
    
    if (!$childId) {
        jsonResponse(['success' => false, 'message' => 'Child ID required'], 400);
    }
    
    if (!$gameId) {
        jsonResponse(['success' => false, 'message' => 'Game ID required'], 400);
    }
    
    // Get next sessionID 
    $maxSession = $db->selectOne("SELECT MAX(sessionID) as max_id FROM play_sessions");
    $nextSessionId = ($maxSession['max_id'] ?? 0) + 1;

    // Create play session
    $sessionId = $db->insert("
        INSERT INTO play_sessions (sessionID, childID, activity_type, activity_id, start_time)
        VALUES (?, ?, 'game', ?, NOW())
    ", [$nextSessionId, $childId, $gameId]);
    
    if (!$sessionId) {
        jsonResponse(['success' => false, 'message' => 'Failed to start session'], 500);
    }
    
    jsonResponse([
        'success' => true,
        'sessionId' => $sessionId
    ]);
}

/
//End a game session and award rewards

function handleEnd() {
    global $db;
    
    requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = intval($input['sessionId'] ?? 0);
    $score = floatval($input['score'] ?? 0);
    $completed = boolval($input['completed'] ?? true);
    $childId = getCurrentChildId();
    
    if (!$sessionId) {
        jsonResponse(['success' => false, 'message' => 'Session ID required'], 400);
    }
    
    // Get session and game details
    $session = $db->selectOne("
        SELECT ps.*, g.xp_reward, g.coin_reward
        FROM play_sessions ps
        JOIN games g ON ps.activity_id = g.gameID
        WHERE ps.sessionID = ? AND ps.childID = ? AND ps.activity_type = 'game'
    ", [$sessionId, $childId]);
    
    if (!$session) {
        jsonResponse(['success' => false, 'message' => 'Session not found'], 404);
    }
    
    // Calculate duration
    $startTime = strtotime($session['start_time']);
    $duration = time() - $startTime;
    
    // Calculate rewards (bonus for high scores)
    $xpEarned = $session['xp_reward'];
    $coinsEarned = $session['coin_reward'];
    
    if ($score >= 90) {
        $xpEarned = floor($xpEarned * 1.5); 
        $coinsEarned = floor($coinsEarned * 1.5);
    } elseif ($score >= 70) {
        $xpEarned = floor($xpEarned * 1.2); 
        $coinsEarned = floor($coinsEarned * 1.2);
    }
    
    // Update session
    $db->update("
        UPDATE play_sessions 
        SET end_time = NOW(),
            duration_seconds = ?,
            score = ?,
            xp_earned = ?,
            coins_earned = ?,
            completed = ?
        WHERE sessionID = ?
    ", [$duration, $score, $xpEarned, $coinsEarned, $completed, $sessionId]);
    
    // Award XP and coins 
    award_xp($childId, $xpEarned, $coinsEarned);
    
    // Get updated child stats
    $child = $db->selectOne("
        SELECT total_xp, current_level, coins, streak_days
        FROM children
        WHERE childID = ?
    ", [$childId]);
    
    // Check for achievements
    checkAchievements($childId);
    
    // Create notification for parent
    $childName = $db->selectOne("SELECT display_name FROM children WHERE childID = ?", [$childId])['display_name'] ?? 'Your child';
    $gameTitle = $db->selectOne("SELECT title FROM games WHERE gameID = ?", [$session['activity_id']])['title'] ?? 'a game';
    
    $db->insert("
        INSERT INTO parent_notifications (parentID, notification_type, title, message)
        SELECT parentID, 'activity_completed', 'Activity Completed', ?
        FROM children WHERE childID = ?
    ", ["$childName completed $gameTitle and earned $xpEarned XP!", $childId]);
    
    jsonResponse([
        'success' => true,
        'rewards' => [
            'xp' => $xpEarned,
            'coins' => $coinsEarned
        ],
        'stats' => $child
    ]);
}


//Award XP and coins for a game (simplified endpoint)

function handleAward() {
    global $db;

    requireAuth();

    $input = json_decode(file_get_contents('php://input'), true);
    $gameId = intval($input['gameId'] ?? 0);
    $xpEarned = intval($input['xpEarned'] ?? 0);
    $coinsEarned = intval($input['coinsEarned'] ?? 0);
    $score = floatval($input['score'] ?? 100);
    $completed = boolval($input['completed'] ?? true);
    $childId = getCurrentChildId();

    error_log("handleAward: Received request - childID=$childId, gameID=$gameId, xp=$xpEarned, coins=$coinsEarned");

    if (!$childId) {
        error_log("handleAward: Missing child ID");
        jsonResponse(['success' => false, 'message' => 'Child ID required'], 400);
    }

    if (!$gameId) {
        error_log("handleAward: Missing game ID");
        jsonResponse(['success' => false, 'message' => 'Game ID required'], 400);
    }

    // Verify game exists
    $game = $db->selectOne("SELECT * FROM games WHERE gameID = ? AND is_active = 1", [$gameId]);
    if (!$game) {
        error_log("handleAward: Game not found - gameID=$gameId");
        jsonResponse(['success' => false, 'message' => 'Invalid game ID'], 404);
    }

    // Get next sessionID
    $maxSession = $db->selectOne("SELECT MAX(sessionID) as max_id FROM play_sessions");
    $nextSessionId = ($maxSession['max_id'] ?? 0) + 1;

    error_log("handleAward: Creating play session with ID $nextSessionId");

    // Create play session record
    $sessionResult = $db->insert("
        INSERT INTO play_sessions (sessionID, childID, activity_type, activity_id, start_time, end_time, score, xp_earned, coins_earned, completed)
        VALUES (?, ?, 'game', ?, NOW(), NOW(), ?, ?, ?, ?)
    ", [$nextSessionId, $childId, $gameId, $score, $xpEarned, $coinsEarned, $completed]);

    if (!$sessionResult) {
        error_log("handleAward: Failed to create play session");
        jsonResponse(['success' => false, 'message' => 'Failed to create play session'], 500);
    }

    error_log("handleAward: Calling award_xp");

    // Award XP and coins
    $awardResult = award_xp($childId, $xpEarned, $coinsEarned);

    if (!$awardResult) {
        error_log("handleAward: award_xp failed");
        jsonResponse(['success' => false, 'message' => 'Failed to award XP and coins'], 500);
    }

    // Get updated child stats
    $child = $db->selectOne("
        SELECT total_xp, current_level, coins, streak_days
        FROM children
        WHERE childID = ?
    ", [$childId]);

    error_log("handleAward: Updated stats - XP: {$child['total_xp']}, Level: {$child['current_level']}, Coins: {$child['coins']}");

    // Check for achievements
    checkAchievements($childId);
    
    // Create notification for parent
    $childName = $db->selectOne("SELECT display_name FROM children WHERE childID = ?", [$childId])['display_name'] ?? 'Your child';
    $gameTitle = $db->selectOne("SELECT title FROM games WHERE gameID = ?", [$gameId])['title'] ?? 'a game';
    
    $db->insert("
        INSERT INTO parent_notifications (parentID, notification_type, title, message)
        SELECT parentID, 'activity_completed', 'Activity Completed', ?
        FROM children WHERE childID = ?
    ", ["$childName completed $gameTitle and earned $xpEarned XP!", $childId]);

    error_log("handleAward: Sending success response");

    jsonResponse([
        'success' => true,
        'rewards' => [
            'xp' => $xpEarned,
            'coins' => $coinsEarned
        ],
        'stats' => $child
    ]);
}

//Check and unlock achievements

function checkAchievements($childId) {
    global $db;
    
    // Get play counts
    $stats = $db->selectOne("
        SELECT 
            COUNT(*) as total_activities,
            COUNT(CASE WHEN activity_type = 'game' THEN 1 END) as games_played,
            COUNT(CASE WHEN activity_type = 'quiz' AND score = 100 THEN 1 END) as perfect_quizzes
        FROM play_sessions
        WHERE childID = ? AND completed = 1
    ", [$childId]);
    
    $child = $db->selectOne("SELECT total_xp, current_level, streak_days FROM children WHERE childID = ?", [$childId]);
    
    // Check achievements
    $achievements = $db->select("
        SELECT achievementID, requirement_type, requirement_value
        FROM achievements
        WHERE is_active = 1
        AND achievementID NOT IN (
            SELECT achievementID FROM child_achievements WHERE childID = ?
        )
    ", [$childId]);
    
    foreach ($achievements as $achievement) {
        $unlocked = false;
        
        switch ($achievement['requirement_type']) {
            case 'activities_completed':
                $unlocked = $stats['total_activities'] >= $achievement['requirement_value'];
                break;
            case 'games_played':
                $unlocked = $stats['games_played'] >= $achievement['requirement_value'];
                break;
            case 'perfect_quiz':
                $unlocked = $stats['perfect_quizzes'] >= $achievement['requirement_value'];
                break;
            case 'total_xp':
                $unlocked = $child['total_xp'] >= $achievement['requirement_value'];
                break;
            case 'level_reached':
                $unlocked = $child['current_level'] >= $achievement['requirement_value'];
                break;
            case 'streak_days':
                $unlocked = $child['streak_days'] >= $achievement['requirement_value'];
                break;
        }
        
        if ($unlocked) {
            $db->insert("
                INSERT INTO child_achievements (childID, achievementID)
                VALUES (?, ?)
            ", [$childId, $achievement['achievementID']]);
        }
    }
}

?>

<?php
/**
 * Bright Minds - Dashboard API
 */

require_once '../includes/config.php';
header('Content-Type: application/json');

requireAuth();

$db = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get-profile':
        $childId = getCurrentChildId();

        error_log("get-profile: childID from session = " . ($childId ?? 'NULL'));
        error_log("get-profile: SESSION contents = " . print_r($_SESSION, true));

        if (!$childId) {
            jsonResponse(['success' => false, 'message' => 'Child ID not found in session'], 400);
        }

        // Get child profile
        $profile = $db->selectOne("
            SELECT childID, display_name, avatar, total_xp, current_level, coins, streak_days
            FROM children
            WHERE childID = ?
        ", [$childId]);

        if ($profile) {
            jsonResponse([
                'success' => true,
                'profile' => $profile
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Profile not found in database'], 404);
        }
        break;

    case 'get-parent-info':
        $childId = getCurrentChildId();

        // Get parent info if linked
        $parent = $db->selectOne("
            SELECT u.userID, u.username, u.email, c.parentID
            FROM children c
            LEFT JOIN users u ON c.parentID = u.userID
            WHERE c.childID = ?
        ", [$childId]);

        if ($parent && $parent['parentID']) {
            jsonResponse([
                'success' => true,
                'hasParent' => true,
                'parent' => [
                    'username' => $parent['username'],
                    'email' => $parent['email']
                ]
            ]);
        } else {
            jsonResponse([
                'success' => true,
                'hasParent' => false
            ]);
        }
        break;

    case 'get-recent-activities':
        $childId = getCurrentChildId();
        $limit = intval($_GET['limit'] ?? 5);

        // Get recent activities
        $activities = $db->select("
            SELECT
                ps.sessionID,
                ps.activity_type,
                ps.activity_id,
                ps.start_time,
                ps.xp_earned,
                ps.coins_earned,
                ps.score,
                CASE
                    WHEN ps.activity_type = 'game' THEN g.title
                    WHEN ps.activity_type = 'quiz' THEN q.title
                    ELSE ps.activity_type
                END as activity_title
            FROM play_sessions ps
            LEFT JOIN games g ON ps.activity_type = 'game' AND ps.activity_id = g.gameID
            LEFT JOIN quizzes q ON ps.activity_type = 'quiz' AND ps.activity_id = q.quizID
            WHERE ps.childID = ?
            ORDER BY ps.start_time DESC
            LIMIT ?
        ", [$childId, $limit]);

        jsonResponse([
            'success' => true,
            'activities' => $activities
        ]);
        break;

    case 'unlink-parent':
        $childId = getCurrentChildId();

        // Unlink parent
        $result = $db->query("
            UPDATE children
            SET parentID = NULL
            WHERE childID = ?
        ", [$childId]);

        if ($result) {
            jsonResponse(['success' => true, 'message' => 'Parent unlinked successfully']);
        } else {
            jsonResponse(['success' => false, 'message' => 'Failed to unlink parent'], 500);
        }
        break;

    case 'stats':
        $childId = getCurrentChildId();

        // Get child stats directly from children table
        $stats = $db->selectOne("
            SELECT childID, display_name, total_xp, current_level, coins, streak_days
            FROM children
            WHERE childID = ?
        ", [$childId]);

        $recentActivity = $db->select("
            SELECT * FROM vw_recent_activities
            WHERE childID = ?
            ORDER BY start_time DESC
            LIMIT 10
        ", [$childId]);

        $achievements = $db->select("
            SELECT a.*, ca.unlocked_at
            FROM child_achievements ca
            JOIN achievements a ON ca.achievementID = a.achievementID
            WHERE ca.childID = ?
            ORDER BY ca.unlocked_at DESC
            LIMIT 5
        ", [$childId]);

        $challenges = $db->select("
            SELECT dc.*, cc.progress, cc.completed
            FROM daily_challenges dc
            LEFT JOIN child_challenges cc ON dc.challengeID = cc.challengeID AND cc.childID = ?
            WHERE dc.active_date = CURDATE()
        ", [$childId]);

        jsonResponse([
            'success' => true,
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'achievements' => $achievements,
            'challenges' => $challenges
        ]);
        break;
        
    case 'leaderboard':
        $period = $_GET['period'] ?? 'weekly';
        $leaderboard = $db->select("
            SELECT l.*, c.display_name, c.avatar
            FROM leaderboard l
            JOIN children c ON l.childID = c.childID
            WHERE l.period_type = ? AND l.period_start = ?
            ORDER BY l.rank_position ASC
            LIMIT 20
        ", [$period, date('Y-m-d')]);
        
        jsonResponse(['success' => true, 'leaderboard' => $leaderboard]);
        break;
        
    case 'achievements':
        $childId = getCurrentChildId();
        $all = $db->select("
            SELECT a.*, 
                   CASE WHEN ca.achievementID IS NOT NULL THEN 1 ELSE 0 END as unlocked,
                   ca.unlocked_at
            FROM achievements a
            LEFT JOIN child_achievements ca ON a.achievementID = ca.achievementID AND ca.childID = ?
            WHERE a.is_active = 1
            ORDER BY unlocked DESC, a.rarity, a.achievementID
        ", [$childId]);
        
        jsonResponse(['success' => true, 'achievements' => $all]);
        break;

    case 'verify-invite-code':
        $inviteCode = $_GET['code'] ?? '';
        if (empty($inviteCode)) {
            jsonResponse(['success' => false, 'message' => 'Invite code is required'], 400);
        }

        // Check if invite code exists and is valid
        $codeData = $db->selectOne("
            SELECT * FROM users 
            WHERE parent_code = ?
        ", [$inviteCode]);

        if ($codeData) {
            jsonResponse(['success' => true, 'message' => 'Invite code is valid', 'parentInfo' => $codeData]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Invalid or expired invite code'], 400);
        }
        break;

    case 'link-to-parent':
        $childId = getCurrentChildId();
        $inviteCode = $_GET['code'] ?? '';
        // Check if invite code exists
        $parent = $db->selectOne("
            SELECT userID FROM users 
            WHERE parent_code = ?
        ", [$inviteCode]);

        if (!$parent) {
            jsonResponse(['success' => false, 'message' => 'Invalid invite code'], 400);
        }

        // Link child to parent
        $update = $db->execute("
        UPDATE children 
        SET parentID = ? 
        WHERE childID = ?
        ", [$parent['userID'], $childId]);

        if ($update) {
            jsonResponse([
                'success' => true,
                'message' => 'Child linked to parent successfully'
            ]);
        } else {
            jsonResponse([
                'success' => false,
                'message' => 'Failed to link child to parent. Please try again.'
            ]);
        }

        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
?>

<?php
/**
 * Bright Minds - Parent Dashboard API
 * Handles parent-specific operations and child management
 */

require_once '../includes/config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Public actions (no auth required)
$publicActions = ['verify-invite', 'link-child'];

// Require auth for protected actions
if (!in_array($action, $publicActions)) {
    requireAuth();
    
    // Verify user is a parent
    $db = getDB();
    $user = $db->selectOne("SELECT role FROM users WHERE userID = ?", [getCurrentUserId()]);
    
    if (!$user || $user['role'] !== 'parent') {
        jsonResponse([
            'success' => false,
            'message' => 'Access denied. Parent account required.'
        ], 403);
    }
}

switch ($action) {
    case 'dashboard':
        handleDashboard();
        break;
    case 'children':
        handleGetChildren();
        break;
    case 'child-details':
        handleChildDetails();
        break;
    case 'child-progress':
        handleChildProgress();
        break;
    case 'child-activities':
        handleChildActivities();
        break;
    case 'get-parent-code':
        handleGetParentCode();
        break;
    case 'generate-invite':
        handleGenerateInvite();
        break;
    case 'verify-invite':
        handleVerifyInvite();
        break;
    case 'link-child':
        handleLinkChild();
        break;
    case 'remove-child':
        handleRemoveChild();
        break;
    case 'daily-report':
        handleDailyReport();
        break;
    case 'weekly-report':
        handleWeeklyReport();
        break;
    case 'export-report':
        handleExportReport();
        break;
    case 'set-goal':
        handleSetGoal();
        break;
    case 'get-goals':
        handleGetGoals();
        break;
    case 'notifications':
        handleNotifications();
        break;
    case 'mark-notification-read':
        handleMarkNotificationRead();
        break;
    case 'update-display-name':
        handleUpdateDisplayName();
        break;
    case 'update-password':
        handleUpdatePassword();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

//Get parent dashboard overview

function handleDashboard() {
    global $db;
    $parentId = getCurrentUserId();
    
    // Get all children linked to this parent 
    $children = $db->select("
        SELECT c.childID, c.display_name, c.age, c.avatar,
               c.total_xp, c.current_level, c.coins, c.streak_days,
               c.last_activity_date,
               u.username, u.email
        FROM children c
        JOIN users u ON c.userID = u.userID
        WHERE c.parentID = ? AND c.parentID IS NOT NULL
        ORDER BY c.display_name
    ", [$parentId]);
    
    // Get summary stats
    $totalChildren = count($children);
    $totalActivities = 0;
    $totalXP = 0;
    $activeToday = 0;
    
    foreach ($children as $child) {
        $totalXP += $child['total_xp'];
        
        // Count activities
        $activities = $db->selectOne("
            SELECT COUNT(*) as count
            FROM play_sessions
            WHERE childID = ? AND completed = 1
        ", [$child['childID']]);
        $totalActivities += $activities['count'] ?? 0;
        
        // Check if active today
        if ($child['last_activity_date'] === date('Y-m-d')) {
            $activeToday++;
        }
    }
    
    // Get recent achievements across all children
    $recentAchievements = $db->select("
        SELECT ca.unlocked_at, c.display_name, c.avatar,
               a.title, a.description, a.badge_icon
        FROM child_achievements ca
        JOIN children c ON ca.childID = c.childID
        JOIN achievements a ON ca.achievementID = a.achievementID
        WHERE c.parentID = ?
        ORDER BY ca.unlocked_at DESC
        LIMIT 5
    ", [$parentId]);
    
    // Get unread notifications
    $unreadNotifications = $db->selectOne("
        SELECT COUNT(*) as count
        FROM parent_notifications
        WHERE parentID = ? AND is_read = 0
    ", [$parentId])['count'] ?? 0;
    
    jsonResponse([
        'success' => true,
        'summary' => [
            'totalChildren' => $totalChildren,
            'totalActivities' => $totalActivities,
            'totalXP' => $totalXP,
            'activeToday' => $activeToday
        ],
        'children' => $children,
        'recentAchievements' => $recentAchievements,
        'unreadNotifications' => $unreadNotifications
    ]);
}


//Get list of children

function handleGetChildren() {
    global $db;
    $parentId = getCurrentUserId();
    
    $children = $db->select("
        SELECT c.*, u.username, u.email,
               (SELECT COUNT(*) FROM play_sessions WHERE childID = c.childID AND completed = 1) as total_activities,
               (SELECT COUNT(*) FROM child_achievements WHERE childID = c.childID) as total_achievements
        FROM children c
        JOIN users u ON c.userID = u.userID
        WHERE c.parentID = ?
        ORDER BY c.display_name
    ", [$parentId]);
    
    jsonResponse([
        'success' => true,
        'children' => $children
    ]);
}


//Get detailed child information

function handleChildDetails() {
    global $db;
    $parentId = getCurrentUserId();
    $childId = intval($_GET['childId'] ?? 0);
    
    if (!$childId) {
        jsonResponse(['success' => false, 'message' => 'Child ID required'], 400);
    }
    
    // Verify parent owns this child
    $child = $db->selectOne("
        SELECT c.*, u.username, u.email, u.created_at
        FROM children c
        JOIN users u ON c.userID = u.userID
        WHERE c.childID = ? AND c.parentID = ?
    ", [$childId, $parentId]);
    
    if (!$child) {
        jsonResponse(['success' => false, 'message' => 'Child not found or access denied'], 404);
    }
    
    // Get activity statistics
    $stats = $db->selectOne("
        SELECT 
            COUNT(DISTINCT sessionID) as total_sessions,
            COUNT(DISTINCT CASE WHEN activity_type = 'game' THEN sessionID END) as games_played,
            COUNT(DISTINCT CASE WHEN activity_type = 'quiz' THEN sessionID END) as quizzes_taken,
            COUNT(DISTINCT CASE WHEN activity_type = 'story' THEN sessionID END) as stories_read,
            AVG(CASE WHEN activity_type = 'quiz' THEN score END) as avg_quiz_score,
            SUM(xp_earned) as total_xp_earned,
            SUM(coins_earned) as total_coins_earned
        FROM play_sessions
        WHERE childID = ? AND completed = 1
    ", [$childId]);
    
    // Get achievements
    $achievements = $db->select("
        SELECT a.*, ca.unlocked_at
        FROM child_achievements ca
        JOIN achievements a ON ca.achievementID = a.achievementID
        WHERE ca.childID = ?
        ORDER BY ca.unlocked_at DESC
    ", [$childId]);
    
    jsonResponse([
        'success' => true,
        'child' => $child,
        'stats' => $stats,
        'achievements' => $achievements
    ]);
}

//Get child progress data for charts

function handleChildProgress() {
    global $db;
    $parentId = getCurrentUserId();
    $childId = intval($_GET['childId'] ?? 0);
    $period = $_GET['period'] ?? 'week'; 
    
    // Verify parent owns this child
    $child = $db->selectOne("
        SELECT childID FROM children WHERE childID = ? AND parentID = ?
    ", [$childId, $parentId]);
    
    if (!$child) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    // Calculate date range
    $dateFrom = match($period) {
        'week' => date('Y-m-d', strtotime('-7 days')),
        'month' => date('Y-m-d', strtotime('-30 days')),
        'year' => date('Y-m-d', strtotime('-365 days')),
        default => date('Y-m-d', strtotime('-7 days'))
    };
    
    // Get daily XP earned
    $dailyXP = $db->select("
        SELECT DATE(start_time) as date, SUM(xp_earned) as xp
        FROM play_sessions
        WHERE childID = ? AND start_time >= ? AND completed = 1
        GROUP BY DATE(start_time)
        ORDER BY date
    ", [$childId, $dateFrom]);
    
    // Get activity breakdown
    $activityBreakdown = $db->select("
        SELECT activity_type, COUNT(*) as count
        FROM play_sessions
        WHERE childID = ? AND start_time >= ? AND completed = 1
        GROUP BY activity_type
    ", [$childId, $dateFrom]);
    
    // Get quiz performance
    $quizPerformance = $db->select("
        SELECT DATE(start_time) as date, AVG(score) as avg_score
        FROM play_sessions
        WHERE childID = ? AND activity_type = 'quiz' AND start_time >= ? AND completed = 1
        GROUP BY DATE(start_time)
        ORDER BY date
    ", [$childId, $dateFrom]);
    
    // Get time spent per day (in minutes)
    $timeSpent = $db->select("
        SELECT DATE(start_time) as date, SUM(duration_seconds) / 60 as minutes
        FROM play_sessions
        WHERE childID = ? AND start_time >= ? AND completed = 1
        GROUP BY DATE(start_time)
        ORDER BY date
    ", [$childId, $dateFrom]);
    
    jsonResponse([
        'success' => true,
        'period' => $period,
        'dailyXP' => $dailyXP,
        'activityBreakdown' => $activityBreakdown,
        'quizPerformance' => $quizPerformance,
        'timeSpent' => $timeSpent
    ]);
}


//Get child recent activities

function handleChildActivities() {
    global $db;
    $parentId = getCurrentUserId();
    $childId = intval($_GET['childId'] ?? 0);
    $limit = intval($_GET['limit'] ?? 20);
    
    // Verify parent owns this child
    $child = $db->selectOne("
        SELECT childID FROM children WHERE childID = ? AND parentID = ?
    ", [$childId, $parentId]);
    
    if (!$child) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $activities = $db->select("
        SELECT ps.*,
            CASE
                WHEN ps.activity_type = 'game' THEN g.title
                WHEN ps.activity_type = 'quiz' THEN q.title
                WHEN ps.activity_type = 'story' THEN s.title
                ELSE CONCAT(ps.activity_type, ' #', ps.activity_id)
            END as activity_title,
            CASE
                WHEN ps.activity_type = 'game' THEN g.category
                WHEN ps.activity_type = 'quiz' THEN q.category
                WHEN ps.activity_type = 'story' THEN s.category
                ELSE ''
            END as category
        FROM play_sessions ps
        LEFT JOIN games g ON ps.activity_type = 'game' AND ps.activity_id = g.gameID
        LEFT JOIN quizzes q ON ps.activity_type = 'quiz' AND ps.activity_id = q.quizID
        LEFT JOIN stories s ON ps.activity_type = 'story' AND ps.activity_id = s.storyID
        WHERE ps.childID = ? 
        AND (ps.completed = 1 OR ps.completed = TRUE)
        ORDER BY ps.start_time DESC
        LIMIT ?
    ", [$childId, $limit]);
    
    jsonResponse([
        'success' => true,
        'activities' => $activities
    ]);
}



//Get parent code from user account
 
function handleGetParentCode() {
    global $db;
    $parentId = getCurrentUserId();
    
    $user = $db->selectOne("
        SELECT parent_code FROM users WHERE userID = ? AND role = 'parent'
    ", [$parentId]);
    
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Parent account not found'], 404);
    }
    
    if (empty($user['parent_code'])) {
        // Generate parent code if it doesn't exist
        $parentCode = 'PAR-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));
        $db->update("
            UPDATE users SET parent_code = ? WHERE userID = ?
        ", [$parentCode, $parentId]);
        
        jsonResponse([
            'success' => true,
            'parentCode' => $parentCode
        ]);
    } else {
        jsonResponse([
            'success' => true,
            'parentCode' => $user['parent_code']
        ]);
    }
}

// Generate invite code for child to link account

function handleGenerateInvite() {
    global $db;
    $parentId = getCurrentUserId();
    
    // Generate unique invite code
    $inviteCode = strtoupper(substr(generateToken(8), 0, 8));
    
    // Store in database 
    $db->query("
        CREATE TABLE IF NOT EXISTS parent_invites (
            inviteID INT AUTO_INCREMENT PRIMARY KEY,
            parentID INT NOT NULL,
            invite_code VARCHAR(10) UNIQUE NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            is_used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parentID) REFERENCES users(userID) ON DELETE CASCADE,
            INDEX idx_code (invite_code)
        )
    ", []);
    
    // Insert invite code (expires in 7 days)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    $inviteId = $db->insert("
        INSERT INTO parent_invites (parentID, invite_code, expires_at)
        VALUES (?, ?, ?)
    ", [$parentId, $inviteCode, $expiresAt]);
    
    if (!$inviteId) {
        jsonResponse(['success' => false, 'message' => 'Failed to generate invite code'], 500);
    }
    
    jsonResponse([
        'success' => true,
        'inviteCode' => $inviteCode,
        'expiresAt' => $expiresAt
    ]);
}



//Verify invite code 

function handleVerifyInvite() {
    global $db;
    $inviteCode = strtoupper($_GET['code'] ?? '');
    
    if (empty($inviteCode)) {
        jsonResponse(['success' => false, 'message' => 'Invite code required'], 400);
    }
    
    $invite = $db->selectOne("
        SELECT pi.*, u.username as parent_username
        FROM parent_invites pi
        JOIN users u ON pi.parentID = u.userID
        WHERE pi.invite_code = ? AND pi.is_used = 0 AND pi.expires_at > NOW()
    ", [$inviteCode]);
    
    if (!$invite) {
        jsonResponse(['success' => false, 'message' => 'Invalid or expired invite code'], 404);
    }
    
    jsonResponse([
        'success' => true,
        'valid' => true,
        'parentUsername' => $invite['parent_username']
    ]);
}

// Link child account to parent using invite code

function handleLinkChild() {
    global $db;
    $input = json_decode(file_get_contents('php://input'), true);
    
    $inviteCode = strtoupper($input['inviteCode'] ?? '');
    $childUserId = intval($input['childUserId'] ?? 0);
    
    if (empty($inviteCode) || !$childUserId) {
        jsonResponse(['success' => false, 'message' => 'Invite code and child user ID required'], 400);
    }
    
    // Verify invite code
    $invite = $db->selectOne("
        SELECT * FROM parent_invites
        WHERE invite_code = ? AND is_used = 0 AND expires_at > NOW()
    ", [$inviteCode]);
    
    if (!$invite) {
        jsonResponse(['success' => false, 'message' => 'Invalid or expired invite code'], 404);
    }
    
    // Verify user is a child
    $user = $db->selectOne("
        SELECT * FROM users WHERE userID = ? AND role = 'child'
    ", [$childUserId]);
    
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'Child account not found'], 404);
    }
    
    // Link child to parent
    $updated = $db->update("
        UPDATE children SET parentID = ? WHERE userID = ?
    ", [$invite['parentID'], $childUserId]);
    
    if (!$updated) {
        jsonResponse(['success' => false, 'message' => 'Failed to link accounts'], 500);
    }
    
    // Mark invite as used
    $db->update("
        UPDATE parent_invites SET is_used = 1 WHERE inviteID = ?
    ", [$invite['inviteID']]);
    
    // Create notification for parent
    $child = $db->selectOne("SELECT display_name FROM children WHERE userID = ?", [$childUserId]);
    $db->insert("
        INSERT INTO parent_notifications (parentID, notification_type, title, message)
        VALUES (?, 'child_linked', 'New Child Account Linked', ?)
    ", [$invite['parentID'], "'{$child['display_name']}' has been linked to your parent account."]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Account successfully linked to parent!'
    ]);
}


//Remove child from parent account

function handleRemoveChild() {
    global $db;
    $parentId = getCurrentUserId();
    $input = json_decode(file_get_contents('php://input'), true);
    $childId = intval($input['childId'] ?? 0);
    
    if (!$childId) {
        jsonResponse(['success' => false, 'message' => 'Child ID required'], 400);
    }
    
    // Verify parent owns this child
    $child = $db->selectOne("
        SELECT childID FROM children WHERE childID = ? AND parentID = ?
    ", [$childId, $parentId]);
    
    if (!$child) {
        jsonResponse(['success' => false, 'message' => 'Child not found or access denied'], 404);
    }
    
    // Remove parent link
    $db->update("
        UPDATE children SET parentID = NULL WHERE childID = ?
    ", [$childId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Child account unlinked successfully'
    ]);
}


//Get daily report for child - shows everything child did today

function handleDailyReport() {
    global $db;
    $parentId = getCurrentUserId();
    $childId = intval($_GET['childId'] ?? 0);
    
    // Verify parent owns this child
    $child = $db->selectOne("
        SELECT c.childID, c.display_name, c.avatar
        FROM children c
        WHERE c.childID = ? AND c.parentID = ?
    ", [$childId, $parentId]);
    
    if (!$child) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $today = date('Y-m-d');
    $todayStart = $today . ' 00:00:00';
    $todayEnd = $today . ' 23:59:59';
    
    // Get all activities from today 
    $todayActivities = $db->select("
        SELECT ps.*,
            CASE
                WHEN ps.activity_type = 'game' THEN g.title
                WHEN ps.activity_type = 'quiz' THEN q.title
                WHEN ps.activity_type = 'story' THEN s.title
            END as activity_title,
            CASE
                WHEN ps.activity_type = 'game' THEN g.category
                WHEN ps.activity_type = 'quiz' THEN q.category
                WHEN ps.activity_type = 'story' THEN s.category
            END as category
        FROM play_sessions ps
        LEFT JOIN games g ON ps.activity_type = 'game' AND ps.activity_id = g.gameID
        LEFT JOIN quizzes q ON ps.activity_type = 'quiz' AND ps.activity_id = q.quizID
        LEFT JOIN stories s ON ps.activity_type = 'story' AND ps.activity_id = s.storyID
        WHERE ps.childID = ? 
        AND ps.completed = 1
        AND (
            DATE(ps.start_time) = ? 
            OR DATE(ps.end_time) = ?
            OR (ps.start_time >= ? AND ps.start_time <= ?)
        )
        ORDER BY ps.start_time DESC
    ", [$childId, $today, $today, $todayStart, $todayEnd]);
    
    // Get today's stats 
    $todayStats = $db->selectOne("
        SELECT 
            COUNT(*) as total_activities,
            SUM(xp_earned) as xp_earned,
            SUM(coins_earned) as coins_earned,
            SUM(duration_seconds) / 60 as minutes_played,
            AVG(CASE WHEN activity_type = 'quiz' THEN score END) as avg_quiz_score
        FROM play_sessions
        WHERE childID = ? 
        AND completed = 1
        AND (
            DATE(start_time) = ? 
            OR DATE(end_time) = ?
            OR (start_time >= ? AND start_time <= ?)
        )
    ", [$childId, $today, $today, $todayStart, $todayEnd]);
    
    // Get achievements unlocked today
    $todayAchievements = $db->select("
        SELECT a.title, a.description, a.badge_icon, ca.unlocked_at
        FROM child_achievements ca
        JOIN achievements a ON ca.achievementID = a.achievementID
        WHERE ca.childID = ? AND DATE(ca.unlocked_at) = ?
        ORDER BY ca.unlocked_at DESC
    ", [$childId, $today]);
    
    jsonResponse([
        'success' => true,
        'child' => $child,
        'date' => $today,
        'stats' => $todayStats,
        'activities' => $todayActivities,
        'achievements' => $todayAchievements
    ]);
}


//Get weekly report for child

function handleWeeklyReport() {
    global $db;
    $parentId = getCurrentUserId();
    $childId = intval($_GET['childId'] ?? 0);
    
    // Verify ownership
    $child = $db->selectOne("
        SELECT c.*, u.username
        FROM children c
        JOIN users u ON c.userID = u.userID
        WHERE c.childID = ? AND c.parentID = ?
    ", [$childId, $parentId]);
    
    if (!$child) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    $weekAgo = date('Y-m-d', strtotime('-7 days'));
    
    // Weekly stats
    $weeklyStats = $db->selectOne("
        SELECT 
            COUNT(*) as total_activities,
            SUM(xp_earned) as xp_earned,
            SUM(coins_earned) as coins_earned,
            SUM(duration_seconds) / 60 as minutes_played,
            AVG(CASE WHEN activity_type = 'quiz' THEN score END) as avg_quiz_score
        FROM play_sessions
        WHERE childID = ? AND start_time >= ? AND completed = 1
    ", [$childId, $weekAgo]);
    
    // New achievements this week
    $newAchievements = $db->select("
        SELECT a.title, a.description, ca.unlocked_at
        FROM child_achievements ca
        JOIN achievements a ON ca.achievementID = a.achievementID
        WHERE ca.childID = ? AND ca.unlocked_at >= ?
    ", [$childId, $weekAgo]);
    
    // Top activities
    $topActivities = $db->select("
        SELECT activity_type, COUNT(*) as count
        FROM play_sessions
        WHERE childID = ? AND start_time >= ? AND completed = 1
        GROUP BY activity_type
        ORDER BY count DESC
    ", [$childId, $weekAgo]);
    
    jsonResponse([
        'success' => true,
        'child' => $child,
        'period' => 'Last 7 Days',
        'stats' => $weeklyStats,
        'newAchievements' => $newAchievements,
        'topActivities' => $topActivities
    ]);
}


//Export report as JSON (can be extended for PDF)

function handleExportReport() {
    handleWeeklyReport(); 
}

//Set learning goal for child

function handleSetGoal() {
    global $db;
    $parentId = getCurrentUserId();
    $input = json_decode(file_get_contents('php://input'), true);
    
    $childId = intval($input['childId'] ?? 0);
    $goalType = sanitizeInput($input['goalType'] ?? '');
    $goalDescription = sanitizeInput($input['goalDescription'] ?? '');
    $targetValue = intval($input['targetValue'] ?? 0);
    $endDate = $input['endDate'] ?? date('Y-m-d', strtotime('+30 days'));
    
    // Verify ownership
    $child = $db->selectOne("
        SELECT childID FROM children WHERE childID = ? AND parentID = ?
    ", [$childId, $parentId]);
    
    if (!$child) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    // Create learning_goals table if it doesn't exist 
    $db->query("
        CREATE TABLE IF NOT EXISTS learning_goals (
            goalID INT AUTO_INCREMENT PRIMARY KEY,
            childID INT NOT NULL,
            parentID INT NOT NULL,
            goal_type VARCHAR(50),
            goal_description TEXT,
            target_value INT,
            current_progress INT DEFAULT 0,
            start_date DATE,
            end_date DATE,
            status ENUM('active', 'completed', 'expired') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (childID) REFERENCES children(childID) ON DELETE CASCADE,
            FOREIGN KEY (parentID) REFERENCES users(userID) ON DELETE CASCADE,
            INDEX idx_child (childID),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", []);
    
    // Get next goalID 
    $maxGoal = $db->selectOne("SELECT MAX(goalID) as max_id FROM learning_goals");
    $goalId = ($maxGoal['max_id'] ?? 0) + 1;
    
    // Insert goal with status='active'
    $result = $db->insert("
        INSERT INTO learning_goals 
        (goalID, childID, parentID, goal_type, goal_description, target_value, start_date, end_date, status)
        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, 'active')
    ", [$goalId, $childId, $parentId, $goalType, $goalDescription, $targetValue, $endDate]);
    
    if (!$result) {
        jsonResponse(['success' => false, 'message' => 'Failed to create goal'], 500);
    }
    
    // Create notification for child 

    
    jsonResponse([
        'success' => true,
        'goalId' => $goalId,
        'message' => 'Goal created successfully!'
    ]);
}

//Get goals for child

function handleGetGoals() {
    global $db;
    $parentId = getCurrentUserId();
    $childId = intval($_GET['childId'] ?? 0);
    
    // Verify ownership
    $child = $db->selectOne("
        SELECT childID FROM children WHERE childID = ? AND parentID = ?
    ", [$childId, $parentId]);
    
    if (!$child) {
        jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
    }
    
    // Create table if it doesn't exist
    $db->query("
        CREATE TABLE IF NOT EXISTS learning_goals (
            goalID INT AUTO_INCREMENT PRIMARY KEY,
            childID INT NOT NULL,
            parentID INT NOT NULL,
            goal_type VARCHAR(50) NOT NULL,
            goal_description VARCHAR(255) NOT NULL,
            target_value INT NOT NULL,
            current_progress INT DEFAULT 0,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            is_completed BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (childID) REFERENCES children(childID) ON DELETE CASCADE,
            FOREIGN KEY (parentID) REFERENCES users(userID) ON DELETE CASCADE,
            INDEX idx_child (childID),
            INDEX idx_parent (parentID),
            INDEX idx_dates (start_date, end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", []);
    
    $goals = $db->select("
        SELECT * FROM learning_goals
        WHERE childID = ?
        ORDER BY created_at DESC
    ", [$childId]);
    
    jsonResponse([
        'success' => true,
        'goals' => $goals
    ]);
}

// Get parent notifications

function handleNotifications() {
    global $db;
    $parentId = getCurrentUserId();
    $limit = intval($_GET['limit'] ?? 20);
    
    $notifications = $db->select("
        SELECT pn.*, c.display_name, c.avatar
        FROM parent_notifications pn
        LEFT JOIN children c ON pn.childID = c.childID
        WHERE pn.parentID = ?
        ORDER BY pn.created_at DESC
        LIMIT ?
    ", [$parentId, $limit]);
    
    jsonResponse([
        'success' => true,
        'notifications' => $notifications
    ]);
}

// Mark notification as read

function handleMarkNotificationRead() {
    global $db;
    $parentId = getCurrentUserId();
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = intval($input['notificationId'] ?? 0);
    
    $db->update("
        UPDATE parent_notifications
        SET is_read = 1
        WHERE notificationID = ? AND parentID = ?
    ", [$notificationId, $parentId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Notification marked as read'
    ]);
}

$db = getDB();

// Update parent display name

function handleUpdateDisplayName() {
    global $db;
    $parentId = getCurrentUserId();
    $input = json_decode(file_get_contents('php://input'), true);
    $displayName = sanitizeInput($input['displayName'] ?? '');
    
    if (empty($displayName)) {
        jsonResponse(['success' => false, 'message' => 'Display name is required'], 400);
    }
    
    // Update display name in users table 
    
    jsonResponse([
        'success' => true,
        'message' => 'Display name updated successfully'
    ]);
}

// Update parent password

function handleUpdatePassword() {
    global $db;
    $parentId = getCurrentUserId();
    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = $input['currentPassword'] ?? '';
    $newPassword = $input['newPassword'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword)) {
        jsonResponse(['success' => false, 'message' => 'Current and new passwords are required'], 400);
    }
    
    if (strlen($newPassword) < 6) {
        jsonResponse(['success' => false, 'message' => 'New password must be at least 6 characters'], 400);
    }
    
    // Get current user
    $user = $db->selectOne("SELECT password FROM users WHERE userID = ?", [$parentId]);
    
    if (!$user) {
        jsonResponse(['success' => false, 'message' => 'User not found'], 404);
    }
    
    // Verify current password
    if (!verifyPassword($currentPassword, $user['password'])) {
        jsonResponse(['success' => false, 'message' => 'Current password is incorrect'], 401);
    }
    
    // Hash new password
    $hashedPassword = hashPassword($newPassword);
    
    // Update password
    $updated = $db->update("UPDATE users SET password = ? WHERE userID = ?", [$hashedPassword, $parentId]);
    
    if ($updated !== false) {
        jsonResponse([
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to update password'], 500);
    }
}
?>
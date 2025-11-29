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
    case 'stats':
        $childId = getCurrentChildId();
        $stats = $db->selectOne("SELECT * FROM vw_child_progress WHERE childID = ?", [$childId]);
        
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
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
?>

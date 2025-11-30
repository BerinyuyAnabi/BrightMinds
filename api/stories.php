<?php
/**
 * Bright Minds - Stories API
 */

require_once '../includes/config.php';
header('Content-Type: application/json');

$db = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $stories = $db->select("SELECT * FROM stories WHERE is_active = 1 ORDER BY storyID");
        jsonResponse(['success' => true, 'stories' => $stories]);
        break;
        
    case 'get':
        $storyId = intval($_GET['id'] ?? 0);
        $story = $db->selectOne("SELECT * FROM stories WHERE storyID = ?", [$storyId]);
        if (!$story) jsonResponse(['success' => false, 'message' => 'Story not found'], 404);
        jsonResponse(['success' => true, 'story' => $story]);
        break;
        
    case 'complete':
        requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        $storyId = intval($input['storyId'] ?? 0);
        $childId = getCurrentChildId();
        
        $story = $db->selectOne("SELECT xp_reward, coin_reward FROM stories WHERE storyID = ?", [$storyId]);
        
        $sessionId = $db->insert("
            INSERT INTO play_sessions 
            (childID, activity_type, activity_id, start_time, end_time, duration_seconds, xp_earned, coins_earned, completed)
            VALUES (?, 'story', ?, NOW() - INTERVAL 60 SECOND, NOW(), 60, ?, ?, 1)
        ", [$childId, $storyId, $story['xp_reward'], $story['coin_reward']]);
        
        award_xp($childId, $story['xp_reward'], $story['coin_reward']);
        
        $child = $db->selectOne("SELECT total_xp, current_level, coins FROM children WHERE childID = ?", [$childId]);
        
        jsonResponse([
            'success' => true,
            'rewards' => ['xp' => $story['xp_reward'], 'coins' => $story['coin_reward']],
            'stats' => $child
        ]);
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}
?>

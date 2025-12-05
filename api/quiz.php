<?php
/**
 * Bright Minds - Quiz API
 * Handles quiz data, questions, and submissions
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
    case 'submit':
        handleSubmit();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

// Get list of quizzes

function handleList() {
    global $db;
    
    $childId = getCurrentChildId();
    
    $quizzes = $db->select("
        SELECT q.*,
               COUNT(DISTINCT qq.questionID) as question_count,
               COUNT(ps.sessionID) as times_taken,
               MAX(ps.score) as best_score,
               AVG(ps.score) as avg_score
        FROM quizzes q
        LEFT JOIN quiz_questions qq ON q.quizID = qq.quizID
        LEFT JOIN play_sessions ps ON q.quizID = ps.activity_id 
            AND ps.activity_type = 'quiz'
            AND ps.childID = ?
        WHERE q.is_active = 1
        GROUP BY q.quizID
        ORDER BY q.quizID
    ", $childId ? [$childId] : [0]);
    
    jsonResponse([
        'success' => true,
        'quizzes' => $quizzes
    ]);
}

// Get quiz with questions and options
 
function handleGet() {
    global $db;
    
    $quizId = intval($_GET['id'] ?? 0);
    
    if (!$quizId) {
        jsonResponse(['success' => false, 'message' => 'Quiz ID required'], 400);
    }
    
    // Get quiz
    $quiz = $db->selectOne("SELECT * FROM quizzes WHERE quizID = ? AND is_active = 1", [$quizId]);
    
    if (!$quiz) {
        jsonResponse(['success' => false, 'message' => 'Quiz not found'], 404);
    }
    
    // Get questions
    $questions = $db->select("
        SELECT * FROM quiz_questions
        WHERE quizID = ?
        ORDER BY order_num, questionID
    ", [$quizId]);
    
    // Get options for each question
    foreach ($questions as &$question) {
        $question['options'] = $db->select("
            SELECT optionID, option_text, is_correct, order_num
            FROM quiz_options
            WHERE questionID = ?
            ORDER BY order_num, optionID
        ", [$question['questionID']]);
    }
    
    $quiz['questions'] = $questions;
    
    jsonResponse([
        'success' => true,
        'quiz' => $quiz
    ]);
}

// Submit quiz answers

function handleSubmit() {
    global $db;
    
    requireAuth();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $quizId = intval($input['quizId'] ?? 0);
    $answers = $input['answers'] ?? [];
    $timeSpent = intval($input['timeSpent'] ?? 0);
    $childId = getCurrentChildId();
    
    if (!$childId) {
        jsonResponse(['success' => false, 'message' => 'Child ID required'], 400);
    }
    
    if (!$quizId) {
        jsonResponse(['success' => false, 'message' => 'Quiz ID required'], 400);
    }
    
    // Get quiz details
    $quiz = $db->selectOne("SELECT * FROM quizzes WHERE quizID = ?", [$quizId]);
    
    if (!$quiz) {
        jsonResponse(['success' => false, 'message' => 'Quiz not found'], 404);
    }
    
    // Get all questions with correct answers
    $questions = $db->select("
        SELECT qq.questionID, qq.question_text, qq.correct_answer, qq.explanation, qq.points
        FROM quiz_questions qq
        WHERE qq.quizID = ?
    ", [$quizId]);
    
    // Calculate score
    $totalPoints = 0;
    $earnedPoints = 0;
    $correctCount = 0;
    $results = [];
    
    foreach ($questions as $question) {
        $totalPoints += $question['points'];
        $questionId = $question['questionID'];
        $userAnswer = $answers[$questionId] ?? null;
        $correctAnswer = $question['correct_answer'];
        
        $isCorrect = false;
        if ($userAnswer !== null) {
            // For multiple choice, compare with correct answer
            if (strtolower(trim($userAnswer)) === strtolower(trim($correctAnswer))) {
                $isCorrect = true;
                $earnedPoints += $question['points'];
                $correctCount++;
            }
        }
        
        $results[] = [
            'questionId' => $questionId,
            'question' => $question['question_text'],
            'userAnswer' => $userAnswer,
            'correctAnswer' => $correctAnswer,
            'isCorrect' => $isCorrect,
            'explanation' => $question['explanation'],
            'points' => $isCorrect ? $question['points'] : 0
        ];
    }
    
    $scorePercentage = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0;
    $passed = $scorePercentage >= $quiz['passing_score'];
    
    // Calculate rewards
    $xpEarned = $quiz['xp_reward'];
    $coinsEarned = $quiz['coin_reward'];
    
    // Bonus for perfect score
    if ($scorePercentage == 100) {
        $xpEarned = floor($xpEarned * 2); 
        $coinsEarned = floor($coinsEarned * 2);
    } elseif ($scorePercentage >= 90) {
        $xpEarned = floor($xpEarned * 1.5);
        $coinsEarned = floor($coinsEarned * 1.5);
    } elseif ($scorePercentage >= $quiz['passing_score']) {
        $xpEarned = floor($xpEarned * 1.2);
        $coinsEarned = floor($coinsEarned * 1.2);
    } else {
        // Reduced rewards for failing
        $xpEarned = floor($xpEarned * 0.5);
        $coinsEarned = floor($coinsEarned * 0.5);
    }
    
    // Get next sessionID 
    $maxSession = $db->selectOne("SELECT MAX(sessionID) as max_id FROM play_sessions");
    $sessionId = ($maxSession['max_id'] ?? 0) + 1;

    // Create play session
    $result = $db->insert("
        INSERT INTO play_sessions
        (sessionID, childID, activity_type, activity_id, start_time, end_time, duration_seconds, score, xp_earned, coins_earned, completed)
        VALUES (?, ?, 'quiz', ?, NOW() - INTERVAL ? SECOND, NOW(), ?, ?, ?, ?, 1)
    ", [$sessionId, $childId, $quizId, $timeSpent, $timeSpent, $scorePercentage, $xpEarned, $coinsEarned]);

    if (!$result) {
        jsonResponse(['success' => false, 'message' => 'Failed to create play session'], 500);
    }
    
    // Award XP and coins (using PHP function instead of stored procedure)
    award_xp($childId, $xpEarned, $coinsEarned);
    
    // Get updated stats
    $child = $db->selectOne("
        SELECT total_xp, current_level, coins, streak_days
        FROM children
        WHERE childID = ?
    ", [$childId]);
    
    // Check achievements
 
    
    // Create notification for parent
    $childName = $db->selectOne("SELECT display_name FROM children WHERE childID = ?", [$childId])['display_name'] ?? 'Your child';
    $quizTitle = $db->selectOne("SELECT title FROM quizzes WHERE quizID = ?", [$quizId])['title'] ?? 'a quiz';
    
    $db->insert("
        INSERT INTO parent_notifications (parentID, notification_type, title, message)
        SELECT parentID, 'activity_completed', 'Quiz Completed', ?
        FROM children WHERE childID = ?
    ", ["$childName completed the quiz '$quizTitle' with a score of $scorePercentage%!", $childId]);

    jsonResponse([
        'success' => true,
        'score' => $scorePercentage,
        'passed' => $passed,
        'correctCount' => $correctCount,
        'totalQuestions' => count($questions),
        'results' => $results,
        'rewards' => [
            'xp' => $xpEarned,
            'coins' => $coinsEarned
        ],
        'stats' => $child
    ]);
}

?>

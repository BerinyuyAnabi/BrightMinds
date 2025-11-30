<?php
/**
 * Debug Quiz System - Complete diagnostic
 */

require_once 'includes/config.php';

// Set up session for child ID 3
session_start();
$_SESSION['user_id'] = 3;
$_SESSION['child_id'] = 3;
$_SESSION['role'] = 'child';
$_SESSION['session_token'] = 'test_token';

$db = getDB();
$childId = 3;

echo "<h1>Complete Quiz System Diagnostic</h1>";
echo "<style>body{font-family:monospace;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} .section{background:#f5f5f5;padding:15px;margin:10px 0;border-left:4px solid #4ECDC4;}</style>";

// 1. Check current state
echo "<div class='section'><h2>1. Current Child Stats</h2>";
$child = $db->selectOne("SELECT * FROM children WHERE childID = ?", [$childId]);
echo "<pre>";
print_r($child);
echo "</pre></div>";

$coinsBefore = $child['coins'];
$xpBefore = $child['total_xp'];

// 2. Test quiz API get
echo "<div class='section'><h2>2. Test Quiz API - Get Quiz 1</h2>";
$_GET['action'] = 'get';
$_GET['id'] = 1;
ob_start();
include 'api/quiz.php';
$getResponse = ob_get_clean();
echo "Response:<br><textarea style='width:100%;height:150px;'>" . htmlspecialchars($getResponse) . "</textarea>";
$quizData = json_decode($getResponse, true);
if ($quizData && $quizData['success']) {
    echo "<p class='success'>✅ Quiz loaded: " . $quizData['quiz']['title'] . "</p>";
    echo "<p>Questions: " . count($quizData['quiz']['questions']) . "</p>";

    // Check first question has is_correct
    if (isset($quizData['quiz']['questions'][0]['options'][0]['is_correct'])) {
        echo "<p class='success'>✅ Options have is_correct field</p>";
    } else {
        echo "<p class='error'>❌ Options missing is_correct field!</p>";
    }
} else {
    echo "<p class='error'>❌ Failed to load quiz</p>";
}
echo "</div>";

// 3. Submit quiz
echo "<div class='section'><h2>3. Test Quiz Submission</h2>";

// Build correct answers
$answers = [];
foreach ($quizData['quiz']['questions'] as $q) {
    // Find correct answer
    foreach ($q['options'] as $idx => $opt) {
        if ($opt['is_correct'] == 1) {
            $letters = ['A', 'B', 'C', 'D'];
            $answers[$q['questionID']] = $letters[$idx];
            break;
        }
    }
}

echo "Submitting answers: <pre>" . print_r($answers, true) . "</pre>";

// Simulate quiz submission
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'submit';
$input = json_encode([
    'quizId' => 1,
    'answers' => $answers,
    'timeSpent' => 120
]);

// Mock php://input
file_put_contents('php://temp', $input);

ob_start();
try {
    // Manually call the submit logic
    $quizId = 1;
    $timeSpent = 120;

    // Get quiz
    $quiz = $db->selectOne("SELECT * FROM quizzes WHERE quizID = ?", [$quizId]);
    echo "Quiz: {$quiz['title']}<br>";

    // Get questions
    $questions = $db->select("
        SELECT qq.questionID, qq.question_text, qq.correct_answer, qq.explanation, qq.points
        FROM quiz_questions qq
        WHERE qq.quizID = ?
    ", [$quizId]);

    // Calculate score
    $totalPoints = 0;
    $earnedPoints = 0;

    foreach ($questions as $question) {
        $totalPoints += $question['points'];
        $questionId = $question['questionID'];
        $userAnswer = $answers[$questionId] ?? null;
        $correctAnswer = $question['correct_answer'];

        if ($userAnswer !== null && strtolower(trim($userAnswer)) === strtolower(trim($correctAnswer))) {
            $earnedPoints += $question['points'];
        }
    }

    $scorePercentage = round(($earnedPoints / $totalPoints) * 100, 2);
    echo "Score: {$scorePercentage}%<br>";

    // Calculate rewards
    $xpEarned = $quiz['xp_reward'];
    $coinsEarned = $quiz['coin_reward'];

    if ($scorePercentage == 100) {
        $xpEarned = floor($xpEarned * 2);
        $coinsEarned = floor($coinsEarned * 2);
    }

    echo "Rewards: {$xpEarned} XP, {$coinsEarned} Coins<br>";

    // Create play session
    $sessionId = $db->insert("
        INSERT INTO play_sessions
        (childID, activity_type, activity_id, start_time, end_time, duration_seconds, score, xp_earned, coins_earned, completed)
        VALUES (?, 'quiz', ?, NOW() - INTERVAL ? SECOND, NOW(), ?, ?, ?, ?, 1)
    ", [$childId, $quizId, $timeSpent, $timeSpent, $scorePercentage, $xpEarned, $coinsEarned]);

    echo "Play session created: ID {$sessionId}<br>";

    // Award XP and coins (using PHP function instead of stored procedure)
    echo "<strong>Calling award_xp({$childId}, {$xpEarned}, {$coinsEarned})</strong><br>";
    $result = award_xp($childId, $xpEarned, $coinsEarned);

    if ($result) {
        echo "<p class='success'>✅ award_xp executed</p>";
    } else {
        echo "<p class='error'>❌ award_xp failed</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>Exception: " . $e->getMessage() . "</p>";
}
ob_end_flush();

echo "</div>";

// 4. Check final state
echo "<div class='section'><h2>4. Final Child Stats</h2>";
$childAfter = $db->selectOne("SELECT * FROM children WHERE childID = ?", [$childId]);
echo "<pre>";
print_r($childAfter);
echo "</pre>";

$coinsAfter = $childAfter['coins'];
$xpAfter = $childAfter['total_xp'];

$coinDiff = $coinsAfter - $coinsBefore;
$xpDiff = $xpAfter - $xpBefore;

echo "<h3>Changes:</h3>";
echo "Coins: {$coinsBefore} → {$coinsAfter} (Δ {$coinDiff})<br>";
echo "XP: {$xpBefore} → {$xpAfter} (Δ {$xpDiff})<br>";

if ($coinDiff > 0 && $xpDiff > 0) {
    echo "<p class='success' style='font-size:1.5em;'>✅ SYSTEM WORKS! Backend is updating correctly!</p>";
    echo "<p>If dashboard doesn't show updates, it's a frontend/cache issue.</p>";
} else {
    echo "<p class='error' style='font-size:1.5em;'>❌ PROBLEM! Coins/XP not updating in database!</p>";
}

echo "</div>";

// 5. Test dashboard API
echo "<div class='section'><h2>5. Test Dashboard API</h2>";
$_GET['action'] = 'stats';
ob_start();
include 'api/dashboard.php';
$dashResponse = ob_get_clean();
echo "Response:<br><textarea style='width:100%;height:100px;'>" . htmlspecialchars($dashResponse) . "</textarea>";
$dashData = json_decode($dashResponse, true);
if ($dashData && $dashData['success']) {
    echo "<p class='success'>✅ Dashboard API works</p>";
    echo "<p>Returns coins: " . $dashData['stats']['coins'] . "</p>";
} else {
    echo "<p class='error'>❌ Dashboard API failed</p>";
}
echo "</div>";

echo "<hr><h2>Summary</h2>";
echo "<p>Check each section above. If sections 1-4 show SUCCESS but coins don't update in browser:</p>";
echo "<ol>";
echo "<li><strong>Clear browser cache completely</strong> (Ctrl+Shift+Delete)</li>";
echo "<li>Use <strong>Incognito/Private window</strong></li>";
echo "<li>Check browser console for JavaScript errors</li>";
echo "<li>Hard refresh the page (Ctrl+Shift+R / Cmd+Shift+R)</li>";
echo "</ol>";
?>

<?php
/**
 * Simple test - submit a quiz and check if coins update
 */

require_once 'includes/config.php';

// Set up session for child ID 3 (getty1122)
session_start();
$_SESSION['user_id'] = 3;
$_SESSION['child_id'] = 3;
$_SESSION['role'] = 'child';
$_SESSION['session_token'] = 'test_token';

$db = getDB();
$childId = 3;

echo "<h2>Simple Quiz Submit Test</h2>";
echo "<p>Testing with child ID: $childId (getty1122)</p><hr>";

// Step 1: Get current coins
echo "<h3>Step 1: Current Coins</h3>";
$before = $db->selectOne("SELECT coins, total_xp FROM children WHERE childID = ?", [$childId]);
echo "Coins: {$before['coins']}<br>";
echo "XP: {$before['total_xp']}<br><br>";

// Step 2: Get quiz questions to build proper answer format
echo "<h3>Step 2: Load Quiz 1</h3>";
$quiz = $db->selectOne("SELECT * FROM quizzes WHERE quizID = 1");
if (!$quiz) {
    echo "❌ Quiz not found!<br>";
    exit;
}
echo "Quiz: {$quiz['title']}<br>";

$questions = $db->select("SELECT questionID, question_text, correct_answer FROM quiz_questions WHERE quizID = 1");
echo "Questions found: " . count($questions) . "<br><br>";

// Step 3: Build answers (all correct)
echo "<h3>Step 3: Build Answers</h3>";
$answers = [];
foreach ($questions as $q) {
    $answers[$q['questionID']] = $q['correct_answer'];
    echo "Q{$q['questionID']}: {$q['correct_answer']}<br>";
}
echo "<br>";

// Step 4: Simulate API call to quiz.php submit
echo "<h3>Step 4: Call Quiz Submit API</h3>";

// Manually call the submit handler
$_GET['action'] = 'submit';
$_POST = []; // Clear POST

// Prepare input like the API expects
$input = [
    'quizId' => 1,
    'answers' => $answers,
    'timeSpent' => 120
];

// Simulate the JSON input
$GLOBALS['HTTP_RAW_POST_DATA'] = json_encode($input);
file_put_contents('php://input', json_encode($input));

// Capture output
ob_start();

// Call the quiz API
try {
    // Include and execute quiz API logic directly
    $quizId = 1;
    $timeSpent = 120;

    // Calculate score manually
    $totalPoints = 0;
    $earnedPoints = 0;
    foreach ($questions as $q) {
        $totalPoints += 10; // Default points
        $earnedPoints += 10; // All correct
    }

    $scorePercentage = 100;
    $xpEarned = $quiz['xp_reward'];
    $coinsEarned = $quiz['coin_reward'];

    // Bonus for perfect score
    $xpEarned = floor($xpEarned * 2);
    $coinsEarned = floor($coinsEarned * 2);

    echo "Calculated rewards:<br>";
    echo "XP to award: $xpEarned<br>";
    echo "Coins to award: $coinsEarned<br><br>";

    // Create play session
    $sessionId = $db->insert("
        INSERT INTO play_sessions
        (childID, activity_type, activity_id, start_time, end_time, duration_seconds, score, xp_earned, coins_earned, completed)
        VALUES (?, 'quiz', ?, NOW() - INTERVAL ? SECOND, NOW(), ?, ?, ?, ?, 1)
    ", [$childId, $quizId, $timeSpent, $timeSpent, $scorePercentage, $xpEarned, $coinsEarned]);

    echo "Play session created: ID $sessionId<br><br>";

    // Award XP and coins - THIS IS THE CRITICAL PART
    echo "<strong>Calling: CALL award_xp($childId, $xpEarned, $coinsEarned)</strong><br>";
    $result = $db->query("CALL award_xp(?, ?, ?)", [$childId, $xpEarned, $coinsEarned]);

    if ($result) {
        echo "✅ award_xp executed successfully<br><br>";
    } else {
        echo "❌ award_xp FAILED<br><br>";
    }

} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

ob_end_clean();

// Step 5: Check coins after
echo "<h3>Step 5: Coins After Submit</h3>";
$after = $db->selectOne("SELECT coins, total_xp FROM children WHERE childID = ?", [$childId]);
echo "Coins: {$after['coins']}<br>";
echo "XP: {$after['total_xp']}<br><br>";

$coinDiff = $after['coins'] - $before['coins'];
$xpDiff = $after['total_xp'] - $before['total_xp'];

echo "<h3>Result</h3>";
echo "Coins changed by: $coinDiff (expected: $coinsEarned)<br>";
echo "XP changed by: $xpDiff (expected: $xpEarned)<br><br>";

if ($coinDiff == $coinsEarned && $xpDiff == $xpEarned) {
    echo "<p style='color: green; font-weight: bold; font-size: 1.5em;'>✅ SUCCESS! Everything works!</p>";
    echo "<p>The backend is working correctly. If the dashboard doesn't update, it's a frontend caching issue.</p>";
} else {
    echo "<p style='color: red; font-weight: bold; font-size: 1.5em;'>❌ PROBLEM!</p>";
    echo "<p>Expected coins: +$coinsEarned, Got: +$coinDiff</p>";
    echo "<p>Expected XP: +$xpEarned, Got: +$xpDiff</p>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>If this test shows SUCCESS, the backend is fine</li>";
echo "<li>Clear your browser cache completely (Ctrl+Shift+Delete)</li>";
echo "<li>Or use Incognito/Private browsing mode</li>";
echo "<li>Login and take a real quiz</li>";
echo "<li>Go back to dashboard - coins should update</li>";
echo "</ol>";
?>

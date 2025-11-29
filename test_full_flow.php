<?php
/**
 * Test the complete flow from quiz submission to database update
 */

require_once 'includes/config.php';

echo "<h2>Complete Coin Flow Test</h2>";
echo "<p>This will test the entire process from quiz completion to coin display.</p><hr>";

$db = getDB();
$childId = 3; // getty1122

echo "<h3>Step 1: Check Current Coins in Database</h3>";
$before = $db->selectOne("SELECT childID, display_name, coins, total_xp FROM children WHERE childID = ?", [$childId]);
echo "<pre>";
print_r($before);
echo "</pre>";
$coinsBefore = $before['coins'];
echo "<strong>Current Coins: $coinsBefore</strong><br><br>";

echo "<h3>Step 2: Simulate Quiz Completion (via API)</h3>";
// Create a fake session for API call
session_start();
$_SESSION['user_id'] = 3;
$_SESSION['child_id'] = 3;
$_SESSION['role'] = 'child';
$_SESSION['session_token'] = 'test_token';

echo "Simulating quiz submission with 15 coins reward...<br>";

// Simulate the exact quiz submission process
$quizId = 1;
$xpEarned = 30;
$coinsEarned = 15;
$scorePercentage = 85;
$timeSpent = 120;

// Step 2a: Create play session (like quiz.php does)
$sessionId = $db->insert("
    INSERT INTO play_sessions
    (childID, activity_type, activity_id, start_time, end_time, duration_seconds, score, xp_earned, coins_earned, completed)
    VALUES (?, 'quiz', ?, NOW() - INTERVAL ? SECOND, NOW(), ?, ?, ?, ?, 1)
", [$childId, $quizId, $timeSpent, $timeSpent, $scorePercentage, $xpEarned, $coinsEarned]);

echo "✅ Play session created (ID: $sessionId)<br>";

// Step 2b: Call award_xp stored procedure (like quiz.php does)
echo "Calling: CALL award_xp($childId, $xpEarned, $coinsEarned)<br>";
$result = $db->query("CALL award_xp(?, ?, ?)", [$childId, $xpEarned, $coinsEarned]);

if ($result) {
    echo "✅ award_xp procedure executed<br><br>";
} else {
    echo "❌ award_xp procedure FAILED<br><br>";
}

echo "<h3>Step 3: Check Database After Update</h3>";
$after = $db->selectOne("SELECT childID, display_name, coins, total_xp FROM children WHERE childID = ?", [$childId]);
echo "<pre>";
print_r($after);
echo "</pre>";
$coinsAfter = $after['coins'];
$coinDiff = $coinsAfter - $coinsBefore;
echo "<strong>Coins After: $coinsAfter (Changed by: $coinDiff)</strong><br><br>";

if ($coinDiff == $coinsEarned) {
    echo "<p style='color: green; font-weight: bold;'>✅ Database Update: SUCCESS!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Database Update: FAILED!</p>";
    echo "Expected change: +$coinsEarned, Actual change: +$coinDiff<br>";
}

echo "<hr><h3>Step 4: Test API Stats Endpoint</h3>";
echo "Testing: api/dashboard.php?action=stats<br><br>";

// Manually call the API endpoint
$_GET['action'] = 'stats';
ob_start();
include 'api/dashboard.php';
$apiResponse = ob_get_clean();

echo "API Response:<br>";
echo "<pre>";
echo htmlspecialchars($apiResponse);
echo "</pre>";

$apiData = json_decode($apiResponse, true);
if ($apiData && $apiData['success']) {
    echo "<strong>Coins from API: " . $apiData['stats']['coins'] . "</strong><br>";
    if ($apiData['stats']['coins'] == $coinsAfter) {
        echo "<p style='color: green; font-weight: bold;'>✅ API Returns Correct Coins!</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ API Returns Wrong Coins!</p>";
        echo "Database has: $coinsAfter, API returned: " . $apiData['stats']['coins'] . "<br>";
    }
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ API Failed!</p>";
}

echo "<hr><h3>Summary</h3>";
echo "<ol>";
echo "<li>Backend (Database & Stored Procedure): " . ($coinDiff == $coinsEarned ? "✅ WORKING" : "❌ BROKEN") . "</li>";
echo "<li>API Endpoint: " . ($apiData && $apiData['success'] ? "✅ WORKING" : "❌ BROKEN") . "</li>";
echo "<li>Coins Value Match: " . ($apiData && $apiData['stats']['coins'] == $coinsAfter ? "✅ MATCHING" : "❌ MISMATCH") . "</li>";
echo "</ol>";

echo "<br><strong>Next Step:</strong> Open the browser console on the dashboard and check if JavaScript is calling the API and updating the display correctly.";
?>

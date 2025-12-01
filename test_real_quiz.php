<?php
/**
 * Simulate a real quiz submission to test the full flow
 */

require_once 'includes/config.php';

// Start session and simulate logged-in child
session_start();
$_SESSION['user_id'] = 3;  // User ID for getty1122
$_SESSION['child_id'] = 3;  // Child ID for getty1122
$_SESSION['role'] = 'child';
$_SESSION['session_token'] = 'test_token';

echo "<h2>Simulating Real Quiz Submission</h2>";

$db = getDB();
$childId = 3;

echo "<h3>Before Quiz:</h3>";
$before = $db->selectOne("SELECT childID, display_name, coins, total_xp FROM children WHERE childID = ?", [$childId]);
echo "Coins: {$before['coins']}, XP: {$before['total_xp']}<br><br>";

// Simulate quiz submission
$quizId = 1;
$xpEarned = 30;
$coinsEarned = 15;
$scorePercentage = 85;
$timeSpent = 120;

echo "<h3>Step 1: Creating play session...</h3>";
$sessionId = $db->insert("
    INSERT INTO play_sessions
    (childID, activity_type, activity_id, start_time, end_time, duration_seconds, score, xp_earned, coins_earned, completed)
    VALUES (?, 'quiz', ?, NOW() - INTERVAL ? SECOND, NOW(), ?, ?, ?, ?, 1)
", [$childId, $quizId, $timeSpent, $timeSpent, $scorePercentage, $xpEarned, $coinsEarned]);

echo "✅ Session ID: $sessionId<br><br>";

echo "<h3>Step 2: Calling award_xp stored procedure...</h3>";
echo "Parameters: childID=$childId, xp=$xpEarned, coins=$coinsEarned<br>";

try {
    $result = $db->query("CALL award_xp(?, ?, ?)", [$childId, $xpEarned, $coinsEarned]);

    if ($result) {
        echo "✅ Procedure called successfully<br>";

        // IMPORTANT: Close the statement to clear results
        $result->close();

        // Clear any remaining results (this is often the issue with stored procedures)
        $conn = $db->getConnection();
        while ($conn->more_results()) {
            $conn->next_result();
        }

    } else {
        echo "❌ Procedure call failed<br>";
        $conn = $db->getConnection();
        echo "Error: " . $conn->error . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

echo "<br><h3>After Quiz:</h3>";
$after = $db->selectOne("SELECT childID, display_name, coins, total_xp FROM children WHERE childID = ?", [$childId]);
echo "Coins: {$after['coins']}, XP: {$after['total_xp']}<br><br>";

echo "<h3>Result:</h3>";
$coinDiff = $after['coins'] - $before['coins'];
$xpDiff = $after['total_xp'] - $before['total_xp'];

if ($coinDiff == $coinsEarned && $xpDiff == $xpEarned) {
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS! Coins and XP updated correctly!</p>";
    echo "Coins: {$before['coins']} → {$after['coins']} (+$coinDiff)<br>";
    echo "XP: {$before['total_xp']} → {$after['total_xp']} (+$xpDiff)<br>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ PROBLEM FOUND!</p>";
    echo "Expected coins: +$coinsEarned, Got: +$coinDiff<br>";
    echo "Expected XP: +$xpEarned, Got: +$xpDiff<br>";
    echo "<br><strong>The stored procedure is not being executed properly in the normal flow.</strong>";
}
?>

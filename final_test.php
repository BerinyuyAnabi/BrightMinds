<?php
/**
 * Final Complete Test - Simulates a full quiz flow
 */

require_once 'includes/config.php';

// Set up session for child ID 3
$_SESSION['user_id'] = 4;
$_SESSION['child_id'] = 3;
$_SESSION['role'] = 'child';
$_SESSION['session_token'] = 'test_token';

$db = getDB();
$childId = 3;

echo "<h1>Final Complete Test</h1>";
echo "<style>body{font-family:monospace;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;} pre{background:#f5f5f5;padding:10px;}</style>";

echo "<h2>Step 1: Current Stats</h2>";
$before = $db->selectOne("SELECT childID, display_name, coins, total_xp, current_level FROM children WHERE childID = ?", [$childId]);
echo "<pre>";
echo "Name: {$before['display_name']}\n";
echo "Coins: {$before['coins']}\n";
echo "XP: {$before['total_xp']}\n";
echo "Level: {$before['current_level']}\n";
echo "</pre>";

echo "<h2>Step 2: Complete Perfect Quiz</h2>";
// Simulate perfect quiz completion
$quizId = 1;
$quiz = $db->selectOne("SELECT * FROM quizzes WHERE quizID = ?", [$quizId]);
echo "Quiz: {$quiz['title']}<br>";
echo "Base rewards: {$quiz['xp_reward']} XP, {$quiz['coin_reward']} coins<br>";

// Perfect score gets 2x rewards
$xpEarned = floor($quiz['xp_reward'] * 2);
$coinsEarned = floor($quiz['coin_reward'] * 2);
echo "Perfect score rewards: {$xpEarned} XP, {$coinsEarned} coins<br><br>";

// Create play session
$sessionId = $db->insert("
    INSERT INTO play_sessions
    (childID, activity_type, activity_id, start_time, end_time, duration_seconds, score, xp_earned, coins_earned, completed)
    VALUES (?, 'quiz', ?, NOW() - INTERVAL 120 SECOND, NOW(), 120, 100, ?, ?, 1)
", [$childId, $quizId, $xpEarned, $coinsEarned]);

echo "✅ Play session created (ID: {$sessionId})<br><br>";

echo "<h2>Step 3: Award Coins via Stored Procedure</h2>";
echo "Calling: <code>CALL award_xp({$childId}, {$xpEarned}, {$coinsEarned})</code><br>";

$result = $db->query("CALL award_xp(?, ?, ?)", [$childId, $xpEarned, $coinsEarned]);

if ($result) {
    echo "<span class='success'>✅ Stored procedure executed successfully</span><br><br>";
} else {
    echo "<span class='error'>❌ Stored procedure failed</span><br><br>";
}

echo "<h2>Step 4: Check Updated Stats</h2>";
$after = $db->selectOne("SELECT childID, display_name, coins, total_xp, current_level FROM children WHERE childID = ?", [$childId]);
echo "<pre>";
echo "Name: {$after['display_name']}\n";
echo "Coins: {$after['coins']}\n";
echo "XP: {$after['total_xp']}\n";
echo "Level: {$after['current_level']}\n";
echo "</pre>";

$coinDiff = $after['coins'] - $before['coins'];
$xpDiff = $after['total_xp'] - $before['total_xp'];

echo "<h2>Result</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr><th>Metric</th><th>Before</th><th>After</th><th>Change</th><th>Expected</th><th>Status</th></tr>";

echo "<tr>";
echo "<td>Coins</td>";
echo "<td>{$before['coins']}</td>";
echo "<td>{$after['coins']}</td>";
echo "<td>+{$coinDiff}</td>";
echo "<td>+{$coinsEarned}</td>";
echo "<td>" . ($coinDiff == $coinsEarned ? "<span class='success'>✅ PASS</span>" : "<span class='error'>❌ FAIL</span>") . "</td>";
echo "</tr>";

echo "<tr>";
echo "<td>XP</td>";
echo "<td>{$before['total_xp']}</td>";
echo "<td>{$after['total_xp']}</td>";
echo "<td>+{$xpDiff}</td>";
echo "<td>+{$xpEarned}</td>";
echo "<td>" . ($xpDiff == $xpEarned ? "<span class='success'>✅ PASS</span>" : "<span class='error'>❌ FAIL</span>") . "</td>";
echo "</tr>";

echo "</table>";

echo "<hr><h2>Final Verdict</h2>";

if ($coinDiff == $coinsEarned && $xpDiff == $xpEarned) {
    echo "<div style='background:#d4edda;border:2px solid #28a745;padding:20px;border-radius:5px;'>";
    echo "<h3 style='color:#28a745;margin:0;'>✅ BACKEND WORKS PERFECTLY!</h3>";
    echo "<p>The database, stored procedure, and all backend logic is working correctly.</p>";
    echo "<p>Coins are being added to the database.</p>";
    echo "</div>";

    echo "<h3>If dashboard still doesn't update:</h3>";
    echo "<ol>";
    echo "<li><strong>Clear ALL browser data</strong> (Ctrl+Shift+Delete)</li>";
    echo "<li>Use <strong>Incognito/Private browsing mode</strong></li>";
    echo "<li>Open browser DevTools (F12) → Console tab</li>";
    echo "<li>Take a quiz and check for JavaScript errors</li>";
    echo "<li>Check Network tab to see if dashboard API is being called</li>";
    echo "</ol>";

    echo "<h3>Quick Test:</h3>";
    echo "<p>1. Open: <a href='dashboard.php' target='_blank'>Dashboard</a></p>";
    echo "<p>2. Check if coins show: <strong>{$after['coins']}</strong></p>";
    echo "<p>3. If not, it's a browser cache issue - use Incognito mode</p>";

} else {
    echo "<div style='background:#f8d7da;border:2px solid #dc3545;padding:20px;border-radius:5px;'>";
    echo "<h3 style='color:#dc3545;margin:0;'>❌ BACKEND PROBLEM!</h3>";
    echo "<p>The stored procedure is not updating the database correctly.</p>";
    echo "<p>Expected: +{$coinsEarned} coins, Got: +{$coinDiff} coins</p>";
    echo "</div>";
}

echo "<hr><p><small>Current database coins: {$after['coins']}</small></p>";
?>

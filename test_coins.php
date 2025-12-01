<?php
/**
 * Test Coin System
 * This script will test if coins are being awarded correctly
 */

require_once 'includes/config.php';

echo "<h2>Coin System Diagnostic Test</h2>";

$db = getDB();
$conn = $db->getConnection();

echo "<h3>1. Check if database exists:</h3>";
$result = $conn->query("SELECT DATABASE() as db_name");
$row = $result->fetch_assoc();
echo "✅ Connected to database: <strong>" . $row['db_name'] . "</strong><br><br>";

echo "<h3>2. Check if 'children' table has 'coins' column:</h3>";
$result = $conn->query("SHOW COLUMNS FROM children LIKE 'coins'");
if ($result->num_rows > 0) {
    $column = $result->fetch_assoc();
    echo "✅ Column 'coins' exists<br>";
    echo "Type: " . $column['Type'] . "<br>";
    echo "Default: " . $column['Default'] . "<br><br>";
} else {
    echo "❌ Column 'coins' does NOT exist!<br><br>";
}

echo "<h3>3. Check if stored procedure 'award_xp' exists:</h3>";
$result = $conn->query("SHOW PROCEDURE STATUS WHERE Db = 'bright_minds_db' AND Name = 'award_xp'");
if ($result->num_rows > 0) {
    echo "✅ Stored procedure 'award_xp' exists<br><br>";

    // Show the procedure definition
    $result = $conn->query("SHOW CREATE PROCEDURE award_xp");
    if ($result) {
        $proc = $result->fetch_assoc();
        echo "<details><summary>View Procedure Code</summary><pre>";
        echo htmlspecialchars($proc['Create Procedure']);
        echo "</pre></details><br>";
    }
} else {
    echo "❌ Stored procedure 'award_xp' does NOT exist!<br>";
    echo "<strong>This is the problem! The database needs to be set up properly.</strong><br><br>";
}

echo "<h3>4. Check if any children exist:</h3>";
$result = $conn->query("SELECT childID, display_name, coins, total_xp, current_level FROM children LIMIT 5");
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Child ID</th><th>Name</th><th>Coins</th><th>XP</th><th>Level</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['childID'] . "</td>";
        echo "<td>" . $row['display_name'] . "</td>";
        echo "<td>" . $row['coins'] . "</td>";
        echo "<td>" . $row['total_xp'] . "</td>";
        echo "<td>" . $row['current_level'] . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
} else {
    echo "⚠️ No children found in database<br><br>";
}

echo "<h3>5. Check recent play sessions:</h3>";
$result = $conn->query("
    SELECT ps.sessionID, ps.childID, ps.activity_type, ps.score, ps.xp_earned, ps.coins_earned, ps.start_time
    FROM play_sessions ps
    ORDER BY ps.start_time DESC
    LIMIT 5
");
if ($result->num_rows > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Session ID</th><th>Child ID</th><th>Activity</th><th>Score</th><th>XP Earned</th><th>Coins Earned</th><th>Time</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['sessionID'] . "</td>";
        echo "<td>" . $row['childID'] . "</td>";
        echo "<td>" . $row['activity_type'] . "</td>";
        echo "<td>" . $row['score'] . "</td>";
        echo "<td>" . $row['xp_earned'] . "</td>";
        echo "<td>" . $row['coins_earned'] . "</td>";
        echo "<td>" . $row['start_time'] . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
} else {
    echo "⚠️ No play sessions found<br><br>";
}

echo "<hr>";
echo "<h3>Summary:</h3>";
echo "<p>If the stored procedure 'award_xp' does NOT exist, you need to run the database setup SQL file.</p>";
echo "<p>The coins are awarded via this stored procedure when you complete quizzes and games.</p>";
?>

<?php
/**
 * Test the award_xp stored procedure directly
 */

require_once 'includes/config.php';

echo "<h2>Testing award_xp Stored Procedure</h2>";

$db = getDB();

// Test with child ID 3 (getty1122)
$childId = 3;

echo "<h3>Before Test:</h3>";
$before = $db->selectOne("SELECT childID, display_name, coins, total_xp, current_level FROM children WHERE childID = ?", [$childId]);
echo "<pre>";
print_r($before);
echo "</pre>";

echo "<h3>Calling award_xp(childID=$childId, xp=50, coins=25)...</h3>";

try {
    // Try calling the stored procedure
    $stmt = $db->query("CALL award_xp(?, ?, ?)", [$childId, 50, 25]);

    if ($stmt) {
        echo "✅ Stored procedure executed successfully!<br>";
        $stmt->close();
    } else {
        echo "❌ Stored procedure failed to execute<br>";
        $conn = $db->getConnection();
        echo "Error: " . $conn->error . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "<br>";
}

echo "<h3>After Test:</h3>";
$after = $db->selectOne("SELECT childID, display_name, coins, total_xp, current_level FROM children WHERE childID = ?", [$childId]);
echo "<pre>";
print_r($after);
echo "</pre>";

echo "<h3>Results:</h3>";
if ($after['coins'] == $before['coins'] + 25 && $after['total_xp'] == $before['total_xp'] + 50) {
    echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS! Coins and XP were added correctly!</p>";
    echo "<p>Coins: {$before['coins']} → {$after['coins']} (+25)</p>";
    echo "<p>XP: {$before['total_xp']} → {$after['total_xp']} (+50)</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ FAILED! Coins/XP were NOT updated!</p>";
    echo "<p>Expected Coins: " . ($before['coins'] + 25) . ", Got: {$after['coins']}</p>";
    echo "<p>Expected XP: " . ($before['total_xp'] + 50) . ", Got: {$after['total_xp']}</p>";
    echo "<br><strong>This means there's an issue with the stored procedure or how it's being called.</strong>";
}
?>

<?php
/**
 * Database Connection Test Script
 * This will help diagnose the connection issue
 */

// Disable error reporting to prevent fatal errors
mysqli_report(MYSQLI_REPORT_OFF);

echo "<h2>MAMP Database Connection Test</h2>";

// Test different port configurations
$configs = [
    ['host' => 'localhost', 'port' => 3306, 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'port' => 3306, 'user' => 'root', 'pass' => 'root'],
    ['host' => 'localhost', 'port' => 8889, 'user' => 'root', 'pass' => ''],
    ['host' => 'localhost', 'port' => 8889, 'user' => 'root', 'pass' => 'root'],
    ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => ''],
    ['host' => '127.0.0.1', 'port' => 8889, 'user' => 'root', 'pass' => ''],
];

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>Host</th><th>Port</th><th>User</th><th>Password</th><th>Status</th><th>Message</th></tr>";

$successConfig = null;

foreach ($configs as $config) {
    echo "<tr>";
    echo "<td>{$config['host']}</td>";
    echo "<td>{$config['port']}</td>";
    echo "<td>{$config['user']}</td>";
    echo "<td>" . ($config['pass'] ? $config['pass'] : '(empty)') . "</td>";

    // Suppress error output
    $conn = @new mysqli(
        $config['host'],
        $config['user'],
        $config['pass'],
        '',
        $config['port']
    );

    if ($conn->connect_error) {
        echo "<td style='color: red;'>❌ Failed</td>";
        echo "<td style='color: red;'>{$conn->connect_error}</td>";
    } else {
        echo "<td style='color: green;'>✅ Success</td>";

        // Check if database exists
        $result = $conn->query("SHOW DATABASES LIKE 'bright_minds_db'");
        if ($result && $result->num_rows > 0) {
            echo "<td style='color: green;'>Connected! Database 'bright_minds_db' exists.</td>";
        } else {
            echo "<td style='color: orange;'>Connected! But database 'bright_minds_db' does NOT exist.</td>";
        }

        $successConfig = $config;
        $conn->close();
    }

    echo "</tr>";
}

echo "</table>";

if ($successConfig) {
    echo "<hr>";
    echo "<h3 style='color: green;'>✅ Working Configuration Found!</h3>";
    echo "<p>Update your config.php with these settings:</p>";
    echo "<pre>";
    echo "define('DB_HOST', '{$successConfig['host']}');\n";
    echo "define('DB_PORT', {$successConfig['port']});\n";
    echo "define('DB_USER', '{$successConfig['user']}');\n";
    echo "define('DB_PASS', '" . ($successConfig['pass'] ?: '') . "');\n";
    echo "</pre>";
} else {
    echo "<hr>";
    echo "<h3 style='color: red;'>❌ No Working Configuration Found</h3>";
    echo "<p>Please check:</p>";
    echo "<ul>";
    echo "<li>MAMP is running (both Apache and MySQL should show green)</li>";
    echo "<li>MySQL server is actually started in MAMP</li>";
    echo "<li>Check MAMP preferences for the correct port</li>";
    echo "</ul>";
}
?>

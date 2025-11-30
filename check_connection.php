<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Diagnostic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        h1 { color: #333; }
        h2 { color: #666; margin-top: 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .test-item { margin: 15px 0; padding: 10px; border-left: 4px solid #ddd; }
        .test-item.pass { border-left-color: #28a745; background: #d4edda; }
        .test-item.fail { border-left-color: #dc3545; background: #f8d7da; }
    </style>
</head>
<body>
    <h1>🔍 Database Connection Diagnostic</h1>

    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // Test 1: Check if config file exists
    echo '<div class="box">';
    echo '<h2>Test 1: Configuration File</h2>';
    if (file_exists(__DIR__ . '/includes/config.php')) {
        echo '<div class="test-item pass">✓ config.php file exists</div>';
        require_once __DIR__ . '/includes/config.php';
    } else {
        echo '<div class="test-item fail">✗ config.php file NOT found</div>';
        die();
    }
    echo '</div>';

    // Test 2: Display current database settings
    echo '<div class="box">';
    echo '<h2>Test 2: Current Database Settings</h2>';
    echo '<pre>';
    echo "Host: " . DB_HOST . "\n";
    echo "Port: " . DB_PORT . "\n";
    echo "User: " . DB_USER . "\n";
    echo "Database: " . DB_NAME . "\n";
    echo '</pre>';
    echo '</div>';

    // Test 3: Try to connect
    echo '<div class="box">';
    echo '<h2>Test 3: Database Connection</h2>';

    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

        if ($conn->connect_error) {
            echo '<div class="test-item fail">';
            echo '<strong>✗ Connection FAILED</strong><br>';
            echo 'Error: ' . $conn->connect_error . '<br>';
            echo 'Error Number: ' . $conn->connect_errno . '<br><br>';

            echo '<strong>Troubleshooting Steps:</strong><br>';
            echo '<ol>';
            echo '<li>Check if MAMP MySQL server is running</li>';
            echo '<li>Verify the database name exists</li>';
            echo '<li>Verify the username and password are correct</li>';
            echo '<li>Check if the port is correct (usually 3306 or 8889)</li>';
            echo '</ol>';
            echo '</div>';
        } else {
            echo '<div class="test-item pass">';
            echo '✓ <strong>Connection SUCCESSFUL</strong><br>';
            echo 'Server version: ' . $conn->server_info;
            echo '</div>';

            // Test 4: Check if tables exist
            echo '</div><div class="box">';
            echo '<h2>Test 4: Database Tables</h2>';

            $tables = ['children', 'users', 'games', 'quizzes', 'play_sessions'];
            foreach ($tables as $table) {
                $result = $conn->query("SHOW TABLES LIKE '$table'");
                if ($result && $result->num_rows > 0) {
                    echo '<div class="test-item pass">✓ Table ' . $table . ' exists</div>';
                } else {
                    echo '<div class="test-item fail">✗ Table ' . $table . ' NOT found</div>';
                }
            }

            // Test 5: Check children table data
            echo '</div><div class="box">';
            echo '<h2>Test 5: Sample Data</h2>';

            $result = $conn->query("SELECT childID, first_name, total_xp, coins, streak_days FROM children LIMIT 5");
            if ($result && $result->num_rows > 0) {
                echo '<div class="test-item pass">✓ Found ' . $result->num_rows . ' children records</div>';
                echo '<pre>';
                echo str_pad('ID', 5) . str_pad('Name', 20) . str_pad('XP', 10) . str_pad('Coins', 10) . str_pad('Streak', 10) . "\n";
                echo str_repeat('-', 55) . "\n";
                while ($row = $result->fetch_assoc()) {
                    echo str_pad($row['childID'], 5);
                    echo str_pad($row['first_name'], 20);
                    echo str_pad($row['total_xp'], 10);
                    echo str_pad($row['coins'], 10);
                    echo str_pad($row['streak_days'], 10);
                    echo "\n";
                }
                echo '</pre>';
            } else {
                echo '<div class="test-item fail">✗ No children records found</div>';
            }

            $conn->close();
        }
    } catch (Exception $e) {
        echo '<div class="test-item fail">';
        echo '✗ <strong>Exception occurred:</strong><br>';
        echo $e->getMessage();
        echo '</div>';
    }
    echo '</div>';

    // Test 6: Check if API endpoints are accessible
    echo '<div class="box">';
    echo '<h2>Test 6: API Endpoints</h2>';
    $apiFiles = ['api/auth.php', 'api/dashboard.php', 'api/games.php', 'api/quiz.php'];
    foreach ($apiFiles as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            echo '<div class="test-item pass">✓ ' . $file . ' exists</div>';
        } else {
            echo '<div class="test-item fail">✗ ' . $file . ' NOT found</div>';
        }
    }
    echo '</div>';
    ?>

    <div class="box" style="background: #e7f3ff; border: 2px solid #0066cc;">
        <h2>📌 Next Steps</h2>
        <p>If the connection test failed:</p>
        <ol>
            <li>Open MAMP and ensure MySQL server is running (green light)</li>
            <li>Click "Open WebStart page" in MAMP to verify the MySQL port</li>
            <li>Check PHPMyAdmin to verify the database name and user credentials</li>
            <li>Update the credentials in <code>includes/config.php</code> if needed</li>
        </ol>
        <p>If connection is successful but XP/coins still not updating:</p>
        <ol>
            <li>Check browser console for API errors (F12 → Console tab)</li>
            <li>Complete a quiz or game and check the Network tab for failed requests</li>
            <li>Verify you're logged in with a valid session</li>
        </ol>
    </div>
</body>
</html>

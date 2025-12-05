<?php
/**
 * Session Debug Tool
 * This file helps diagnose session and authentication issues
 */

require_once 'includes/config.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        h2 { margin-top: 0; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Session & Auth Debug Tool</h1>

    <div class="section">
        <h2>1. PHP Session Status</h2>
        <?php
        echo "<p>Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "<span class='success'>ACTIVE</span>" : "<span class='error'>NOT ACTIVE</span>") . "</p>";
        echo "<p>Session ID: " . (session_id() ?: "<span class='error'>None</span>") . "</p>";
        echo "<p>Session Name: " . session_name() . "</p>";
        ?>
    </div>

    <div class="section">
        <h2>2. Session Variables</h2>
        <pre><?php print_r($_SESSION); ?></pre>
    </div>

    <div class="section">
        <h2>3. Authentication Check</h2>
        <?php
        $isLoggedIn = isLoggedIn();
        $userId = getCurrentUserId();
        $childId = getCurrentChildId();

        echo "<p>Is Logged In: " . ($isLoggedIn ? "<span class='success'>YES</span>" : "<span class='error'>NO</span>") . "</p>";
        echo "<p>User ID: " . ($userId ?: "<span class='error'>None</span>") . "</p>";
        echo "<p>Child ID: " . ($childId ?: "<span class='error'>None</span>") . "</p>";
        ?>
    </div>

    <div class="section">
        <h2>4. Database Connection</h2>
        <?php
        try {
            $db = getDB();
            $conn = $db->getConnection();
            echo "<p class='success'>✓ Database connection successful</p>";
            echo "<p>Database: " . DB_NAME . "</p>";
            echo "<p>Host: " . DB_HOST . "</p>";
        } catch (Exception $e) {
            echo "<p class='error'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>5. User Data from Database</h2>
        <?php
        if ($childId) {
            $child = $db->selectOne("SELECT * FROM children WHERE childID = ?", [$childId]);
            if ($child) {
                echo "<pre>" . print_r($child, true) . "</pre>";
            } else {
                echo "<p class='error'>Child not found in database</p>";
            }
        } else {
            echo "<p class='warning'>No child ID in session</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>6. Games Table Check</h2>
        <?php
        try {
            $games = $db->select("SELECT gameID, title, is_active FROM games LIMIT 5");
            if ($games) {
                echo "<p class='success'>✓ Games table accessible</p>";
                echo "<pre>" . print_r($games, true) . "</pre>";
            } else {
                echo "<p class='warning'>No games found</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>✗ Error accessing games table: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>7. Recent Error Logs</h2>
        <?php
        $logFile = __DIR__ . '/logs/error_' . date('Y-m-d') . '.log';
        if (file_exists($logFile)) {
            $logs = file_get_contents($logFile);
            $recentLogs = implode("\n", array_slice(explode("\n", $logs), -20));
            echo "<pre>" . htmlspecialchars($recentLogs) . "</pre>";
        } else {
            echo "<p>No error log for today</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>8. Test Reward API</h2>
        <button onclick="testRewardAPI()">Test Reward Upload</button>
        <pre id="apiResult"></pre>
    </div>

    <script>
        async function testRewardAPI() {
            const result = document.getElementById('apiResult');
            result.textContent = 'Testing...\n';

            try {
                const response = await fetch('api/games.php?action=award', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        gameId: 1,
                        xpEarned: 10,
                        coinsEarned: 5,
                        score: 100,
                        completed: true
                    })
                });

                result.textContent += `Response Status: ${response.status}\n`;
                result.textContent += `Response OK: ${response.ok}\n\n`;

                const text = await response.text();
                result.textContent += 'Response:\n' + text;
            } catch (error) {
                result.textContent += 'ERROR: ' + error.message;
            }
        }
    </script>
</body>
</html>

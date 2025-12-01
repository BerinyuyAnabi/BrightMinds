<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Integration Test - Bright Minds</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        .test-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .test-section {
            margin: 30px 0;
            padding: 20px;
            border: 2px solid var(--primary-blue);
            border-radius: 15px;
        }
        .test-result {
            padding: 15px;
            margin: 10px 0;
            border-radius: 10px;
            font-family: monospace;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 2px solid #bee5eb;
        }
        .test-button {
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 1rem;
            cursor: pointer;
            margin: 5px;
        }
        .test-button:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1 style="color: var(--primary-blue); text-align: center;">🧪 Dashboard Integration Test</h1>

        <!-- Test 1: Database Connection -->
        <div class="test-section">
            <h2>1️⃣ Database Connection Test</h2>
            <?php
            require_once 'includes/config.php';
            try {
                $db = getDB();
                $result = $db->selectOne("SELECT 1 as test");
                if ($result && $result['test'] == 1) {
                    echo '<div class="test-result success">✅ Database connection successful!</div>';
                } else {
                    echo '<div class="test-result error">❌ Database query failed</div>';
                }
            } catch (Exception $e) {
                echo '<div class="test-result error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <!-- Test 2: Helper Functions -->
        <div class="test-section">
            <h2>2️⃣ Helper Functions Test</h2>
            <?php
            $tests = [];

            // Test award_xp function exists
            $tests[] = ['name' => 'award_xp() function', 'result' => function_exists('award_xp')];
            $tests[] = ['name' => 'update_streak() function', 'result' => function_exists('update_streak')];
            $tests[] = ['name' => 'isParentLinked() function', 'result' => function_exists('isParentLinked')];
            $tests[] = ['name' => 'jsonResponse() function', 'result' => function_exists('jsonResponse')];

            foreach ($tests as $test) {
                $status = $test['result'] ? 'success' : 'error';
                $icon = $test['result'] ? '✅' : '❌';
                echo "<div class='test-result $status'>$icon {$test['name']}: " . ($test['result'] ? 'EXISTS' : 'MISSING') . "</div>";
            }
            ?>
        </div>

        <!-- Test 3: Database Tables -->
        <div class="test-section">
            <h2>3️⃣ Database Tables Test</h2>
            <?php
            $tables = ['users', 'children', 'games', 'quizzes', 'play_sessions', 'achievements'];
            foreach ($tables as $table) {
                try {
                    $result = $db->select("SELECT COUNT(*) as count FROM $table");
                    if ($result !== false) {
                        $count = $result[0]['count'] ?? 0;
                        echo "<div class='test-result success'>✅ Table '$table': $count rows</div>";
                    } else {
                        echo "<div class='test-result error'>❌ Table '$table': Query failed</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='test-result error'>❌ Table '$table': " . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
            ?>
        </div>

        <!-- Test 4: Sample Data -->
        <div class="test-section">
            <h2>4️⃣ Sample Child Data</h2>
            <?php
            $children = $db->select("SELECT c.*, u.username FROM children c JOIN users u ON c.userID = u.userID LIMIT 3");
            if ($children && count($children) > 0) {
                echo '<div class="test-result success">✅ Found ' . count($children) . ' child account(s)</div>';
                foreach ($children as $child) {
                    echo "<div class='test-result info'>";
                    echo "👤 <strong>{$child['display_name']}</strong> (@{$child['username']})<br>";
                    echo "🎯 Level: {$child['current_level']} | ⚡ XP: {$child['total_xp']} | 🪙 Coins: {$child['coins']} | 🔥 Streak: {$child['streak_days']} days<br>";
                    echo "🆔 ChildID: {$child['childID']} | Avatar: {$child['avatar']}<br>";
                    echo "👨‍👩‍👧 Parent Linked: " . (isParentLinked($child['childID']) ? 'Yes ✅' : 'No ❌');
                    echo "</div>";
                }
            } else {
                echo '<div class="test-result error">❌ No child accounts found. Please register a child account first.</div>';
            }
            ?>
        </div>

        <!-- Test 5: API Endpoints -->
        <div class="test-section">
            <h2>5️⃣ API Endpoints Test</h2>
            <div class="test-result info">
                📡 Available API endpoints:
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li><strong>api/auth.php</strong>: register, login, logout, verify, check</li>
                    <li><strong>api/dashboard.php</strong>: get-profile, get-parent-info, link-to-parent, unlink-parent, verify-invite-code</li>
                    <li><strong>api/games.php</strong>: list, get, start, end, award</li>
                    <li><strong>api/quiz.php</strong>: list, get, submit</li>
                </ul>
            </div>
            <button class="test-button" onclick="testAPI('api/games.php?action=list')">Test Games List API</button>
            <button class="test-button" onclick="testAPI('api/quiz.php?action=list')">Test Quiz List API</button>
            <div id="api-result"></div>
        </div>

        <!-- Test 6: Configuration -->
        <div class="test-section">
            <h2>6️⃣ Configuration Test</h2>
            <?php
            echo '<div class="test-result info">';
            echo "🔧 <strong>Database:</strong> " . DB_NAME . " @ " . DB_HOST . ":" . DB_PORT . "<br>";
            echo "🌐 <strong>Base URL:</strong> " . BASE_URL . "<br>";
            echo "📊 <strong>XP per Level:</strong> " . XP_PER_LEVEL . "<br>";
            echo "🎮 <strong>App Version:</strong> " . APP_VERSION;
            echo '</div>';
            ?>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <button class="test-button" onclick="location.href='index.html'" style="background: var(--secondary-purple);">
                ← Back to Login
            </button>
            <button class="test-button" onclick="location.href='dashboard.php'" style="background: var(--green);">
                Go to Dashboard →
            </button>
        </div>
    </div>

    <script>
        async function testAPI(endpoint) {
            const resultDiv = document.getElementById('api-result');
            resultDiv.innerHTML = '<div class="test-result info">⏳ Testing API: ' + endpoint + '...</div>';

            try {
                const response = await fetch(endpoint);
                const data = await response.json();

                if (data.success) {
                    resultDiv.innerHTML = '<div class="test-result success">✅ API Test Successful!<br><pre>' +
                        JSON.stringify(data, null, 2) + '</pre></div>';
                } else {
                    resultDiv.innerHTML = '<div class="test-result error">❌ API returned error: ' +
                        (data.message || 'Unknown error') + '</div>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<div class="test-result error">❌ API Test Failed: ' +
                    error.message + '</div>';
            }
        }
    </script>
</body>
</html>

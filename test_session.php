<?php
/**
 * Session Diagnostic Tool
 * Use this to check if sessions are working correctly after deployment
 */

require_once 'includes/config.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Diagnostic - Bright Minds</title>
    <link rel="stylesheet" href="css/main.css">
    <style>
        .diag-container {
            max-width: 800px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .diag-section {
            margin: 20px 0;
            padding: 15px;
            border-radius: 10px;
            background: #f8f9fa;
        }
        .success { background: #d4edda; color: #155724; border: 2px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 2px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 2px solid #bee5eb; }
        pre { background: #272822; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto; }
        .btn { padding: 10px 20px; background: var(--primary-blue); color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
    </style>
</head>
<body>
    <div class="diag-container">
        <h1 style="color: var(--primary-blue); text-align: center;">🔍 Session Diagnostic</h1>

        <!-- Session Status -->
        <div class="diag-section <?php echo isLoggedIn() ? 'success' : 'error'; ?>">
            <h2>Session Status</h2>
            <?php if (isLoggedIn()): ?>
                <p>✅ <strong>User is logged in!</strong></p>
            <?php else: ?>
                <p>❌ <strong>No active session</strong></p>
                <p>Please <a href="index.html">login</a> first, then return to this page.</p>
            <?php endif; ?>
        </div>

        <?php if (isLoggedIn()): ?>
            <!-- Session Variables -->
            <div class="diag-section info">
                <h2>Session Variables</h2>
                <pre><?php print_r($_SESSION); ?></pre>
            </div>

            <!-- User Information -->
            <div class="diag-section info">
                <h2>Current User Info</h2>
                <p><strong>User ID:</strong> <?php echo getCurrentUserId() ?? 'NULL'; ?></p>
                <p><strong>Child ID:</strong> <?php echo getCurrentChildId() ?? 'NULL'; ?></p>
                <p><strong>Role:</strong> <?php echo $_SESSION['role'] ?? 'NULL'; ?></p>
            </div>

            <!-- Database Check -->
            <div class="diag-section">
                <?php
                $userId = getCurrentUserId();
                $childId = getCurrentChildId();

                if ($userId) {
                    $db = getDB();

                    // Get user data
                    $user = $db->selectOne("SELECT * FROM users WHERE userID = ?", [$userId]);

                    if ($user) {
                        echo '<h2>✅ User Found in Database</h2>';
                        echo '<p><strong>Username:</strong> ' . htmlspecialchars($user['username']) . '</p>';
                        echo '<p><strong>Email:</strong> ' . htmlspecialchars($user['email']) . '</p>';
                        echo '<p><strong>Role:</strong> ' . htmlspecialchars($user['role']) . '</p>';

                        // If role is child, get child data
                        if ($user['role'] === 'child') {
                            $child = $db->selectOne("SELECT * FROM children WHERE userID = ?", [$userId]);

                            if ($child) {
                                echo '<h3 class="diag-section success">✅ Child Record Found</h3>';
                                echo '<p><strong>Child ID:</strong> ' . $child['childID'] . '</p>';
                                echo '<p><strong>Display Name:</strong> ' . htmlspecialchars($child['display_name']) . '</p>';
                                echo '<p><strong>Avatar:</strong> ' . htmlspecialchars($child['avatar']) . '</p>';
                                echo '<p><strong>XP:</strong> ' . $child['total_xp'] . '</p>';
                                echo '<p><strong>Level:</strong> ' . $child['current_level'] . '</p>';
                                echo '<p><strong>Coins:</strong> ' . $child['coins'] . '</p>';
                                echo '<p><strong>Streak:</strong> ' . $child['streak_days'] . ' days</p>';

                                // Check if session has child_id
                                if (!isset($_SESSION['child_id'])) {
                                    echo '<div class="diag-section error">';
                                    echo '<p>⚠️ <strong>WARNING:</strong> Child ID is NOT in session!</p>';
                                    echo '<p>Child ID from database: ' . $child['childID'] . '</p>';
                                    echo '<p>This should have been set during login.</p>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="diag-section success">';
                                    echo '<p>✅ Child ID is correctly stored in session</p>';
                                    echo '</div>';
                                }
                            } else {
                                echo '<div class="diag-section error">';
                                echo '<p>❌ <strong>ERROR:</strong> User is child but no child record found!</p>';
                                echo '</div>';
                            }
                        }
                    } else {
                        echo '<div class="diag-section error">';
                        echo '<p>❌ <strong>ERROR:</strong> User not found in database!</p>';
                        echo '</div>';
                    }
                }
                ?>
            </div>

            <!-- API Test -->
            <div class="diag-section info">
                <h2>Test Dashboard API</h2>
                <button class="btn" onclick="testAPI()">Test Get Profile API</button>
                <div id="api-result"></div>
            </div>

            <script>
                async function testAPI() {
                    const resultDiv = document.getElementById('api-result');
                    resultDiv.innerHTML = '<p>⏳ Testing API...</p>';

                    try {
                        const response = await fetch('api/dashboard.php?action=get-profile');
                        const data = await response.json();

                        if (data.success) {
                            resultDiv.innerHTML = `
                                <div class="diag-section success">
                                    <h3>✅ API Test Successful!</h3>
                                    <p><strong>Name:</strong> ${data.profile.display_name}</p>
                                    <p><strong>Avatar:</strong> ${data.profile.avatar}</p>
                                    <p><strong>XP:</strong> ${data.profile.total_xp}</p>
                                    <p><strong>Level:</strong> ${data.profile.current_level}</p>
                                    <p><strong>Coins:</strong> ${data.profile.coins}</p>
                                    <p><strong>Streak:</strong> ${data.profile.streak_days} days</p>
                                </div>
                            `;
                        } else {
                            resultDiv.innerHTML = `
                                <div class="diag-section error">
                                    <p>❌ <strong>API Error:</strong> ${data.message}</p>
                                    <p>This means the session or child ID is not being passed correctly to the API.</p>
                                </div>
                            `;
                        }
                    } catch (error) {
                        resultDiv.innerHTML = `
                            <div class="diag-section error">
                                <p>❌ <strong>Request Failed:</strong> ${error.message}</p>
                            </div>
                        `;
                    }
                }
            </script>

        <?php endif; ?>

        <!-- Actions -->
        <div style="text-align: center; margin-top: 30px;">
            <?php if (isLoggedIn()): ?>
                <a href="dashboard.php" class="btn">Go to Dashboard</a>
                <a href="api/auth.php?action=logout" class="btn" style="background: #dc3545;">Logout</a>
            <?php else: ?>
                <a href="index.html" class="btn">Go to Login</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

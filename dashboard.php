<?php
/**
 * Bright Minds - Child Dashboard
 * Protected page - requires authentication
 */

require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: index.html');
    exit;
}

// Verify user is a child
$db = getDB();
$user = $db->selectOne("SELECT role FROM users WHERE userID = ?", [getCurrentUserId()]);

if (!$user || $user['role'] !== 'child') {
    // If not a child, redirect based on role
    if ($user && $user['role'] === 'parent') {
        header('Location: parent-dashboard.php');
        exit;
    }
    header('Location: index.html');
    exit;
}

// Get child data for initial page load
$childId = getCurrentChildId();
$childData = null;
if ($childId) {
    $childData = $db->selectOne(
        "SELECT c.*, u.username, u.email 
         FROM children c
         JOIN users u ON c.userID = u.userID 
         WHERE c.childID = ?",
        [$childId]
    );
}
 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Bright Minds</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/celebrations.css">
    <style>
        /* Parent Link Section Styles */
        .parent-link-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            /* background: blueviolet; */
            padding: 20px;
            border-radius: 15px;
            margin: 20px 0;
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .parent-link-section h3 {
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .parent-link-section p {
            margin: 5px 0 15px 0;
            opacity: 0.95;
        }
        
        .parent-link-input {
            width: 100%;
            padding: 12px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 10px;
            font-size: 1rem;
            margin-bottom: 10px;
            background: rgba(255,255,255,0.1);
            color: white;
            text-transform: uppercase;
        }
        
        .parent-link-input::placeholder {
            color: rgba(255,255,255,0.6);
        }
        
        .parent-link-input:focus {
            outline: none;
            border-color: rgba(255,255,255,0.6);
            background: rgba(255,255,255,0.15);
        }
        
        .parent-info {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 10px;
            margin: 10px 0;
        }
        
        .parent-info p {
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
        }
        
        .btn-unlink {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-unlink:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
        }
    </style>
</head>
<body>
    <div class="animated-bg"></div>
    
    <div class="dashboard">
        <!-- Header -->
        <div class="dashboard-header">
            <div class="user-profile">
                <div class="user-avatar" id="userAvatar"><?php echo htmlspecialchars($childData['avatar'] ?? '🦉'); ?></div>
                <div>
                    <h1>Welcome back, <span id="userName"><?php echo htmlspecialchars($childData['display_name'] ?? 'Explorer'); ?></span>! 👋</h1>
                    <p>Ready for another adventure?</p>
                </div>
            </div>
            
            <div class="user-stats">
                <div class="stat-card">
                    <div class="stat-icon">⚡</div>
                    <div class="stat-value" id="xpValue"><?php echo $childData['total_xp'] ?? 0; ?></div>
                    <div class="stat-label">Total XP</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🎯</div>
                    <div class="stat-value" id="levelValue"><?php echo $childData['current_level'] ?? 1; ?></div>
                    <div class="stat-label">Level</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🪙</div>
                    <div class="stat-value" id="coinsValue"><?php echo $childData['coins'] ?? 0; ?></div>
                    <div class="stat-label">Coins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🔥</div>
                    <div class="stat-value" id="streakValue"><?php echo $childData['streak_days'] ?? 0; ?></div>
                    <div class="stat-label">Day Streak</div>
                </div>
            </div>
        </div>
        
        <!-- Parent Linking Section -->
        <div class="parent-link-section" id="parentLinkSection">

<?php
// session_start();
$childId = $_SESSION['user_id'];

echo '<div> Parent Linking '.$childId. ' Placeholder </div>';
// if(isParentLinked($childId)){ 
//     echo '
//             <!-- Show if not linked -->
//             <div id="notLinkedView" class="hidden">
//                 <h3>🔗 Link to Parent Account</h3>
//                 <p>Connect with your parent so they can track your amazing progress!</p>
//                 <input 
//                     type="text" 
//                     id="parentInviteCode" 
//                     class="parent-link-input"
//                     placeholder="Enter parent code (e.g., PAR-A7B3K or LINK-8X4Y)" 
//                     maxlength="12">
//                 <button onclick="linkToParent()" class="btn btn-primary" style="width: 100%;">
//                     🚀 Link My Account
//                 </button>
//             </div>
//     '
// } else {
//     echo ' 
//             <!-- Show if linked -->
//             <div id="linkedView" class="hidden">
//                 <h3>✅ Connected to Parent</h3>
//                 <div class="parent-info">
//                     <p>
//                         <strong>Parent:</strong> 
//                         <span id="parentUsername">Loading...</span>
//                     </p>
//                     <p>
//                         <strong>Linked since:</strong> 
//                         <span id="linkedDate">Loading...</span>
//                     </p>
//                 </div>
//                 <button onclick="confirmUnlinkParent()" class="btn-unlink">
//                     🔓 Unlink from Parent
//                 </button>
//             </div>
//         </div> '
// }
        ?>
        
        <!-- Activities -->
        <h2 style="color: var(--primary-blue); margin: 20px 0; font-size: 2rem;">Choose Your Activity</h2>
        <div class="activities-grid">
            <div class="activity-card" onclick="location.href='games.html'">
                <div class="activity-icon">🎮</div>
                <h3>Play Games</h3>
                <p>Fun and educational games</p>
            </div>
            
            <div class="activity-card" onclick="location.href='quiz.html'">
                <div class="activity-icon">📝</div>
                <h3>Take a Quiz</h3>
                <p>Test your knowledge</p>
            </div>
            
            <div class="activity-card" onclick="location.href='stories.html'">
                <div class="activity-icon">📖</div>
                <h3>Read Stories</h3>
                <p>Amazing tales and lessons</p>
            </div>
            
            <div class="activity-card" onclick="location.href='achievements.html'">
                <div class="activity-icon">🏆</div>
                <h3>Achievements</h3>
                <p>View your badges</p>
            </div>
        </div>
        
        <!-- Logout Button -->
        <button class="btn btn-secondary" onclick="logout()" style="margin-top: 30px;">
            <span class="btn-icon">🚪</span>
            Logout
        </button>
    </div>

    <!-- Toast Notification -->
    <div class="toast hidden" id="toast">
        <span class="toast-icon">✔</span>
        <span class="toast-message"></span>
    </div>

    <script src="js/celebrations.js"></script>
    <script src="js/dashboard.js"></script>
</body>
</html>
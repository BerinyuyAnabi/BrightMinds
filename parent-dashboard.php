<?php
require_once 'includes/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: parent-auth.html');
    exit;
}

// Verify user is a parent
$db = getDB();
$userId = getCurrentUserId();

if (!$userId) {
    // No user ID in session - redirect to login
    header('Location: parent-auth.html');
    exit;
}

// Get user with child relationship check
$user = $db->selectOne("
    SELECT u.role, c.childID 
    FROM users u 
    LEFT JOIN children c ON u.userID = c.userID 
    WHERE u.userID = ?
", [$userId]);

// Only redirects if we have a valid user record 
if ($user && isset($user['role']) && $user['role'] === 'child' && $user['childID']) {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Account Mismatch</title>
        <link rel="stylesheet" href="css/main.css">
        <style>
            body { display: flex; justify-content: center; align-items: center; height: 100vh; background: #f0f2f5; font-family: sans-serif; }
            .card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
            .btn { display: inline-block; padding: 12px 24px; background: #FF6B6B; color: white; text-decoration: none; border-radius: 10px; margin-top: 20px; transition: all 0.3s; }
            .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(255, 107, 107, 0.4); }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Wrong Dashboard 🧸</h1>
            <p>You are currently logged in as a <strong>Child</strong> (' . htmlspecialchars($user['username']) . ').</p>
            <p>This page is for Parents.</p>
            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                <a href="dashboard.php" class="btn">Go to Child Dashboard</a>
                <button onclick="fetch(\'api/auth.php?action=logout\').then(() => window.location.reload());" class="btn" style="background: #ef4444;">Logout</button>
            </div>
        </div>
    </body>
    </html>';
    exit;
}
// For any other case allow page to load

// Get parent data for initial page load
$parentData = $db->selectOne(
    "SELECT u.* FROM users u WHERE u.userID = ?",
    [getCurrentUserId()]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - Bright Minds</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/parent-dashboard.css">
</head>
<body>
    <div class="animated-bg"></div>
    
    <div class="parent-container">
        <!-- Header -->
        <header class="parent-header">
            <div class="header-content">
                <div class="brand">
                    <div class="brand-icon">🧠</div>
                    <div>
                        <h1>Bright Minds</h1>
                        <p class="subtitle">Parent Dashboard</p>
                    </div>
                </div>
                
                <div class="header-actions">
                    <button class="btn-icon" onclick="showNotifications()" id="notificationBtn">
                        🔔
                        <span class="notification-badge hidden" id="notificationBadge">0</span>
                    </button>
                    <div class="parent-profile" onclick="toggleProfileMenu()">
                        <span id="parentName">Parent</span>
                        <div class="profile-avatar">👤</div>
                    </div>
                </div>
            </div>
            
            <!-- Profile Dropdown -->
            <div class="profile-dropdown hidden" id="profileDropdown">
                <button onclick="switchTab('settings')">⚙️ Settings</button>
                <button onclick="logout()">🚪 Logout</button>
            </div>
        </header>
        
        <!-- Navigation Tabs -->
        <nav class="nav-tabs">
            <button class="nav-tab active" data-tab="overview">📊 Overview</button>
            <button class="nav-tab" data-tab="children">👶 Children</button>
            <button class="nav-tab" data-tab="reports">📈 Reports</button>
            <button class="nav-tab" data-tab="goals">🎯 Goals</button>
            <button class="nav-tab" data-tab="settings">⚙️ Settings</button>
            <button class="nav-tab logout-tab" onclick="logout()">🚪 Logout</button>
        </nav>
        
        <!-- Overview Tab -->
        <div class="tab-content active" id="overview-tab">
            <!-- Summary Cards -->
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-icon">👶</div>
                    <div class="summary-data">
                        <div class="summary-value" id="totalChildren">0</div>
                        <div class="summary-label">Children</div>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">✅</div>
                    <div class="summary-data">
                        <div class="summary-value" id="totalActivities">0</div>
                        <div class="summary-label">Activities Completed</div>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">⚡</div>
                    <div class="summary-data">
                        <div class="summary-value" id="totalXP">0</div>
                        <div class="summary-label">Total XP Earned</div>
                    </div>
                </div>
                
                <div class="summary-card">
                    <div class="summary-icon">📅</div>
                    <div class="summary-data">
                        <div class="summary-value" id="activeToday">0</div>
                        <div class="summary-label">Active Today</div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="action-buttons">
                    <button class="action-btn" onclick="showInviteModal()">
                        <span class="btn-icon">➕</span>
                        Link Child Account
                    </button>
                    <button class="action-btn" onclick="exportAllReports()">
                        <span class="btn-icon">📥</span>
                        Export All Reports
                    </button>
                    <button class="action-btn" onclick="switchTab('settings')">
                        <span class="btn-icon">⚙️</span>
                        Manage Settings
                    </button>
                </div>
            </div>
            
            <!-- Children Quick View -->
            <div class="section">
                <h2>Children Overview</h2>
                <div class="children-grid" id="childrenQuickView">
                    <div class="loading-placeholder">Loading children...</div>
                </div>
            </div>
            
            <!-- Recent Achievements -->
            <div class="section">
                <h2>Recent Achievements</h2>
                <div class="achievements-list" id="recentAchievements">
                    <div class="loading-placeholder">Loading achievements...</div>
                </div>
            </div>
        </div>
        
        <!-- Children Tab -->
        <div class="tab-content" id="children-tab">
            <div class="section-header">
                <h2>Manage Children</h2>
                <button class="btn btn-primary" onclick="showInviteModal()">
                    <span class="btn-icon">➕</span>
                    Link New Child
                </button>
            </div>
            
            <div class="children-detailed-list" id="childrenDetailedList">
                <div class="loading-placeholder">Loading children...</div>
            </div>
        </div>
        
        <!-- Reports Tab -->
        <div class="tab-content" id="reports-tab">
            <div class="section-header">
                <h2>Progress Reports</h2>
                <select id="reportChildSelect" onchange="loadChildReport()">
                    <option value="">Select a child...</option>
                </select>
            </div>
            
            <div id="reportContent">
                <div class="empty-state">
                    <div class="empty-icon">📊</div>
                    <p>Select a child to view their progress report</p>
                </div>
            </div>
        </div>
        
        <!-- Goals Tab -->
        <div class="tab-content" id="goals-tab">
            <div class="section-header">
                <h2>Learning Goals</h2>
                <button class="btn btn-primary" onclick="showGoalModal()">
                    <span class="btn-icon">🎯</span>
                    Set New Goal
                </button>
            </div>
            
            <div id="goalsContent">
                <div class="empty-state">
                    <div class="empty-icon">🎯</div>
                    <p>No goals set yet. Create a goal to track your child's progress!</p>
                </div>
            </div>
        </div>
        
        <!-- Settings Tab -->
        <div class="tab-content" id="settings-tab">
            <div class="section-header">
                <h2>Account Settings</h2>
            </div>
            
            <div class="settings-container">
                <!-- Account Information -->
                <div class="settings-section">
                    <h3>Account Information</h3>
                    <div class="form-field">
                        <label>Display Name</label>
                        <input type="text" id="settingsDisplayName" placeholder="Your display name">
                        <button class="btn btn-secondary" onclick="updateDisplayName()">Update</button>
                    </div>
                    
                    <div class="form-field">
                        <label>Email</label>
                        <input type="email" id="settingsEmail" readonly>
                    </div>
                    
                    <div class="form-field">
                        <label>Username</label>
                        <input type="text" id="settingsUsername" readonly>
                    </div>
                </div>
                
                <!-- Password Change -->
                <div class="settings-section">
                    <h3>Change Password</h3>
                    <div class="form-field">
                        <label>Current Password</label>
                        <input type="password" id="currentPassword" placeholder="Enter current password">
                    </div>
                    
                    <div class="form-field">
                        <label>New Password</label>
                        <input type="password" id="newPassword" placeholder="Enter new password">
                    </div>
                    
                    <div class="form-field">
                        <label>Confirm New Password</label>
                        <input type="password" id="confirmNewPassword" placeholder="Confirm new password">
                    </div>
                    
                    <button class="btn btn-primary" onclick="updatePassword()">Change Password</button>
                </div>
                
                <!-- Notification Preferences -->
                <div class="settings-section">
                    <h3>Notification Preferences</h3>
                    <div class="form-field">
                        <label class="toggle-label">
                            <input type="checkbox" id="notifyGoalCompleted" checked>
                            <span>Goal Completion Notifications</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="toggle-label">
                            <input type="checkbox" id="notifyAchievements" checked>
                            <span>Achievement Notifications</span>
                        </label>
                    </div>
                    
                    <div class="form-field">
                        <label class="toggle-label">
                            <input type="checkbox" id="notifyDailyReports" checked>
                            <span>Daily Activity Reports</span>
                        </label>
                    </div>
                    
                    <button class="btn btn-secondary" onclick="saveNotificationPreferences()">Save Preferences</button>
                </div>
                
                <!-- Privacy Settings -->
                <div class="settings-section">
                    <h3>Privacy Settings</h3>
                    <div class="form-field">
                        <label class="toggle-label">
                            <input type="checkbox" id="shareProgress" checked>
                            <span>Share progress with linked children</span>
                        </label>
                    </div>
                    
                    <button class="btn btn-secondary" onclick="savePrivacySettings()">Save Settings</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Invite Code Modal -->
    <div class="modal hidden" id="inviteModal">
        <div class="modal-overlay" onclick="closeInviteModal()"></div>
        <div class="modal-content">
            <button class="modal-close" onclick="closeInviteModal()">×</button>
            <h2>Link Child Account</h2>
            <p>Generate an invite code for your child to link their account</p>
            
            <div class="invite-code-display hidden" id="inviteCodeDisplay">
                <div class="invite-code" id="inviteCodeText">XXXXXXXX</div>
                <p class="invite-instructions">
                    Share this code with your child. They can enter it in their account settings to link to your parent dashboard.
                </p>
                <p class="invite-expiry">This code expires in 7 days</p>
                <button class="btn btn-secondary" onclick="copyInviteCode()">
                    📋 Copy Code
                </button>
            </div>
            
            <button class="btn btn-primary btn-large" onclick="generateInviteCode()" id="generateBtn">
                Generate Invite Code
            </button>
        </div>
    </div>
    
    <!-- Goal Modal -->
    <div class="modal hidden" id="goalModal">
        <div class="modal-overlay" onclick="closeGoalModal()"></div>
        <div class="modal-content">
            <button class="modal-close" onclick="closeGoalModal()">×</button>
            <h2>Set Learning Goal</h2>
            
            <form id="goalForm" onsubmit="submitGoal(event)">
                <div class="form-field">
                    <label>Select Child</label>
                    <select id="goalChildSelect" required>
                        <option value="">Choose a child...</option>
                    </select>
                </div>
                
                <div class="form-field">
                    <label>Goal Type</label>
                    <select id="goalType" required>
                        <option value="xp_earned">XP Earned</option>
                        <option value="coins_earned">Coins Earned</option>
                        <option value="games_played">Games Played</option>
                        <option value="quizzes_completed">Quizzes Completed</option>
                        <option value="total_xp">Total XP</option>
                        <option value="total_coins">Total Coins</option>
                    </select>
                </div>
                
                <div class="form-field">
                    <label>Goal Description</label>
                    <input type="text" id="goalDescription" placeholder="E.g., Complete 3 activities daily" required>
                </div>
                
                <div class="form-field">
                    <label>Target Value</label>
                    <input type="number" id="goalTarget" placeholder="E.g., 21 (for 3 per day × 7 days)" required min="1">
                </div>
                
                <div class="form-field">
                    <label>End Date</label>
                    <input type="date" id="goalEndDate" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-large">
                    Create Goal
                </button>
            </form>
        </div>
    </div>
    
    <!-- Notifications Panel -->
    <div class="notifications-panel hidden" id="notificationsPanel">
        <div class="panel-header">
            <h3>Notifications</h3>
            <button onclick="closeNotifications()">×</button>
        </div>
        <div class="notifications-list" id="notificationsList">
            <div class="loading-placeholder">Loading notifications...</div>
        </div>
    </div>
    
    <script src="js/parent-dashboard.js"></script>
</body>
</html>


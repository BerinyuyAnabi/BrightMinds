// Parent Dashboard JavaScript

const API_BASE = 'api/parent.php';

// Initialize on page load
window.addEventListener('DOMContentLoaded', () => {
    checkParentAuth();
    loadParentData();
    initializeTabs();
    loadDashboard();
});

// Check if parent is authenticated via API
async function checkParentAuth() {
    try {
        const response = await fetch('api/auth.php?action=verify');
        const data = await response.json();
        
        if (!data.success) {
            // Session expired or not authenticated
            window.location.href = 'parent-auth.html';
            return;
        }
        
        // Verify user is a parent
        if (data.user && data.user.role !== 'parent') {
            // Redirect to appropriate dashboard
            if (data.user.role === 'child') {
                window.location.href = 'dashboard.php';
            } else {
                window.location.href = 'parent-auth.html';
            }
            return;
        }
        
        // Store user data in localStorage for client-side use
        if (data.user) {
            localStorage.setItem('brightMindsSession', JSON.stringify({
                userId: data.user.userId,
                username: data.user.username,
                displayName: data.user.displayName,
                email: data.user.email,
                role: data.user.role
            }));
            
            // Update parent name
            const parentNameEl = document.getElementById('parentName');
            if (parentNameEl) {
                parentNameEl.textContent = data.user.displayName || data.user.username;
            }
        }
    } catch (error) {
        console.error('Auth check failed:', error);
        window.location.href = 'parent-auth.html';
    }
}

// Load parent data
function loadParentData() {
    // Load notifications count
    loadNotificationsCount();
}

// Initialize tab navigation
function initializeTabs() {
    const tabs = document.querySelectorAll('.nav-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const tabName = tab.getAttribute('data-tab');
            switchTab(tabName);
        });
    });
}

// Switch tabs
function switchTab(tabName) {
    // Update tab buttons
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.classList.remove('active');
        if (tab.getAttribute('data-tab') === tabName) {
            tab.classList.add('active');
        }
    });
    
    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.getElementById(`${tabName}-tab`).classList.add('active');
    
    // Load tab data
    switch(tabName) {
        case 'overview':
            loadDashboard();
            break;
        case 'children':
            loadChildrenList();
            break;
        case 'reports':
            loadReportsTab();
            break;
        case 'goals':
            loadGoalsTab();
            break;
    }
}

// Load dashboard overview
async function loadDashboard() {
    try {
        // For demo purposes, use mock data
        const mockData = {
            success: true,
            summary: {
                totalChildren: 2,
                totalActivities: 45,
                totalXP: 1250,
                activeToday: 1
            },
            children: [
                {
                    childID: 1,
                    display_name: 'Emma Explorer',
                    age: 8,
                    avatar: '🦉',
                    total_xp: 850,
                    current_level: 9,
                    coins: 320,
                    streak_days: 7,
                    last_activity_date: new Date().toISOString().split('T')[0]
                },
                {
                    childID: 2,
                    display_name: 'Sam Scientist',
                    age: 10,
                    avatar: '🦊',
                    total_xp: 400,
                    current_level: 4,
                    coins: 150,
                    streak_days: 3,
                    last_activity_date: new Date(Date.now() - 86400000).toISOString().split('T')[0]
                }
            ],
            recentAchievements: [
                {
                    display_name: 'Emma Explorer',
                    avatar: '🦉',
                    title: 'Week Warrior',
                    description: 'Maintain a 7-day streak',
                    badge_icon: '🔥',
                    unlocked_at: new Date(Date.now() - 3600000).toISOString()
                },
                {
                    display_name: 'Sam Scientist',
                    avatar: '🦊',
                    title: 'Quiz Master',
                    description: 'Complete 10 quizzes',
                    badge_icon: '🧠',
                    unlocked_at: new Date(Date.now() - 7200000).toISOString()
                }
            ],
            unreadNotifications: 3
        };
        
        displayDashboardData(mockData);
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showToast('Failed to load dashboard data', 'error');
    }
}

// Display dashboard data
function displayDashboardData(data) {
    // Update summary cards
    document.getElementById('totalChildren').textContent = data.summary.totalChildren;
    document.getElementById('totalActivities').textContent = data.summary.totalActivities;
    document.getElementById('totalXP').textContent = data.summary.totalXP.toLocaleString();
    document.getElementById('activeToday').textContent = data.summary.activeToday;
    
    // Update notifications badge
    if (data.unreadNotifications > 0) {
        const badge = document.getElementById('notificationBadge');
        badge.textContent = data.unreadNotifications;
        badge.classList.remove('hidden');
    }
    
    // Display children quick view
    displayChildrenQuickView(data.children);
    
    // Display recent achievements
    displayRecentAchievements(data.recentAchievements);
}

// Display children quick view
function displayChildrenQuickView(children) {
    const container = document.getElementById('childrenQuickView');
    
    if (!children || children.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">👶</div>
                <p>No children linked yet. Click "Link Child Account" to get started!</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = children.map(child => `
        <div class="child-card" onclick="viewChildDetails(${child.childID})">
            <div class="child-card-header">
                <div class="child-avatar">${child.avatar}</div>
                <div class="child-info">
                    <h3>${child.display_name}</h3>
                    <div class="child-age">${child.age} years old</div>
                </div>
            </div>
            <div class="child-stats">
                <div class="stat-item">
                    <div class="stat-value">⚡${child.total_xp}</div>
                    <div class="stat-label">XP</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">Lv. ${child.current_level}</div>
                    <div class="stat-label">Level</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">🪙${child.coins}</div>
                    <div class="stat-label">Coins</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">🔥${child.streak_days}</div>
                    <div class="stat-label">Streak</div>
                </div>
            </div>
            <div class="child-actions">
                <button onclick="event.stopPropagation(); viewChildReport(${child.childID})">📊 Report</button>
                <button onclick="event.stopPropagation(); viewChildActivities(${child.childID})">📝 Activities</button>
            </div>
        </div>
    `).join('');
}

// Display recent achievements
function displayRecentAchievements(achievements) {
    const container = document.getElementById('recentAchievements');
    
    if (!achievements || achievements.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <p>No recent achievements to display</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = achievements.map(achievement => {
        const timeAgo = formatTimeAgo(new Date(achievement.unlocked_at));
        return `
            <div class="achievement-item">
                <div class="achievement-badge">${achievement.badge_icon}</div>
                <div class="achievement-info">
                    <div class="achievement-child">
                        <span class="achievement-child-avatar">${achievement.avatar}</span>
                        <strong>${achievement.display_name}</strong>
                    </div>
                    <div class="achievement-title">${achievement.title}</div>
                    <div class="achievement-description">${achievement.description}</div>
                    <div class="achievement-time">${timeAgo}</div>
                </div>
            </div>
        `;
    }).join('');
}

// Load children list
function loadChildrenList() {
    // Use data from dashboard
    loadDashboard().then(() => {
        const container = document.getElementById('childrenDetailedList');
        // For now, reuse quick view
        // In production, this would have more detailed cards
        container.innerHTML = document.getElementById('childrenQuickView').innerHTML;
    });
}

// Load reports tab
function loadReportsTab() {
    const select = document.getElementById('reportChildSelect');
    
    // Populate with children (mock data)
    const children = [
        { childID: 1, display_name: 'Emma Explorer' },
        { childID: 2, display_name: 'Sam Scientist' }
    ];
    
    select.innerHTML = '<option value="">Select a child...</option>' + 
        children.map(child => `<option value="${child.childID}">${child.display_name}</option>`).join('');
}

// Load child report
function loadChildReport() {
    const select = document.getElementById('reportChildSelect');
    const childId = select.value;
    
    if (!childId) {
        document.getElementById('reportContent').innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <p>Select a child to view their progress report</p>
            </div>
        `;
        return;
    }
    
    // Mock report data
    const reportData = {
        child: { display_name: select.options[select.selectedIndex].text },
        stats: {
            total_activities: 25,
            xp_earned: 450,
            coins_earned: 220,
            minutes_played: 180,
            avg_quiz_score: 87.5
        }
    };
    
    document.getElementById('reportContent').innerHTML = `
        <div class="section">
            <h2>Weekly Report for ${reportData.child.display_name}</h2>
            <div class="summary-grid">
                <div class="summary-card">
                    <div class="summary-icon">✅</div>
                    <div class="summary-data">
                        <div class="summary-value">${reportData.stats.total_activities}</div>
                        <div class="summary-label">Activities</div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon">⚡</div>
                    <div class="summary-data">
                        <div class="summary-value">${reportData.stats.xp_earned}</div>
                        <div class="summary-label">XP Earned</div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon">🪙</div>
                    <div class="summary-data">
                        <div class="summary-value">${reportData.stats.coins_earned}</div>
                        <div class="summary-label">Coins Earned</div>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon">⏱️</div>
                    <div class="summary-data">
                        <div class="summary-value">${reportData.stats.minutes_played}</div>
                        <div class="summary-label">Minutes Played</div>
                    </div>
                </div>
            </div>
            <button class="btn btn-primary" onclick="exportReport(${childId})">
                📥 Export Report
            </button>
        </div>
    `;
}

// Load goals tab
function loadGoalsTab() {
    document.getElementById('goalsContent').innerHTML = `
        <div class="empty-state">
            <div class="empty-icon">🎯</div>
            <p>No goals set yet. Create a goal to track your child's progress!</p>
        </div>
    `;
}

// Show invite modal
function showInviteModal() {
    document.getElementById('inviteModal').classList.remove('hidden');
    document.getElementById('inviteCodeDisplay').classList.add('hidden');
    document.getElementById('generateBtn').style.display = 'inline-flex';
}

// Close invite modal
function closeInviteModal() {
    document.getElementById('inviteModal').classList.add('hidden');
}

// Generate invite code
function generateInviteCode() {
    // Generate random 8-character code
    const code = Math.random().toString(36).substring(2, 10).toUpperCase();
    
    document.getElementById('inviteCodeText').textContent = code;
    document.getElementById('inviteCodeDisplay').classList.remove('hidden');
    document.getElementById('generateBtn').style.display = 'none';
    
    showToast('Invite code generated successfully!', 'success');
}

// Copy invite code
function copyInviteCode() {
    const code = document.getElementById('inviteCodeText').textContent;
    navigator.clipboard.writeText(code).then(() => {
        showToast('Code copied to clipboard!', 'success');
    });
}

// Show goal modal
function showGoalModal() {
    // Populate child select
    const select = document.getElementById('goalChildSelect');
    const children = [
        { childID: 1, display_name: 'Emma Explorer' },
        { childID: 2, display_name: 'Sam Scientist' }
    ];
    
    select.innerHTML = '<option value="">Choose a child...</option>' + 
        children.map(child => `<option value="${child.childID}">${child.display_name}</option>`).join('');
    
    // Set default end date to 30 days from now
    const endDate = new Date();
    endDate.setDate(endDate.getDate() + 30);
    document.getElementById('goalEndDate').value = endDate.toISOString().split('T')[0];
    
    document.getElementById('goalModal').classList.remove('hidden');
}

// Close goal modal
function closeGoalModal() {
    document.getElementById('goalModal').classList.add('hidden');
}

// Submit goal
function submitGoal(event) {
    event.preventDefault();
    
    const childId = document.getElementById('goalChildSelect').value;
    const goalType = document.getElementById('goalType').value;
    const description = document.getElementById('goalDescription').value;
    const target = document.getElementById('goalTarget').value;
    const endDate = document.getElementById('goalEndDate').value;
    
    // In production, send to API
    console.log('Creating goal:', { childId, goalType, description, target, endDate });
    
    showToast('Goal created successfully!', 'success');
    closeGoalModal();
    
    // Reload goals tab if currently viewing it
    const activeTab = document.querySelector('.nav-tab.active');
    if (activeTab && activeTab.getAttribute('data-tab') === 'goals') {
        loadGoalsTab();
    }
}

// Show notifications
function showNotifications() {
    document.getElementById('notificationsPanel').classList.remove('hidden');
    loadNotifications();
}

// Close notifications
function closeNotifications() {
    document.getElementById('notificationsPanel').classList.add('hidden');
}

// Load notifications
function loadNotifications() {
    const container = document.getElementById('notificationsList');
    
    // Mock notifications
    const notifications = [
        {
            notificationID: 1,
            title: 'Achievement Unlocked!',
            message: 'Emma Explorer unlocked "Week Warrior" achievement',
            is_read: false,
            created_at: new Date(Date.now() - 3600000).toISOString()
        },
        {
            notificationID: 2,
            title: 'New Child Linked',
            message: 'Sam Scientist has been linked to your account',
            is_read: false,
            created_at: new Date(Date.now() - 7200000).toISOString()
        },
        {
            notificationID: 3,
            title: 'Goal Completed',
            message: 'Emma reached her weekly XP goal!',
            is_read: true,
            created_at: new Date(Date.now() - 86400000).toISOString()
        }
    ];
    
    if (!notifications || notifications.length === 0) {
        container.innerHTML = '<div class="empty-state"><p>No notifications</p></div>';
        return;
    }
    
    container.innerHTML = notifications.map(notif => `
        <div class="notification-item ${notif.is_read ? '' : 'unread'}"
        onclick="markNotificationRead(${notif.notificationID})">
            <div class="notification-title">${notif.title}</div>
            <div class="notification-message">${notif.message}</div>
            <div class="notification-time">${formatTimeAgo(new Date(notif.created_at))}</div>
        </div>
    `).join('');
}

// Load notifications count
function loadNotificationsCount() {
    // Mock unread count
    const unreadCount = 3;
    if (unreadCount > 0) {
        const badge = document.getElementById('notificationBadge');
        badge.textContent = unreadCount;
        badge.classList.remove('hidden');
    }
}

// Mark notification as read
function markNotificationRead(notificationId) {
    // In production, send to API
    console.log('Marking notification as read:', notificationId);
}

// Toggle profile menu
function toggleProfileMenu() {
    const dropdown = document.getElementById('profileDropdown');
    dropdown.classList.toggle('hidden');
}

// Close profile menu when clicking outside
document.addEventListener('click', (e) => {
    const profile = document.querySelector('.parent-profile');
    const dropdown = document.getElementById('profileDropdown');
    
    if (dropdown && !dropdown.classList.contains('hidden')) {
        if (!profile.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    }
});

// View child details
function viewChildDetails(childId) {
    console.log('View child details:', childId);
    // Navigate to detailed view or show modal
}

// View child report
function viewChildReport(childId) {
    switchTab('reports');
    setTimeout(() => {
        document.getElementById('reportChildSelect').value = childId;
        loadChildReport();
    }, 100);
}

// View child activities
function viewChildActivities(childId) {
    console.log('View activities for child:', childId);
    // Navigate to activities view
}

// Export report
function exportReport(childId) {
    showToast('Exporting report...', 'success');
    // In production, generate and download PDF/CSV
}

// Export all reports
function exportAllReports() {
    showToast('Exporting all reports...', 'success');
    // In production, generate comprehensive report
}

// Logout
async function logout() {
    const confirmed = confirm('Are you sure you want to logout?');
    if (confirmed) {
        try {
            // Call logout API to destroy server session
            await fetch('api/auth.php?action=logout', {
                method: 'POST'
            });
        } catch (error) {
            console.error('Logout error:', error);
        }
        
        // Clear local storage
        localStorage.removeItem('brightMindsSession');
        showToast('Logged out successfully', 'success');
        setTimeout(() => {
            window.location.href = 'parent-auth.html';
        }, 1000);
    }
}

// Show toast notification
function showToast(message, type = 'success') {
    let toast = document.getElementById('toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast';
        toast.className = 'toast hidden';
        toast.innerHTML = `
            <span class="toast-icon">✔</span>
            <span class="toast-message"></span>
        `;
        document.body.appendChild(toast);
    }
    
    const toastMessage = toast.querySelector('.toast-message');
    if (toastMessage) {
        toastMessage.textContent = message;
    }
    
    toast.className = 'toast';
    if (type === 'error') {
        toast.classList.add('toast-error');
    } else if (type === 'warning') {
        toast.classList.add('toast-warning');
    }
    
    toast.classList.remove('hidden');
    
    setTimeout(() => {
        toast.classList.add('hidden');
    }, 3000);
}

// Format time ago
function formatTimeAgo(date) {
    const seconds = Math.floor((new Date() - date) / 1000);
    
    const intervals = {
        year: 31536000,
        month: 2592000,
        week: 604800,
        day: 86400,
        hour: 3600,
        minute: 60
    };
    
    for (const [unit, secondsInUnit] of Object.entries(intervals)) {
        const interval = Math.floor(seconds / secondsInUnit);
        if (interval >= 1) {
            return `${interval} ${unit}${interval === 1 ? '' : 's'} ago`;
        }
    }
    
    return 'just now';
}

// Close modals with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.add('hidden');
        });
        closeNotifications();
    }
});
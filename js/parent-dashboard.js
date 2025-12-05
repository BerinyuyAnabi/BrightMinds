// Parent Dashboard JavaScript

const API_BASE = 'api/parent.php';
let currentDashboardData = null; // Store dashboard data for use across functions

/**
 * Convert avatar string to emoji
 */
function getAvatarEmoji(avatarString) {
    const avatarMap = {
        'owl': '🦉',
        'fox': '🦊',
        'rabbit': '🐰',
        'bear': '🐻',
        'cat': '🐱',
        'dog': '🐶',
        'tiger': '🐯',
        'lion': '🦁',
        'monkey': '🐵',
        'panda': '🐼',
        'koala': '🐨',
        'penguin': '🐧'
    };
    // If exact match fails, try case-insensitive. If that fails, return the string itself if it's an emoji, or default to owl
    const key = avatarString?.toLowerCase();
    return avatarMap[key] || (avatarString && avatarString.match(/\p{Emoji}/u) ? avatarString : '🦉');
}

// Initialize on page load
window.addEventListener('DOMContentLoaded', () => {
    checkParentAuth();
    loadParentData();
    initializeTabs();
    loadDashboard();

    // Poll for updates every 30 seconds
    setInterval(() => {
        if (!document.hidden) {
            console.log('Polling for updates...');
            const activeTab = document.querySelector('.nav-tab.active').getAttribute('data-tab');
            if (activeTab === 'overview') {
                loadDashboard();
            } else if (activeTab === 'goals') {
                loadDashboard().then(() => loadGoalsTab());
            } else if (activeTab === 'reports') {
                loadReportsTab();
            }
        }
    }, 30000);
});

// Check if parent is authenticated via API
async function checkParentAuth() {
    // Check localStorage first - if it says parent, allow access immediately
    const sessionData = localStorage.getItem('brightMindsSession');
    if (sessionData) {
        try {
            const userData = JSON.parse(sessionData);
            if (userData.role === 'parent') {
                // User is a parent in localStorage - verify with server in background but don't redirect
                fetch('api/auth.php?action=verify')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.user) {
                            if (data.user.role === 'parent') {
                                // Update localStorage silently
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
                            // Don't redirect even if role changed - let user stay on page
                            // Only redirect if explicitly needed (e.g., session expired)
                        }
                    })
                    .catch(err => console.log('Background auth check failed:', err));
                return; // Allow access, don't redirect
            }
        } catch (e) {
            // If parse fails, continue to server check
        }
    }

    // Only verify with server if localStorage doesn't have valid parent session
    try {
        const response = await fetch('api/auth.php?action=verify');
        const data = await response.json();

        if (!data.success || !data.user) {
            // Session expired or not authenticated
            localStorage.removeItem('brightMindsSession');
            window.location.href = 'parent-auth.html';
            return;
        }

        // Store user data in localStorage regardless of role
        localStorage.setItem('brightMindsSession', JSON.stringify({
            userId: data.user.userId,
            username: data.user.username,
            displayName: data.user.displayName,
            email: data.user.email,
            role: data.user.role,
            childID: data.user.childID
        }));

        // Only redirect if role is explicitly child AND we're on parent dashboard
        // AND localStorage doesn't say we're a parent (to prevent redirect loops)
        if (data.user.role === 'child') {
            const sessionData = localStorage.getItem('brightMindsSession');
            if (sessionData) {
                try {
                    const userData = JSON.parse(sessionData);
                    // If localStorage says we're a parent, don't redirect - trust localStorage
                    if (userData.role === 'parent') {
                        return; // Stay on page, localStorage is more reliable
                    }
                } catch (e) {
                    // If parse fails, continue
                }
            }
            // Only redirect if localStorage also says child or doesn't exist
            const currentPage = window.location.pathname.split('/').pop();
            if (currentPage === 'parent-dashboard.php' || currentPage === 'parent-dashboard.html') {
                // STOP! Do not redirect. This causes the loop.
                console.warn('Session mismatch: Server sees Child, Page is Parent. Stopping redirect.');
                // Show a modal or toast forcing logout
                showToast('Session conflict detected. Please log out and log in again.', 'error');
                // Optional: Provide a logout button or link here if not present
                return;
            }
            return;
        }

        // Update parent name if role is parent
        if (data.user.role === 'parent') {
            const parentNameEl = document.getElementById('parentName');
            if (parentNameEl) {
                parentNameEl.textContent = data.user.displayName || data.user.username;
            }
        }
    } catch (error) {
        console.error('Auth check failed:', error);
        // Don't redirect on error - let user continue if they have localStorage session
        const sessionData = localStorage.getItem('brightMindsSession');
        if (!sessionData) {
            window.location.href = 'parent-auth.html';
        }
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
    switch (tabName) {
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
            // Always refresh dashboard data when opening goals tab to ensure we have latest children
            loadDashboard().then(() => {
                loadGoalsTab();
            });
            break;
        case 'settings':
            loadSettingsTab();
            break;
    }
}

// Load reports tab - populate child select
async function loadReportsTab() {
    const select = document.getElementById('reportChildSelect');

    try {
        // Use cached dashboard data if available, otherwise fetch
        let data = currentDashboardData;
        if (!data) {
            const response = await fetch(`${API_BASE}?action=dashboard&_t=${Date.now()}`);
            data = await response.json();
            if (data.success) {
                currentDashboardData = data;
            }
        }

        if (data && data.success && data.children && data.children.length > 0) {
            select.innerHTML = '<option value="">Select a child...</option>' +
                data.children.map(child => `<option value="${child.childID}">${child.display_name}</option>`).join('');
        } else {
            select.innerHTML = '<option value="">No children linked</option>';
        }
    } catch (error) {
        console.error('Error loading children for reports:', error);
        select.innerHTML = '<option value="">Error loading children</option>';
    }
}

// Load dashboard overview
async function loadDashboard() {
    try {
        // Load real data from API with cache-busting to ensure fresh data
        const response = await fetch(`${API_BASE}?action=dashboard&_t=${Date.now()}`);
        const data = await response.json();

        console.log('Parent dashboard data loaded:', data);
        if (data.children) {
            console.log('Children found:', data.children.length, data.children);
        }

        if (data.success) {
            currentDashboardData = data; // Store for use in other functions
            displayDashboardData(data);
        } else {
            console.error('Failed to load dashboard:', data.message);
            showToast('Failed to load dashboard data', 'error');
            // Fallback to empty state
            displayDashboardData({
                success: true,
                summary: {
                    totalChildren: 0,
                    totalActivities: 0,
                    totalXP: 0,
                    activeToday: 0
                },
                children: [],
                recentAchievements: [],
                unreadNotifications: 0
            });
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showToast('Failed to load dashboard data', 'error');
        // Fallback to empty state
        displayDashboardData({
            success: true,
            summary: {
                totalChildren: 0,
                totalActivities: 0,
                totalXP: 0,
                activeToday: 0
            },
            children: [],
            recentAchievements: [],
            unreadNotifications: 0
        });
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

    container.innerHTML = children.map(child => {
        const avatarEmoji = getAvatarEmoji(child.avatar);
        return `
        <div class="child-card" onclick="viewChildDetails(${child.childID})">
            <div class="child-card-header">
                <div class="child-avatar">${avatarEmoji}</div>
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
                <button onclick="event.stopPropagation(); confirmUnlinkChild(${child.childID}, '${child.display_name}')" style="background: #ef4444; color: white;">🔓 Unlink</button>
            </div>
        </div>
    `;
    }).join('');
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
        const avatarEmoji = getAvatarEmoji(achievement.avatar);
        return `
            <div class="achievement-item">
                <div class="achievement-badge">${achievement.badge_icon}</div>
                <div class="achievement-info">
                    <div class="achievement-child">
                        <span class="achievement-child-avatar">${avatarEmoji}</span>
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

// Load reports tab - populate child select
async function loadReportsTab() {
    const select = document.getElementById('reportChildSelect');

    try {
        // Use cached dashboard data if available, otherwise fetch
        let data = currentDashboardData;
        if (!data) {
            const response = await fetch(`${API_BASE}?action=dashboard&_t=${Date.now()}`);
            data = await response.json();
            if (data.success) {
                currentDashboardData = data;
            }
        }

        if (data && data.success && data.children && data.children.length > 0) {
            select.innerHTML = '<option value="">Select a child...</option>' +
                data.children.map(child => `<option value="${child.childID}">${child.display_name}</option>`).join('');
        } else {
            select.innerHTML = '<option value="">No children linked</option>';
        }
    } catch (error) {
        console.error('Error loading children for reports:', error);
        select.innerHTML = '<option value="">Error loading children</option>';
    }
}

// Load child report - shows daily activities
async function loadChildReport() {
    const select = document.getElementById('reportChildSelect');
    const childId = select.value;
    const container = document.getElementById('reportContent');

    if (!childId) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">📊</div>
                <p>Select a child to view their progress report</p>
            </div>
        `;
        return;
    }

    try {
        const response = await fetch(`${API_BASE}?action=daily-report&childId=${childId}`);
        const data = await response.json();

        if (!data.success) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">❌</div>
                    <p>${data.message || 'Failed to load report'}</p>
                </div>
            `;
            return;
        }

        const stats = data.stats || {};
        const activities = data.activities || [];
        const achievements = data.achievements || [];
        const today = new Date(data.date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        let activitiesHtml = '';
        if (activities.length > 0) {
            activitiesHtml = activities.map(activity => {
                const time = new Date(activity.start_time).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                const icon = activity.activity_type === 'game' ? '🎮' : activity.activity_type === 'quiz' ? '📝' : '📖';
                return `
                    <div class="activity-item" style="background: white; padding: 15px; margin: 10px 0; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <div style="font-size: 2rem;">${icon}</div>
                            <div style="flex: 1;">
                                <div style="font-weight: bold; color: var(--primary-blue);">${activity.activity_title || activity.activity_type}</div>
                                <div style="color: var(--gray); font-size: 0.9rem; margin-top: 5px;">
                                    ${time} • ${activity.activity_type}
                                    ${activity.score ? ` • Score: ${Math.round(activity.score)}%` : ''}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="color: var(--primary-blue); font-weight: bold;">+${activity.xp_earned || 0} XP</div>
                                <div style="color: var(--gray); font-size: 0.9rem;">+${activity.coins_earned || 0} 🪙</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            activitiesHtml = '<div class="empty-state"><p>No activities today yet</p></div>';
        }

        let achievementsHtml = '';
        if (achievements.length > 0) {
            achievementsHtml = achievements.map(ach => {
                const time = new Date(ach.unlocked_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                return `
                    <div style="background: white; padding: 15px; margin: 10px 0; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 15px;">
                        <div style="font-size: 2rem;">${ach.badge_icon || '🏆'}</div>
                        <div style="flex: 1;">
                            <div style="font-weight: bold;">${ach.title}</div>
                            <div style="color: var(--gray); font-size: 0.9rem;">${ach.description}</div>
                            <div style="color: var(--gray); font-size: 0.8rem; margin-top: 5px;">Unlocked at ${time}</div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        container.innerHTML = `
            <div class="section">
                <h2>📅 Daily Report - ${today}</h2>
                <h3 style="color: var(--primary-blue); margin: 20px 0 10px 0;">Today's Summary</h3>
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-icon">✅</div>
                        <div class="summary-data">
                            <div class="summary-value">${stats.total_activities || 0}</div>
                            <div class="summary-label">Activities</div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon">⚡</div>
                        <div class="summary-data">
                            <div class="summary-value">${stats.xp_earned || 0}</div>
                            <div class="summary-label">XP Earned</div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon">🪙</div>
                        <div class="summary-data">
                            <div class="summary-value">${stats.coins_earned || 0}</div>
                            <div class="summary-label">Coins Earned</div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon">⏱️</div>
                        <div class="summary-data">
                            <div class="summary-value">${Math.round(stats.minutes_played || 0)}</div>
                            <div class="summary-label">Minutes Played</div>
                        </div>
                    </div>
                </div>
                
                <h3 style="color: var(--primary-blue); margin: 30px 0 15px 0;">Today's Activities</h3>
                <div style="max-height: 400px; overflow-y: auto;">
                    ${activitiesHtml}
                </div>
                
                ${achievements.length > 0 ? `
                    <h3 style="color: var(--primary-blue); margin: 30px 0 15px 0;">Achievements Unlocked Today</h3>
                    <div style="max-height: 300px; overflow-y: auto;">
                        ${achievementsHtml}
                    </div>
                ` : ''}
                
                <button class="btn btn-primary" onclick="exportReport(${childId})" style="margin-top: 20px;">
                    📥 Export Report
                </button>
            </div>
        `;
    } catch (error) {
        console.error('Error loading report:', error);
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">❌</div>
                <p>Error loading report. Please try again.</p>
            </div>
        `;
    }
}

// Load goals tab
async function loadGoalsTab() {
    const container = document.getElementById('goalsContent');
    const select = document.getElementById('goalChildSelect');

    try {
        // Use cached dashboard data if available, otherwise fetch
        let dashboardData = currentDashboardData;
        if (!dashboardData) {
            const dashboardResponse = await fetch(`${API_BASE}?action=dashboard&_t=${Date.now()}`);
            dashboardData = await dashboardResponse.json();
            if (dashboardData.success) {
                currentDashboardData = dashboardData;
            }
        }

        // Populate child select dropdown in goal modal
        if (select) {
            if (dashboardData && dashboardData.children && dashboardData.children.length > 0) {
                select.innerHTML = '<option value="">Choose a child...</option>' +
                    dashboardData.children.map(child => `<option value="${child.childID}">${child.display_name}</option>`).join('');
            } else {
                select.innerHTML = '<option value="">No children linked</option>';
            }
        }

        if (!dashboardData || !dashboardData.success || !dashboardData.children || dashboardData.children.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">👶</div>
                    <p>No children linked yet. Link a child account first!</p>
                </div>
            `;
            return;
        }

        // Load goals for all children
        const allGoals = [];
        for (const child of dashboardData.children) {
            try {
                const response = await fetch(`${API_BASE}?action=get-goals&childId=${child.childID}`);
                const data = await response.json();
                if (data.success && data.goals) {
                    data.goals.forEach(goal => {
                        goal.childName = child.display_name;
                        goal.childId = child.childID;
                        allGoals.push(goal);
                    });
                }
            } catch (error) {
                console.error(`Error loading goals for child ${child.childID}:`, error);
            }
        }

        if (allGoals.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-icon">🎯</div>
                    <p>No goals set yet. Create a goal to track your child's progress!</p>
                </div>
            `;
            return;
        }

        // Display goals grouped by child
        const goalsByChild = {};
        allGoals.forEach(goal => {
            if (!goalsByChild[goal.childId]) {
                goalsByChild[goal.childId] = [];
            }
            goalsByChild[goal.childId].push(goal);
        });

        container.innerHTML = Object.keys(goalsByChild).map(childId => {
            const childGoals = goalsByChild[childId];
            const childName = childGoals[0].childName;

            return `
                <div style="background: white; padding: 20px; margin: 20px 0; border-radius: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h3 style="color: var(--primary-blue); margin-bottom: 15px;">${childName}</h3>
                    ${childGoals.map(goal => {
                const progress = goal.current_progress || 0;
                const target = goal.target_value;
                const percent = Math.min(100, Math.round((progress / target) * 100));
                const isCompleted = goal.is_completed || percent >= 100;
                const endDate = new Date(goal.end_date);
                const daysLeft = Math.ceil((endDate - new Date()) / (1000 * 60 * 60 * 24));

                return `
                            <div style="background: ${isCompleted ? '#d4edda' : '#f8f9fa'}; padding: 15px; margin: 10px 0; border-radius: 10px; border-left: 4px solid ${isCompleted ? '#28a745' : 'var(--primary-blue)'};">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: bold; color: var(--primary-blue); margin-bottom: 5px;">
                                            ${goal.goal_description}
                                        </div>
                                        <div style="color: var(--gray); font-size: 0.9rem;">
                                            Type: ${goal.goal_type.replace('_', ' ')} • Target: ${target}
                                        </div>
                                    </div>
                                    ${isCompleted ? '<div style="color: #28a745; font-weight: bold;">✓ Completed</div>' : ''}
                                </div>
                                <div style="margin-top: 10px;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                        <span style="font-size: 0.9rem; color: var(--gray);">Progress: ${progress} / ${target}</span>
                                        <span style="font-size: 0.9rem; color: var(--gray);">${percent}%</span>
                                    </div>
                                    <div style="background: #e9ecef; border-radius: 10px; height: 20px; overflow: hidden;">
                                        <div style="background: ${isCompleted ? '#28a745' : 'var(--primary-blue)'}; height: 100%; width: ${percent}%; transition: width 0.3s;"></div>
                                    </div>
                                    <div style="color: var(--gray); font-size: 0.8rem; margin-top: 5px;">
                                        ${daysLeft > 0 ? `${daysLeft} days remaining` : daysLeft === 0 ? 'Ends today' : 'Expired'}
                                    </div>
                                </div>
                            </div>
                        `;
            }).join('')}
                </div>
            `;
        }).join('');
    } catch (error) {
        console.error('Error loading goals:', error);
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-icon">❌</div>
                <p>Error loading goals. Please try again.</p>
            </div>
        `;
    }
}

// Show invite modal
async function showInviteModal() {
    document.getElementById('inviteModal').classList.remove('hidden');

    // Load parent code from API
    try {
        const response = await fetch('api/parent.php?action=get-parent-code');
        const codeData = await response.json();

        if (codeData.success && codeData.parentCode) {
            document.getElementById('inviteCodeText').textContent = codeData.parentCode;
            document.getElementById('inviteCodeDisplay').classList.remove('hidden');
            document.getElementById('generateBtn').style.display = 'none';
        } else {
            // Fallback: show generate button if no code exists
            document.getElementById('inviteCodeDisplay').classList.add('hidden');
            document.getElementById('generateBtn').style.display = 'inline-flex';
        }
    } catch (error) {
        console.error('Error loading parent code:', error);
        document.getElementById('inviteCodeDisplay').classList.add('hidden');
        document.getElementById('generateBtn').style.display = 'inline-flex';
    }
}

// Close invite modal
function closeInviteModal() {
    document.getElementById('inviteModal').classList.add('hidden');
}

// Generate invite code (if needed - should already exist)
async function generateInviteCode() {
    try {
        const response = await fetch('api/parent.php?action=generate-invite', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await response.json();

        // Check for both possible property names (inviteCode or parentCode)
        const code = data.inviteCode || data.parentCode;
        if (data.success && code) {
            document.getElementById('inviteCodeText').textContent = code;
            document.getElementById('inviteCodeDisplay').classList.remove('hidden');
            document.getElementById('generateBtn').style.display = 'none';
            showToast('Invite code generated successfully!', 'success');
        } else {
            showToast(data.message || 'Failed to generate invite code', 'error');
        }
    } catch (error) {
        console.error('Error generating invite code:', error);
        showToast('Failed to generate invite code', 'error');
    }
}

// Copy invite code
function copyInviteCode() {
    const codeElement = document.getElementById('inviteCodeText');
    if (!codeElement) {
        showToast('Invite code not found', 'error');
        return;
    }

    const code = codeElement.textContent.trim();

    // Try modern clipboard API first
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(code)
            .then(() => {
                showToast('Code copied to clipboard!', 'success');
            })
            .catch(err => {
                console.error('Clipboard API failed:', err);
                fallbackCopy(code);
            });
    } else {
        // Fallback for older browsers or non-HTTPS
        fallbackCopy(code);
    }
}

// Fallback copy method for browsers without clipboard API or non-HTTPS
function fallbackCopy(text) {
    // Create a temporary textarea element
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.top = '-9999px';
    textarea.style.left = '-9999px';
    textarea.setAttribute('readonly', '');

    document.body.appendChild(textarea);

    // Select and copy the text
    textarea.select();
    textarea.setSelectionRange(0, 99999); // For mobile devices

    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showToast('Code copied to clipboard!', 'success');
        } else {
            showToast('Failed to copy code. Please copy manually.', 'error');
        }
    } catch (err) {
        console.error('Fallback copy failed:', err);
        showToast('Failed to copy. Please copy manually: ' + text, 'error');
    } finally {
        document.body.removeChild(textarea);
    }
}

// Show goal modal
async function showGoalModal() {
    const select = document.getElementById('goalChildSelect');
    const modal = document.getElementById('goalModal');

    if (!select || !modal) {
        console.error('Goal modal elements not found');
        return;
    }

    // Always fetch fresh data to ensure we have the latest linked children
    try {
        const response = await fetch(`${API_BASE}?action=dashboard&_t=${Date.now()}`);
        const data = await response.json();

        console.log('Goal modal - Dashboard data:', data);
        console.log('Goal modal - Children found:', data.children ? data.children.length : 0);

        if (data.success) {
            // Update cached data
            currentDashboardData = data;

            // Populate child select dropdown
            if (data.children && data.children.length > 0) {
                select.innerHTML = '<option value="">Choose a child...</option>' +
                    data.children.map(child => `<option value="${child.childID}">${child.display_name}</option>`).join('');

                // Set default end date to 30 days from now
                const endDate = new Date();
                endDate.setDate(endDate.getDate() + 30);
                const endDateInput = document.getElementById('goalEndDate');
                if (endDateInput) {
                    endDateInput.value = endDate.toISOString().split('T')[0];
                }

                // Show the modal
                modal.classList.remove('hidden');
            } else {
                // No children linked - show error and don't open modal
                select.innerHTML = '<option value="">No children linked</option>';
                showToast('Please link a child account first. Go to the Children tab to link a child.', 'error');
                return;
            }
        } else {
            console.error('Failed to load dashboard data:', data.message);
            showToast('Error loading children. Please try again.', 'error');
            return;
        }
    } catch (error) {
        console.error('Error loading children for goal modal:', error);
        showToast('Error loading children. Please try again.', 'error');
        return;
    }
}

// Close goal modal
function closeGoalModal() {
    document.getElementById('goalModal').classList.add('hidden');
    // Reset form
    const form = document.getElementById('goalForm');
    if (form) {
        form.reset();
    }
}


// Submit goal
async function submitGoal(event) {
    event.preventDefault();

    const childId = document.getElementById('goalChildSelect').value;
    const goalType = document.getElementById('goalType').value;
    const description = document.getElementById('goalDescription').value;
    const target = document.getElementById('goalTarget').value;
    const endDate = document.getElementById('goalEndDate').value;

    if (!childId) {
        showToast('Please select a child', 'error');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}?action=set-goal`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                childId: childId,
                goalType: goalType,
                goalDescription: description,
                targetValue: target,
                endDate: endDate
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast('Goal created successfully!', 'success');
            closeGoalModal();

            // Reload goals tab if currently viewing it
            const activeTab = document.querySelector('.nav-tab.active');
            if (activeTab && activeTab.getAttribute('data-tab') === 'goals') {
                loadGoalsTab();
            }
        } else {
            showToast(data.message || 'Failed to create goal', 'error');
        }
    } catch (error) {
        console.error('Error creating goal:', error);
        showToast('Error creating goal. Please try again.', 'error');
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
async function loadNotifications() {
    const container = document.getElementById('notificationsList');
    container.innerHTML = '<div class="loading-placeholder">Loading notifications...</div>';

    try {
        const response = await fetch(`${API_BASE}?action=notifications&_t=${Date.now()}`);
        const data = await response.json();

        if (!data.success) {
            container.innerHTML = '<div class="empty-state"><p>Error loading notifications</p></div>';
            return;
        }

        const notifications = data.notifications || [];

        if (notifications.length === 0) {
            container.innerHTML = '<div class="empty-state"><p>No notifications</p></div>';
            updateNotificationBadge(0);
            return;
        }

        // Update badge with unread count
        const unreadCount = notifications.filter(n => !n.is_read || n.is_read === 0).length;
        updateNotificationBadge(unreadCount);

        container.innerHTML = notifications.map(notif => {
            const isRead = notif.is_read === 1 || notif.is_read === true;
            const timeAgo = getTimeAgo(notif.created_at);
            const childName = notif.display_name || 'Your child';

            return `
        <div class="notification-item ${isRead ? '' : 'unread'}"
        onclick="markNotificationRead(${notif.notificationID})">
            <div class="notification-title">${notif.title || 'Notification'}</div>
            <div class="notification-message">${notif.message || ''}</div>
            <div class="notification-time">${timeAgo}</div>
        </div>
    `;
        }).join('');
    } catch (error) {
        console.error('Error loading notifications:', error);
        container.innerHTML = '<div class="empty-state"><p>Error loading notifications</p></div>';
    }
}

// Update notification badge
function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }
}

// Get time ago string
function getTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
    if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
    return date.toLocaleDateString();
}

// Load notifications count
async function loadNotificationsCount() {
    try {
        const response = await fetch(`${API_BASE}?action=notifications&limit=100&_t=${Date.now()}`);
        const data = await response.json();

        if (data.success && data.notifications) {
            const unreadCount = data.notifications.filter(n => !n.is_read || n.is_read === 0).length;
            updateNotificationBadge(unreadCount);
        }
    } catch (error) {
        console.error('Error loading notification count:', error);
    }
}

// Mark notification as read
async function markNotificationRead(notificationId) {
    try {
        const response = await fetch(`${API_BASE}?action=mark-notification-read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ notificationId: notificationId })
        });

        const data = await response.json();

        if (data.success) {
            // Reload notifications
            loadNotifications();
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
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

// View child activities - shows all games, quizzes, and stories
async function viewChildActivities(childId) {
    try {
        const response = await fetch(`${API_BASE}?action=child-activities&childId=${childId}&limit=100`);
        const data = await response.json();

        if (!data.success) {
            showToast(data.message || 'Failed to load activities', 'error');
            return;
        }

        const activities = data.activities || [];

        // Create modal to display activities
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.id = 'activitiesModal';
        modal.innerHTML = `
            <div class="modal-overlay" onclick="closeActivitiesModal()"></div>
            <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow-y: auto;">
                <button class="modal-close" onclick="closeActivitiesModal()">×</button>
                <h2>📝 All Activities</h2>
                <div id="activitiesList" style="margin-top: 20px;">
                    ${activities.length > 0 ? activities.map(activity => {
            const date = new Date(activity.start_time);
            const dateStr = date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
            const timeStr = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            const icon = activity.activity_type === 'game' ? '🎮' : activity.activity_type === 'quiz' ? '📝' : '📖';
            const duration = activity.duration_seconds ? Math.round(activity.duration_seconds / 60) : 0;

            return `
                            <div class="activity-item" style="background: white; padding: 20px; margin: 15px 0; border-radius: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border-left: 4px solid var(--primary-blue);">
                                <div style="display: flex; align-items: start; gap: 20px;">
                                    <div style="font-size: 3rem;">${icon}</div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: bold; font-size: 1.2rem; color: var(--primary-blue); margin-bottom: 8px;">
                                            ${activity.activity_title || activity.activity_type}
                                        </div>
                                        <div style="color: var(--gray); margin-bottom: 10px;">
                                            <div>📅 ${dateStr} at ${timeStr}</div>
                                            ${duration > 0 ? `<div>⏱️ Duration: ${duration} minutes</div>` : ''}
                                            ${activity.score !== null ? `<div>📊 Score: ${Math.round(activity.score)}%</div>` : ''}
                                        </div>
                                        <div style="display: flex; gap: 20px; margin-top: 10px;">
                                            <div style="color: var(--primary-blue); font-weight: bold;">
                                                ⚡ +${activity.xp_earned || 0} XP
                                            </div>
                                            <div style="color: var(--gray); font-weight: bold;">
                                                🪙 +${activity.coins_earned || 0} Coins
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
        }).join('') : '<div class="empty-state"><p>No activities yet</p></div>'}
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        modal.classList.remove('hidden');
    } catch (error) {
        console.error('Error loading activities:', error);
        showToast('Error loading activities. Please try again.', 'error');
    }
}

// Close activities modal
function closeActivitiesModal() {
    const modal = document.getElementById('activitiesModal');
    if (modal) {
        modal.classList.add('hidden');
        setTimeout(() => modal.remove(), 300);
    }
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

// Confirm unlink child
function confirmUnlinkChild(childId, childName) {
    const confirmed = confirm(
        `Are you sure you want to unlink ${childName}?\n\n` +
        'You will no longer be able to see their progress and activities.\n' +
        'The child can link back to your account using your parent code.'
    );

    if (confirmed) {
        unlinkChild(childId);
    }
}

// Unlink child from parent account
async function unlinkChild(childId) {
    try {
        const response = await fetch(`${API_BASE}?action=remove-child`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                childId: childId
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast(`Child unlinked successfully`, 'success');
            // Reload dashboard to refresh children list
            setTimeout(() => {
                loadDashboard();
            }, 1000);
        } else {
            showToast(data.message || 'Failed to unlink child', 'error');
        }
    } catch (error) {
        console.error('Error unlinking child:', error);
        showToast('An error occurred. Please try again.', 'error');
    }
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

// Load settings tab
async function loadSettingsTab() {
    try {
        const sessionData = JSON.parse(localStorage.getItem('brightMindsSession'));
        if (sessionData) {
            document.getElementById('parentEmail').value = sessionData.email || '';
            document.getElementById('parentUsername').value = sessionData.username || '';
            document.getElementById('parentDisplayName').value = sessionData.displayName || sessionData.username || '';
        }
    } catch (error) {
        console.error('Error loading settings:', error);
    }
}

// Update display name
async function updateDisplayName() {
    const displayName = document.getElementById('settingsDisplayName').value;
    if (!displayName) {
        showToast('Display name cannot be empty', 'error');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}?action=update-display-name`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ displayName })
        });
        const data = await response.json();

        if (data.success) {
            showToast('Display name updated successfully', 'success');
            const sessionData = JSON.parse(localStorage.getItem('brightMindsSession'));
            sessionData.displayName = displayName;
            localStorage.setItem('brightMindsSession', JSON.stringify(sessionData));
            document.getElementById('parentName').textContent = displayName;
        } else {
            showToast(data.message || 'Failed to update display name', 'error');
        }
    } catch (error) {
        console.error('Error updating display name:', error);
        showToast('An error occurred. Please try again.', 'error');
    }
}

// Load settings tab
async function loadSettingsTab() {
    // Load parent data to populate settings
    const sessionData = localStorage.getItem('brightMindsSession');
    if (sessionData) {
        try {
            const userData = JSON.parse(sessionData);
            const displayNameField = document.getElementById('settingsDisplayName');
            const emailField = document.getElementById('settingsEmail');
            const usernameField = document.getElementById('settingsUsername');

            if (displayNameField) displayNameField.value = userData.displayName || '';
            if (emailField) emailField.value = userData.email || '';
            if (usernameField) usernameField.value = userData.username || '';
        } catch (e) {
            console.error('Error loading settings data:', e);
        }
    }

    // Load saved notification preferences
    const notifSettings = localStorage.getItem('parentNotificationSettings');
    if (notifSettings) {
        try {
            const settings = JSON.parse(notifSettings);
            const goalCompleted = document.getElementById('notifyGoalCompleted');
            const achievements = document.getElementById('notifyAchievements');
            const dailyReports = document.getElementById('notifyDailyReports');

            if (goalCompleted) goalCompleted.checked = settings.goalCompleted !== false;
            if (achievements) achievements.checked = settings.achievements !== false;
            if (dailyReports) dailyReports.checked = settings.dailyReports !== false;
        } catch (e) {
            console.error('Error loading notification preferences:', e);
        }
    }

    // Load saved privacy settings
    const privacySettings = localStorage.getItem('parentPrivacySettings');
    if (privacySettings) {
        try {
            const settings = JSON.parse(privacySettings);
            const shareProgress = document.getElementById('shareProgress');
            if (shareProgress) shareProgress.checked = settings.shareProgress !== false;
        } catch (e) {
            console.error('Error loading privacy settings:', e);
        }
    }
}

// Update password
async function updatePassword() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmNewPassword').value;

    if (!currentPassword || !newPassword || !confirmPassword) {
        showToast('Please fill all password fields', 'error');
        return;
    }

    if (newPassword.length < 6) {
        showToast('New password must be at least 6 characters', 'error');
        return;
    }

    if (newPassword !== confirmPassword) {
        showToast('New passwords do not match', 'error');
        return;
    }

    try {
        const response = await fetch(`${API_BASE}?action=update-password`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ currentPassword, newPassword })
        });
        const data = await response.json();

        if (data.success) {
            showToast('Password updated successfully', 'success');
            document.getElementById('currentPassword').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('confirmPassword').value = '';
        } else {
            showToast(data.message || 'Failed to update password', 'error');
        }
    } catch (error) {
        console.error('Error updating password:', error);
        showToast('An error occurred. Please try again.', 'error');
    }
}

// Save notification preferences
function saveNotificationPreferences() {
    const settings = {
        goalCompleted: document.getElementById('notifyGoalCompleted').checked,
        achievements: document.getElementById('notifyAchievements').checked,
        dailyReports: document.getElementById('notifyDailyReports').checked
    };

    localStorage.setItem('parentNotificationSettings', JSON.stringify(settings));
    showToast('Notification preferences saved', 'success');
}

// Save privacy settings
function savePrivacySettings() {
    const shareProgress = document.getElementById('shareProgress').checked;
    localStorage.setItem('parentPrivacySettings', JSON.stringify({ shareProgress }));
    showToast('Privacy settings saved', 'success');
}

// Export all data
function exportAllData() {
    showToast('Data export feature coming soon!', 'info');
}

// Delete account
function deleteAccount() {
    if (confirm('Are you sure you want to delete your account? This action cannot be undone.')) {
        showToast('Account deletion feature coming soon!', 'info');
    }
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
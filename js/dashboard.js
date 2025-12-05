/**
 * Child Dashboard JavaScript
 * Handles profile loading, parent linking, and activity display
 */

const API_BASE = 'api/dashboard.php';
let currentProfile = null;

// Initialize on page load
window.addEventListener('DOMContentLoaded', () => {
    console.log('=== Dashboard.js loaded ===');

    // Skip dashboard initialization on quiz pages and other non-dashboard pages
    const currentPage = window.location.pathname.split('/').pop();
    const currentPath = window.location.pathname;
    console.log('Current page:', currentPage);

    // Pages that should NOT run dashboard auth checks
    const skipAuthPages = [
        'quiz-take.html',
        'quiz-take-backup.html',
        'games.html',
        'stories.html',
        'achievements.html',
        'story-read.html'
    ];

    // Also skip if in games folder
    if (currentPath.includes('/games/') || currentPage.includes('game')) {
        console.log('Skipping dashboard initialization on game page');
        return;
    }

    if (skipAuthPages.includes(currentPage)) {
        console.log('Skipping dashboard initialization on:', currentPage);
        return;
    }

    // Only run auth check on actual dashboard page
    if (currentPage !== 'dashboard.php' && currentPage !== 'dashboard.html') {
        console.log('Not on dashboard page, skipping auth check');
        return;
    }

    // Check if we have a session before initializing
    const sessionData = localStorage.getItem('brightMindsSession');
    console.log('Session data exists:', !!sessionData);

    if (!sessionData) {
        console.log('No session found, skipping dashboard initialization');
        return;
    }

    console.log('Initializing dashboard...');
    checkAuth();
    loadProfile();
    loadParentInfo(); // This will hide parent section if linked
    loadRecentActivities();
    loadGoals(); // This will show goals section only if goals exist

    // Poll for updates every 30 seconds
    setInterval(() => {
        if (!document.hidden) {
            console.log('Polling for updates...');
            loadProfile();
            loadRecentActivities();
            loadGoals();
        }
    }, 30000);
});

// Listen for profile update events (triggered when rewards are awarded)
window.addEventListener('profileUpdated', () => {
    // Only refresh if we have a valid session
    const sessionData = localStorage.getItem('brightMindsSession');
    if (!sessionData) return;

    console.log('Profile update event received, refreshing...');
    loadProfile();
    loadRecentActivities();
    loadGoals(); // Also refresh goals
});

// Refresh profile when page becomes visible (user returns from game)
document.addEventListener('visibilitychange', () => {
    // Only refresh if we have a valid session
    const sessionData = localStorage.getItem('brightMindsSession');
    if (!sessionData) return;

    if (!document.hidden && typeof loadProfile === 'function') {
        console.log('Page became visible, refreshing profile...');
        // User returned to page, refresh profile to show updated coins
        setTimeout(() => {
            loadProfile();
            loadRecentActivities();
            loadGoals(); // Reload goals when page becomes visible
        }, 100);
    }
});

// Also refresh when window gets focus (in case visibilitychange doesn't fire)
window.addEventListener('focus', () => {
    // Only refresh if we have a valid session
    const sessionData = localStorage.getItem('brightMindsSession');
    if (!sessionData) return;

    console.log('Window focused, refreshing profile...');
    if (typeof loadProfile === 'function') {
        setTimeout(() => {
            loadProfile();
            loadRecentActivities();
            loadGoals(); // Reload goals when window gets focus
        }, 100);
    }
});

// Refresh when page loads (in case user navigated back via browser back button)
window.addEventListener('pageshow', (event) => {
    // Only refresh if we have a valid session
    const sessionData = localStorage.getItem('brightMindsSession');
    if (!sessionData) return;

    if (event.persisted) {
        console.log('Page loaded from cache, refreshing profile...');
        loadProfile();
        loadRecentActivities();
        loadGoals(); // Reload goals
    }
});

/**
 * Check if user is authenticated
 * Only runs on dashboard.php page - doesn't redirect if already on valid child pages
 */
async function checkAuth() {
    // Only redirect if we're actually on the dashboard page and role is wrong
    const currentPage = window.location.pathname.split('/').pop();
    if (currentPage !== 'dashboard.php' && currentPage !== 'dashboard.html') {
        return; // Don't check/redirect on other pages
    }

    // Check localStorage first - if it says child, allow access immediately
    const sessionData = localStorage.getItem('brightMindsSession');
    if (sessionData) {
        try {
            const userData = JSON.parse(sessionData);
            if (userData.role === 'child') {
                // User is a child in localStorage - allow access immediately
                // Skip background check if we just linked to parent to prevent any redirect
                if (!window._justLinkedToParent) {
                    // Only do background check if we didn't just link
                    fetch('api/auth.php?action=verify')
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.user) {
                                // Always preserve child role in localStorage
                                const roleToStore = data.user.role === 'child' ? 'child' : 'child'; // Always child
                                localStorage.setItem('brightMindsSession', JSON.stringify({
                                    userId: data.user.userId,
                                    username: data.user.username,
                                    displayName: data.user.displayName,
                                    email: data.user.email,
                                    role: roleToStore, // Always keep as child
                                    avatar: data.user.avatar,
                                    childID: data.user.childID
                                }));
                            }
                        })
                        .catch(err => console.log('Background auth check failed:', err));
                }
                return; // Allow access, don't redirect - child stays on child dashboard
            }
        } catch (e) {
            // If parse fails, continue to server check
        }
    }

    // Only verify with server if localStorage doesn't have valid child session
    // BUT: Always trust localStorage if it says child - don't let server override it
    const existingSession = localStorage.getItem('brightMindsSession');
    let existingRole = null;
    if (existingSession) {
        try {
            const existingData = JSON.parse(existingSession);
            existingRole = existingData.role;
        } catch (e) {
            // If parse fails, continue
        }
    }

    try {
        const response = await fetch('api/auth.php?action=verify');
        const data = await response.json();

        if (!data.success || !data.user) {
            // Only remove session if localStorage doesn't say child
            if (existingRole !== 'child') {
                localStorage.removeItem('brightMindsSession');
                // Only redirect if we're on dashboard
                if (currentPage === 'dashboard.php' || currentPage === 'dashboard.html') {
                    window.location.href = 'index.html';
                }
            }
            return;
        }

        // CRITICAL: If localStorage says child, ALWAYS preserve child role
        // Even if server returns parent role, trust localStorage
        let roleToStore = data.user.role;
        if (existingRole === 'child') {
            roleToStore = 'child'; // Always preserve child role if localStorage says child
        }

        localStorage.setItem('brightMindsSession', JSON.stringify({
            userId: data.user.userId,
            username: data.user.username,
            displayName: data.user.displayName,
            email: data.user.email,
            role: roleToStore, // Use preserved role
            avatar: data.user.avatar,
            childID: data.user.childID
        }));

        // NEVER redirect if localStorage says child - trust localStorage completely
        if (existingRole === 'child') {
            console.log('localStorage says child, staying on child dashboard');
            return; // Stay on page, localStorage is authoritative
        }

        // Only redirect if role is explicitly parent AND localStorage doesn't say child
        // AND we're on child dashboard
        if (data.user.role === 'parent' && existingRole !== 'child') {
            // Don't redirect if we just linked to a parent
            if (window._justLinkedToParent) {
                console.log('Just linked to parent, preventing redirect');
                return;
            }

            // Only redirect if localStorage also says parent or doesn't exist
            if (currentPage === 'dashboard.php' || currentPage === 'dashboard.html') {
                // window.location.href = 'parent-dashboard.php';
                console.log('User is parent but on child dashboard. Not redirecting to prevent loops.');
            }
            return;
        }

        // If role is child, allow access – no redirect needed
    } catch (error) {
        console.error('Error checking auth:', error);
        // Don't redirect on error - let user continue if they have localStorage session
        // Only redirect if we're on dashboard and no session at all
        const sessionData = localStorage.getItem('brightMindsSession');
        if (!sessionData && (currentPage === 'dashboard.php' || currentPage === 'dashboard.html')) {
            window.location.href = 'index.html';
        }
    }
}

/**
 * Load child profile
 */
async function loadProfile() {
    try {
        // Add cache-busting timestamp to ensure fresh data
        const response = await fetch(`${API_BASE}?action=get-profile&_t=${Date.now()}`);
        const data = await response.json();

        console.log('Profile loaded from API:', data);

        if (data.success && data.profile) {
            currentProfile = data.profile;
            console.log('Displaying profile with coins:', data.profile.coins);
            displayProfile(data.profile);
        } else {
            console.error('Failed to load profile:', data.message);
        }
    } catch (error) {
        console.error('Error loading profile:', error);
        // Use session data as fallback
        const sessionData = localStorage.getItem('brightMindsSession');
        if (sessionData) {
            const userData = JSON.parse(sessionData);
            const userNameEl = document.getElementById('userName');
            if (userNameEl) {
                userNameEl.textContent = userData.displayName || 'Explorer';
            }
        }
    }
}

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
        'dog': '🐶'
    };
    return avatarMap[avatarString?.toLowerCase()] || '🦉';
}

/**
 * Display profile data
 */
function displayProfile(profile) {
    const userNameEl = document.getElementById('userName');
    const userAvatarEl = document.getElementById('userAvatar');
    const xpEl = document.getElementById('xpValue');
    const levelEl = document.getElementById('levelValue');
    const coinsEl = document.getElementById('coinsValue');
    const streakEl = document.getElementById('streakValue');

    console.log('Updating dashboard display. Coins:', profile.coins, 'XP:', profile.total_xp);

    if (userNameEl) userNameEl.textContent = profile.display_name || 'Explorer';
    if (userAvatarEl) {
        // Convert avatar string to emoji
        const avatarEmoji = getAvatarEmoji(profile.avatar);
        userAvatarEl.textContent = avatarEmoji;
    }
    if (xpEl) {
        xpEl.textContent = profile.total_xp || 0;
        console.log('XP element updated to:', xpEl.textContent);
    }
    if (levelEl) {
        levelEl.textContent = profile.current_level || 1;
        console.log('Level element updated to:', levelEl.textContent);
    }
    if (coinsEl) {
        const coinsValue = parseInt(profile.coins) || 0;
        coinsEl.textContent = coinsValue;
        console.log('Coins element updated to:', coinsEl.textContent, 'from profile.coins:', profile.coins);
    } else {
        console.error('coinsValue element not found in DOM!');
    }
    if (streakEl) streakEl.textContent = profile.streak_days || 0;
}

/**
 * Load parent information
 */
async function loadParentInfo() {
    try {
        const response = await fetch(`${API_BASE}?action=get-parent-info`);
        const data = await response.json();

        if (data.success) {
            if (data.hasParent && data.parent) {
                showLinkedView(data.parent);
            } else {
                showNotLinkedView();
            }
        }
    } catch (error) {
        console.error('Error loading parent info:', error);
        showNotLinkedView();
    }
}

/**
 * Show "not linked" view
 */
function showNotLinkedView() {
    const notLinkedView = document.getElementById('notLinkedView');
    const linkedView = document.getElementById('linkedView');

    if (notLinkedView) {
        notLinkedView.classList.remove('hidden');
    }
    if (linkedView) {
        linkedView.classList.add('hidden');
    }
}

/**
 * Show "linked" view - hide entire parent section after linking
 * Child should only see goals, not parent info
 */
function showLinkedView(parent) {
    // Hide the entire parent linking section after linking
    // Child should only see goals (if any), not parent account info
    const parentLinkSection = document.getElementById('parentLinkSection');
    if (parentLinkSection) {
        parentLinkSection.classList.add('hidden');
    }

    // Also hide the linked view if it exists (shouldn't be visible anyway)
    const notLinkedView = document.getElementById('notLinkedView');
    const linkedView = document.getElementById('linkedView');
    if (notLinkedView) {
        notLinkedView.classList.add('hidden');
    }
    if (linkedView) {
        linkedView.classList.add('hidden');
    }
}

/**
 * Link to parent account
 */
async function linkToParent() {
    const inviteCodeInput = document.getElementById('parentInviteCode');
    const inviteCode = inviteCodeInput.value.trim().toUpperCase();

    if (!inviteCode) {
        showToast('Please enter a parent code', 'error');
        return;
    }

    // Accept both formats:
    // 1. PAR-XXXXX format (from users.parent_code)
    // 2. 8-character alphanumeric (from parent_invites table)
    if (!inviteCode.match(/^PAR-[A-Z0-9]{5}$/) && !inviteCode.match(/^[A-Z0-9]{6,10}$/)) {
        showToast('Invalid code format', 'error');
        return;
    }

    try {
        // First verify the code
        const verifyResponse = await fetch(`${API_BASE}?action=verify-invite-code&code=${inviteCode}`);
        const verifyData = await verifyResponse.json();

        if (!verifyData.success) {
            showToast(verifyData.message || 'Invalid or expired code', 'error');
            return;
        }

        // Ask for confirmation
        const parentInfo = verifyData.parentInfo;
        const confirmMsg = `Link your account to ${parentInfo.username}?\n\nThey will be able to see your progress and activities.`;

        if (!confirm(confirmMsg)) {
            return;
        }

        // Link to parent - send code in URL parameter
        const linkResponse = await fetch(`${API_BASE}?action=link-to-parent&code=${encodeURIComponent(inviteCode)}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const linkData = await linkResponse.json();
        console.log('Link to parent response:', linkData);

        if (linkData.success) {
            showToast('Successfully linked to parent account! 🎉', 'success');
            inviteCodeInput.value = '';

            // CRITICAL: Ensure localStorage still has child role to prevent redirect
            const sessionData = localStorage.getItem('brightMindsSession');
            if (sessionData) {
                try {
                    const userData = JSON.parse(sessionData);
                    // Force child role in localStorage - this prevents any redirect
                    userData.role = 'child';
                    localStorage.setItem('brightMindsSession', JSON.stringify(userData));
                } catch (e) {
                    // If parse fails, ignore
                }
            }

            // Prevent any auth checks from redirecting for a longer period
            window._justLinkedToParent = true;
            setTimeout(() => {
                window._justLinkedToParent = false;
            }, 5000); // Extended to 5 seconds

            // Simply update the UI to show linked view - NO page reload, NO redirect
            // Just show that they're linked and load goals
            showLinkedView({
                username: parentInfo.username || 'Parent',
                linkedDate: new Date().toISOString()
            });

            // Load goals (if any exist)
            loadGoals();

            // That's it - child stays on their dashboard, sees linked status and goals
        } else {
            showToast(linkData.message || 'Failed to link account', 'error');
        }
    } catch (error) {
        console.error('Error linking to parent:', error);
        showToast('An error occurred. Please try again.', 'error');
    }
}

// Unlink functionality removed - only parent can unlink children

/**
 * Load recent activities
 */
async function loadRecentActivities() {
    const container = document.querySelector('.activity-items');
    if (!container) return;

    try {
        const response = await fetch(`${API_BASE}?action=get-recent-activities&limit=5`);
        const data = await response.json();

        if (data.success && data.activities && data.activities.length > 0) {
            displayActivities(data.activities);
        } else {
            container.innerHTML = `
                <div class="empty-state">
                    <p>No activities yet. Start your learning journey!</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading activities:', error);
        container.innerHTML = `
            <div class="empty-state">
                <p>Unable to load activities</p>
            </div>
        `;
    }
}

/**
 * Display activities
 */
function displayActivities(activities) {
    const container = document.querySelector('.activity-items');
    if (!container) return;

    container.innerHTML = activities.map(activity => {
        const activityIcon = getActivityIcon(activity.activity_type);
        const date = new Date(activity.start_time);
        const timeAgo = formatTimeAgo(date);

        return `
            <div class="activity-item">
                <div class="activity-icon">${activityIcon}</div>
                <div class="activity-info">
                    <div class="activity-title">${activity.activity_title || activity.activity_type}</div>
                    <div class="activity-meta">
                        <span>⚡ ${activity.xp_earned || 0} XP</span>
                        <span>🪙 ${activity.coins_earned || 0} coins</span>
                        <span>${timeAgo}</span>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Get activity icon based on type
 */
function getActivityIcon(type) {
    const icons = {
        'game': '🎮',
        'quiz': '📝',
        'story': '📖',
        'achievement': '🏆'
    };
    return icons[type] || '📚';
}

/**
 * Format time ago
 */
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

/**
 * Logout
 */
async function logout() {
    const confirmed = confirm('Are you sure you want to logout?');
    if (confirmed) {
        try {
            // Call logout API to destroy server session
            await fetch('api/auth.php?action=logout', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
        } catch (error) {
            console.error('Logout API error:', error);
        }

        // Remove local session data
        localStorage.removeItem('brightMindsSession');

        showToast('Logged out successfully', 'success');

        setTimeout(() => {
            window.location.href = 'index.html';
        }, 1000);
    }
}

/**
 * Show toast notification
 */
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

// Allow Enter key to submit parent code
document.addEventListener('DOMContentLoaded', () => {
    const parentCodeInput = document.getElementById('parentInviteCode');
    if (parentCodeInput) {
        parentCodeInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                linkToParent();
            }
        });
    }
});

/**
 * Start a game session (called when game begins)
 * @param {number} gameId - The game ID (1-5)
 * @returns {Promise<number>} - Session ID
 */
async function startGameSession(gameId) {
    try {
        const response = await fetch('api/games.php?action=start', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ gameId: gameId })
        });

        const data = await response.json();
        if (data.success) {
            return data.sessionId;
        }
        console.error('Failed to start game session:', data.message);
        return null;
    } catch (error) {
        console.error('Error starting game session:', error);
        return null;
    }
}

/**
 * Award XP and coins for game completion
 * This function saves the game session and awards rewards properly
 * @param {number} xpAmount - XP to award
 * @param {number} coinAmount - Coins to award
 * @param {number} gameId - Game ID (1-5) - Required
 * @param {number} sessionId - Session ID (if game session was started)
 * @param {number} score - Final score (0-100)
 * @param {boolean} completed - Whether game was completed
 */





async function awardXP(xp, coins = 0) {
    console.log('=== awardXP called ===');
    console.log('XP:', xp, 'Coins:', coins);

    const sessionData = localStorage.getItem('brightMindsSession');
    if (!sessionData) {
        console.error('No session data found!');
        showToast('Session expired. Please log in again.', 'error');
        return;
    }

    let userData;
    try {
        userData = JSON.parse(sessionData);
        console.log('Current user data:', userData);
    } catch (e) {
        console.error('Failed to parse session data:', e);
        showToast('Invalid session. Please log in again.', 'error');
        return;
    }

    const oldLevel = userData.level || 1;

    try {
        // Detect game ID from URL
        let gameId = null;
        const pathname = window.location.pathname;
        console.log('Current pathname:', pathname);
        const gameMatch = pathname.match(/game(\d+)\.html/i);
        if (gameMatch) {
            gameId = parseInt(gameMatch[1]);
            console.log('Detected game ID:', gameId);
        }

        if (!gameId) {
            console.error('Could not detect game ID from URL:', pathname);
            showToast('Error: Could not save rewards. Please try again.', 'error');
            return;
        }

        // Determine API path based on current location
        // If we're in /games/ folder, use ../api/, otherwise use api/
        const apiPath = window.location.pathname.includes('/games/') ? '../api/games.php' : 'api/games.php';
        console.log('Using API path:', apiPath);

        // Send rewards to backend API
        const response = await fetch(`${apiPath}?action=award`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                gameId: gameId,
                xpEarned: parseInt(xp) || 0,
                coinsEarned: parseInt(coins) || 0,
                score: 100,
                completed: true
            })
        });

        // Check if response is ok
        if (!response.ok) {
            const errorText = await response.text();
            console.error('HTTP Error:', response.status, errorText);
            showToast(`Server error: ${response.status}`, 'error');
            return;
        }

        const data = await response.json();
        console.log('=== Award API response ===');
        console.log('Full response:', data);
        console.log('Success:', data.success);
        console.log('Stats:', data.stats);

        if (!data.success) {
            console.error('Failed to award rewards:', data.message);
            showToast('Failed to save rewards: ' + (data.message || 'Unknown error'), 'error');
            return;
        }

        console.log('API call successful!');

        // Update localStorage with server response
        if (data.stats) {
            console.log('Updating localStorage with server stats...');
            console.log('Before - coins:', userData.coins, 'xp:', userData.xp);
            userData.xp = data.stats.total_xp;
            userData.level = data.stats.current_level;
            userData.coins = data.stats.coins;
            userData.streak = data.stats.streak_days;
            localStorage.setItem('brightMindsSession', JSON.stringify(userData));
            console.log('After - coins:', userData.coins, 'xp:', userData.xp);
        } else {
            console.warn('No stats in response, using fallback...');
            // Fallback: update locally
            userData.xp = (userData.xp || 0) + xp;
            userData.coins = (userData.coins || 0) + coins;
            const newLevel = Math.floor(userData.xp / 100) + 1;
            userData.level = newLevel;
            localStorage.setItem('brightMindsSession', JSON.stringify(userData));
        }

        const newLevel = userData.level;
        const leveledUp = newLevel > oldLevel;

        // Update UI immediately
        if (typeof updateStats === 'function') {
            updateStats(userData);
        }

        // Dispatch custom event to notify dashboard to refresh
        window.dispatchEvent(new CustomEvent('profileUpdated', {
            detail: { stats: data.stats }
        }));

        // Show celebration animations
        if (window.Celebrations) {
            // Show confetti and success effects
            Celebrations.showSuccess('Great Job!', xp, coins);

            // Show level up modal if leveled up
            if (leveledUp) {
                setTimeout(() => {
                    Celebrations.showLevelUp(newLevel);
                }, 1500);
            }
        } else {
            // Fallback to toast notifications
            if (leveledUp) {
                showToast(`Level Up! You're now Level ${newLevel}!`, 'success');
                setTimeout(() => {
                    showToast(`+${xp} XP, +${coins} Coins earned!`, 'success');
                }, 2000);
            } else {
                showToast(`+${xp} XP, +${coins} Coins earned!`, 'success');
            }
        }

        // Dispatch custom event to notify dashboard to refresh
        window.dispatchEvent(new CustomEvent('profileUpdated', {
            detail: { stats: data.stats }
        }));

        // Refresh profile to show updated stats (only if on dashboard page)
        setTimeout(() => {
            if (typeof loadProfile === 'function' && document.getElementById('userName')) {
                loadProfile().catch(err => {
                    console.log('Profile refresh skipped (not on dashboard):', err);
                });
            }
        }, 500);

        return data;

    } catch (error) {
        console.error('Error awarding XP:', error);
        console.error('Error details:', {
            message: error.message,
            stack: error.stack,
            xp: xp,
            coins: coins
        });
        showToast('Error saving rewards: ' + error.message, 'error');
    }
}


/**
 * Update stats display in UI
 */
function updateStats(userData) {
    console.log('updateStats called with:', userData);
    if (document.getElementById('xpValue')) {
        const xpValue = userData.total_xp || userData.xp || 0;
        document.getElementById('xpValue').textContent = xpValue;
        console.log('Updated XP display to:', xpValue);
    }
    if (document.getElementById('levelValue')) {
        const levelValue = userData.current_level || userData.level || 1;
        document.getElementById('levelValue').textContent = levelValue;
        console.log('Updated level display to:', levelValue);
    }
    if (document.getElementById('coinsValue')) {
        const coinsValue = userData.coins || 0;
        document.getElementById('coinsValue').textContent = coinsValue;
        console.log('Updated coins display to:', coinsValue);
    }
    if (document.getElementById('streakValue')) {
        const streakValue = userData.streak_days || userData.streak || 0;
        document.getElementById('streakValue').textContent = streakValue;
        console.log('Updated streak display to:', streakValue);
    }
}

/**
 * Update local storage stats
 */
function updateLocalStats(stats) {
    try {
        const sessionData = localStorage.getItem('brightMindsSession');
        if (sessionData) {
            const userData = JSON.parse(sessionData);
            if (stats) {
                userData.xp = stats.total_xp || userData.xp || 0;
                userData.level = stats.current_level || userData.level || 1;
                userData.coins = stats.coins || userData.coins || 0;
                userData.streak = stats.streak_days || userData.streak || 0;
                localStorage.setItem('brightMindsSession', JSON.stringify(userData));
            }
        }
    } catch (error) {
        console.error('Error updating local stats:', error);
    }
}

/**
 * Load goals for child dashboard
 * Only shows goals section if goals exist
 */
async function loadGoals() {
    const goalsSection = document.getElementById('goalsSection');
    const goalsList = document.getElementById('goalsList');

    if (!goalsSection || !goalsList) {
        return; // Goals section doesn't exist on this page
    }

    try {
        const response = await fetch(`${API_BASE}?action=get-goals&_t=${Date.now()}`);
        const data = await response.json();

        console.log('Goals API response:', data);

        if (!data.success) {
            console.error('Goals API error:', data.message);
            // Hide goals section if error
            goalsSection.classList.add('hidden');
            return;
        }

        // Filter to show only active goals (using status field)
        const activeGoals = data.goals ? data.goals.filter(goal => {
            const endDate = new Date(goal.end_date);
            return goal.status === 'active' && endDate >= new Date();
        }) : [];

        // If no goals exist, hide the entire goals section
        if (!data.goals || data.goals.length === 0 || activeGoals.length === 0) {
            goalsSection.classList.add('hidden');
            return;
        }

        // Show goals section and display goals
        goalsSection.classList.remove('hidden');
        goalsList.innerHTML = activeGoals.map(goal => {
            const progress = goal.current_progress || 0;
            const target = goal.target_value;
            const percent = Math.min(100, Math.round((progress / target) * 100));
            const endDate = new Date(goal.end_date);
            const daysLeft = Math.ceil((endDate - new Date()) / (1000 * 60 * 60 * 24));

            return `
                <div style="background: rgba(255,255,255,0.15); padding: 15px; margin: 10px 0; border-radius: 10px; backdrop-filter: blur(10px);">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div style="flex: 1;">
                            <div style="font-weight: bold; font-size: 1.1rem; margin-bottom: 5px;">
                                ${goal.goal_description}
                            </div>
                            <div style="opacity: 0.9; font-size: 0.9rem;">
                                ${goal.goal_type.replace('_', ' ')} • Target: ${target}
                            </div>
                        </div>
                    </div>
                    <div style="margin-top: 10px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px; opacity: 0.9;">
                            <span style="font-size: 0.9rem;">Progress: ${progress} / ${target}</span>
                            <span style="font-size: 0.9rem; font-weight: bold;">${percent}%</span>
                        </div>
                        <div style="background: rgba(255,255,255,0.3); border-radius: 10px; height: 20px; overflow: hidden;">
                            <div style="background: white; height: 100%; width: ${percent}%; transition: width 0.3s; border-radius: 10px;"></div>
                        </div>
                        <div style="opacity: 0.8; font-size: 0.8rem; margin-top: 5px;">
                            ${daysLeft > 0 ? `${daysLeft} days left` : daysLeft === 0 ? 'Ends today!' : 'Expired'}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    } catch (error) {
        console.error('Error loading goals:', error);
        goalsList.innerHTML = `
            <div style="text-align: center; padding: 20px; opacity: 0.9;">
                <p>Unable to load goals</p>
            </div>
        `;
    }
}
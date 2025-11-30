/**
 * Child Dashboard JavaScript
 * Handles profile loading, parent linking, and activity display
 */

const API_BASE = 'api/dashboard.php';
let currentProfile = null;

// Initialize on page load
window.addEventListener('DOMContentLoaded', () => {
    // Skip dashboard initialization on quiz pages - they handle their own auth
    const currentPage = window.location.pathname.split('/').pop();
    if (currentPage === 'quiz-take.html' || currentPage.includes('quiz-take')) {
        console.log('Skipping dashboard initialization on quiz page');
        return;
    }
    
    checkAuth();
    loadProfile();
    loadParentInfo();
    loadRecentActivities();
});

let childId = localStorage.getItem('brightMindsSession')['userId'];


// Refresh profile when page becomes visible (user returns from game)
document.addEventListener('visibilitychange', () => {
    if (!document.hidden && typeof loadProfile === 'function') {
        console.log('Page became visible, refreshing profile...');
        // User returned to page, refresh profile to show updated coins
        setTimeout(() => {
            loadProfile();
            loadRecentActivities();
        }, 100);
    }
});

// Also refresh when window gets focus (in case visibilitychange doesn't fire)
window.addEventListener('focus', () => {
    console.log('Window focused, refreshing profile...');
    if (typeof loadProfile === 'function') {
        setTimeout(() => {
            loadProfile();
            loadRecentActivities();
        }, 100);
    }
});

// Refresh when page loads (in case user navigated back via browser back button)
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        console.log('Page loaded from cache, refreshing profile...');
        loadProfile();
        loadRecentActivities();
    }
});

/**
 * Check if user is authenticated
 */
function checkAuth() {
    const sessionData = localStorage.getItem('brightMindsSession');
    if (!sessionData) {
        window.location.href = 'index.html';
        return;
    }
    
    try {
        const userData = JSON.parse(sessionData);
        if (userData.role !== 'child') {
            // Redirect to appropriate dashboard
            if (userData.role === 'parent') {
                window.location.href = 'parent-dashboard.php';
            } else {
                window.location.href = 'index.html';
            }
        }
    } catch (error) {
        console.error('Error checking auth:', error);
        window.location.href = 'index.html';
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
            document.getElementById('userName').textContent = userData.displayName || 'Explorer';
        }
    }
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
    if (userAvatarEl) userAvatarEl.textContent = profile.avatar || '🦉';
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
    const parentSection = document.getElementById('parentLinkSection');
    const notLinkedView = document.getElementById('notLinkedView');
    const linkedView = document.getElementById('linkedView');
    
    if (parentSection && notLinkedView && linkedView) {
        notLinkedView.classList.remove('hidden');
        linkedView.classList.add('hidden');
    }
}

/**
 * Show "linked" view with parent info
 */
function showLinkedView(parent) {
    const parentSection = document.getElementById('parentLinkSection');
    const notLinkedView = document.getElementById('notLinkedView');
    const linkedView = document.getElementById('linkedView');
    const parentUsernameEl = document.getElementById('parentUsername');
    const linkedDateEl = document.getElementById('linkedDate');
    
    if (parentSection && notLinkedView && linkedView) {
        notLinkedView.classList.add('hidden');
        linkedView.classList.remove('hidden');
        
        if (parentUsernameEl) {
            parentUsernameEl.textContent = parent.username || parent.email || 'Parent';
        }
        
        if (linkedDateEl && parent.linkedDate) {
            const date = new Date(parent.linkedDate);
            linkedDateEl.textContent = date.toLocaleDateString();
        }
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
    
    // Validate format (PAR-XXXXX or LINK-XXXX)
    // if (!inviteCode.match(/^(PAR|LINK)-[A-Z0-9]{4,5}$/)) {
    //     showToast('Invalid code format. Use PAR-XXXXX or LINK-XXXX', 'error');
    //     return;
    // }
    
    try {
        // First verify the code
        const verifyResponse = await fetch(`${API_BASE}?action=verify-invite-code&code=${inviteCode}`);
        const verifyData = await verifyResponse.json();
        
        if (!verifyData.success || !verifyData.valid) {
            showToast(verifyData.message || 'Invalid or expired code', 'error');
            return;
        }
        
        // Ask for confirmation
        const parentInfo = verifyData.parentInfo;
        const confirmMsg = `Link your account to ${parentInfo.username}?\n\nThey will be able to see your progress and activities.`;
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        // Link to parent
        const linkResponse = await fetch(`${API_BASE}?action=link-to-parent`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                inviteCode: inviteCode
            })
        });
        
        const linkData = await linkResponse.json();
        
        if (linkData.success) {
            showToast('Successfully linked to parent account! 🎉', 'success');
            inviteCodeInput.value = '';
            
            // Reload parent info
            setTimeout(() => {
                loadParentInfo();
            }, 1500);
        } else {
            showToast(linkData.message || 'Failed to link account', 'error');
        }
    } catch (error) {
        console.error('Error linking to parent:', error);
        showToast('An error occurred. Please try again.', 'error');
    }
}

/**
 * Confirm unlink from parent
 */
function confirmUnlinkParent() {
    const confirmed = confirm(
        'Are you sure you want to unlink from your parent account?\n\n' +
        'Your parent will no longer be able to see your progress.'
    );
    
    if (confirmed) {
        unlinkFromParent();
    }
}

/**
 * Unlink from parent account
 */
async function unlinkFromParent() {
    try {
        const response = await fetch(`${API_BASE}?action=unlink-parent`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('Successfully unlinked from parent account', 'success');
            
            // Show not linked view
            setTimeout(() => {
                loadParentInfo();
            }, 1000);
        } else {
            showToast(data.message || 'Failed to unlink account', 'error');
        }
    } catch (error) {
        console.error('Error unlinking from parent:', error);
        showToast('An error occurred. Please try again.', 'error');
    }
}

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
function logout() {
    const confirmed = confirm('Are you sure you want to logout?');
    if (confirmed) {
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
async function awardXP(xpAmount, coinAmount, gameId = null, sessionId = null, score = 0, completed = true) {
    try {
        // Parse gameId if it's provided
        if (gameId !== null && gameId !== undefined) {
            gameId = parseInt(gameId);
        }
        
        console.log('awardXP called with:', { xpAmount, coinAmount, gameId, sessionId, score });
        console.log('gameId type:', typeof gameId, 'value:', gameId);
        
        // Determine game ID from URL if not provided or invalid
        if (!gameId || isNaN(gameId)) {
            // Try multiple URL patterns
            const pathname = window.location.pathname;
            const href = window.location.href;
            console.log('Trying to detect game ID from pathname:', pathname);
            console.log('Trying to detect game ID from href:', href);
            
            // Pattern 1: /games/game1.html or games/game1.html
            // let gameMatch = pathname.match(/(?:games\/|games\\/)game(\d+)\.html/i);
            let gameMatch = pathname.match(/(?:games\/|games\\)game(\d+)\.html/i);

            if (!gameMatch) {
                // Pattern 2: /game1.html
                gameMatch = pathname.match(/game(\d+)\.html/i);
            }
            if (!gameMatch) {
                // Pattern 3: From href
                gameMatch = href.match(/game(\d+)\.html/i);
            }
            
            if (gameMatch) {
                gameId = parseInt(gameMatch[1]);
                console.log('Auto-detected game ID from URL:', gameId);
            }
        }
        
        // Validate game ID
        if (!gameId || isNaN(gameId) || gameId < 1 || gameId > 10) {
            console.error('Invalid or missing game ID:', gameId);
            console.error('Current URL:', window.location.href);
            console.error('Current pathname:', window.location.pathname);
            alert('Error: Game ID not found. Please contact support if this continues. URL: ' + window.location.href);
            return;
        }
        
        if (!xpAmount && !coinAmount) {
            console.warn('No rewards to award');
            return;
        }
        
        // If we have a session ID, use the proper API endpoint with custom rewards
        if (sessionId) {
            console.log('Using session endpoint with sessionId:', sessionId);
            const response = await fetch('api/games.php?action=end', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    sessionId: sessionId,
                    score: score,
                    completed: completed,
                    xpEarned: xpAmount,
                    coinsEarned: coinAmount
                })
            });
            
            const data = await response.json();
            console.log('Session endpoint response:', data);
            
            if (data.success) {
                // Update local storage with new stats
                updateLocalStats(data.stats);
                console.log('Updated stats:', data.stats);
                
                // Refresh dashboard if on dashboard page
                if (typeof loadProfile === 'function') {
                    loadProfile();
                }
                
                return data;
            } else {
                console.error('Session endpoint failed:', data.message);
            }
        }
        
        // Use the simpler award endpoint for games without sessions
        const requestBody = {
            gameId: parseInt(gameId), // Ensure it's an integer
            xpEarned: parseInt(xpAmount) || 0,
            coinsEarned: parseInt(coinAmount) || 0,
            score: parseFloat(score) || (xpAmount > 0 ? 100 : 0),
            completed: completed !== false
        };
        
        console.log('Using award endpoint with gameId:', gameId);
        console.log('Request body being sent:', requestBody);
        
        const response = await fetch('api/games.php?action=award', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody)
        });
        
        const data = await response.json();
        console.log('Award endpoint response:', data);
        
        if (data.success) {
            // Update local storage with new stats
            updateLocalStats(data.stats);
            console.log('Successfully awarded rewards. Updated stats:', data.stats);
            
            // Refresh dashboard if on dashboard page
            if (typeof loadProfile === 'function') {
                loadProfile();
            }
            
            return data;
        } else {
            console.error('Failed to award XP:', data.message);
            alert('Failed to save rewards: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error awarding XP:', error);
        alert('Error saving rewards. Please check console for details.');
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
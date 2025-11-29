// Dashboard JavaScript

// Check authentication on page load
window.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    loadUserData();
    initializeDashboard();
});

// Check if user is authenticated via API
async function checkAuth() {
    try {
        const response = await fetch('api/auth.php?action=verify');
        const data = await response.json();
        
        if (!data.success) {
            // Session expired or not authenticated
            window.location.href = 'index.html';
            return;
        }
        
        // Store user data in localStorage for client-side use
        if (data.user) {
            localStorage.setItem('brightMindsSession', JSON.stringify({
                userId: data.user.userId,
                username: data.user.username,
                displayName: data.user.displayName,
                email: data.user.email,
                role: data.user.role,
                avatar: data.user.avatar,
                childID: data.user.childID
            }));
        }
    } catch (error) {
        console.error('Auth check failed:', error);
        window.location.href = 'index.html';
    }
}

// Load user data
function loadUserData() {
    const sessionData = localStorage.getItem('brightMindsSession');
    if (!sessionData) return;
    
    try {
        const userData = JSON.parse(sessionData);
        
        // Update user name
        const userNameEl = document.getElementById('userName');
        if (userNameEl) {
            userNameEl.textContent = userData.displayName || userData.username;
        }
        
        // Update avatar
        const userAvatarEl = document.getElementById('userAvatar');
        if (userAvatarEl) {
            const avatarEmojis = {
                'owl': '🦉',
                'fox': '🦊',
                'rabbit': '🐰',
                'bear': '🐻',
                'cat': '🐱',
                'dog': '🐶'
            };
            userAvatarEl.textContent = avatarEmojis[userData.avatar] || '🦉';
        }
        
        // Update stats
        updateStats(userData);
        
    } catch (error) {
        console.error('Error loading user data:', error);
    }
}

// Update user statistics
function updateStats(userData) {
    // Update XP
    const xpEl = document.getElementById('xpValue');
    if (xpEl) {
        animateValue(xpEl, 0, userData.xp || 0, 1000);
    }
    
    // Update Level
    const levelEl = document.getElementById('levelValue');
    if (levelEl) {
        animateValue(levelEl, 0, userData.level || 1, 800);
    }
    
    // Update Coins
    const coinsEl = document.getElementById('coinsValue');
    if (coinsEl) {
        animateValue(coinsEl, 0, userData.coins || 0, 1200);
    }
    
    // Update Streak
    const streakEl = document.getElementById('streakValue');
    if (streakEl) {
        animateValue(streakEl, 0, userData.streak || 0, 600);
    }
}

// Animate number counting
function animateValue(element, start, end, duration) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if ((increment > 0 && current >= end) || (increment < 0 && current <= end)) {
            current = end;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current);
    }, 16);
}

// Initialize dashboard features
function initializeDashboard() {
    // Update daily streak
    updateDailyStreak();
    
    // Load recent activities if on dashboard
    if (document.querySelector('.activity-feed')) {
        loadRecentActivities();
    }
    
    // Load leaderboard if present
    if (document.querySelector('.leaderboard')) {
        loadLeaderboard();
    }
}

// Update daily streak
function updateDailyStreak() {
    const sessionData = localStorage.getItem('brightMindsSession');
    if (!sessionData) return;

    const userData = JSON.parse(sessionData);
    const lastLogin = userData.lastLogin || null;
    const today = new Date().toDateString();

    if (lastLogin !== today) {
        // Check if it's consecutive day
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);

        if (lastLogin === yesterday.toDateString()) {
            userData.streak = (userData.streak || 0) + 1;
        } else if (lastLogin !== null) {
            userData.streak = 1;
        } else {
            userData.streak = 1;
        }

        userData.lastLogin = today;
        localStorage.setItem('brightMindsSession', JSON.stringify(userData));

        // Show streak celebration with animations
        if (userData.streak > 1) {
            setTimeout(() => {
                if (window.Celebrations) {
                    Celebrations.showStreakCelebration(userData.streak);
                }
                showToast(`🔥 ${userData.streak} day streak! Keep it up!`, 'success');
            }, 1500);
        }
    }
}

// Logout function
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
        showToast('Goodbye! See you soon! 👋', 'success');
        setTimeout(() => {
            window.location.href = 'index.html';
        }, 1000);
    }
}

// Load recent activities
function loadRecentActivities() {
    const activities = [
        { icon: '🎮', title: 'Completed Memory Match', reward: '+50 XP', time: '2 hours ago' },
        { icon: '📝', title: 'Finished Math Quiz', reward: '+80 XP', time: '5 hours ago' },
        { icon: '📖', title: 'Read "The Brave Elephant"', reward: '+30 XP', time: '1 day ago' },
        { icon: '🏆', title: 'Unlocked Achievement', reward: '+100 Coins', time: '2 days ago' }
    ];
    
    const feedContainer = document.querySelector('.activity-feed');
    if (!feedContainer) return;
    
    const itemsHTML = activities.map(activity => `
        <div class="activity-item">
            <div class="activity-item-icon">${activity.icon}</div>
            <div class="activity-item-content">
                <div class="activity-item-title">${activity.title}</div>
                <div class="activity-item-time">${activity.time}</div>
            </div>
            <div class="activity-item-reward">${activity.reward}</div>
        </div>
    `).join('');
    
    const existingItems = feedContainer.querySelector('.activity-items');
    if (existingItems) {
        existingItems.innerHTML = itemsHTML;
    }
}

// Load leaderboard
function loadLeaderboard() {
    const leaderboardData = [
        { rank: 1, name: 'Emma Explorer', avatar: '🦉', score: '2,450 XP' },
        { rank: 2, name: 'Sam Scientist', avatar: '🦊', score: '2,180 XP' },
        { rank: 3, name: 'Lucy Learner', avatar: '🐰', score: '1,950 XP' },
        { rank: 4, name: 'Max Mathematics', avatar: '🻭', score: '1,720 XP' },
        { rank: 5, name: 'Olivia Observer', avatar: '🐱', score: '1,580 XP' }
    ];
    
    const leaderboardContainer = document.querySelector('.leaderboard');
    if (!leaderboardContainer) return;
    
    const itemsHTML = leaderboardData.map(item => {
        let rankClass = '';
        if (item.rank === 1) rankClass = 'gold';
        else if (item.rank === 2) rankClass = 'silver';
        else if (item.rank === 3) rankClass = 'bronze';
        
        return `
            <div class="leaderboard-item">
                <div class="leaderboard-rank ${rankClass}">${item.rank}</div>
                <div class="leaderboard-avatar">${item.avatar}</div>
                <div class="leaderboard-info">
                    <div class="leaderboard-name">${item.name}</div>
                    <div class="leaderboard-score">${item.score}</div>
                </div>
            </div>
        `;
    }).join('');
    
    const existingItems = leaderboardContainer.querySelector('.leaderboard-items');
    if (existingItems) {
        existingItems.innerHTML = itemsHTML;
    } else {
        leaderboardContainer.innerHTML += itemsHTML;
    }
}

// Show toast notification
function showToast(message, type = 'success') {
    // Create toast if it doesn't exist
    let toast = document.getElementById('toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'toast';
        toast.className = 'toast hidden';
        toast.innerHTML = `
            <span class="toast-icon">✓</span>
            <span class="toast-message"></span>
        `;
        document.body.appendChild(toast);
    }
    
    const toastMessage = toast.querySelector('.toast-message');
    if (toastMessage) {
        toastMessage.textContent = message;
    }
    
    // Set type
    toast.className = 'toast';
    if (type === 'error') {
        toast.classList.add('toast-error');
    } else if (type === 'warning') {
        toast.classList.add('toast-warning');
    }
    
    // Show toast
    toast.classList.remove('hidden');
    
    // Hide after 3 seconds
    setTimeout(() => {
        toast.classList.add('hidden');
    }, 3000);
}

// Award XP and update user stats
function awardXP(xp, coins = 0) {
    const sessionData = localStorage.getItem('brightMindsSession');
    if (!sessionData) return;

    const userData = JSON.parse(sessionData);
    const oldLevel = userData.level || 1;

    // Add XP
    userData.xp = (userData.xp || 0) + xp;

    // Add coins
    userData.coins = (userData.coins || 0) + coins;

    // Calculate level
    const newLevel = Math.floor(userData.xp / 100) + 1;
    const leveledUp = newLevel > oldLevel;
    userData.level = newLevel;

    // Save updated data
    localStorage.setItem('brightMindsSession', JSON.stringify(userData));

    // Update UI
    updateStats(userData);

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
            showToast(`🎉 Level Up! You're now Level ${newLevel}!`, 'success');
            setTimeout(() => {
                showToast(`+${xp} XP, +${coins} Coins earned!`, 'success');
            }, 2000);
        } else {
            showToast(`+${xp} XP, +${coins} Coins earned!`, 'success');
        }
    }
}

// Export functions for use in other scripts
window.awardXP = awardXP;
window.showToast = showToast;
window.logout = logout;

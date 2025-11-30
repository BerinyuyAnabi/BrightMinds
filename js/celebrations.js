

const Celebrations = {
    // Sound effects using Web Audio API
    audioContext: null,

    init() {
        // Initialize Web Audio API 
        try {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        } catch (e) {
            console.warn('Web Audio API not supported');
        }
    },

    // Play a  tone
    playTone(frequency, duration, type = 'sine') {
        if (!this.audioContext) return;

        const oscillator = this.audioContext.createOscillator();
        const gainNode = this.audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(this.audioContext.destination);

        oscillator.frequency.value = frequency;
        oscillator.type = type;

        gainNode.gain.setValueAtTime(0.3, this.audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + duration);

        oscillator.start(this.audioContext.currentTime);
        oscillator.stop(this.audioContext.currentTime + duration);
    },

    // Sound Effects
    sounds: {
        xpGain() {
            Celebrations.playTone(523.25, 0.1); // C5
            setTimeout(() => Celebrations.playTone(659.25, 0.1), 100); // E5
        },

        coinGain() {
            Celebrations.playTone(880, 0.05); // A5
            setTimeout(() => Celebrations.playTone(1046.5, 0.05), 50); // C6
        },

        levelUp() {
            Celebrations.playTone(523.25, 0.15); // C5
            setTimeout(() => Celebrations.playTone(659.25, 0.15), 150); // E5
            setTimeout(() => Celebrations.playTone(783.99, 0.15), 300); // G5
            setTimeout(() => Celebrations.playTone(1046.5, 0.3), 450); // C6
        },

        achievement() {
            Celebrations.playTone(659.25, 0.2); // E5
            setTimeout(() => Celebrations.playTone(783.99, 0.2), 200); // G5
            setTimeout(() => Celebrations.playTone(1046.5, 0.2), 400); // C6
            setTimeout(() => Celebrations.playTone(1318.5, 0.4), 600); // E6
        },

        success() {
            Celebrations.playTone(783.99, 0.1); // G5
            setTimeout(() => Celebrations.playTone(1046.5, 0.2), 100); // C6
        }
    },

    // Confetti Animation
    showConfetti(duration = 3000, count = 100) {
        const container = document.createElement('div');
        container.className = 'confetti-container';
        document.body.appendChild(container);

        const colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E2'];

        for (let i = 0; i < count; i++) {
            setTimeout(() => {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDelay = Math.random() * 0.5 + 's';
                confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
                container.appendChild(confetti);

                setTimeout(() => confetti.remove(), 3000);
            }, Math.random() * duration * 0.5);
        }

        setTimeout(() => container.remove(), duration + 3000);
    },

    // Coin Rain Animation
    showCoinRain(count = 50) {
        const container = document.createElement('div');
        container.className = 'coin-rain-container';
        document.body.appendChild(container);

        for (let i = 0; i < count; i++) {
            setTimeout(() => {
                const coin = document.createElement('div');
                coin.className = 'coin';
                coin.textContent =  '🪙';
                coin.style.left = Math.random() * 100 + '%';
                coin.style.animationDelay = Math.random() * 0.3 + 's';
                coin.style.animationDuration = (Math.random() * 1 + 1.5) + 's';
                container.appendChild(coin);

                setTimeout(() => coin.remove(), 2500);
            }, i * 50);
        }

        this.sounds.coinGain();
        setTimeout(() => container.remove(), 3000);
    },

    // Star Burst Effect
    showStarBurst() {
        const burst = document.createElement('div');
        burst.className = 'star-burst';
        document.body.appendChild(burst);

        const stars = ['⭐', '✨', '🌟', '💫'];;
        const particleCount = 52;

        for (let i = 0; i < particleCount; i++) {
            const particle = document.createElement('div');
            particle.className = 'star-particle';
            particle.textContent = stars[Math.floor(Math.random() * stars.length)];

            const angle = (i / particleCount) * Math.PI * 2;
            const distance = 150;
            const tx = Math.cos(angle) * distance;
            const ty = Math.sin(angle) * distance;

            particle.style.setProperty('--tx', tx + 'px');
            particle.style.setProperty('--ty', ty + 'px');
            particle.style.left = '50%';
            particle.style.top = '50%';
            particle.style.transform = 'translate(-50%, -50%)';

            burst.appendChild(particle);
        }

        setTimeout(() => burst.remove(), 1000);
    },

    // XP Gain Popup
    showXPGain(amount) {
        const popup = document.createElement('div');
        popup.className = 'xp-popup';
        popup.innerHTML = `
            <span class="xp-popup-icon">⚡</span>
            +${amount} XP
        `;
        document.body.appendChild(popup);

        this.sounds.xpGain();

        setTimeout(() => popup.remove(), 2000);
    },

    // Coin Gain Popup
    showCoinGain(amount) {
        const popup = document.createElement('div');
        popup.className = 'xp-popup';
        popup.innerHTML = `
            <span class="xp-popup-icon">>🪙</span>
            +${amount} Coins
        `;
        popup.style.top = '30%';
        document.body.appendChild(popup);

        this.sounds.coinGain();

        setTimeout(() => popup.remove(), 2000);
    },

    // Level Up Modal
    showLevelUp(newLevel, callback) {
        const modal = document.createElement('div');
        modal.className = 'levelup-modal';
        modal.innerHTML = `
            <div class="levelup-content">
                <div class="levelup-icon"><🎉</div>
                <div class="levelup-text">LEVEL UP!</div>
                <div class="levelup-number">${newLevel}</div>
                <div class="levelup-message">You're becoming a learning superstar!</div>
            </div>
        `;
        document.body.appendChild(modal);

        this.showConfetti(4000, 150);
        this.sounds.levelUp();

        setTimeout(() => {
            modal.remove();
            if (callback) callback();
        }, 3000);
    },

    // Achievement Unlock Modal
    showAchievementUnlock(achievement, callback) {
        const modal = document.createElement('div');
        modal.className = 'achievement-unlock-modal';
        modal.innerHTML = `
            <div class="achievement-unlock-content">
                <div class="achievement-unlock-header"><🎊 ACHIEVEMENT UNLOCKED! <🎊</div>
                <div class="achievement-unlock-badge">${achievement.badge}</div>
                <div class="achievement-unlock-title">${achievement.title}</div>
                <div class="achievement-unlock-description">${achievement.description}</div>
                <div class="achievement-unlock-reward">${achievement.reward}</div>
                <button class="trophy-close" onclick="this.closest('.achievement-unlock-modal').remove(); ${callback ? callback.name + '()' : ''}">
                    Awesome! <🎉

                </button>
            </div>
        `;
        document.body.appendChild(modal);

        this.showConfetti(5000, 200);
        this.showStarBurst();
        this.sounds.achievement();

        // Auto-close after 5 seconds if user doesn't click
        setTimeout(() => {
            if (document.body.contains(modal)) {
                modal.remove();
                if (callback) callback();
            }
        }, 5000);
    },

    // Trophy Presentation
    showTrophy(icon, title, message, reward, callback) {
        const modal = document.createElement('div');
        modal.className = 'trophy-modal';
        modal.innerHTML = `
            <div class="trophy-content">
                <div class="trophy-icon">${icon}</div>
                <div class="trophy-title">${title}</div>
                <div class="trophy-message">${message}</div>
                ${reward ? `<div class="trophy-reward">${reward}</div>` : ''}
                <button class="trophy-close">Continue</button>
            </div>
        `;
        document.body.appendChild(modal);

        this.showConfetti(4000, 120);
        this.sounds.success();

        modal.querySelector('.trophy-close').addEventListener('click', () => {
            modal.remove();
            if (callback) callback();
        });
    },

    // Streak Celebration
    showStreakCelebration(streakDays) {
        const celebration = document.createElement('div');
        celebration.className = 'streak-celebration';
        celebration.innerHTML = '🔥';
        document.body.appendChild(celebration);

        this.showXPGain(streakDays * 5);
        this.sounds.success();

        setTimeout(() => celebration.remove(), 1500);
    },

    // Success Animation (for completing activities)
    showSuccess(message = 'Great Job!', xp = 0, coins = 0) {
        this.showStarBurst();

        if (xp > 0) {
            setTimeout(() => this.showXPGain(xp), 300);
        }

        if (coins > 0) {
            setTimeout(() => this.showCoinGain(coins), 600);
        }

        this.sounds.success();
    },

    // Quick celebration for small wins
    quickCelebrate(emoji = '🎉') {
        const celebration = document.createElement('div');
        celebration.className = 'streak-celebration';
        celebration.innerHTML = emoji;
        document.body.appendChild(celebration);

        this.sounds.success();

        setTimeout(() => celebration.remove(), 1500);
    }
};

// Initialize on page load
if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', () => {
        Celebrations.init();
    });
}

// Make it globally available
if (typeof window !== 'undefined') {
    window.Celebrations = Celebrations;
}

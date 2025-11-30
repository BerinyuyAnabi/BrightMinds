// Authentication JavaScript

// Check if user is already logged in
window.addEventListener('DOMContentLoaded', () => {
    checkSession();
    initializeAuthForms();
});

// Check existing session via API
async function checkSession() {
    try {
        const response = await fetch('api/auth.php?action=verify');
        const data = await response.json();
        
        if (data.success && data.user) {
            // User is logged in, redirect to appropriate dashboard
            if (window.location.pathname.includes('index.html') || window.location.pathname.includes('parent-auth.html')) {
                if (data.user.role === 'parent') {
                    window.location.href = 'parent-dashboard.php';
                } else {
                    window.location.href = 'dashboard.php';
                }
            }
        }
    } catch (error) {
        // If verification fails, user is not logged in - stay on auth page
        console.log('No active session');
    }
}

// Initialize auth forms
function initializeAuthForms() {
    // Get elements
    const welcomeScreen = document.getElementById('welcomeScreen');
    const signupForm = document.getElementById('signupForm');
    const loginForm = document.getElementById('loginForm');
    
    const btnSignup = document.getElementById('btnSignup');
    const btnLogin = document.getElementById('btnLogin');
    const signupBack = document.getElementById('signupBack');
    const loginBack = document.getElementById('loginBack');
    
    // Show signup form
    if (btnSignup) {
        btnSignup.addEventListener('click', () => {
            welcomeScreen.classList.add('hidden');
            signupForm.classList.remove('hidden');
        });
    }
    
    // Show login form
    if (btnLogin) {
        btnLogin.addEventListener('click', () => {
            welcomeScreen.classList.add('hidden');
            loginForm.classList.remove('hidden');
        });
    }
    
    // Back to welcome from signup
    if (signupBack) {
        signupBack.addEventListener('click', () => {
            signupForm.classList.add('hidden');
            welcomeScreen.classList.remove('hidden');
        });
    }
    
    // Back to welcome from login
    if (loginBack) {
        loginBack.addEventListener('click', () => {
            loginForm.classList.add('hidden');
            welcomeScreen.classList.remove('hidden');
        });
    }
    
    // Setup form handlers
    setupSignupForm();
    setupLoginForm();
    setupAvatarSelector();
    setupPasswordStrength();
}

// Setup signup form
function setupSignupForm() {
    const form = document.getElementById('formSignup');
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Get form data
        const username = document.getElementById('signupUsername').value.trim();
        const email = document.getElementById('signupEmail').value.trim();
        const password = document.getElementById('signupPassword').value;
        const displayName = document.getElementById('displayName').value.trim();
        const age = document.getElementById('age').value;
        const avatar = document.getElementById('selectedAvatar').value;
        
        // Validate
        if (!username || !email || !password || !displayName || !age) {
            showToast('Please fill in all fields', 'error');
            return;
        }
        
        // Show loading
        document.getElementById('loadingSignup').classList.remove('hidden');
        
        try {
            // Call registration API
            const response = await fetch('api/auth.php?action=register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    username,
                    email,
                    password,
                    displayName,
                    age: parseInt(age),
                    avatar,
                    role: 'child'
                })
            });

            // Get response as text first for debugging
            const responseText = await response.text();
            console.log('Registration response:', responseText);

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response:', parseError);
                console.error('Response text:', responseText);
                document.getElementById('loadingSignup').classList.add('hidden');
                showToast('Invalid response from server. Please check console for details.', 'error');
                return;
            }

            // Hide loading
            document.getElementById('loadingSignup').classList.add('hidden');

            if (data.success) {
                // Store user data in localStorage for client-side use
                localStorage.setItem('brightMindsSession', JSON.stringify({
                    userId: data.user?.userId || data.userId,
                    username: data.user?.username || username,
                    displayName: data.user?.displayName || displayName,
                    email: data.user?.email || email,
                    role: data.user?.role || data.role || 'child',
                    avatar: data.user?.avatar || avatar,
                    childID: data.user?.childID || null
                }));

                // Show success
                showToast('Account created successfully! Welcome! 🎉', 'success');

                // Redirect to dashboard
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 1500);
            } else {
                // Show specific error message from server
                const errorMessage = data.message || data.errors || 'Registration failed. Please try again.';
                showToast(errorMessage, 'error');
            }
        } catch (error) {
            console.error('Registration error:', error);
            document.getElementById('loadingSignup').classList.add('hidden');
            showToast('An error occurred. Please try again.', 'error');
        }
    });
}

// Setup login form
function setupLoginForm() {
    const form = document.getElementById('formLogin');
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Get form data
        const username = document.getElementById('loginUsername').value.trim();
        const password = document.getElementById('loginPassword').value;
        const rememberMe = document.getElementById('rememberMe')?.checked || false;
        
        // Validate
        if (!username || !password) {
            showToast('Please enter username and password', 'error');
            return;
        }
        
        // Show loading
        document.getElementById('loadingLogin').classList.remove('hidden');
        
        try {
            // Call login API
            const response = await fetch('api/auth.php?action=login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    username,
                    password,
                    rememberMe
                })
            });

            const responseText = await response.text();
            console.log('Login response:', responseText);

            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Failed to parse JSON response:', parseError);
                console.error('Response text:', responseText);
                throw new Error('Invalid response from server. Please check console for details.');
            }
            
            // Hide loading
            document.getElementById('loadingLogin').classList.add('hidden');
            
            if (data.success && data.user) {
                // Store user data in localStorage for client-side use
                localStorage.setItem('brightMindsSession', JSON.stringify({
                    userId: data.user.userId,
                    username: data.user.username,
                    displayName: data.user.displayName,
                    email: data.user.email,
                    role: data.user.role,
                    avatar: data.user.avatar,
                    childID: data.user.childID || null
                }));
                
                // Show success
                showToast('Welcome back! 🎈', 'success');
                
                // Redirect based on role
                setTimeout(() => {
                    if (data.user.role === 'parent') {
                        window.location.href = 'parent-dashboard.php';
                    } else {
                        window.location.href = 'dashboard.php';
                    }
                }, 1500);
            } else {
                // Show error
                showToast(data.message || 'Login failed. Please try again.', 'error');
            }
        } catch (error) {
            console.error('Login error:', error);
            document.getElementById('loadingLogin').classList.add('hidden');
            showToast('An error occurred. Please try again.', 'error');
        }
    });
}

// Setup avatar selector
function setupAvatarSelector() {
    const avatarOptions = document.querySelectorAll('.avatar-option');
    const selectedAvatarInput = document.getElementById('selectedAvatar');
    
    avatarOptions.forEach(option => {
        option.addEventListener('click', () => {
            // Remove selected class from all
            avatarOptions.forEach(opt => opt.classList.remove('selected'));
            
            // Add selected class to clicked
            option.classList.add('selected');
            
            // Update hidden input
            const avatar = option.getAttribute('data-avatar');
            if (selectedAvatarInput) {
                selectedAvatarInput.value = avatar;
            }
        });
    });
}

// Setup password strength checker
function setupPasswordStrength() {
    const passwordInput = document.getElementById('signupPassword');
    if (!passwordInput) return;
    
    passwordInput.addEventListener('input', () => {
        const password = passwordInput.value;
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        
        if (!strengthFill || !strengthText) return;
        
        // Calculate strength
        let strength = 0;
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^a-zA-Z\d]/.test(password)) strength++;
        
        // Update UI
        strengthFill.className = 'strength-fill';
        if (strength === 0) {
            strengthFill.classList.add('weak');
            strengthText.textContent = '';
        } else if (strength <= 2) {
            strengthFill.classList.add('weak');
            strengthText.textContent = 'Weak password';
            strengthText.style.color = 'var(--coral)';
        } else if (strength === 3) {
            strengthFill.classList.add('medium');
            strengthText.textContent = 'Medium password';
            strengthText.style.color = 'var(--sunshine)';
        } else {
            strengthFill.classList.add('strong');
            strengthText.textContent = 'Strong password! ✓';
            strengthText.style.color = 'var(--mint)';
        }
        
        // Validate input
        if (password.length >= 6) {
            passwordInput.classList.add('valid');
            passwordInput.classList.remove('invalid');
        } else {
            passwordInput.classList.remove('valid');
            passwordInput.classList.add('invalid');
        }
    });
}

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
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

// Form validation helpers
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validateUsername(username) {
    return username.length >= 3 && /^[a-zA-Z0-9_]+$/.test(username);
}

// Real-time validation
document.addEventListener('DOMContentLoaded', () => {
    // Email validation
    const emailInput = document.getElementById('signupEmail');
    if (emailInput) {
        emailInput.addEventListener('blur', () => {
            const email = emailInput.value.trim();
            if (email && !validateEmail(email)) {
                emailInput.classList.add('invalid');
                emailInput.classList.remove('valid');
                const errorEl = document.getElementById('emailError');
                if (errorEl) {
                    errorEl.textContent = 'Please enter a valid email';
                    errorEl.classList.add('show');
                }
            } else if (email) {
                emailInput.classList.add('valid');
                emailInput.classList.remove('invalid');
                const errorEl = document.getElementById('emailError');
                if (errorEl) {
                    errorEl.classList.remove('show');
                }
            }
        });
    }
    
    // Username validation
    const usernameInput = document.getElementById('signupUsername');
    if (usernameInput) {
        usernameInput.addEventListener('blur', () => {
            const username = usernameInput.value.trim();
            if (username && !validateUsername(username)) {
                usernameInput.classList.add('invalid');
                usernameInput.classList.remove('valid');
                const errorEl = document.getElementById('usernameError');
                if (errorEl) {
                    errorEl.textContent = 'Username must be at least 3 characters (letters, numbers, underscore)';
                    errorEl.classList.add('show');
                }
            } else if (username) {
                usernameInput.classList.add('valid');
                usernameInput.classList.remove('invalid');
                const errorEl = document.getElementById('usernameError');
                if (errorEl) {
                    errorEl.classList.remove('show');
                }
            }
        });
    }
});

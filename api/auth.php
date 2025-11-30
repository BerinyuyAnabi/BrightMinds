<?php
/**
 * Bright Minds - Authentication API
 * Handles user registration, login, logout, and session management
 */

// Prevent any output before JSON
ob_start();

require_once '../includes/config.php';

// Set JSON header
header('Content-Type: application/json');

// Handle CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$db = getDB();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        handleRegister();
        break;
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'verify':
        handleVerify();
        break;
    case 'check':
        handleCheck();
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

/**
 * Handle user registration
 */
function handleRegister() {
    global $db;
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = sanitizeInput($input['username'] ?? '');
    $email = sanitizeInput($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $displayName = sanitizeInput($input['displayName'] ?? '');
    $age = intval($input['age'] ?? 0);
    $avatar = sanitizeInput($input['avatar'] ?? 'owl');
    $role = sanitizeInput($input['role'] ?? 'child');
    
    // Validation
    $errors = [];
    
    if (empty($username) || strlen($username) < 3) {
        $errors['username'] = 'Username must be at least 3 characters';
    }
    
    if (empty($email) || !validateEmail($email)) {
        $errors['email'] = 'Valid email is required';
    }
    
    if (empty($password) || strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors['password'] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters';
    }
    
    if ($role === 'child') {
        if (empty($displayName)) {
            $errors['displayName'] = 'Display name is required';
        }
        if ($age < 5 || $age > 12) {
            $errors['age'] = 'Age must be between 5 and 12';
        }
    }
    
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'errors' => $errors], 400);
    }
    
    // Check if username exists
    $existingUser = $db->selectOne(
        "SELECT userID FROM users WHERE username = ? OR email = ?",
        [$username, $email]
    );
    
    if ($existingUser) {
        jsonResponse([
            'success' => false,
            'message' => 'Username or email already exists'
        ], 400);
    }
    
    // Hash password
    $hashedPassword = hashPassword($password);

    // Get next userID (manual increment since AUTO_INCREMENT may not be enabled)
    $maxUser = $db->selectOne("SELECT MAX(userID) as max_id FROM users");
    $nextUserId = ($maxUser['max_id'] ?? 0) + 1;

    // Generate parent code if role is parent (replaces database trigger)
    $parentCode = null;
    if ($role === 'parent') {
        $parentCode = 'PAR-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 5));
    }

    // Insert user
    if ($role === 'parent') {
        $userId = $db->insert(
            "INSERT INTO users (userID, username, email, password, role, parent_code) VALUES (?, ?, ?, ?, ?, ?)",
            [$nextUserId, $username, $email, $hashedPassword, $role, $parentCode]
        );
    } else {
        $userId = $db->insert(
            "INSERT INTO users (userID, username, email, password, role) VALUES (?, ?, ?, ?, ?)",
            [$nextUserId, $username, $email, $hashedPassword, $role]
        );
    }
    
    if (!$userId) {
        jsonResponse([
            'success' => false,
            'message' => 'Registration failed. Please try again.'
        ], 500);
    }

    // If child, create child profile
    $childId = null;
    if ($role === 'child') {
        // Get next childID (manual increment since AUTO_INCREMENT may not be enabled)
        $maxChild = $db->selectOne("SELECT MAX(childID) as max_id FROM children");
        $nextChildId = ($maxChild['max_id'] ?? 0) + 1;

        $childId = $db->insert(
            "INSERT INTO children (childID, userID, display_name, age, avatar) VALUES (?, ?, ?, ?, ?)",
            [$nextChildId, $userId, $displayName, $age, $avatar]
        );

        if (!$childId) {
            jsonResponse([
                'success' => false,
                'message' => 'Failed to create child profile'
            ], 500);
        }
    }

    // Log activity
    logActivity("New user registered: $username (ID: $userId)", 'auth');

    // Auto login - pass childId for children
    $sessionToken = createSession($userId, $role, $childId);

    jsonResponse([
        'success' => true,
        'message' => 'Registration successful!',
        'user' => [
            'userId' => $userId,
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'displayName' => $displayName ?? $username,
            'avatar' => $avatar ?? 'owl',
            'childID' => $childId
        ],
        'sessionToken' => $sessionToken
    ]);
}

/**
 * Handle user login
 */
function handleLogin() {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $username = sanitizeInput($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $rememberMe = $input['rememberMe'] ?? false;
    
    // Validation
    if (empty($username) || empty($password)) {
        jsonResponse([
            'success' => false,
            'message' => 'Username and password are required'
        ], 400);
    }
    
    // Get user
    $user = $db->selectOne(
        "SELECT u.*, c.childID, c.display_name, c.avatar 
         FROM users u
         LEFT JOIN children c ON u.userID = c.userID
         WHERE u.username = ? OR u.email = ?",
        [$username, $username]
    );
    
    if (!$user) {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid username or password'
        ], 401);
    }
    
    // Verify password
    if (!verifyPassword($password, $user['password'])) {
        logActivity("Failed login attempt for: $username", 'auth');
        jsonResponse([
            'success' => false,
            'message' => 'Invalid username or password'
        ], 401);
    }
    
    // Check if account is active
    if (!$user['is_active']) {
        jsonResponse([
            'success' => false,
            'message' => 'Account is deactivated. Please contact support.'
        ], 403);
    }
    
    // Update last login
    $db->update(
        "UPDATE users SET last_login = NOW() WHERE userID = ?",
        [$user['userID']]
    );
    
    // Update streak if child (using PHP function instead of stored procedure)
    if ($user['role'] === 'child' && isset($user['childID'])) {
        update_streak($user['childID']);
    }
    
    // Create session
    $sessionToken = createSession($user['userID'], $user['role'], $user['childID'] ?? null, $rememberMe);
    
    logActivity("User logged in: {$user['username']} (ID: {$user['userID']})", 'auth');
    
    jsonResponse([
        'success' => true,
        'message' => 'Login successful!',
        'user' => [
            'userId' => $user['userID'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
            'displayName' => $user['display_name'] ?? $user['username'],
            'avatar' => $user['avatar'] ?? 'owl',
            'childID' => $user['childID'] ?? null
        ],
        'sessionToken' => $sessionToken
    ]);
}

/**
 * Handle logout
 */
function handleLogout() {
    global $db;
    
    if (isLoggedIn()) {
        $sessionToken = $_SESSION['session_token'] ?? null;
        
        if ($sessionToken) {
            // Deactivate session in database
            $db->update(
                "UPDATE user_sessions SET is_active = 0 WHERE session_token = ?",
                [$sessionToken]
            );
        }
        
        logActivity("User logged out: " . getCurrentUserId(), 'auth');
    }
    
    // Destroy session
    session_destroy();
    
    jsonResponse([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
}

/**
 * Verify session
 */
function handleVerify() {
    global $db;
    
    if (!isLoggedIn()) {
        jsonResponse([
            'success' => false,
            'message' => 'Not authenticated'
        ], 401);
    }
    
    $sessionToken = $_SESSION['session_token'];
    
    // Verify session in database
    $session = $db->selectOne(
        "SELECT s.*, u.username, u.email, u.role, c.childID, c.display_name, c.avatar
         FROM user_sessions s
         JOIN users u ON s.userID = u.userID
         LEFT JOIN children c ON u.userID = c.userID
         WHERE s.session_token = ? AND s.is_active = 1 AND s.expires_at > NOW()",
        [$sessionToken]
    );
    
    if (!$session) {
        session_destroy();
        jsonResponse([
            'success' => false,
            'message' => 'Session expired'
        ], 401);
    }
    
    jsonResponse([
        'success' => true,
        'user' => [
            'userId' => $session['userID'],
            'username' => $session['username'],
            'email' => $session['email'],
            'role' => $session['role'],
            'displayName' => $session['display_name'] ?? $session['username'],
            'avatar' => $session['avatar'] ?? 'owl',
            'childID' => $session['childID'] ?? null
        ]
    ]);
}

/**
 * Quick session check
 */
function handleCheck() {
    jsonResponse([
        'authenticated' => isLoggedIn(),
        'userId' => getCurrentUserId(),
        'childId' => getCurrentChildId()
    ]);
}

/**
 * Create user session
 */
function createSession($userId, $role, $childId = null, $rememberMe = false) {
    global $db;
    
    // Generate session token
    $sessionToken = generateToken();
    
    // Set session lifetime
    $lifetime = $rememberMe ? (30 * 24 * 3600) : SESSION_LIFETIME; // 30 days or 24 hours
    $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);
    
    // Get next sessionID (manual increment since AUTO_INCREMENT may not be enabled)
    $maxSession = $db->selectOne("SELECT MAX(sessionID) as max_id FROM user_sessions");
    $nextSessionId = ($maxSession['max_id'] ?? 0) + 1;

    // Insert session into database
    $db->insert(
        "INSERT INTO user_sessions (sessionID, userID, session_token, ip_address, user_agent, expires_at)
         VALUES (?, ?, ?, ?, ?, ?)",
        [
            $nextSessionId,
            $userId,
            $sessionToken,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $expiresAt
        ]
    );
    
    // Set session variables
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
    $_SESSION['session_token'] = $sessionToken;
    
    if ($childId) {
        $_SESSION['child_id'] = $childId;
    }
    
    // Set session cookie lifetime
    if ($rememberMe) {
        setcookie(SESSION_NAME, session_id(), time() + $lifetime, '/');
    }
    
    return $sessionToken;
}

?>

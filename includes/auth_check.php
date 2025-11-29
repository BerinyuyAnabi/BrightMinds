<?php
/**
 * Authentication Check - Include this at the top of protected pages
 * This file ensures only logged-in users can access pages
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is authenticated
 */
function checkAuth($redirectTo = 'index.html') {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_token'])) {
        // Not logged in - redirect to login page
        header('Location: ' . $redirectTo);
        exit;
    }

    // Optionally: Verify session in database
    require_once __DIR__ . '/config.php';
    $db = getDB();

    $session = $db->selectOne(
        "SELECT * FROM user_sessions
         WHERE session_token = ?
         AND userID = ?
         AND is_active = 1
         AND expires_at > NOW()",
        [$_SESSION['session_token'], $_SESSION['user_id']]
    );

    if (!$session) {
        // Session expired or invalid
        session_destroy();
        header('Location: ' . $redirectTo);
        exit;
    }

    return true;
}

/**
 * Check if user has specific role
 */
function checkRole($requiredRole, $redirectTo = 'index.html') {
    checkAuth($redirectTo);

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $requiredRole) {
        // User doesn't have required role
        if ($requiredRole === 'child' && $_SESSION['role'] === 'parent') {
            header('Location: parent-dashboard.php');
        } elseif ($requiredRole === 'parent' && $_SESSION['role'] === 'child') {
            header('Location: dashboard.php');
        } else {
            header('Location: ' . $redirectTo);
        }
        exit;
    }

    return true;
}

/**
 * Check if user is a child with valid child profile
 */
function checkChildAuth() {
    checkRole('child');

    if (!isset($_SESSION['child_id'])) {
        // Child doesn't have a profile
        session_destroy();
        header('Location: index.html');
        exit;
    }

    return true;
}
?>

<?php
require_once 'includes/config.php';
header('Content-Type: application/json');

echo json_encode([
    'php_session' => [
        'user_id' => $_SESSION['user_id'] ?? 'NOT SET',
        'child_id' => $_SESSION['child_id'] ?? 'NOT SET',
        'role' => $_SESSION['role'] ?? 'NOT SET',
        'session_token' => isset($_SESSION['session_token']) ? 'EXISTS' : 'NOT SET'
    ],
    'getCurrentUserId' => getCurrentUserId(),
    'getCurrentChildId' => getCurrentChildId(),
    'isLoggedIn' => isLoggedIn()
], JSON_PRETTY_PRINT);
?>

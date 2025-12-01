<?php
/**
 * Bright Minds Learning Platform
 * Configuration File
 *
 * This file contains database and application settings
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ========================================
// DATABASE CONFIGURATION
// ========================================
// Using MAMP default settings (port 8889, root/root)
// define('DB_HOST', 'localhost');
// define('DB_PORT', 8889);
// define('DB_USER', 'root');
// define('DB_PASS', 'root');
// define('DB_NAME', 'webtech_2025A_logan_anabi');

// Original credentials (if needed, uncomment these)
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', 'logan.anabi');
define('DB_PASS', 'Minushbest#0');
define('DB_NAME', 'webtech_2025A_logan_anabi');

// ========================================
// APPLICATION SETTINGS
// ========================================
define('APP_NAME', 'Bright Minds');
define('APP_VERSION', '2.0');
define('BASE_URL', 'http://localhost:8888/bright-minds/'); // Changed this line 
define('TIMEZONE', 'UTC');

// Set timezone
date_default_timezone_set(TIMEZONE);

// ========================================
// SESSION SETTINGS
// ========================================
define('SESSION_LIFETIME', 86400); // 24 hours
define('SESSION_NAME', 'bright_minds_session');

// ========================================
// GAMIFICATION SETTINGS
// ========================================
define('XP_PER_LEVEL', 100);
define('LEVEL_MULTIPLIER', 1.2);
define('MAX_STREAK_BONUS', 50);

// ========================================
// FILE UPLOAD SETTINGS
// ========================================
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// ========================================
// SECURITY SETTINGS
// ========================================
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 300); // 5 minutes

// ========================================
// DATABASE CONNECTION CLASS
// ========================================
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new mysqli(
                DB_HOST, 
                DB_USER, 
                DB_PASS, 
                DB_NAME, 
                DB_PORT
            );
            
            if ($this->connection->connect_error) {
                throw new Exception('Database connection failed: ' . $this->connection->connect_error);
            }
            
            // Set charset
            $this->connection->set_charset('utf8mb4');
            
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed. Please try again later.'
            ]));
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->connection->prepare($sql);

        if (!$stmt) {
            $this->logError('Query preparation failed: ' . $this->connection->error);
            return false;
        }

        if (!empty($params)) {
            $types = '';
            $values = [];

            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
                $values[] = $param;
            }

            $stmt->bind_param($types, ...$values);
        }

        $stmt->execute();

        // Handle stored procedures: close statement and clear remaining results
        if (stripos($sql, 'CALL') === 0) {
            $stmt->close();
            // Clear any remaining results from the stored procedure
            while ($this->connection->more_results()) {
                $this->connection->next_result();
            }
            return true;
        }

        return $stmt;
    }
    
    public function select($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        if (!$stmt) return [];
        
        $result = $stmt->get_result();
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
    }
    
    public function selectOne($sql, $params = []) {
        $results = $this->select($sql, $params);
        return !empty($results) ? $results[0] : null;
    }
    
    public function insert($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        if (!$stmt) return false;

        $insertId = $this->connection->insert_id;
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        // If insert_id is 0 (manual ID or no AUTO_INCREMENT), return affected_rows > 0 ? true : false
        // Otherwise return the auto-generated ID
        if ($insertId > 0) {
            return $insertId;
        }
        return $affectedRows > 0 ? true : false;
    }
    
    public function update($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        if (!$stmt) return false;
        
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        return $affectedRows;
    }
    
    public function delete($sql, $params = []) {
        return $this->update($sql, $params);
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    private function logError($message) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/error_' . date('Y-m-d') . '.log';
        $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }
    
    public function __destruct() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

// ========================================
// HELPER FUNCTIONS
// ========================================

/**
 * Get database instance
 */
function getDB() {
    return Database::getInstance();
}

/**
 * Hash password securely
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

/**
 * Verify password
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Generate random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Award XP and coins to a child (replaces stored procedure)
 * This function updates the child's XP, coins, level, and activity streak
 */
function award_xp($childID, $xp_amount, $coin_amount) {
    $db = getDB();

    error_log("award_xp called: childID=$childID, xp=$xp_amount, coins=$coin_amount");

    // Get current stats
    $child = $db->selectOne("
        SELECT total_xp, current_level, coins
        FROM children
        WHERE childID = ?
    ", [$childID]);

    if (!$child) {
        error_log("award_xp: Child not found with ID $childID");
        return false;
    }

    $current_xp = $child['total_xp'];
    $current_level = $child['current_level'];
    $current_coins = $child['coins'];

    error_log("award_xp: Current stats - XP: $current_xp, Level: $current_level, Coins: $current_coins");

    // Update XP and coins (do NOT update last_activity_date here - let update_streak handle it)
    $result = $db->update("
        UPDATE children
        SET total_xp = total_xp + ?,
            coins = coins + ?
        WHERE childID = ?
    ", [$xp_amount, $coin_amount, $childID]);

    if ($result === false) {
        error_log("award_xp: Failed to update XP and coins");
        return false;
    }

    // Calculate new level (100 XP per level)
    $new_xp = $current_xp + $xp_amount;
    $new_level = floor($new_xp / 100) + 1;

    error_log("award_xp: New XP: $new_xp, New Level: $new_level");

    // Update level if it changed
    if ($new_level > $current_level) {
        $levelResult = $db->update("
            UPDATE children
            SET current_level = ?
            WHERE childID = ?
        ", [$new_level, $childID]);

        if ($levelResult === false) {
            error_log("award_xp: Failed to update level");
        } else {
            error_log("award_xp: Level updated to $new_level");
        }
    }

    // Update streak when awarding XP (activity indicates daily engagement)
    update_streak($childID);

    error_log("award_xp: Update completed successfully");
    return true;
}

/**
 * Update login streak for a child
 * Replaces the update_streak stored procedure
 *
 * @param int $childID The child's ID
 * @return int The current streak days
 */
function update_streak($childID) {
    $db = getDB();

    // Get current streak and last activity date
    $child = $db->selectOne("
        SELECT last_activity_date, streak_days
        FROM children
        WHERE childID = ?
    ", [$childID]);

    if (!$child) return 0;

    $lastActivity = $child['last_activity_date'];
    $currentStreak = $child['streak_days'] ?? 0;
    $today = date('Y-m-d');

    if ($lastActivity === $today) {
        // Already logged in today, do nothing
        return $currentStreak;
    } elseif ($lastActivity === date('Y-m-d', strtotime('-1 day'))) {
        // Logged in yesterday, increment streak
        $newStreak = $currentStreak + 1;
        $db->update("
            UPDATE children
            SET streak_days = ?,
                last_activity_date = ?
            WHERE childID = ?
        ", [$newStreak, $today, $childID]);
        return $newStreak;
    } else {
        // Streak broken, reset to 1
        $db->update("
            UPDATE children
            SET streak_days = 1,
                last_activity_date = ?
            WHERE childID = ?
        ", [$today, $childID]);
        return 1;
    }
}

/**
 * Sanitize input
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    // Clear any output buffers to prevent PHP warnings/errors from breaking JSON
    if (ob_get_length()) ob_clean();

    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['session_token']);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current child ID
 * If not in session but user is logged in, try to fetch from database
 */
function getCurrentChildId() {
    // First check session
    if (isset($_SESSION['child_id']) && $_SESSION['child_id']) {
        return $_SESSION['child_id'];
    }

    // Fallback: Try to get from database using user_id
    $userId = getCurrentUserId();
    if ($userId) {
        $db = getDB();
        $child = $db->selectOne("SELECT childID FROM children WHERE userID = ?", [$userId]);
        if ($child && isset($child['childID'])) {
            // Store in session for future requests
            $_SESSION['child_id'] = $child['childID'];
            error_log("getCurrentChildId: Retrieved childID from database: {$child['childID']}");
            return $child['childID'];
        }
    }

    return null;
}

/**
 * Require authentication
 */
function requireAuth() {
    if (!isLoggedIn()) {
        jsonResponse([
            'success' => false,
            'message' => 'Authentication required',
            'redirect' => 'index.html'
        ], 401);
    }
}

/**
 * Calculate XP needed for level
 */
function xpForLevel($level) {
    return floor(XP_PER_LEVEL * pow(LEVEL_MULTIPLIER, $level - 1));
}

/**
 * Calculate level from XP
 */
function calculateLevel($xp) {
    $level = 1;
    $totalXP = 0;
    
    while ($totalXP <= $xp) {
        $totalXP += xpForLevel($level);
        if ($totalXP > $xp) break;
        $level++;
    }
    
    return $level;
}

/**
 * Log activity
 */
function logActivity($message, $type = 'info') {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/' . $type . '_' . date('Y-m-d') . '.log';
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    @file_put_contents($logFile, $entry, FILE_APPEND);
}

function isParentLinked($childID) {
    $db = getDB();
    $result = $db->selectOne("
        SELECT parentID
        FROM children
        WHERE childID = ?
    ", [$childID]);

    // If no child was found
    if ($result === null) {
        return false;
    }

    // Child exists — now check if parentID is not NULL
    return $result['parentID'] !== null;
}


?>

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
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);  // XAMPP default port
define('DB_USER', 'root');  // XAMPP default user
define('DB_PASS', '');  // XAMPP default password is EMPTY
define('DB_NAME', 'bright_minds_db');

// ========================================
// APPLICATION SETTINGS
// ========================================
define('APP_NAME', 'Bright Minds');
define('APP_VERSION', '2.0');
define('BASE_URL', 'http://localhost/BrightMinds/BrightMinds/'); 
define('TIMEZONE', 'UTC');

// Set timezone
date_default_timezone_set(TIMEZONE);

// ========================================
// SESSION SETTINGS
// ========================================
define('SESSION_LIFETIME', 604800); // 7 days
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
define('MAX_FILE_SIZE', 5242880); 
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);

// ========================================
// SECURITY SETTINGS
// ========================================
define('PASSWORD_MIN_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 300); 

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

        // Handles stored procedures: close statement and clear remaining results
        if (stripos($sql, 'CALL') === 0) {
            $stmt->close();
            // Clears any remaining results from the stored procedure
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

        // If insert_id is 0 Otherwise return the auto-generated ID
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


// Get database instance

function getDB() {
    return Database::getInstance();
}

// Hash password securely

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Verify password

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Generate random token

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

    // Update XP, coins, and last activity date (for streak tracking)
    $result = $db->query("
        UPDATE children
        SET total_xp = total_xp + ?,
            coins = coins + ?,
            last_activity_date = CURDATE()
        WHERE childID = ?
    ", [$xp_amount, $coin_amount, $childID]);

    if (!$result) {
        error_log("award_xp: Failed to update XP and coins");
        return false;
    }

    // Calculate new level (100 XP per level)
    $new_xp = $current_xp + $xp_amount;
    $new_level = floor($new_xp / 100) + 1;

    error_log("award_xp: New XP: $new_xp, New Level: $new_level");

    // Update level if it changed
    if ($new_level > $current_level) {
        $levelResult = $db->query("
            UPDATE children
            SET current_level = ?
            WHERE childID = ?
        ", [$new_level, $childID]);

        if (!$levelResult) {
            error_log("award_xp: Failed to update level");
        } else {
            error_log("award_xp: Level updated to $new_level");
        }
    }

    // Update streak when awarding XP 
    update_streak($childID);
    
    // Update goal progress for active goals
    update_goal_progress($childID, $xp_amount, $coin_amount);

    error_log("award_xp: Update completed successfully");
    return true;
}

/**
 * Update login streak for a child
 * Replaces the update_streak stored procedure
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
    $currentStreak = intval($child['streak_days'] ?? 0);
    $today = date('Y-m-d');

    // Handle NULL or empty last_activity_date
    if (empty($lastActivity) || $lastActivity === null) {
        // First time activity, set streak to 1
        $db->query("
            UPDATE children
            SET streak_days = 1,
                last_activity_date = ?
            WHERE childID = ?
        ", [$today, $childID]);
        return 1;
    }

    // Convert to date string for comparison (handle datetime format)
    $lastActivityDate = date('Y-m-d', strtotime($lastActivity));
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($lastActivityDate === $today) {
        // Already logged in today, do nothing
        return $currentStreak;
    } elseif ($lastActivityDate === $yesterday) {
        // Logged in yesterday, increment streak
        $newStreak = $currentStreak + 1;
        $db->query("
            UPDATE children
            SET streak_days = ?,
                last_activity_date = ?
            WHERE childID = ?
        ", [$newStreak, $today, $childID]);
        return $newStreak;
    } else {
        // Streak broken, reset to 1
        $db->query("
            UPDATE children
            SET streak_days = 1,
                last_activity_date = ?
            WHERE childID = ?
        ", [$today, $childID]);
        return 1;
    }
}

// Update goal progress when child earns XP/coins

function update_goal_progress($childID, $xpEarned, $coinsEarned) {
    $db = getDB();
    
    // Get active goals for this child
    $today = date('Y-m-d');
    $goals = $db->select("
        SELECT goalID, parentID, goal_type, goal_description, target_value, current_progress, end_date
        FROM learning_goals
        WHERE childID = ? 
        AND status = 'active'
        AND end_date >= ?
    ", [$childID, $today]);
    
    foreach ($goals as $goal) {
        $newProgress = $goal['current_progress'];
        
        // Update progress based on goal type
        if ($goal['goal_type'] === 'xp_earned' || $goal['goal_type'] === 'total_xp') {
            $newProgress = $goal['current_progress'] + $xpEarned;
        } elseif ($goal['goal_type'] === 'coins_earned' || $goal['goal_type'] === 'total_coins') {
            $newProgress = $goal['current_progress'] + $coinsEarned;
        } elseif ($goal['goal_type'] === 'games_played' || $goal['goal_type'] === 'quizzes_completed') {
            $newProgress = $goal['current_progress'] + 1;
        }
        
        // Check if goal is completed
        $status = 'active';
        if ($newProgress >= $goal['target_value']) {
            $newProgress = $goal['target_value']; 
            
            // Creates notification for parent
            create_notification(
                $goal['parentID'],
                $childID,
                'goal_completed',
                'Goal Completed! 🎉',
                "Your child has completed the goal: " . $goal['goal_description']
            );
        }
        
        // Update goal
        $db->query("
            UPDATE learning_goals
            SET current_progress = ?,
                status = ?
            WHERE goalID = ?
        ", [$newProgress, $status, $goal['goalID']]);
    }
}

// Create a notification for a parent

function create_notification($parentID, $childID, $type, $title, $message) {
    $db = getDB();
    
    // Create parent_notifications table if it doesn't exist
    $db->query("
        CREATE TABLE IF NOT EXISTS parent_notifications (
            notificationID INT AUTO_INCREMENT PRIMARY KEY,
            parentID INT NOT NULL,
            childID INT,
            notification_type VARCHAR(50),
            title VARCHAR(200),
            message TEXT,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (parentID) REFERENCES users(userID) ON DELETE CASCADE,
            FOREIGN KEY (childID) REFERENCES children(childID) ON DELETE CASCADE,
            INDEX idx_parent (parentID),
            INDEX idx_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ", []);
    
    // Get next notificationID
    $maxNotif = $db->selectOne("SELECT MAX(notificationID) as max_id FROM parent_notifications");
    $notifId = ($maxNotif['max_id'] ?? 0) + 1;
    
    // Insert notification
    $db->insert("
        INSERT INTO parent_notifications 
        (notificationID, parentID, childID, notification_type, title, message)
        VALUES (?, ?, ?, ?, ?, ?)
    ", [$notifId, $parentID, $childID, $type, $title, $message]);
}

// Sanitize input

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

//Validate email

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}


function jsonResponse($data, $statusCode = 200) {
    // Clears any output buffers to prevent PHP warnings/errors 

    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Check if user is logged in

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['session_token']);
}

// Get current user ID

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get current child ID

function getCurrentChildId() {
    return $_SESSION['child_id'] ?? null;
}

// Require authentication

function requireAuth() {
    if (!isLoggedIn()) {
        jsonResponse([
            'success' => false,
            'message' => 'Authentication required',
            'redirect' => 'index.html'
        ], 401);
    }
}

// Calculate XP needed for level

function xpForLevel($level) {
    return floor(XP_PER_LEVEL * pow(LEVEL_MULTIPLIER, $level - 1));
}

// Calculate level from XP

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

// Log activity

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
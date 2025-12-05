-- ========================================
-- BRIGHT MINDS LEARNING PLATFORM
--  Database Schema
-- ========================================

-- Drop database if exists and create fresh
DROP DATABASE IF EXISTS bright_minds_db;
CREATE DATABASE bright_minds_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bright_minds_db;

-- ========================================
-- USERS & AUTHENTICATION TABLES
-- ========================================

-- Users table (parents and children)
CREATE TABLE users (
    userID INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('parent', 'child') NOT NULL DEFAULT 'child',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Children profiles
CREATE TABLE children (
    childID INT AUTO_INCREMENT PRIMARY KEY,
    userID INT NOT NULL,
    parentID INT NULL,
    display_name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    grade_level INT NULL,
    avatar VARCHAR(50) DEFAULT 'owl',
    total_xp INT DEFAULT 0,
    current_level INT DEFAULT 1,
    coins INT DEFAULT 0,
    streak_days INT DEFAULT 0,
    last_activity_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE,
    FOREIGN KEY (parentID) REFERENCES users(userID) ON DELETE SET NULL,
    INDEX idx_user (userID),
    INDEX idx_parent (parentID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sessions table for authentication
CREATE TABLE user_sessions (
    sessionID INT AUTO_INCREMENT PRIMARY KEY,
    userID INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE,
    INDEX idx_token (session_token),
    INDEX idx_user (userID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- CONTENT TABLES
-- ========================================

-- Games catalog
CREATE TABLE games (
    gameID INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    difficulty_level ENUM('easy', 'medium', 'hard') DEFAULT 'easy',
    min_age INT DEFAULT 5,
    max_age INT DEFAULT 12,
    xp_reward INT DEFAULT 10,
    coin_reward INT DEFAULT 5,
    thumbnail VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quizzes
CREATE TABLE quizzes (
    quizID INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    difficulty_level ENUM('easy', 'medium', 'hard') DEFAULT 'easy',
    time_limit INT DEFAULT 300,
    passing_score INT DEFAULT 60,
    xp_reward INT DEFAULT 15,
    coin_reward INT DEFAULT 10,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz questions
CREATE TABLE quiz_questions (
    questionID INT AUTO_INCREMENT PRIMARY KEY,
    quizID INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'fill_blank') DEFAULT 'multiple_choice',
    correct_answer TEXT NOT NULL,
    explanation TEXT,
    points INT DEFAULT 10,
    order_num INT DEFAULT 0,
    FOREIGN KEY (quizID) REFERENCES quizzes(quizID) ON DELETE CASCADE,
    INDEX idx_quiz (quizID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz answer options
CREATE TABLE quiz_options (
    optionID INT AUTO_INCREMENT PRIMARY KEY,
    questionID INT NOT NULL,
    option_text TEXT NOT NULL,
    is_correct BOOLEAN DEFAULT FALSE,
    order_num INT DEFAULT 0,
    FOREIGN KEY (questionID) REFERENCES quiz_questions(questionID) ON DELETE CASCADE,
    INDEX idx_question (questionID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stories
CREATE TABLE stories (
    storyID INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    author VARCHAR(100),
    content TEXT NOT NULL,
    moral_lesson TEXT,
    category VARCHAR(50),
    age_group VARCHAR(20),
    reading_time INT,
    xp_reward INT DEFAULT 5,
    coin_reward INT DEFAULT 3,
    thumbnail VARCHAR(255),
    audio_url VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- ACTIVITY & PROGRESS TRACKING
-- ========================================

-- Play sessions
CREATE TABLE play_sessions (
    sessionID INT AUTO_INCREMENT PRIMARY KEY,
    childID INT NOT NULL,
    activity_type ENUM('game', 'quiz', 'story') NOT NULL,
    activity_id INT NOT NULL,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    duration_seconds INT,
    score DECIMAL(5,2),
    xp_earned INT DEFAULT 0,
    coins_earned INT DEFAULT 0,
    completed BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (childID) REFERENCES children(childID) ON DELETE CASCADE,
    INDEX idx_child (childID),
    INDEX idx_activity (activity_type, activity_id),
    INDEX idx_completed (completed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Achievements system
CREATE TABLE achievements (
    achievementID INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    badge_icon VARCHAR(50),
    requirement_type VARCHAR(50),
    requirement_value INT,
    xp_reward INT DEFAULT 50,
    coin_reward INT DEFAULT 25,
    rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Child achievements (unlocked badges)
CREATE TABLE child_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    childID INT NOT NULL,
    achievementID INT NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (childID) REFERENCES children(childID) ON DELETE CASCADE,
    FOREIGN KEY (achievementID) REFERENCES achievements(achievementID) ON DELETE CASCADE,
    UNIQUE KEY unique_achievement (childID, achievementID),
    INDEX idx_child (childID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Daily challenges
CREATE TABLE daily_challenges (
    challengeID INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    challenge_type VARCHAR(50),
    goal_value INT,
    xp_reward INT DEFAULT 20,
    coin_reward INT DEFAULT 15,
    active_date DATE NOT NULL,
    expires_at TIMESTAMP,
    INDEX idx_date (active_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Child daily challenge progress
CREATE TABLE child_challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    childID INT NOT NULL,
    challengeID INT NOT NULL,
    progress INT DEFAULT 0,
    completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (childID) REFERENCES children(childID) ON DELETE CASCADE,
    FOREIGN KEY (challengeID) REFERENCES daily_challenges(challengeID) ON DELETE CASCADE,
    INDEX idx_child (childID),
    INDEX idx_challenge (challengeID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Leaderboard (weekly/monthly rankings)
CREATE TABLE leaderboard (
    id INT AUTO_INCREMENT PRIMARY KEY,
    childID INT NOT NULL,
    period_type ENUM('weekly', 'monthly', 'all_time') NOT NULL,
    period_start DATE NOT NULL,
    total_xp INT DEFAULT 0,
    total_games INT DEFAULT 0,
    total_quizzes INT DEFAULT 0,
    rank_position INT,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (childID) REFERENCES children(childID) ON DELETE CASCADE,
    UNIQUE KEY unique_period (childID, period_type, period_start),
    INDEX idx_period (period_type, period_start),
    INDEX idx_rank (rank_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- PARENT MONITORING TABLES
-- ========================================

-- Parent notifications
CREATE TABLE parent_notifications (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Learning goals set by parents
CREATE TABLE learning_goals (
    goalID INT AUTO_INCREMENT PRIMARY KEY,
    childID INT NOT NULL,
    parentID INT NOT NULL,
    goal_type VARCHAR(50),
    goal_description TEXT,
    target_value INT,
    current_progress INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'completed', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (childID) REFERENCES children(childID) ON DELETE CASCADE,
    FOREIGN KEY (parentID) REFERENCES users(userID) ON DELETE CASCADE,
    INDEX idx_child (childID),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========================================
-- SAMPLE DATA INSERTION
-- ========================================

-- Insert sample users (passwords are 'password123' hashed with bcrypt)
INSERT INTO users (username, email, password, role) VALUES
('parent_demo', 'parent@brightminds.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'parent'),
('ella_explorer', 'ella@brightminds.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'child'),
('sam_scientist', 'sam@brightminds.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'child');

-- Insert sample children
INSERT INTO children (userID, parentID, display_name, age, grade_level, avatar, total_xp, current_level, coins) VALUES
(2, 1, 'Ella Explorer', 8, 3, 'owl', 250, 5, 120),
(3, 1, 'Sam Scientist', 9, 4, 'fox', 180, 4, 90);

-- Insert games
INSERT INTO games (title, description, category, difficulty_level, min_age, max_age, xp_reward, coin_reward) VALUES
('Memory Match', 'Flip cards to find matching pairs! Improve your memory skills.', 'memory', 'easy', 5, 10, 15, 8),
('Math Dash', 'Solve math problems as fast as you can! Race against time.', 'math', 'medium', 7, 12, 20, 10),
('Word Builder', 'Create words from jumbled letters. Expand your vocabulary!', 'vocabulary', 'easy', 6, 11, 15, 8),
('Science Explorer', 'Learn amazing science facts through fun experiments!', 'science', 'medium', 8, 12, 25, 12),
('Catch the Stars', 'Use arrow keys to catch falling stars before time runs out!', 'reflexes', 'easy', 5, 10, 10, 5);

-- Insert quizzes
INSERT INTO quizzes (title, description, category, difficulty_level, time_limit, xp_reward, coin_reward) VALUES
('Animal Kingdom Quiz', 'Test your knowledge about different animals!', 'science', 'easy', 300, 20, 10),
('Math Champions', 'Show off your math skills with these fun problems!', 'math', 'medium', 600, 30, 15),
('World Geography', 'How well do you know our planet? Find out!', 'geography', 'medium', 450, 25, 12),
('Space Adventures', 'Learn about planets, stars, and the universe!', 'science', 'hard', 480, 35, 18);

-- Insert quiz questions for Animal Kingdom Quiz
INSERT INTO quiz_questions (quizID, question_text, question_type, correct_answer, explanation, points) VALUES
(1, 'What is the largest animal in the ocean?', 'multiple_choice', 'Blue Whale', 'The blue whale can grow up to 100 feet long and weigh 200 tons!', 10),
(1, 'How many legs does a spider have?', 'multiple_choice', '8', 'All spiders have 8 legs. This is one way to tell them apart from insects!', 10),
(1, 'Which animal is known as the King of the Jungle?', 'multiple_choice', 'Lion', 'Lions are called the King of the Jungle because of their strength and majesty!', 10),
(1, 'What do bees make?', 'multiple_choice', 'Honey', 'Bees collect nectar from flowers and turn it into honey!', 10),
(1, 'Can penguins fly?', 'true_false', 'false', 'Penguins cannot fly, but they are excellent swimmers!', 10);

-- Insert quiz options
INSERT INTO quiz_options (questionID, option_text, is_correct, order_num) VALUES
-- Question 1 options
(1, 'Blue Whale', TRUE, 1),
(1, 'Shark', FALSE, 2),
(1, 'Dolphin', FALSE, 3),
(1, 'Octopus', FALSE, 4),
-- Question 2 options
(2, '6', FALSE, 1),
(2, '8', TRUE, 2),
(2, '10', FALSE, 3),
(2, '4', FALSE, 4),
-- Question 3 options
(3, 'Tiger', FALSE, 1),
(3, 'Lion', TRUE, 2),
(3, 'Bear', FALSE, 3),
(3, 'Elephant', FALSE, 4),
-- Question 4 options
(4, 'Honey', TRUE, 1),
(4, 'Milk', FALSE, 2),
(4, 'Jam', FALSE, 3),
(4, 'Syrup', FALSE, 4),
-- Question 5 options (True/False)
(5, 'True', FALSE, 1),
(5, 'False', TRUE, 2);

-- Insert stories
INSERT INTO stories (title, author, content, moral_lesson, category, age_group, reading_time, xp_reward, coin_reward) VALUES
('The Brave Little Elephant', 'Bright Minds Team', 
'Once upon a time, in the heart of a vast jungle, there lived a little elephant named Ella. Ella was smaller than all the other elephants, but she had the biggest dreams.\n\nOne day, a terrible storm came. The rain poured down, and the river began to flood. All the animals were scared! The bridge across the river started to break.\n\n"We need to help!" said Ella. Even though she was small, Ella used her trunk to help move logs and stones. She worked harder than anyone.\n\nTogether with her friends, they built a new bridge. All the animals crossed safely to the other side.\n\n"Thank you, Ella!" everyone cheered. "You may be little, but you have the biggest heart!"',
'Even the smallest person can make a big difference when they try their best!', 
'courage', '5-8', 5, 10, 5),

('The Rainbow Painter', 'Bright Minds Team',
'Sam loved to paint. Every day after school, he would paint pictures of everything he saw - trees, birds, houses, and friends.\n\nOne morning, Sam woke up and found that all the colors in his town had disappeared! Everything was gray - the sky, the grass, even the flowers!\n\n"This is terrible!" said the townspeople. "What can we do?"\n\nSam had an idea. He took his paintbrush and started painting. He painted the sky blue, the grass green, and the flowers every color of the rainbow.\n\nAs Sam painted, something magical happened. The colors came back! The whole town sparkled with beautiful colors again.\n\n"Thank you, Sam!" everyone said. "Your creativity saved our town!"\n\nFrom that day on, Sam knew that art could make the world a more beautiful place.',
'Creativity and imagination can brighten the world around us!',
'creativity', '6-9', 6, 10, 5),

('The Curious Cat\'s Journey', 'Bright Minds Team',
'There once was a curious cat named Whiskers who loved to explore. One day, Whiskers wondered, "What\'s beyond the hill?"\n\nWhiskers climbed and climbed until she reached the top. There, she found a beautiful meadow full of butterflies!\n\n"Wow!" she thought. "But what\'s beyond those trees?"\n\nWhiskers kept exploring and found a sparkling stream, a field of flowers, and made many new friends.\n\nWhen she finally returned home, her family asked, "Where have you been?"\n\n"On an adventure!" Whiskers said. "And I learned that there\'s always something new to discover if you\'re curious enough to look!"',
'Curiosity leads to wonderful discoveries and new adventures!',
'exploration', '5-7', 4, 8, 4);

-- Insert achievements
INSERT INTO achievements (title, description, category, badge_icon, requirement_type, requirement_value, xp_reward, coin_reward, rarity) VALUES
('First Steps', 'Complete your first activity!', 'milestone', '🎯', 'activities_completed', 1, 50, 25, 'common'),
('Quick Learner', 'Complete 10 activities', 'milestone', '⚡', 'activities_completed', 10, 100, 50, 'common'),
('Reading Star', 'Read 5 stories', 'reading', '⭐', 'stories_read', 5, 75, 30, 'common'),
('Quiz Master', 'Complete 10 quizzes with 80% or higher', 'quiz', '🏆', 'quiz_mastery', 10, 150, 75, 'rare'),
('Game Champion', 'Play 20 games', 'gaming', '🎮', 'games_played', 20, 100, 50, 'common'),
('Perfect Score', 'Get 100% on any quiz', 'achievement', '💯', 'perfect_quiz', 1, 200, 100, 'epic'),
('Week Warrior', 'Maintain a 7-day streak', 'streak', '🔥', 'streak_days', 7, 250, 125, 'rare'),
('Knowledge Seeker', 'Earn 1000 total XP', 'milestone', '🧠', 'total_xp', 1000, 300, 150, 'epic'),
('Early Bird', 'Log in before 8 AM', 'special', '🌅', 'early_login', 1, 50, 25, 'common'),
('Night Owl', 'Log in after 8 PM', 'special', '🦉', 'late_login', 1, 50, 25, 'common'),
('Legendary Learner', 'Reach Level 10', 'milestone', '👑', 'level_reached', 10, 500, 250, 'legendary');

-- Insert daily challenges (for today)
INSERT INTO daily_challenges (title, description, challenge_type, goal_value, xp_reward, coin_reward, active_date, expires_at) VALUES
('Daily Explorer', 'Complete 3 activities today', 'daily_activities', 3, 30, 15, CURDATE(), DATE_ADD(NOW(), INTERVAL 1 DAY)),
('Quiz Champion', 'Score 80% or higher on any quiz', 'quiz_score', 80, 40, 20, CURDATE(), DATE_ADD(NOW(), INTERVAL 1 DAY)),
('Story Time', 'Read at least one story', 'read_story', 1, 20, 10, CURDATE(), DATE_ADD(NOW(), INTERVAL 1 DAY));

-- ========================================
-- USEFUL VIEWS
-- ========================================

-- View for child progress summary
CREATE VIEW vw_child_progress AS
SELECT 
    c.childID,
    c.display_name,
    c.age,
    c.avatar,
    c.total_xp,
    c.current_level,
    c.coins,
    c.streak_days,
    COUNT(DISTINCT ps.sessionID) as total_sessions,
    COUNT(DISTINCT CASE WHEN ps.activity_type = 'game' THEN ps.sessionID END) as games_played,
    COUNT(DISTINCT CASE WHEN ps.activity_type = 'quiz' THEN ps.sessionID END) as quizzes_taken,
    COUNT(DISTINCT CASE WHEN ps.activity_type = 'story' THEN ps.sessionID END) as stories_read,
    COUNT(DISTINCT ca.achievementID) as achievements_unlocked,
    AVG(CASE WHEN ps.activity_type = 'quiz' THEN ps.score END) as avg_quiz_score
FROM children c
LEFT JOIN play_sessions ps ON c.childID = ps.childID
LEFT JOIN child_achievements ca ON c.childID = ca.childID
GROUP BY c.childID;

-- View for recent activity feed
CREATE VIEW vw_recent_activities AS
SELECT 
    ps.sessionID,
    c.childID,
    c.display_name,
    ps.activity_type,
    CASE 
        WHEN ps.activity_type = 'game' THEN g.title
        WHEN ps.activity_type = 'quiz' THEN q.title
        WHEN ps.activity_type = 'story' THEN s.title
    END as activity_title,
    ps.score,
    ps.xp_earned,
    ps.coins_earned,
    ps.completed,
    ps.start_time
FROM play_sessions ps
JOIN children c ON ps.childID = c.childID
LEFT JOIN games g ON ps.activity_type = 'game' AND ps.activity_id = g.gameID
LEFT JOIN quizzes q ON ps.activity_type = 'quiz' AND ps.activity_id = q.quizID
LEFT JOIN stories s ON ps.activity_type = 'story' AND ps.activity_id = s.storyID
ORDER BY ps.start_time DESC;

-- ========================================
-- STORED PROCEDURES
-- ========================================

DELIMITER //

-- Procedure to award XP and handle leveling up
CREATE PROCEDURE award_xp(
    IN p_childID INT,
    IN p_xp_amount INT,
    IN p_coin_amount INT
)
BEGIN
    DECLARE current_xp INT;
    DECLARE current_level INT;
    DECLARE new_level INT;
    DECLARE xp_for_next_level INT;
    
    -- Get current stats
    SELECT total_xp, current_level INTO current_xp, current_level
    FROM children WHERE childID = p_childID;
    
    -- Update XP and coins
    UPDATE children 
    SET total_xp = total_xp + p_xp_amount,
        coins = coins + p_coin_amount
    WHERE childID = p_childID;
    
    -- Calculate new level (100 XP per level, exponential growth)
    SET new_level = FLOOR((current_xp + p_xp_amount) / 100) + 1;
    
    -- Update level if it changed
    IF new_level > current_level THEN
        UPDATE children SET current_level = new_level WHERE childID = p_childID;
    END IF;
END //

-- Procedure to check and update streak
CREATE PROCEDURE update_streak(IN p_childID INT)
BEGIN
    DECLARE last_activity DATE;
    DECLARE current_streak INT;
    
    SELECT last_activity_date, streak_days INTO last_activity, current_streak
    FROM children WHERE childID = p_childID;
    
    IF last_activity = CURDATE() THEN
        -- Already logged in today, do nothing
        SELECT current_streak as streak_days;
    ELSEIF last_activity = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN
        -- Logged in yesterday, increment streak
        UPDATE children 
        SET streak_days = streak_days + 1,
            last_activity_date = CURDATE()
        WHERE childID = p_childID;
        SELECT current_streak + 1 as streak_days;
    ELSE
        -- Streak broken, reset to 1
        UPDATE children 
        SET streak_days = 1,
            last_activity_date = CURDATE()
        WHERE childID = p_childID;
        SELECT 1 as streak_days;
    END IF;
END //

DELIMITER ;

-- ========================================
-- INDEXES FOR OPTIMIZATION
-- ========================================

-- Additional composite indexes for common queries
CREATE INDEX idx_sessions_child_activity ON play_sessions(childID, activity_type, start_time);
CREATE INDEX idx_achievements_child_unlocked ON child_achievements(childID, unlocked_at);
CREATE INDEX idx_leaderboard_period_rank ON leaderboard(period_type, period_start, rank_position);

-- ========================================
-- DATABASE COMPLETE
-- ========================================

SELECT 'Database setup complete!' as Status;
SELECT COUNT(*) as TotalTables FROM information_schema.tables WHERE table_schema = 'bright_minds_db';

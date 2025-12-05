-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 29, 2025 at 04:04 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bright_minds_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `award_xp` (IN `p_childID` INT, IN `p_xp_amount` INT, IN `p_coin_amount` INT)   BEGIN
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
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `link_child_to_parent` (IN `p_invite_code` VARCHAR(12), IN `p_child_user_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_parent_id INT;
    DECLARE v_child_id INT;
    DECLARE v_invite_id INT;
    DECLARE v_already_linked BOOLEAN DEFAULT FALSE;
    DECLARE v_invite_expired BOOLEAN DEFAULT TRUE;
    DECLARE v_is_used BOOLEAN DEFAULT FALSE;
    
    -- Initialize
    SET p_success = FALSE;
    SET p_message = 'Unknown error';
    
    -- Start transaction
    START TRANSACTION;
    
    -- Verify invite code exists and is valid
    SELECT inviteID, parentID, 
           (expires_at < NOW()) as is_expired,
           is_used
    INTO v_invite_id, v_parent_id, v_invite_expired, v_is_used
    FROM parent_invites
    WHERE invite_code = p_invite_code
    LIMIT 1;
    
    -- Check if invite exists
    IF v_invite_id IS NULL THEN
        SET p_message = 'Invalid invite code';
        ROLLBACK;
    -- Check if expired
    ELSEIF v_invite_expired THEN
        SET p_message = 'Invite code has expired';
        ROLLBACK;
    -- Check if already used
    ELSEIF v_is_used THEN
        SET p_message = 'Invite code has already been used';
        ROLLBACK;
    ELSE
        -- Get child ID
        SELECT childID INTO v_child_id
        FROM children
        WHERE userID = p_child_user_id
        LIMIT 1;
        
        IF v_child_id IS NULL THEN
            SET p_message = 'Child account not found';
            ROLLBACK;
        ELSE
            -- Check if child is already linked to another parent
            SELECT (parentID IS NOT NULL) INTO v_already_linked
            FROM children
            WHERE childID = v_child_id;
            
            IF v_already_linked THEN
                SET p_message = 'Child is already linked to a parent';
                ROLLBACK;
            ELSE
                -- Link child to parent
                UPDATE children 
                SET parentID = v_parent_id
                WHERE childID = v_child_id;
                
                -- Mark invite as used
                UPDATE parent_invites
                SET is_used = TRUE,
                    used_by_childID = v_child_id,
                    used_at = NOW()
                WHERE inviteID = v_invite_id;
                
                -- Record in history
                INSERT INTO parent_children_history 
                (childID, parentID, action, performed_by, invite_code)
                VALUES 
                (v_child_id, v_parent_id, 'linked', 'child', p_invite_code);
                
                SET p_success = TRUE;
                SET p_message = 'Successfully linked to parent account';
                COMMIT;
            END IF;
        END IF;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `update_streak` (IN `p_childID` INT)   BEGIN
    DECLARE last_activity DATE;
    DECLARE current_streak INT;
    
    SELECT last_activity_date, streak_days INTO last_activity, current_streak
    FROM children WHERE childID = p_childID;
    
    IF last_activity = CURDATE() THEN
        SELECT current_streak as streak_days;
        
    ELSEIF last_activity = DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN
        UPDATE children 
        SET streak_days = streak_days + 1,
            last_activity_date = CURDATE()
        WHERE childID = p_childID;
        SELECT current_streak + 1 as streak_days;
        
    ELSE
        UPDATE children 
        SET streak_days = 1,
            last_activity_date = CURDATE()
        WHERE childID = p_childID;
        SELECT 1 as streak_days;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `achievements`
--

CREATE TABLE `achievements` (
  `achievementID` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `badge_icon` varchar(50) DEFAULT NULL,
  `requirement_type` varchar(50) DEFAULT NULL,
  `requirement_value` int(11) DEFAULT NULL,
  `xp_reward` int(11) DEFAULT 50,
  `coin_reward` int(11) DEFAULT 25,
  `rarity` enum('common','rare','epic','legendary') DEFAULT 'common',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `achievements`
--

INSERT INTO `achievements` (`achievementID`, `title`, `description`, `category`, `badge_icon`, `requirement_type`, `requirement_value`, `xp_reward`, `coin_reward`, `rarity`, `is_active`, `created_at`) VALUES
(1, 'First Steps', 'Complete your first activity!', 'milestone', '🎯', 'activities_completed', 1, 50, 25, 'common', 1, '2025-11-26 11:52:35'),
(2, 'Quick Learner', 'Complete 10 activities', 'milestone', '⚡', 'activities_completed', 10, 100, 50, 'common', 1, '2025-11-26 11:52:35'),
(3, 'Reading Star', 'Read 5 stories', 'reading', '⭐', 'stories_read', 5, 75, 30, 'common', 1, '2025-11-26 11:52:35'),
(4, 'Quiz Master', 'Complete 10 quizzes with 80% or higher', 'quiz', '🏆', 'quiz_mastery', 10, 150, 75, 'rare', 1, '2025-11-26 11:52:35'),
(5, 'Game Champion', 'Play 20 games', 'gaming', '🎮', 'games_played', 20, 100, 50, 'common', 1, '2025-11-26 11:52:35'),
(6, 'Perfect Score', 'Get 100% on any quiz', 'achievement', '💯', 'perfect_quiz', 1, 200, 100, 'epic', 1, '2025-11-26 11:52:35'),
(7, 'Week Warrior', 'Maintain a 7-day streak', 'streak', '🔥', 'streak_days', 7, 250, 125, 'rare', 1, '2025-11-26 11:52:35'),
(8, 'Knowledge Seeker', 'Earn 1000 total XP', 'milestone', '🧠', 'total_xp', 1000, 300, 150, 'epic', 1, '2025-11-26 11:52:35'),
(9, 'Early Bird', 'Log in before 8 AM', 'special', '🌅', 'early_login', 1, 50, 25, 'common', 1, '2025-11-26 11:52:35'),
(10, 'Night Owl', 'Log in after 8 PM', 'special', '🦉', 'late_login', 1, 50, 25, 'common', 1, '2025-11-26 11:52:35'),
(11, 'Legendary Learner', 'Reach Level 10', 'milestone', '👑', 'level_reached', 10, 500, 250, 'legendary', 1, '2025-11-26 11:52:35');

-- --------------------------------------------------------

--
-- Table structure for table `children`
--

CREATE TABLE `children` (
  `childID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `parentID` int(11) DEFAULT NULL,
  `display_name` varchar(100) NOT NULL,
  `age` int(11) NOT NULL,
  `grade_level` int(11) DEFAULT NULL,
  `avatar` varchar(50) DEFAULT 'owl',
  `total_xp` int(11) DEFAULT 0,
  `current_level` int(11) DEFAULT 1,
  `coins` int(11) DEFAULT 0,
  `streak_days` int(11) DEFAULT 0,
  `last_activity_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `children`
--

INSERT INTO `children` (`childID`, `userID`, `parentID`, `display_name`, `age`, `grade_level`, `avatar`, `total_xp`, `current_level`, `coins`, `streak_days`, `last_activity_date`, `created_at`) VALUES
(1, 2, 1, 'Ella Explorer', 8, 3, 'owl', 250, 5, 120, 0, NULL, '2025-11-26 11:52:35'),
(2, 3, 1, 'Sam Scientist', 9, 4, 'fox', 180, 4, 90, 0, NULL, '2025-11-26 11:52:35'),
(3, 4, NULL, 'getty1122', 12, NULL, 'cat', 364, 1, 182, 1, '2025-11-26', '2025-11-26 12:02:06'),
(4, 5, NULL, 'nana', 6, NULL, 'dog', 0, 1, 0, 0, NULL, '2025-11-26 13:19:21'),
(5, 6, NULL, 'naana', 5, NULL, 'fox', 0, 1, 0, 0, NULL, '2025-11-29 14:44:56');

-- --------------------------------------------------------

--
-- Table structure for table `child_achievements`
--

CREATE TABLE `child_achievements` (
  `id` int(11) NOT NULL,
  `childID` int(11) NOT NULL,
  `achievementID` int(11) NOT NULL,
  `unlocked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `child_challenges`
--

CREATE TABLE `child_challenges` (
  `id` int(11) NOT NULL,
  `childID` int(11) NOT NULL,
  `challengeID` int(11) NOT NULL,
  `progress` int(11) DEFAULT 0,
  `completed` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_challenges`
--

CREATE TABLE `daily_challenges` (
  `challengeID` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `challenge_type` varchar(50) DEFAULT NULL,
  `goal_value` int(11) DEFAULT NULL,
  `xp_reward` int(11) DEFAULT 20,
  `coin_reward` int(11) DEFAULT 15,
  `active_date` date NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `daily_challenges`
--

INSERT INTO `daily_challenges` (`challengeID`, `title`, `description`, `challenge_type`, `goal_value`, `xp_reward`, `coin_reward`, `active_date`, `expires_at`) VALUES
(1, 'Daily Explorer', 'Complete 3 activities today', 'daily_activities', 3, 30, 15, '2025-11-26', '2025-11-27 11:53:54'),
(2, 'Quiz Champion', 'Score 80% or higher on any quiz', 'quiz_score', 80, 40, 20, '2025-11-26', '2025-11-27 11:53:54'),
(3, 'Story Time', 'Read at least one story', 'read_story', 1, 20, 10, '2025-11-26', '2025-11-27 11:53:54');

-- --------------------------------------------------------

--
-- Table structure for table `games`
--

CREATE TABLE `games` (
  `gameID` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `difficulty_level` enum('easy','medium','hard') DEFAULT 'easy',
  `min_age` int(11) DEFAULT 5,
  `max_age` int(11) DEFAULT 12,
  `xp_reward` int(11) DEFAULT 10,
  `coin_reward` int(11) DEFAULT 5,
  `thumbnail` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `games`
--

INSERT INTO `games` (`gameID`, `title`, `description`, `category`, `difficulty_level`, `min_age`, `max_age`, `xp_reward`, `coin_reward`, `thumbnail`, `is_active`, `created_at`) VALUES
(1, 'Memory Match', 'Flip cards to find matching pairs! Improve your memory skills.', 'memory', 'easy', 5, 10, 15, 8, NULL, 1, '2025-11-26 11:52:35'),
(2, 'Math Dash', 'Solve math problems as fast as you can! Race against time.', 'math', 'medium', 7, 12, 20, 10, NULL, 1, '2025-11-26 11:52:35'),
(3, 'Word Builder', 'Create words from jumbled letters. Expand your vocabulary!', 'vocabulary', 'easy', 6, 11, 15, 8, NULL, 1, '2025-11-26 11:52:35'),
(4, 'Science Explorer', 'Learn amazing science facts through fun experiments!', 'science', 'medium', 8, 12, 25, 12, NULL, 1, '2025-11-26 11:52:35'),
(5, 'Catch the Stars', 'Use arrow keys to catch falling stars before time runs out!', 'reflexes', 'easy', 5, 10, 10, 5, NULL, 1, '2025-11-26 11:52:35');

-- --------------------------------------------------------

--
-- Table structure for table `leaderboard`
--

CREATE TABLE `leaderboard` (
  `id` int(11) NOT NULL,
  `childID` int(11) NOT NULL,
  `period_type` enum('weekly','monthly','all_time') NOT NULL,
  `period_start` date NOT NULL,
  `total_xp` int(11) DEFAULT 0,
  `total_games` int(11) DEFAULT 0,
  `total_quizzes` int(11) DEFAULT 0,
  `rank_position` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `learning_goals`
--

CREATE TABLE `learning_goals` (
  `goalID` int(11) NOT NULL,
  `childID` int(11) NOT NULL,
  `parentID` int(11) NOT NULL,
  `goal_type` varchar(50) DEFAULT NULL,
  `goal_description` text DEFAULT NULL,
  `target_value` int(11) DEFAULT NULL,
  `current_progress` int(11) DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','completed','expired') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `learning_goals`
--

INSERT INTO `learning_goals` (`goalID`, `childID`, `parentID`, `goal_type`, `goal_description`, `target_value`, `current_progress`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 1, 1, 'weekly_xp', 'Earn 500 XP this week', 500, 0, '2025-11-28', '2025-12-05', 'active', '2025-11-28 03:16:11'),
(2, 2, 1, 'daily_activities', 'Complete 3 activities daily', 21, 0, '2025-11-28', '2025-12-05', 'active', '2025-11-28 03:16:11');

-- --------------------------------------------------------

--
-- Table structure for table `parent_children_history`
--

CREATE TABLE `parent_children_history` (
  `historyID` int(11) NOT NULL,
  `childID` int(11) NOT NULL,
  `parentID` int(11) DEFAULT NULL,
  `action` enum('linked','unlinked') NOT NULL,
  `performed_by` enum('child','parent','system') NOT NULL,
  `invite_code` varchar(12) DEFAULT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='History of parent-child linking actions';

-- --------------------------------------------------------

--
-- Table structure for table `parent_invites`
--

CREATE TABLE `parent_invites` (
  `inviteID` int(11) NOT NULL,
  `parentID` int(11) NOT NULL,
  `invite_code` varchar(10) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_used` tinyint(1) DEFAULT 0,
  `used_by_childID` int(11) DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parent_invites`
--

INSERT INTO `parent_invites` (`inviteID`, `parentID`, `invite_code`, `expires_at`, `is_used`, `used_by_childID`, `used_at`, `created_at`) VALUES
(1, 1, 'TEST1234', '2025-12-05 03:16:11', 0, NULL, NULL, '2025-11-28 03:16:11'),
(2, 1, 'DEMO5678', '2025-12-05 03:16:11', 0, NULL, NULL, '2025-11-28 03:16:11');

-- --------------------------------------------------------

--
-- Table structure for table `parent_notifications`
--

CREATE TABLE `parent_notifications` (
  `notificationID` int(11) NOT NULL,
  `parentID` int(11) NOT NULL,
  `childID` int(11) DEFAULT NULL,
  `notification_type` varchar(50) DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parent_notifications`
--

INSERT INTO `parent_notifications` (`notificationID`, `parentID`, `childID`, `notification_type`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 1, 'achievement', 'Achievement Unlocked!', 'Ella Explorer unlocked \"Week Warrior\" achievement!', 0, '2025-11-28 03:16:11'),
(2, 1, 2, 'milestone', 'Level Up!', 'Sam Scientist reached Level 4!', 0, '2025-11-28 03:16:11');

-- --------------------------------------------------------

--
-- Table structure for table `play_sessions`
--

CREATE TABLE `play_sessions` (
  `sessionID` int(11) NOT NULL,
  `childID` int(11) NOT NULL,
  `activity_type` enum('game','quiz','story') NOT NULL,
  `activity_id` int(11) NOT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `xp_earned` int(11) DEFAULT 0,
  `coins_earned` int(11) DEFAULT 0,
  `completed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `play_sessions`
--

INSERT INTO `play_sessions` (`sessionID`, `childID`, `activity_type`, `activity_id`, `start_time`, `end_time`, `duration_seconds`, `score`, `xp_earned`, `coins_earned`, `completed`) VALUES
(1, 3, 'game', 1, '2025-11-26 12:37:36', NULL, NULL, NULL, 0, 0, 0),
(2, 3, 'game', 1, '2025-11-26 12:39:13', NULL, NULL, NULL, 0, 0, 0),
(3, 3, 'quiz', 1, '2025-11-26 12:39:20', '2025-11-26 12:39:43', 23, 100.00, 40, 20, 1),
(4, 3, 'quiz', 1, '2025-11-26 12:39:20', '2025-11-26 12:39:46', 26, 100.00, 40, 20, 1),
(5, 3, 'quiz', 1, '2025-11-26 12:39:20', '2025-11-26 12:39:48', 28, 100.00, 40, 20, 1),
(6, 3, 'quiz', 1, '2025-11-26 12:39:20', '2025-11-26 12:39:48', 28, 100.00, 40, 20, 1),
(7, 3, 'quiz', 1, '2025-11-26 12:39:21', '2025-11-26 12:39:55', 34, 100.00, 40, 20, 1),
(8, 3, 'quiz', 1, '2025-11-26 12:39:21', '2025-11-26 12:39:55', 34, 100.00, 40, 20, 1),
(9, 3, 'quiz', 1, '2025-11-26 12:39:20', '2025-11-26 12:39:55', 35, 100.00, 40, 20, 1),
(10, 3, 'quiz', 2, '2025-11-26 12:40:29', '2025-11-26 12:40:30', 1, 0.00, 15, 7, 1),
(11, 3, 'quiz', 3, '2025-11-26 12:40:34', '2025-11-26 12:40:35', 1, 0.00, 12, 6, 1),
(12, 3, 'quiz', 3, '2025-11-26 12:40:33', '2025-11-26 12:40:35', 2, 0.00, 12, 6, 1),
(13, 3, 'quiz', 4, '2025-11-26 12:40:38', '2025-11-26 12:40:39', 1, 0.00, 17, 9, 1),
(14, 3, 'story', 1, '2025-11-26 12:39:48', '2025-11-26 12:40:48', 60, NULL, 10, 5, 1),
(15, 3, 'story', 2, '2025-11-26 12:40:00', '2025-11-26 12:41:00', 60, NULL, 10, 5, 1),
(16, 3, 'story', 3, '2025-11-26 12:40:11', '2025-11-26 12:41:11', 60, NULL, 8, 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `quizzes`
--

CREATE TABLE `quizzes` (
  `quizID` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `difficulty_level` enum('easy','medium','hard') DEFAULT 'easy',
  `time_limit` int(11) DEFAULT 300,
  `passing_score` int(11) DEFAULT 60,
  `xp_reward` int(11) DEFAULT 15,
  `coin_reward` int(11) DEFAULT 10,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quizzes`
--

INSERT INTO `quizzes` (`quizID`, `title`, `description`, `category`, `difficulty_level`, `time_limit`, `passing_score`, `xp_reward`, `coin_reward`, `is_active`, `created_at`) VALUES
(1, 'Animal Kingdom Quiz', 'Test your knowledge about different animals!', 'science', 'easy', 300, 60, 20, 10, 1, '2025-11-26 11:52:35'),
(2, 'Math Champions', 'Show off your math skills with these fun problems!', 'math', 'medium', 600, 60, 30, 15, 1, '2025-11-26 11:52:35'),
(3, 'World Geography', 'How well do you know our planet? Find out!', 'geography', 'medium', 450, 60, 25, 12, 1, '2025-11-26 11:52:35'),
(4, 'Space Adventures', 'Learn about planets, stars, and the universe!', 'science', 'hard', 480, 60, 35, 18, 1, '2025-11-26 11:52:35');

-- --------------------------------------------------------

--
-- Table structure for table `quiz_options`
--

CREATE TABLE `quiz_options` (
  `optionID` int(11) NOT NULL,
  `questionID` int(11) NOT NULL,
  `option_text` text NOT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `order_num` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_options`
--

INSERT INTO `quiz_options` (`optionID`, `questionID`, `option_text`, `is_correct`, `order_num`) VALUES
(1, 1, 'Blue Whale', 1, 1),
(2, 1, 'Shark', 0, 2),
(3, 1, 'Dolphin', 0, 3),
(4, 1, 'Octopus', 0, 4),
(5, 2, '6', 0, 1),
(6, 2, '8', 1, 2),
(7, 2, '10', 0, 3),
(8, 2, '4', 0, 4),
(9, 3, 'Tiger', 0, 1),
(10, 3, 'Lion', 1, 2),
(11, 3, 'Bear', 0, 3),
(12, 3, 'Elephant', 0, 4),
(13, 4, 'Honey', 1, 1),
(14, 4, 'Milk', 0, 2),
(15, 4, 'Jam', 0, 3),
(16, 4, 'Syrup', 0, 4),
(17, 5, 'True', 0, 1),
(18, 5, 'False', 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `quiz_questions`
--

CREATE TABLE `quiz_questions` (
  `questionID` int(11) NOT NULL,
  `quizID` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('multiple_choice','true_false','fill_blank') DEFAULT 'multiple_choice',
  `correct_answer` text NOT NULL,
  `explanation` text DEFAULT NULL,
  `points` int(11) DEFAULT 10,
  `order_num` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quiz_questions`
--

INSERT INTO `quiz_questions` (`questionID`, `quizID`, `question_text`, `question_type`, `correct_answer`, `explanation`, `points`, `order_num`) VALUES
(1, 1, 'What is the largest animal in the ocean?', 'multiple_choice', 'Blue Whale', 'The blue whale can grow up to 100 feet long and weigh 200 tons!', 10, 0),
(2, 1, 'How many legs does a spider have?', 'multiple_choice', '8', 'All spiders have 8 legs. This is one way to tell them apart from insects!', 10, 0),
(3, 1, 'Which animal is known as the King of the Jungle?', 'multiple_choice', 'Lion', 'Lions are called the King of the Jungle because of their strength and majesty!', 10, 0),
(4, 1, 'What do bees make?', 'multiple_choice', 'Honey', 'Bees collect nectar from flowers and turn it into honey!', 10, 0),
(5, 1, 'Can penguins fly?', 'true_false', 'false', 'Penguins cannot fly, but they are excellent swimmers!', 10, 0);

-- --------------------------------------------------------

--
-- Table structure for table `stories`
--

CREATE TABLE `stories` (
  `storyID` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `author` varchar(100) DEFAULT NULL,
  `content` text NOT NULL,
  `moral_lesson` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `age_group` varchar(20) DEFAULT NULL,
  `reading_time` int(11) DEFAULT NULL,
  `xp_reward` int(11) DEFAULT 5,
  `coin_reward` int(11) DEFAULT 3,
  `thumbnail` varchar(255) DEFAULT NULL,
  `audio_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stories`
--

INSERT INTO `stories` (`storyID`, `title`, `author`, `content`, `moral_lesson`, `category`, `age_group`, `reading_time`, `xp_reward`, `coin_reward`, `thumbnail`, `audio_url`, `is_active`, `created_at`) VALUES
(1, 'The Brave Little Elephant', 'Bright Minds Team', 'Once upon a time, in the heart of a vast jungle, there lived a little elephant named Ella. Ella was smaller than all the other elephants, but she had the biggest dreams.\n\nOne day, a terrible storm came. The rain poured down, and the river began to flood. All the animals were scared! The bridge across the river started to break.\n\n\"We need to help!\" said Ella. Even though she was small, Ella used her trunk to help move logs and stones. She worked harder than anyone.\n\nTogether with her friends, they built a new bridge. All the animals crossed safely to the other side.\n\n\"Thank you, Ella!\" everyone cheered. \"You may be little, but you have the biggest heart!\"', 'Even the smallest person can make a big difference when they try their best!', 'courage', '5-8', 5, 10, 5, NULL, NULL, 1, '2025-11-26 11:52:35'),
(2, 'The Rainbow Painter', 'Bright Minds Team', 'Sam loved to paint. Every day after school, he would paint pictures of everything he saw - trees, birds, houses, and friends.\n\nOne morning, Sam woke up and found that all the colors in his town had disappeared! Everything was gray - the sky, the grass, even the flowers!\n\n\"This is terrible!\" said the townspeople. \"What can we do?\"\n\nSam had an idea. He took his paintbrush and started painting. He painted the sky blue, the grass green, and the flowers every color of the rainbow.\n\nAs Sam painted, something magical happened. The colors came back! The whole town sparkled with beautiful colors again.\n\n\"Thank you, Sam!\" everyone said. \"Your creativity saved our town!\"\n\nFrom that day on, Sam knew that art could make the world a more beautiful place.', 'Creativity and imagination can brighten the world around us!', 'creativity', '6-9', 6, 10, 5, NULL, NULL, 1, '2025-11-26 11:52:35'),
(3, 'The Curious Cat\'s Journey', 'Bright Minds Team', 'There once was a curious cat named Whiskers who loved to explore. One day, Whiskers wondered, \"What\'s beyond the hill?\"\n\nWhiskers climbed and climbed until she reached the top. There, she found a beautiful meadow full of butterflies!\n\n\"Wow!\" she thought. \"But what\'s beyond those trees?\"\n\nWhiskers kept exploring and found a sparkling stream, a field of flowers, and made many new friends.\n\nWhen she finally returned home, her family asked, \"Where have you been?\"\n\n\"On an adventure!\" Whiskers said. \"And I learned that there\'s always something new to discover if you\'re curious enough to look!\"', 'Curiosity leads to wonderful discoveries and new adventures!', 'exploration', '5-7', 4, 8, 4, NULL, NULL, 1, '2025-11-26 11:52:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `userID` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('parent','child') NOT NULL DEFAULT 'child',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `parent_code` varchar(12) DEFAULT NULL COMMENT 'Permanent parent identification code'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`userID`, `username`, `email`, `password`, `role`, `created_at`, `last_login`, `is_active`, `parent_code`) VALUES
(1, 'parent_demo', 'parent@brightminds.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'parent', '2025-11-26 11:52:35', NULL, 1, 'PAR-22E18'),
(2, 'ella_explorer', 'ella@brightminds.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'child', '2025-11-26 11:52:35', NULL, 1, NULL),
(3, 'sam_scientist', 'sam@brightminds.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'child', '2025-11-26 11:52:35', NULL, 1, NULL),
(4, 'Getty', 'getty@gmail.com', '$2y$10$dmrPSe3LR05OUL72ovHSvOMnKc4njanxYiRnkby1Td9bTwKmcSWx.', 'child', '2025-11-26 12:02:06', '2025-11-26 13:21:24', 1, NULL),
(5, 'nana', 'nana@gmail.com', '$2y$10$kkxJIjKUJ.hVX4KY.RIqXeqQPtHTvj.aKkO2//pW4BAo492D64IHK', 'child', '2025-11-26 13:19:21', NULL, 1, NULL),
(6, 'naana', 'naana@gmail.com', '$2y$10$PXngFLs64v/luhqa2XLZIupklV5pVBEIJ4I/YL4WjjXfL0lPasWRO', 'child', '2025-11-29 14:44:56', NULL, 1, NULL);

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `before_parent_insert` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
    IF NEW.role = 'parent' AND (NEW.parent_code IS NULL OR NEW.parent_code = '') THEN
        SET NEW.parent_code = CONCAT('PAR-', UPPER(SUBSTRING(MD5(CONCAT(NEW.userID, RAND(), NOW())), 1, 5)));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `sessionID` int(11) NOT NULL,
  `userID` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`sessionID`, `userID`, `session_token`, `ip_address`, `user_agent`, `created_at`, `expires_at`, `is_active`) VALUES
(1, 4, '377a77456a38fe76d5a1af14fd5b10ccb67285d6f210525f18b43766a0fdda25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 12:37:22', '2025-11-27 12:37:22', 0),
(2, 4, '163edbf068654e0d72845f1ba1c65c51b07efb0842ac2940bf1c5a643540974f', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:09:26', '2025-12-26 13:09:26', 0),
(3, 5, '15092baa18be881f079dbef65cb30d6c17a243ee3ed3aa7a4b9874b198329983', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:19:21', '2025-11-27 13:19:21', 0),
(4, 4, '359a3c11f3e8ced89c3928fd96bbf116b45ea8b9b21d29cb06356c18866d92d3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', '2025-11-26 13:21:24', '2025-11-27 13:21:24', 1),
(5, 6, '76ed337fbb48ddf3e00fcff2d4e2ad7d571286831adf528a8c1b3b5335281bb0', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-29 14:44:56', '2025-11-30 14:44:56', 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_child_progress`
-- (See below for the actual view)
--
CREATE TABLE `vw_child_progress` (
`childID` int(11)
,`display_name` varchar(100)
,`age` int(11)
,`avatar` varchar(50)
,`total_xp` int(11)
,`current_level` int(11)
,`coins` int(11)
,`streak_days` int(11)
,`total_sessions` bigint(21)
,`games_played` bigint(21)
,`quizzes_taken` bigint(21)
,`stories_read` bigint(21)
,`achievements_unlocked` bigint(21)
,`avg_quiz_score` decimal(9,6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_recent_activities`
-- (See below for the actual view)
--
CREATE TABLE `vw_recent_activities` (
`sessionID` int(11)
,`childID` int(11)
,`display_name` varchar(100)
,`activity_type` enum('game','quiz','story')
,`activity_title` varchar(200)
,`score` decimal(5,2)
,`xp_earned` int(11)
,`coins_earned` int(11)
,`completed` tinyint(1)
,`start_time` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `vw_child_progress`
--
DROP TABLE IF EXISTS `vw_child_progress`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_child_progress`  AS SELECT `c`.`childID` AS `childID`, `c`.`display_name` AS `display_name`, `c`.`age` AS `age`, `c`.`avatar` AS `avatar`, `c`.`total_xp` AS `total_xp`, `c`.`current_level` AS `current_level`, `c`.`coins` AS `coins`, `c`.`streak_days` AS `streak_days`, count(distinct `ps`.`sessionID`) AS `total_sessions`, count(distinct case when `ps`.`activity_type` = 'game' then `ps`.`sessionID` end) AS `games_played`, count(distinct case when `ps`.`activity_type` = 'quiz' then `ps`.`sessionID` end) AS `quizzes_taken`, count(distinct case when `ps`.`activity_type` = 'story' then `ps`.`sessionID` end) AS `stories_read`, count(distinct `ca`.`achievementID`) AS `achievements_unlocked`, avg(case when `ps`.`activity_type` = 'quiz' then `ps`.`score` end) AS `avg_quiz_score` FROM ((`children` `c` left join `play_sessions` `ps` on(`c`.`childID` = `ps`.`childID`)) left join `child_achievements` `ca` on(`c`.`childID` = `ca`.`childID`)) GROUP BY `c`.`childID` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_recent_activities`
--
DROP TABLE IF EXISTS `vw_recent_activities`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_recent_activities`  AS SELECT `ps`.`sessionID` AS `sessionID`, `c`.`childID` AS `childID`, `c`.`display_name` AS `display_name`, `ps`.`activity_type` AS `activity_type`, CASE WHEN `ps`.`activity_type` = 'game' THEN `g`.`title` WHEN `ps`.`activity_type` = 'quiz' THEN `q`.`title` WHEN `ps`.`activity_type` = 'story' THEN `s`.`title` END AS `activity_title`, `ps`.`score` AS `score`, `ps`.`xp_earned` AS `xp_earned`, `ps`.`coins_earned` AS `coins_earned`, `ps`.`completed` AS `completed`, `ps`.`start_time` AS `start_time` FROM ((((`play_sessions` `ps` join `children` `c` on(`ps`.`childID` = `c`.`childID`)) left join `games` `g` on(`ps`.`activity_type` = 'game' and `ps`.`activity_id` = `g`.`gameID`)) left join `quizzes` `q` on(`ps`.`activity_type` = 'quiz' and `ps`.`activity_id` = `q`.`quizID`)) left join `stories` `s` on(`ps`.`activity_type` = 'story' and `ps`.`activity_id` = `s`.`storyID`)) ORDER BY `ps`.`start_time` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `achievements`
--
ALTER TABLE `achievements`
  ADD PRIMARY KEY (`achievementID`);

--
-- Indexes for table `children`
--
ALTER TABLE `children`
  ADD PRIMARY KEY (`childID`),
  ADD KEY `idx_user` (`userID`),
  ADD KEY `idx_parent` (`parentID`),
  ADD KEY `idx_parent_id` (`parentID`);

--
-- Indexes for table `child_achievements`
--
ALTER TABLE `child_achievements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_achievement` (`childID`,`achievementID`),
  ADD KEY `achievementID` (`achievementID`),
  ADD KEY `idx_child` (`childID`),
  ADD KEY `idx_achievements_child_unlocked` (`childID`,`unlocked_at`);

--
-- Indexes for table `child_challenges`
--
ALTER TABLE `child_challenges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_child` (`childID`),
  ADD KEY `idx_challenge` (`challengeID`);

--
-- Indexes for table `daily_challenges`
--
ALTER TABLE `daily_challenges`
  ADD PRIMARY KEY (`challengeID`),
  ADD KEY `idx_date` (`active_date`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`gameID`);

--
-- Indexes for table `leaderboard`
--
ALTER TABLE `leaderboard`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_period` (`childID`,`period_type`,`period_start`),
  ADD KEY `idx_period` (`period_type`,`period_start`),
  ADD KEY `idx_rank` (`rank_position`),
  ADD KEY `idx_leaderboard_period_rank` (`period_type`,`period_start`,`rank_position`);

--
-- Indexes for table `learning_goals`
--
ALTER TABLE `learning_goals`
  ADD PRIMARY KEY (`goalID`),
  ADD KEY `parentID` (`parentID`),
  ADD KEY `idx_child` (`childID`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `parent_children_history`
--
ALTER TABLE `parent_children_history`
  ADD PRIMARY KEY (`historyID`),
  ADD KEY `idx_child` (`childID`),
  ADD KEY `idx_parent` (`parentID`),
  ADD KEY `idx_action_date` (`action_date`);

--
-- Indexes for table `parent_invites`
--
ALTER TABLE `parent_invites`
  ADD PRIMARY KEY (`inviteID`),
  ADD UNIQUE KEY `invite_code` (`invite_code`),
  ADD KEY `idx_code` (`invite_code`),
  ADD KEY `idx_parent` (`parentID`),
  ADD KEY `idx_expires_used` (`expires_at`,`is_used`);

--
-- Indexes for table `parent_notifications`
--
ALTER TABLE `parent_notifications`
  ADD PRIMARY KEY (`notificationID`),
  ADD KEY `childID` (`childID`),
  ADD KEY `idx_parent` (`parentID`),
  ADD KEY `idx_read` (`is_read`);

--
-- Indexes for table `play_sessions`
--
ALTER TABLE `play_sessions`
  ADD PRIMARY KEY (`sessionID`),
  ADD KEY `idx_child` (`childID`),
  ADD KEY `idx_activity` (`activity_type`,`activity_id`),
  ADD KEY `idx_completed` (`completed`),
  ADD KEY `idx_sessions_child_activity` (`childID`,`activity_type`,`start_time`);

--
-- Indexes for table `quizzes`
--
ALTER TABLE `quizzes`
  ADD PRIMARY KEY (`quizID`);

--
-- Indexes for table `quiz_options`
--
ALTER TABLE `quiz_options`
  ADD PRIMARY KEY (`optionID`),
  ADD KEY `idx_question` (`questionID`);

--
-- Indexes for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD PRIMARY KEY (`questionID`),
  ADD KEY `idx_quiz` (`quizID`);

--
-- Indexes for table `stories`
--
ALTER TABLE `stories`
  ADD PRIMARY KEY (`storyID`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`userID`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `parent_code` (`parent_code`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_parent_code` (`parent_code`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`sessionID`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_token` (`session_token`),
  ADD KEY `idx_user` (`userID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `achievements`
--
ALTER TABLE `achievements`
  MODIFY `achievementID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `childID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `child_achievements`
--
ALTER TABLE `child_achievements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `child_challenges`
--
ALTER TABLE `child_challenges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_challenges`
--
ALTER TABLE `daily_challenges`
  MODIFY `challengeID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `gameID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `leaderboard`
--
ALTER TABLE `leaderboard`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `learning_goals`
--
ALTER TABLE `learning_goals`
  MODIFY `goalID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `parent_children_history`
--
ALTER TABLE `parent_children_history`
  MODIFY `historyID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parent_invites`
--
ALTER TABLE `parent_invites`
  MODIFY `inviteID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `parent_notifications`
--
ALTER TABLE `parent_notifications`
  MODIFY `notificationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `play_sessions`
--
ALTER TABLE `play_sessions`
  MODIFY `sessionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `quizzes`
--
ALTER TABLE `quizzes`
  MODIFY `quizID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `quiz_options`
--
ALTER TABLE `quiz_options`
  MODIFY `optionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  MODIFY `questionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `stories`
--
ALTER TABLE `stories`
  MODIFY `storyID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `userID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `sessionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `children`
--
ALTER TABLE `children`
  ADD CONSTRAINT `children_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `children_ibfk_2` FOREIGN KEY (`parentID`) REFERENCES `users` (`userID`) ON DELETE SET NULL;

--
-- Constraints for table `child_achievements`
--
ALTER TABLE `child_achievements`
  ADD CONSTRAINT `child_achievements_ibfk_1` FOREIGN KEY (`childID`) REFERENCES `children` (`childID`) ON DELETE CASCADE,
  ADD CONSTRAINT `child_achievements_ibfk_2` FOREIGN KEY (`achievementID`) REFERENCES `achievements` (`achievementID`) ON DELETE CASCADE;

--
-- Constraints for table `child_challenges`
--
ALTER TABLE `child_challenges`
  ADD CONSTRAINT `child_challenges_ibfk_1` FOREIGN KEY (`childID`) REFERENCES `children` (`childID`) ON DELETE CASCADE,
  ADD CONSTRAINT `child_challenges_ibfk_2` FOREIGN KEY (`challengeID`) REFERENCES `daily_challenges` (`challengeID`) ON DELETE CASCADE;

--
-- Constraints for table `leaderboard`
--
ALTER TABLE `leaderboard`
  ADD CONSTRAINT `leaderboard_ibfk_1` FOREIGN KEY (`childID`) REFERENCES `children` (`childID`) ON DELETE CASCADE;

--
-- Constraints for table `learning_goals`
--
ALTER TABLE `learning_goals`
  ADD CONSTRAINT `learning_goals_ibfk_1` FOREIGN KEY (`childID`) REFERENCES `children` (`childID`) ON DELETE CASCADE,
  ADD CONSTRAINT `learning_goals_ibfk_2` FOREIGN KEY (`parentID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `parent_children_history`
--
ALTER TABLE `parent_children_history`
  ADD CONSTRAINT `parent_children_history_ibfk_1` FOREIGN KEY (`childID`) REFERENCES `children` (`childID`) ON DELETE CASCADE;

--
-- Constraints for table `parent_invites`
--
ALTER TABLE `parent_invites`
  ADD CONSTRAINT `parent_invites_ibfk_1` FOREIGN KEY (`parentID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;

--
-- Constraints for table `parent_notifications`
--
ALTER TABLE `parent_notifications`
  ADD CONSTRAINT `parent_notifications_ibfk_1` FOREIGN KEY (`parentID`) REFERENCES `users` (`userID`) ON DELETE CASCADE,
  ADD CONSTRAINT `parent_notifications_ibfk_2` FOREIGN KEY (`childID`) REFERENCES `children` (`childID`) ON DELETE CASCADE;

--
-- Constraints for table `play_sessions`
--
ALTER TABLE `play_sessions`
  ADD CONSTRAINT `play_sessions_ibfk_1` FOREIGN KEY (`childID`) REFERENCES `children` (`childID`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_options`
--
ALTER TABLE `quiz_options`
  ADD CONSTRAINT `quiz_options_ibfk_1` FOREIGN KEY (`questionID`) REFERENCES `quiz_questions` (`questionID`) ON DELETE CASCADE;

--
-- Constraints for table `quiz_questions`
--
ALTER TABLE `quiz_questions`
  ADD CONSTRAINT `quiz_questions_ibfk_1` FOREIGN KEY (`quizID`) REFERENCES `quizzes` (`quizID`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`userID`) REFERENCES `users` (`userID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

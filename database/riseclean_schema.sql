-- ==========================================
-- RiseClean Database Schema
-- Database: riseclean_db
-- RiseClean Platform - Rise to a Cleaner Future
-- ==========================================

-- Create database
CREATE DATABASE IF NOT EXISTS riseclean_db;
USE riseclean_db;

-- ==========================================
-- Table: roles (from existing template)
-- ==========================================
CREATE TABLE IF NOT EXISTS roles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default roles
INSERT INTO roles (role_name, description) VALUES
('admin', 'Full system access with all permissions'),
('manager', 'Management level access with limited permissions'),
('staff', 'Basic staff access for daily operations'),
('user', 'Regular user access for educational modules and challenges');

-- ==========================================
-- Table: users (extended for RiseClean gamification)
-- ==========================================
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    role_id INT NOT NULL DEFAULT 4,
    total_xp INT DEFAULT 0,
    total_points INT DEFAULT 0,
    current_level INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role_id (role_id),
    INDEX idx_total_xp (total_xp),
    INDEX idx_total_points (total_points),
    INDEX idx_current_level (current_level)
);

-- ==========================================
-- Table: levels (RiseClean gamification system)
-- ==========================================
CREATE TABLE IF NOT EXISTS levels (
    level_id INT PRIMARY KEY,
    level_name VARCHAR(50) NOT NULL,
    min_xp INT NOT NULL
);

-- Insert default levels
INSERT INTO levels (level_id, level_name, min_xp) VALUES
(1, 'Newbie', 0),
(2, 'Eco-Friend', 100),
(3, 'Green Apprentice', 300),
(4, 'Eco-Warrior', 600),
(5, 'Green Master', 1000),
(6, 'Eco-Champion', 1500),
(7, 'Sustainability Hero', 2100),
(8, 'Planet Protector', 3000);

-- ==========================================
-- Table: education_modules (Educational content)
-- ==========================================
CREATE TABLE IF NOT EXISTS education_modules (
    module_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    content TEXT,
    xp_reward INT DEFAULT 10,
    point_reward INT DEFAULT 5,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    category VARCHAR(50),
    duration_minutes INT DEFAULT 10,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==========================================
-- Table: challenges (Daily/special challenges)
-- ==========================================
CREATE TABLE IF NOT EXISTS challenges (
    challenge_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    xp_reward INT DEFAULT 20,
    point_reward INT DEFAULT 10,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    challenge_type ENUM('daily', 'special', 'weekly') DEFAULT 'daily',
    start_date DATE,
    end_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==========================================
-- Table: user_progress (Track completed modules and challenges)
-- ==========================================
CREATE TABLE IF NOT EXISTS user_progress (
    progress_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    item_id INT NOT NULL, -- Can be module_id or challenge_id
    item_type ENUM('module', 'challenge') NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    is_verified TINYINT(1) DEFAULT 0,
    submission_text TEXT,
    submission_image VARCHAR(255),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_item (user_id, item_id, item_type),
    INDEX idx_item_type (item_type, item_id),
    INDEX idx_completed_at (completed_at),
    INDEX idx_is_verified (is_verified)
);

-- ==========================================
-- Table: rewards (Reward catalog for point redemption)
-- ==========================================
CREATE TABLE IF NOT EXISTS rewards (
    reward_id INT PRIMARY KEY AUTO_INCREMENT,
    reward_name VARCHAR(100) NOT NULL,
    point_cost INT NOT NULL,
    description TEXT,
    image VARCHAR(255),
    stock INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==========================================
-- Table: user_rewards (Track rewards claimed by users)
-- ==========================================
CREATE TABLE IF NOT EXISTS user_rewards (
    user_reward_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reward_id INT NOT NULL,
    quantity INT DEFAULT 1,
    claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES rewards(reward_id) ON DELETE CASCADE,

    INDEX idx_user_reward (user_id, reward_id)
);


-- ==========================================
-- Table: activity_logs (from existing template)
-- ==========================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    activity_type VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at)
);

-- ==========================================
-- Views for easy data access
-- ==========================================

-- View: users with level information
CREATE OR REPLACE VIEW v_users_with_levels AS
SELECT
    u.id,
    u.username,
    u.email,
    u.first_name,
    u.last_name,
    u.total_xp,
    u.total_points,
    u.current_level,
    l.level_name,
    u.is_active,
    u.last_login,
    u.created_at
FROM users u
LEFT JOIN levels l ON u.current_level = l.level_id;

-- View: leaderboard (by XP)
CREATE OR REPLACE VIEW v_leaderboard AS
SELECT
    u.id,
    u.username,
    u.first_name,
    u.last_name,
    u.total_xp,
    u.total_points,
    u.current_level,
    l.level_name,
    RANK() OVER (ORDER BY u.total_xp DESC, u.total_points DESC) as xp_rank
FROM users u
LEFT JOIN levels l ON u.current_level = l.level_id
WHERE u.is_active = 1
ORDER BY u.total_xp DESC, u.total_points DESC;

-- View: completed modules per user
CREATE OR REPLACE VIEW v_user_completed_modules AS
SELECT
    u.id as user_id,
    u.username,
    u.first_name,
    u.last_name,
    up.item_id as module_id,
    em.title as module_title,
    up.completed_at,
    up.is_verified
FROM user_progress up
JOIN users u ON up.user_id = u.id
JOIN education_modules em ON up.item_id = em.module_id
WHERE up.item_type = 'module';

-- View: completed challenges per user
CREATE OR REPLACE VIEW v_user_completed_challenges AS
SELECT
    u.id as user_id,
    u.username,
    u.first_name,
    u.last_name,
    up.item_id as challenge_id,
    c.title as challenge_title,
    up.completed_at,
    up.is_verified
FROM user_progress up
JOIN users u ON up.user_id = u.id
JOIN challenges c ON up.item_id = c.challenge_id
WHERE up.item_type = 'challenge';

-- ==========================================
-- Insert sample data
-- ==========================================

-- Insert sample admin user
INSERT INTO users (username, email, password, first_name, last_name, phone, role_id, is_active, total_xp, total_points) VALUES
('admin', 'admin@riseclean.com', '$2y$10$vuT0T56.1nqR1mzWBGKKH.lILeAA7EvUjyBTnBmaCVwuixoZgfKqy', 'Admin', 'RiseClean', '+62 895-3895-71188', 1, 1, 0, 0);

-- Insert sample education modules
INSERT INTO education_modules (title, content, xp_reward, point_reward, category, duration_minutes) VALUES
('Introduction to Waste Management', 'Learn the basics of waste management and its importance in our daily lives.', 15, 8, 'Basics', 15),
('Recycling Techniques', 'Discover different recycling methods and how to properly sort materials.', 25, 12, 'Recycling', 25),
('Composting at Home', 'Step-by-step guide to starting your own composting system.', 30, 15, 'Organic Waste', 30),
('Plastic Waste Reduction', 'Practical tips to reduce plastic consumption in everyday life.', 20, 10, 'Reduction', 20),
('E-waste Disposal', 'Safe handling and disposal methods for electronic waste.', 35, 18, 'Special Waste', 35);

-- Insert sample challenges
INSERT INTO challenges (title, description, xp_reward, point_reward, challenge_type) VALUES
('Sort Recyclables', 'Sort your household recyclables into appropriate bins.', 25, 15, 'daily'),
('Reduce Plastic Use', 'Avoid using single-use plastics for 24 hours.', 30, 18, 'daily'),
('Clean Your Space', 'Tidy up a small area in your home or workplace.', 20, 12, 'daily'),
('Plant a Tree', 'Plant a tree or take care of a plant at home.', 50, 30, 'special'),
('Share Awareness', 'Share information about waste management with friends.', 35, 20, 'special');

-- Insert sample rewards
INSERT INTO rewards (reward_name, point_cost, description, stock) VALUES
('Eco-Friendly Water Bottle', 200, 'Reusable water bottle to reduce plastic waste', 50),
('Digital Certificate', 100, 'Certificate of participation in RiseClean platform', 1000),
('Eco Shopping Bag', 150, 'Reusable shopping bag to reduce plastic usage', 75),
('Tree Planting Voucher', 500, 'Voucher for a tree planting activity', 25),
('RiseClean T-Shirt', 300, 'Limited edition RiseClean awareness t-shirt', 40);

-- ==========================================
-- Triggers to update user level when XP changes
-- ==========================================

DELIMITER //
CREATE TRIGGER tr_update_user_level_after_insert
AFTER INSERT ON user_progress
FOR EACH ROW
BEGIN
    DECLARE new_xp INT DEFAULT 0;
    DECLARE new_level INT DEFAULT 1;
    DECLARE max_level INT DEFAULT 0;
    
    -- Calculate new XP based on completed item
    IF NEW.item_type = 'module' THEN
        SELECT xp_reward INTO @reward FROM education_modules WHERE module_id = NEW.item_id;
    ELSEIF NEW.item_type = 'challenge' THEN
        SELECT xp_reward INTO @reward FROM challenges WHERE challenge_id = NEW.item_id;
    END IF;
    
    -- Update user total XP
    SELECT total_xp INTO new_xp FROM users WHERE id = NEW.user_id;
    SET new_xp = new_xp + @reward;
    
    UPDATE users SET total_xp = new_xp WHERE id = NEW.user_id;
    
    -- Determine new level based on XP thresholds
    SELECT MAX(level_id) INTO max_level FROM levels;
    
    SELECT level_id INTO new_level FROM levels 
    WHERE min_xp <= new_xp 
    ORDER BY min_xp DESC 
    LIMIT 1;
    
    UPDATE users SET current_level = new_level WHERE id = NEW.user_id;
END //

CREATE TRIGGER tr_update_user_points_after_insert
AFTER INSERT ON user_progress
FOR EACH ROW
BEGIN
    DECLARE reward_points INT DEFAULT 0;
    
    -- Calculate reward points based on completed item
    IF NEW.item_type = 'module' THEN
        SELECT point_reward INTO @reward FROM education_modules WHERE module_id = NEW.item_id;
    ELSEIF NEW.item_type = 'challenge' THEN
        SELECT point_reward INTO @reward FROM challenges WHERE challenge_id = NEW.item_id;
    END IF;
    
    -- Update user total points
    UPDATE users SET total_points = total_points + @reward WHERE id = NEW.user_id;
END //
DELIMITER ;

SELECT 'RiseClean database created successfully!' as message;
SELECT 'Database name: riseclean_db' as info;
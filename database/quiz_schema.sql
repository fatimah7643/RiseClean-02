-- ==========================================
-- RiseClean Quiz Database Schema
-- Add quiz functionality to riseclean_db
-- ==========================================

-- ==========================================
-- Table: quiz_questions (Quiz questions for educational evaluation)
-- ==========================================
CREATE TABLE IF NOT EXISTS quiz_questions (
    question_id INT PRIMARY KEY AUTO_INCREMENT,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'short_answer') DEFAULT 'multiple_choice',
    category VARCHAR(50),
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    xp_reward INT DEFAULT 10,
    point_reward INT DEFAULT 5,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_difficulty (difficulty),
    INDEX idx_is_active (is_active),
    INDEX idx_created_at (created_at)
);

-- ==========================================
-- Table: quiz_choices (Answer choices for multiple choice questions)
-- ==========================================
CREATE TABLE IF NOT EXISTS quiz_choices (
    choice_id INT PRIMARY KEY AUTO_INCREMENT,
    question_id INT NOT NULL,
    choice_text TEXT NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    choice_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(question_id) ON DELETE CASCADE,
    INDEX idx_question_id (question_id),
    INDEX idx_choice_order (choice_order)
);

-- ==========================================
-- Table: user_quiz_answers (Track user answers to quiz questions)
-- ==========================================
CREATE TABLE IF NOT EXISTS user_quiz_answers (
    answer_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_choice_id INT NULL, -- For multiple choice questions
    answer_text TEXT NULL, -- For short answer questions
    is_correct TINYINT(1) DEFAULT 0,
    points_earned INT DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(question_id) ON DELETE CASCADE,
    FOREIGN KEY (selected_choice_id) REFERENCES quiz_choices(choice_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_question_id (question_id),
    INDEX idx_is_correct (is_correct),
    INDEX idx_submitted_at (submitted_at)
);

-- ==========================================
-- Table: quiz_sessions (Track quiz sessions for progress)
-- ==========================================
CREATE TABLE IF NOT EXISTS quiz_sessions (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    questions_count INT DEFAULT 0,
    questions_answered INT DEFAULT 0,
    score DECIMAL(5,2) DEFAULT 0.00, -- Percentage score
    total_xp_earned INT DEFAULT 0,
    total_points_earned INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    is_completed TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token),
    INDEX idx_is_completed (is_completed),
    INDEX idx_started_at (started_at)
);

-- ==========================================
-- Insert sample quiz questions
-- ==========================================

-- Insert sample questions about waste management
INSERT INTO quiz_questions (question_text, question_type, category, difficulty, xp_reward, point_reward, is_active) VALUES
('Apa yang dimaksud dengan 3R dalam pengelolaan sampah?', 'multiple_choice', 'Basics', 'easy', 15, 8),
('Plastik dapat didaur ulang berapa kali?', 'multiple_choice', 'Recycling', 'medium', 20, 10),
('Apa manfaat dari composting (pembuatan kompos)?', 'multiple_choice', 'Organic Waste', 'medium', 25, 12),
('Apa yang dimaksud dengan e-waste?', 'multiple_choice', 'Special Waste', 'easy', 15, 8),
('Apa yang sebaiknya dilakukan sebelum membuang baterai bekas?', 'multiple_choice', 'Special Waste', 'hard', 30, 15);

-- Insert choices for the first question
INSERT INTO quiz_choices (question_id, choice_text, is_correct, choice_order) VALUES
(1, 'Reduce, Reuse, Recycle', 1, 1),
(1, 'Read, Remember, Repeat', 0, 2),
(1, 'Run, Rest, Relax', 0, 3),
(1, 'Rise, Rise, Rise', 0, 4);

-- Insert choices for the second question
INSERT INTO quiz_choices (question_id, choice_text, is_correct, choice_order) VALUES
(2, 'Sekali', 0, 1),
(2, 'Dua kali', 0, 2),
(2, 'Tiga kali', 0, 3),
(2, 'Bergantung pada jenis plastik', 1, 4);

-- Insert choices for the third question
INSERT INTO quiz_choices (question_id, choice_text, is_correct, choice_order) VALUES
(3, 'Mengurangi produksi sampah organik', 0, 1),
(3, 'Menghasilkan pupuk organik yang berguna', 1, 2),
(3, 'Mengurangi penggunaan plastik', 0, 3),
(3, 'Membuat tanah menjadi asam', 0, 4);

-- Insert choices for the fourth question
INSERT INTO quiz_choices (question_id, choice_text, is_correct, choice_order) VALUES
(4, 'Sampah elektronik', 1, 1),
(4, 'Sampah makanan', 0, 2),
(4, 'Sampah plastik', 0, 3),
(4, 'Sampah kertas', 0, 4);

-- Insert choices for the fifth question
INSERT INTO quiz_choices (question_id, choice_text, is_correct, choice_order) VALUES
(5, 'Dibuang sembarangan', 0, 1),
(5, 'Dibakar agar tidak mencemari lingkungan', 0, 2),
(5, 'Dibawa ke tempat daur ulang khusus', 1, 3),
(5, 'Ditanam dalam tanah', 0, 4);

-- ==========================================
-- Triggers to update user XP and points when quiz answers are inserted
-- ==========================================

DELIMITER //

CREATE TRIGGER tr_update_user_xp_points_after_quiz_answer
AFTER INSERT ON user_quiz_answers
FOR EACH ROW
BEGIN
    DECLARE reward_xp INT DEFAULT 0;
    DECLARE reward_points INT DEFAULT 0;
    DECLARE current_xp INT DEFAULT 0;
    DECLARE current_points INT DEFAULT 0;
    DECLARE new_level INT DEFAULT 1;
    DECLARE max_level INT DEFAULT 0;

    -- Get reward values if the answer is correct
    IF NEW.is_correct = 1 THEN
        SELECT xp_reward, point_reward INTO reward_xp, reward_points
        FROM quiz_questions
        WHERE question_id = NEW.question_id;

        -- Get current user XP and points
        SELECT total_xp, total_points INTO current_xp, current_points
        FROM users
        WHERE id = NEW.user_id;

        -- Update user total XP and points
        UPDATE users
        SET total_xp = current_xp + reward_xp,
            total_points = current_points + reward_points
        WHERE id = NEW.user_id;

        -- Determine new level based on updated XP
        SELECT MAX(level_id) INTO max_level FROM levels;

        SELECT level_id INTO new_level FROM levels
        WHERE min_xp <= (current_xp + reward_xp)
        ORDER BY min_xp DESC
        LIMIT 1;

        UPDATE users SET current_level = new_level WHERE id = NEW.user_id;
    END IF;
END //

DELIMITER ;

SELECT 'Quiz tables created successfully!' as message;
-- ==========================================
-- RiseClean Quiz-Education Module Integration
-- Add module_id to quiz_questions table
-- ==========================================

-- Add module_id column to quiz_questions table
ALTER TABLE quiz_questions ADD COLUMN module_id INT NULL DEFAULT NULL AFTER question_text;

-- Add foreign key constraint
ALTER TABLE quiz_questions 
ADD CONSTRAINT fk_quiz_questions_module_id 
FOREIGN KEY (module_id) REFERENCES education_modules(module_id) 
ON DELETE SET NULL;

-- Add index for better performance
ALTER TABLE quiz_questions ADD INDEX idx_module_id (module_id);

-- Update the trigger to handle module completion properly
DROP TRIGGER IF EXISTS tr_update_user_xp_points_after_quiz_answer;

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
    DECLARE module_id_val INT DEFAULT NULL;
    DECLARE quiz_question_xp INT DEFAULT 0;
    DECLARE quiz_question_points INT DEFAULT 0;

    -- Get reward values if the answer is correct
    IF NEW.is_correct = 1 THEN
        SELECT xp_reward, point_reward, module_id INTO quiz_question_xp, quiz_question_points, module_id_val
        FROM quiz_questions
        WHERE question_id = NEW.question_id;

        -- If this question is associated with a module, we need to handle module completion differently
        IF module_id_val IS NOT NULL THEN
            -- For module-associated quizzes, we'll handle rewards differently
            -- Check if user has already completed this module
            SELECT progress_id INTO @progress_id 
            FROM user_progress 
            WHERE user_id = NEW.user_id AND item_id = module_id_val AND item_type = 'module';
            
            -- If module hasn't been marked as completed yet, mark it now
            IF @progress_id IS NULL THEN
                -- Insert progress record for the module
                INSERT INTO user_progress (user_id, item_id, item_type, is_verified)
                VALUES (NEW.user_id, module_id_val, 'module', 1);
                
                -- Get the module's reward values
                SELECT xp_reward, point_reward INTO reward_xp, reward_points
                FROM education_modules
                WHERE module_id = module_id_val;
                
                -- Update user total XP and points based on module rewards
                UPDATE users
                SET total_xp = total_xp + reward_xp,
                    total_points = total_points + reward_points
                WHERE id = NEW.user_id;

                -- Determine new level based on updated XP
                SELECT MAX(level_id) INTO max_level FROM levels;

                SELECT level_id INTO new_level FROM levels
                WHERE min_xp <= (SELECT total_xp FROM users WHERE id = NEW.user_id)
                ORDER BY min_xp DESC
                LIMIT 1;

                UPDATE users SET current_level = new_level WHERE id = NEW.user_id;
            END IF;
        ELSE
            -- For standalone quizzes, use the quiz's reward values
            SET reward_xp = quiz_question_xp;
            SET reward_points = quiz_question_points;

            -- Update user total XP and points
            UPDATE users
            SET total_xp = total_xp + reward_xp,
                total_points = total_points + reward_points
            WHERE id = NEW.user_id;

            -- Determine new level based on updated XP
            SELECT MAX(level_id) INTO max_level FROM levels;

            SELECT level_id INTO new_level FROM levels
            WHERE min_xp <= (SELECT total_xp FROM users WHERE id = NEW.user_id)
            ORDER BY min_xp DESC
            LIMIT 1;

            UPDATE users SET current_level = new_level WHERE id = NEW.user_id;
        END IF;
    END IF;
END //

DELIMITER ;

-- Also create a function to check if a module's quiz has been completed
DELIMITER //

CREATE PROCEDURE CheckModuleQuizCompletion(
    IN p_user_id INT,
    IN p_module_id INT,
    OUT p_quiz_completed BOOLEAN
)
BEGIN
    DECLARE quiz_count INT DEFAULT 0;
    DECLARE answered_count INT DEFAULT 0;
    
    -- Get the total number of quiz questions for this module
    SELECT COUNT(*) INTO quiz_count
    FROM quiz_questions
    WHERE module_id = p_module_id AND is_active = 1;
    
    -- If no quizzes for this module, consider it completed
    IF quiz_count = 0 THEN
        SET p_quiz_completed = TRUE;
    ELSE
        -- Get the number of correctly answered questions for this module's quizzes
        SELECT COUNT(*) INTO answered_count
        FROM user_quiz_answers uqa
        JOIN quiz_questions qq ON uqa.question_id = qq.question_id
        WHERE uqa.user_id = p_user_id 
          AND qq.module_id = p_module_id
          AND uqa.is_correct = 1;
          
        -- Check if all questions have been answered correctly
        IF answered_count >= quiz_count THEN
            SET p_quiz_completed = TRUE;
        ELSE
            SET p_quiz_completed = FALSE;
        END IF;
    END IF;
END //

DELIMITER ;

-- Create a view to show module completion status with quiz requirements
CREATE OR REPLACE VIEW v_user_module_completion AS
SELECT 
    up.user_id,
    up.item_id as module_id,
    em.title as module_title,
    up.completed_at,
    CASE 
        WHEN qq_count.total_quizzes = 0 THEN 1  -- No quizzes, considered completed
        ELSE uc.check_result
    END as is_quiz_completed
FROM user_progress up
JOIN education_modules em ON up.item_id = em.module_id
LEFT JOIN (
    SELECT module_id, COUNT(*) as total_quizzes
    FROM quiz_questions
    WHERE is_active = 1
    GROUP BY module_id
) qq_count ON em.module_id = qq_count.module_id
LEFT JOIN (
    SELECT 
        uqa.user_id,
        qq.module_id,
        CASE 
            WHEN COUNT(uqa.answer_id) >= qq_count.total_quizzes THEN 1
            ELSE 0
        END as check_result
    FROM user_quiz_answers uqa
    JOIN quiz_questions qq ON uqa.question_id = qq.question_id
    JOIN (
        SELECT module_id, COUNT(*) as total_quizzes
        FROM quiz_questions
        WHERE is_active = 1
        GROUP BY module_id
    ) qq_count ON qq.module_id = qq_count.module_id
    WHERE uqa.is_correct = 1
    GROUP BY uqa.user_id, qq.module_id
) uc ON up.user_id = uc.user_id AND up.item_id = uc.module_id
WHERE up.item_type = 'module';

SELECT 'Quiz-Education Module Integration applied successfully!' as message;
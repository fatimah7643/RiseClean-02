<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['error_message'] = 'Please log in to complete modules.';
    redirect(BASE_URL . 'index.php?page=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $moduleId = (int)$_POST['module_id'];
    $redirectUrl = $_POST['redirect_url'] ?? BASE_URL . 'index.php?page=education/list';

    try {
        // Verify module exists and is active
        $moduleStmt = $pdo->prepare("SELECT * FROM education_modules WHERE module_id = ? AND is_active = 1");
        $moduleStmt->execute([$moduleId]);
        $module = $moduleStmt->fetch();

        if (!$module) {
            throw new Exception('Module not found or inactive.');
        }

        // Check if user has already completed this module
        $checkStmt = $pdo->prepare("
            SELECT * FROM user_progress
            WHERE user_id = ? AND item_id = ? AND item_type = 'module'
        ");
        $checkStmt->execute([$_SESSION['user_id'], $moduleId]);
        $existingProgress = $checkStmt->fetch();

        if ($existingProgress) {
            $_SESSION['error_message'] = 'You have already completed this module.';
            redirect($redirectUrl);
            exit;
        }

        // Check if the module has associated quiz questions
        $quizCheckStmt = $pdo->prepare("
            SELECT COUNT(*) as question_count
            FROM quiz_questions
            WHERE module_id = ? AND is_active = 1
        ");
        $quizCheckStmt->execute([$moduleId]);
        $quizCheckResult = $quizCheckStmt->fetch();
        $hasQuiz = $quizCheckResult['question_count'] > 0;

        // If the module has a quiz, check if the user has passed it
        if ($hasQuiz) {
            // Get total questions for this module
            $totalQuestionsStmt = $pdo->prepare("
                SELECT COUNT(*) as total_questions
                FROM quiz_questions
                WHERE module_id = ? AND is_active = 1
            ");
            $totalQuestionsStmt->execute([$moduleId]);
            $totalQuestions = $totalQuestionsStmt->fetch()['total_questions'];

            if ($totalQuestions > 0) {
                // Get user's correct answers for this module's quiz
                $userAnswersStmt = $pdo->prepare("
                    SELECT COUNT(*) as correct_answers
                    FROM user_quiz_answers uqa
                    JOIN quiz_questions qq ON uqa.question_id = qq.question_id
                    WHERE uqa.user_id = ? AND qq.module_id = ? AND uqa.is_correct = 1
                ");
                $userAnswersStmt->execute([$_SESSION['user_id'], $moduleId]);
                $correctAnswers = $userAnswersStmt->fetch()['correct_answers'];

                // Check if user has passed the quiz (70% correct)
                $passingScore = $totalQuestions * 0.7;
                if ($correctAnswers < $passingScore) {
                    $_SESSION['error_message'] = 'You must pass the quiz before completing this module. Please take the quiz and achieve at least 70% to continue.';
                    redirect($redirectUrl);
                    exit;
                }
            }
        }

        // Start transaction
        $pdo->beginTransaction();

        // Insert user progress
        $progressStmt = $pdo->prepare("
            INSERT INTO user_progress (user_id, item_id, item_type, completed_at)
            VALUES (?, ?, 'module', NOW())
        ");
        $progressStmt->execute([$_SESSION['user_id'], $moduleId]);

        // Update user XP and points
        $updateUserStmt = $pdo->prepare("
            UPDATE users
            SET total_xp = total_xp + ?, total_points = total_points + ?
            WHERE id = ?
        ");
        $updateUserStmt->execute([$module['xp_reward'], $module['point_reward'], $_SESSION['user_id']]);

        // Commit transaction
        $pdo->commit();

        $_SESSION['success_message'] = 'Module completed successfully! You earned ' . $module['xp_reward'] . ' XP and ' . $module['point_reward'] . ' points.';
        redirect($redirectUrl);

    } catch (Exception $e) {
        $pdo->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        redirect($redirectUrl);
    }
} else {
    redirect(BASE_URL . 'index.php?page=education/list');
}
?>
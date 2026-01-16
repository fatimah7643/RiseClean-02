<?php
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Get module ID from GET parameter
$moduleId = (int)$_GET['id'];

// Fetch education module
$stmt = $pdo->prepare("
    SELECT * FROM education_modules 
    WHERE module_id = ? AND is_active = 1
");
$stmt->execute([$moduleId]);
$module = $stmt->fetch();

if (!$module) {
    $_SESSION['error_message'] = "Education module not found.";
    redirect(BASE_URL . 'index.php?page=education/list');
    exit;
}

// Check if user has completed this module (to determine if they can take the quiz)
$userCompleted = false;
if (isset($_SESSION['user_id'])) {
    $progressStmt = $pdo->prepare("
        SELECT * FROM user_progress
        WHERE user_id = ? AND item_id = ? AND item_type = 'module'
    ");
    $progressStmt->execute([$_SESSION['user_id'], $moduleId]);
    $userProgress = $progressStmt->fetch();
    $userCompleted = $userProgress ? true : false;
}

// Check if user has passed the quiz for this module
$quizPassed = false;
if (isset($_SESSION['user_id']) && $hasQuiz) {
    // Check if user has answered all questions for this module and achieved passing score
    $quizQuestionsStmt = $pdo->prepare("
        SELECT COUNT(*) as total_questions
        FROM quiz_questions
        WHERE module_id = ? AND is_active = 1
    ");
    $quizQuestionsStmt->execute([$moduleId]);
    $totalQuestions = $quizQuestionsStmt->fetch()['total_questions'];

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

        // For now, we'll consider the quiz passed if the user has answered all questions
        // In a real implementation, you'd check if the score meets the passing criteria (e.g., 70%)
        $quizPassed = ($correctAnswers >= $totalQuestions * 0.7); // 70% required to pass
    }
}

// Check if the module has associated quiz questions
$hasQuiz = false;
$quizCheckStmt = $pdo->prepare("
    SELECT COUNT(*) as question_count 
    FROM quiz_questions 
    WHERE module_id = ? AND is_active = 1
");
$quizCheckStmt->execute([$moduleId]);
$quizCheckResult = $quizCheckStmt->fetch();
$hasQuiz = $quizCheckResult['question_count'] > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($module['title']); ?> - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .module-content {
            line-height: 1.8;
        }
        .module-content p {
            margin-bottom: 1rem;
        }
        .module-content h2 {
            font-size: 1.5rem;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            color: #1f2937;
        }
        .module-content h3 {
            font-size: 1.25rem;
            margin-top: 1.25rem;
            margin-bottom: 0.75rem;
            color: #374151;
        }
        .module-content ul, .module-content ol {
            margin-left: 1.5rem;
            margin-bottom: 1rem;
        }
        .module-content li {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($module['title']); ?></h1>
                    <a href="<?php echo BASE_URL; ?>index.php?page=education/list" class="text-blue-600 hover:text-blue-800 font-medium">← Back to List</a>
                </div>

                <!-- Module Info -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="text-center">
                        <div class="text-sm text-gray-600">Difficulty</div>
                        <div class="font-semibold capitalize"><?php echo htmlspecialchars($module['difficulty']); ?></div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm text-gray-600">Category</div>
                        <div class="font-semibold"><?php echo htmlspecialchars($module['category'] ?: 'N/A'); ?></div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm text-gray-600">Duration</div>
                        <div class="font-semibold"><?php echo $module['duration_minutes']; ?> min</div>
                    </div>
                    <div class="text-center">
                        <div class="text-sm text-gray-600">Rewards</div>
                        <div class="font-semibold"><?php echo $module['xp_reward']; ?> XP, <?php echo $module['point_reward']; ?> Points</div>
                    </div>
                </div>

                <!-- Module Content -->
                <div class="module-content mb-8">
                    <?php echo nl2br(htmlspecialchars($module['content'])); ?>
                </div>

                <!-- Quiz Section -->
                <?php if ($hasQuiz): ?>
                    <div class="border-t pt-6">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h2 class="text-xl font-semibold text-blue-800 mb-3">Knowledge Check</h2>
                            <p class="text-blue-700 mb-4">
                                Complete the quiz to earn your rewards and demonstrate your understanding of this module.
                            </p>

                            <?php if (isLoggedIn()): ?>
                                <?php if ($userCompleted): ?>
                                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                                        You have already completed this module.
                                    </div>
                                    <a href="<?php echo BASE_URL; ?>index.php?page=games&action=module_quiz&id=<?php echo $moduleId; ?>"
                                       class="inline-block bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 font-medium">
                                        Review Quiz
                                    </a>
                                <?php elseif ($quizPassed): ?>
                                    <!-- Quiz passed, allow module completion -->
                                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                                        Congratulations! You passed the quiz. You can now complete the module to earn your rewards.
                                    </div>
                                    <form method="POST" action="<?php echo BASE_URL; ?>includes/process_module_completion.php" class="inline">
                                        <input type="hidden" name="module_id" value="<?php echo $moduleId; ?>">
                                        <input type="hidden" name="redirect_url" value="<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">
                                        <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 font-medium">
                                            Complete Module & Claim Rewards
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <a href="<?php echo BASE_URL; ?>index.php?page=games&action=module_quiz&id=<?php echo $moduleId; ?>"
                                       class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 font-medium">
                                        Take Quiz Now
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-gray-700 mb-4">
                                    Please <a href="<?php echo BASE_URL; ?>index.php?page=login" class="text-blue-600 hover:underline">log in</a> to take the quiz.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="border-t pt-6">
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <h2 class="text-xl font-semibold text-gray-800 mb-3">Complete Module</h2>
                            <p class="text-gray-700 mb-4">
                                This module does not have a quiz. You can mark it as completed to earn your rewards.
                            </p>

                            <?php if (isLoggedIn() && !$userCompleted): ?>
                                <form method="POST" action="<?php echo BASE_URL; ?>includes/process_module_completion.php" class="inline">
                                    <input type="hidden" name="module_id" value="<?php echo $moduleId; ?>">
                                    <input type="hidden" name="redirect_url" value="<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">
                                    <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 font-medium">
                                        Mark as Completed
                                    </button>
                                </form>
                            <?php elseif (isLoggedIn() && $userCompleted): ?>
                                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                                    You have already completed this module.
                                </div>
                            <?php else: ?>
                                <p class="text-gray-700">
                                    Please <a href="<?php echo BASE_URL; ?>index.php?page=login" class="text-blue-600 hover:underline">log in</a> to complete this module.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
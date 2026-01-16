<?php
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth_check.php';

// Only allow admin access
if (!isAdmin()) {
    redirect(BASE_URL . 'index.php?page=dashboard');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();

        // Sanitize and validate inputs
        $title = cleanInput($_POST['title']);
        $content = cleanInput($_POST['content']);
        $xp_reward = (int)$_POST['xp_reward'];
        $point_reward = (int)$_POST['point_reward'];
        $difficulty = cleanInput($_POST['difficulty']);
        $category = cleanInput($_POST['category']);
        $duration_minutes = (int)$_POST['duration_minutes'];

        // Validate required fields
        if (empty($title) || empty($content)) {
            throw new Exception("Title and content are required.");
        }

        // Insert education module
        $stmt = $pdo->prepare("
            INSERT INTO education_modules (title, content, xp_reward, point_reward, difficulty, category, duration_minutes, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$title, $content, $xp_reward, $point_reward, $difficulty, $category, $duration_minutes]);
        
        $moduleId = $pdo->lastInsertId();

        // Process quiz questions if any
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $questionData) {
                $questionText = cleanInput($questionData['question_text']);
                
                if (!empty($questionText)) {
                    // Insert question
                    $questionStmt = $pdo->prepare("
                        INSERT INTO quiz_questions (module_id, question_text, question_type, xp_reward, point_reward, difficulty, is_active)
                        VALUES (?, ?, 'multiple_choice', ?, ?, ?, 1)
                    ");
                    $questionStmt->execute([
                        $moduleId,
                        $questionText,
                        $xp_reward, // Using module's reward values for individual questions
                        $point_reward,
                        $difficulty
                    ]);
                    
                    $questionId = $pdo->lastInsertId();

                    // Insert choices
                    if (isset($questionData['choices']) && is_array($questionData['choices'])) {
                        foreach ($questionData['choices'] as $choiceIndex => $choiceText) {
                            $choiceText = cleanInput($choiceText);
                            $isCorrect = isset($questionData['correct_choice']) && $questionData['correct_choice'] == $choiceIndex ? 1 : 0;
                            
                            if (!empty($choiceText)) {
                                $choiceStmt = $pdo->prepare("
                                    INSERT INTO quiz_choices (question_id, choice_text, is_correct)
                                    VALUES (?, ?, ?)
                                ");
                                $choiceStmt->execute([$questionId, $choiceText, $isCorrect]);
                            }
                        }
                    }
                }
            }
        }

        // Commit transaction
        $pdo->commit();

        $_SESSION['success_message'] = "Education module created successfully!";
        redirect(BASE_URL . 'index.php?page=education/list');
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollback();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Education Module - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .question-container {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background-color: #f9fafb;
        }
        .choice-container {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .choice-input {
            flex: 1;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h1 class="text-2xl font-bold text-gray-800">Create Education Module</h1>
                    <a href="<?php echo BASE_URL; ?>index.php?page=education/list" class="text-blue-600 hover:text-blue-800 font-medium">← Back to List</a>
                </div>

                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="moduleForm" class="space-y-6">
                    <!-- Module Information -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Module Title *</label>
                            <input type="text" name="title" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter module title">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <input type="text" name="category"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter category">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Difficulty</label>
                            <select name="difficulty" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="easy">Easy</option>
                                <option value="medium" selected>Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Duration (minutes)</label>
                            <input type="number" name="duration_minutes" value="10"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">XP Reward</label>
                            <input type="number" name="xp_reward" value="10"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Point Reward</label>
                            <input type="number" name="point_reward" value="5"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Content *</label>
                        <textarea name="content" rows="8" required
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Enter module content here..."></textarea>
                    </div>

                    <!-- Quiz Questions Section -->
                    <div class="border-t pt-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-gray-800">Quiz Questions</h2>
                            <button type="button" id="addQuestionBtn" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                                + Add Question
                            </button>
                        </div>
                        
                        <div id="questionsContainer">
                            <!-- Questions will be added here dynamically -->
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 pt-6">
                        <a href="<?php echo BASE_URL; ?>index.php?page=education/list" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Create Module
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let questionCounter = 0;

        document.getElementById('addQuestionBtn').addEventListener('click', function() {
            const questionId = 'question_' + Date.now();
            questionCounter++;
            
            const questionHtml = `
                <div class="question-container" data-question-id="${questionId}">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-medium text-gray-800">Question ${questionCounter}</h3>
                        <button type="button" class="remove-question-btn text-red-600 hover:text-red-800">
                            Remove Question
                        </button>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Question Text</label>
                        <textarea name="questions[${questionId}][question_text]" 
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Enter your question here..." rows="2"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Answer Choices</label>
                        <div class="choices-container">
                            ${generateChoiceInputs(questionId)}
                        </div>
                        <button type="button" class="add-choice-btn mt-2 text-sm text-blue-600 hover:text-blue-800">
                            + Add Choice
                        </button>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Correct Answer</label>
                        <select name="questions[${questionId}][correct_choice]" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select correct answer</option>
                        </select>
                    </div>
                </div>
            `;
            
            document.getElementById('questionsContainer').insertAdjacentHTML('beforeend', questionHtml);
            updateChoiceOptions(questionId);
        });

        // Generate choice inputs
        function generateChoiceInputs(questionId) {
            let choicesHtml = '';
            for (let i = 0; i < 4; i++) {
                choicesHtml += `
                    <div class="choice-container">
                        <input type="text" name="questions[${questionId}][choices][${i}]" 
                               class="choice-input px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Choice ${String.fromCharCode(65 + i)}">
                        <button type="button" class="remove-choice-btn text-red-600 hover:text-red-800 ml-2">
                            ×
                        </button>
                    </div>
                `;
            }
            return choicesHtml;
        }

        // Update choice options in the correct answer dropdown
        function updateChoiceOptions(questionId) {
            const questionElement = document.querySelector(`[data-question-id="${questionId}"]`);
            const choicesContainer = questionElement.querySelector('.choices-container');
            const choiceInputs = choicesContainer.querySelectorAll('input[type="text"]');
            const correctAnswerSelect = questionElement.querySelector(`select[name="questions[${questionId}][correct_choice]"]`);
            
            // Clear existing options except the first one
            correctAnswerSelect.innerHTML = '<option value="">Select correct answer</option>';
            
            choiceInputs.forEach((input, index) => {
                const option = document.createElement('option');
                option.value = index;
                option.textContent = `Choice ${String.fromCharCode(65 + index)}`;
                correctAnswerSelect.appendChild(option);
            });
        }

        // Event delegation for dynamically added elements
        document.addEventListener('click', function(e) {
            // Remove question button
            if (e.target.classList.contains('remove-question-btn')) {
                e.target.closest('.question-container').remove();
                updateQuestionNumbers();
            }
            
            // Add choice button
            if (e.target.classList.contains('add-choice-btn')) {
                const questionContainer = e.target.closest('.question-container');
                const questionId = questionContainer.getAttribute('data-question-id');
                const choicesContainer = questionContainer.querySelector('.choices-container');
                
                const choiceCount = choicesContainer.querySelectorAll('.choice-container').length;
                const newChoiceHtml = `
                    <div class="choice-container">
                        <input type="text" name="questions[${questionId}][choices][${choiceCount}]" 
                               class="choice-input px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Choice ${String.fromCharCode(65 + choiceCount)}">
                        <button type="button" class="remove-choice-btn text-red-600 hover:text-red-800 ml-2">
                            ×
                        </button>
                    </div>
                `;
                
                choicesContainer.insertAdjacentHTML('beforeend', newChoiceHtml);
                updateChoiceOptions(questionId);
            }
            
            // Remove choice button
            if (e.target.classList.contains('remove-choice-btn') && !e.target.classList.contains('remove-question-btn')) {
                const questionContainer = e.target.closest('.question-container');
                const questionId = questionContainer.getAttribute('data-question-id');
                
                e.target.closest('.choice-container').remove();
                updateChoiceOptions(questionId);
            }
        });

        // Update question numbers when removing questions
        function updateQuestionNumbers() {
            const questions = document.querySelectorAll('.question-container');
            questions.forEach((question, index) => {
                const header = question.querySelector('h3');
                header.textContent = `Question ${index + 1}`;
            });
            questionCounter = questions.length;
        }

        // Update choice options when typing in choice inputs
        document.addEventListener('input', function(e) {
            if (e.target.matches('input[type="text"]') && e.target.name.includes('[choices][')) {
                const questionContainer = e.target.closest('.question-container');
                const questionId = questionContainer.getAttribute('data-question-id');
                updateChoiceOptions(questionId);
            }
        });
    </script>
</body>
</html>
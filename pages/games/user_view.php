<?php
// For regular users - take quizzes
$action = isset($_GET['action']) ? cleanInput($_GET['action']) : 'list';
$categoryId = isset($_GET['category']) ? cleanInput($_GET['category']) : '';

if ($action === 'take' && isset($_GET['id'])) {
    $quizId = (int)$_GET['id'];
    
    // Get the question
    $stmt = $pdo->prepare("
        SELECT qq.*, u.username as created_by_name
        FROM quiz_questions qq
        LEFT JOIN users u ON qq.created_by = u.id
        WHERE qq.question_id = ? AND qq.is_active = 1
    ");
    $stmt->execute([$quizId]);
    $question = $stmt->fetch();
    
    if (!$question) {
        setAlert('error', 'Pertanyaan tidak ditemukan atau tidak aktif!');
        redirect('index.php?page=games');
    }
    
    // Get choices if it's a multiple choice question
    $choices = [];
    if ($question['question_type'] === 'multiple_choice') {
        $choices_stmt = $pdo->prepare("SELECT * FROM quiz_choices WHERE question_id = ? ORDER BY choice_order");
        $choices_stmt->execute([$quizId]);
        $choices = $choices_stmt->fetchAll();
    }
    
    // Handle answer submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selected_choice_id = isset($_POST['choice_id']) ? (int)$_POST['choice_id'] : 0;
        $answer_text = isset($_POST['answer_text']) ? cleanInput($_POST['answer_text']) : '';
        
        $is_correct = false;
        $points_earned = 0;
        
        if ($question['question_type'] === 'multiple_choice') {
            // Check if the selected choice is correct
            if ($selected_choice_id > 0) {
                $choice_stmt = $pdo->prepare("SELECT is_correct FROM quiz_choices WHERE choice_id = ?");
                $choice_stmt->execute([$selected_choice_id]);
                $choice = $choice_stmt->fetch();
                
                if ($choice && $choice['is_correct'] == 1) {
                    $is_correct = true;
                    $points_earned = $question['point_reward'];
                }
            }
        } elseif ($question['question_type'] === 'true_false') {
            // For true/false, we'll need to implement logic based on expected answer
            // For now, we'll skip this and just show a message
            $is_correct = false; // Placeholder - would need actual validation
        } elseif ($question['question_type'] === 'short_answer') {
            // For short answer, we'll need to implement text comparison logic
            // For now, we'll skip this and just show a message
            $is_correct = false; // Placeholder - would need actual validation
        }
        
        // Record the answer
        $answer_stmt = $pdo->prepare("
            INSERT INTO user_quiz_answers (user_id, question_id, selected_choice_id, answer_text, is_correct, points_earned)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $answer_stmt->execute([$_SESSION['user_id'], $quizId, $selected_choice_id, $answer_text, $is_correct ? 1 : 0, $points_earned]);
        
        // Update user's XP and points if answer is correct
        if ($is_correct) {
            // The trigger in the database will handle updating user XP and points
            setAlert('success', "Jawaban benar! Anda mendapatkan {$question['xp_reward']} XP dan {$question['point_reward']} poin.");
        } else {
            setAlert('warning', 'Jawaban salah. Silakan coba lagi!');
        }
        
        redirect('index.php?page=games');
    }
} elseif ($action === 'random') {
    // Get a random active question
    $stmt = $pdo->query("
        SELECT qq.*, u.username as created_by_name
        FROM quiz_questions qq
        LEFT JOIN users u ON qq.created_by = u.id
        WHERE qq.is_active = 1
        ORDER BY RAND()
        LIMIT 1
    ");
    $question = $stmt->fetch();
    
    if (!$question) {
        setAlert('error', 'Tidak ada pertanyaan kuis yang tersedia!');
        redirect('index.php?page=games');
    }
    
    // Get choices if it's a multiple choice question
    $choices = [];
    if ($question['question_type'] === 'multiple_choice') {
        $choices_stmt = $pdo->prepare("SELECT * FROM quiz_choices WHERE question_id = ? ORDER BY choice_order");
        $choices_stmt->execute([$question['question_id']]);
        $choices = $choices_stmt->fetchAll();
    }
    
    // Handle answer submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $selected_choice_id = isset($_POST['choice_id']) ? (int)$_POST['choice_id'] : 0;
        $answer_text = isset($_POST['answer_text']) ? cleanInput($_POST['answer_text']) : '';
        
        $is_correct = false;
        $points_earned = 0;
        
        if ($question['question_type'] === 'multiple_choice') {
            // Check if the selected choice is correct
            if ($selected_choice_id > 0) {
                $choice_stmt = $pdo->prepare("SELECT is_correct FROM quiz_choices WHERE choice_id = ?");
                $choice_stmt->execute([$selected_choice_id]);
                $choice = $choice_stmt->fetch();
                
                if ($choice && $choice['is_correct'] == 1) {
                    $is_correct = true;
                    $points_earned = $question['point_reward'];
                }
            }
        } elseif ($question['question_type'] === 'true_false') {
            // For true/false, we'll need to implement logic based on expected answer
            $is_correct = false; // Placeholder - would need actual validation
        } elseif ($question['question_type'] === 'short_answer') {
            // For short answer, we'll need to implement text comparison logic
            $is_correct = false; // Placeholder - would need actual validation
        }
        
        // Record the answer
        $answer_stmt = $pdo->prepare("
            INSERT INTO user_quiz_answers (user_id, question_id, selected_choice_id, answer_text, is_correct, points_earned)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $answer_stmt->execute([$_SESSION['user_id'], $question['question_id'], $selected_choice_id, $answer_text, $is_correct ? 1 : 0, $points_earned]);
        
        // Update user's XP and points if answer is correct
        if ($is_correct) {
            // The trigger in the database will handle updating user XP and points
            setAlert('success', "Jawaban benar! Anda mendapatkan {$question['xp_reward']} XP dan {$question['point_reward']} poin.");
        } else {
            setAlert('warning', 'Jawaban salah. Silakan coba lagi!');
        }
        
        redirect('index.php?page=games');
    }
} else {
    // List all available quiz questions
    $whereClause = "WHERE qq.is_active = 1";
    $params = [];
    
    if ($categoryId) {
        $whereClause .= " AND qq.category = ?";
        $params[] = $categoryId;
    }
    
    // Get all available questions
    $sql = "SELECT qq.*, u.username as created_by_name
            FROM quiz_questions qq
            LEFT JOIN users u ON qq.created_by = u.id
            $whereClause
            ORDER BY qq.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $questions = $stmt->fetchAll();
    
    // Get all categories for filter
    $categories = $pdo->query("SELECT DISTINCT category FROM quiz_questions WHERE category IS NOT NULL AND is_active = 1 ORDER BY category")->fetchAll();
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Kuis Edukasi</h1>
    <p class="text-gray-500 mt-1">Uji pengetahuanmu tentang pengelolaan sampah dan lingkungan</p>
</div>

<?php if ($action === 'take' || $action === 'random'): ?>
<!-- Quiz Question View -->
<div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
    <div class="flex justify-between items-start mb-4">
        <div>
            <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?php echo ucfirst($question['difficulty']); ?></span>
            <h1 class="text-2xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($question['question_text']); ?></h1>
            <div class="flex items-center text-sm text-gray-500 mt-2">
                <span>Kategori: <?php echo htmlspecialchars($question['category']); ?></span>
            </div>
        </div>
        <div class="text-right">
            <div class="text-lg font-bold text-green-600">+<?php echo $question['xp_reward']; ?> XP</div>
            <div class="text-sm text-gray-500">+<?php echo $question['point_reward']; ?> Poin</div>
        </div>
    </div>

    <form method="POST" class="mt-6 space-y-4">
        <?php if ($question['question_type'] === 'multiple_choice'): ?>
            <?php foreach ($choices as $choice): ?>
                <div class="flex items-start">
                    <input type="radio" name="choice_id" value="<?php echo $choice['choice_id']; ?>" id="choice_<?php echo $choice['choice_id']; ?>" required
                           class="mt-1 h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                    <label for="choice_<?php echo $choice['choice_id']; ?>" class="ml-3 block text-gray-700">
                        <?php echo htmlspecialchars($choice['choice_text']); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        <?php elseif ($question['question_type'] === 'true_false'): ?>
            <div class="flex items-start">
                <input type="radio" name="choice_id" value="1" id="true_choice" required
                       class="mt-1 h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                <label for="true_choice" class="ml-3 block text-gray-700">Benar</label>
            </div>
            <div class="flex items-start">
                <input type="radio" name="choice_id" value="0" id="false_choice" required
                       class="mt-1 h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                <label for="false_choice" class="ml-3 block text-gray-700">Salah</label>
            </div>
        <?php elseif ($question['question_type'] === 'short_answer'): ?>
            <div>
                <label for="answer_text" class="block text-sm font-medium text-gray-700 mb-2">Jawaban Anda</label>
                <textarea name="answer_text" id="answer_text" rows="3" required
                          class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"></textarea>
            </div>
        <?php endif; ?>

        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
            <div class="flex items-center space-x-4">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">
                    Jawab
                </button>
                <a href="index.php?page=games" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-xl transition-colors">
                    Kembali
                </a>
            </div>
        </div>
    </form>
</div>

<?php else: ?>
<!-- List All Quiz Questions -->
<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Daftar Kuis</h2>
        <p class="text-gray-500">Uji pengetahuanmu dan dapatkan XP serta poin</p>
    </div>
    <a href="index.php?page=games&action=random" class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
        </svg>
        Kuis Acak
    </a>
</div>

<!-- Category Filter -->
<div class="bg-white rounded-2xl shadow-sm p-4 mb-6">
    <div class="flex flex-wrap gap-2">
        <a href="index.php?page=games" class="px-3 py-1 rounded-full text-sm font-medium <?php echo !$categoryId ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800 hover:bg-gray-200'; ?>">
            Semua Kategori
        </a>
        <?php foreach ($categories as $category): ?>
            <a href="index.php?page=games&category=<?php echo urlencode($category['category']); ?>" 
               class="px-3 py-1 rounded-full text-sm font-medium <?php echo $categoryId === $category['category'] ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800 hover:bg-gray-200'; ?>">
                <?php echo htmlspecialchars($category['category']); ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($questions as $question): ?>
    <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-200">
        <div class="flex justify-between items-start">
            <div>
                <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?php echo ucfirst($question['difficulty']); ?></span>
                <h3 class="font-bold text-gray-800 mt-2"><?php echo htmlspecialchars(substr($question['question_text'], 0, 60)) . (strlen($question['question_text']) > 60 ? '...' : ''); ?></h3>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold text-green-600">+<?php echo $question['xp_reward']; ?> XP</div>
                <div class="text-sm text-gray-500">+<?php echo $question['point_reward']; ?> Pts</div>
            </div>
        </div>

        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
            <div class="text-sm text-gray-500">
                <span><?php echo htmlspecialchars($question['category']); ?></span>
            </div>

            <div class="flex items-center space-x-2">
                <a href="index.php?page=games&action=take&id=<?php echo $question['question_id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                    Jawab →
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (count($questions) == 0): ?>
    <div class="col-span-full text-center py-12">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <h3 class="text-lg font-medium text-gray-500">Belum ada kuis tersedia</h3>
        <p class="text-gray-400 mt-1">Kuis akan ditambahkan segera. Silakan kembali lagi nanti.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Stats Section -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-500">Total Kuis</p>
                <p class="font-semibold"><?php echo count($questions); ?> Kuis</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-6">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center mr-4">
                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-500">Kuis Terjawab</p>
                <?php
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as answered_count
                    FROM user_quiz_answers
                    WHERE user_id = ?
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $answered_count = $stmt->fetch()['answered_count'];
                ?>
                <p class="font-semibold"><?php echo $answered_count; ?> Jawaban</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm p-6">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center mr-4">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-500">Kuis Benar</p>
                <?php
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as correct_count
                    FROM user_quiz_answers
                    WHERE user_id = ? AND is_correct = 1
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $correct_count = $stmt->fetch()['correct_count'];
                ?>
                <p class="font-semibold"><?php echo $correct_count; ?> Benar</p>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
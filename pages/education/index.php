<?php
// Check if user is accessing with admin privileges for managing modules
$action = isset($_GET['action']) ? $_GET['action'] : 'view';
$moduleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (hasRole(['admin', 'manager'])) {
    // Admin/Manager functionality
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'create' || $action === 'edit') {
            $title = cleanInput($_POST['title']);
            $content = $_POST['content']; // Using raw content for now (could be HTML)
            $xp_reward = (int)$_POST['xp_reward'];
            $point_reward = (int)$_POST['point_reward'];
            $difficulty = cleanInput($_POST['difficulty']);
            $category = cleanInput($_POST['category']);
            $duration_minutes = (int)$_POST['duration_minutes'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($action === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO education_modules (title, content, xp_reward, point_reward, difficulty, category, duration_minutes, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $content, $xp_reward, $point_reward, $difficulty, $category, $duration_minutes, $is_active]);
                setAlert('success', 'Modul edukasi berhasil ditambahkan');
            } elseif ($action === 'edit' && $moduleId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE education_modules
                    SET title = ?, content = ?, xp_reward = ?, point_reward = ?, difficulty = ?, category = ?, duration_minutes = ?, is_active = ?, updated_at = NOW()
                    WHERE module_id = ?
                ");
                $stmt->execute([$title, $content, $xp_reward, $point_reward, $difficulty, $category, $duration_minutes, $is_active, $moduleId]);
                setAlert('success', 'Modul edukasi berhasil diperbarui');
            }

            redirect('index.php?page=education');
        } elseif ($action === 'delete' && $moduleId > 0) {
            $stmt = $pdo->prepare("DELETE FROM education_modules WHERE module_id = ?");
            $stmt->execute([$moduleId]);
            setAlert('success', 'Modul edukasi berhasil dihapus');
            redirect('index.php?page=education');
        }
    }

    if ($action === 'edit' && $moduleId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM education_modules WHERE module_id = ?");
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch();
    }
}

// For regular users - view modules and mark as complete
if ($action === 'complete' && $moduleId > 0 && !hasRole(['admin', 'manager'])) {
    // Try to award points for completing the module
    if (awardUserPoints($_SESSION['user_id'], 'module', $moduleId)) {
        setAlert('success', 'Modul berhasil diselesaikan! Kamu mendapatkan XP dan poin.');
    } else {
        setAlert('warning', 'Kamu sudah menyelesaikan modul ini sebelumnya.');
    }

    redirect('index.php?page=education');
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Modul Edukasi</h1>
    <p class="text-gray-500 mt-1">Pelajari tentang pengelolaan sampah dan kebersihan lingkungan</p>
</div>

<?php if (hasRole(['admin', 'manager']) && ($action === 'create' || $action === 'edit')): ?>
<!-- Create/Edit Form for Admin/Manager -->
<div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo $action === 'create' ? 'Tambah Modul Baru' : 'Edit Modul'; ?></h3>

    <form method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Judul Modul</label>
                <input type="text" name="title" required
                       value="<?php echo isset($module) ? htmlspecialchars($module['title']) : (isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''); ?>"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                <input type="text" name="category"
                       value="<?php echo isset($module) ? htmlspecialchars($module['category']) : (isset($_POST['category']) ? htmlspecialchars($_POST['category']) : 'General'); ?>"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Hadiah XP</label>
                <input type="number" name="xp_reward" min="0" required
                       value="<?php echo isset($module) ? $module['xp_reward'] : (isset($_POST['xp_reward']) ? $_POST['xp_reward'] : 10); ?>"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Hadiah Poin</label>
                <input type="number" name="point_reward" min="0" required
                       value="<?php echo isset($module) ? $module['point_reward'] : (isset($_POST['point_reward']) ? $_POST['point_reward'] : 5); ?>"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tingkat Kesulitan</label>
                <select name="difficulty" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                    <option value="easy" <?php echo (isset($module) && $module['difficulty'] === 'easy') || (isset($_POST['difficulty']) && $_POST['difficulty'] === 'easy') ? 'selected' : ''; ?>>Mudah</option>
                    <option value="medium" <?php echo (isset($module) && $module['difficulty'] === 'medium') || (isset($_POST['difficulty']) && $_POST['difficulty'] === 'medium') ? 'selected' : ''; ?>>Sedang</option>
                    <option value="hard" <?php echo (isset($module) && $module['difficulty'] === 'hard') || (isset($_POST['difficulty']) && $_POST['difficulty'] === 'hard') ? 'selected' : ''; ?>>Sulit</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Durasi (menit)</label>
                <input type="number" name="duration_minutes" min="1" required
                       value="<?php echo isset($module) ? $module['duration_minutes'] : (isset($_POST['duration_minutes']) ? $_POST['duration_minutes'] : 10); ?>"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Konten Modul</label>
                <textarea name="content" rows="8"
                          class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"><?php echo isset($module) ? htmlspecialchars($module['content']) : (isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''); ?></textarea>
            </div>

            <div class="md:col-span-2 flex items-center">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1"
                           <?php echo (isset($module) && $module['is_active']) || (isset($_POST['is_active']) && $_POST['is_active']) ? 'checked' : ''; ?>
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700">Aktifkan Modul</span>
                </label>
            </div>
        </div>
        <textarea name="content" class="...">...</textarea>

<div id="quiz-container" class="mt-8 border-t pt-4">
    <h3 class="text-lg font-bold">Kuis Terkait (Pilihan Ganda)</h3>
    
    <div class="question-item bg-gray-50 p-4 rounded-xl mb-4">
        <input type="text" name="questions[0][text]" placeholder="Pertanyaan 1" class="w-full mb-2">
        <div class="grid grid-cols-2 gap-2">
            <input type="text" name="questions[0][options][A]" placeholder="Pilihan A">
            <input type="text" name="questions[0][options][B]" placeholder="Pilihan B">
            <input type="text" name="questions[0][options][C]" placeholder="Pilihan C">
            <input type="text" name="questions[0][options][D]" placeholder="Pilihan D">
        </div>
        <select name="questions[0][correct]" class="mt-2">
            <option value="A">Jawaban Benar: A</option>
            <option value="B">Jawaban Benar: B</option>
            <option value="C">Jawaban Benar: C</option>
            <option value="D">Jawaban Benar: D</option>
        </select>
    </div>
</div>
<button type="button" onclick="addQuestion()" class="text-blue-600">+ Tambah Soal Lagi</button>
        <div class="flex items-center space-x-4 mt-6">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">
                <?php echo $action === 'create' ? 'Tambah Modul' : 'Perbarui Modul'; ?>
            </button>
            <a href="index.php?page=education" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-xl transition-colors">
                Batal
            </a>
        </div>
    </form>
</div>

<?php elseif (hasRole(['admin', 'manager']) && $action === 'manage_quiz' && $moduleId > 0): ?>
<!-- Quiz Management for Education Module -->
<?php
// Get the module details
$stmt = $pdo->prepare("SELECT * FROM education_modules WHERE module_id = ?");
$stmt->execute([$moduleId]);
$module = $stmt->fetch();

if (!$module) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4">Modul tidak ditemukan.</div>';
    echo '<a href="index.php?page=education" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">Kembali ke Daftar Modul</a>';
} else {

// Handle quiz question creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $question_text = cleanInput($_POST['question_text']);
    $question_type = cleanInput($_POST['question_type']);
    $category = cleanInput($_POST['category']);
    $difficulty = cleanInput($_POST['difficulty']);
    $xp_reward = (int)$_POST['xp_reward'];
    $point_reward = (int)$_POST['point_reward'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($question_text) || empty($category)) {
        $error = 'Pertanyaan dan kategori harus diisi!';
    } elseif ($xp_reward < 0 || $point_reward < 0) {
        $error = 'Hadiah XP dan poin harus berupa angka positif!';
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Insert the question with module_id
            $stmt = $pdo->prepare("
                INSERT INTO quiz_questions (question_text, question_type, module_id, category, difficulty, xp_reward, point_reward, created_by, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([$question_text, $question_type, $moduleId, $category, $difficulty, $xp_reward, $point_reward, $_SESSION['user_id'], $is_active]);
            $question_id = $pdo->lastInsertId();

            // Handle choices for multiple choice questions
            if ($question_type === 'multiple_choice') {
                $choices = $_POST['choices'] ?? [];
                $correct_choice = (int)($_POST['correct_choice'] ?? 0);

                for ($i = 0; $i < count($choices); $i++) {
                    if (!empty(trim($choices[$i]))) {
                        $choice_text = cleanInput($choices[$i]);
                        $is_correct = ($i + 1) == $correct_choice ? 1 : 0;

                        $choice_stmt = $pdo->prepare("
                            INSERT INTO quiz_choices (question_id, choice_text, is_correct, choice_order)
                            VALUES (?, ?, ?, ?)
                        ");
                        $choice_stmt->execute([$question_id, $choice_text, $is_correct, $i + 1]);
                    }
                }
            }

            // Commit transaction
            $pdo->commit();

            logActivity($_SESSION['user_id'], 'create_quiz_question', "Created new quiz question for module: {$module['title']}");
            setAlert('success', 'Pertanyaan kuis berhasil ditambahkan ke modul!');
            redirect("index.php?page=education&action=manage_quiz&id={$moduleId}");
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollback();
            $error = 'Gagal menambahkan pertanyaan kuis: ' . $e->getMessage();
        }
    }
}

// Get all quiz questions for this module
$quiz_questions = $pdo->prepare("
    SELECT qq.*, COUNT(qc.choice_id) as choice_count
    FROM quiz_questions qq
    LEFT JOIN quiz_choices qc ON qq.question_id = qc.question_id
    WHERE qq.module_id = ?
    GROUP BY qq.question_id
    ORDER BY qq.created_at DESC
");
$quiz_questions->execute([$moduleId]);
$questions = $quiz_questions->fetchAll();

// Get all categories and difficulties for dropdowns
$categories = $pdo->query("SELECT DISTINCT category FROM quiz_questions WHERE category IS NOT NULL ORDER BY category")->fetchAll();
$difficulties = $pdo->query("SELECT DISTINCT difficulty FROM quiz_questions ORDER BY FIELD(difficulty, 'easy', 'medium', 'hard')")->fetchAll();
?>

<div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-lg font-bold text-gray-800">Manajemen Kuis - <?php echo htmlspecialchars($module['title']); ?></h3>
            <p class="text-gray-500 text-sm">Tambahkan dan kelola pertanyaan kuis untuk modul ini</p>
        </div>
        <a href="index.php?page=education" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-4 rounded-xl transition-colors">
            Kembali
        </a>
    </div>

    <!-- Add Question Form -->
    <div class="border border-gray-200 rounded-xl p-4 mb-6">
        <h4 class="font-bold text-gray-800 mb-4">Tambah Pertanyaan Kuis</h4>

        <?php if (isset($error)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="module_id" value="<?php echo $moduleId; ?>">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pertanyaan *</label>
                    <textarea name="question_text" required rows="2"
                              class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"><?php echo isset($_POST['question_text']) ? htmlspecialchars($_POST['question_text']) : ''; ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Pertanyaan</label>
                    <select name="question_type" id="question_type" required
                            class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                        <option value="multiple_choice" <?php echo (isset($_POST['question_type']) && $_POST['question_type'] === 'multiple_choice') ? 'selected' : ''; ?>>Pilihan Ganda</option>
                        <option value="true_false" <?php echo (isset($_POST['question_type']) && $_POST['question_type'] === 'true_false') ? 'selected' : ''; ?>>Benar/Salah</option>
                        <option value="short_answer" <?php echo (isset($_POST['question_type']) && $_POST['question_type'] === 'short_answer') ? 'selected' : ''; ?>>Jawaban Singkat</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori *</label>
                    <select name="category" required
                            class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                        <option value="">Pilih Kategori</option>
                        <?php foreach ($categories as $category_item): ?>
                            <option value="<?php echo $category_item['category']; ?>"
                                    <?php echo (isset($_POST['category']) && $_POST['category'] === $category_item['category']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category_item['category']); ?>
                            </option>
                        <?php endforeach; ?>
                        <!-- Default options if no records exist yet -->
                        <option value="Basics" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Basics') ? 'selected' : ''; ?>>Basics</option>
                        <option value="Recycling" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Recycling') ? 'selected' : ''; ?>>Recycling</option>
                        <option value="Organic Waste" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Organic Waste') ? 'selected' : ''; ?>>Organic Waste</option>
                        <option value="Special Waste" <?php echo (isset($_POST['category']) && $_POST['category'] === 'Special Waste') ? 'selected' : ''; ?>>Special Waste</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tingkat Kesulitan</label>
                    <select name="difficulty" required
                            class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                        <option value="easy" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] === 'easy') ? 'selected' : ''; ?>>Mudah</option>
                        <option value="medium" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] === 'medium') ? 'selected' : ''; ?>>Sedang</option>
                        <option value="hard" <?php echo (isset($_POST['difficulty']) && $_POST['difficulty'] === 'hard') ? 'selected' : ''; ?>>Sulit</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Hadiah XP</label>
                    <input type="number" name="xp_reward" min="0" required
                           value="<?php echo isset($_POST['xp_reward']) ? (int)$_POST['xp_reward'] : 10; ?>"
                           class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Hadiah Poin</label>
                    <input type="number" name="point_reward" min="0" required
                           value="<?php echo isset($_POST['point_reward']) ? (int)$_POST['point_reward'] : 5; ?>"
                           class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                </div>

                <div class="md:col-span-2 flex items-center">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1"
                               <?php echo (!isset($_POST['is_active']) || $_POST['is_active']) ? 'checked' : ''; ?>
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Aktifkan Pertanyaan</span>
                    </label>
                </div>
            </div>

            <!-- Multiple Choice Options -->
            <div id="multiple-choice-section" class="border-t pt-4 mt-4">
                <h5 class="text-md font-bold text-gray-800 mb-3">Pilihan Jawaban</h5>

                <div id="choices-container">
                    <?php
                    $num_choices = isset($_POST['choices']) ? count($_POST['choices']) : 4;
                    for ($i = 0; $i < $num_choices; $i++):
                    ?>
                    <div class="choice-row flex items-center mb-2">
                        <div class="flex-1 mr-2">
                            <input type="text" name="choices[]"
                                   value="<?php echo isset($_POST['choices'][$i]) ? htmlspecialchars($_POST['choices'][$i]) : ''; ?>"
                                   placeholder="Pilihan <?php echo $i + 1; ?>"
                                   class="w-full px-3 py-1 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                        </div>
                        <div class="flex items-center">
                            <input type="radio" name="correct_choice" value="<?php echo $i + 1; ?>"
                                   <?php echo (isset($_POST['correct_choice']) && $_POST['correct_choice'] == $i + 1) ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            <label class="ml-1 text-sm text-gray-700">Benar</label>
                        </div>
                        <?php if ($i >= 2): ?>
                        <button type="button" class="ml-2 text-red-600 hover:text-red-800 remove-choice">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>

                <div class="mt-3">
                    <button type="button" id="add-choice" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm py-1 px-3 rounded-lg transition-colors inline-flex items-center">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Tambah Pilihan
                    </button>
                </div>
            </div>

            <div class="flex space-x-3 pt-4">
                <button type="submit" name="add_question" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-xl transition-colors">
                    Tambah Pertanyaan
                </button>
            </div>
        </form>
    </div>

    <!-- Existing Questions List -->
    <div>
        <h4 class="font-bold text-gray-800 mb-4">Pertanyaan Kuis Saat Ini</h4>

        <?php if (count($questions) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pertanyaan</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kesulitan</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hadiah</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pilihan</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($questions as $question): ?>
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 max-w-xs truncate"><?php echo htmlspecialchars($question['question_text']); ?></div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?></div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                <?php echo $question['difficulty'] === 'easy' ? 'bg-green-100 text-green-800' :
                                       ($question['difficulty'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo ucfirst($question['difficulty']); ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                            <?php echo $question['xp_reward']; ?> XP, <?php echo $question['point_reward']; ?> Poin
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                            <?php echo $question['choice_count']; ?> pilihan
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <?php if ($question['is_active']): ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                    Aktif
                                </span>
                            <?php else: ?>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                    Tidak Aktif
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                            <a href="index.php?page=games&action=edit&id=<?php echo $question['question_id']; ?>"
                               class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                            <a href="index.php?page=games&action=delete&id=<?php echo $question['question_id']; ?>"
                               class="text-red-600 hover:text-red-900"
                               onclick="return confirm('Apakah Anda yakin ingin menghapus pertanyaan ini?')">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-8 text-gray-500">
            <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="mt-2">Belum ada pertanyaan kuis untuk modul ini</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const questionTypeSelect = document.getElementById('question_type');
    const multipleChoiceSection = document.getElementById('multiple-choice-section');

    // Show/hide multiple choice section based on question type
    function toggleChoiceSection() {
        if (questionTypeSelect.value === 'multiple_choice') {
            multipleChoiceSection.style.display = 'block';
        } else {
            multipleChoiceSection.style.display = 'none';
        }
    }

    questionTypeSelect.addEventListener('change', toggleChoiceSection);
    toggleChoiceSection(); // Initialize on page load

    // Add choice functionality
    document.getElementById('add-choice').addEventListener('click', function() {
        const container = document.getElementById('choices-container');
        const choiceCount = container.children.length;

        const choiceDiv = document.createElement('div');
        choiceDiv.className = 'choice-row flex items-center mb-2';
        choiceDiv.innerHTML = `
            <div class="flex-1 mr-2">
                <input type="text" name="choices[]"
                       placeholder="Pilihan ${choiceCount + 1}"
                       class="w-full px-3 py-1 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
            </div>
            <div class="flex items-center">
                <input type="radio" name="correct_choice" value="${choiceCount + 1}"
                       class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                <label class="ml-1 text-sm text-gray-700">Benar</label>
            </div>
            <button type="button" class="ml-2 text-red-600 hover:text-red-800 remove-choice">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
            </button>
        `;

        container.appendChild(choiceDiv);

        // Add event listener to the new remove button
        choiceDiv.querySelector('.remove-choice').addEventListener('click', function() {
            if (container.children.length > 2) { // Keep at least 2 choices
                container.removeChild(choiceDiv);
            } else {
                alert('Minimal harus ada 2 pilihan jawaban');
            }
        });
    });

    // Remove choice functionality
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-choice')) {
            const choiceRow = e.target.closest('.choice-row');
            const container = document.getElementById('choices-container');

            if (container.children.length > 2) { // Keep at least 2 choices
                container.removeChild(choiceRow);
            } else {
                alert('Minimal harus ada 2 pilihan jawaban');
            }
        }
    });
});
</script>

<?php } // Close the else block for when module exists ?>
<?php endif; // Close the quiz management section ?>

<?php if ($action === 'view' && $moduleId > 0): ?>
    <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
        <div class="flex justify-between items-start mb-4">
            <div>
                <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?php echo ucfirst($module['difficulty']); ?></span>
                <h1 class="text-2xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($module['title']); ?></h1>
                <div class="flex items-center text-sm text-gray-500 mt-2">
                    <span><?php echo $module['duration_minutes']; ?> menit</span>
                    <span class="mx-2">•</span>
                    <span>Kategori: <?php echo htmlspecialchars($module['category']); ?></span>
                </div>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold text-green-600">+<?php echo $module['xp_reward']; ?> XP</div>
                <div class="text-sm text-gray-500">+<?php echo $module['point_reward']; ?> Poin</div>
            </div>
        </div>

        <div class="prose max-w-none border-t border-b border-gray-200 py-6 my-4">
            <?php echo $module['content']; ?>
        </div>

        <div class="flex items-center justify-between pt-4 border-t border-gray-200">
            <div class="flex items-center space-x-4">
                <?php if (!hasRole(['admin', 'manager'])): ?>
                    <?php
                    $quizCount = getModuleQuizCount($module['module_id']);
                    $quizCompleted = hasCompletedModuleQuiz($_SESSION['user_id'], $module['module_id']);
                    $quizCorrectAnswers = getModuleQuizCorrectAnswers($_SESSION['user_id'], $module['module_id']);

                    if ($quizCount > 0 && !$quizCompleted):
                    ?>
                        <a href="index.php?page=games&action=module_quiz&id=<?php echo $module['module_id']; ?>"
                           class="bg-orange-600 hover:bg-orange-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">
                            Kerjakan Kuis untuk Mendapatkan XP
                        </a>
                        <div class="text-sm text-gray-600">
                            Kuis: <?php echo $quizCorrectAnswers; ?>/<?php echo $quizCount; ?> benar
                        </div>
                    <?php elseif ($quizCount > 0 && $quizCompleted): ?>
                        <?php if (!$isCompleted): ?>
                            <a href="index.php?page=education&action=complete&id=<?php echo $module['module_id']; ?>"
                               class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors"
                               onclick="return confirm('Apakah kamu yakin sudah menyelesaikan modul ini?')">
                                Tandai Selesai
                            </a>
                        <?php else: ?>
                            <span class="bg-green-100 text-green-800 px-4 py-2 rounded-xl font-medium">✓ Sudah Selesai</span>
                        <?php endif; ?>
                    <?php elseif ($quizCount == 0 && !$isCompleted): ?>
                        <a href="index.php?page=education&action=complete&id=<?php echo $module['module_id']; ?>"
                           class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors"
                           onclick="return confirm('Apakah kamu yakin sudah menyelesaikan modul ini?')">
                            Tandai Selesai
                        </a>
                    <?php elseif ($isCompleted): ?>
                        <span class="bg-green-100 text-green-800 px-4 py-2 rounded-xl font-medium">✓ Sudah Selesai</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (hasRole(['admin', 'manager'])): ?>
                    <a href="index.php?page=education&action=manage_quiz&id=<?php echo $module['module_id']; ?>"
                       class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors">
                        Kelola Kuis
                    </a>
                    <a href="index.php?page=education&action=edit&id=<?php echo $module['module_id']; ?>"
                       class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors">
                        Edit
                    </a>
                    <a href="index.php?page=education&action=delete&id=<?php echo $module['module_id']; ?>"
                       class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors"
                       onclick="return confirmDelete('Apakah kamu yakin ingin menghapus modul ini?')">
                        Hapus
                    </a>
                <?php endif; ?>
            </div>
            <a href="index.php?page=education" class="text-blue-600 hover:text-blue-800 font-medium">‹ Kembali ke Daftar</a>
        </div>
    </div>
<?php  ?>

<?php else: ?>
<!-- List All Modules -->
<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Daftar Modul Edukasi</h2>
        <p class="text-gray-500">Pelajari tentang pengelolaan sampah dan lingkungan</p>
    </div>
    <?php if (hasRole(['admin', 'manager'])): ?>
    <a href="index.php?page=education&action=create" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        Tambah Modul
    </a>
    <?php endif; ?>
</div>

<?php if (hasRole(['admin', 'manager'])): ?>
    <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-xl mb-6">
        <p>Anda sedang dalam mode manajemen. Anda dapat melihat semua modul termasuk yang tidak aktif.</p>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php
    $query = hasRole(['admin', 'manager'])
        ? "SELECT * FROM education_modules ORDER BY created_at DESC"
        : "SELECT * FROM education_modules WHERE is_active = 1 ORDER BY created_at DESC";
    $stmt = $pdo->query($query);
    $modules = $stmt->fetchAll();

    foreach ($modules as $module):
        // Check if user has completed this module
        $stmtCompleted = $pdo->prepare("
            SELECT progress_id FROM user_progress
            WHERE user_id = ? AND item_id = ? AND item_type = 'module'
        ");
        $stmtCompleted->execute([$_SESSION['user_id'], $module['module_id']]);
        $isCompleted = $stmtCompleted->fetch() ? true : false;
    ?>
    <div class="bg-white rounded-2xl shadow-sm p-6 border <?php echo $isCompleted ? 'border-green-200 bg-green-50' : 'border-gray-200'; ?>">
        <?php if (!$module['is_active']): ?>
        <div class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full mb-2 inline-block">Tidak Aktif</div>
        <?php endif; ?>

        <div class="flex justify-between items-start">
            <div>
                <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?php echo ucfirst($module['difficulty']); ?></span>
                <h3 class="font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($module['title']); ?></h3>
                <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars(substr(strip_tags($module['content']), 0, 100)); ?>...</p>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold text-green-600">+<?php echo $module['xp_reward']; ?> XP</div>
                <div class="text-sm text-gray-500">+<?php echo $module['point_reward']; ?> Pts</div>
            </div>
        </div>

        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
            <div class="text-sm text-gray-500">
                <span><?php echo $module['duration_minutes']; ?> menit</span>
                <span class="mx-2">•</span>
                <span><?php echo htmlspecialchars($module['category']); ?></span>
            </div>

            <div class="flex items-center space-x-2">
                <?php if ($isCompleted): ?>
                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">✓ Selesai</span>
                <?php endif; ?>
                <a href="index.php?page=education&action=view&id=<?php echo $module['module_id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                    <?php echo $isCompleted ? 'Lihat Lagi' : 'Mulai'; ?> →
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (count($modules) == 0): ?>
    <div class="col-span-full text-center py-12">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
        <h3 class="text-lg font-medium text-gray-500">Belum ada modul edukasi</h3>
        <p class="text-gray-400 mt-1">
            <?php echo hasRole(['admin', 'manager']) ? 'Tambahkan modul edukasi pertama Anda.' : 'Belum ada modul yang tersedia saat ini.'; ?>
        </p>
        <?php if (hasRole(['admin', 'manager'])): ?>
        <a href="index.php?page=education&action=create" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors">
            Tambah Modul
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
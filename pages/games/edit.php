<?php
// Check permission
if (!hasRole(['admin', 'manager'])) {
    require_once __DIR__ . '/../errors/403.php';
    exit;
}

$question_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($question_id <= 0) {
    setAlert('error', 'ID pertanyaan tidak valid!');
    redirect('index.php?page=games');
}

$error = '';
$success = false;

// Get the question and its choices
$stmt = $pdo->prepare("
    SELECT qq.*, u.username as created_by_name
    FROM quiz_questions qq
    LEFT JOIN users u ON qq.created_by = u.id
    WHERE qq.question_id = ?
");
$stmt->execute([$question_id]);
$question = $stmt->fetch();

if (!$question) {
    setAlert('error', 'Pertanyaan tidak ditemukan!');
    redirect('index.php?page=games');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            
            // Update the question
            $stmt = $pdo->prepare("
                UPDATE quiz_questions
                SET question_text = ?, question_type = ?, category = ?, difficulty = ?, 
                    xp_reward = ?, point_reward = ?, is_active = ?, updated_at = NOW()
                WHERE question_id = ?
            ");
            
            $stmt->execute([$question_text, $question_type, $category, $difficulty, $xp_reward, $point_reward, $is_active, $question_id]);
            
            // Handle choices for multiple choice questions
            if ($question_type === 'multiple_choice') {
                // First, delete existing choices
                $delete_stmt = $pdo->prepare("DELETE FROM quiz_choices WHERE question_id = ?");
                $delete_stmt->execute([$question_id]);
                
                // Then add new choices
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
            
            logActivity($_SESSION['user_id'], 'update_quiz_question', "Updated quiz question: $question_text");
            setAlert('success', 'Pertanyaan kuis berhasil diperbarui!');
            redirect('index.php?page=games');
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollback();
            $error = 'Gagal memperbarui pertanyaan kuis: ' . $e->getMessage();
        }
    }
} else {
    // Load existing choices if it's a multiple choice question
    if ($question['question_type'] === 'multiple_choice') {
        $choices_stmt = $pdo->prepare("SELECT * FROM quiz_choices WHERE question_id = ? ORDER BY choice_order");
        $choices_stmt->execute([$question_id]);
        $choices = $choices_stmt->fetchAll();
    }
}

// Get all categories and difficulties for dropdowns
$categories = $pdo->query("SELECT DISTINCT category FROM quiz_questions WHERE category IS NOT NULL ORDER BY category")->fetchAll();
$difficulties = $pdo->query("SELECT DISTINCT difficulty FROM quiz_questions ORDER BY FIELD(difficulty, 'easy', 'medium', 'hard')")->fetchAll();
?>

<div class="mb-6">
    <div class="flex items-center space-x-4 mb-4">
        <a href="index.php?page=games" class="text-gray-600 hover:text-gray-800">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Edit Pertanyaan Kuis</h1>
            <p class="text-gray-500 mt-1">Edit pertanyaan untuk kuis edukasi</p>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4">
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-sm p-6">
    <form method="POST" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Pertanyaan *</label>
                <textarea name="question_text" required rows="3"
                          class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"><?php echo htmlspecialchars($question['question_text']); ?></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipe Pertanyaan</label>
                <select name="question_type" id="question_type" required
                        class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                    <option value="multiple_choice" <?php echo $question['question_type'] === 'multiple_choice' ? 'selected' : ''; ?>>Pilihan Ganda</option>
                    <option value="true_false" <?php echo $question['question_type'] === 'true_false' ? 'selected' : ''; ?>>Benar/Salah</option>
                    <option value="short_answer" <?php echo $question['question_type'] === 'short_answer' ? 'selected' : ''; ?>>Jawaban Singkat</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Kategori *</label>
                <select name="category" required
                        class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($categories as $category_item): ?>
                        <option value="<?php echo $category_item['category']; ?>"
                                <?php echo $question['category'] === $category_item['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category_item['category']); ?>
                        </option>
                    <?php endforeach; ?>
                    <!-- Default options if no records exist yet -->
                    <option value="Basics" <?php echo $question['category'] === 'Basics' ? 'selected' : ''; ?>>Basics</option>
                    <option value="Recycling" <?php echo $question['category'] === 'Recycling' ? 'selected' : ''; ?>>Recycling</option>
                    <option value="Organic Waste" <?php echo $question['category'] === 'Organic Waste' ? 'selected' : ''; ?>>Organic Waste</option>
                    <option value="Special Waste" <?php echo $question['category'] === 'Special Waste' ? 'selected' : ''; ?>>Special Waste</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tingkat Kesulitan</label>
                <select name="difficulty" required
                        class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                    <option value="easy" <?php echo $question['difficulty'] === 'easy' ? 'selected' : ''; ?>>Mudah</option>
                    <option value="medium" <?php echo $question['difficulty'] === 'medium' ? 'selected' : ''; ?>>Sedang</option>
                    <option value="hard" <?php echo $question['difficulty'] === 'hard' ? 'selected' : ''; ?>>Sulit</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Hadiah XP</label>
                <input type="number" name="xp_reward" min="0" required
                       value="<?php echo (int)$question['xp_reward']; ?>"
                       class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Hadiah Poin</label>
                <input type="number" name="point_reward" min="0" required
                       value="<?php echo (int)$question['point_reward']; ?>"
                       class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
            </div>

            <div class="md:col-span-2 flex items-center">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1"
                           <?php echo $question['is_active'] ? 'checked' : ''; ?>
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700">Aktifkan Pertanyaan</span>
                </label>
            </div>
        </div>

        <!-- Multiple Choice Options -->
        <div id="multiple-choice-section" class="border-t pt-6 mt-6" style="<?php echo $question['question_type'] === 'multiple_choice' ? '' : 'display: none;'; ?>">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Pilihan Jawaban</h3>
            
            <div id="choices-container">
                <?php if (isset($choices) && !empty($choices)): ?>
                    <?php foreach ($choices as $index => $choice): ?>
                    <div class="choice-row flex items-center mb-3">
                        <div class="flex-1 mr-3">
                            <input type="text" name="choices[]" 
                                   value="<?php echo htmlspecialchars($choice['choice_text']); ?>"
                                   placeholder="Pilihan <?php echo $index + 1; ?>"
                                   class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                        </div>
                        <div class="flex items-center">
                            <input type="radio" name="correct_choice" value="<?php echo $index + 1; ?>"
                                   <?php echo $choice['is_correct'] ? 'checked' : ''; ?>
                                   class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            <label class="ml-2 text-sm text-gray-700">Benar</label>
                        </div>
                        <?php if ($index >= 2): ?>
                        <button type="button" class="ml-3 text-red-600 hover:text-red-800 remove-choice">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- Default choices if none exist -->
                    <div class="choice-row flex items-center mb-3">
                        <div class="flex-1 mr-3">
                            <input type="text" name="choices[]" 
                                   placeholder="Pilihan 1"
                                   class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                        </div>
                        <div class="flex items-center">
                            <input type="radio" name="correct_choice" value="1"
                                   class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            <label class="ml-2 text-sm text-gray-700">Benar</label>
                        </div>
                    </div>
                    <div class="choice-row flex items-center mb-3">
                        <div class="flex-1 mr-3">
                            <input type="text" name="choices[]" 
                                   placeholder="Pilihan 2"
                                   class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                        </div>
                        <div class="flex items-center">
                            <input type="radio" name="correct_choice" value="2"
                                   class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            <label class="ml-2 text-sm text-gray-700">Benar</label>
                        </div>
                    </div>
                    <div class="choice-row flex items-center mb-3">
                        <div class="flex-1 mr-3">
                            <input type="text" name="choices[]" 
                                   placeholder="Pilihan 3"
                                   class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                        </div>
                        <div class="flex items-center">
                            <input type="radio" name="correct_choice" value="3"
                                   class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            <label class="ml-2 text-sm text-gray-700">Benar</label>
                        </div>
                        <button type="button" class="ml-3 text-red-600 hover:text-red-800 remove-choice">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                    <div class="choice-row flex items-center mb-3">
                        <div class="flex-1 mr-3">
                            <input type="text" name="choices[]" 
                                   placeholder="Pilihan 4"
                                   class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
                        </div>
                        <div class="flex items-center">
                            <input type="radio" name="correct_choice" value="4"
                                   class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            <label class="ml-2 text-sm text-gray-700">Benar</label>
                        </div>
                        <button type="button" class="ml-3 text-red-600 hover:text-red-800 remove-choice">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-4">
                <button type="button" id="add-choice" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-xl transition-colors inline-flex items-center">
                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Tambah Pilihan
                </button>
            </div>
        </div>

        <div class="flex space-x-3 pt-4 border-t">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-xl transition-colors">
                Simpan Perubahan
            </button>
            <a href="index.php?page=games" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-6 rounded-xl transition-colors inline-block">
                Batal
            </a>
        </div>
    </form>
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
        choiceDiv.className = 'choice-row flex items-center mb-3';
        choiceDiv.innerHTML = `
            <div class="flex-1 mr-3">
                <input type="text" name="choices[]" 
                       placeholder="Pilihan ${choiceCount + 1}"
                       class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
            </div>
            <div class="flex items-center">
                <input type="radio" name="correct_choice" value="${choiceCount + 1}"
                       class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                <label class="ml-2 text-sm text-gray-700">Benar</label>
            </div>
            <button type="button" class="ml-3 text-red-600 hover:text-red-800 remove-choice">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
</div>
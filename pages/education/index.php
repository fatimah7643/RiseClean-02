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

            try {
                // Start transaction
                $pdo->beginTransaction();

                if ($action === 'create') {
                    $stmt = $pdo->prepare("
                        INSERT INTO education_modules (title, content, xp_reward, point_reward, difficulty, category, duration_minutes, is_active)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$title, $content, $xp_reward, $point_reward, $difficulty, $category, $duration_minutes, $is_active]);
                    $moduleId = $pdo->lastInsertId();
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

                // Process quiz questions if they exist
                if (isset($_POST['questions']) && is_array($_POST['questions'])) {
                    // Delete existing questions for this module (if editing)
                    if ($action === 'edit' && $moduleId > 0) {
                        $deleteQuestionsStmt = $pdo->prepare("DELETE FROM quiz_questions WHERE module_id = ?");
                        $deleteQuestionsStmt->execute([$moduleId]);
                    }

                    foreach ($_POST['questions'] as $questionData) {
                        if (!empty(trim($questionData['text']))) {
                            // Insert question
                            $questionText = cleanInput($questionData['text']);
                            $correctAnswer = $questionData['correct'] ?? '';
                            
                            $insertQuestionStmt = $pdo->prepare("
                                INSERT INTO quiz_questions (module_id, question_text, difficulty, xp_reward, point_reward, is_active)
                                VALUES (?, ?, 'medium', 5, 2, 1)
                            ");
                            $insertQuestionStmt->execute([$moduleId, $questionText]);
                            $questionId = $pdo->lastInsertId();

                            // Insert choices
                            if (isset($questionData['options']) && is_array($questionData['options'])) {
                                foreach ($questionData['options'] as $choiceLetter => $choiceText) {
                                    if (!empty(trim($choiceText))) {
                                        $choiceText = cleanInput($choiceText);
                                        $isCorrect = ($choiceLetter === $correctAnswer) ? 1 : 0;
                                        
                                        $insertChoiceStmt = $pdo->prepare("
                                            INSERT INTO quiz_choices (question_id, choice_text, is_correct)
                                            VALUES (?, ?, ?)
                                        ");
                                        $insertChoiceStmt->execute([$questionId, $choiceText, $isCorrect]);
                                    }
                                }
                            }
                        }
                    }
                }

                // Commit transaction
                $pdo->commit();
                
                redirect('index.php?page=education');
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollback();
                setAlert('error', 'Gagal menyimpan modul: ' . $e->getMessage());
                redirect("index.php?page=education&action={$action}" . ($moduleId > 0 ? "&id={$moduleId}" : ""));
            }
        } elseif ($action === 'delete' && $moduleId > 0) {
            $stmt = $pdo->prepare("DELETE FROM education_modules WHERE module_id = ?");
            $stmt->execute([$moduleId]);
            setAlert('success', 'Modul edukasi berhasil dihapus');
            redirect('index.php?page=education');
        }
        elseif ($action === 'delete_question' && isset($_GET['id'])) {
            $questionId = (int)$_GET['id'];
            $module_id = isset($_GET['module']) ? (int)$_GET['module'] : 0;
            
            // Delete the question and its choices
            $pdo->beginTransaction();
            try {
                // Delete choices first (due to foreign key constraint)
                $deleteChoicesStmt = $pdo->prepare("DELETE FROM quiz_choices WHERE question_id = ?");
                $deleteChoicesStmt->execute([$questionId]);
                
                // Then delete the question
                $deleteQuestionStmt = $pdo->prepare("DELETE FROM quiz_questions WHERE question_id = ?");
                $deleteQuestionStmt->execute([$questionId]);
                
                $pdo->commit();
                setAlert('success', 'Pertanyaan kuis berhasil dihapus');
            } catch (Exception $e) {
                $pdo->rollback();
                setAlert('error', 'Gagal menghapus pertanyaan: ' . $e->getMessage());
            }
            
            // Redirect back to the manage quiz page
            redirect("index.php?page=education&action=manage_quiz&id={$module_id}");
        }
    }

    if ($action === 'edit' || $action === 'view' && $moduleId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM education_modules WHERE module_id = ?");
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch();

        //jika data tidak ditemukan, kembali ke daftar
        if (!$module) {
            setAlert('error', 'Modul edukasi tidak ditemukan');
            redirect('index.php?page=education');
        }
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

// Initialize variables
$isCompleted = false;

// Fetch module for viewing (both admin and regular users)
if ($action === 'view' && $moduleId > 0) {
    if (hasRole(['admin', 'manager'])) {
        // Admins can see all modules (active and inactive)
        $stmt = $pdo->prepare("SELECT * FROM education_modules WHERE module_id = ?");
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch();
    } else {
        // Regular users can only see active modules
        $stmt = $pdo->prepare("SELECT * FROM education_modules WHERE module_id = ? AND is_active = 1");
        $stmt->execute([$moduleId]);
        $module = $stmt->fetch();
    }

    // If module not found, redirect with error
    if (!$module) {
        setAlert('error', 'Modul edukasi tidak ditemukan atau tidak aktif');
        redirect('index.php?page=education');
        exit;
    }
    
    // Check if user has completed this module
    $stmtCompleted = $pdo->prepare("
        SELECT progress_id FROM user_progress
        WHERE user_id = ? AND item_id = ? AND item_type = 'module'
    ");
    $stmtCompleted->execute([$_SESSION['user_id'], $module['module_id']]);
    $isCompleted = $stmtCompleted->fetch() ? true : false;
}
?>

<div class="mb-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-2">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Modul Edukasi</h1>
            <p class="text-gray-600 mt-2">Pelajari tentang pengelolaan sampah dan kebersihan lingkungan</p>
        </div>
        <?php if (hasRole(['admin', 'manager'])): ?>
        <a href="index.php?page=education&action=create" class="self-start bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-5 rounded-xl transition-all duration-300 shadow-md hover:shadow-lg flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Tambah Modul
        </a>
        <?php endif; ?>
    </div>

    <?php if (hasRole(['admin', 'manager'])): ?>
    <div class="bg-blue-50 border border-blue-100 text-blue-700 px-4 py-3 rounded-xl mt-4 flex items-start">
        <svg class="w-5 h-5 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div>
            <p class="font-medium">Mode Manajemen Aktif</p>
            <p class="text-sm mt-1">Anda dapat melihat semua modul termasuk yang tidak aktif.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (hasRole(['admin', 'manager']) && ($action === 'create' || $action === 'edit')): ?>
<!-- Create/Edit Form for Admin/Manager -->
<div class="bg-white rounded-2xl shadow-sm p-6 mb-8 border border-gray-100">
    <div class="p-0.5 bg-blue-500 mb-6 rounded-t-xl"></div>
    <h3 class="text-xl font-bold text-gray-800 mb-5"><?php echo $action === 'create' ? 'Tambah Modul Baru' : 'Edit Modul'; ?></h3>

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

            <div class="md:col-span-2 flex flex-col sm:flex-row sm:items-center gap-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1"
                           <?php echo (isset($module) && $module['is_active']) || (isset($_POST['is_active']) && $_POST['is_active']) ? 'checked' : ''; ?>
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700">Aktifkan Modul</span>
                </label>
                
                <div class="flex flex-wrap gap-3 sm:ml-auto">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-xl transition-all duration-300 shadow-md hover:shadow-lg flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        <?php echo $action === 'create' ? 'Tambah Modul' : 'Perbarui Modul'; ?>
                    </button>
                    <a href="index.php?page=education" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2.5 px-6 rounded-xl transition-all duration-300 shadow-sm hover:shadow-md flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Batal
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Kuis Section -->
        <div id="quiz-section" class="mt-8 border-t pt-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-800">Kuis Terkait</h3>
                <button type="button" id="add-question-btn" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Tambah Soal
                </button>
            </div>
            
            <div id="questions-container">
                <!-- Default first question -->
                <div class="question-item bg-gray-50 p-5 rounded-xl mb-4 border border-gray-200">
                    <div class="mb-3">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pertanyaan</label>
                        <input type="text" name="questions[0][text]" placeholder="Masukkan pertanyaan..." 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pilihan A</label>
                            <input type="text" name="questions[0][options][A]" placeholder="Pilihan A" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pilihan B</label>
                            <input type="text" name="questions[0][options][B]" placeholder="Pilihan B" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pilihan C</label>
                            <input type="text" name="questions[0][options][C]" placeholder="Pilihan C" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Pilihan D</label>
                            <input type="text" name="questions[0][options][D]" placeholder="Pilihan D" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Jawaban Benar</label>
                        <div class="flex space-x-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="questions[0][correct]" value="A" class="text-blue-600">
                                <span class="ml-2">A</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="questions[0][correct]" value="B" class="text-blue-600">
                                <span class="ml-2">B</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="questions[0][correct]" value="C" class="text-blue-600">
                                <span class="ml-2">C</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="questions[0][correct]" value="D" class="text-blue-600">
                                <span class="ml-2">D</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-3 text-right">
                        <button type="button" class="remove-question bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-lg text-sm transition-colors">
                            Hapus Soal
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="flex flex-wrap gap-3 mt-6">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-6 rounded-xl transition-all duration-300 shadow-md hover:shadow-lg flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <?php echo $action === 'create' ? 'Tambah Modul' : 'Perbarui Modul'; ?>
            </button>
            <a href="index.php?page=education" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2.5 px-6 rounded-xl transition-all duration-300 shadow-sm hover:shadow-md flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Batal
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let questionIndex = 1;
    
    // Add new question
    document.getElementById('add-question-btn').addEventListener('click', function() {
        const container = document.getElementById('questions-container');
        const newQuestion = document.createElement('div');
        newQuestion.className = 'question-item bg-gray-50 p-5 rounded-xl mb-4 border border-gray-200';
        newQuestion.innerHTML = `
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Pertanyaan</label>
                <input type="text" name="questions[${questionIndex}][text]" placeholder="Masukkan pertanyaan..." 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pilihan A</label>
                    <input type="text" name="questions[${questionIndex}][options][A]" placeholder="Pilihan A" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pilihan B</label>
                    <input type="text" name="questions[${questionIndex}][options][B]" placeholder="Pilihan B" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pilihan C</label>
                    <input type="text" name="questions[${questionIndex}][options][C]" placeholder="Pilihan C" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Pilihan D</label>
                    <input type="text" name="questions[${questionIndex}][options][D]" placeholder="Pilihan D" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Jawaban Benar</label>
                <div class="flex space-x-4">
                    <label class="inline-flex items-center">
                        <input type="radio" name="questions[${questionIndex}][correct]" value="A" class="text-blue-600">
                        <span class="ml-2">A</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="questions[${questionIndex}][correct]" value="B" class="text-blue-600">
                        <span class="ml-2">B</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="questions[${questionIndex}][correct]" value="C" class="text-blue-600">
                        <span class="ml-2">C</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="questions[${questionIndex}][correct]" value="D" class="text-blue-600">
                        <span class="ml-2">D</span>
                    </label>
                </div>
            </div>
            
            <div class="mt-3 text-right">
                <button type="button" class="remove-question bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-lg text-sm transition-colors">
                    Hapus Soal
                </button>
            </div>
        `;
        
        container.appendChild(newQuestion);
        questionIndex++;
        
        // Add event listener to the new remove button
        newQuestion.querySelector('.remove-question').addEventListener('click', function() {
            if (container.children.length > 1) {
                container.removeChild(newQuestion);
            } else {
                alert('Minimal harus ada satu soal kuis');
            }
        });
    });
    
    // Add event listeners to existing remove buttons
    document.querySelectorAll('.remove-question').forEach(button => {
        button.addEventListener('click', function() {
            const container = document.getElementById('questions-container');
            const questionItem = this.closest('.question-item');
            
            if (container.children.length > 1) {
                container.removeChild(questionItem);
            } else {
                alert('Minimal harus ada satu soal kuis');
            }
        });
    });
});
</script>
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
                INSERT INTO quiz_questions (question_text, module_id, difficulty, xp_reward, point_reward, is_active)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([$question_text, $moduleId, $difficulty, $xp_reward, $point_reward, $is_active]);
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
    SELECT qq.question_id, qq.question_text, qq.difficulty, qq.xp_reward, qq.point_reward, qq.is_active,
           COUNT(qc.choice_id) as choice_count
    FROM quiz_questions qq
    LEFT JOIN quiz_choices qc ON qq.question_id = qc.question_id
    WHERE qq.module_id = ?
    GROUP BY qq.question_id, qq.question_text, qq.difficulty, qq.xp_reward, qq.point_reward, qq.is_active
    ORDER BY qq.question_id DESC
");
$quiz_questions->execute([$moduleId]);
$questions = $quiz_questions->fetchAll();

// Get all categories and difficulties for dropdowns
$categories = $pdo->query("SELECT DISTINCT category FROM quiz_questions WHERE category IS NOT NULL ORDER BY category")->fetchAll();
$difficulties = $pdo->query("SELECT DISTINCT difficulty FROM quiz_questions ORDER BY FIELD(difficulty, 'easy', 'medium', 'hard')")->fetchAll();
?>

<div class="bg-white rounded-2xl shadow-sm p-6 mb-8 border border-gray-100">
    <div class="p-0.5 bg-purple-500 mb-6 rounded-t-xl"></div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h3 class="text-xl font-bold text-gray-800">Manajemen Kuis - <?php echo htmlspecialchars($module['title']); ?></h3>
            <p class="text-gray-600 text-sm mt-1">Tambahkan dan kelola pertanyaan kuis untuk modul ini</p>
        </div>
        <a href="index.php?page=education" class="self-start bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2.5 px-5 rounded-xl transition-all duration-300 shadow-sm hover:shadow-md flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
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
                            <!-- Edit link temporarily disabled until edit functionality is implemented -->
                            <span class="text-gray-400 mr-3">Edit</span>
                            <a href="index.php?page=education&action=delete_question&id=<?php echo $question['question_id']; ?>&module=<?php echo $moduleId; ?>"
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
        if (questionTypeSelect && multipleChoiceSection) {
            if (questionTypeSelect.value === 'multiple_choice') {
                multipleChoiceSection.style.display = 'block';
            } else {
                multipleChoiceSection.style.display = 'none';
            }
        }
    }

    if (questionTypeSelect) {
        questionTypeSelect.addEventListener('change', toggleChoiceSection);
        toggleChoiceSection(); // Initialize on page load
    }

    // Add choice functionality
    const addChoiceBtn = document.getElementById('add-choice');
    if (addChoiceBtn) {
        addChoiceBtn.addEventListener('click', function() {
            const container = document.getElementById('choices-container');
            if (!container) return;

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
            const removeBtn = choiceDiv.querySelector('.remove-choice');
            if (removeBtn) {
                removeBtn.addEventListener('click', function() {
                    if (container.children.length > 2) { // Keep at least 2 choices
                        container.removeChild(choiceDiv);
                    } else {
                        alert('Minimal harus ada 2 pilihan jawaban');
                    }
                });
            }
        });
    }

    // Remove choice functionality
    document.addEventListener('click', function(e) {
        const removeBtn = e.target.closest('.remove-choice');
        if (removeBtn) {
            const choiceRow = removeBtn.closest('.choice-row');
            const container = document.getElementById('choices-container');

            if (container && container.children.length > 2) { // Keep at least 2 choices
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
    <div class="bg-blue-50 rounded-2xl shadow-lg overflow-hidden mb-6 border border-gray-100">
        <div class="p-1 bg-blue-500"></div>
        <div class="p-6 sm:p-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-6 mb-6">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="inline-flex items-center justify-center bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full">
                            <?php echo ucfirst($module['difficulty']); ?>
                        </span>
                        <?php if (!$module['is_active']): ?>
                            <span class="inline-flex items-center justify-center bg-yellow-100 text-yellow-800 text-xs font-semibold px-3 py-1 rounded-full">
                                Tidak Aktif
                            </span>
                        <?php endif; ?>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 leading-tight"><?php echo htmlspecialchars($module['title']); ?></h1>
                    <div class="flex flex-wrap items-center gap-3 mt-3 text-gray-600">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span><?php echo $module['duration_minutes']; ?> menit</span>
                        </div>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                            </svg>
                            <span><?php echo htmlspecialchars($module['category']); ?></span>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-5 text-center min-w-[140px] border border-gray-100">
                    <div class="text-2xl font-bold text-green-600"><?php echo $module['xp_reward']; ?><span class="text-sm font-normal text-gray-500">XP</span></div>
                    <div class="text-sm text-gray-600 mt-1">+<?php echo $module['point_reward']; ?> Poin</div>
                </div>
            </div>

            <div class="bg-white rounded-xl p-6 border border-gray-200 shadow-sm">
                <?php echo $module['content']; ?>
            </div>

            <div class="flex flex-col sm:flex-row items-center justify-between pt-6 mt-6 gap-4 border-t border-gray-200">
                <div class="flex flex-wrap items-center gap-3">
                    <?php if (!hasRole(['admin', 'manager'])): ?>
                        <?php
                        $quizCount = getModuleQuizCount($module['module_id']);
                        $quizCompleted = hasCompletedModuleQuiz($_SESSION['user_id'], $module['module_id']);
                        $quizCorrectAnswers = getModuleQuizCorrectAnswers($_SESSION['user_id'], $module['module_id']);

                        if ($quizCount > 0 && !$quizCompleted):
                        ?>
                            <a href="index.php?page=games&action=module_quiz&id=<?php echo $module['module_id']; ?>"
                               class="bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                                Kerjakan Kuis untuk Mendapatkan XP
                            </a>
                            <div class="text-sm text-gray-600 bg-blue-50 px-3 py-2 rounded-lg">
                                Kuis: <?php echo $quizCorrectAnswers; ?>/<?php echo $quizCount; ?> benar
                            </div>
                        <?php elseif ($quizCount > 0 && $quizCompleted): ?>
                            <?php if (!$isCompleted): ?>
                                <a href="index.php?page=education&action=complete&id=<?php echo $module['module_id']; ?>"
                                   class="bg-green-500 hover:bg-green-600 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5"
                                   onclick="return confirm('Apakah kamu yakin sudah menyelesaikan modul ini?')">
                                    Tandai Selesai
                                </a>
                            <?php else: ?>
                                <span class="inline-flex items-center bg-green-100 text-green-800 px-4 py-2 rounded-xl font-medium">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    ✓ Sudah Selesai
                                </span>
                            <?php endif; ?>
                        <?php elseif ($quizCount == 0 && !$isCompleted): ?>
                            <a href="index.php?page=education&action=complete&id=<?php echo $module['module_id']; ?>"
                               class="bg-green-500 hover:bg-green-600 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 shadow-md hover:shadow-lg transform hover:-translate-y-0.5"
                               onclick="return confirm('Apakah kamu yakin sudah menyelesaikan modul ini?')">
                                Tandai Selesai
                            </a>
                        <?php elseif ($isCompleted): ?>
                            <span class="inline-flex items-center bg-green-100 text-green-800 px-4 py-2 rounded-xl font-medium">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                ✓ Sudah Selesai
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (hasRole(['admin', 'manager'])): ?>
                        <a href="index.php?page=education&action=manage_quiz&id=<?php echo $module['module_id']; ?>"
                           class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2.5 px-5 rounded-xl transition-all duration-300 shadow hover:shadow-md flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Kelola Kuis
                        </a>
                        <a href="index.php?page=education&action=edit&id=<?php echo $module['module_id']; ?>"
                           class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-5 rounded-xl transition-all duration-300 shadow hover:shadow-md flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit
                        </a>
                        <a href="index.php?page=education&action=delete&id=<?php echo $module['module_id']; ?>"
                           class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2.5 px-5 rounded-xl transition-all duration-300 shadow hover:shadow-md flex items-center"
                           onclick="return confirmDelete('Apakah kamu yakin ingin menghapus modul ini?')">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Hapus
                        </a>
                    <?php endif; ?>
                </div>
                <a href="index.php?page=education" class="text-blue-600 hover:text-blue-800 font-medium flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Kembali ke Daftar
                </a>
            </div>
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
    <div class="bg-white rounded-2xl shadow-sm overflow-hidden border <?php echo $isCompleted ? 'border-green-200 ring-1 ring-green-50' : 'border-gray-200'; ?> transition-transform duration-300 hover:shadow-md hover:-translate-y-1">
        <div class="p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <?php if (!$module['is_active']): ?>
                    <div class="bg-yellow-100 text-yellow-800 text-xs font-semibold px-2.5 py-1 rounded-full mb-2 inline-block">Tidak Aktif</div>
                    <?php endif; ?>

                    <div class="flex items-center gap-2 mb-2">
                        <span class="inline-flex items-center justify-center bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-1 rounded-full">
                            <?php echo ucfirst($module['difficulty']); ?>
                        </span>
                        <?php if ($isCompleted): ?>
                            <span class="inline-flex items-center justify-center bg-green-100 text-green-800 text-xs font-semibold px-2.5 py-1 rounded-full">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Selesai
                            </span>
                        <?php endif; ?>
                    </div>

                    <h3 class="font-bold text-gray-800 text-lg leading-tight mb-2"><?php echo htmlspecialchars($module['title']); ?></h3>
                    <p class="text-gray-600 text-sm mb-4 max-h-16 overflow-hidden"><?php echo htmlspecialchars(substr(strip_tags($module['content']), 0, 120)); ?>...</p>
                </div>

                <div class="bg-blue-50 rounded-lg p-3 text-center min-w-[80px] border border-gray-100">
                    <div class="text-lg font-bold text-green-600"><?php echo $module['xp_reward']; ?><span class="text-xs font-normal text-gray-500">XP</span></div>
                    <div class="text-xs text-gray-600 mt-1">+<?php echo $module['point_reward']; ?> P</div>
                </div>
            </div>

            <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                <div class="flex items-center text-sm text-gray-500 gap-3">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span><?php echo $module['duration_minutes']; ?>m</span>
                    </div>
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        <span class="truncate max-w-[80px]"><?php echo htmlspecialchars($module['category']); ?></span>
                    </div>
                </div>

                <a href="index.php?page=education&action=view&id=<?php echo $module['module_id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium text-sm flex items-center group">
                    <?php echo $isCompleted ? 'Lihat Lagi' : 'Mulai'; ?>
                    <svg class="w-4 h-4 ml-1 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (count($modules) == 0): ?>
    <div class="col-span-full text-center py-16">
        <div class="mx-auto bg-gray-100 rounded-full p-4 w-20 h-20 flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-700">Belum ada modul edukasi</h3>
        <p class="text-gray-500 mt-2 max-w-md mx-auto">
            <?php echo hasRole(['admin', 'manager']) ? 'Tambahkan modul edukasi pertama Anda untuk memulai.' : 'Belum ada modul yang tersedia saat ini. Silakan coba lagi nanti.'; ?>
        </p>
        <?php if (hasRole(['admin', 'manager'])): ?>
        <a href="index.php?page=education&action=create" class="inline-block mt-6 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-xl transition-all duration-300 shadow-md hover:shadow-lg">
            Tambah Modul Pertama
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
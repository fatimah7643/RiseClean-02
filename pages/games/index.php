<?php
// Check if user is admin/manager or regular user
if (hasRole(['admin', 'manager'])) {
    // Admin/Manager functionality - already handled in the existing file
    $action = isset($_GET['action']) ? cleanInput($_GET['action']) : 'list';

    switch ($action) {
        case 'create':
            require_once 'create.php';
            break;
        case 'edit':
            require_once 'edit.php';
            break;
        case 'delete':
            require_once 'delete.php';
            break;
        default:
            // List quiz questions
            $search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
            $categoryFilter = isset($_GET['category']) ? cleanInput($_GET['category']) : '';
            $difficultyFilter = isset($_GET['difficulty']) ? cleanInput($_GET['difficulty']) : '';

            // Pagination setup
            $currentPage = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
            $itemsPerPage = 10;
            $offset = ($currentPage - 1) * $itemsPerPage;

            // Count total records with filters
            $countSql = "SELECT COUNT(*) FROM quiz_questions WHERE 1=1";
            if ($search) {
                $countSql .= " AND (question_text LIKE :search1 OR category LIKE :search2)";
            }
            if ($categoryFilter) {
                $countSql .= " AND category = :category";
            }
            if ($difficultyFilter) {
                $countSql .= " AND difficulty = :difficulty";
            }

            $countStmt = $pdo->prepare($countSql);
            if ($search) {
                $countStmt->bindValue(':search1', "%$search%");
                $countStmt->bindValue(':search2', "%$search%");
            }
            if ($categoryFilter) {
                $countStmt->bindValue(':category', $categoryFilter);
            }
            if ($difficultyFilter) {
                $countStmt->bindValue(':difficulty', $difficultyFilter);
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetchColumn();
            $totalPages = ceil($totalRecords / $itemsPerPage);

            // Main query with pagination
            $sql = "SELECT qq.*, u.username as created_by_name
                    FROM quiz_questions qq
                    LEFT JOIN users u ON qq.created_by = u.id
                    WHERE 1=1";

            if ($search) {
                $sql .= " AND (question_text LIKE :search1 OR category LIKE :search2)";
            }

            if ($categoryFilter) {
                $sql .= " AND category = :category";
            }

            if ($difficultyFilter) {
                $sql .= " AND difficulty = :difficulty";
            }

            $sql .= " ORDER BY qq.created_at DESC LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);

            if ($search) {
                $stmt->bindValue(':search1', "%$search%");
                $stmt->bindValue(':search2', "%$search%");
            }
            if ($categoryFilter) {
                $stmt->bindValue(':category', $categoryFilter);
            }
            if ($difficultyFilter) {
                $stmt->bindValue(':difficulty', $difficultyFilter);
            }
            $stmt->bindValue(':limit', $itemsPerPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $questions = $stmt->fetchAll();

            // Get distinct categories and difficulties for filter
            $categories = $pdo->query("SELECT DISTINCT category FROM quiz_questions WHERE category IS NOT NULL ORDER BY category")->fetchAll();
            $difficulties = $pdo->query("SELECT DISTINCT difficulty FROM quiz_questions ORDER BY FIELD(difficulty, 'easy', 'medium', 'hard')")->fetchAll();
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-3xl font-bold text-gray-800">Manajemen Kuis</h1>
        <p class="text-gray-500 mt-1">Kelola pertanyaan kuis edukasi</p>
    </div>
    <?php if (hasRole(['admin', 'manager'])): ?>
    <a href="index.php?page=games&action=create" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-xl transition-colors inline-flex items-center space-x-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        <span>Tambah Soal</span>
    </a>
    <?php endif; ?>
</div>

<!-- Search & Filter -->
<div class="bg-white rounded-2xl shadow-sm p-4 mb-6">
    <form method="GET" class="flex flex-col md:flex-row gap-3">
        <input type="hidden" name="page" value="games">
        <div class="flex-1">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Cari pertanyaan atau kategori..."
                   class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
        </div>
        <select name="category" class="px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
            <option value="">Semua Kategori</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?php echo $category['category']; ?>" <?php echo $categoryFilter === $category['category'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($category['category']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="difficulty" class="px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
            <option value="">Semua Tingkat Kesulitan</option>
            <?php foreach ($difficulties as $difficulty): ?>
                <option value="<?php echo $difficulty['difficulty']; ?>" <?php echo $difficultyFilter === $difficulty['difficulty'] ? 'selected' : ''; ?>>
                    <?php echo ucfirst($difficulty['difficulty']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2 rounded-xl transition-colors">
            Filter
        </button>
        <?php if ($search || $categoryFilter || $difficultyFilter): ?>
        <a href="index.php?page=games" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium px-6 py-2 rounded-xl transition-colors inline-flex items-center">
            Reset
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Questions List -->
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pertanyaan</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kesulitan</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hadiah</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dibuat Oleh</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (count($questions) > 0): ?>
                    <?php foreach ($questions as $question): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 max-w-xs truncate"><?php echo htmlspecialchars($question['question_text']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($question['category']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?php echo $question['difficulty'] === 'easy' ? 'bg-green-100 text-green-800' : 
                                       ($question['difficulty'] === 'medium' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'); ?>">
                                <?php echo ucfirst($question['difficulty']); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo $question['xp_reward']; ?> XP, <?php echo $question['point_reward']; ?> Poin
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
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
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo htmlspecialchars($question['created_by_name'] ?? 'System'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="index.php?page=games&action=edit&id=<?php echo $question['question_id']; ?>" 
                               class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                            <a href="index.php?page=games&action=delete&id=<?php echo $question['question_id']; ?>" 
                               class="text-red-600 hover:text-red-900"
                               onclick="return confirm('Apakah Anda yakin ingin menghapus pertanyaan ini?')">Hapus</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <p class="text-lg font-medium text-gray-500">Tidak ada pertanyaan kuis</p>
                            <p class="text-gray-400 mt-1">Tambahkan pertanyaan kuis pertama Anda</p>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="flex justify-center mt-4">
            <nav class="flex items-center space-x-2">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=games<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter ? '&category=' . urlencode($categoryFilter) : ''; ?><?php echo $difficultyFilter ? '&difficulty=' . urlencode($difficultyFilter) : ''; ?>&page_num=<?php echo $currentPage - 1; ?>"
                       class="px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-100">
                        <span>Sebelumnya</span>
                    </a>
                <?php endif; ?>

                <?php
                // Calculate pagination range to show
                $start = max(1, $currentPage - 2);
                $end = min($totalPages, $currentPage + 2);
                ?>

                <?php if ($start > 1): ?>
                    <a href="?page=games<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter ? '&category=' . urlencode($categoryFilter) : ''; ?><?php echo $difficultyFilter ? '&difficulty=' . urlencode($difficultyFilter) : ''; ?>&page_num=1"
                       class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100">
                        1
                    </a>
                    <?php if ($start > 2): ?>
                        <span class="px-4 py-2 text-sm font-medium text-gray-500">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?page=games<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter ? '&category=' . urlencode($categoryFilter) : ''; ?><?php echo $difficultyFilter ? '&difficulty=' . urlencode($difficultyFilter) : ''; ?>&page_num=<?php echo $i; ?>"
                       class="px-4 py-2 text-sm font-medium <?php echo $i == $currentPage ? 'bg-blue-600 text-white' : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-100'; ?> rounded-lg">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?>
                        <span class="px-4 py-2 text-sm font-medium text-gray-500">...</span>
                    <?php endif; ?>
                    <a href="?page=games<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter ? '&category=' . urlencode($categoryFilter) : ''; ?><?php echo $difficultyFilter ? '&difficulty=' . urlencode($difficultyFilter) : ''; ?>&page_num=<?php echo $totalPages; ?>"
                       class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100">
                        <?php echo $totalPages; ?>
                    </a>
                <?php endif; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=games<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryFilter ? '&category=' . urlencode($categoryFilter) : ''; ?><?php echo $difficultyFilter ? '&difficulty=' . urlencode($difficultyFilter) : ''; ?>&page_num=<?php echo $currentPage + 1; ?>"
                       class="px-4 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-lg hover:bg-gray-100">
                        <span>Selanjutnya</span>
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Records info -->
<?php if ($totalRecords > 0): ?>
    <div class="text-center mt-4 text-gray-500">
        Menampilkan <?php echo $offset + 1; ?>-<?php echo min($offset + $itemsPerPage, $totalRecords); ?> dari <?php echo $totalRecords; ?> data
    </div>
<?php endif; ?>

<?php
            break;
    }
} else {
    // For regular users - take quizzes
    $action = isset($_GET['action']) ? cleanInput($_GET['action']) : 'list';
    $categoryId = isset($_GET['category']) ? cleanInput($_GET['category']) : '';
    $moduleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($action === 'module_quiz' && $moduleId > 0) {
        // Get all quiz questions for the specific module
        $stmt = $pdo->prepare("
            SELECT qq.*
            FROM quiz_questions qq
            WHERE qq.module_id = ? AND qq.is_active = 1
            ORDER BY RAND()  -- Randomize the order of questions
        ");
        $stmt->execute([$moduleId]);
        $questions = $stmt->fetchAll();

        if (empty($questions)) {
            setAlert('error', 'Tidak ada kuis tersedia untuk modul ini!');
            redirect('index.php?page=education&action=view&id=' . $moduleId);
        }

        // Get module information
        $moduleStmt = $pdo->prepare("SELECT title FROM education_modules WHERE module_id = ?");
        $moduleStmt->execute([$moduleId]);
        $module = $moduleStmt->fetch();

        if (!$module) {
            setAlert('error', 'Modul tidak ditemukan!');
            redirect('index.php?page=education');
        }

        // Process quiz submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_module_quiz'])) {
            $score = 0;
            $total_questions = count($questions);
            $correct_answers = 0;

            foreach ($questions as $question) {
                $question_id = $question['question_id'];
                $user_answer = isset($_POST["question_$question_id"]) ? (int)$_POST["question_$question_id"] : 0;

                // Check if the selected choice is correct
                if ($user_answer > 0) {
                    $choice_stmt = $pdo->prepare("SELECT is_correct FROM quiz_choices WHERE choice_id = ?");
                    $choice_stmt->execute([$user_answer]);
                    $choice = $choice_stmt->fetch();

                    if ($choice && $choice['is_correct'] == 1) {
                        $correct_answers++;
                    }
                }
            }

            $percentage = $total_questions > 0 ? ($correct_answers / $total_questions) * 100 : 0;

            // Save each answer individually
            foreach ($questions as $question) {
                $question_id = $question['question_id'];
                $is_correct = false;
                $selected_choice_id = null;
                $answer_text = null;
                $points_earned = 0;

                if ($question['question_type'] === 'multiple_choice' || $question['question_type'] === 'true_false') {
                    $user_answer = isset($_POST["question_$question_id"]) ? (int)$_POST["question_$question_id"] : 0;

                    if ($user_answer > 0) {
                        // Verify that the choice belongs to the question to prevent tampering
                        $choice_stmt = $pdo->prepare("
                            SELECT is_correct
                            FROM quiz_choices
                            WHERE choice_id = ? AND question_id = ?
                        ");
                        $choice_stmt->execute([$user_answer, $question_id]);
                        $choice = $choice_stmt->fetch();

                        if ($choice) {
                            $selected_choice_id = $user_answer;
                            if ($choice['is_correct'] == 1) {
                                $is_correct = true;
                                $points_earned = $question['point_reward'];
                            }
                        }
                    }
                } elseif ($question['question_type'] === 'short_answer') {
                    $answer_text = isset($_POST["answer_text_$question_id"]) ? cleanInput($_POST["answer_text_$question_id"]) : '';
                    // For short answer, we would typically have a more sophisticated checking mechanism
                    // For now, we'll set is_correct to false and points to 0
                    // In a real implementation, you'd compare against expected answers
                }

                // Record the answer
                $answer_stmt = $pdo->prepare("
                    INSERT INTO user_quiz_answers (user_id, question_id, selected_choice_id, answer_text, is_correct, points_earned)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $answer_stmt->execute([
                    $_SESSION['user_id'],
                    $question_id,
                    $selected_choice_id,
                    $answer_text,
                    $is_correct ? 1 : 0,
                    $points_earned
                ]);
            }

            // Show results
            $quiz_result_message = "Kuis selesai! Skor: $correct_answers/$total_questions (" . round($percentage, 2) . "%)";
            if ($percentage >= 70) { // Passing score is 70%
                $quiz_result_message .= " Selamat! Anda telah menyelesaikan kuis untuk modul ini.";
            } else {
                $quiz_result_message .= " Anda perlu mencapai skor minimal 70% untuk menyelesaikan kuis.";
            }
        }

        // If quiz is not submitted yet, get choices for each question
        if (!isset($_POST['submit_module_quiz'])) {
            foreach ($questions as &$question) {
                $choices_stmt = $pdo->prepare("SELECT * FROM quiz_choices WHERE question_id = ? ORDER BY choice_order");
                $choices_stmt->execute([$question['question_id']]);
                $question['choices'] = $choices_stmt->fetchAll();
            }
        }
    } elseif ($action === 'take' && isset($_GET['id'])) {
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

<?php if ($action === 'module_quiz' && $moduleId > 0): ?>
<!-- Module Quiz View -->
<div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
    <div class="flex justify-between items-start mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 mt-2">Kuis Modul: <?php echo htmlspecialchars($module['title']); ?></h1>
            <div class="flex items-center text-sm text-gray-500 mt-2">
                <span>Jumlah Soal: <?php echo count($questions); ?></span>
            </div>
        </div>
        <div class="text-right">
            <div class="text-sm text-gray-500">Skor Minimum: 70%</div>
        </div>
    </div>

    <?php if (isset($quiz_result_message)): ?>
        <div class="mb-6 p-4 rounded-xl <?php echo strpos($quiz_result_message, 'minimal 70%') ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
            <?php echo $quiz_result_message; ?>
            <br><br>
            <a href="index.php?page=education&action=view&id=<?php echo $moduleId; ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors inline-block">
                Kembali ke Modul
            </a>
        </div>
    <?php else: ?>
        <form method="POST" class="mt-6 space-y-6">
            <input type="hidden" name="submit_module_quiz" value="1">

            <?php foreach ($questions as $index => $question): ?>
                <div class="border border-gray-200 rounded-xl p-4 mb-4">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?php echo ucfirst($question['difficulty']); ?></span>
                            <h3 class="font-bold text-gray-800 mt-2"><?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?></h3>
                        </div>
                        <div class="text-right">
                            <div class="text-md font-bold text-green-600">+<?php echo $question['xp_reward']; ?> XP</div>
                            <div class="text-sm text-gray-500">+<?php echo $question['point_reward']; ?> Poin</div>
                        </div>
                    </div>

                    <?php if ($question['question_type'] === 'multiple_choice' && isset($question['choices'])): ?>
                        <?php foreach ($question['choices'] as $choice): ?>
                            <div class="flex items-start mb-2">
                                <input type="radio" name="question_<?php echo $question['question_id']; ?>" value="<?php echo $choice['choice_id']; ?>" id="choice_<?php echo $choice['choice_id']; ?>" required
                                       class="mt-1 h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                                <label for="choice_<?php echo $choice['choice_id']; ?>" class="ml-3 block text-gray-700">
                                    <?php echo htmlspecialchars($choice['choice_text']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php elseif ($question['question_type'] === 'true_false'): ?>
                        <div class="flex items-start mb-2">
                            <input type="radio" name="question_<?php echo $question['question_id']; ?>" value="1" id="true_choice_<?php echo $question['question_id']; ?>" required
                                   class="mt-1 h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            <label for="true_choice_<?php echo $question['question_id']; ?>" class="ml-3 block text-gray-700">Benar</label>
                        </div>
                        <div class="flex items-start mb-2">
                            <input type="radio" name="question_<?php echo $question['question_id']; ?>" value="0" id="false_choice_<?php echo $question['question_id']; ?>" required
                                   class="mt-1 h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500">
                            <label for="false_choice_<?php echo $question['question_id']; ?>" class="ml-3 block text-gray-700">Salah</label>
                        </div>
                    <?php elseif ($question['question_type'] === 'short_answer'): ?>
                        <div>
                            <label for="answer_text_<?php echo $question['question_id']; ?>" class="block text-sm font-medium text-gray-700 mb-2">Jawaban Anda</label>
                            <textarea name="answer_text_<?php echo $question['question_id']; ?>" id="answer_text_<?php echo $question['question_id']; ?>" rows="3" required
                                      class="w-full px-4 py-2 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none"></textarea>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                <div class="flex items-center space-x-4">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">
                        Submit Kuis
                    </button>
                    <a href="index.php?page=education&action=view&id=<?php echo $moduleId; ?>" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-xl transition-colors">
                        Batalkan
                    </a>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php elseif ($action === 'take' || $action === 'random'): ?>
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
<?php } ?>
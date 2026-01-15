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

// Get the question to show its details before deletion
$stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE question_id = ?");
$stmt->execute([$question_id]);
$question = $stmt->fetch();

if (!$question) {
    setAlert('error', 'Pertanyaan tidak ditemukan!');
    redirect('index.php?page=games');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete the question (this will also delete choices due to foreign key constraint)
        $stmt = $pdo->prepare("DELETE FROM quiz_questions WHERE question_id = ?");
        $stmt->execute([$question_id]);
        
        // Commit transaction
        $pdo->commit();
        
        logActivity($_SESSION['user_id'], 'delete_quiz_question', "Deleted quiz question: {$question['question_text']}");
        setAlert('success', 'Pertanyaan kuis berhasil dihapus!');
        redirect('index.php?page=games');
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollback();
        $error = 'Gagal menghapus pertanyaan kuis: ' . $e->getMessage();
    }
}
?>

<div class="mb-6">
    <div class="flex items-center space-x-4 mb-4">
        <a href="index.php?page=games" class="text-gray-600 hover:text-gray-800">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Hapus Pertanyaan Kuis</h1>
            <p class="text-gray-500 mt-1">Konfirmasi penghapusan pertanyaan</p>
        </div>
    </div>
</div>

<div class="bg-white rounded-2xl shadow-sm p-6">
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
        <div class="flex">
            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
            <h3 class="ml-3 text-sm font-medium text-yellow-800">
                Peringatan Penghapusan
            </h3>
        </div>
        <div class="mt-2 ml-8 text-sm text-yellow-700">
            <p>Anda akan menghapus pertanyaan kuis berikut:</p>
        </div>
    </div>

    <div class="border border-gray-200 rounded-xl p-4 mb-6">
        <h3 class="font-bold text-gray-800 mb-2">Pertanyaan:</h3>
        <p class="text-gray-700"><?php echo htmlspecialchars($question['question_text']); ?></p>
        
        <div class="mt-3 grid grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-500">Kategori:</p>
                <p class="font-medium"><?php echo htmlspecialchars($question['category']); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Kesulitan:</p>
                <p class="font-medium"><?php echo ucfirst($question['difficulty']); ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Hadiah XP:</p>
                <p class="font-medium"><?php echo $question['xp_reward']; ?> XP</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Hadiah Poin:</p>
                <p class="font-medium"><?php echo $question['point_reward']; ?> Poin</p>
            </div>
        </div>
    </div>

    <form method="POST" class="flex space-x-3">
        <input type="hidden" name="confirm_delete" value="1">
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-6 rounded-xl transition-colors">
            Ya, Hapus Sekarang
        </button>
        <a href="index.php?page=games" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-6 rounded-xl transition-colors inline-block">
            Batal
        </a>
    </form>
</div>
</div>
<?php
// Check if user is accessing with admin privileges for managing challenges
$action = isset($_GET['action']) ? $_GET['action'] : 'view';
$challengeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (hasRole(['admin', 'manager'])) {
    // Admin/Manager functionality
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'create' || $action === 'edit') {
            $title = cleanInput($_POST['title']);
            $description = cleanInput($_POST['description']);
            $xp_reward = (int)$_POST['xp_reward'];
            $point_reward = (int)$_POST['point_reward'];
            $difficulty = cleanInput($_POST['difficulty']);
            $challenge_type = cleanInput($_POST['challenge_type']);
            $start_date = cleanInput($_POST['start_date']);
            $end_date = cleanInput($_POST['end_date']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if ($action === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO challenges (title, description, xp_reward, point_reward, difficulty, challenge_type, start_date, end_date, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $xp_reward, $point_reward, $difficulty, $challenge_type, $start_date, $end_date, $is_active]);
                setAlert('success', 'Tantangan berhasil ditambahkan');
            } elseif ($action === 'edit' && $challengeId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE challenges
                    SET title = ?, description = ?, xp_reward = ?, point_reward = ?, difficulty = ?, challenge_type = ?, start_date = ?, end_date = ?, is_active = ?, updated_at = NOW()
                    WHERE challenge_id = ?
                ");
                $stmt->execute([$title, $description, $xp_reward, $point_reward, $difficulty, $challenge_type, $start_date, $end_date, $is_active, $challengeId]);
                setAlert('success', 'Tantangan berhasil diperbarui');
            }

            redirect('index.php?page=challenges');
        } elseif ($action === 'delete' && $challengeId > 0) {
            $stmt = $pdo->prepare("DELETE FROM challenges WHERE challenge_id = ?");
            $stmt->execute([$challengeId]);
            setAlert('success', 'Tantangan berhasil dihapus');
            redirect('index.php?page=challenges');
        } elseif ($action === 'verify' && $challengeId > 0) {
            // Admin is verifying a submitted challenge
            $submissionId = (int)$_POST['submission_id'];

            // Verify the submission and award points
            $stmt = $pdo->prepare("
                UPDATE user_progress
                SET is_verified = 1
                WHERE progress_id = ? AND item_id = ? AND item_type = 'challenge' AND is_verified = 0
            ");
            $result = $stmt->execute([$submissionId, $challengeId]);

            if ($stmt->rowCount() > 0) {
                // Get challenge details to award correct points
                $stmtChallenge = $pdo->prepare("
                    SELECT xp_reward, point_reward
                    FROM challenges
                    WHERE challenge_id = ?
                ");
                $stmtChallenge->execute([$challengeId]);
                $challengeRewards = $stmtChallenge->fetch();

                if ($challengeRewards) {
                    // Update user's XP and points directly
                    $stmtUpdate = $pdo->prepare("
                        UPDATE users
                        SET total_xp = total_xp + ?, total_points = total_points + ?
                        WHERE id = (SELECT user_id FROM user_progress WHERE progress_id = ?)
                    ");
                    $stmtUpdate->execute([
                        $challengeRewards['xp_reward'],
                        $challengeRewards['point_reward'],
                        $submissionId
                    ]);

                    // Update user's level based on new XP
                    $stmtUserId = $pdo->prepare("SELECT user_id FROM user_progress WHERE progress_id = ?");
                    $stmtUserId->execute([$submissionId]);
                    $userId = $stmtUserId->fetch()['user_id'];
                    updateUserLevel($userId);

                    setAlert('success', 'Tantangan berhasil diverifikasi dan poin telah diberikan.');
                }
            } else {
                setAlert('error', 'Gagal memverifikasi tantangan. Data mungkin sudah diproses sebelumnya.');
            }

            redirect('index.php?page=challenges');
        }
    }

    if ($action === 'edit' && $challengeId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM challenges WHERE challenge_id = ?");
        $stmt->execute([$challengeId]);
        $challenge = $stmt->fetch();
    }
}

// For regular users - submit challenges
if ($action === 'submit' && $challengeId > 0 && !hasRole(['admin', 'manager'])) {
    // Check if user has already submitted this challenge
    $stmt = $pdo->prepare("
        SELECT progress_id FROM user_progress
        WHERE user_id = ? AND item_id = ? AND item_type = 'challenge'
    ");
    $stmt->execute([$_SESSION['user_id'], $challengeId]);
    $existingProgress = $stmt->fetch();

    if (!$existingProgress) {
        // Get current challenge details to verify if it's active and within dates
        $stmtChallenge = $pdo->prepare("SELECT * FROM challenges WHERE challenge_id = ?");
        $stmtChallenge->execute([$challengeId]);
        $challengeDetails = $stmtChallenge->fetch();

        $now = new DateTime();
        $startDate = $challengeDetails['start_date'] ? new DateTime($challengeDetails['start_date']) : $now;
        $endDate = $challengeDetails['end_date'] ? new DateTime($challengeDetails['end_date']) : $now;

        if ($challengeDetails['is_active'] && $now >= $startDate && $now <= $endDate) {
            // Get submission data
            $submission_text = cleanInput($_POST['submission_text'] ?? '');
            $submission_image = null;

            // Handle image upload if provided
            if (isset($_FILES['submission_image']) && $_FILES['submission_image']['error'] === UPLOAD_ERR_OK) {
                $submission_image = uploadImage($_FILES['submission_image'], 0, 'submissions');
            }

            // Insert the submission for verification
            $stmt = $pdo->prepare("
                INSERT INTO user_progress (user_id, item_id, item_type, is_verified, submission_text, submission_image)
                VALUES (?, ?, 'challenge', 0, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $challengeId, $submission_text, $submission_image]);

            setAlert('success', 'Tantangan berhasil dikirim untuk verifikasi!');
        } else {
            setAlert('error', 'Tantangan ini tidak aktif atau sudah melewati batas waktu.');
        }
    } else {
        setAlert('warning', 'Kamu sudah mengirimkan tantangan ini sebelumnya.');
    }

    redirect('index.php?page=challenges');
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Tantangan</h1>
    <p class="text-gray-500 mt-1">Selesaikan tantangan untuk mendapatkan XP dan poin</p>
</div>

<?php if (hasRole(['admin', 'manager']) && ($action === 'create' || $action === 'edit')): ?>
<!-- Create/Edit Form for Admin/Manager -->
<div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo $action === 'create' ? 'Tambah Tantangan Baru' : 'Edit Tantangan'; ?></h3>
    
    <form method="POST">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Judul Tantangan</label>
                <input type="text" name="title" required
                       value="<?php echo isset($challenge) ? htmlspecialchars($challenge['title']) : (isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''); ?>"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi Tantangan</label>
                <textarea name="description" rows="4" required
                          class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"><?php echo isset($challenge) ? htmlspecialchars($challenge['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''); ?></textarea>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Hadiah XP</label>
                <input type="number" name="xp_reward" min="0" required
                       value="<?php echo isset($challenge) ? $challenge['xp_reward'] : (isset($_POST['xp_reward']) ? $_POST['xp_reward'] : 20); ?>"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Hadiah Poin</label>
                <input type="number" name="point_reward" min="0" required
                       value="<?php echo isset($challenge) ? $challenge['point_reward'] : (isset($_POST['point_reward']) ? $_POST['point_reward'] : 10); ?>"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tingkat Kesulitan</label>
                <select name="difficulty" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                    <option value="easy" <?php echo (isset($challenge) && $challenge['difficulty'] === 'easy') || (isset($_POST['difficulty']) && $_POST['difficulty'] === 'easy') ? 'selected' : ''; ?>>Mudah</option>
                    <option value="medium" <?php echo (isset($challenge) && $challenge['difficulty'] === 'medium') || (isset($_POST['difficulty']) && $_POST['difficulty'] === 'medium') ? 'selected' : ''; ?>>Sedang</option>
                    <option value="hard" <?php echo (isset($challenge) && $challenge['difficulty'] === 'hard') || (isset($_POST['difficulty']) && $_POST['difficulty'] === 'hard') ? 'selected' : ''; ?>>Sulit</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Tantangan</label>
                <select name="challenge_type" class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                    <option value="daily" <?php echo (isset($challenge) && $challenge['challenge_type'] === 'daily') || (isset($_POST['challenge_type']) && $_POST['challenge_type'] === 'daily') ? 'selected' : ''; ?>>Harian</option>
                    <option value="weekly" <?php echo (isset($challenge) && $challenge['challenge_type'] === 'weekly') || (isset($_POST['challenge_type']) && $_POST['challenge_type'] === 'weekly') ? 'selected' : ''; ?>>Mingguan</option>
                    <option value="special" <?php echo (isset($challenge) && $challenge['challenge_type'] === 'special') || (isset($_POST['challenge_type']) && $_POST['challenge_type'] === 'special') ? 'selected' : ''; ?>>Spesial</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
                <input type="date" name="start_date"
                       value="<?php echo isset($challenge) ? $challenge['start_date'] : (isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d')); ?>"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Selesai</label>
                <input type="date" name="end_date"
                       value="<?php echo isset($challenge) ? $challenge['end_date'] : (isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d', strtotime('+7 days'))); ?>"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
            
            <div class="md:col-span-2 flex items-center">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" 
                           <?php echo (isset($challenge) && $challenge['is_active']) || (isset($_POST['is_active']) && $_POST['is_active']) ? 'checked' : ''; ?>
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700">Aktifkan Tantangan</span>
                </label>
            </div>
        </div>
        
        <div class="flex items-center space-x-4 mt-6">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">
                <?php echo $action === 'create' ? 'Tambah Tantangan' : 'Perbarui Tantangan'; ?>
            </button>
            <a href="index.php?page=challenges" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-xl transition-colors">
                Batal
            </a>
        </div>
    </form>
</div>

<?php elseif ($action === 'submit' && $challengeId > 0 && !hasRole(['admin', 'manager'])): ?>
<!-- Submit Challenge Form -->
<?php 
$stmt = $pdo->prepare("SELECT * FROM challenges WHERE challenge_id = ? AND is_active = 1");
$stmt->execute([$challengeId]);
$challenge = $stmt->fetch();

if (!$challenge) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4">Tantangan tidak ditemukan atau tidak aktif.</div>';
    echo '<a href="index.php?page=challenges" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">Kembali ke Daftar Tantangan</a>';
} else {
    // Check if user has already submitted this challenge
    $stmtSubmitted = $pdo->prepare("
        SELECT progress_id FROM user_progress 
        WHERE user_id = ? AND item_id = ? AND item_type = 'challenge'
    ");
    $stmtSubmitted->execute([$_SESSION['user_id'], $challengeId]);
    $hasSubmitted = $stmtSubmitted->fetch() ? true : false;
    
    if ($hasSubmitted) {
        echo '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-xl mb-4">Kamu sudah mengirimkan tantangan ini sebelumnya.</div>';
        echo '<a href="index.php?page=challenges" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">Kembali ke Daftar Tantangan</a>';
    } else {
        $now = new DateTime();
        $startDate = $challenge['start_date'] ? new DateTime($challenge['start_date']) : $now;
        $endDate = $challenge['end_date'] ? new DateTime($challenge['end_date']) : $now;
        
        if ($now < $startDate) {
            echo '<div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-xl mb-4">Tantangan ini belum mulai. Tanggal mulai: ' . formatDate($challenge['start_date']) . '</div>';
            echo '<a href="index.php?page=challenges" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">Kembali ke Daftar Tantangan</a>';
        } elseif ($now > $endDate) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4">Tantangan ini sudah berakhir. Tanggal berakhir: ' . formatDate($challenge['end_date']) . '</div>';
            echo '<a href="index.php?page=challenges" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">Kembali ke Daftar Tantangan</a>';
        } else {
?>
    <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
        <div class="mb-4">
            <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?php echo ucfirst($challenge['challenge_type']); ?></span>
            <h1 class="text-2xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($challenge['title']); ?></h1>
            <p class="text-gray-500 mt-2">Dapatkan <?php echo $challenge['xp_reward']; ?> XP dan <?php echo $challenge['point_reward']; ?> poin</p>
        </div>
        
        <div class="border-t border-gray-200 pt-4 mb-6">
            <h3 class="font-bold text-gray-800 mb-2">Deskripsi Tantangan</h3>
            <p class="text-gray-600"><?php echo htmlspecialchars($challenge['description']); ?></p>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Jelaskan bagaimana kamu menyelesaikan tantangan ini:</label>
                <textarea name="submission_text" rows="4" required
                          class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                          placeholder="Contoh: Saya memilah sampah plastik dan organik di rumah saya hari ini..."></textarea>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Unggah bukti (opsional):</label>
                <input type="file" name="submission_image" accept="image/*"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG, maksimal 5MB</p>
            </div>
            
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="id" value="<?php echo $challenge['challenge_id']; ?>">
            
            <div class="flex items-center space-x-4">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">
                    Kirim Tantangan
                </button>
                <a href="index.php?page=challenges" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-xl transition-colors">
                    Batal
                </a>
            </div>
        </form>
    </div>
<?php
        }
    }
}
?>

<?php elseif ($action === 'view' && $challengeId > 0): ?>
<!-- View Challenge Detail -->
<?php 
$stmt = $pdo->prepare("SELECT * FROM challenges WHERE challenge_id = ? AND is_active = 1");
$stmt->execute([$challengeId]);
$challenge = $stmt->fetch();

if (!$challenge) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4">Tantangan tidak ditemukan atau tidak aktif.</div>';
    echo '<a href="index.php?page=challenges" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">Kembali ke Daftar Tantangan</a>';
} else {
    // Check if user has submitted this challenge
    $stmtSubmitted = $pdo->prepare("
        SELECT progress_id, is_verified FROM user_progress 
        WHERE user_id = ? AND item_id = ? AND item_type = 'challenge'
    ");
    $stmtSubmitted->execute([$_SESSION['user_id'], $challengeId]);
    $submission = $stmtSubmitted->fetch();
    
    $now = new DateTime();
    $startDate = $challenge['start_date'] ? new DateTime($challenge['start_date']) : $now;
    $endDate = $challenge['end_date'] ? new DateTime($challenge['end_date']) : $now;
    $isActive = ($now >= $startDate && $now <= $endDate && $challenge['is_active']);
?>
    <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
        <div class="flex justify-between items-start mb-4">
            <div>
                <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?php echo ucfirst($challenge['challenge_type']); ?></span>
                <h1 class="text-2xl font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($challenge['title']); ?></h1>
                <div class="flex items-center text-sm text-gray-500 mt-2">
                    <?php if ($challenge['start_date'] && $challenge['end_date']): ?>
                        <span><?php echo formatDate($challenge['start_date']); ?> - <?php echo formatDate($challenge['end_date']); ?></span>
                    <?php else: ?>
                        <span>Tanggal tidak terbatas</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold text-green-600">+<?php echo $challenge['xp_reward']; ?> XP</div>
                <div class="text-sm text-gray-500">+<?php echo $challenge['point_reward']; ?> Poin</div>
            </div>
        </div>
        
        <div class="border-t border-gray-200 pt-4 mb-6">
            <h3 class="font-bold text-gray-800 mb-2">Deskripsi Tantangan</h3>
            <p class="text-gray-600"><?php echo htmlspecialchars($challenge['description']); ?></p>
        </div>
        
        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
            <div class="flex items-center space-x-4">
                <?php if (!$submission && $isActive && !hasRole(['admin', 'manager'])): ?>
                    <a href="index.php?page=challenges&action=submit&id=<?php echo $challenge['challenge_id']; ?>" 
                       class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">
                        Ikuti Tantangan
                    </a>
                <?php elseif ($submission && !hasRole(['admin', 'manager'])): ?>
                    <?php if ($submission['is_verified']): ?>
                        <span class="bg-green-100 text-green-800 px-4 py-2 rounded-xl font-medium">✓ Terverifikasi</span>
                    <?php else: ?>
                        <span class="bg-yellow-100 text-yellow-800 px-4 py-2 rounded-xl font-medium">Dikirim - Menunggu Verifikasi</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (hasRole(['admin', 'manager'])): ?>
                    <a href="index.php?page=challenges&action=edit&id=<?php echo $challenge['challenge_id']; ?>" 
                       class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors">
                        Edit
                    </a>
                    <a href="index.php?page=challenges&action=delete&id=<?php echo $challenge['challenge_id']; ?>" 
                       class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors"
                       onclick="return confirmDelete('Apakah kamu yakin ingin menghapus tantangan ini?')">
                        Hapus
                    </a>
                <?php endif; ?>
            </div>
            <a href="index.php?page=challenges" class="text-blue-600 hover:text-blue-800 font-medium">‹ Kembali ke Daftar</a>
        </div>
    </div>
<?php } ?>

<?php else: ?>
<!-- List All Challenges -->
<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Daftar Tantangan</h2>
        <p class="text-gray-500">Tantangan harian dan spesial untuk meningkatkan kebersihan lingkungan</p>
    </div>
    <?php if (hasRole(['admin', 'manager'])): ?>
    <a href="index.php?page=challenges&action=create" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        Tambah Tantangan
    </a>
    <?php endif; ?>
</div>

<?php if (hasRole(['admin', 'manager'])): ?>
    <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-xl mb-6">
        <p>Anda sedang dalam mode manajemen. Anda dapat melihat semua tantangan termasuk yang tidak aktif.</p>
    </div>

    <!-- Admin Section: Pending Submissions -->
    <div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Tantangan Menunggu Verifikasi</h3>

        <?php
        $stmtPending = $pdo->query("
            SELECT up.progress_id, up.user_id, up.item_id, up.submission_text, up.submission_image, up.completed_at,
                   u.username, u.first_name, u.last_name, c.title as challenge_title
            FROM user_progress up
            JOIN users u ON up.user_id = u.id
            JOIN challenges c ON up.item_id = c.challenge_id
            WHERE up.item_type = 'challenge' AND up.is_verified = 0
            ORDER BY up.completed_at DESC
            LIMIT 20
        ");
        $pendingSubmissions = $stmtPending->fetchAll();
        ?>

        <?php if (count($pendingSubmissions) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pengguna</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tantangan</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bukti</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($pendingSubmissions as $submission): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']); ?></div>
                            <div class="text-sm text-gray-500">@<?php echo htmlspecialchars($submission['username']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($submission['challenge_title']); ?></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo formatDate($submission['completed_at']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php if (!empty($submission['submission_image'])): ?>
                                <a href="<?php echo getImageUrl($submission['submission_image'], 'submissions'); ?>"
                                   target="_blank"
                                   class="text-blue-600 hover:text-blue-800">Lihat Bukti</a>
                            <?php else: ?>
                                <span class="text-gray-500">Tidak ada bukti</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <form method="POST" class="inline"
                                  onsubmit="return confirm('Verifikasi tantangan ini dan berikan poin kepada pengguna?')">
                                <input type="hidden" name="action" value="verify">
                                <input type="hidden" name="id" value="<?php echo $submission['item_id']; ?>">
                                <input type="hidden" name="submission_id" value="<?php echo $submission['progress_id']; ?>">
                                <button type="submit"
                                        class="bg-green-600 hover:bg-green-700 text-white font-medium py-1 px-3 rounded-lg text-sm">
                                    Verifikasi
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-gray-500 text-center py-4">Tidak ada tantangan menunggu verifikasi.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php
    $query = hasRole(['admin', 'manager']) 
        ? "SELECT * FROM challenges ORDER BY created_at DESC" 
        : "SELECT * FROM challenges WHERE is_active = 1 ORDER BY created_at DESC";
    $stmt = $pdo->query($query);
    $challenges = $stmt->fetchAll();
    
    foreach ($challenges as $challenge):
        // Check if user has submitted this challenge
        $stmtSubmitted = $pdo->prepare("
            SELECT progress_id, is_verified FROM user_progress 
            WHERE user_id = ? AND item_id = ? AND item_type = 'challenge'
        ");
        $stmtSubmitted->execute([$_SESSION['user_id'], $challenge['challenge_id']]);
        $submission = $stmtSubmitted->fetch();
        
        $now = new DateTime();
        $startDate = $challenge['start_date'] ? new DateTime($challenge['start_date']) : $now;
        $endDate = $challenge['end_date'] ? new DateTime($challenge['end_date']) : $now;
        $isActive = ($now >= $startDate && $now <= $endDate && $challenge['is_active']);
    ?>
    <div class="bg-white rounded-2xl shadow-sm p-6 border <?php echo $submission ? ($submission['is_verified'] ? 'border-green-200 bg-green-50' : 'border-yellow-200 bg-yellow-50') : 'border-gray-200'; ?>">
        <?php if (!$challenge['is_active']): ?>
        <div class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full mb-2 inline-block">Tidak Aktif</div>
        <?php elseif (!$isActive): ?>
        <div class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full mb-2 inline-block">
            <?php echo $now < $startDate ? 'Belum Mulai' : 'Sudah Berakhir'; ?>
        </div>
        <?php endif; ?>
        
        <div class="flex justify-between items-start">
            <div>
                <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?php echo ucfirst($challenge['challenge_type']); ?></span>
                <h3 class="font-bold text-gray-800 mt-2"><?php echo htmlspecialchars($challenge['title']); ?></h3>
                <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars(substr($challenge['description'], 0, 100)); ?>...</p>
            </div>
            <div class="text-right">
                <div class="text-lg font-bold text-green-600">+<?php echo $challenge['xp_reward']; ?> XP</div>
                <div class="text-sm text-gray-500">+<?php echo $challenge['point_reward']; ?> Pts</div>
            </div>
        </div>
        
        <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
            <div class="text-sm text-gray-500">
                <?php if ($challenge['start_date'] && $challenge['end_date']): ?>
                    <span><?php echo formatDate($challenge['start_date']); ?></span>
                    <span class="mx-1">-</span>
                    <span><?php echo formatDate($challenge['end_date']); ?></span>
                <?php else: ?>
                    <span>Tanggal tidak terbatas</span>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center space-x-2">
                <?php if ($submission): ?>
                    <?php if ($submission['is_verified']): ?>
                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">✓ Terverifikasi</span>
                    <?php else: ?>
                        <span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">Dikirim</span>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="index.php?page=challenges&action=view&id=<?php echo $challenge['challenge_id']; ?>" class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                    <?php echo $submission ? 'Detail' : 'Ikuti'; ?> →
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (count($challenges) == 0): ?>
    <div class="col-span-full text-center py-12">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
        </svg>
        <h3 class="text-lg font-medium text-gray-500">Belum ada tantangan</h3>
        <p class="text-gray-400 mt-1">
            <?php echo hasRole(['admin', 'manager']) ? 'Tambahkan tantangan pertama Anda.' : 'Belum ada tantangan yang tersedia saat ini.'; ?>
        </p>
        <?php if (hasRole(['admin', 'manager'])): ?>
        <a href="index.php?page=challenges&action=create" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors">
            Tambah Tantangan
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
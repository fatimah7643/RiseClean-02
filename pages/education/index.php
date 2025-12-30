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

<?php elseif ($action === 'view' && $moduleId > 0): ?>
<!-- View Module Detail -->
<?php 
$stmt = $pdo->prepare("SELECT * FROM education_modules WHERE module_id = ? AND is_active = 1");
$stmt->execute([$moduleId]);
$module = $stmt->fetch();

if (!$module) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4">Modul tidak ditemukan atau tidak aktif.</div>';
    echo '<a href="index.php?page=education" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">Kembali ke Daftar Modul</a>';
} else {
    // Check if user has completed this module
    $stmtCompleted = $pdo->prepare("
        SELECT progress_id FROM user_progress 
        WHERE user_id = ? AND item_id = ? AND item_type = 'module'
    ");
    $stmtCompleted->execute([$_SESSION['user_id'], $moduleId]);
    $isCompleted = $stmtCompleted->fetch() ? true : false;
?>
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
                <?php if (!$isCompleted && !hasRole(['admin', 'manager'])): ?>
                    <a href="index.php?page=education&action=complete&id=<?php echo $module['module_id']; ?>" 
                       class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors"
                       onclick="return confirm('Apakah kamu yakin sudah menyelesaikan modul ini?')">
                        Tandai Selesai
                    </a>
                <?php elseif ($isCompleted && !hasRole(['admin', 'manager'])): ?>
                    <span class="bg-green-100 text-green-800 px-4 py-2 rounded-xl font-medium">✓ Sudah Selesai</span>
                <?php endif; ?>
                
                <?php if (hasRole(['admin', 'manager'])): ?>
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
<?php } ?>

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
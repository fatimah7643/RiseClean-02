<?php
// Check if user is accessing with admin privileges for managing rewards
$action = isset($_GET['action']) ? $_GET['action'] : 'view';
$rewardId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (hasRole(['admin', 'manager'])) {
    // Admin/Manager functionality
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'create' || $action === 'edit') {
            $reward_name = cleanInput($_POST['reward_name']);
            $point_cost = (int)$_POST['point_cost'];
            $description = cleanInput($_POST['description']);
            $stock = (int)$_POST['stock'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Handle image upload if provided
            $image = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image = uploadImage($_FILES['image'], 0, 'rewards');
            } else {
                $image = cleanInput($_POST['existing_image'] ?? '');
            }

            if ($action === 'create') {
                $stmt = $pdo->prepare("
                    INSERT INTO rewards (reward_name, point_cost, description, image, stock, is_active)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$reward_name, $point_cost, $description, $image, $stock, $is_active]);
                setAlert('success', 'Hadiah berhasil ditambahkan');
            } elseif ($action === 'edit' && $rewardId > 0) {
                $stmt = $pdo->prepare("
                    UPDATE rewards 
                    SET reward_name = ?, point_cost = ?, description = ?, image = ?, stock = ?, is_active = ?, updated_at = NOW()
                    WHERE reward_id = ?
                ");
                $stmt->execute([$reward_name, $point_cost, $description, $image, $stock, $is_active, $rewardId]);
                setAlert('success', 'Hadiah berhasil diperbarui');
            }
            
            redirect('index.php?page=rewards');
        } elseif ($action === 'delete' && $rewardId > 0) {
            // Delete associated image file if exists
            $stmt = $pdo->prepare("SELECT image FROM rewards WHERE reward_id = ?");
            $stmt->execute([$rewardId]);
            $reward = $stmt->fetch();
            
            if ($reward && !empty($reward['image'])) {
                deleteImage($reward['image'], 'rewards');
            }
            
            $stmt = $pdo->prepare("DELETE FROM rewards WHERE reward_id = ?");
            $stmt->execute([$rewardId]);
            setAlert('success', 'Hadiah berhasil dihapus');
            redirect('index.php?page=rewards');
        }
    }

    if ($action === 'edit' && $rewardId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM rewards WHERE reward_id = ?");
        $stmt->execute([$rewardId]);
        $reward = $stmt->fetch();
    }
}

// For regular users - redeem rewards
if ($action === 'redeem' && $rewardId > 0 && !hasRole(['admin', 'manager'])) {
    // Get reward details
    $stmt = $pdo->prepare("SELECT * FROM rewards WHERE reward_id = ?");
    $stmt->execute([$rewardId]);
    $reward = $stmt->fetch();
    
    if ($reward && $reward['is_active'] && $reward['stock'] > 0) {
        // Check if user has enough points
        if ($currentUser['total_points'] >= $reward['point_cost']) {
            // Check if user has already claimed this reward recently (to prevent spam)
            $stmtCheck = $pdo->prepare("
                SELECT COUNT(*) as count FROM user_rewards 
                WHERE user_id = ? AND reward_id = ?
            ");
            $stmtCheck->execute([$_SESSION['user_id'], $rewardId]);
            $alreadyClaimed = $stmtCheck->fetch()['count'];
            
            if ($alreadyClaimed === 0) {
                try {
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    // Reduce reward stock
                    $stmt = $pdo->prepare("UPDATE rewards SET stock = stock - 1 WHERE reward_id = ? AND stock > 0");
                    $stmt->execute([$rewardId]);
                    $affectedRows = $stmt->rowCount();
                    
                    if ($affectedRows > 0) {
                        // Deduct user points
                        $stmt = $pdo->prepare("UPDATE users SET total_points = total_points - ? WHERE id = ?");
                        $stmt->execute([$reward['point_cost'], $_SESSION['user_id']]);
                        
                        // Record the reward claim
                        $stmt = $pdo->prepare("
                            INSERT INTO user_rewards (user_id, reward_id, quantity, claimed_at)
                            VALUES (?, ?, 1, NOW())
                        ");
                        $stmt->execute([$_SESSION['user_id'], $rewardId]);
                        
                        // Log activity
                        logActivity(
                            $_SESSION['user_id'], 
                            'reward_claim', 
                            'User claimed reward: ' . $reward['reward_name']
                        );
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        setAlert('success', 'Hadiah berhasil ditukarkan! Poinmu telah dikurangi.');
                    } else {
                        // Rollback if no stock available
                        $pdo->rollback();
                        setAlert('error', 'Maaf, hadiah ini sudah habis stoknya.');
                    }
                } catch (Exception $e) {
                    // Rollback on error
                    $pdo->rollback();
                    setAlert('error', 'Terjadi kesalahan saat menukarkan hadiah. Silakan coba lagi.');
                }
            } else {
                setAlert('warning', 'Kamu sudah pernah menukarkan hadiah ini sebelumnya.');
            }
        } else {
            setAlert('error', 'Poinmu tidak cukup untuk menukarkan hadiah ini.');
        }
    } else {
        setAlert('error', 'Hadiah tidak tersedia atau stok habis.');
    }
    
    redirect('index.php?page=rewards');
}
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Hadiah & Tukar Poin</h1>
    <p class="text-gray-500 mt-1">Tukarkan poinmu dengan hadiah keren</p>
</div>

<?php if (hasRole(['admin', 'manager']) && ($action === 'create' || $action === 'edit')): ?>
<!-- Create/Edit Form for Admin/Manager -->
<div class="bg-white rounded-2xl shadow-sm p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><?php echo $action === 'create' ? 'Tambah Hadiah Baru' : 'Edit Hadiah'; ?></h3>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Nama Hadiah</label>
                <input type="text" name="reward_name" required
                       value="<?php echo isset($reward) ? htmlspecialchars($reward['reward_name']) : (isset($_POST['reward_name']) ? htmlspecialchars($_POST['reward_name']) : ''); ?>"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Biaya Poin</label>
                <input type="number" name="point_cost" min="1" required
                       value="<?php echo isset($reward) ? $reward['point_cost'] : (isset($_POST['point_cost']) ? $_POST['point_cost'] : 100); ?>"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Stok Tersedia</label>
                <input type="number" name="stock" min="0" required
                       value="<?php echo isset($reward) ? $reward['stock'] : (isset($_POST['stock']) ? $_POST['stock'] : 10); ?>"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                <textarea name="description" rows="3"
                          class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"><?php echo isset($reward) ? htmlspecialchars($reward['description']) : (isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''); ?></textarea>
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Gambar Hadiah</label>
                <?php if (isset($reward) && !empty($reward['image'])): ?>
                    <div class="mb-2">
                        <img src="<?php echo getImageUrl($reward['image'], 'rewards'); ?>" class="w-24 h-24 object-cover rounded-lg border">
                        <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($reward['image']); ?>">
                    </div>
                <?php endif; ?>
                <input type="file" name="image" accept="image/*"
                       class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                <p class="text-xs text-gray-500 mt-1">Format: JPG, PNG, maksimal 5MB</p>
            </div>
            
            <div class="md:col-span-2 flex items-center">
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" 
                           <?php echo (isset($reward) && $reward['is_active']) || (isset($_POST['is_active']) && $_POST['is_active']) ? 'checked' : ''; ?>
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700">Aktifkan Hadiah</span>
                </label>
            </div>
        </div>
        
        <div class="flex items-center space-x-4 mt-6">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-xl transition-colors">
                <?php echo $action === 'create' ? 'Tambah Hadiah' : 'Perbarui Hadiah'; ?>
            </button>
            <a href="index.php?page=rewards" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-xl transition-colors">
                Batal
            </a>
        </div>
    </form>
</div>

<?php else: ?>
<!-- User Rewards Catalog -->
<div class="flex justify-between items-center mb-6">
    <div>
        <h2 class="text-xl font-bold text-gray-800">Katalog Hadiah</h2>
        <p class="text-gray-500">Poin kamu: <span class="font-semibold text-green-600"><?php echo number_format($currentUser['total_points']); ?></span></p>
    </div>
    <?php if (hasRole(['admin', 'manager'])): ?>
    <a href="index.php?page=rewards&action=create" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors flex items-center">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
        </svg>
        Tambah Hadiah
    </a>
    <?php endif; ?>
</div>

<?php if (hasRole(['admin', 'manager'])): ?>
    <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-xl mb-6">
        <p>Anda sedang dalam mode manajemen. Anda dapat melihat semua hadiah termasuk yang tidak aktif.</p>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php
    $query = hasRole(['admin', 'manager']) 
        ? "SELECT * FROM rewards ORDER BY point_cost ASC" 
        : "SELECT * FROM rewards WHERE is_active = 1 AND stock > 0 ORDER BY point_cost ASC";
    $stmt = $pdo->query($query);
    $rewards = $stmt->fetchAll();
    
    foreach ($rewards as $reward):
        $canAfford = $currentUser['total_points'] >= $reward['point_cost'];
        $isAvailable = $reward['stock'] > 0;
    ?>
    <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-200">
        <?php if (!$reward['is_active']): ?>
        <div class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full mb-2 inline-block">Tidak Aktif</div>
        <?php endif; ?>
        
        <div class="flex justify-center mb-4">
            <?php if (!empty($reward['image'])): ?>
                <img src="<?php echo getImageUrl($reward['image'], 'rewards'); ?>" class="w-32 h-32 object-cover rounded-xl border" alt="<?php echo htmlspecialchars($reward['reward_name']); ?>">
            <?php else: ?>
                <div class="w-32 h-32 bg-gray-200 rounded-xl flex items-center justify-center border-2 border-dashed border-gray-300">
                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center">
            <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($reward['reward_name']); ?></h3>
            <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars(substr($reward['description'], 0, 60)); ?>...</p>
            
            <div class="mt-4">
                <div class="text-2xl font-bold text-green-600"><?php echo number_format($reward['point_cost']); ?> Poin</div>
                <div class="text-sm text-gray-500">Stok: <?php echo $reward['stock']; ?></div>
            </div>
            
            <div class="mt-4">
                <?php if ($isAvailable && $canAfford && !hasRole(['admin', 'manager'])): ?>
                    <a href="index.php?page=rewards&action=redeem&id=<?php echo $reward['reward_id']; ?>" 
                       class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors block"
                       onclick="return confirm('Apakah kamu yakin ingin menukarkan hadiah ini?\n\n<?php echo htmlspecialchars($reward['reward_name']); ?>\nHarga: <?php echo number_format($reward['point_cost']); ?> poin')">
                        Tukarkan
                    </a>
                <?php elseif (!hasRole(['admin', 'manager'])): ?>
                    <?php if (!$isAvailable): ?>
                        <span class="w-full bg-red-100 text-red-800 font-semibold py-2 px-4 rounded-xl block">Habis</span>
                    <?php elseif (!$canAfford): ?>
                        <span class="w-full bg-gray-100 text-gray-800 font-semibold py-2 px-4 rounded-xl block">Tidak Cukup Poin</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if (hasRole(['admin', 'manager'])): ?>
                    <div class="flex space-x-2 mt-2">
                        <a href="index.php?page=rewards&action=edit&id=<?php echo $reward['reward_id']; ?>" 
                           class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-1 px-2 rounded-xl transition-colors text-sm">
                            Edit
                        </a>
                        <a href="index.php?page=rewards&action=delete&id=<?php echo $reward['reward_id']; ?>" 
                           class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold py-1 px-2 rounded-xl transition-colors text-sm"
                           onclick="return confirmDelete('Apakah kamu yakin ingin menghapus hadiah ini?')">
                            Hapus
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if (count($rewards) == 0): ?>
    <div class="col-span-full text-center py-12">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
        </svg>
        <h3 class="text-lg font-medium text-gray-500">Belum ada hadiah</h3>
        <p class="text-gray-400 mt-1">
            <?php echo hasRole(['admin', 'manager']) ? 'Tambahkan hadiah pertama Anda.' : 'Belum ada hadiah yang tersedia saat ini.'; ?>
        </p>
        <?php if (hasRole(['admin', 'manager'])): ?>
        <a href="index.php?page=rewards&action=create" class="inline-block mt-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-xl transition-colors">
            Tambah Hadiah
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- User's Redeemed Rewards -->
<?php if (!hasRole(['admin', 'manager'])): ?>
<div class="mt-8">
    <h2 class="text-xl font-bold text-gray-800 mb-4">Hadiah yang Sudah Ditukarkan</h2>
    
    <?php
    $stmtRedeemed = $pdo->prepare("
        SELECT ur.*, r.reward_name, r.description, r.image, r.point_cost
        FROM user_rewards ur
        JOIN rewards r ON ur.reward_id = r.reward_id
        WHERE ur.user_id = ?
        ORDER BY ur.claimed_at DESC
    ");
    $stmtRedeemed->execute([$_SESSION['user_id']]);
    $redeemedRewards = $stmtRedeemed->fetchAll();
    ?>
    
    <?php if (count($redeemedRewards) > 0): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($redeemedRewards as $redeemed): ?>
        <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-200 flex items-center">
            <?php if (!empty($redeemed['image'])): ?>
                <img src="<?php echo getImageUrl($redeemed['image'], 'rewards'); ?>" class="w-16 h-16 object-cover rounded-lg mr-4" alt="<?php echo htmlspecialchars($redeemed['reward_name']); ?>">
            <?php else: ?>
                <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                    </svg>
                </div>
            <?php endif; ?>
            
            <div class="flex-1">
                <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($redeemed['reward_name']); ?></h4>
                <p class="text-sm text-gray-500">Tanggal: <?php echo formatDate($redeemed['claimed_at']); ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl shadow-sm p-6 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
        </svg>
        <p class="text-gray-500">Kamu belum menukarkan hadiah apapun.</p>
        <p class="text-sm text-gray-400 mt-1">Kumpulkan poin dengan menyelesaikan modul dan tantangan.</p>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<?php
// Get user stats for the current user
$stmt = $pdo->prepare("
    SELECT u.total_xp, u.total_points, u.current_level, l.level_name
    FROM users u
    LEFT JOIN levels l ON u.current_level = l.level_id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$userStats = $stmt->fetch();

// Get next level info if not at max level
$nextLevel = null;
if ($userStats['current_level'] < 8) { // Assuming max level is 8
    $nextLevelStmt = $pdo->prepare("SELECT level_id, level_name, min_xp FROM levels WHERE level_id = ?");
    $nextLevelStmt->execute([$userStats['current_level'] + 1]);
    $nextLevel = $nextLevelStmt->fetch();
}

// Get recent activities for the user
$stmtUserActivities = $pdo->prepare("
    SELECT * FROM activity_logs
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmtUserActivities->execute([$_SESSION['user_id']]);
$userActivities = $stmtUserActivities->fetchAll();

// Get completed modules count
$stmtCompletedModules = $pdo->prepare("
    SELECT COUNT(*) as count FROM user_progress 
    WHERE user_id = ? AND item_type = 'module'
");
$stmtCompletedModules->execute([$_SESSION['user_id']]);
$completedModules = $stmtCompletedModules->fetch()['count'];

// Get completed challenges count
$stmtCompletedChallenges = $pdo->prepare("
    SELECT COUNT(*) as count FROM user_progress 
    WHERE user_id = ? AND item_type = 'challenge'
");
$stmtCompletedChallenges->execute([$_SESSION['user_id']]);
$completedChallenges = $stmtCompletedChallenges->fetch()['count'];

// Get recent completed modules
$stmtRecentModules = $pdo->prepare("
    SELECT em.title, up.completed_at 
    FROM user_progress up
    JOIN education_modules em ON up.item_id = em.module_id
    WHERE up.user_id = ? AND up.item_type = 'module'
    ORDER BY up.completed_at DESC
    LIMIT 3
");
$stmtRecentModules->execute([$_SESSION['user_id']]);
$recentModules = $stmtRecentModules->fetchAll();

// Get recent completed challenges
$stmtRecentChallenges = $pdo->prepare("
    SELECT c.title, up.completed_at 
    FROM user_progress up
    JOIN challenges c ON up.item_id = c.challenge_id
    WHERE up.user_id = ? AND up.item_type = 'challenge'
    ORDER BY up.completed_at DESC
    LIMIT 3
");
$stmtRecentChallenges->execute([$_SESSION['user_id']]);
$recentChallenges = $stmtRecentChallenges->fetchAll();

// Get active daily challenges
$stmtActiveChallenges = $pdo->prepare("
    SELECT challenge_id, title, description, xp_reward, point_reward 
    FROM challenges 
    WHERE is_active = 1 AND challenge_type = 'daily'
    ORDER BY created_at DESC
    LIMIT 3
");
$stmtActiveChallenges->execute();
$activeChallenges = $stmtActiveChallenges->fetchAll();
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Dashboard</h1>
    <p class="text-gray-500 mt-1">Rise to a Cleaner Future! | Selamat datang kembali, <?php echo htmlspecialchars($currentUser['first_name']); ?>!</p>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
    <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-sm p-6 text-white card-hover transition-all">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm opacity-80 font-medium">Total XP</p>
                <p class="text-2xl font-bold mt-1"><?php echo number_format($userStats['total_xp']); ?></p>
                <p class="text-xs opacity-70 mt-2">Pengalaman terkumpul</p>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl shadow-sm p-6 text-white card-hover transition-all">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm opacity-80 font-medium">Total Points</p>
                <p class="text-2xl font-bold mt-1"><?php echo number_format($userStats['total_points']); ?></p>
                <p class="text-xs opacity-70 mt-2">Poin yang terkumpul</p>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl shadow-sm p-6 text-white card-hover transition-all">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm opacity-80 font-medium">Level</p>
                <p class="text-2xl font-bold mt-1"><?php echo $userStats['current_level']; ?></p>
                <p class="text-xs opacity-70 mt-2"><?php echo htmlspecialchars($userStats['level_name']); ?></p>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
        </div>
    </div>

    <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-2xl shadow-sm p-6 text-white card-hover transition-all">
        <div class="flex items-center justify-between">
            <div>
                <?php if ($nextLevel): ?>
                    <p class="text-sm opacity-80 font-medium">Next Level</p>
                    <p class="text-2xl font-bold mt-1"><?php echo $nextLevel['level_name']; ?></p>
                    <div class="w-full bg-white/20 rounded-full h-2 mt-2">
                        <?php 
                        $currentXP = $userStats['total_xp'];
                        $minXPForCurrentLevel = $userStats['current_level'] > 1 ? ($userStats['current_level'] - 1) * 200 : 0; // Simplified calculation
                        $minXPForNextLevel = $nextLevel['min_xp'];
                        $levelRange = $minXPForNextLevel - $minXPForCurrentLevel;
                        $progress = max(0, min(100, (($currentXP - $minXPForCurrentLevel) / $levelRange) * 100));
                        ?>
                        <div class="bg-white h-2 rounded-full" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                <?php else: ?>
                    <p class="text-sm opacity-80 font-medium">Status</p>
                    <p class="text-2xl font-bold mt-1">Max Level</p>
                    <p class="text-xs opacity-70 mt-2">Kamu adalah Planet Protector!</p>
                <?php endif; ?>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Progress Overview -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Learning Progress -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Progres Pembelajaran</h3>
        </div>
        <div class="space-y-4">
            <div class="flex justify-between">
                <span class="text-gray-600">Modul Diselesaikan</span>
                <span class="font-semibold"><?php echo $completedModules; ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Tantangan Diselesaikan</span>
                <span class="font-semibold"><?php echo $completedChallenges; ?></span>
            </div>
            
            <div class="pt-2">
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <?php 
                    // Calculate overall progress percentage
                    $totalPossible = 30; // Adjust based on actual total modules and challenges
                    $overallProgress = min(100, max(0, (($completedModules + $completedChallenges) / $totalPossible) * 100));
                    ?>
                    <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo $overallProgress; ?>%"></div>
                </div>
                <div class="flex justify-between text-xs text-gray-500 mt-1">
                    <span>Awal</span>
                    <span><?php echo round($overallProgress); ?>%</span>
                    <span>Selesai</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Aktivitas Terkini</h3>
        </div>
        <div class="space-y-3">
            <?php if (count($userActivities) > 0): ?>
                <?php foreach ($userActivities as $activity): ?>
                <div class="flex items-start space-x-3 pb-3 border-b last:border-b-0">
                    <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-gray-700"><?php echo htmlspecialchars($activity['description']); ?></p>
                        <p class="text-xs text-gray-400 mt-1"><?php echo formatDate($activity['created_at']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-sm text-gray-500 text-center py-4">Belum ada aktivitas</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Achievements & Active Challenges -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Recent Achievements -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Pencapaian Terbaru</h3>
        </div>
        
        <!-- Recent Modules -->
        <div class="mb-5">
            <h4 class="font-medium text-gray-700 mb-2">Modul Terselesaikan</h4>
            <?php if (count($recentModules) > 0): ?>
                <div class="space-y-2">
                    <?php foreach ($recentModules as $module): ?>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-700"><?php echo htmlspecialchars($module['title']); ?></span>
                        <span class="text-xs text-green-600">+<?php echo formatDate($module['completed_at']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 py-2">Belum menyelesaikan modul apapun</p>
            <?php endif; ?>
        </div>
        
        <!-- Recent Challenges -->
        <div>
            <h4 class="font-medium text-gray-700 mb-2">Tantangan Terselesaikan</h4>
            <?php if (count($recentChallenges) > 0): ?>
                <div class="space-y-2">
                    <?php foreach ($recentChallenges as $challenge): ?>
                    <div class="flex justify-between items-center py-2 border-b border-gray-100">
                        <span class="text-sm text-gray-700"><?php echo htmlspecialchars($challenge['title']); ?></span>
                        <span class="text-xs text-green-600">+<?php echo formatDate($challenge['completed_at']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-sm text-gray-500 py-2">Belum menyelesaikan tantangan apapun</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Daily Challenges -->
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800">Tantangan Harian</h3>
        </div>
        <div class="space-y-4">
            <?php if (count($activeChallenges) > 0): ?>
                <?php foreach ($activeChallenges as $challenge): ?>
                <div class="border border-gray-200 rounded-xl p-4 hover:shadow-md transition-shadow">
                    <div class="flex justify-between">
                        <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($challenge['title']); ?></h4>
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full"><?php echo $challenge['xp_reward']; ?> XP</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars(substr($challenge['description'], 0, 80)); ?><?php echo strlen($challenge['description']) > 80 ? '...' : ''; ?></p>
                    <div class="flex items-center justify-between mt-3">
                        <span class="text-xs text-gray-500"><?php echo $challenge['point_reward']; ?> pts</span>
                        <a href="index.php?page=challenges&action=detail&id=<?php echo $challenge['challenge_id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Lihat Tantangan</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-sm text-gray-500 text-center py-6">Tidak ada tantangan harian saat ini</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-2xl shadow-sm p-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Aksi Cepat</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <a href="index.php?page=education" class="flex flex-col items-center justify-center space-y-2 bg-white hover:bg-blue-50 text-blue-700 rounded-xl py-4 transition-colors shadow-sm">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <span class="font-medium text-sm">Modul</span>
        </a>

        <a href="index.php?page=challenges" class="flex flex-col items-center justify-center space-y-2 bg-white hover:bg-green-50 text-green-700 rounded-xl py-4 transition-colors shadow-sm">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <span class="font-medium text-sm">Tantangan</span>
        </a>

        <a href="index.php?page=leaderboard" class="flex flex-col items-center justify-center space-y-2 bg-white hover:bg-purple-50 text-purple-700 rounded-xl py-4 transition-colors shadow-sm">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="font-medium text-sm">Peringkat</span>
        </a>

        <a href="index.php?page=rewards" class="flex flex-col items-center justify-center space-y-2 bg-white hover:bg-orange-50 text-orange-700 rounded-xl py-4 transition-colors shadow-sm">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
            </svg>
            <span class="font-medium text-sm">Hadiah</span>
        </a>
    </div>
</div>
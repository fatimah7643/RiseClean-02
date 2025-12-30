<?php
// Get leaderboard data
$stmt = $pdo->query("
    SELECT 
        u.id,
        u.username,
        u.first_name,
        u.last_name,
        u.total_xp,
        u.total_points,
        u.current_level,
        l.level_name,
        RANK() OVER (ORDER BY u.total_xp DESC, u.total_points DESC) as xp_rank
    FROM users u
    LEFT JOIN levels l ON u.current_level = l.level_id
    WHERE u.is_active = 1
    ORDER BY u.total_xp DESC, u.total_points DESC
    LIMIT 50
");
$leaderboard = $stmt->fetchAll();

// Get current user's position
$stmtUser = $pdo->prepare("
    SELECT 
        u.id,
        u.username,
        u.first_name,
        u.last_name,
        u.total_xp,
        u.total_points,
        u.current_level,
        l.level_name,
        RANK() OVER (ORDER BY u.total_xp DESC, u.total_points DESC) as xp_rank
    FROM users u
    LEFT JOIN levels l ON u.current_level = l.level_id
    WHERE u.id = ?
");
$stmtUser->execute([$_SESSION['user_id']]);
$userPosition = $stmtUser->fetch();

// Find user's overall rank
$stmtRank = $pdo->prepare("
    SELECT COUNT(*) as user_count
    FROM users
    WHERE is_active = 1
    AND (total_xp > ? OR (total_xp = ? AND total_points > ?))
");
$stmtRank->execute([$userPosition['total_xp'], $userPosition['total_xp'], $userPosition['total_points']]);
$userRank = $stmtRank->fetch()['user_count'] + 1;
?>

<div class="mb-6">
    <h1 class="text-3xl font-bold text-gray-800">Papan Peringkat</h1>
    <p class="text-gray-500 mt-1">Peringkat pengguna berdasarkan total XP dan poin</p>
</div>

<!-- Your Rank Card -->
<div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-sm p-6 text-white mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-lg font-bold mb-1">Peringkat Kamu</h3>
            <div class="flex items-center space-x-4">
                <div class="text-2xl font-bold">#<?php echo $userRank; ?></div>
                <div>
                    <p class="font-semibold"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></p>
                    <p class="text-sm opacity-80">Level <?php echo $currentUser['current_level']; ?> - <?php echo htmlspecialchars($currentUser['level_name']); ?></p>
                </div>
            </div>
        </div>
        <div class="text-right">
            <p class="text-2xl font-bold"><?php echo number_format($currentUser['total_xp']); ?> XP</p>
            <p class="text-sm opacity-80"><?php echo number_format($currentUser['total_points']); ?> Poin</p>
        </div>
    </div>
</div>

<!-- Leaderboard Table -->
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Peringkat</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pengguna</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">XP</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poin</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($leaderboard as $index => $user): ?>
                <tr class="<?php echo $user['id'] == $_SESSION['user_id'] ? 'bg-blue-50 font-semibold' : ''; ?>">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <?php if ($index === 0): ?>
                                <span class="w-6 h-6 rounded-full bg-yellow-400 flex items-center justify-center text-xs text-white font-bold mr-2">1</span>
                                <svg class="w-5 h-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            <?php elseif ($index === 1): ?>
                                <span class="w-6 h-6 rounded-full bg-gray-400 flex items-center justify-center text-xs text-white font-bold mr-2">2</span>
                                <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            <?php elseif ($index === 2): ?>
                                <span class="w-6 h-6 rounded-full bg-orange-700 flex items-center justify-center text-xs text-white font-bold mr-2">3</span>
                                <svg class="w-5 h-5 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            <?php else: ?>
                                <span class="w-6 h-6 rounded-full bg-gray-200 flex items-center justify-center text-xs text-gray-700 font-bold mr-2"><?php echo $index + 1; ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10">
                                <img class="h-10 w-10 rounded-full" src="<?php echo getAvatarUrl($user['avatar']); ?>" alt="">
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                <div class="text-sm text-gray-500">@<?php echo htmlspecialchars($user['username']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900">Lvl <?php echo $user['current_level']; ?></div>
                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['level_name']); ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">
                        <?php echo number_format($user['total_xp']); ?> XP
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        <?php echo number_format($user['total_points']); ?> Pts
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if (empty($leaderboard)): ?>
    <div class="text-center py-12">
        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        <h3 class="text-lg font-medium text-gray-500">Belum ada data peringkat</h3>
        <p class="text-gray-400 mt-1">Peringkat akan muncul ketika pengguna mulai aktif.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Leaderboard Info -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center mr-4">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-500">Cara Naik Peringkat</p>
                <p class="font-semibold">Selesaikan modul dan tantangan</p>
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
                <p class="text-sm text-gray-500">Hadiah</p>
                <p class="font-semibold">Tukarkan poin di halaman hadiah</p>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl shadow-sm p-6">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center mr-4">
                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-500">Level Up</p>
                <p class="font-semibold">Kumpulkan XP untuk naik level</p>
            </div>
        </div>
    </div>
</div>
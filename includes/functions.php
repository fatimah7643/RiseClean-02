<?php
// Redirect function
function redirect($url) {
    // Pastikan tidak ada output sebelum redirect
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    } else {
        // Fallback jika header sudah terkirim
        echo "<script>window.location.href='" . htmlspecialchars($url) . "';</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url) . "'></noscript>";
        exit();
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get current user data
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("
        SELECT u.*, r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

// Check user role
function hasRole($roleNames) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    if (is_array($roleNames)) {
        return in_array($user['role_name'], $roleNames);
    }
    return $user['role_name'] === $roleNames;
}

// Sanitize input
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Log activity
function logActivity($userId, $type, $description) {
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, activity_type, description, ip_address) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $type, $description, $_SERVER['REMOTE_ADDR']]);
}

// Format date Indonesia
function formatDate($date) {
    return date('d F Y H:i', strtotime($date));
}

// Get avatar URL
function getAvatarUrl($avatar) {
    if (empty($avatar) || $avatar === 'default-avatar.png') {
        return 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['username'] ?? 'User') . '&background=3b82f6&color=fff';
    }
    return BASE_URL . 'assets/uploads/avatars/' . $avatar;
}

// Alert message
function setAlert($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type, // success, error, warning, info
        'message' => $message
    ];
}

function getAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        unset($_SESSION['alert']);
        return $alert;
    }
    return null;
}

// Upload image
function uploadImage($file, $itemId = 0, $imageType = 'general') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Check if uploads directory exists, create if not
    $uploadDir = UPLOAD_PATH . $imageType . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Allowed file extensions
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Check file type
    if (!in_array($fileExtension, $allowedExtensions)) {
        return false;
    }

    // Check file size (limit to 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }

    // Generate unique filename
    // If item ID is 0 (temporary), use timestamp instead
    if ($itemId == 0) {
        $fileName = 'tmp_' . time() . '_' . uniqid() . '.' . $fileExtension;
    } else {
        $fileName = $itemId . '_' . time() . '.' . $fileExtension;
    }
    $targetPath = $uploadDir . $fileName;

    // Resize image if needed (optional)
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $fileName;
    }

    return false;
}

// Delete image
function deleteImage($imageName, $imageType = 'general') {
    if (!empty($imageName)) {
        $imagePath = UPLOAD_PATH . $imageType . '/' . $imageName;
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
}

// Get image URL
function getImageUrl($imageName, $imageType = 'general') {
    if (empty($imageName)) {
        return BASE_URL . 'assets/images/no-image.svg'; // Default image if none exists
    }
    return BASE_URL . 'assets/uploads/' . $imageType . '/' . $imageName;
}

// Award XP and points to user for completing an activity
function awardUserPoints($userId, $itemType, $itemId) {
    global $pdo;

    // For education modules, check if the user has completed the associated quiz
    if ($itemType === 'module') {
        // Check if the module has associated quiz questions
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as quiz_count
            FROM quiz_questions
            WHERE module_id = ? AND is_active = 1
        ");
        $stmt->execute([$itemId]);
        $quizCount = $stmt->fetch()['quiz_count'];

        if ($quizCount > 0) {
            // Module has quiz questions, check if user has passed the quiz with minimum 70% score
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT qq.question_id) as total_questions,
                       COUNT(DISTINCT uqa.question_id) as correct_answers
                FROM quiz_questions qq
                LEFT JOIN user_quiz_answers uqa ON qq.question_id = uqa.question_id AND uqa.user_id = ? AND uqa.is_correct = 1
                WHERE qq.module_id = ? AND qq.is_active = 1
            ");
            $stmt->execute([$userId, $itemId]);
            $result = $stmt->fetch();

            // Calculate percentage of correct answers
            $totalQuestions = $result['total_questions'];
            $correctAnswers = $result['correct_answers'];

            if ($totalQuestions > 0) {
                $percentage = ($correctAnswers / $totalQuestions) * 100;

                // User must achieve at least 70% to pass the quiz
                if ($percentage < 70) {
                    // User hasn't passed the quiz with minimum required score, return false
                    return false;
                }
            }
        }

        $stmt = $pdo->prepare("SELECT xp_reward, point_reward FROM education_modules WHERE module_id = ?");
        $stmt->execute([$itemId]);
        $reward = $stmt->fetch();
    } elseif ($itemType === 'challenge') {
        $stmt = $pdo->prepare("SELECT xp_reward, point_reward FROM challenges WHERE challenge_id = ?");
        $stmt->execute([$itemId]);
        $reward = $stmt->fetch();
    } else {
        return false; // Invalid item type
    }

    if (!$reward) {
        return false; // Item not found
    }

    // Check if user has already completed this item
    $stmt = $pdo->prepare("
        SELECT progress_id FROM user_progress
        WHERE user_id = ? AND item_id = ? AND item_type = ?
    ");
    $stmt->execute([$userId, $itemId, $itemType]);
    if ($stmt->fetch()) {
        return false; // Already completed
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Insert progress record
        $stmt = $pdo->prepare("
            INSERT INTO user_progress (user_id, item_id, item_type, is_verified)
            VALUES (?, ?, ?, 1)
        ");
        $stmt->execute([$userId, $itemId, $itemType]);

        // Update user's XP and points (the triggers should handle this automatically)
        // But just in case, we'll also update directly here
        $stmt = $pdo->prepare("
            UPDATE users
            SET total_xp = total_xp + ?, total_points = total_points + ?
            WHERE id = ?
        ");
        $stmt->execute([$reward['xp_reward'], $reward['point_reward'], $userId]);

        // Update user's level based on XP
        updateUserLevel($userId);

        // Commit transaction
        $pdo->commit();

        return true;
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollback();
        return false;
    }
}

// Update user's level based on their XP
function updateUserLevel($userId) {
    global $pdo;

    // Get user's current XP
    $stmt = $pdo->prepare("SELECT total_xp FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    $currentXP = $userData['total_xp'];

    // Find the appropriate level based on XP thresholds
    $stmt = $pdo->prepare("
        SELECT level_id FROM levels
        WHERE min_xp <= ?
        ORDER BY min_xp DESC
        LIMIT 1
    ");
    $stmt->execute([$currentXP]);
    $level = $stmt->fetch();

    $newLevel = $level ? $level['level_id'] : 1;

    // Update user's level
    $stmt = $pdo->prepare("UPDATE users SET current_level = ? WHERE id = ?");
    $stmt->execute([$newLevel, $userId]);
}

// Get user's current stats (XP, points, level)
function getUserStats($userId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT total_xp, total_points, current_level
        FROM users
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

// Get user's rank on the leaderboard
function getUserRank($userId) {
    global $pdo;

    // Get the user's XP and points
    $stmt = $pdo->prepare("SELECT total_xp, total_points FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) return 0;

    // Count how many users have more XP or same XP but more points
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as user_count
        FROM users
        WHERE is_active = 1
        AND (total_xp > ? OR (total_xp = ? AND total_points > ?))
    ");
    $stmt->execute([$user['total_xp'], $user['total_xp'], $user['total_points']]);
    $rank = $stmt->fetch()['user_count'] + 1;

    return $rank;
}

// Get user's next level information
function getNextLevelInfo($userLevel, $userXP) {
    global $pdo;

    if ($userLevel >= 8) { // Assuming max level is 8
        return null; // Already at max level
    }

    $stmt = $pdo->prepare("SELECT * FROM levels WHERE level_id = ?");
    $stmt->execute([$userLevel + 1]);
    $nextLevel = $stmt->fetch();

    if ($nextLevel) {
        $xpToNext = $nextLevel['min_xp'] - $userXP;
        return [
            'level_id' => $nextLevel['level_id'],
            'level_name' => $nextLevel['level_name'],
            'min_xp' => $nextLevel['min_xp'],
            'xp_needed' => $xpToNext
        ];
    }

    return null;
}

// Check if user has completed the quiz for a specific module
function hasCompletedModuleQuiz($userId, $moduleId) {
    global $pdo;

    // Check if the module has associated quiz questions
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as quiz_count
        FROM quiz_questions
        WHERE module_id = ? AND is_active = 1
    ");
    $stmt->execute([$moduleId]);
    $quizCount = $stmt->fetch()['quiz_count'];

    // If no quiz questions, consider the quiz as completed
    if ($quizCount == 0) {
        return true;
    }

    // Check if user has passed the quiz with minimum 70% score
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT qq.question_id) as total_questions,
               COUNT(DISTINCT uqa.question_id) as correct_answers
        FROM quiz_questions qq
        LEFT JOIN user_quiz_answers uqa ON qq.question_id = uqa.question_id AND uqa.user_id = ? AND uqa.is_correct = 1
        WHERE qq.module_id = ? AND qq.is_active = 1
    ");
    $stmt->execute([$userId, $moduleId]);
    $result = $stmt->fetch();

    // Calculate percentage of correct answers
    $totalQuestions = $result['total_questions'];
    $correctAnswers = $result['correct_answers'];

    if ($totalQuestions > 0) {
        $percentage = ($correctAnswers / $totalQuestions) * 100;

        // User must achieve at least 70% to pass the quiz
        return $percentage >= 70;
    }

    return false;
}

// Get the number of quiz questions for a module
function getModuleQuizCount($moduleId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as quiz_count
        FROM quiz_questions
        WHERE module_id = ? AND is_active = 1
    ");
    $stmt->execute([$moduleId]);
    return $stmt->fetch()['quiz_count'];
}

// Get the number of quiz questions answered correctly by a user for a module
function getModuleQuizCorrectAnswers($userId, $moduleId) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as correct_answers
        FROM quiz_questions qq
        JOIN user_quiz_answers uqa ON qq.question_id = uqa.question_id
        WHERE qq.module_id = ? AND uqa.user_id = ? AND uqa.is_correct = 1 AND qq.is_active = 1
    ");
    $stmt->execute([$moduleId, $userId]);
    return $stmt->fetch()['correct_answers'];
}


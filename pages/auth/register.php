<?php
if (isLoggedIn()) {
    redirect(BASE_URL . 'index.php?page=dashboard');
}

// Check if registration is allowed
$registration_allowed = true; // This can be configured in the future

if ($registration_allowed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username']);
    $email = cleanInput($_POST['email']);
    $password = cleanInput($_POST['password']);
    $confirm_password = cleanInput($_POST['confirm_password']);
    $first_name = cleanInput($_POST['first_name']);
    $last_name = cleanInput($_POST['last_name']);
    $captcha_input = cleanInput($_POST['captcha']);
    $captcha_session = $_SESSION['captcha'] ?? '';

    // Validation
    $errors = [];
    
    if (empty($username) || strlen($username) < 3) {
        $errors[] = 'Username harus minimal 3 karakter';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid';
    }
    
    if (empty($first_name)) {
        $errors[] = 'Nama depan harus diisi';
    }
    
    if (empty($password) || strlen($password) < 6) {
        $errors[] = 'Password harus minimal 6 karakter';
    }
    
    if ($password !== $confirm_password) {
        $errors[] = 'Password dan konfirmasi password tidak cocok';
    }
    
    if (strtolower(trim($captcha_input)) !== strtolower(trim($captcha_session))) {
        $errors[] = 'CAPTCHA tidak valid';
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
            $errors[] = 'Username atau email sudah digunakan';
        }
    }
    
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user with initial gamification values
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, first_name, last_name, role_id, total_xp, total_points, current_level)
            VALUES (?, ?, ?, ?, ?, 4, 0, 0, 1)
        ");
        $stmt->execute([$username, $email, $hashed_password, $first_name, $last_name]);
        
        // Get the new user ID
        $new_user_id = $pdo->lastInsertId();
        
        // Add welcome message activity
        logActivity($new_user_id, 'user_registered', 'New user registered to RiseClean platform');
        
        // Set success message and redirect to login
        setAlert('success', 'Akun berhasil dibuat! Silakan login untuk memulai perjalananmu.');
        redirect(BASE_URL . 'index.php?page=login');
    }
}

// Generate CAPTCHA values
$num1 = rand(1, 50);
$num2 = rand(1, 5);
$operation = rand(0, 1); // 0 for addition, 1 for subtraction
$operation_symbol = $operation === 0 ? '+' : '+';
$answer = $operation === 0 ? $num1 + $num2 : $num1 + $num2;

// Store correct answer in session
$_SESSION['captcha'] = $answer;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-green-500 to-green-700 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800"><?php echo SITE_NAME; ?></h1>
                <p class="text-gray-500 mt-1"><?php echo SITE_TAGLINE; ?></p>
                <p class="text-gray-500 mt-2">Buat akun untuk memulai perjalanan bersihmu</p>
            </div>

            <?php if (isset($errors) && count($errors) > 0): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl mb-4">
                <ul class="list-disc pl-5 space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Depan</label>
                    <input type="text" name="first_name" required
                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition"
                           placeholder="Masukkan nama depan">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nama Belakang</label>
                    <input type="text" name="last_name"
                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition"
                           placeholder="Masukkan nama belakang">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                    <input type="text" name="username" required
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition"
                           placeholder="Masukkan username">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition"
                           placeholder="Masukkan email">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition"
                           placeholder="Masukkan password">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password</label>
                    <input type="password" name="confirm_password" required
                           class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition"
                           placeholder="Konfirmasi password">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">CAPTCHA</label>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex-1 flex items-center justify-center bg-gray-100 rounded-xl h-12 border border-gray-200 font-bold text-lg text-gray-800 min-h-[48px]">
                            <?php echo "$num1 $operation_symbol $num2 = ?"; ?>
                        </div>
                        <input type="text" name="captcha" required
                               class="px-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent outline-none transition flex-1 min-w-[150px]"
                               placeholder="Jawaban">
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Masukkan hasil dari perhitungan di atas</p>
                </div>

                <button type="submit"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-xl transition-colors">
                    Daftar
                </button>
            </form>

            <div class="mt-6 text-center text-sm text-gray-500">
                <p>Sudah punya akun? <a href="index.php?page=login" class="text-green-600 font-semibold hover:text-green-800">Login di sini</a></p>
            </div>
        </div>
    </div>

    <script>
        // Function to refresh CAPTCHA
        function refreshCaptcha() {
            location.reload();
        }
    </script>
</body>
</html>
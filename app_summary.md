Ringkasan Aplikasi: RiseClean

Tagline: "Rise to a Cleaner Future"

RiseClean adalah platform edukasi kebersihan berbasis web yang dirancang untuk meningkatkan kesadaran masyarakat terhadap pengelolaan sampah melalui pendekatan gamifikasi.

1. Fitur Utama (Gamifikasi & Edukasi)

Modul Edukasi Interaktif: Konten pembelajaran mengenai manajemen sampah (daur ulang, pemilahan, dsb).

Sistem Poin & XP: Pengguna mendapatkan poin setelah menyelesaikan materi atau tantangan.

Leveling System: Tingkatan pengguna (misal: Newbie, Eco-Warrior, Green Master) berdasarkan akumulasi XP.

Daily Challenges: Tantangan harian untuk melakukan aksi nyata di dunia medis (misal: "Foto sampah plastik yang sudah dipilah").

Leaderboard: Papan peringkat untuk memacu kompetisi positif antar pengguna.

Reward/Redeem: Penukaran poin dengan insentif digital atau fisik (sesuai pengembangan kedepan).

Feedback System: Mekanisme bagi pengguna untuk memberikan laporan atau masukan terkait isu kebersihan.

2. Alur Aplikasi (User Journey)

Registrasi/Login: Pengguna membuat akun untuk mulai melacak progres.

Dashboard: Menampilkan status level, jumlah poin saat ini, dan tantangan yang tersedia.

Belajar & Aksi: Pengguna memilih modul edukasi atau mengambil tantangan harian.

Verifikasi & Reward: Sistem memverifikasi penyelesaian (bisa melalui kuis atau unggah foto). Poin dan XP diberikan secara otomatis.

Ranking: Pengguna melihat posisi mereka di Leaderboard global atau komunitas.

3. Kebutuhan Sistem

Fungsional

Sistem mampu mencatat progres belajar setiap pengguna secara individu.

Sistem mampu menghitung poin dan memperbarui level secara real-time.

Admin mampu mengelola konten edukasi, kuis, dan tantangan harian.

Fitur leaderboard yang dapat diurutkan berdasarkan periode waktu.

Non-Fungsional

Usability: Antarmuka responsif (mobile-friendly) agar mudah digunakan saat melakukan tantangan di luar ruangan.

Reliability: Konsistensi data poin dan reward agar pengguna tidak kehilangan progres.

Performance: Loading halaman konten edukasi harus cepat untuk menjaga engagement.

4. Skema Database (MySQL)

Skema ini dirancang untuk mendukung sistem gamifikasi yang dinamis.

-- 1. Tabel Users
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    total_xp INT DEFAULT 0,
    total_points INT DEFAULT 0,
    current_level INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Tabel Levels (Definisi ambang batas XP untuk tiap level)
CREATE TABLE levels (
    level_id INT PRIMARY KEY,
    level_name VARCHAR(50),
    min_xp INT NOT NULL
);

-- 3. Tabel Education_Modules (Materi Edukasi)
CREATE TABLE modules (
    module_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    content TEXT,
    xp_reward INT DEFAULT 10,
    point_reward INT DEFAULT 5
);

-- 4. Tabel Challenges (Tantangan Harian/Spesial)
CREATE TABLE challenges (
    challenge_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    xp_reward INT DEFAULT 20,
    point_reward INT DEFAULT 10,
    start_date DATE,
    end_date DATE
);

-- 5. Tabel User_Progress (Mencatat apa saja yang sudah diselesaikan)
CREATE TABLE user_progress (
    progress_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    item_id INT, -- Bisa ID modul atau ID challenge
    item_type ENUM('module', 'challenge'),
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- 6. Tabel Rewards (Katalog hadiah penukaran poin)
CREATE TABLE rewards (
    reward_id INT AUTO_INCREMENT PRIMARY KEY,
    reward_name VARCHAR(100),
    point_cost INT,
    stock INT DEFAULT 0
);

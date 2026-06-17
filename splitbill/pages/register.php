<?php
$page_title = "Daftar Akun Baru";
require_once '../includes/config.php';

// Jika sudah login, langsung ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = validate_input($_POST['username'] ?? '');
    $email = validate_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Backend Validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Semua kolom wajib diisi.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    } elseif (strlen($password) < 6) {
        $error = "Password harus minimal 6 karakter.";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok.";
    } else {
        try {
            // Cek apakah username atau email sudah terdaftar
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email");
            $stmt->execute(['username' => $username, 'email' => $email]);
            $existing_user = $stmt->fetch();
            
            if ($existing_user) {
                $error = "Username atau Email sudah terdaftar.";
            } else {
                // Enkripsi password sesuai ketentuan teknis poin 2 (Autentikasi)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Simpan ke database dengan Prepared Statements
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
                $stmt->execute([
                    'username' => $username,
                    'email' => $email,
                    'password' => $hashed_password
                ]);
                
                $_SESSION['register_success'] = "Pendaftaran berhasil! Silakan masuk.";
                header("Location: login.php");
                exit();
            }
        } catch (PDOException $e) {
            $error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-10 col-md-8 col-lg-5">
            <div class="card card-custom shadow border-0 mt-3 mt-md-5">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="icon-shape bg-primary-soft text-primary rounded-circle mb-3 mx-auto d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="bi bi-person-plus fs-3"></i>
                        </div>
                        <h2 class="card-title fw-bold text-gradient-primary">Daftar Akun</h2>
                        <p class="text-muted small">Buat akun untuk mulai membagi tagihan</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-4 rounded-3 border-0 small" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div><?php echo h($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Form dengan validasi JS (Client-side) -->
                    <form id="registerForm" action="register.php" method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label text-muted small fw-semibold">Username</label>
                            <div class="input-group input-group-custom">
                                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control bg-transparent border-start-0 ps-0 text-white" id="username" name="username" placeholder="Masukkan username" required value="<?php echo isset($username) ? h($username) : ''; ?>">
                            </div>
                            <div class="invalid-feedback" id="usernameFeedback">Username tidak boleh kosong.</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label text-muted small fw-semibold">Email</label>
                            <div class="input-group input-group-custom">
                                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control bg-transparent border-start-0 ps-0 text-white" id="email" name="email" placeholder="contoh@email.com" required value="<?php echo isset($email) ? h($email) : ''; ?>">
                            </div>
                            <div class="invalid-feedback" id="emailFeedback">Format email tidak valid atau kosong.</div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label text-muted small fw-semibold">Password (min. 6 karakter)</label>
                            <div class="input-group input-group-custom">
                                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control bg-transparent border-start-0 ps-0 text-white" id="password" name="password" placeholder="Masukkan password" required minlength="6">
                            </div>
                            <div class="invalid-feedback" id="passwordFeedback">Password minimal 6 karakter.</div>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label text-muted small fw-semibold">Konfirmasi Password</label>
                            <div class="input-group input-group-custom">
                                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" class="form-control bg-transparent border-start-0 ps-0 text-white" id="confirm_password" name="confirm_password" placeholder="Ulangi password" required>
                            </div>
                            <div class="invalid-feedback" id="confirmPasswordFeedback">Konfirmasi password tidak cocok.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold mb-3">Daftar Sekarang</button>
                        
                        <div class="text-center">
                            <span class="text-muted small">Sudah punya akun?</span>
                            <a href="login.php" class="text-primary small fw-semibold text-decoration-none ms-1">Masuk di sini</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

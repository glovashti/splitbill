<?php
$page_title = "Masuk Akun";
require_once '../includes/config.php';

// Jika sudah login, langsung ke dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Check if registration redirect success exists
if (isset($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = validate_input($_POST['identity'] ?? ''); // Bisa username atau email
    $password = $_POST['password'] ?? '';

    // Backend Validation
    if (empty($identity) || empty($password)) {
        $error = "Username/Email dan Password wajib diisi.";
    } else {
        try {
            // Cari user di database menggunakan Prepared Statements
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username OR email = :email");
            $stmt->execute(['username' => $identity, 'email' => $identity]);
            $user = $stmt->fetch();

            // Verifikasi Password menggunakan password_verify() sesuai spesifikasi teknis poin 2
            if ($user && password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Username/Email atau password Anda salah.";
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
                            <i class="bi bi-shield-lock fs-3"></i>
                        </div>
                        <h2 class="card-title fw-bold text-gradient-primary">Selamat Datang</h2>
                        <p class="text-muted small">Silakan masuk untuk mengelola split bill Anda</p>
                    </div>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success d-flex align-items-center py-2 px-3 mb-4 rounded-3 border-0 small" role="alert">
                            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                            <div><?php echo h($success); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-4 rounded-3 border-0 small" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div><?php echo h($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Form dengan validasi JS (Client-side) -->
                    <form id="loginForm" action="login.php" method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="identity" class="form-label text-muted small fw-semibold">Username atau Email</label>
                            <div class="input-group input-group-custom">
                                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control bg-transparent border-start-0 ps-0 text-white" id="identity" name="identity" placeholder="Masukkan username atau email" required value="<?php echo isset($identity) ? h($identity) : ''; ?>">
                            </div>
                            <div class="invalid-feedback">Username atau Email tidak boleh kosong.</div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label text-muted small fw-semibold">Password</label>
                            <div class="input-group input-group-custom">
                                <span class="input-group-text bg-transparent border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control bg-transparent border-start-0 ps-0 text-white" id="password" name="password" placeholder="Masukkan password" required>
                            </div>
                            <div class="invalid-feedback">Password tidak boleh kosong.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold mb-3">Masuk</button>
                        
                        <div class="text-center">
                            <span class="text-muted small">Belum punya akun?</span>
                            <a href="register.php" class="text-primary small fw-semibold text-decoration-none ms-1">Daftar di sini</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

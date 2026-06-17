<?php
$page_title = "Bagi Tagihan Tanpa Pusing";
require_once 'includes/config.php';

// Jika user sudah masuk, langsung arahkan ke Dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: pages/dashboard.php");
    exit();
}

require_once 'includes/header.php';
?>

<div class="container py-3 py-md-5">
    <!-- Hero Section -->
    <div class="row align-items-center g-5 py-3 py-md-5">
        <div class="col-12 col-lg-6">
            <h1 class="display-4 fw-extrabold text-white mb-3 text-gradient-primary font-outfit" style="font-size: clamp(2.5rem, 5vw, 3.8rem); line-height: 1.15;">
                Makan Bareng Teman Tanpa Ribet Bagi Tagihan
            </h1>
            <p class="lead text-muted mb-4 fs-5" style="max-width: 540px;">
                Hitung porsi pembayaran masing-masing teman secara detail, transparan, dan adil. Lengkap dengan perhitungan pajak dan biaya layanan secara otomatis!
            </p>
            <div class="d-flex flex-wrap gap-3">
                <a href="pages/register.php" class="btn btn-primary btn-cta btn-lg px-4 shadow">
                    Mulai Sekarang <i class="bi bi-arrow-right-short ms-1 fs-5"></i>
                </a>
                <a href="pages/login.php" class="btn btn-outline-secondary btn-lg px-4 rounded-pill">
                    Masuk Akun
                </a>
            </div>
        </div>
        <div class="col-12 col-lg-6 text-center">
            <div class="hero-image-wrapper p-3 position-relative">
                <div class="card card-custom border-0 shadow-lg text-start mx-auto" style="max-width: 450px; transform: rotate(1deg);">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <span class="badge bg-primary-soft text-primary rounded-pill px-3 py-1.5 fw-semibold small">Paling Populer</span>
                            <i class="bi bi-wallet2 text-primary fs-3"></i>
                        </div>
                        <h4 class="fw-bold text-white mb-1">Makan Siang Bareng 🍕</h4>
                        <p class="text-muted small mb-4">Tagihan hari ini di Pizza Hut</p>
                        
                        <div class="mb-4">
                            <div class="d-flex justify-content-between mb-2 small text-muted">
                                <span>Pizza Carbonara (1 Porsi)</span>
                                <span>Rp 95.000</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2 small text-muted">
                                <span>Ice Tea (3 Gelas)</span>
                                <span>Rp 36.000</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2 small text-muted">
                                <span>Pajak & Servis (15%)</span>
                                <span>Rp 19.650</span>
                            </div>
                            <div class="border-top border-secondary border-opacity-10 pt-3 d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-light">Total Pembayaran</span>
                                <span class="fw-bold text-gradient-primary fs-5">Rp 150.650</span>
                            </div>
                        </div>

                        <div class="bg-primary-soft rounded-3 p-3">
                            <div class="d-flex justify-content-between align-items-center small text-white-50">
                                <span>Budi pays (Pizza + Tea)</span>
                                <span class="fw-semibold text-white">Rp 74.750</span>
                            </div>
                            <hr class="my-2 border-secondary border-opacity-25">
                            <div class="d-flex justify-content-between align-items-center small text-white-50">
                                <span>Caca pays (Tea share only)</span>
                                <span class="fw-semibold text-white">Rp 37.950</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Decorative blurred glow -->
                <div class="position-absolute top-50 start-50 translate-middle bg-primary rounded-circle opacity-10 blur-glow" style="width: 300px; height: 300px; z-index: -1;"></div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="row g-4 py-5 mt-3 border-top border-secondary border-opacity-10">
        <div class="col-12 col-md-4">
            <div class="card card-custom h-100 border-0 shadow-sm p-3">
                <div class="card-body">
                    <div class="icon-shape bg-primary-soft text-primary rounded-3 mb-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-check2-square fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-2">Presisi 100%</h5>
                    <p class="text-muted small mb-0">Pembagian per item memastikan tidak ada yang membayar lebih banyak dari yang dimakan atau diminum.</p>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card card-custom h-100 border-0 shadow-sm p-3">
                <div class="card-body">
                    <div class="icon-shape bg-success-soft text-success rounded-3 mb-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-shield-lock-fill fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-2">Aman & Terpercaya</h5>
                    <p class="text-muted small mb-0">Aplikasi dilindungi dengan enkripsi password standard industri serta validasi server-side yang ketat.</p>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card card-custom h-100 border-0 shadow-sm p-3">
                <div class="card-body">
                    <div class="icon-shape bg-info-soft text-info rounded-3 mb-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-lightning-charge-fill fs-4"></i>
                    </div>
                    <h5 class="fw-bold text-white mb-2">Mudah Dipelajari</h5>
                    <p class="text-muted small mb-0">Dirancang dengan arsitektur PHP dasar (PDO) yang rapi, ideal untuk bahan ajar proyek web semester awal.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

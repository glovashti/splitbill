<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - SplitBill" : "SplitBill - Mudah Bagi Tagihan"; ?></title>
    
    <!-- Google Fonts: Inter & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/splitbill/assets/css/style.css">
</head>
<body>

    <!-- Navbar Responsif (Collapse di Mobile) -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?php echo isset($_SESSION['user_id']) ? '/splitbill/pages/dashboard.php' : '/splitbill/index.php'; ?>">
                <i class="bi bi-wallet2 me-2 text-gradient-primary fs-4"></i>
                <span class="brand-text">Split<span class="text-primary-light">Bill</span></span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>" href="/splitbill/pages/dashboard.php">
                                <i class="bi bi-grid-fill me-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item ms-lg-2">
                            <div class="dropdown">
                                <a class="nav-link dropdown-toggle btn btn-user px-3 d-flex align-items-center" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-person-circle me-2 fs-5"></i>
                                    <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="userDropdown">
                                    <li>
                                        <a class="dropdown-item text-danger d-flex align-items-center" href="/splitbill/pages/logout.php">
                                            <i class="bi bi-box-arrow-right me-2"></i> Keluar
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link px-3 <?php echo (basename($_SERVER['PHP_SELF']) == 'login.php' || basename($_SERVER['PHP_SELF']) == 'index.php' && !isset($_SESSION['user_id'])) ? 'active' : ''; ?>" href="/splitbill/pages/login.php">
                                Masuk
                            </a>
                        </li>
                        <li class="nav-item ms-lg-2">
                            <a class="btn btn-primary btn-cta px-4" href="/splitbill/pages/register.php">
                                Daftar Gratis
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <main class="py-4 py-md-5">

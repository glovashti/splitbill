<?php
$page_title = "Dashboard Tagihan";
require_once '../includes/config.php';

// Cek autentikasi sesuai ketentuan teknis
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Ambil pesan dari session jika ada (POST-Redirect-GET)
if (isset($_SESSION['bill_success'])) {
    $success = $_SESSION['bill_success'];
    unset($_SESSION['bill_success']);
}
if (isset($_SESSION['bill_error'])) {
    $error = $_SESSION['bill_error'];
    unset($_SESSION['bill_error']);
}

// Proses tambah bill baru (Create)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_bill') {
    $title = validate_input($_POST['title'] ?? '');
    $tax_percent = floatval($_POST['tax_percent'] ?? 0);
    $service_percent = floatval($_POST['service_percent'] ?? 0);

    // Validasi form (min. 2 field diisi dengan benar)
    if (empty($title)) {
        $_SESSION['bill_error'] = "Judul tagihan tidak boleh kosong.";
    } elseif ($tax_percent < 0 || $service_percent < 0) {
        $_SESSION['bill_error'] = "Pajak atau Service Charge tidak boleh bernilai negatif.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO bills (user_id, title, tax_percent, service_percent) VALUES (:user_id, :title, :tax_percent, :service_percent)");
            $stmt->execute([
                'user_id' => $user_id,
                'title' => $title,
                'tax_percent' => $tax_percent,
                'service_percent' => $service_percent
            ]);
            $new_bill_id = $pdo->lastInsertId();
            
            $_SESSION['bill_success'] = "Tagihan '" . h($title) . "' berhasil dibuat! Silakan kelola anggota dan item.";
            header("Location: bill-detail.php?id=" . $new_bill_id);
            exit();
        } catch (PDOException $e) {
            $_SESSION['bill_error'] = "Gagal membuat tagihan: " . $e->getMessage();
        }
    }
    header("Location: dashboard.php");
    exit();
}

// Proses hapus bill (Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_bill') {
    $bill_id = intval($_POST['bill_id'] ?? 0);

    try {
        $stmt = $pdo->prepare("SELECT id FROM bills WHERE id = :id AND user_id = :user_id");
        $stmt->execute(['id' => $bill_id, 'user_id' => $user_id]);
        $bill_exists = $stmt->fetch();
        
        if ($bill_exists) {
            $stmt = $pdo->prepare("DELETE FROM bills WHERE id = :id");
            $stmt->execute(['id' => $bill_id]);
            $_SESSION['bill_success'] = "Tagihan berhasil dihapus.";
        } else {
            $_SESSION['bill_error'] = "Akses ditolak atau tagihan tidak ditemukan.";
        }
    } catch (PDOException $e) {
        $_SESSION['bill_error'] = "Gagal menghapus tagihan: " . $e->getMessage();
    }
    header("Location: dashboard.php");
    exit();
}

// Proses edit bill (Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_bill') {
    $bill_id = intval($_POST['bill_id'] ?? 0);
    $title = validate_input($_POST['title'] ?? '');
    $tax_percent = floatval($_POST['tax_percent'] ?? 0);
    $service_percent = floatval($_POST['service_percent'] ?? 0);

    if (empty($title) || $tax_percent < 0 || $service_percent < 0) {
        $_SESSION['bill_error'] = "Judul tagihan tidak boleh kosong, Pajak & Servis tidak boleh negatif.";
    } else {
        try {
            // Pastikan bill tersebut milik user yang sedang login
            $stmt = $pdo->prepare("SELECT id FROM bills WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $bill_id, 'user_id' => $user_id]);
            $bill_exists = $stmt->fetch();
            
            if ($bill_exists) {
                $stmt = $pdo->prepare("UPDATE bills SET title = :title, tax_percent = :tax_percent, service_percent = :service_percent WHERE id = :id");
                $stmt->execute([
                    'title' => $title,
                    'tax_percent' => $tax_percent,
                    'service_percent' => $service_percent,
                    'id' => $bill_id
                ]);
                
                // Recalculate bill total amount because tax/service percent changed
                $stmt = $pdo->prepare("
                    UPDATE bills b
                    SET b.total_amount = (
                        SELECT COALESCE(SUM(i.price * i.qty), 0) * (1 + (b.tax_percent / 100) + (b.service_percent / 100))
                        FROM items i
                        WHERE i.bill_id = b.id
                    )
                    WHERE b.id = :id;
                ");
                $stmt->execute(['id' => $bill_id]);
                
                $_SESSION['bill_success'] = "Tagihan '" . h($title) . "' berhasil diperbarui.";
            } else {
                $_SESSION['bill_error'] = "Akses ditolak atau tagihan tidak ditemukan.";
            }
        } catch (PDOException $e) {
            $_SESSION['bill_error'] = "Gagal memperbarui tagihan: " . $e->getMessage();
        }
    }
    header("Location: dashboard.php");
    exit();
}

// Ambil semua bill milik user menggunakan Query Kompleks 3 (JOIN/Subquery)
try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.title, b.tax_percent, b.service_percent, b.total_amount, b.created_at,
               (SELECT COUNT(*) FROM members m WHERE m.bill_id = b.id) AS member_count,
               (SELECT COUNT(*) FROM items i WHERE i.bill_id = b.id) AS item_count
        FROM bills b
        WHERE b.user_id = :user_id
        ORDER BY b.created_at DESC
    ");
    $stmt->execute(['user_id' => $user_id]);
    $bills = $stmt->fetchAll();
    
    // Hitung ringkasan statistik dashboard
    $total_bills_count = count($bills);
    $total_spent = 0;
    foreach ($bills as $b) {
        $total_spent += $b['total_amount'];
    }
} catch (PDOException $e) {
    $error = "Gagal mengambil data tagihan: " . $e->getMessage();
    $bills = [];
}

require_once '../includes/header.php';
?>

<div class="container px-4">
    <!-- Header Dashboard -->
    <div class="row align-items-center mb-4 g-3">
        <div class="col-12 col-md-6">
            <h1 class="fw-bold text-white mb-1">Dashboard Tagihan</h1>
            <p class="text-muted mb-0">Kelola dan bagi pengeluaran bersama teman-teman Anda</p>
        </div>
        <div class="col-12 col-md-6 text-md-end">
            <!-- Button memicu Modal Bootstrap -->
            <button type="button" class="btn btn-primary btn-cta d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#createBillModal">
                <i class="bi bi-plus-circle-fill me-2"></i> Buat Tagihan Baru
            </button>
        </div>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 rounded-3 mb-4 small" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo h($success); ?>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 rounded-3 mb-4 small" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo h($error); ?>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Statistik Ringkas (Bootstrap Cards) -->
    <div class="row g-3 mb-5">
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card card-custom h-100 shadow border-0">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="icon-shape bg-primary-soft text-primary rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-receipt-cutoff fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted small fw-semibold mb-1">Total Tagihan</h6>
                        <h3 class="fw-bold text-white mb-0"><?php echo $total_bills_count; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card card-custom h-100 shadow border-0">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="icon-shape bg-success-soft text-success rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-cash-coin fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted small fw-semibold mb-1">Akumulasi Tagihan</h6>
                        <h3 class="fw-bold text-white mb-0"><?php echo format_rupiah($total_spent); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-12 col-lg-4">
            <div class="card card-custom h-100 shadow border-0">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="icon-shape bg-info-soft text-info rounded-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-person-hearts fs-4"></i>
                    </div>
                    <div>
                        <h6 class="text-muted small fw-semibold mb-1">Pengguna Aktif</h6>
                        <h3 class="fw-bold text-white mb-0"><?php echo h($_SESSION['username']); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Daftar Tagihan -->
    <div class="row">
        <div class="col-12">
            <h4 class="fw-bold text-white mb-3"><i class="bi bi-list-task me-2 text-primary"></i>Daftar Tagihan Anda</h4>
            <?php if (empty($bills)): ?>
                <!-- Empty State -->
                <div class="card card-custom border-0 shadow py-5 text-center">
                    <div class="card-body">
                        <img src="" alt="" class="mb-3 d-none">
                        <div class="icon-shape bg-primary-soft text-primary rounded-circle mb-3 mx-auto d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                            <i class="bi bi-folder-x fs-2"></i>
                        </div>
                        <h5 class="fw-bold text-white">Belum ada tagihan</h5>
                        <p class="text-muted small mb-4">Buat tagihan pertama Anda untuk membagi pengeluaran makan, nongkrong, dll.</p>
                        <button type="button" class="btn btn-primary px-4 btn-cta" data-bs-toggle="modal" data-bs-target="#createBillModal">
                            Buat Sekarang
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <!-- Grid System untuk menampilkan bill -->
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($bills as $bill): ?>
                        <div class="col">
                            <div class="card card-custom h-100 shadow border-0 hover-lift">
                                <div class="card-body p-4 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title fw-bold text-white mb-0 text-truncate" style="max-width: 80%;"><?php echo h($bill['title']); ?></h5>
                                            <span class="badge bg-primary-soft text-primary rounded-pill small">
                                                <i class="bi bi-clock me-1"></i><?php echo date('d M Y', strtotime($bill['created_at'])); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <h3 class="fw-bold text-gradient-primary mb-3"><?php echo format_rupiah($bill['total_amount']); ?></h3>
                                            <div class="d-flex gap-2">
                                                <span class="badge bg-dark-soft text-muted border border-secondary border-opacity-25 rounded-pill py-1 px-2.5">
                                                    <i class="bi bi-people me-1"></i><?php echo $bill['member_count']; ?> Teman
                                                </span>
                                                <span class="badge bg-dark-soft text-muted border border-secondary border-opacity-25 rounded-pill py-1 px-2.5">
                                                    <i class="bi bi-egg-fried me-1"></i><?php echo $bill['item_count']; ?> Item
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-secondary border-opacity-10">
                                        <a href="bill-detail.php?id=<?php echo $bill['id']; ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                            Kelola <i class="bi bi-arrow-right-short ms-1"></i>
                                        </a>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-secondary btn-icon-only rounded-circle border-0 text-muted" data-bs-toggle="modal" data-bs-target="#editBillModal_<?php echo $bill['id']; ?>" aria-label="Edit Tagihan">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <!-- Form Delete dengan Event Listener JS -->
                                            <form action="dashboard.php" method="POST" class="delete-bill-form">
                                                <input type="hidden" name="action" value="delete_bill">
                                                <input type="hidden" name="bill_id" value="<?php echo $bill['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-icon-only rounded-circle border-0" aria-label="Hapus Tagihan">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Bootstrap: Tambah Tagihan Baru -->
<div class="modal fade modal-custom" id="createBillModal" tabindex="-1" aria-labelledby="createBillModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-0 shadow">
            <div class="modal-header border-bottom border-secondary border-opacity-10 p-4 pb-3">
                <h5 class="modal-title fw-bold text-white" id="createBillModalLabel"><i class="bi bi-receipt me-2 text-primary"></i>Buat Tagihan Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="createBillForm" action="dashboard.php" method="POST" class="needs-validation" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="create_bill">
                    
                    <div class="mb-3">
                        <label for="title" class="form-label text-muted small fw-semibold">Judul Tagihan</label>
                        <input type="text" class="form-control bg-dark border-secondary border-opacity-25 text-white" id="title" name="title" placeholder="Contoh: Makan Malam di McD" required>
                        <div class="invalid-feedback">Judul tagihan wajib diisi.</div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="tax_percent" class="form-label text-muted small fw-semibold">Pajak (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control bg-dark border-secondary border-opacity-25 text-white" id="tax_percent" name="tax_percent" value="10.00" required>
                                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">%</span>
                                </div>
                                <div class="invalid-feedback">Pajak tidak boleh kosong / negatif.</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="service_percent" class="form-label text-muted small fw-semibold">Service Charge (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control bg-dark border-secondary border-opacity-25 text-white" id="service_percent" name="service_percent" value="5.00" required>
                                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">%</span>
                                </div>
                                <div class="invalid-feedback">Service Charge tidak boleh kosong / negatif.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-10 p-4 pt-3">
                    <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Simpan Tagihan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($bills as $bill): ?>
    <!-- Modal: Edit Tagihan -->
    <div class="modal fade modal-custom" id="editBillModal_<?php echo $bill['id']; ?>" tabindex="-1" aria-labelledby="editBillModalLabel_<?php echo $bill['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content card-custom border-0 shadow">
                <div class="modal-header border-bottom border-secondary border-opacity-10 p-4 pb-3">
                    <h5 class="modal-title fw-bold text-white" id="editBillModalLabel_<?php echo $bill['id']; ?>"><i class="bi bi-pencil-square me-2 text-primary"></i>Ubah Detail Tagihan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="dashboard.php" method="POST" class="needs-validation" novalidate>
                    <div class="modal-body text-start p-4">
                        <input type="hidden" name="action" value="edit_bill">
                        <input type="hidden" name="bill_id" value="<?php echo $bill['id']; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold">Judul Tagihan</label>
                            <input type="text" class="form-control bg-dark border-secondary border-opacity-25 text-white" name="title" value="<?php echo h($bill['title']); ?>" required>
                            <div class="invalid-feedback">Judul tagihan wajib diisi.</div>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-semibold">Pajak (%)</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" class="form-control bg-dark border-secondary border-opacity-25 text-white" name="tax_percent" value="<?php echo floatval($bill['tax_percent']); ?>" required>
                                        <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">%</span>
                                    </div>
                                    <div class="invalid-feedback">Pajak tidak boleh kosong / negatif.</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="mb-3">
                                    <label class="form-label text-muted small fw-semibold">Service Charge (%)</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" class="form-control bg-dark border-secondary border-opacity-25 text-white" name="service_percent" value="<?php echo floatval($bill['service_percent']); ?>" required>
                                        <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">%</span>
                                    </div>
                                    <div class="invalid-feedback">Service Charge tidak boleh kosong / negatif.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-secondary border-opacity-10 p-4 pt-3">
                        <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary px-4">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php require_once '../includes/footer.php'; ?>

<?php
$page_title = "Detail Tagihan";
require_once '../includes/config.php';

// Cek autentikasi sesuai ketentuan teknis
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$bill_id = intval($_GET['id'] ?? 0);
$error = '';
$success = '';

// Check if redirect messages exist (POST-Redirect-GET)
if (isset($_SESSION['bill_success'])) {
    $success = $_SESSION['bill_success'];
    unset($_SESSION['bill_success']);
}
if (isset($_SESSION['bill_error'])) {
    $error = $_SESSION['bill_error'];
    unset($_SESSION['bill_error']);
}

// 1. Verifikasi Kepemilikan Tagihan (Security)
try {
    $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = :id AND user_id = :user_id");
    $stmt->execute(['id' => $bill_id, 'user_id' => $user_id]);
    $bill = $stmt->fetch();
    
    if (!$bill) {
        $_SESSION['error'] = "Tagihan tidak ditemukan atau Anda tidak memiliki akses.";
        header("Location: dashboard.php");
        exit();
    }
} catch (PDOException $e) {
    die("Kesalahan sistem: " . $e->getMessage());
}

// 2. Proses POST Actions (CRUD Anggota, CRUD Item, Simpan Pembagian)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // A. Tambah Anggota
    if ($action === 'add_member') {
        $name = validate_input($_POST['name'] ?? '');
        if (empty($name)) {
            $_SESSION['bill_error'] = "Nama anggota tidak boleh kosong.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO members (bill_id, name) VALUES (:bill_id, :name)");
                $stmt->execute(['bill_id' => $bill_id, 'name' => $name]);
                $_SESSION['bill_success'] = "Anggota '" . h($name) . "' berhasil ditambahkan.";
            } catch (PDOException $e) {
                $_SESSION['bill_error'] = "Gagal menambahkan anggota: " . $e->getMessage();
            }
        }
        header("Location: bill-detail.php?id=" . $bill_id);
        exit();
    }
    
    // B. Hapus Anggota
    elseif ($action === 'delete_member') {
        $member_id = intval($_POST['member_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM members WHERE id = :id AND bill_id = :bill_id");
            $stmt->execute(['id' => $member_id, 'bill_id' => $bill_id]);
            $_SESSION['bill_success'] = "Anggota berhasil dihapus.";
        } catch (PDOException $e) {
            $_SESSION['bill_error'] = "Gagal menghapus anggota: " . $e->getMessage();
        }
        header("Location: bill-detail.php?id=" . $bill_id);
        exit();
    }

    // C. Tambah Item
    elseif ($action === 'add_item') {
        $item_name = validate_input($_POST['item_name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $qty = intval($_POST['qty'] ?? 1);

        if (empty($item_name) || $price <= 0 || $qty <= 0) {
            $_SESSION['bill_error'] = "Nama item harus diisi, harga & kuantitas harus lebih dari 0.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO items (bill_id, item_name, price, qty) VALUES (:bill_id, :item_name, :price, :qty)");
                $stmt->execute([
                    'bill_id' => $bill_id,
                    'item_name' => $item_name,
                    'price' => $price,
                    'qty' => $qty
                ]);
                $_SESSION['bill_success'] = "Item '" . h($item_name) . "' berhasil ditambahkan.";
            } catch (PDOException $e) {
                $_SESSION['bill_error'] = "Gagal menambahkan item: " . $e->getMessage();
            }
        }
        header("Location: bill-detail.php?id=" . $bill_id);
        exit();
    }

    // D. Edit Item
    elseif ($action === 'edit_item') {
        $item_id = intval($_POST['item_id'] ?? 0);
        $item_name = validate_input($_POST['item_name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $qty = intval($_POST['qty'] ?? 1);

        if (empty($item_name) || $price <= 0 || $qty <= 0) {
            $_SESSION['bill_error'] = "Semua input edit item harus valid.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE items SET item_name = :item_name, price = :price, qty = :qty WHERE id = :id AND bill_id = :bill_id");
                $stmt->execute([
                    'item_name' => $item_name,
                    'price' => $price,
                    'qty' => $qty,
                    'id' => $item_id,
                    'bill_id' => $bill_id
                ]);
                $_SESSION['bill_success'] = "Item berhasil diperbarui.";
            } catch (PDOException $e) {
                $_SESSION['bill_error'] = "Gagal memperbarui item: " . $e->getMessage();
            }
        }
        header("Location: bill-detail.php?id=" . $bill_id);
        exit();
    }

    // E. Hapus Item
    elseif ($action === 'delete_item') {
        $item_id = intval($_POST['item_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM items WHERE id = :id AND bill_id = :bill_id");
            $stmt->execute(['id' => $item_id, 'bill_id' => $bill_id]);
            $_SESSION['bill_success'] = "Item berhasil dihapus.";
        } catch (PDOException $e) {
            $_SESSION['bill_error'] = "Gagal menghapus item: " . $e->getMessage();
        }
        header("Location: bill-detail.php?id=" . $bill_id);
        exit();
    }

    // F. Simpan Pembagian Item (Matrix Checklist Shares)
    elseif ($action === 'save_shares') {
        $shares = $_POST['shares'] ?? []; // format: [item_id => [member_id, member_id, ...]]
        
        try {
            $pdo->beginTransaction();
            
            // 1. Ambil semua item_id di tagihan ini untuk di-reset
            $stmt = $pdo->prepare("SELECT id FROM items WHERE bill_id = :bill_id");
            $stmt->execute(['bill_id' => $bill_id]);
            $item_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($item_ids)) {
                // Hapus data pembagian lama untuk item-item di tagihan ini
                $in_placeholders = implode(',', array_fill(0, count($item_ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM item_shares WHERE item_id IN ($in_placeholders)");
                $stmt->execute($item_ids);
            }
            
            // 2. Insert data pembagian yang baru
            if (!empty($shares)) {
                $stmt = $pdo->prepare("INSERT INTO item_shares (item_id, member_id) VALUES (:item_id, :member_id)");
                foreach ($shares as $item_id => $member_list) {
                    // Pastikan item_id tersebut milik tagihan ini (validasi kepemilikan data)
                    if (in_array($item_id, $item_ids)) {
                        foreach ($member_list as $member_id) {
                            $stmt->execute([
                                'item_id' => intval($item_id),
                                'member_id' => intval($member_id)
                            ]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            $_SESSION['bill_success'] = "Pembagian tagihan berhasil diperbarui.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['bill_error'] = "Gagal menyimpan pembagian: " . $e->getMessage();
        }
        header("Location: bill-detail.php?id=" . $bill_id);
        exit();
    }

    // G. Edit Detail Tagihan
    elseif ($action === 'edit_bill') {
        $title = validate_input($_POST['title'] ?? '');
        $tax_percent = floatval($_POST['tax_percent'] ?? 0);
        $service_percent = floatval($_POST['service_percent'] ?? 0);

        if (empty($title) || $tax_percent < 0 || $service_percent < 0) {
            $_SESSION['bill_error'] = "Judul tagihan tidak boleh kosong, Pajak & Servis tidak boleh negatif.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE bills SET title = :title, tax_percent = :tax_percent, service_percent = :service_percent WHERE id = :id AND user_id = :user_id");
                $stmt->execute([
                    'title' => $title,
                    'tax_percent' => $tax_percent,
                    'service_percent' => $service_percent,
                    'id' => $bill_id,
                    'user_id' => $user_id
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

                $_SESSION['bill_success'] = "Detail tagihan berhasil diperbarui.";
            } catch (PDOException $e) {
                $_SESSION['bill_error'] = "Gagal memperbarui tagihan: " . $e->getMessage();
            }
        }
        header("Location: bill-detail.php?id=" . $bill_id);
        exit();
    }

    // H. Hapus Tagihan secara penuh
    elseif ($action === 'delete_bill') {
        try {
            $stmt = $pdo->prepare("SELECT id FROM bills WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $bill_id, 'user_id' => $user_id]);
            $bill_exists = $stmt->fetch();
            
            if ($bill_exists) {
                $stmt = $pdo->prepare("DELETE FROM bills WHERE id = :id");
                $stmt->execute(['id' => $bill_id]);
                $_SESSION['bill_success'] = "Tagihan berhasil dihapus.";
                header("Location: dashboard.php");
                exit();
            } else {
                $_SESSION['bill_error'] = "Akses ditolak atau tagihan tidak ditemukan.";
            }
        } catch (PDOException $e) {
            $_SESSION['bill_error'] = "Gagal menghapus tagihan: " . $e->getMessage();
        }
        header("Location: bill-detail.php?id=" . $bill_id);
        exit();
    }
}

// 3. Ambil data Anggota, Item, dan Pembagian untuk ditampilkan di View
try {
    // Ambil Anggota
    $stmt = $pdo->prepare("SELECT * FROM members WHERE bill_id = :bill_id ORDER BY id ASC");
    $stmt->execute(['bill_id' => $bill_id]);
    $members = $stmt->fetchAll();
    
    // Ambil Item (Menggunakan Query Kompleks 1 - JOIN untuk mendapatkan nama sharer)
    $stmt = $pdo->prepare("
        SELECT i.id, i.item_name, i.price, i.qty, 
               (i.price * i.qty) AS total_price,
               GROUP_CONCAT(m.name SEPARATOR ', ') AS shared_with,
               COUNT(ish.member_id) AS total_sharers
        FROM items i
        LEFT JOIN item_shares ish ON i.id = ish.item_id
        LEFT JOIN members m ON ish.member_id = m.id
        WHERE i.bill_id = :bill_id
        GROUP BY i.id
        ORDER BY i.id ASC
    ");
    $stmt->execute(['bill_id' => $bill_id]);
    $items = $stmt->fetchAll();

    // Mapping item shares saat ini untuk mencentang checkbox secara default
    $current_shares = [];
    if (!empty($items)) {
        $item_ids = array_column($items, 'id');
        $in_placeholders = implode(',', array_fill(0, count($item_ids), '?'));
        $stmt = $pdo->prepare("SELECT item_id, member_id FROM item_shares WHERE item_id IN ($in_placeholders)");
        $stmt->execute($item_ids);
        $shares_raw = $stmt->fetchAll();
        
        foreach ($shares_raw as $row) {
            $current_shares[$row['item_id']][] = $row['member_id'];
        }
    }
    
    // Hitung Pembagian Per Anggota (Menggunakan Query Kompleks 2 - Subquery & JOIN dengan NULLIF)
    $stmt = $pdo->prepare("
        SELECT m.id AS member_id, m.name AS member_name,
               COALESCE(SUM(item_cost_per_person.share_cost), 0) AS subtotal,
               COALESCE(SUM(item_cost_per_person.share_cost), 0) * (1 + (b.tax_percent / 100) + (b.service_percent / 100)) AS total_owed
        FROM members m
        JOIN bills b ON m.bill_id = b.id
        LEFT JOIN item_shares ish ON m.id = ish.member_id
        LEFT JOIN (
            SELECT i.id AS item_id, 
                   (i.price * i.qty) / NULLIF((SELECT COUNT(*) FROM item_shares WHERE item_id = i.id), 0) AS share_cost
            FROM items i
            WHERE i.bill_id = :item_bill_id
        ) item_cost_per_person ON ish.item_id = item_cost_per_person.item_id
        WHERE m.bill_id = :member_bill_id
        GROUP BY m.id
        ORDER BY m.id ASC
    ");
    $stmt->execute(['item_bill_id' => $bill_id, 'member_bill_id' => $bill_id]);
    $member_calculations = $stmt->fetchAll();

    // Tarik total ringkasan dari View Database (View 2)
    $stmt = $pdo->prepare("SELECT * FROM view_bill_totals WHERE bill_id = :bill_id");
    $stmt->execute(['bill_id' => $bill_id]);
    $bill_totals = $stmt->fetch();

} catch (PDOException $e) {
    $error = "Terjadi kegagalan penarikan data: " . $e->getMessage();
}

require_once '../includes/header.php';
?>

<div class="container px-4">
    <!-- Breadcrumb & Back button -->
    <div class="mb-4">
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-chevron-left"></i> Kembali ke Dashboard
        </a>
    </div>

    <!-- Header Detail Tagihan -->
    <div class="card card-custom border-0 shadow mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center g-3">
                <div class="col-12 col-md-8">
                    <span class="badge bg-primary-soft text-primary mb-2">Tagihan Aktif</span>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <h2 class="fw-bold text-white mb-0"><?php echo h($bill['title']); ?></h2>
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#editBillModal">
                            <i class="bi bi-pencil-square me-1"></i> Edit Detail
                        </button>
                        <form action="bill-detail.php?id=<?php echo $bill_id; ?>" method="POST" class="delete-bill-form d-inline">
                            <input type="hidden" name="action" value="delete_bill">
                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                                <i class="bi bi-trash me-1"></i> Hapus Tagihan
                            </button>
                        </form>
                    </div>
                    <div class="d-flex flex-wrap gap-3 mt-2 text-muted small">
                        <span><i class="bi bi-calendar3 me-1"></i> Dibuat: <?php echo date('d M Y, H:i', strtotime($bill['created_at'])); ?></span>
                        <span><i class="bi bi-percent me-1"></i> Pajak: <?php echo floatval($bill['tax_percent']); ?>%</span>
                        <span><i class="bi bi-shield-check me-1"></i> Servis: <?php echo floatval($bill['service_percent']); ?>%</span>
                    </div>
                </div>
                <div class="col-12 col-md-4 text-md-end">
                    <h5 class="text-muted small fw-semibold mb-1">Grand Total (Triggers Sync)</h5>
                    <h2 class="fw-bold text-gradient-primary mb-0"><?php echo format_rupiah($bill['total_amount']); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Tampilkan Alert -->
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

    <!-- Main Content: Grid System (Bootstrap) -->
    <div class="row g-4">
        <!-- Kolom Kiri: Kelola Anggota -->
        <div class="col-12 col-lg-4">
            <div class="card card-custom border-0 shadow mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-white mb-3 d-flex align-items-center">
                        <i class="bi bi-people-fill text-primary me-2"></i> Kelola Teman
                        <span class="badge bg-primary-soft text-primary ms-auto small"><?php echo count($members); ?> Orang</span>
                    </h5>
                    
                    <!-- Form Tambah Anggota (dengan validasi input) -->
                    <form action="bill-detail.php?id=<?php echo $bill_id; ?>" method="POST" class="mb-4 needs-validation" novalidate>
                        <input type="hidden" name="action" value="add_member">
                        <div class="input-group">
                            <input type="text" class="form-control bg-dark border-secondary border-opacity-25 text-white" name="name" placeholder="Nama teman baru..." required>
                            <button type="submit" class="btn btn-primary" type="button"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    </form>

                    <!-- Daftar Anggota -->
                    <?php if (empty($members)): ?>
                        <div class="text-center py-3 text-muted small">
                            <i class="bi bi-people fs-3 d-block mb-2 text-secondary"></i>
                            Belum ada teman yang ditambahkan.
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush border-top border-secondary border-opacity-10">
                            <?php foreach ($members as $member): ?>
                                <li class="list-group-item bg-transparent text-white d-flex justify-content-between align-items-center px-0 py-2.5">
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-letter bg-primary-soft text-primary rounded-circle d-flex align-items-center justify-content-center me-2.5" style="width: 32px; height: 32px; font-weight: 600; font-size: 0.85rem;">
                                            <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                                        </div>
                                        <span><?php echo h($member['name']); ?></span>
                                    </div>
                                    <!-- Form Delete Anggota dengan Event Listener JS -->
                                    <form action="bill-detail.php?id=<?php echo $bill_id; ?>" method="POST" class="delete-member-form">
                                        <input type="hidden" name="action" value="delete_member">
                                        <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                        <button type="submit" class="btn btn-link text-danger border-0 p-0 shadow-none" aria-label="Hapus Anggota">
                                            <i class="bi bi-trash small"></i>
                                        </button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Kolom Kanan: Kelola Makanan & Pembagian -->
        <div class="col-12 col-lg-8">
            <div class="card card-custom border-0 shadow">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap g-2">
                        <h5 class="fw-bold text-white mb-0 d-flex align-items-center">
                            <i class="bi bi-egg-fried text-primary me-2"></i> Daftar Makanan / Item
                        </h5>
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addItemModal">
                            <i class="bi bi-plus-lg me-1"></i> Tambah Item
                        </button>
                    </div>

                    <?php if (empty($items)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-receipt fs-1 d-block mb-3 text-secondary"></i>
                            Belum ada item makanan/tagihan. Tambahkan item di atas!
                        </div>
                    <?php else: ?>
                        <!-- Form Matrix Checklist Pembagian -->
                        <form action="bill-detail.php?id=<?php echo $bill_id; ?>" method="POST">
                            <input type="hidden" name="action" value="save_shares">
                            
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle mb-4">
                                    <thead>
                                        <tr class="text-muted small">
                                            <th scope="col" style="min-width: 150px;">Nama Item</th>
                                            <th scope="col" class="text-end">Harga</th>
                                            <th scope="col" class="text-center" style="width: 70px;">Qty</th>
                                            <th scope="col" class="text-end">Total</th>
                                            <th scope="col" style="min-width: 250px;" class="ps-4">Dibagi Dengan (Centang Teman)</th>
                                            <th scope="col" class="text-center" style="width: 80px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td>
                                                    <span class="fw-semibold text-white d-block"><?php echo h($item['item_name']); ?></span>
                                                    <?php if (!empty($item['shared_with'])): ?>
                                                        <span class="text-primary small d-block" style="font-size: 0.75rem;">
                                                            <i class="bi bi-people-fill me-1"></i><?php echo h($item['shared_with']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-warning small d-block" style="font-size: 0.75rem;">
                                                            <i class="bi bi-exclamation-triangle-fill me-1"></i>Belum dibagi!
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end"><?php echo format_rupiah($item['price']); ?></td>
                                                <td class="text-center"><?php echo $item['qty']; ?></td>
                                                <td class="text-end text-gradient-primary fw-semibold"><?php echo format_rupiah($item['total_price']); ?></td>
                                                <td class="ps-4">
                                                    <!-- Checklist Anggota untuk Item ini -->
                                                    <?php if (empty($members)): ?>
                                                        <span class="text-muted small italic">Masukkan teman dulu di kolom kiri</span>
                                                    <?php else: ?>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <?php foreach ($members as $member): 
                                                                $is_checked = isset($current_shares[$item['id']]) && in_array($member['id'], $current_shares[$item['id']]);
                                                            ?>
                                                                <div class="form-check-button">
                                                                    <input type="checkbox" 
                                                                           class="btn-check" 
                                                                           id="share_<?php echo $item['id']; ?>_<?php echo $member['id']; ?>" 
                                                                           name="shares[<?php echo $item['id']; ?>][]" 
                                                                           value="<?php echo $member['id']; ?>" 
                                                                           autocomplete="off"
                                                                           <?php echo $is_checked ? 'checked' : ''; ?>>
                                                                    <label class="btn btn-outline-secondary btn-xs rounded-pill" for="share_<?php echo $item['id']; ?>_<?php echo $member['id']; ?>">
                                                                        <?php echo h($member['name']); ?>
                                                                    </label>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <div class="d-flex justify-content-center gap-2">
                                                        <!-- Edit Item Button triggers JS handler -->
                                                        <button type="button" 
                                                                class="btn btn-link text-primary p-0 border-0 edit-item-btn shadow-none" 
                                                                data-id="<?php echo $item['id']; ?>"
                                                                data-name="<?php echo h($item['item_name']); ?>"
                                                                data-price="<?php echo $item['price']; ?>"
                                                                data-qty="<?php echo $item['qty']; ?>"
                                                                aria-label="Edit Item">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <!-- Delete Item Button -->
                                                        <button type="button" 
                                                                class="btn btn-link text-danger p-0 border-0 delete-item-trigger shadow-none" 
                                                                data-id="<?php echo $item['id']; ?>"
                                                                aria-label="Hapus Item">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if (!empty($members)): ?>
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow">
                                        <i class="bi bi-check-all me-1"></i> Simpan Pembagian
                                    </button>
                                </div>
                            <?php endif; ?>
                        </form>

                        <!-- Hidden form for deleting item -->
                        <form id="deleteItemForm" action="bill-detail.php?id=<?php echo $bill_id; ?>" method="POST" class="d-none">
                            <input type="hidden" name="action" value="delete_item">
                            <input type="hidden" name="item_id" id="delete_item_id" value="">
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Hasil Akhir (Perhitungan Split Bill) -->
    <div class="row mt-4 mb-5">
        <div class="col-12">
            <div class="card card-custom border-0 shadow">
                <div class="card-body p-4 p-md-5">
                    <h4 class="fw-bold text-white mb-4"><i class="bi bi-calculator text-primary me-2"></i>Rincian Pembayaran Per Orang</h4>
                    
                    <div class="row g-4">
                        <!-- Rincian Tiap Anggota -->
                        <div class="col-12 col-lg-7">
                            <div class="table-responsive">
                                <table class="table table-dark table-hover align-middle">
                                    <thead>
                                        <tr class="text-muted small">
                                            <th scope="col">Nama Teman</th>
                                            <th scope="col" class="text-end">Subtotal Porsi Makanan</th>
                                            <th scope="col" class="text-end">Porsi Pajak + Servis</th>
                                            <th scope="col" class="text-end text-primary">Total Harus Dibayar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($member_calculations)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted small">Belum ada anggota atau kalkulasi pembagian item.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($member_calculations as $calc): 
                                                $tax_share = $calc['subtotal'] * ($bill['tax_percent'] / 100);
                                                $service_share = $calc['subtotal'] * ($bill['service_percent'] / 100);
                                                $tax_service_total = $tax_share + $service_share;
                                            ?>
                                                <tr>
                                                    <td class="fw-semibold text-white">
                                                        <i class="bi bi-person me-1.5 text-muted"></i><?php echo h($calc['member_name']); ?>
                                                    </td>
                                                    <td class="text-end"><?php echo format_rupiah($calc['subtotal']); ?></td>
                                                    <td class="text-end text-muted"><?php echo format_rupiah($tax_service_total); ?></td>
                                                    <td class="text-end text-gradient-primary fw-bold"><?php echo format_rupiah($calc['total_owed']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Ringkasan Bill secara Global (Menggunakan data View 2) -->
                        <div class="col-12 col-lg-5 ps-lg-5">
                            <div class="bg-dark bg-opacity-20 p-4 rounded-4 border border-secondary border-opacity-10">
                                <h5 class="fw-bold text-white mb-3">Ringkasan Tagihan</h5>
                                <div class="d-flex justify-content-between mb-2 small text-muted">
                                    <span>Subtotal Item</span>
                                    <span><?php echo format_rupiah($bill_totals['subtotal_amount'] ?? 0); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2 small text-muted">
                                    <span>Total Pajak (<?php echo floatval($bill['tax_percent']); ?>%)</span>
                                    <span><?php echo format_rupiah($bill_totals['tax_amount'] ?? 0); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-3 small text-muted">
                                    <span>Total Servis (<?php echo floatval($bill['service_percent']); ?>%)</span>
                                    <span><?php echo format_rupiah($bill_totals['service_amount'] ?? 0); ?></span>
                                </div>
                                <div class="border-top border-secondary border-opacity-25 pt-3 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-white">Total Tagihan Akhir</span>
                                    <span class="fw-bold text-gradient-primary fs-4"><?php echo format_rupiah($bill_totals['grand_total_amount'] ?? 0); ?></span>
                                </div>
                                <div class="mt-4 text-center">
                                    <span class="badge bg-success-soft text-success rounded-pill py-1.5 px-3 small">
                                        <i class="bi bi-check-circle-fill me-1"></i>Semua kalkulasi presisi 100%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Tambah Makanan / Item -->
<div class="modal fade modal-custom" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-0 shadow">
            <div class="modal-header border-bottom border-secondary border-opacity-10 p-4 pb-3">
                <h5 class="modal-title fw-bold text-white" id="addItemModalLabel"><i class="bi bi-egg-fried me-2 text-primary"></i>Tambah Makanan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addItemForm" action="bill-detail.php?id=<?php echo $bill_id; ?>" method="POST" class="needs-validation" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="add_item">
                    
                    <div class="mb-3">
                        <label for="item_name" class="form-label text-muted small fw-semibold">Nama Makanan / Item</label>
                        <input type="text" class="form-control bg-dark border-secondary border-opacity-25 text-white" id="item_name" name="item_name" placeholder="Contoh: Nasi Goreng Gila" required>
                        <div class="invalid-feedback">Nama item wajib diisi.</div>
                    </div>

                    <div class="row">
                        <div class="col-8">
                            <div class="mb-3">
                                <label for="price" class="form-label text-muted small fw-semibold">Harga Satuan (Rp)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">Rp</span>
                                    <input type="number" min="1" class="form-control bg-dark border-secondary border-opacity-25 text-white" id="price" name="price" placeholder="Contoh: 25000" required>
                                </div>
                                <div class="invalid-feedback">Harga harus lebih dari 0.</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mb-3">
                                <label for="qty" class="form-label text-muted small fw-semibold">Jumlah (Qty)</label>
                                <input type="number" min="1" class="form-control bg-dark border-secondary border-opacity-25 text-white" id="qty" name="qty" value="1" required>
                                <div class="invalid-feedback">Qty min 1.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-10 p-4 pt-3">
                    <button type="button" class="btn btn-outline-secondary px-4 rounded-pill" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4">Tambah Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit Makanan / Item -->
<div class="modal fade modal-custom" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-0 shadow">
            <div class="modal-header border-bottom border-secondary border-opacity-10 p-4 pb-3">
                <h5 class="modal-title fw-bold text-white" id="editItemModalLabel"><i class="bi bi-pencil-square me-2 text-primary"></i>Ubah Makanan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editItemForm" action="bill-detail.php?id=<?php echo $bill_id; ?>" method="POST" class="needs-validation" novalidate>
                <div class="modal-body p-4">
                    <input type="hidden" name="action" value="edit_item">
                    <input type="hidden" name="item_id" id="edit_item_id" value="">
                    
                    <div class="mb-3">
                        <label for="edit_item_name" class="form-label text-muted small fw-semibold">Nama Makanan / Item</label>
                        <input type="text" class="form-control bg-dark border-secondary border-opacity-25 text-white" id="edit_item_name" name="item_name" required>
                        <div class="invalid-feedback">Nama item wajib diisi.</div>
                    </div>

                    <div class="row">
                        <div class="col-8">
                            <div class="mb-3">
                                <label for="edit_price" class="form-label text-muted small fw-semibold">Harga Satuan (Rp)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">Rp</span>
                                    <input type="number" min="1" class="form-control bg-dark border-secondary border-opacity-25 text-white" id="edit_price" name="price" required>
                                </div>
                                <div class="invalid-feedback">Harga harus lebih dari 0.</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="mb-3">
                                <label for="edit_qty" class="form-label text-muted small fw-semibold">Jumlah (Qty)</label>
                                <input type="number" min="1" class="form-control bg-dark border-secondary border-opacity-25 text-white" id="edit_qty" name="qty" required>
                                <div class="invalid-feedback">Qty min 1.</div>
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

<!-- Modal: Edit Detail Tagihan -->
<div class="modal fade modal-custom" id="editBillModal" tabindex="-1" aria-labelledby="editBillModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content card-custom border-0 shadow">
            <div class="modal-header border-bottom border-secondary border-opacity-10 p-4 pb-3">
                <h5 class="modal-title fw-bold text-white" id="editBillModalLabel"><i class="bi bi-pencil-square me-2 text-primary"></i>Ubah Detail Tagihan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editBillForm" action="bill-detail.php?id=<?php echo $bill_id; ?>" method="POST" class="needs-validation" novalidate>
                <div class="modal-body text-start p-4">
                    <input type="hidden" name="action" value="edit_bill">
                    
                    <div class="mb-3">
                        <label for="edit_bill_title" class="form-label text-muted small fw-semibold">Judul Tagihan</label>
                        <input type="text" class="form-control bg-dark border-secondary border-opacity-25 text-white" id="edit_bill_title" name="title" value="<?php echo h($bill['title']); ?>" required>
                        <div class="invalid-feedback">Judul tagihan wajib diisi.</div>
                    </div>

                    <div class="row">
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="edit_bill_tax" class="form-label text-muted small fw-semibold">Pajak (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control bg-dark border-secondary border-opacity-25 text-white" id="edit_bill_tax" name="tax_percent" value="<?php echo floatval($bill['tax_percent']); ?>" required>
                                    <span class="input-group-text bg-dark border-secondary border-opacity-25 text-muted">%</span>
                                </div>
                                <div class="invalid-feedback">Pajak tidak boleh kosong / negatif.</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="mb-3">
                                <label for="edit_bill_service" class="form-label text-muted small fw-semibold">Service Charge (%)</label>
                                <div class="input-group">
                                    <input type="number" step="0.01" min="0" class="form-control bg-dark border-secondary border-opacity-25 text-white" id="edit_bill_service" name="service_percent" value="<?php echo floatval($bill['service_percent']); ?>" required>
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

<?php require_once '../includes/footer.php'; ?>

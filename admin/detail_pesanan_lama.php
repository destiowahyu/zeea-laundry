<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
include '../includes/db.php';

// Ambil ID pesanan dari URL
$id_pesanan = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$id_pesanan) {
    echo "ID pesanan tidak valid. ";
    echo "Silahkan kembali ke halaman sebelumnya.";
    exit();
}

// Ambil data pesanan
$query = "SELECT p.*, pel.nama AS nama_pelanggan, pel.no_hp, pak.nama AS nama_paket, pak.harga AS harga_paket 
          FROM pesanan p 
          JOIN pelanggan pel ON p.id_pelanggan = pel.id
          JOIN paket pak ON p.id_paket = pak.id
          WHERE p.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_pesanan);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Pesanan tidak ditemukan.";
    exit();
}

$data = $result->fetch_assoc();

// Format status untuk tampilan
$status_class = '';
$status_text = $data['status'];
$payment_status = isset($_SESSION['payment_status_'.$id_pesanan]) ? $_SESSION['payment_status_'.$id_pesanan] : '';

// Format status lengkap
$full_status = $status_text;
if ($status_text === 'selesai' && !empty($payment_status)) {
    $full_status = 'selesai - ' . $payment_status;
}

// Set class berdasarkan status
switch ($full_status) {
    case 'diproses':
        $status_class = 'bg-warning text-dark';
        break;
    case 'selesai - sudah lunas':
        $status_class = 'bg-success text-white';
        break;
    case 'selesai - belum lunas':
        $status_class = 'bg-info text-white';
        break;
    case 'selesai':
        $status_class = 'bg-success text-white';
        break;
    case 'dibatalkan':
        $status_class = 'bg-danger text-white';
        break;
    default:
        $status_class = 'bg-secondary text-white';
}

// Proses update status jika ada request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_status'])) {
        // Update status menjadi selesai
        $new_status = 'selesai';
        
        $query_update = "UPDATE pesanan SET status = ? WHERE id = ?";
        $stmt_update = $conn->prepare($query_update);
        $stmt_update->bind_param("si", $new_status, $id_pesanan);
        
        if ($stmt_update->execute()) {
            // Simpan status pembayaran di session untuk ditampilkan
            $_SESSION['payment_status_'.$id_pesanan] = $_POST['payment_status'];
            
            // Refresh data setelah update
            header("Location: detail_pesanan.php?id=$id_pesanan&success=1");
            exit();
        } else {
            $error_message = "Gagal mengupdate status: " . $conn->error;
        }
    } elseif (isset($_POST['mark_paid'])) {
        // Update status pembayaran menjadi lunas
        $_SESSION['payment_status_'.$id_pesanan] = 'sudah lunas';
        
        // Refresh data setelah update
        header("Location: detail_pesanan.php?id=$id_pesanan&success=3");
        exit();
    } elseif (isset($_POST['update_pesanan'])) {
        // Update data pesanan
        $berat = floatval($_POST['berat']);
        $id_paket = intval($_POST['id_paket']);
        
        // Ambil harga paket
        $query_paket = "SELECT harga FROM paket WHERE id = ?";
        $stmt_paket = $conn->prepare($query_paket);
        $stmt_paket->bind_param("i", $id_paket);
        $stmt_paket->execute();
        $result_paket = $stmt_paket->get_result();
        $paket_data = $result_paket->fetch_assoc();
        
        // Hitung total harga
        $harga_total = $berat * $paket_data['harga'];
        
        // Update pesanan
        $query_update = "UPDATE pesanan SET id_paket = ?, berat = ?, harga = ? WHERE id = ?";
        $stmt_update = $conn->prepare($query_update);
        $stmt_update->bind_param("iddi", $id_paket, $berat, $harga_total, $id_pesanan);
        
        if ($stmt_update->execute()) {
            header("Location: detail_pesanan.php?id=$id_pesanan&success=2");
            exit();
        } else {
            $error_message = "Gagal mengupdate pesanan: " . $conn->error;
        }
    }
}

// Ambil daftar paket untuk modal edit
$query_paket_list = "SELECT id, nama, harga FROM paket ORDER BY nama ASC";
$result_paket_list = $conn->query($query_paket_list);
$paket_list = [];
while ($row = $result_paket_list->fetch_assoc()) {
    $paket_list[] = $row;
}

// Format pesan WhatsApp
$phone = preg_replace('/[^0-9]/', '', $data['no_hp']);
$tanggal = date('d F Y', strtotime($data['waktu']));
$waktu = date('H:i', strtotime($data['waktu']));
$berat = number_format($data['berat'], 2);
$paket = $data['nama_paket'];
$harga = number_format($data['harga'], 0, ',', '.');

$message = "Ini adalah pesan otomatis dari Zeea Laundry.\n\n";
$message .= "Pelanggan yth, pesanan atas nama {$data['nama_pelanggan']} pada tanggal {$tanggal} pukul {$waktu} ";
$message .= "dengan total berat cucian {$berat} kg dan paket {$paket}\n";
$message .= "total harganya adalah Rp {$harga}\n\n";
$message .= "TELAH SELESAI.\n\n";

// Tambahkan informasi pembayaran jika belum lunas
if ($full_status === 'selesai - belum lunas') {
    $message .= "Silahkan melakukan pembayaran melalui QRIS berikut:\n";
    $message .= "Berikut adalah foto QRIS untuk melakukan pembayaran.\n\n";
}

$message .= "TERIMAKASIH TELAH MENGGUNAKAN JASA ZEEA LAUNDRY.";

// URL untuk mengirim gambar QR code jika belum lunas
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
$qris_url = $base_url . "/zeea_laundry/assets/images/qriszeealaundry.jpg";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan - Admin Zeea Laundry</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.min.css">
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.all.min.js"></script>
    <style>
        .status-badge {
            font-size: 14px;
            padding: 8px 12px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .detail-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .detail-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
        }
        
        .detail-value {
            font-weight: 500;
        }
        
        .price-value {
            font-weight: bold;
            color: #28a745;
        }
        
        .action-buttons {
            margin-top: 20px;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 20px;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            width: 2px;
            height: 100%;
            background-color: #dee2e6;
        }
        
        .timeline-item:last-child:before {
            height: 0;
        }
        
        .timeline-badge {
            position: absolute;
            left: -38px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: #42c3cf;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #42c3cf;
        }
        
        .timeline-date {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .timeline-content {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .status-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            
            .container {
                max-width: 100% !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <!-- Overlay -->
    <div class="overlay no-print" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <?php include 'sidebar_admin.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php if ($_GET['success'] == '1'): ?>
                Status pesanan berhasil diperbarui.
                <?php elseif ($_GET['success'] == '2'): ?>
                Data pesanan berhasil diperbarui.
                <?php elseif ($_GET['success'] == '3'): ?>
                Status pembayaran berhasil diperbarui menjadi lunas.
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Detail Pesanan #<?= $id_pesanan ?></h1>
                <div class="no-print">
                    <button onclick="window.print()" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-print"></i> Cetak
                    </button>
                    <a href="pesanan.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
            
            <!-- Status Pesanan Header -->
            <div class="status-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Status Pesanan:</h4>
                <span class="status-badge <?= $status_class ?>"><?= ucfirst($full_status) ?></span>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <!-- Informasi Pesanan -->
                    <div class="card-admin detail-card">
                        <div class="card-header">Informasi Pesanan</div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">ID Pesanan</div>
                                <div class="col-md-8 detail-value">#<?= $id_pesanan ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">Tanggal Pesanan</div>
                                <div class="col-md-8 detail-value"><?= date('d F Y, H:i', strtotime($data['waktu'])) ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">Nama Pelanggan</div>
                                <div class="col-md-8 detail-value"><?= htmlspecialchars($data['nama_pelanggan']) ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">No. HP</div>
                                <div class="col-md-8 detail-value"><?= htmlspecialchars($data['no_hp']) ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">Paket Laundry</div>
                                <div class="col-md-8 detail-value"><?= htmlspecialchars($data['nama_paket']) ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">Berat</div>
                                <div class="col-md-8 detail-value"><?= number_format($data['berat'], 2) ?> kg</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">Harga per Kg</div>
                                <div class="col-md-8 detail-value">Rp <?= number_format($data['harga_paket'], 0, ',', '.') ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4">Total Harga</div>
                                <div class="col-md-8 price-value">Rp <?= number_format($data['harga'], 0, ',', '.') ?></div>
                            </div>
                            
                            <!-- Tombol Aksi -->
                            <div class="d-flex mt-4">
                                <?php if ($full_status === 'selesai - belum lunas'): ?>
                                <!-- Tombol untuk menandai pesanan sudah lunas -->
                                <form action="detail_pesanan.php?id=<?= $id_pesanan ?>" method="POST" class="me-2">
                                    <button type="submit" name="mark_paid" class="btn btn-success">
                                        <i class="fas fa-check-circle"></i> Pesanan Lunas
                                    </button>
                                </form>
                                
                                <!-- Tombol WhatsApp dengan QRIS -->
                                <a href="https://wa.me/<?= $phone ?>?text=<?= urlencode($message) ?>" 
                                   target="_blank" 
                                   class="btn btn-success" 
                                   id="whatsappButton"
                                   onclick="sendQRIS()">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </a>
                                <?php elseif ($data['status'] === 'diproses'): ?>
                                <!-- Tombol untuk menandai pesanan selesai -->
                                <button type="button" class="btn btn-primary me-2" id="btnChangeStatus">
                                    <i class="fas fa-check-circle"></i> Pesanan Selesai
                                </button>
                                
                                <!-- Tombol WhatsApp biasa -->
                                <a href="https://wa.me/<?= $phone ?>?text=<?= urlencode($message) ?>" 
                                   target="_blank" 
                                   class="btn btn-success" 
                                   id="whatsappButton">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </a>
                                <?php else: ?>
                                <!-- Tombol WhatsApp biasa untuk status lainnya -->
                                <a href="https://wa.me/<?= $phone ?>?text=<?= urlencode($message) ?>" 
                                   target="_blank" 
                                   class="btn btn-success" 
                                   id="whatsappButton">
                                    <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Tombol Edit dan Hapus -->
                    <div class="card-admin detail-card no-print">
                        <div class="card-header">Kelola Pesanan</div>
                        <div class="card-body">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editPesananModal">
                                    <i class="fas fa-edit"></i> Edit Pesanan
                                </button>
                                
                                <button type="button" class="btn btn-danger" id="btnDelete">
                                    <i class="fas fa-trash"></i> Hapus Pesanan
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Timeline Pesanan -->
                    <div class="card-admin detail-card">
                        <div class="card-header">Timeline Pesanan</div>
                        <div class="card-body">
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="timeline-badge"></div>
                                    <div class="timeline-date"><?= date('d F Y, H:i', strtotime($data['waktu'])) ?></div>
                                    <div class="timeline-content">
                                        <h6>Pesanan Dibuat</h6>
                                        <p>Pesanan telah dibuat dan menunggu diproses.</p>
                                    </div>
                                </div>
                                
                                <?php if ($data['status'] === 'selesai'): ?>
                                <div class="timeline-item">
                                    <div class="timeline-badge"></div>
                                    <div class="timeline-date"><?= date('d F Y, H:i', strtotime($data['waktu'] . ' +1 day')) ?></div>
                                    <div class="timeline-content">
                                        <h6>Pesanan Selesai</h6>
                                        <p>Pesanan telah selesai diproses dan siap diambil.</p>
                                        <?php if (!empty($payment_status)): ?>
                                            <?php if ($payment_status === 'sudah lunas'): ?>
                                            <p><span class="badge bg-success">Pembayaran Lunas</span></p>
                                            <?php else: ?>
                                            <p><span class="badge bg-warning text-dark">Belum Lunas</span></p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php elseif ($data['status'] === 'dibatalkan'): ?>
                                <div class="timeline-item">
                                    <div class="timeline-badge"></div>
                                    <div class="timeline-date"><?= date('d F Y, H:i', strtotime($data['waktu'] . ' +1 hour')) ?></div>
                                    <div class="timeline-content">
                                        <h6>Pesanan Dibatalkan</h6>
                                        <p>Pesanan telah dibatalkan.</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Placeholder untuk layout balance -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit Pesanan -->
    <div class="modal fade" id="editPesananModal" tabindex="-1" aria-labelledby="editPesananModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPesananModalLabel">Edit Pesanan #<?= $id_pesanan ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="detail_pesanan.php?id=<?= $id_pesanan ?>" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="id_paket" class="form-label">Paket Laundry</label>
                            <select class="form-select" id="id_paket" name="id_paket" required>
                                <?php foreach ($paket_list as $paket): ?>
                                <option value="<?= $paket['id'] ?>" <?= ($paket['id'] == $data['id_paket']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($paket['nama']) ?> - Rp <?= number_format($paket['harga'], 0, ',', '.') ?>/kg
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="berat" class="form-label">Berat (kg)</label>
                            <input type="number" class="form-control" id="berat" name="berat" step="0.1" min="0.5" value="<?= $data['berat'] ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_pesanan" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Form untuk submit status -->
    <form id="changeStatusForm" action="detail_pesanan.php?id=<?= $id_pesanan ?>" method="POST" style="display: none;">
        <input type="hidden" name="change_status" value="1">
        <input type="hidden" name="payment_status" id="payment_status" value="">
    </form>

    <script>
        $(document).ready(function() {
            // Konfirmasi hapus pesanan
            $('#btnDelete').click(function() {
                Swal.fire({
                    title: 'Hapus Pesanan?',
                    text: "Pesanan yang dihapus tidak dapat dikembalikan!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Ya, hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Redirect ke proses hapus
                        window.location.href = 'hapus_pesanan.php?id=<?= $id_pesanan ?>';
                    }
                });
            });
            
            // Konfirmasi pesanan selesai dan status pembayaran
            $('#btnChangeStatus').click(function() {
                Swal.fire({
                    title: 'Konfirmasi Pesanan',
                    text: "Apakah pesanan benar-benar sudah selesai?",
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Ya, sudah selesai',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Konfirmasi pembayaran
                        Swal.fire({
                            title: 'Status Pembayaran',
                            text: "Apakah pesanan ini sudah dibayar?",
                            icon: 'question',
                            showCancelButton: true,
                            showDenyButton: true,
                            confirmButtonColor: '#28a745',
                            denyButtonColor: '#ffc107',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Sudah Lunas',
                            denyButtonText: 'Belum Lunas',
                            cancelButtonText: 'Batal'
                        }).then((paymentResult) => {
                            if (paymentResult.isConfirmed) {
                                // Sudah lunas
                                $('#payment_status').val('sudah lunas');
                                $('#changeStatusForm').submit();
                            } else if (paymentResult.isDenied) {
                                // Belum lunas
                                $('#payment_status').val('belum lunas');
                                $('#changeStatusForm').submit();
                            }
                        });
                    }
                });
            });
        });
        
        // Fungsi untuk mengirim gambar QRIS setelah mengirim pesan WhatsApp
        function sendQRIS() {
            // Buka tab baru untuk WhatsApp
            // Setelah beberapa detik, buka tab baru untuk gambar QRIS
            setTimeout(function() {
                window.open('<?= $qris_url ?>', '_blank');
            }, 1000);
        }
    </script>
</body>
</html>
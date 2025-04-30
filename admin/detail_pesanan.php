<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}


date_default_timezone_set('Asia/Jakarta');

$current_page = basename($_SERVER['PHP_SELF']);
include '../includes/db.php';


$adminUsername = $_SESSION['username'];
$query_admin = $conn->prepare("SELECT id, username FROM admin WHERE username = ?");
$query_admin->bind_param("s", $adminUsername);
$query_admin->execute();
$result_admin = $query_admin->get_result();

if ($result_admin->num_rows === 0) {
    echo "Data admin tidak ditemukan. Silakan login kembali.";
    exit();
}

$adminData = $result_admin->fetch_assoc();
$adminId = $adminData['id'];
$adminName = $adminData['username'];


if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: pesanan.php");
    exit();
}

$pesanan_id = $_GET['id'];


$query_pesanan_utama = "SELECT p.*, pl.nama as nama_pelanggan, pl.no_hp 
                 FROM pesanan p 
                 JOIN pelanggan pl ON p.id_pelanggan = pl.id 
                 WHERE p.id = $pesanan_id";

$result_pesanan_utama = $conn->query($query_pesanan_utama);

if ($result_pesanan_utama->num_rows === 0) {
    echo "Pesanan tidak ditemukan. Silahkan kembali ke halaman sebelumnya.";
    exit();
}

$pesanan_utama = $result_pesanan_utama->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_all'])) {
        $new_status = $_POST['status'];
        $new_payment_status = $_POST['status_pembayaran'];
        
       
        $id_pelanggan = $pesanan_utama['id_pelanggan'];
        $waktu_pesanan = $pesanan_utama['waktu'];
        
        
        $update_sql = "UPDATE pesanan SET status = '$new_status', status_pembayaran = '$new_payment_status' 
                      WHERE id_pelanggan = $id_pelanggan AND waktu = '$waktu_pesanan'";
        
        
        if ($conn->query($update_sql)) {
            $success_message = "Status pesanan berhasil diperbarui menjadi " . ucfirst($new_status) . " dan status pembayaran menjadi " . 
                              ($new_payment_status === 'sudah_dibayar' ? 'Sudah Dibayar' : 'Belum Dibayar') . ".";
            
            
            if ($new_status === 'selesai') {
                $tgl_selesai = date('Y-m-d');
                
                $get_all_ids = $conn->query("SELECT id, harga FROM pesanan WHERE id_pelanggan = $id_pelanggan AND waktu = '$waktu_pesanan'");
                
                while ($pesanan_item = $get_all_ids->fetch_assoc()) {
                    $item_id = $pesanan_item['id'];
                    $item_harga = $pesanan_item['harga'];
                    
                    $check_riwayat = $conn->query("SELECT id FROM riwayat WHERE id_pesanan = $item_id");
                    
                    if ($check_riwayat->num_rows === 0) {
                        $conn->query("INSERT INTO riwayat (id_pesanan, tgl_selesai, harga) VALUES ($item_id, '$tgl_selesai', $item_harga)");
                    }
                }
            }
            
            $result_pesanan_utama = $conn->query($query_pesanan_utama);
            $pesanan_utama = $result_pesanan_utama->fetch_assoc();
        } else {
            $error_message = "Gagal memperbarui status pesanan: " . $conn->error;
        }
    }
}

$id_pelanggan = $pesanan_utama['id_pelanggan'];
$waktu_pesanan = $pesanan_utama['waktu'];

$query_semua_item = "SELECT p.*, pk.nama as nama_paket, pk.harga as harga_paket 
                    FROM pesanan p 
                    JOIN paket pk ON p.id_paket = pk.id 
                    WHERE p.id_pelanggan = $id_pelanggan 
                    AND p.waktu = '$waktu_pesanan'";

$result_semua_item = $conn->query($query_semua_item);
$items_pesanan = [];
$total_harga_semua = 0;

while ($item = $result_semua_item->fetch_assoc()) {
    $items_pesanan[] = $item;
    $total_harga_semua += $item['harga'];
}


function formatTanggalIndonesia($tanggal) {
    $hari = array(
        'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
    );
    
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    $timestamp = strtotime($tanggal);
    $hari_ini = $hari[date('w', $timestamp)];
    $tanggal_ini = date('j', $timestamp);
    $bulan_ini = $bulan[date('n', $timestamp)];
    $tahun_ini = date('Y', $timestamp);
    $jam = date('H:i', $timestamp);
    
    return "$hari_ini, $tanggal_ini $bulan_ini $tahun_ini $jam";
}

$tanggal_sekarang = formatTanggalIndonesia(date('Y-m-d H:i:s'));
$tanggal_pesanan = formatTanggalIndonesia($pesanan_utama['waktu']);

function getStatusBadge($status) {
    switch ($status) {
        case 'diproses':
            return '<span class="badge bg-warning text-dark">Diproses</span>';
        case 'selesai':
            return '<span class="badge bg-success">Selesai</span>';
        case 'dibatalkan':
            return '<span class="badge bg-danger">Dibatalkan</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

//status pembayaran
function getPaymentStatusBadge($status) {
    switch ($status) {
        case 'belum_dibayar':
            return '<span class="badge bg-danger">Belum Dibayar</span>';
        case 'sudah_dibayar':
            return '<span class="badge bg-success">Sudah Dibayar</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

$query_layanan = "SELECT * FROM antar_jemput WHERE id_pesanan = $pesanan_id";
$result_layanan = $conn->query($query_layanan);
$layanan = $result_layanan->fetch_assoc();


$harga_formatted = number_format($total_harga_semua, 0, ',', '.');
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Pesanan</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.min.css">
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.20/dist/sweetalert2.all.min.js"></script>
    <style>
        .content {
            padding: 60px 80px;
        }
        @media (max-width: 768px) {
            .content{
                padding : 60px 30px;
            }
        }
        .detail-card {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .detail-header {
            background-color: #42c3cf;
            color: white;
            padding: 20px;
            position: relative;
        }
        
        .detail-header h2 {
            margin: 0;
            font-size: 24px;
        }
        
        .detail-body {
            padding: 25px;
            background-color: white;
        }
        
        .detail-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-section h3 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 500;
            color: #666;
        }
        
        .detail-value {
            font-weight: 600;
            text-align: right;
        }
        
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        
        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .action-buttons .btn {
            flex: 1;
            margin: 0 5px;
            border-radius: 10px;
            padding: 12px;
            font-weight: 500;
        }
        
        .action-buttons .btn:first-child {
            margin-left: 0;
        }
        
        .action-buttons .btn:last-child {
            margin-right: 0;
        }
        
        .btn-whatsapp {
            background-color: #25D366;
            color: white;
        }
        
        .btn-whatsapp:hover {
            background-color: #128C7E;
            color: white;
        }
        
        .btn-print {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-print:hover {
            background-color: #5a6268;
            color: white;
        }
        
        .current-date {
            font-size: 14px;
            color: #6c757d;
            text-align: right;
            margin-bottom: 15px;
        }
        
        .status-form {
            margin-top: 20px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .price-highlight {
            font-size: 24px;
            color: #28a745;
            font-weight: bold;
        }
        
        .customer-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .customer-info i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
            color: #42c3cf;
        }
        
        .back-button {
            margin-bottom: 20px;
        }
        
        .status-badges {
            display: flex;
            gap: 10px;
            position: absolute;
            top: 20px;
            right: 20px;
        }
        
        .paket-item {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #42c3cf;
        }
        
        .paket-item:last-child {
            margin-bottom: 0;
        }
        
        .paket-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #dee2e6;
        }
        
        .paket-item-title {
            font-weight: bold;
            font-size: 16px;
            color: #333;
        }
        
        .paket-item-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .paket-item-label {
            color: #666;
        }
        
        .paket-item-value {
            font-weight: 500;
        }
        
        .total-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            font-weight: 500;
            margin-bottom: 5px;
            display: block;
        }
        
        .update-btn {
            background-color: #42c3cf;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .update-btn:hover {
            background-color: #38adb8;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Styling untuk form status pesanan dan pembayaran */
        .status-pesanan-section {
            background-color: #e8f4f8;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #42c3cf;
            margin-bottom: 15px;
        }
        
        .status-pembayaran-section {
            background-color: #f8f0e8;
            border-radius: 8px;
            padding: 15px;
            border-left: 4px solid #ff9800;
            margin-bottom: 15px;
        }
        
        .status-label {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
        }
        
        .status-pesanan-label {
            color: #0c7b93;
        }
        
        .status-pembayaran-label {
            color: #d35400;
        }
        
        .status-icon {
            margin-right: 8px;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
        }
        
        .status-pesanan-icon {
            background-color: #42c3cf;
        }
        
        .status-pembayaran-icon {
            background-color: #ff9800;
        }
        
        /* Custom SweetAlert Styling */
        .custom-swal-popup {
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }
        
        .custom-swal-title {
            font-size: 22px;
            color: #333;
            margin-bottom: 15px;
        }
        
        .custom-swal-content {
            font-size: 16px;
            color: #555;
            margin-bottom: 20px;
        }
        
        .custom-swal-confirm {
            background-color: #42c3cf !important;
            border-radius: 8px !important;
            font-weight: 500 !important;
            padding: 12px 25px !important;
            margin-right: 10px !important;
        }
        
        .custom-swal-cancel {
            background-color: #f8f9fa !important;
            color: #333 !important;
            border-radius: 8px !important;
            font-weight: 500 !important;
            padding: 12px 25px !important;
            box-shadow: none !important;
            border: 1px solid #dee2e6 !important;
        }
        
        .custom-swal-icon {
            border-color: #42c3cf !important;
            color: #42c3cf !important;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                padding: 0;
                margin: 0;
            }
            
            .detail-card {
                box-shadow: none;
                margin: 0;
            }
            
            .container {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
        }

        /* Mobile styles */
        @media (max-width: 576px) {
            .detail-header {
                padding: 15px;
            }
            
            .detail-header h2 {
                font-size: 18px;
                margin-bottom: 25px;
            }
            
            .status-badges {
                position: static;
                margin-top: 10px;
                justify-content: flex-start;
                flex-wrap: wrap;
            }
            
            .detail-body {
                padding: 15px;
            }
            
            .detail-section h3 {
                font-size: 16px;
            }
            
            .paket-item {
                padding: 12px;
            }
            
            .paket-item-header {
                flex-direction: column;
            }
            
            .paket-item-id {
                margin-top: 5px;
                font-size: 12px;
            }
            
            .paket-item-details {
                flex-direction: column;
                margin-bottom: 8px;
            }
            
            .paket-item-value {
                margin-top: 2px;
            }
            
            .detail-item {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 12px;
            }
            
            .detail-value {
                text-align: left;
                margin-top: 3px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .action-buttons .btn {
                margin: 0;
            }
            
            .status-form {
                padding: 15px;
            }
            
            .form-group {
                margin-bottom: 12px;
            }
            
            .customer-info p {
                margin-bottom: 8px;
            }
            
            .total-section {
                padding: 12px;
            }
            
            .total-row {
                font-size: 16px;
            }
            
            .price-highlight {
                font-size: 20px;
            }
            
            .status-pesanan-section,
            .status-pembayaran-section {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar_admin.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">

    <div class="back-button no-print">
                <a href="pesanan.php" class="btn btn-outline-secondary" id="backButton">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar Pesanan
                </a>
            </div>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="detail-card">
                <div class="detail-header">
                    <h2>Detail Pesanan</h2>
                    <div class="status-badges">
                        <?php echo getStatusBadge($pesanan_utama['status']); ?>
                        <?php echo getPaymentStatusBadge($pesanan_utama['status_pembayaran']); ?>
                    </div>
                </div>
                
                <div class="detail-body">
                    <div class="detail-section">
                        <h3><i class="fas fa-user-circle"></i> Informasi Pelanggan</h3>
                        <div class="customer-info">
                            <p><i class="fas fa-user"></i> <strong>Nama:</strong> <?php echo $pesanan_utama['nama_pelanggan']; ?></p>
                            <p><i class="fas fa-phone"></i> <strong>No. HP:</strong> <?php echo $pesanan_utama['no_hp']; ?></p>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h3><i class="fas fa-box-open"></i> Detail Paket</h3>
                        
                        <?php if (count($items_pesanan) > 0): ?>
                            <?php foreach ($items_pesanan as $item): ?>
                                <div class="paket-item">
                                    <div class="paket-item-header">
                                        <div class="paket-item-title"><?php echo $item['nama_paket']; ?></div>
                                        <div class="paket-item-id">ID: #<?php echo $item['id']; ?></div>
                                    </div>
                                    <div class="paket-item-details">
                                        <span class="paket-item-label">Harga per Kg:</span>
                                        <span class="paket-item-value">Rp <?php 
                                            // Jika paket khusus dan harga_custom ada, gunakan harga_custom
                                            if ($item['nama_paket'] === 'Paket Khusus' && isset($item['harga_custom']) && $item['harga_custom'] > 0) {
                                                echo number_format($item['harga_custom'], 0, ',', '.');
                                            } else {
                                                echo number_format($item['harga_paket'], 0, ',', '.');
                                            }
                                        ?></span>
                                    </div>
                                    <div class="paket-item-details">
                                        <span class="paket-item-label">Berat:</span>
                                        <span class="paket-item-value"><?php echo number_format($item['berat'], 2, ',', '.'); ?> kg</span>
                                    </div>
                                    <div class="paket-item-details">
                                        <span class="paket-item-label">Total Harga:</span>
                                        <span class="paket-item-value">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="total-section">
                                <div class="total-row">
                                    <span>Total Pesanan:</span>
                                    <span class="price-highlight">Rp <?php echo number_format($total_harga_semua, 0, ',', '.'); ?></span>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Tidak ada data paket tersedia.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="detail-section">
                        <h3><i class="fas fa-info-circle"></i> Informasi Pesanan</h3>
                        <div class="detail-item">
                            <span class="detail-label">Tanggal Pesanan:</span>
                            <span class="detail-value"><?php echo $tanggal_pesanan; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status Pesanan:</span>
                            <span class="detail-value"><?php echo getStatusBadge($pesanan_utama['status']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Status Pembayaran:</span>
                            <span class="detail-value"><?php echo getPaymentStatusBadge($pesanan_utama['status_pembayaran']); ?></span>
                        </div>
                        
                        <?php if ($layanan): ?>
                        <div class="detail-item">
                            <span class="detail-label">Layanan Antar/Jemput:</span>
                            <span class="detail-value"><?php echo ucfirst($layanan['layanan']); ?></span>
                        </div>
                        
                        <?php if ($layanan['alamat_jemput']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Alamat Jemput:</span>
                            <span class="detail-value"><?php echo $layanan['alamat_jemput']; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($layanan['alamat_antar']): ?>
                        <div class="detail-item">
                            <span class="detail-label">Alamat Antar:</span>
                            <span class="detail-value"><?php echo $layanan['alamat_antar']; ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detail-item">
                            <span class="detail-label">Status Layanan:</span>
                            <span class="detail-value"><?php echo ucfirst($layanan['status']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Combined Form for Update Status with Different Colors -->
                    <div class="status-form no-print">
                        <h3><i class="fas fa-edit"></i> Update Status Pesanan</h3>
                        <form method="POST" id="updateStatusForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="status-pesanan-section">
                                        <div class="status-label status-pesanan-label">
                                            <div class="status-icon status-pesanan-icon">
                                                <i class="fas fa-tasks"></i>
                                            </div>
                                            Status Pesanan:
                                        </div>
                                        <select name="status" id="status" class="form-select">
                                            <option value="diproses" <?php echo ($pesanan_utama['status'] === 'diproses') ? 'selected' : ''; ?>>Diproses</option>
                                            <option value="selesai" <?php echo ($pesanan_utama['status'] === 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                                            <option value="dibatalkan" <?php echo ($pesanan_utama['status'] === 'dibatalkan') ? 'selected' : ''; ?>>Dibatalkan</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="status-pembayaran-section">
                                        <div class="status-label status-pembayaran-label">
                                            <div class="status-icon status-pembayaran-icon">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </div>
                                            Status Pembayaran:
                                        </div>
                                        <select name="status_pembayaran" id="status_pembayaran" class="form-select">
                                            <option value="belum_dibayar" <?php echo ($pesanan_utama['status_pembayaran'] === 'belum_dibayar') ? 'selected' : ''; ?>>Belum Dibayar</option>
                                            <option value="sudah_dibayar" <?php echo ($pesanan_utama['status_pembayaran'] === 'sudah_dibayar') ? 'selected' : ''; ?>>Sudah Dibayar</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12">
                                    <button type="submit" name="update_all" class="update-btn">
                                        <i class="fas fa-save"></i> Update Status
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <div class="action-buttons no-print">
                        <button id="whatsappBtn" class="btn btn-whatsapp">
                            <i class="fab fa-whatsapp"></i> Hubungi via WhatsApp
                        </button>
                        <button onclick="window.print()" class="btn btn-print">
                            <i class="fas fa-print"></i> Cetak Detail
                        </button>
                    </div>
                </div>
            </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handling WhatsApp button
        $(document).ready(function() {
            $('#whatsappBtn').on('click', function() {
                const orderStatus = '<?php echo $pesanan_utama['status']; ?>';
                
                if (orderStatus === 'selesai') {
                    // If status is completed, proceed with WhatsApp
                    const phoneNumber = '<?php echo str_replace(['+', ' '], '', $pesanan_utama['no_hp']); ?>';
                    
                    const message = `
*INI ADALAH PESAN OTOMATIS DARI SISTEM LAYANAN ZEEA LAUNDRY*

Halo *<?php echo $pesanan_utama['nama_pelanggan']; ?>*,
Pesanan laundry Anda dengan detail berikut telah *<?php echo ucfirst($pesanan_utama['status']); ?>*:

*Detail Pesanan:*
- ID Pesanan : #<?php echo $pesanan_id; ?>

- Tanggal Pesanan : <?php echo $tanggal_pesanan; ?>


*Detail Item:*
<?php foreach ($items_pesanan as $item): ?>
- Paket: <?php echo $item['nama_paket']; ?> 
- Berat: <?php echo number_format($item['berat'], 2, ',', '.'); ?> kg
- Harga per kg: Rp <?php 
    if ($item['nama_paket'] === 'Paket Khusus' && isset($item['harga_custom']) && $item['harga_custom'] > 0) {
        echo number_format($item['harga_custom'], 0, ',', '.');
    } else {
        echo number_format($item['harga_paket'], 0, ',', '.');
    }
?> 
- Subtotal: Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?>


<?php endforeach; ?>
- Total Harga Yang Harus Dibayar : *Rp <?php echo $harga_formatted; ?>*
- Status Pembayaran : *<?php echo ($pesanan_utama['status_pembayaran'] === 'sudah_dibayar') ? 'Sudah Dibayar' : 'Belum Dibayar'; ?>*

Terima kasih telah menggunakan jasa Zeea Laundry.
Silakan ambil cucian Anda di toko kami.

Salam, *Zeea Laundry*
RT.02/RW.03, Jambangan, Padaran, Kabupaten Rembang, Jawa Tengah 59219
Whatsapp : 0895395442010
`;
                    
                    window.open(`https://wa.me/${phoneNumber}?text=${encodeURIComponent(message)}`, '_blank');
                } else {
                    // If status is not completed, show warning
                    Swal.fire({
                        title: 'Perhatian!',
                        text: 'Anda harus mengubah status pesanan menjadi "Selesai" terlebih dahulu sebelum menghubungi pelanggan via WhatsApp.',
                        icon: 'warning',
                        confirmButtonText: 'Mengerti'
                    });
                }
            });
            
            // Konfigurasi SweetAlert2 custom
            const swalWithBootstrapButtons = Swal.mixin({
                customClass: {
                    popup: 'custom-swal-popup',
                    title: 'custom-swal-title',
                    content: 'custom-swal-content',
                    confirmButton: 'custom-swal-confirm',
                    cancelButton: 'custom-swal-cancel',
                    icon: 'custom-swal-icon'
                },
                buttonsStyling: false
            });
            
            // Track if form has been changed
            let formChanged = false;
            const originalStatus = $('#status').val();
            const originalPaymentStatus = $('#status_pembayaran').val();
            
            // Listen for changes on select elements
            $('#status, #status_pembayaran').on('change', function() {
                const currentStatus = $('#status').val();
                const currentPaymentStatus = $('#status_pembayaran').val();
                
                // Check if any value has changed
                if (currentStatus !== originalStatus || currentPaymentStatus !== originalPaymentStatus) {
                    formChanged = true;
                    
                    // Highlight the update button to draw attention
                    $('.update-btn').addClass('animate__animated animate__pulse animate__infinite');
                } else {
                    formChanged = false;
                    $('.update-btn').removeClass('animate__animated animate__pulse animate__infinite');
                }
            });
            
            // Reset form changed flag when form is submitted
            $('#updateStatusForm').on('submit', function() {
                formChanged = false;
            });
            
            // Show confirmation dialog when leaving page with unsaved changes
            window.addEventListener('beforeunload', function(e) {
                if (formChanged) {
                    // Standard message (browsers will show their own message)
                    const confirmationMessage = 'Anda telah mengubah status tetapi belum menyimpannya. Apakah Anda yakin ingin meninggalkan halaman ini?';
                    
                    // For older browsers
                    e.returnValue = confirmationMessage;
                    
                    // For modern browsers
                    return confirmationMessage;
                }
            });
            
            // Add confirmation to back button and other links
            $('#backButton, a:not([href^="#"]):not([href^="javascript"]):not([href^="mailto"]):not([href^="tel"])').on('click', function(e) {
                if (formChanged) {
                    e.preventDefault();
                    
                    const targetHref = $(this).attr('href');
                    
                    swalWithBootstrapButtons.fire({
                        title: '<i class="fas fa-exclamation-triangle text-warning mr-2"></i> Perubahan Belum Disimpan!',
                        html: '<div class="mb-3">Anda telah mengubah status pesanan tetapi belum menyimpannya.</div><div>Apa yang ingin Anda lakukan?</div>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: '<i class="fas fa-save mr-2"></i> Simpan Perubahan',
                        cancelButtonText: '<i class="fas fa-times mr-2"></i> Tinggalkan Halaman',
                        reverseButtons: true,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        allowEnterKey: false,
                        backdrop: true,
                        showClass: {
                            popup: 'animate__animated animate__fadeInDown animate__faster'
                        },
                        hideClass: {
                            popup: 'animate__animated animate__fadeOutUp animate__faster'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // User wants to save changes
                            $('#updateStatusForm').submit();
                        } else {
                            // User wants to leave without saving
                            formChanged = false;
                            window.location.href = targetHref;
                        }
                    });
                }
            });
            
            // Add animation library
            const animateCSSLink = document.createElement('link');
            animateCSSLink.rel = 'stylesheet';
            animateCSSLink.href = 'https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css';
            document.head.appendChild(animateCSSLink);
        });
    </script>
</body>
</html>


<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

date_default_timezone_set('Asia/Jakarta');

$current_page = 'pesanan.php';
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

// Check if tracking code is provided
if (!isset($_GET['tracking']) || empty($_GET['tracking'])) {
    header("Location: pesanan.php");
    exit();
}

$tracking_code = $_GET['tracking'];

// Query to get order details by tracking code
$query_pesanan_utama = "SELECT p.*, pl.nama as nama_pelanggan, pl.no_hp 
                 FROM pesanan p 
                 JOIN pelanggan pl ON p.id_pelanggan = pl.id 
                 WHERE p.tracking_code = ? 
                 LIMIT 1";

$stmt = $conn->prepare($query_pesanan_utama);
$stmt->bind_param("s", $tracking_code);
$stmt->execute();
$result_pesanan_utama = $stmt->get_result();

if ($result_pesanan_utama->num_rows === 0) {
    echo "Pesanan tidak ditemukan. Silahkan kembali ke halaman sebelumnya.";
    exit();
}

$pesanan_utama = $result_pesanan_utama->fetch_assoc();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_all'])) {
        $new_status = $_POST['status'];
        $new_payment_status = $_POST['status_pembayaran'];
        
        // Update all orders with the same tracking code
        $update_sql = "UPDATE pesanan SET status = ?, status_pembayaran = ? WHERE tracking_code = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sss", $new_status, $new_payment_status, $tracking_code);
        
        if ($update_stmt->execute()) {
            $success_message = "Status pesanan berhasil diperbarui menjadi " . ucfirst($new_status) . " dan status pembayaran menjadi " . 
                              ($new_payment_status === 'sudah_dibayar' ? 'Sudah Dibayar' : 'Belum Dibayar') . ".";
            
            if ($new_status === 'selesai') {
                $tgl_selesai = date('Y-m-d');
                
                // Get all related orders
                $get_all_ids = $conn->prepare("SELECT id, harga FROM pesanan WHERE tracking_code = ?");
                $get_all_ids->bind_param("s", $tracking_code);
                $get_all_ids->execute();
                $all_orders = $get_all_ids->get_result();
                
                while ($pesanan_item = $all_orders->fetch_assoc()) {
                    $item_id = $pesanan_item['id'];
                    $item_harga = $pesanan_item['harga'];
                    
                    $check_riwayat = $conn->prepare("SELECT id FROM riwayat WHERE id_pesanan = ?");
                    $check_riwayat->bind_param("i", $item_id);
                    $check_riwayat->execute();
                    
                    if ($check_riwayat->get_result()->num_rows === 0) {
                        $insert_riwayat = $conn->prepare("INSERT INTO riwayat (id_pesanan, tgl_selesai, harga) VALUES (?, ?, ?)");
                        $insert_riwayat->bind_param("isd", $item_id, $tgl_selesai, $item_harga);
                        $insert_riwayat->execute();
                    }
                }
            }
            
            // Refresh data
            $stmt->execute();
            $result_pesanan_utama = $stmt->get_result();
            $pesanan_utama = $result_pesanan_utama->fetch_assoc();
        } else {
            $error_message = "Gagal memperbarui status pesanan: " . $conn->error;
        }
    }
    
    // Handle edit order
    if (isset($_POST['edit_order'])) {
        $order_id = $_POST['order_id'];
        $new_berat = floatval($_POST['berat']);
        $new_paket_id = intval($_POST['paket_id']);
        $harga_custom = isset($_POST['harga_custom']) ? floatval($_POST['harga_custom']) : null;
        
        // Get package price
        $get_paket = $conn->prepare("SELECT harga FROM paket WHERE id = ?");
        $get_paket->bind_param("i", $new_paket_id);
        $get_paket->execute();
        $paket_result = $get_paket->get_result();
        $paket_data = $paket_result->fetch_assoc();
        
        // Calculate new price
        $harga_per_kg = $harga_custom ? $harga_custom : $paket_data['harga'];
        $new_total = $new_berat * $harga_per_kg;
        
        // Update order
        $update_order_sql = "UPDATE pesanan SET id_paket = ?, berat = ?, harga = ?, harga_custom = ? WHERE id = ?";
        $update_order_stmt = $conn->prepare($update_order_sql);
        $update_order_stmt->bind_param("idddi", $new_paket_id, $new_berat, $new_total, $harga_custom, $order_id);
        
        if ($update_order_stmt->execute()) {
            $success_message = "Pesanan berhasil diperbarui.";
            
            // Refresh data
            $stmt->execute();
            $result_pesanan_utama = $stmt->get_result();
            $pesanan_utama = $result_pesanan_utama->fetch_assoc();
        } else {
            $error_message = "Gagal memperbarui pesanan: " . $conn->error;
        }
    }
}

// Get all orders with the same tracking code
$query_semua_item = "SELECT p.*, pk.nama as nama_paket, pk.harga as harga_paket 
                    FROM pesanan p 
                    JOIN paket pk ON p.id_paket = pk.id 
                    WHERE p.tracking_code = ?
                    ORDER BY p.id ASC";

$stmt_items = $conn->prepare($query_semua_item);
$stmt_items->bind_param("s", $tracking_code);
$stmt_items->execute();
$result_semua_item = $stmt_items->get_result();

$items_pesanan = [];
$total_harga_semua = 0;

while ($item = $result_semua_item->fetch_assoc()) {
    $items_pesanan[] = $item;
    $total_harga_semua += $item['harga'];
}

// Get all packages for edit form
$query_paket = "SELECT id, nama, harga FROM paket ORDER BY nama";
$result_paket = $conn->query($query_paket);
$paket_options = [];
while ($paket = $result_paket->fetch_assoc()) {
    $paket_options[] = $paket;
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

// Check for antar jemput service
$query_layanan = "SELECT * FROM antar_jemput WHERE tracking_code = ? LIMIT 1";
$stmt_layanan = $conn->prepare($query_layanan);
$stmt_layanan->bind_param("s", $tracking_code);
$stmt_layanan->execute();
$result_layanan = $stmt_layanan->get_result();
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/clipboard.js/2.0.8/clipboard.min.js"></script>
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
            gap: 10px;
        }
        
        .action-buttons .btn {
            flex: 1;
            border-radius: 10px;
            padding: 12px;
            font-weight: 500;
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
            position: relative;
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
        
        /* Edit Button */
        .btn-edit {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #ffc107;
            color: #212529;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn-edit:hover {
            background: #e0a800;
            transform: scale(1.1);
        }
        
        /* Edit Form */
        .edit-form {
            display: none;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        
        .edit-form.show {
            display: block;
        }
        
        .edit-form .form-control {
            margin-bottom: 10px;
        }
        
        .edit-form .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .edit-form .btn-group .btn {
            flex: 1;
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
        
        /* Tracking code styles */
        .tracking-code-container {
            background-color: rgba(66, 195, 207, 0.1);
            border: 2px dashed #42c3cf;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .tracking-code-label {
            font-size: 14px;
            color: #495057;
            margin-bottom: 5px;
            display: block;
        }
        
        .tracking-code-value {
            font-size: 18px;
            font-weight: bold;
            color: #42c3cf !important;
            letter-spacing: 1px;
            word-break: break-all;
            max-width: 100%;
            background-color: transparent !important;
            border: none !important;
            padding: 0 !important;
        }
        
        .btn-copy {
            white-space: nowrap;
            background-color: white;
            color:rgb(81, 81, 81);
            border: 1px solid #42c3cf;
            border-radius: 5px;
            cursor: pointer;
            padding: 5px 10px;
            font-size: 16px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-copy:hover {
            transform: scale(1.1);
        }
        
        .btn-copy.copied {
            color: #42c3cf;
            animation: pulse 0.5s;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .custom-tooltip {
            position: absolute;
            background-color: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1000;
            pointer-events: none;
            white-space: nowrap;
        }
        
        .custom-tooltip::before {
            content: '';
            position: absolute;
            top: -5px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 0 5px 5px;
            border-style: solid;
            border-color: transparent transparent #333;
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
                margin-bottom: 10px;
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
            
            .tracking-code-container {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 10px;
                background-color: rgba(66, 195, 207, 0.1) !important;
            }
            
            .tracking-code-container > div {
                flex: 1 1 auto;
                min-width: 200px;
            }
            
            .tracking-code-value {
                font-size: 16px;
                word-break: break-all;
                background-color: transparent !important;
                padding: 8px 0;
                border-radius: 5px;
                display: inline-block;
                max-width: 100%;
                color: #42c3cf !important;
                border: none !important;
            }
            
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar-admin.php'; ?>

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
                    <?php if (!empty($pesanan_utama['tracking_code'])): ?>
                    <div class="tracking-code-container">
                        <div>
                            <span class="tracking-code-label">Kode Tracking:</span>
                            <div class="tracking-code-value"><?php echo $pesanan_utama['tracking_code']; ?></div>
                        </div>
                        <button type="button" class="btn-copy" data-clipboard-text="<?php echo $pesanan_utama['tracking_code']; ?>" title="Salin Kode Tracking">
                            <i class="fas fa-copy"></i> Salin
                        </button>
                    </div>
                    <?php endif; ?>
                    
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
                                        <div class="paket-item-title d-flex align-items-center gap-2">
                                            <?php if (!empty($item['icon'])): ?>
                                                <img src="../assets/uploads/paket_icons/<?php echo htmlspecialchars($item['icon']); ?>" alt="<?php echo htmlspecialchars($item['nama_paket']); ?>" style="width:32px;height:32px;object-fit:contain;vertical-align:middle;">
                                            <?php endif; ?>
                                            <span><?php echo $item['nama_paket']; ?></span>
                                        </div>
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
                                    <!-- Edit Form -->
                                    <div class="edit-form no-print" id="edit-form-<?php echo $item['id']; ?>">
                                        <form method="POST">
                                            <input type="hidden" name="order_id" value="<?php echo $item['id']; ?>">
                                            <div class="form-group">
                                                <label>Paket:</label>
                                                <select name="paket_id" class="form-control" required>
                                                    <?php foreach ($paket_options as $paket): ?>
                                                        <option value="<?php echo $paket['id']; ?>" 
                                                                data-harga="<?php echo $paket['harga']; ?>"
                                                                <?php echo $paket['id'] == $item['id_paket'] ? 'selected' : ''; ?>>
                                                            <?php echo $paket['nama']; ?> - Rp <?php echo number_format($paket['harga'], 0, ',', '.'); ?>/kg
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Berat (kg):</label>
                                                <input type="number" name="berat" class="form-control" step="0.01" min="0.01" 
                                                       value="<?php echo $item['berat']; ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Harga Custom (opsional, kosongkan untuk menggunakan harga paket):</label>
                                                <input type="number" name="harga_custom" class="form-control" step="0.01" min="0" 
                                                       value="<?php echo $item['harga_custom'] ?? ''; ?>" 
                                                       placeholder="Masukkan harga custom per kg">
                                            </div>
                                            <div class="btn-group">
                                                <button type="submit" name="edit_order" class="btn btn-success">
                                                    <i class="fas fa-save"></i> Simpan
                                                </button>
                                                <button type="button" class="btn btn-secondary" onclick="toggleEditForm(<?php echo $item['id']; ?>)">
                                                    <i class="fas fa-times"></i> Batal
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                    <!-- Dedicated Edit Button -->
                                    <button type="button" class="btn btn-warning mt-2 no-print" onclick="toggleEditForm(<?php echo $item['id']; ?>)">
                                        <i class="fas fa-edit"></i> Edit Pesanan
                                    </button>
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
                            <span class="detail-label">Layanan Antar Jemput:</span>
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
                        <button id="waBtn" class="btn btn-whatsapp">
                            <i class="fab fa-whatsapp"></i> Kirim WhatsApp
                        </button>
                        <a href="print-detail-pesanan.php?tracking=<?php echo urlencode($pesanan_utama['tracking_code']); ?>" target="_blank" class="btn btn-print no-print">
                            <i class="fas fa-print"></i> Cetak Struk
                        </a>
                    </div>
                </div>
            </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize clipboard.js
        var clipboard = new ClipboardJS('.btn-copy');

        clipboard.on('success', function(e) {
            // Show tooltip or notification
            const button = e.trigger;
            const originalTitle = button.getAttribute('title');

            button.setAttribute('title', 'Tersalin!');
            button.classList.add('copied');

            setTimeout(function() {
                button.setAttribute('title', originalTitle);
                button.classList.remove('copied');
            }, 1500);

            e.clearSelection();
        });

        // Add tooltip functionality
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.forEach(function(tooltipTriggerEl) {
            tooltipTriggerEl.addEventListener('mouseenter', function() {
                const tooltip = document.createElement('div');
                tooltip.className = 'custom-tooltip';
                tooltip.textContent = this.getAttribute('title');

                document.body.appendChild(tooltip);

                const rect = this.getBoundingClientRect();
                tooltip.style.top = rect.bottom + 5 + 'px';
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';

                this.addEventListener('mouseleave', function() {
                    document.body.removeChild(tooltip);
                }, { once: true });
            });
        });

        // Toggle edit form
        function toggleEditForm(orderId) {
            const form = document.getElementById('edit-form-' + orderId);
            if (form.classList.contains('show')) {
                form.classList.remove('show');
            } else {
                // Hide all other edit forms
                document.querySelectorAll('.edit-form').forEach(f => f.classList.remove('show'));
                form.classList.add('show');
            }
        }

        // Enhanced WhatsApp functionality
        $(document).ready(function() {
            // Handle WhatsApp button (otomatis sesuai status)
            $('#waBtn').on('click', function() {
                // Data dari PHP
                const status = '<?php echo $pesanan_utama['status']; ?>';
                const payment = '<?php echo $pesanan_utama['status_pembayaran']; ?>';
                const phone = '<?php echo str_replace(['+', ' '], '', $pesanan_utama['no_hp']); ?>';
                const name = '<?php echo $pesanan_utama['nama_pelanggan']; ?>';
                const tracking = '<?php echo $pesanan_utama['tracking_code']; ?>';
                const total = '<?php echo $harga_formatted; ?>';
                const date = '<?php echo $tanggal_pesanan; ?>';
                let message = '';
                const customerPageUrl = `http://localhost/zeea-laundry/pelanggan/tracking.php?code=${encodeURIComponent(tracking)}`;
                if (status === 'diproses') {
                    message = `*ZEEA LAUNDRY - NOTIFIKASI PESANAN*\n\n`;
                    message += `Halo *${name}*,\n\n`;
                    message += `Pesanan laundry Anda telah masuk ke sistem kami dan sedang *DIPROSES* 🔄\n\n`;
                    message += `*DETAIL PESANAN:*\n`;
                    message += `• Kode Tracking: *${tracking}*\n`;
                    message += `• Tanggal Pesanan: ${date}\n`;
                    message += `• Status: *Sedang Diproses*\n`;
                    message += `• Total Harga: *Rp ${total}*\n`;
                    message += `• Status Pembayaran: *${payment === 'sudah_dibayar' ? 'Sudah Dibayar' : 'Belum Dibayar'}*\n\n`;
                    let itemDetails = '';
                    <?php foreach ($items_pesanan as $item): ?>
                    itemDetails += `• Paket: <?php echo $item['nama_paket']; ?>\n`;
                    itemDetails += `• Berat: <?php echo number_format($item['berat'], 2, ',', '.'); ?> kg\n`;
                    itemDetails += `• Harga per kg: Rp <?php
                        if ($item['nama_paket'] === 'Paket Khusus' && isset($item['harga_custom']) && $item['harga_custom'] > 0) {
                            echo number_format($item['harga_custom'], 0, ',', '.');
                        } else {
                            echo number_format($item['harga_paket'], 0, ',', '.');
                        }
                    ?>\n`;
                    itemDetails += `• Subtotal: Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?>\n\n`;
                    <?php endforeach; ?>
                    message += `*DETAIL PAKET:*\n${itemDetails}`;
                    message += `*CEK STATUS PESANAN:*\n`;
                    message += `Klik link berikut untuk mengecek status pesanan Anda secara real-time:\n`;
                    message += `${customerPageUrl}\n\n`;
                    message += `Atau masukkan kode tracking *${tracking}* di halaman pelanggan website kami.\n\n`;
                    message += `Kami akan menghubungi Anda kembali ketika pesanan sudah selesai.\n\n`;
                    message += `Terima kasih telah mempercayakan cucian Anda kepada kami!\n\n`;
                    message += `*ZEEA LAUNDRY*\n`;
                    message += `RT.02/RW.03, Jambangan, Padaran, Kabupaten Rembang, Jawa Tengah 59219\n`;
                    message += `WhatsApp: 0895395442010`;
                } else if (status === 'selesai') {
                    message = `*ZEEA LAUNDRY - PESANAN SELESAI*\n\n`;
                    message += `Halo *${name}*,\n\n`;
                    message += `Kabar gembira! Pesanan laundry Anda telah *SELESAI* dan siap untuk diambil!\n\n`;
                    message += `*DETAIL PESANAN:*\n`;
                    message += `• Kode Tracking: *${tracking}*\n`;
                    message += `• Tanggal Pesanan: ${date}\n`;
                    message += `• Status: *SELESAI*\n`;
                    message += `• Total Harga: *Rp ${total}*\n`;
                    message += `• Status Pembayaran: *${payment === 'sudah_dibayar' ? 'Sudah Dibayar' : 'Belum Dibayar'}*\n\n`;
                    let itemDetails = '';
                    <?php foreach ($items_pesanan as $item): ?>
                    itemDetails += `• Paket: <?php echo $item['nama_paket']; ?>\n`;
                    itemDetails += `• Berat: <?php echo number_format($item['berat'], 2, ',', '.'); ?> kg\n`;
                    itemDetails += `• Harga per kg: Rp <?php
                        if ($item['nama_paket'] === 'Paket Khusus' && isset($item['harga_custom']) && $item['harga_custom'] > 0) {
                            echo number_format($item['harga_custom'], 0, ',', '.');
                        } else {
                            echo number_format($item['harga_paket'], 0, ',', '.');
                        }
                    ?>\n`;
                    itemDetails += `• Subtotal: Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?>\n\n`;
                    <?php endforeach; ?>
                    message += `*PAKET YANG SUDAH SELESAI:*\n${itemDetails}`;
                    message += `*CEK DETAIL LENGKAP:*\n`;
                    message += `Untuk melihat detail lengkap pesanan, klik link berikut:\n`;
                    message += `${customerPageUrl}\n\n`;
                    message += `*SILAKAN AMBIL CUCIAN ANDA:*\n`;
                    message += `Cucian Anda sudah siap dan dapat diambil di toko kami.\n\n`;
                    message += `*Jam Operasional:*\n`;
                    message += `Senin - Minggu: 06.30 - 21.00 WIB\n\n`;
                    if (payment === 'belum_dibayar') {
                        message += `*PERHATIAN:* Pembayaran belum lunas. Silakan lakukan pembayaran saat pengambilan.\n\n`;
                    }
                    message += `Terima kasih telah menggunakan jasa Zeea Laundry!\n`;
                    message += `Kepuasan Anda adalah prioritas kami.\n\n`;
                    message += `*ZEEA LAUNDRY*\n`;
                    message += `RT.02/RW.03, Jambangan, Padaran, Kabupaten Rembang, Jawa Tengah 59219\n`;
                    message += `WhatsApp: 0895395442010`;
                } else if (status === 'dibatalkan') {
                    message = `*ZEEA LAUNDRY - PESANAN DIBATALKAN*\n\n`;
                    message += `Halo *${name}*,\n\n`;
                    message += `Pesanan laundry Anda dengan kode tracking *${tracking}* telah *DIBATALKAN*.\n\n`;
                    message += `Jika ini tidak sesuai, silakan hubungi admin kami.\n\n`;
                    message += `*ZEEA LAUNDRY*\n`;
                    message += `RT.02/RW.03, Jambangan, Padaran, Kabupaten Rembang, Jawa Tengah 59219\n`;
                    message += `WhatsApp: 0895395442010`;
                }
                let cleanPhone = phone.replace(/[^0-9]/g, '');
                if (!cleanPhone.startsWith('62')) {
                    if (cleanPhone.startsWith('0')) {
                        cleanPhone = '62' + cleanPhone.substring(1);
                    } else {
                        cleanPhone = '62' + cleanPhone;
                    }
                }
                const whatsappUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;
                window.open(whatsappUrl, '_blank');
                Swal.fire({
                    title: 'WhatsApp Dibuka!',
                    text: 'Pesan telah disiapkan dan WhatsApp dibuka.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
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

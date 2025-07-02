<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include '../includes/db.php';

$current_page = basename($_SERVER['PHP_SELF']);

// Get service status from settings table (create if not exists)
$conn->query("CREATE TABLE IF NOT EXISTS `settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_name` varchar(50) NOT NULL,
    `setting_value` varchar(255) NOT NULL,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Insert default value if not exists
$conn->query("INSERT IGNORE INTO `settings` (`setting_name`, `setting_value`) VALUES ('antar_jemput_active', 'active');");

// Get current service status
$serviceStatusQuery = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'antar_jemput_active'");
$serviceStatus = $serviceStatusQuery->fetch_assoc()['setting_value'];

// Handle service status toggle
if (isset($_POST['toggle_service'])) {
    $newStatus = ($_POST['toggle_service'] === 'activate') ? 'active' : 'inactive';
    $updateQuery = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_name = 'antar_jemput_active'");
    $updateQuery->bind_param("s", $newStatus);
    
    if ($updateQuery->execute()) {
        $serviceStatus = $newStatus;
        $statusMessage = $newStatus === 'active' ? "Layanan antar jemput berhasil diaktifkan" : "Layanan antar jemput berhasil dinonaktifkan";
        $statusMessageType = "success";
    } else {
        $statusMessage = "Gagal memperbarui status layanan: " . $updateQuery->error;
        $statusMessageType = "danger";
    }
}

// Handle status update
if (isset($_POST['update_status'])) {
    $id = $_POST['request_id'];
    $newStatus = $_POST['new_status'];
    $updateTime = date('Y-m-d H:i:s');
    
    // Determine which timestamp to update based on the service type
    $stmt = $conn->prepare("SELECT layanan FROM antar_jemput WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $serviceType = $result->fetch_assoc()['layanan'];
    
    if ($newStatus === 'dalam perjalanan') {
        if ($serviceType === 'antar' || $serviceType === 'antar-jemput') {
            $updateQuery = $conn->prepare("UPDATE antar_jemput SET status = ?, waktu_antar = ? WHERE id = ?");
        } else {
            $updateQuery = $conn->prepare("UPDATE antar_jemput SET status = ?, waktu_jemput = ? WHERE id = ?");
        }
        $updateQuery->bind_param("ssi", $newStatus, $updateTime, $id);
    } else {
        $updateQuery = $conn->prepare("UPDATE antar_jemput SET status = ? WHERE id = ?");
        $updateQuery->bind_param("si", $newStatus, $id);
    }
    
    if ($updateQuery->execute()) {
        $statusMessage = "Status permintaan berhasil diperbarui.";
        $statusMessageType = "success";
    } else {
        $statusMessage = "Gagal memperbarui status: " . $updateQuery->error;
        $statusMessageType = "danger";
    }
}

// Get all pickup/delivery requests with customer information
$requestsQuery = "
    SELECT aj.*, p.id as pesanan_id, p.id_pelanggan, p.waktu as waktu_pesanan, p.harga as total_harga, 
           pel.nama as nama_pelanggan, pel.no_hp as telepon_pelanggan
    FROM antar_jemput aj
    LEFT JOIN pesanan p ON aj.id_pesanan = p.id
    LEFT JOIN pelanggan pel ON p.id_pelanggan = pel.id
    ORDER BY 
        CASE 
            WHEN aj.status = 'menunggu' THEN 1
            WHEN aj.status = 'dalam perjalanan' THEN 2
            WHEN aj.status = 'selesai' THEN 3
        END,
        aj.id DESC
";

$requestsResult = $conn->query($requestsQuery);
$requests = [];
if ($requestsResult) {
    while ($row = $requestsResult->fetch_assoc()) {
        $requests[] = $row;
    }
}

// Count requests by status
$countQuery = "
    SELECT 
        SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) as menunggu_count,
        SUM(CASE WHEN status = 'dalam perjalanan' THEN 1 ELSE 0 END) as perjalanan_count,
        SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai_count,
        COUNT(*) as total_count
    FROM antar_jemput
";
$countResult = $conn->query($countQuery);
$counts = $countResult->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Antar Jemput - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <style>
        .service-status-card {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
            border-left: 5px solid #42c3cf;
        }
        
        .service-status-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .service-status-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 15px;
        }
        
        .service-status-header h4 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }
        
        .service-status-header i {
            margin-right: 12px;
            font-size: 1.8rem;
            color: #42c3cf;
        }
        
        .status-toggle-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            background-color: var(--status-bg-color);
            border: 1px solid var(--status-border-color);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .status-toggle-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .status-text-container {
            display: flex;
            align-items: center;
        }
        
        .toggle-form {
            margin: 0;
        }
        
        .toggle-button {
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
            background-color: #ccc;
            border-radius: 34px;
            transition: .4s;
        }
        
        .toggle-slider {
            position: absolute;
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        .toggle-slider.active {
            transform: translateX(26px);
            background-color: white;
        }
        
        .toggle-switch:hover {
            opacity: 0.8;
        }
        
        .toggle-slider.active + .toggle-switch {
            background-color: #28a745;
        }
        
        .status-toggle-container .toggle-switch {
            background-color: var(--toggle-bg-color);
        }
        
        .status-info {
            background-color: rgba(66, 195, 207, 0.1);
            border-left: 4px solid #42c3cf;
            padding: 15px;
            border-radius: 0 12px 12px 0;
            font-size: 0.95rem;
            color: #555;
            margin-top: 20px;
            line-height: 1.6;
        }
        
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            flex: 1;
            min-width: 200px;
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
        }
        
        .stat-waiting {
            background: linear-gradient(135deg, #ffc107, #ff9800);
        }
        
        .stat-progress {
            background: linear-gradient(135deg, #42c3cf, #3498db);
        }
        
        .stat-completed {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .stat-total {
            background: linear-gradient(135deg, #6c757d, #495057);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .request-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .request-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .request-header {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .request-id {
            font-weight: 600;
            font-size: 1.1rem;
            color: #333;
        }
        
        .request-type {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .request-type.antar {
            background-color: rgba(52, 152, 219, 0.15);
            color: #3498db;
            border: 1px solid rgba(52, 152, 219, 0.3);
        }
        
        .request-type.jemput {
            background-color: rgba(155, 89, 182, 0.15);
            color: #9b59b6;
            border: 1px solid rgba(155, 89, 182, 0.3);
        }
        
        .request-type.antar-jemput {
            background-color: rgba(46, 204, 113, 0.15);
            color: #2ecc71;
            border: 1px solid rgba(46, 204, 113, 0.3);
        }
        
        .request-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .request-status.menunggu {
            background-color: rgba(255, 193, 7, 0.15);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        
        .request-status.dalam-perjalanan {
            background-color: rgba(66, 195, 207, 0.15);
            color: #42c3cf;
            border: 1px solid rgba(66, 195, 207, 0.3);
        }
        
        .request-status.selesai {
            background-color: rgba(40, 167, 69, 0.15);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .request-body {
            padding: 20px;
        }
        
        .customer-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-group {
            flex: 1;
            min-width: 200px;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
        }
        
        .address-box {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #42c3cf;
        }
        
        .address-box h6 {
            margin-top: 0;
            color: #42c3cf;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .request-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }
        
        .action-btn {
            padding: 10px 15px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .action-btn.whatsapp-btn {
            background-color: #25D366;
            color: white;
        }
        
        .action-btn.process-btn {
            background-color: #42c3cf;
            color: white;
        }
        
        .action-btn.complete-btn {
            background-color: #28a745;
            color: white;
        }
        
        .action-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .no-requests {
            background-color: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin-top: 20px;
        }
        
        .no-requests i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .no-requests h5 {
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .no-requests p {
            color: #6c757d;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .filter-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background-color: #42c3cf;
            border-color: #42c3cf;
            color: white;
        }
        
        /* Toast notification styles */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            min-width: 300px;
            max-width: 400px;
            animation: slideIn 0.3s ease forwards;
            opacity: 0;
            transform: translateX(100%);
        }
        
        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes slideOut {
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
        
        .toast.hide {
            animation: slideOut 0.3s ease forwards;
        }
        
        .toast-icon {
            width: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px 0;
            font-size: 1.5rem;
        }
        
        .toast-success .toast-icon {
            background-color: #28a745;
            color: white;
        }
        
        .toast-danger .toast-icon {
            background-color: #dc3545;
            color: white;
        }
        
        .toast-content {
            padding: 15px;
            flex: 1;
        }
        
        .toast-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .toast-message {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .toast-close {
            padding: 15px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            color: #6c757d;
            transition: color 0.2s;
        }
        
        .toast-close:hover {
            color: #333;
        }
        
        @media (max-width: 768px) {
            .status-actions {
                flex-direction: column;
            }
            
            .status-btn {
                width: 100%;
            }
            
            .stats-container {
                flex-direction: column;
            }
            
            .stat-card {
                width: 100%;
            }
            
            .request-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .request-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
            
            .toast-container {
                left: 20px;
                right: 20px;
            }
            
            .toast {
                width: 100%;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar-admin.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <div class="header-container" id="header">
                <h1>Manajemen Antar Jemput</h1>
            </div>

            <div class="content-wrapper">
                <!-- Toast Container for Notifications -->
                <div class="toast-container" id="toastContainer"></div>
                
                <!-- Service Status Card -->
                <div class="service-status-card">
                    <div class="service-status-header">
                        <i class="fas fa-truck"></i>
                        <h4>Status Layanan Antar Jemput</h4>
                    </div>
                    
                    <div class="status-toggle-container" id="statusToggleContainer" 
                         style="--status-bg-color: <?= $serviceStatus === 'active' ? 'rgba(40, 167, 69, 0.15)' : 'rgba(220, 53, 69, 0.15)' ?>; 
                                --status-border-color: <?= $serviceStatus === 'active' ? 'rgba(40, 167, 69, 0.3)' : 'rgba(220, 53, 69, 0.3)' ?>;
                                --toggle-bg-color: <?= $serviceStatus === 'active' ? '#28a745' : '#ccc' ?>;">
                        <div class="status-text-container">
                            <div class="status-indicator <?= $serviceStatus === 'active' ? 'status-active' : 'status-inactive' ?>"></div>
                            <div class="status-text">
                                Layanan Antar Jemput saat ini <?= $serviceStatus === 'active' ? 'AKTIF' : 'NONAKTIF' ?>
                            </div>
                        </div>
                        
                        <form method="post" action="" class="toggle-form" id="toggleForm">
                            <input type="hidden" name="toggle_service" value="<?= $serviceStatus === 'active' ? 'deactivate' : 'activate' ?>">
                            <button type="submit" class="toggle-button" title="<?= $serviceStatus === 'active' ? 'Nonaktifkan Layanan' : 'Aktifkan Layanan' ?>">
                                <div class="toggle-switch">
                                    <div class="toggle-slider <?= $serviceStatus === 'active' ? 'active' : '' ?>"></div>
                                </div>
                            </button>
                        </form>
                    </div>
                    
                    <div class="status-info">
                        <i class="fas fa-info-circle me-2"></i> 
                        <?php if ($serviceStatus === 'active'): ?>
                            Layanan antar jemput aktif. Pelanggan dapat memesan layanan antar jemput cucian.
                        <?php else: ?>
                            Layanan antar jemput nonaktif. Pelanggan tidak dapat memesan layanan antar jemput cucian.
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-icon stat-waiting">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-value"><?= $counts['menunggu_count'] ?></div>
                        <div class="stat-label">Menunggu</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon stat-progress">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="stat-value"><?= $counts['perjalanan_count'] ?></div>
                        <div class="stat-label">Dalam Perjalanan</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon stat-completed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value"><?= $counts['selesai_count'] ?></div>
                        <div class="stat-label">Selesai</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon stat-total">
                            <i class="fas fa-list"></i>
                        </div>
                        <div class="stat-value"><?= $counts['total_count'] ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                </div>
                
                <!-- Filter Buttons -->
                <div class="filter-container">
                    <button class="filter-btn active" data-filter="all">Semua</button>
                    <button class="filter-btn" data-filter="menunggu">Menunggu</button>
                    <button class="filter-btn" data-filter="dalam-perjalanan">Dalam Perjalanan</button>
                    <button class="filter-btn" data-filter="selesai">Selesai</button>
                    <button class="filter-btn" data-filter="antar">Antar</button>
                    <button class="filter-btn" data-filter="jemput">Jemput</button>
                    <button class="filter-btn" data-filter="antar-jemput">Antar-Jemput</button>
                </div>
                
                <!-- Requests List -->
                <?php if (count($requests) > 0): ?>
                    <?php foreach ($requests as $request): ?>
                        <div class="request-card" 
                             data-status="<?= $request['status'] ?>" 
                             data-type="<?= $request['layanan'] ?>">
                            <div class="request-header">
                                <div class="request-id">
                                    #<?= $request['pesanan_id'] ?? 'Baru' ?>
                                </div>
                                <div class="d-flex gap-2">
                                    <span class="request-type <?= str_replace('-', '-', $request['layanan']) ?>">
                                        <?= ucfirst($request['layanan']) ?>
                                    </span>
                                    <span class="request-status <?= str_replace(' ', '-', $request['status']) ?>">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="request-body">
                                <div class="customer-info">
                                    <div class="info-group">
                                        <div class="info-label">Nama Pelanggan</div>
                                        <div class="info-value"><?= htmlspecialchars($request['nama_pelanggan'] ?? 'Tidak tersedia') ?></div>
                                    </div>
                                    <div class="info-group">
                                        <div class="info-label">Telepon</div>
                                        <div class="info-value"><?= htmlspecialchars($request['telepon_pelanggan'] ?? 'Tidak tersedia') ?></div>
                                    </div>
                                    <div class="info-group">
                                        <div class="info-label">Waktu Pesanan</div>
                                        <div class="info-value"><?= isset($request['waktu_pesanan']) ? date('d/m/Y H:i', strtotime($request['waktu_pesanan'])) : 'Tidak tersedia' ?></div>
                                    </div>
                                    <div class="info-group">
                                        <div class="info-label">Total Harga</div>
                                        <div class="info-value"><?= isset($request['total_harga']) ? 'Rp ' . number_format($request['total_harga'], 0, ',', '.') : 'Tidak tersedia' ?></div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($request['alamat_jemput'])): ?>
                                <div class="address-box">
                                    <h6><i class="fas fa-map-marker-alt"></i> Alamat Jemput</h6>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($request['alamat_jemput'])) ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($request['alamat_antar'])): ?>
                                <div class="address-box">
                                    <h6><i class="fas fa-map-marker-alt"></i> Alamat Antar</h6>
                                    <p class="mb-0"><?= nl2br(htmlspecialchars($request['alamat_antar'])) ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="request-actions">
                                    <?php 
                                    // Format phone number for WhatsApp (remove any non-digit characters)
                                    $phone = isset($request['telepon_pelanggan']) ? preg_replace('/[^0-9]/', '', $request['telepon_pelanggan']) : '';
                                    // If phone starts with '0', replace with '62'
                                    if (substr($phone, 0, 1) === '0') {
                                        $phone = '62' . substr($phone, 1);
                                    }
                                    
                                    // Prepare WhatsApp message
                                    $message = "Pelanggan yth, Zeea Laundry akan segera ke tempat Anda, mohon pastikan Anda berada di rumah. Terima kasih.";
                                    $whatsappUrl = !empty($phone) ? "https://wa.me/{$phone}?text=" . urlencode($message) : "#";
                                    ?>
                                    
                                    <a href="<?= $whatsappUrl ?>" target="_blank" class="action-btn whatsapp-btn" <?= empty($phone) ? 'disabled' : '' ?>>
                                        <i class="fab fa-whatsapp"></i> WhatsApp
                                    </a>
                                    
                                    <?php if ($request['status'] === 'menunggu'): ?>
                                    <form method="post" action="" style="display: inline;" class="status-update-form">
                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        <input type="hidden" name="new_status" value="dalam perjalanan">
                                        <button type="submit" name="update_status" class="action-btn process-btn">
                                            <i class="fas fa-truck"></i> Proses Permintaan
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($request['status'] === 'dalam perjalanan'): ?>
                                    <form method="post" action="" style="display: inline;" class="status-update-form">
                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        <input type="hidden" name="new_status" value="selesai">
                                        <button type="submit" name="update_status" class="action-btn complete-btn">
                                            <i class="fas fa-check-circle"></i> Selesaikan
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="action-btn btn-secondary" data-bs-toggle="modal" data-bs-target="#detailModal<?= $request['id'] ?>">
                                        <i class="fas fa-eye"></i> Lihat Detail
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Detail Modal -->
                        <div class="modal fade" id="detailModal<?= $request['id'] ?>" tabindex="-1" aria-labelledby="detailModalLabel<?= $request['id'] ?>" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="detailModalLabel<?= $request['id'] ?>">Detail Permintaan #<?= $request['pesanan_id'] ?? 'Baru' ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <h6>Informasi Pelanggan</h6>
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <td>Nama</td>
                                                        <td>: <?= htmlspecialchars($request['nama_pelanggan'] ?? 'Tidak tersedia') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Telepon</td>
                                                        <td>: <?= htmlspecialchars($request['telepon_pelanggan'] ?? 'Tidak tersedia') ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Informasi Pesanan</h6>
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <td>ID Pesanan</td>
                                                        <td>: <?= $request['pesanan_id'] ?? 'Baru' ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Waktu</td>
                                                        <td>: <?= isset($request['waktu_pesanan']) ? date('d/m/Y H:i', strtotime($request['waktu_pesanan'])) : 'Tidak tersedia' ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Total</td>
                                                        <td>: <?= isset($request['total_harga']) ? 'Rp ' . number_format($request['total_harga'], 0, ',', '.') : 'Tidak tersedia' ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <h6>Informasi Antar Jemput</h6>
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <td>Jenis Layanan</td>
                                                        <td>: <?= ucfirst($request['layanan']) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td>Status</td>
                                                        <td>: <?= ucfirst($request['status']) ?></td>
                                                    </tr>
                                                    <?php if (!empty($request['waktu_jemput'])): ?>
                                                    <tr>
                                                        <td>Waktu Jemput</td>
                                                        <td>: <?= date('d/m/Y H:i', strtotime($request['waktu_jemput'])) ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php if (!empty($request['waktu_antar'])): ?>
                                                    <tr>
                                                        <td>Waktu Antar</td>
                                                        <td>: <?= date('d/m/Y H:i', strtotime($request['waktu_antar'])) ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($request['alamat_jemput'])): ?>
                                        <div class="mb-3">
                                            <h6>Alamat Jemput</h6>
                                            <div class="p-3 bg-light rounded">
                                                <?= nl2br(htmlspecialchars($request['alamat_jemput'])) ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($request['alamat_antar'])): ?>
                                        <div class="mb-3">
                                            <h6>Alamat Antar</h6>
                                            <div class="p-3 bg-light rounded">
                                                <?= nl2br(htmlspecialchars($request['alamat_antar'])) ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <a href="<?= $whatsappUrl ?>" target="_blank" class="btn btn-success" <?= empty($phone) ? 'disabled' : '' ?>>
                                            <i class="fab fa-whatsapp"></i> WhatsApp
                                        </a>
                                        <?php if ($request['status'] === 'menunggu'): ?>
                                        <form method="post" action="" style="display: inline;" class="status-update-form">
                                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                            <input type="hidden" name="new_status" value="dalam perjalanan">
                                            <button type="submit" name="update_status" class="btn btn-primary">
                                                <i class="fas fa-truck"></i> Proses
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($request['status'] === 'dalam perjalanan'): ?>
                                        <form method="post" action="" style="display: inline;" class="status-update-form">
                                            <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                            <input type="hidden" name="new_status" value="selesai">
                                            <button type="submit" name="update_status" class="btn btn-success">
                                                <i class="fas fa-check-circle"></i> Selesai
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-requests">
                        <i class="fas fa-inbox"></i>
                        <h5>Tidak Ada Permintaan Antar Jemput</h5>
                        <p>Saat ini tidak ada permintaan antar jemput yang perlu diproses.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Filter functionality
            $('.filter-btn').click(function() {
                $('.filter-btn').removeClass('active');
                $(this).addClass('active');
                
                const filter = $(this).data('filter');
                
                if (filter === 'all') {
                    $('.request-card').show();
                } else {
                    $('.request-card').hide();
                    $(`.request-card[data-status="${filter}"], .request-card[data-type="${filter}"]`).show();
                }
            });
            
            // Make the entire status toggle container clickable
            $('#statusToggleContainer').click(function(e) {
                // Prevent the click if it's on the toggle button itself
                if (!$(e.target).closest('.toggle-button').length) {
                    $('#toggleForm').submit();
                }
            });
            
            // Toast notification function
            function showToast(type, title, message) {
                const toastId = 'toast-' + Date.now();
                const toastHtml = `
                    <div class="toast toast-${type}" id="${toastId}">
                        <div class="toast-icon">
                            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                        </div>
                        <div class="toast-content">
                            <div class="toast-title">${title}</div>
                            <div class="toast-message">${message}</div>
                        </div>
                        <button class="toast-close" onclick="closeToast('${toastId}')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                
                $('#toastContainer').append(toastHtml);
                
                // Auto close after 5 seconds
                setTimeout(function() {
                    closeToast(toastId);
                }, 5000);
            }
            
            // Close toast function
            window.closeToast = function(toastId) {
                const toast = $('#' + toastId);
                toast.addClass('hide');
                setTimeout(function() {
                    toast.remove();
                }, 300);
            };
            
            <?php if (isset($statusMessage)): ?>
            // Show toast notification if there's a status message
            showToast('<?= $statusMessageType ?>', 
                      '<?= $statusMessageType === "success" ? "Berhasil!" : "Gagal!" ?>', 
                      '<?= $statusMessage ?>');
            <?php endif; ?>
            
            // Handle form submissions with AJAX
            $('.status-update-form').submit(function(e) {
                e.preventDefault();
                
                $.ajax({
                    type: 'POST',
                    url: window.location.href,
                    data: $(this).serialize(),
                    success: function(response) {
                        // Reload the page to reflect changes
                        location.reload();
                    },
                    error: function() {
                        showToast('danger', 'Gagal!', 'Terjadi kesalahan saat memperbarui status.');
                    }
                });
            });
            
            // Handle toggle form submission with AJAX
            $('#toggleForm').submit(function(e) {
                e.preventDefault();
                
                $.ajax({
                    type: 'POST',
                    url: window.location.href,
                    data: $(this).serialize(),
                    success: function(response) {
                        // Get the current status
                        const currentStatus = $('input[name="toggle_service"]').val() === 'activate' ? 'inactive' : 'active';
                        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
                        
                        // Update the UI without reloading
                        if (newStatus === 'active') {
                            $('#statusToggleContainer').css({
                                '--status-bg-color': 'rgba(40, 167, 69, 0.15)',
                                '--status-border-color': 'rgba(40, 167, 69, 0.3)',
                                '--toggle-bg-color': '#28a745'
                            });
                            $('.toggle-slider').addClass('active');
                            $('.status-text').text('Layanan Antar Jemput saat ini AKTIF');
                            $('.status-info').html('<i class="fas fa-info-circle me-2"></i> Layanan antar jemput aktif. Pelanggan dapat memesan layanan antar jemput cucian.');
                            $('input[name="toggle_service"]').val('deactivate');
                            
                            showToast('success', 'Berhasil!', 'Layanan antar jemput berhasil diaktifkan');
                        } else {
                            $('#statusToggleContainer').css({
                                '--status-bg-color': 'rgba(220, 53, 69, 0.15)',
                                '--status-border-color': 'rgba(220, 53, 69, 0.3)',
                                '--toggle-bg-color': '#ccc'
                            });
                            $('.toggle-slider').removeClass('active');
                            $('.status-text').text('Layanan Antar Jemput saat ini NONAKTIF');
                            $('.status-info').html('<i class="fas fa-info-circle me-2"></i> Layanan antar jemput nonaktif. Pelanggan tidak dapat memesan layanan antar jemput cucian.');
                            $('input[name="toggle_service"]').val('activate');
                            
                            showToast('success', 'Berhasil!', 'Layanan antar jemput berhasil dinonaktifkan');
                        }
                    },
                    error: function() {
                        showToast('danger', 'Gagal!', 'Terjadi kesalahan saat memperbarui status layanan.');
                    }
                });
            });
        });
    </script>
</body>
</html>

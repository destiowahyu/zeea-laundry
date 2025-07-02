<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

include '../includes/db.php';

$current_page = basename($_SERVER['PHP_SELF']);

// Ambil ID Admin dari session
$adminId = $_SESSION['id'];

// Ambil data nama admin dari database
$stmt = $conn->prepare("SELECT username FROM admin WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$adminData = $stmt->get_result()->fetch_assoc();
$namaAdmin = $adminData['username'] ?? "Tidak Ditemukan";

// Fetch jumlah pesanan hari ini
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM pesanan WHERE DATE(waktu) = CURDATE()");
$stmt->execute();
$pesananHariIni = $stmt->get_result()->fetch_assoc()['total'];

// Fetch jumlah pesanan yang sudah selesai
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM pesanan WHERE status = 'selesai' AND DATE(waktu) = CURDATE()");
$stmt->execute();
$pesananSelesai = $stmt->get_result()->fetch_assoc()['total'];

// Fetch total pemasukan hari ini - PERBAIKAN: Handle null values
$stmt = $conn->prepare("SELECT COALESCE(SUM(harga), 0) AS total FROM pesanan WHERE DATE(waktu) = CURDATE()");
$stmt->execute();
$pemasukanHariIni = $stmt->get_result()->fetch_assoc()['total'];

// Pastikan nilai tidak null untuk number_format
$pemasukanHariIni = $pemasukanHariIni ?? 0;

// Get current store status - hanya ambil record dengan id = 1
$storeStatusQuery = "SELECT status, waktu FROM toko_status WHERE id = 1 LIMIT 1";
$storeStatusResult = $conn->query($storeStatusQuery);
$storeStatus = "buka"; // Default status is open
$statusTime = "";

if ($storeStatusResult && $storeStatusResult->num_rows > 0) {
    $statusRow = $storeStatusResult->fetch_assoc();
    $storeStatus = $statusRow['status'];
    $statusTime = date('d/m/Y H:i', strtotime($statusRow['waktu']));
} else {
    // Jika belum ada record, buat record pertama
    $stmt = $conn->prepare("INSERT INTO toko_status (id, status, waktu) VALUES (1, 'buka', NOW())");
    $stmt->execute();
    $storeStatus = "buka";
    $statusTime = date('d/m/Y H:i');
}

// Process store status update
if (isset($_POST['update_status'])) {
    $status = $_POST['update_status'];
    
    // Update existing record instead of inserting new one
    $stmt = $conn->prepare("UPDATE toko_status SET status = ?, waktu = NOW() WHERE id = 1");
    $stmt->bind_param("s", $status);
    
    if ($stmt->execute()) {
        $statusMessage = "Status toko berhasil diperbarui.";
        $statusMessageType = "success";
        
        // Update the current status
        $storeStatus = $status;
        $statusTime = date('d/m/Y H:i');
    } else {
        $statusMessage = "Gagal memperbarui status toko: " . $stmt->error;
        $statusMessageType = "danger";
    }
}

$stmt->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <style>
        /* Same styles as your provided example for consistent design */
        .store-status-card {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
            border-left: 5px solid #42c3cf;
        }
        
        .store-status-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .store-status-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 15px;
        }
        
        .store-status-header h4 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
        }
        
        .store-status-header i {
            margin-right: 12px;
            font-size: 1.8rem;
            color: #42c3cf;
        }
        
        .current-status {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .current-status.open {
            background-color: rgba(40, 167, 69, 0.15);
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .current-status.closed {
            background-color: rgba(220, 53, 69, 0.15);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .status-indicator {
            display: inline-block;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 12px;
            position: relative;
        }
        
        .status-indicator::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: inherit;
            top: 0;
            left: 0;
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.5);
                opacity: 0.5;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .status-open {
            background-color: #28a745;
            box-shadow: 0 0 10px rgba(40, 167, 69, 0.5);
        }
        
        .status-closed {
            background-color: #dc3545;
            box-shadow: 0 0 10px rgba(220, 53, 69, 0.5);
        }
        
        .status-text {
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .status-time {
            margin-left: auto;
            font-size: 0.9rem;
            color: #6c757d;
            background-color: rgba(255, 255, 255, 0.7);
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .status-actions {
            display: flex;
            gap: 15px;
        }
        
        /* Enhanced button styles */
        .status-btn {
            flex: 1;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .status-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: all 0.4s ease;
        }
        
        .status-btn:hover::before {
            left: 100%;
        }
        
        .status-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .status-btn:active {
            transform: translateY(2px);
        }
        
        .status-btn.open-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        
        .status-btn.open-btn:disabled {
            background: linear-gradient(135deg, #28a745, #20c997);
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .status-btn.close-btn {
            background: linear-gradient(135deg, #dc3545, #e83e8c);
            color: white;
        }
        
        .status-btn.close-btn:disabled {
            background: linear-gradient(135deg, #dc3545, #e83e8c);
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .status-btn i {
            font-size: 1.2rem;
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
        
        /* Mobile optimization */
        @media (max-width: 768px) {
            .status-actions {
                flex-direction: column;
            }
            
            .status-btn {
                width: 100%;
                padding: 15px;
            }
            
            .current-status {
                flex-direction: column;
                text-align: center;
                padding: 15px 10px;
            }
            
            .status-indicator {
                margin-right: 0;
                margin-bottom: 10px;
                width: 20px;
                height: 20px;
            }
            
            .status-time {
                margin-left: 0;
                margin-top: 10px;
                width: 100%;
                text-align: center;
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
            <h1>Dashboard</h1>
        </div>

        <div class="content-wrapper">
            <div class="welcome mb-4">
                Selamat Datang, <span><strong style="color: #42c3cf;"><?= htmlspecialchars($namaAdmin) ?>!</strong></span>
            </div>
            
            <!-- Store Status Card -->
            <div class="store-status-card mb-4">
                <div class="store-status-header">
                    <i class="fas fa-store"></i>
                    <h4>Status Toko</h4>
                </div>
                
                <?php if (isset($statusMessage)): ?>
                <div class="alert alert-<?= $statusMessageType ?> alert-dismissible fade show" role="alert">
                    <?= $statusMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="current-status <?= $storeStatus == 'buka' ? 'open' : 'closed' ?>">
                    <div class="status-indicator <?= $storeStatus == 'buka' ? 'status-open' : 'status-closed' ?>"></div>
                    <div class="status-text">
                        Toko sedang <?= $storeStatus == 'buka' ? 'BUKA' : 'TUTUP' ?>
                    </div>
                    <?php if (!empty($statusTime)): ?>
                    <div class="status-time">
                        <i class="far fa-clock me-1"></i> Diperbarui: <?= $statusTime ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <form method="post" action="">
                    <div class="status-actions">
                        <button type="submit" name="update_status" value="buka" class="status-btn open-btn" <?= $storeStatus == 'buka' ? 'disabled' : '' ?>>
                            <i class="fas fa-door-open"></i> Buka Toko
                        </button>
                        <button type="submit" name="update_status" value="tutup" class="status-btn close-btn" <?= $storeStatus == 'tutup' ? 'disabled' : '' ?>>
                            <i class="fas fa-door-closed"></i> Tutup Toko
                        </button>
                    </div>
                </form>
                
                <div class="status-info">
                    <i class="fas fa-info-circle me-2"></i> Saat toko ditutup, pelanggan tidak akan dapat menggunakan layanan antar jemput cucian dan akan melihat pemberitahuan di halaman utama.
                </div>
            </div>
            
            <div class="container-fluid px-0 mb-4">
                <div class="row g-4">
                    <div class="col-md-3">
                        <a href="pesanan.php" style="text-decoration:none;">
                            <div class="card-dashboard h-100">
                                <i class="fas fa-box"></i>
                                <h5>Pesanan Hari Ini</h5>
                                <p><?= $pesananHariIni ?></p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="pesanan.php" style="text-decoration:none;">
                            <div class="card-dashboard h-100">
                                <i class="fas fa-check-circle"></i>
                                <h5>Pesanan Selesai</h5>
                                <p><?= $pesananSelesai ?></p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="laporan-pemasukan.php" style="text-decoration:none;">
                            <div class="card-dashboard h-100">
                                <i class="fas fa-wallet"></i>
                                <h5>Pemasukan Hari Ini</h5>
                                <p><?= "Rp " . number_format((float)$pemasukanHariIni, 0, ',', '.') ?></p>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="riwayat-transaksi.php" style="text-decoration:none;">
                            <div class="card-dashboard h-100">
                                <i class="fas fa-history"></i>
                                <h5>Riwayat Transaksi</h5>
                                <p>Lihat Semua Riwayat</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
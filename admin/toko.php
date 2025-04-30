<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
  header("Location: login.php");
  exit();
}

// Set zona waktu ke Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

$current_page = basename($_SERVER['PHP_SELF']);
include '../includes/db.php';

// Process form submission
if (isset($_POST['update_status'])) {
    $status = $_POST['status'];
    
    // Insert new status
    $stmt = $conn->prepare("INSERT INTO toko_status (status, waktu) VALUES (?, NOW())");
    $stmt->bind_param("s", $status);
    
    if ($stmt->execute()) {
        $message = "Status toko berhasil diperbarui.";
        $messageType = "success";
    } else {
        $message = "Gagal memperbarui status toko: " . $stmt->error;
        $messageType = "danger";
    }
}

// Get current store status
$storeStatusQuery = "SELECT status, waktu FROM toko_status ORDER BY id DESC LIMIT 1";
$storeStatusResult = $conn->query($storeStatusQuery);
$storeStatus = "buka"; // Default status is open
$statusTime = "";

if ($storeStatusResult && $storeStatusResult->num_rows > 0) {
    $statusRow = $storeStatusResult->fetch_assoc();
    $storeStatus = $statusRow['status'];
    $statusTime = date('d/m/Y H:i', strtotime($statusRow['waktu']));
}

// Get status history
$historyQuery = "SELECT status, waktu FROM toko_status ORDER BY id DESC LIMIT 10";
$historyResult = $conn->query($historyQuery);
$statusHistory = [];

if ($historyResult && $historyResult->num_rows > 0) {
    while ($row = $historyResult->fetch_assoc()) {
        $statusHistory[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Status Toko - Zeea Laundry</title>
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #42c3cf;
            --primary-light: #e8f7f9;
            --primary-dark: #38adb8;
            --secondary: #ffc107;
            --secondary-dark: #e0a800;
            --dark: #333333;
            --light: #ffffff;
            --gray: #f8f9fa;
            --text-gray: #6c757d;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--primary-light);
            color: var(--dark);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .page-title span {
            color: var(--primary);
        }
        
        .card {
            background-color: var(--light);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            border: none;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, #4dd0e1 100%);
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            padding: 15px 20px;
            border: none;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .status-indicator {
            display: inline-block;
            width: 15px;
            height: 15px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .status-open {
            background-color: #28a745;
        }
        
        .status-closed {
            background-color: #dc3545;
        }
        
        .current-status {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .current-status.open {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .current-status.closed {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .status-text {
            font-size: 1.2rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .status-time {
            font-size: 0.9rem;
            margin-left: auto;
            color: var(--text-gray);
        }
        
        .btn {
            height: 48px;
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(66, 195, 207, 0.3);
        }
        
        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: var(--primary-light);
            color: var(--primary);
            font-weight: 600;
            padding: 12px;
            border: none;
        }
        
        .table tbody tr {
            transition: var(--transition);
        }
        
        .table tbody tr:hover {
            background-color: rgba(66, 195, 207, 0.05);
        }
        
        .table tbody td {
            padding: 12px;
            vertical-align: middle;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            border-radius: 10px;
            padding: 8px 20px;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            margin-bottom: 20px;
        }
        
        .back-btn:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(66, 195, 207, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Pengaturan <span>Status Toko</span></h1>
            <p class="text-muted">Atur status toko untuk memberi tahu pelanggan apakah toko sedang buka atau tutup</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <a href="index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
                </a>
                
                <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-info-circle me-2"></i> <?php echo $message; ?>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-store me-2"></i> Status Toko Saat Ini
                    </div>
                    <div class="card-body">
                        <div class="current-status <?php echo $storeStatus == 'buka' ? 'open' : 'closed'; ?>">
                            <div class="status-indicator <?php echo $storeStatus == 'buka' ? 'status-open' : 'status-closed'; ?>"></div>
                            <div class="status-text">
                                Toko sedang <?php echo $storeStatus == 'buka' ? 'BUKA' : 'TUTUP'; ?>
                            </div>
                            <?php if (!empty($statusTime)): ?>
                            <div class="status-time">
                                Diperbarui pada: <?php echo $statusTime; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label class="form-label">Ubah Status Toko</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="update_status" value="buka" class="btn btn-success flex-grow-1 <?php echo $storeStatus == 'buka' ? 'disabled' : ''; ?>">
                                        <i class="fas fa-door-open me-2"></i> Buka Toko
                                    </button>
                                    <button type="submit" name="update_status" value="tutup" class="btn btn-danger flex-grow-1 <?php echo $storeStatus == 'tutup' ? 'disabled' : ''; ?>">
                                        <i class="fas fa-door-closed me-2"></i> Tutup Toko
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Saat toko ditutup, pelanggan tidak akan dapat menggunakan layanan antar jemput cucian.
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history me-2"></i> Riwayat Perubahan Status
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($statusHistory)): ?>
                                    <tr>
                                        <td colspan="2" class="text-center">Belum ada riwayat perubahan status</td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($statusHistory as $history): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="status-indicator <?php echo $history['status'] == 'buka' ? 'status-open' : 'status-closed'; ?>"></div>
                                                    <span class="ms-2"><?php echo $history['status'] == 'buka' ? 'Buka' : 'Tutup'; ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($history['waktu'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

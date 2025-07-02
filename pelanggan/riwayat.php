<?php
// Include database connection
include '../includes/db.php';

// Initialize variables
$phoneNumber = "";
$orderHistory = [];
$antarJemputHistory = [];
$message = "";

// Batasi maksimal 10 data terbaru untuk riwayat pesanan dan antar jemput
define('MAX_HISTORY', 10);

// Check if this is an AJAX request for order details
if(isset($_GET['ajax_get_details']) && isset($_GET['tracking_code'])) {
    // Set content type to JSON
    header('Content-Type: application/json');
    
    // Initialize response
    $response = [
        'success' => false,
        'message' => 'Invalid request',
        'order' => null,
        'items' => []
    ];
    
    $trackingCode = $_GET['tracking_code'];
    
    // Get order details
    $query = "SELECT p.*, pk.nama as nama_paket, pk.harga as harga_per_kg, pl.nama as nama_pelanggan, pl.no_hp as telepon 
             FROM pesanan p 
             JOIN paket pk ON p.id_paket = pk.id 
             JOIN pelanggan pl ON p.id_pelanggan = pl.id 
             WHERE p.tracking_code = ? 
             ORDER BY p.id ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $trackingCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $items = [];
        $totalHarga = 0;
        
        while ($item = $result->fetch_assoc()) {
            $items[] = $item;
            $totalHarga += $item['harga'];
        }
        
        // Use the first item for order details
        $orderDetails = $items[0];
        $orderDetails['total_harga'] = $totalHarga;
        
        $response = [
            'success' => true,
            'message' => 'Order details retrieved successfully',
            'order' => $orderDetails,
            'items' => $items
        ];
    } else {
        $response['message'] = 'No order found with the provided tracking code';
    }
    
    // Return JSON response and exit
    echo json_encode($response);
    exit;
}

// Check if this is an AJAX request for antar-jemput details
if(isset($_GET['ajax_get_aj_details']) && isset($_GET['aj_id'])) {
    // Set content type to JSON
    header('Content-Type: application/json');
    
    // Initialize response
    $response = [
        'success' => false,
        'message' => 'Invalid request',
        'antar_jemput' => null
    ];
    
    $ajId = intval($_GET['aj_id']);
    
    // Get antar-jemput details
    $query = "SELECT 
    aj.*, 
    p.tracking_code as pesanan_tracking_code,
    p.status as pesanan_status,
    p.status_pembayaran as pesanan_status_pembayaran,
    COALESCE(aj.nama_pelanggan, pl.nama, 'Pelanggan') as nama_pelanggan_final,
    COALESCE(aj.no_hp, pl.no_hp, '') as no_hp_final
FROM antar_jemput aj 
LEFT JOIN pesanan p ON aj.id_pesanan = p.id
LEFT JOIN pelanggan pl ON p.id_pelanggan = pl.id
WHERE aj.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $ajId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $antarJemputDetails = $result->fetch_assoc();
        
        $response = [
            'success' => true,
            'message' => 'Antar-jemput details retrieved successfully',
            'antar_jemput' => $antarJemputDetails
        ];
    } else {
        $response['message'] = 'No antar-jemput service found with the provided ID';
    }
    
    // Return JSON response and exit
    echo json_encode($response);
    exit;
}

// Get store status
$storeStatusQuery = "SELECT status, waktu FROM toko_status ORDER BY id DESC LIMIT 1";
$storeStatusResult = $conn->query($storeStatusQuery);
$storeStatus = "buka"; // Default status is open
$statusTime = "";

if ($storeStatusResult && $storeStatusResult->num_rows > 0) {
    $statusRow = $storeStatusResult->fetch_assoc();
    $storeStatus = $statusRow['status'];
    $statusTime = date('H:i', strtotime($statusRow['waktu']));
}

// Process history form
if (isset($_POST['view_history'])) {
    $phoneNumber = trim($_POST['phone_number']);
    
    // Clean and format phone number
    $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    // Try different phone number formats
    $phoneFormats = [];
    if (substr($cleanPhone, 0, 1) === '0') {
        $phoneFormats[] = $cleanPhone;
        $phoneFormats[] = '+62' . substr($cleanPhone, 1);
        $phoneFormats[] = '62' . substr($cleanPhone, 1);
    } elseif (substr($cleanPhone, 0, 2) === '62') {
        $phoneFormats[] = $cleanPhone;
        $phoneFormats[] = '+' . $cleanPhone;
        $phoneFormats[] = '0' . substr($cleanPhone, 2);
    } else {
        $phoneFormats[] = $cleanPhone;
        $phoneFormats[] = '0' . $cleanPhone;
        $phoneFormats[] = '62' . $cleanPhone;
        $phoneFormats[] = '+62' . $cleanPhone;
    }
    
    // Get customer ID first
    $customerQuery = "SELECT id, nama, no_hp FROM pelanggan WHERE no_hp IN (" . implode(',', array_fill(0, count($phoneFormats), '?')) . ") LIMIT 1";
    $stmt = $conn->prepare($customerQuery);
    $stmt->bind_param(str_repeat('s', count($phoneFormats)), ...$phoneFormats);
    $stmt->execute();
    $customerResult = $stmt->get_result();
    
    if ($customerResult->num_rows > 0) {
        $customer = $customerResult->fetch_assoc();
        $customerId = $customer['id'];
        
        // Get all unique tracking codes for laundry orders
        $historyQuery = "SELECT DISTINCT p.tracking_code, p.waktu
                        FROM pesanan p 
                        WHERE p.id_pelanggan = ? 
                        ORDER BY p.waktu DESC";
        
        $stmt = $conn->prepare($historyQuery);
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $historyResult = $stmt->get_result();
        
        // Fetch all unique tracking codes
        $trackingCodes = [];
        while ($row = $historyResult->fetch_assoc()) {
            $trackingCodes[] = [
                'tracking_code' => $row['tracking_code'],
                'waktu' => $row['waktu']
            ];
        }

        // Get details for each tracking code
        $orderHistory = [];
        foreach ($trackingCodes as $code) {
            $detailQuery = "SELECT p.*, pk.nama as nama_paket, pk.harga as harga_per_kg, pl.nama as nama_pelanggan, pl.no_hp as telepon 
                           FROM pesanan p 
                           JOIN paket pk ON p.id_paket = pk.id 
                           JOIN pelanggan pl ON p.id_pelanggan = pl.id 
                           WHERE p.tracking_code = ? 
                           ORDER BY p.id ASC";
            
            $stmt = $conn->prepare($detailQuery);
            $stmt->bind_param("s", $code['tracking_code']);
            $stmt->execute();
            $detailResult = $stmt->get_result();
            
            $items = [];
            $totalHarga = 0;
            while ($item = $detailResult->fetch_assoc()) {
                $items[] = $item;
                $totalHarga += $item['harga'];
            }
            
            if (count($items) > 0) {
                $orderHistory[] = [
                    'tracking_code' => $code['tracking_code'],
                    'waktu' => $code['waktu'],
                    'items' => $items,
                    'total_harga' => $totalHarga,
                    'status' => $items[0]['status'], // Use status from first item
                    'status_pembayaran' => $items[0]['status_pembayaran'] // Use payment status from first item
                ];
            }
        }
        
        // Get antar-jemput history - now includes both linked and standalone services
        $antarJemputQuery = "SELECT 
    aj.*, 
    p.tracking_code as pesanan_tracking_code,
    p.status as pesanan_status,
    p.status_pembayaran as pesanan_status_pembayaran,
    COALESCE(aj.nama_pelanggan, pl.nama, 'Pelanggan') as nama_pelanggan_final,
    COALESCE(aj.no_hp, pl.no_hp, '') as no_hp_final
FROM antar_jemput aj 
LEFT JOIN pesanan p ON aj.id_pesanan = p.id
LEFT JOIN pelanggan pl ON p.id_pelanggan = pl.id
WHERE (aj.id_pelanggan = ? OR p.id_pelanggan = ?)
ORDER BY aj.id DESC";

        $stmt = $conn->prepare($antarJemputQuery);
        $stmt->bind_param("ii", $customerId, $customerId);
        $stmt->execute();
        $antarJemputResult = $stmt->get_result();
        
        while ($row = $antarJemputResult->fetch_assoc()) {
            $antarJemputHistory[] = $row;
        }
        
        if (count($orderHistory) == 0 && count($antarJemputHistory) == 0) {
            $message = "Tidak ada riwayat pesanan atau layanan antar-jemput ditemukan untuk nomor telepon ini.";
        }
    } else {
        $message = "<div class='not-registered-alert'>
            <div class='not-registered-icon'>
                <i class='fas fa-exclamation-circle'></i>
            </div>
            <div class='not-registered-content'>
                <h5>Nomor Telepon Tidak Ditemukan</h5>
                <p>Nomor telepon yang Anda masukkan tidak terdaftar dalam sistem kami. Silakan gunakan layanan jemput cucian untuk mendaftar atau periksa kembali nomor telepon Anda.</p>
                <a href='jemput.php' class='btn btn-primary mt-2'>
                    <i class='fas fa-truck-pickup me-2'></i> Gunakan Layanan Jemput
                </a>
            </div>
        </div>";
    }
}

// Batasi maksimal 10 data terbaru untuk riwayat pesanan dan antar jemput
$orderHistory = array_slice($orderHistory, 0, MAX_HISTORY);
$antarJemputHistory = array_slice($antarJemputHistory, 0, MAX_HISTORY);

function getStatusBadge($status) {
    switch ($status) {
        case 'menunggu':
            return '<span class="status-badge status-menunggu"><i class="fas fa-clock me-1"></i>Menunggu</span>';
        case 'dalam perjalanan':
            return '<span class="status-badge status-dalam-perjalanan"><i class="fas fa-truck me-1"></i>Dalam Perjalanan</span>';
        case 'selesai':
            return '<span class="status-badge status-selesai"><i class="fas fa-check-circle me-1"></i>Selesai</span>';
        case 'diproses':
            return '<span class="status-badge status-diproses"><i class="fas fa-cog me-1"></i>Diproses</span>';
        case 'dibatalkan':
            return '<span class="status-badge status-dibatalkan"><i class="fas fa-times-circle me-1"></i>Dibatalkan</span>';
        default:
            return '<span class="status-badge status-unknown">Unknown</span>';
    }
}

function getLayananBadge($layanan) {
    switch ($layanan) {
        case 'antar':
            return '<span class="layanan-badge layanan-antar"><i class="fas fa-truck me-1"></i>Antar</span>';
        case 'jemput':
            return '<span class="layanan-badge layanan-jemput"><i class="fas fa-home me-1"></i>Jemput</span>';
        case 'antar-jemput':
            return '<span class="layanan-badge layanan-antar-jemput"><i class="fas fa-exchange-alt me-1"></i>Antar & Jemput</span>';
        default:
            return '<span class="layanan-badge layanan-unknown">Unknown</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Zeea Laundry - Riwayat Cucian</title>
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
            --container-sm-width: 100%;
            --mobile-padding: 15px;
            --success-green: #28a745;
            --danger-red: #dc3545;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-text-size-adjust: 100%;
        }
        
        html, body {
            overflow-x: hidden;
            width: 100%;
            min-height: 100vh;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            background-color: var(--primary-light);
            position: relative;
            touch-action: manipulation;
            -webkit-overflow-scrolling: touch;
        }
        
        .container {
            width: 100%;
            padding-right: var(--mobile-padding);
            padding-left: var(--mobile-padding);
            margin-right: auto;
            margin-left: auto;
        }
        
        /* Main Content */
        .main-content {
            padding: 30px 0;
            min-height: 100vh;
            position: relative;
            width: 100%;
        }
        
        .main-content::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background-color: rgba(66, 195, 207, 0.1);
            z-index: -1;
        }
        
        .main-content::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background-color: rgba(66, 195, 207, 0.1);
            z-index: -1;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        /* Store Status Indicator */
        .store-status-indicator {
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .store-status-indicator.open {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-green);
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .store-status-indicator.closed {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-red);
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .page-badge {
            display: inline-block;
            background-color: rgba(66, 195, 207, 0.1);
            color: var(--primary);
            font-weight: 500;
            font-size: 14px;
            padding: 6px 15px;
            border-radius: 30px;
            margin-bottom: 10px;
            animation: fadeInUp 0.8s ease;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
            animation: fadeInUp 1s ease;
            line-height: 1.3;
        }
        
        .page-title span {
            color: var(--primary);
        }
        
        .page-subtitle {
            font-size: 0.95rem;
            color: var(--text-gray);
            margin-bottom: 20px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            animation: fadeInUp 1.2s ease;
            line-height: 1.5;
        }
        
        /* Customer Card */
        .customer-card {
            background-color: var(--light);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            transition: var(--transition);
            border: none;
            width: 100%;
        }
        
        .customer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, #4dd0e1 100%);
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            padding: 15px 20px;
            border: none;
        }
        
        .card-header i {
            margin-right: 10px;
        }
        
        .card-body {
            padding: 20px;
            width: 100%;
        }
        
        /* Form Styles */
        .form-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .form-control {
            height: 48px;
            border-radius: 10px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: var(--transition);
            width: 100%;
        }
        
        .form-control:focus {
            box-shadow: none;
            border-color: var(--primary);
        }
        
        textarea.form-control {
            height: auto;
            min-height: 100px;
        }
        
        .form-text {
            color: var(--text-gray);
            font-size: 0.85rem;
            margin-top: 5px;
        }
        
        /* Button Styles */
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
            white-space: nowrap;
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
        
        .btn-secondary {
            background-color: var(--secondary);
            border-color: var(--secondary);
            color: var(--dark);
        }
        
        .btn-secondary:hover {
            background-color: var(--secondary-dark);
            border-color: var(--secondary-dark);
            color: var(--dark);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255, 193, 7, 0.3);
        }
        
        .btn-outline-primary {
            border-color: var(--primary);
            color: var(--primary);
            background-color: transparent;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(66, 195, 207, 0.3);
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .alert-info {
            background-color: rgba(66, 195, 207, 0.1);
            color: var(--primary);
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .alert-warning {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        /* Table Styles */
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.03);
            width: 100%;
        }
        
        .table {
            margin-bottom: 0;
            width: 100%;
        }
        
        .table thead th {
            background-color: var(--primary-light);
            color: var(--primary);
            font-weight: 600;
            padding: 12px;
            border: none;
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        .table tbody tr {
            background-color: white;
            transition: var(--transition);
        }
        
        .table tbody tr:hover {
            background-color: rgba(66, 195, 207, 0.05);
        }
        
        .table tbody td {
            padding: 12px;
            vertical-align: middle;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            font-size: 0.85rem;
            white-space: nowrap;
        }
        
        /* Mobile-optimized table */
        .mobile-table {
            display: none;
            width: 100%;
        }
        
        .mobile-order-card {
            background-color: white;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            width: 100%;
        }
        
        .mobile-order-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .mobile-order-id {
            font-weight: 600;
            color: var(--primary);
            font-size: 0.9rem;
        }
        
        .mobile-order-date {
            font-size: 0.8rem;
            color: var(--text-gray);
        }
        
        .mobile-order-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 0.85rem;
        }
        
        .mobile-order-label {
            font-weight: 500;
            color: var(--dark);
        }
        
        .mobile-order-value {
            color: var(--text-gray);
            text-align: right;
        }
        
        .mobile-order-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        /* Status Badge Styles */
        .status-badge {
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .status-diproses, .status-processing {
            background-color: rgba(255, 193, 7, 0.2);
            color: #e0a800;
            border: 1px solid rgba(255, 193, 7, 0.5);
        }

        .status-selesai {
            background-color: rgba(40, 167, 69, 0.2);
            color: #218838;
            border: 1px solid rgba(40, 167, 69, 0.5);
        }

        .status-dibatalkan {
            background-color: rgba(220, 53, 69, 0.2);
            color: #c82333;
            border: 1px solid rgba(220, 53, 69, 0.5);
        }

        .status-belum_dibayar, .payment-unpaid {
            background-color: rgba(220, 53, 69, 0.2);
            color: #c82333;
            border: 1px solid rgba(220, 53, 69, 0.5);
        }

        .status-sudah_dibayar {
            background-color: rgba(40, 167, 69, 0.2);
            color: #218838;
            border: 1px solid rgba(40, 167, 69, 0.5);
        }

        .status-menunggu {
            background-color: rgba(255, 193, 7, 0.2);
            color: #e0a800;
            border: 1px solid rgba(255, 193, 7, 0.5);
        }

        .status-dalam-perjalanan {
            background-color: rgba(23, 162, 184, 0.2);
            color: #138496;
            border: 1px solid rgba(23, 162, 184, 0.5);
        }

        .layanan-badge {
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
            text-align: center;
        }

        .layanan-antar {
            background-color: rgba(0, 123, 255, 0.2);
            color: #0056b3;
            border: 1px solid rgba(0, 123, 255, 0.5);
        }

        .layanan-jemput {
            background-color: rgba(40, 167, 69, 0.2);
            color: #218838;
            border: 1px solid rgba(40, 167, 69, 0.5);
        }

        .layanan-antar-jemput {
            background-color: rgba(23, 162, 184, 0.2);
            color: #138496;
            border: 1px solid rgba(23, 162, 184, 0.5);
        }

        .table tr:hover .status-processing {
            animation: pulse-warning 1s infinite alternate;
        }

        .table tr:hover .payment-unpaid {
            animation: pulse-danger 1s infinite alternate;
        }

        @keyframes pulse-warning {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
            }
            100% {
                transform: scale(1.05);
                box-shadow: 0 0 10px 5px rgba(255, 193, 7, 0.7);
            }
        }

        @keyframes pulse-danger {
            0% {
                transform: scale(1);
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }
            100% {
                transform: scale(1.05);
                box-shadow: 0 0 10px 5px rgba(220, 53, 69, 0.7);
            }
        }
        
        /* Footer */
        .footer {
            background-color: var(--light);
            padding: 15px 0;
            text-align: center;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.05);
            width: 100%;
        }
        
        .footer-text {
            color: var(--text-gray);
            font-size: 0.85rem;
            margin: 0;
        }
        
        /* Back Button */
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
            font-size: 0.9rem;
        }
        
        .back-btn:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(66, 195, 207, 0.3);
        }
        
        /* Not Registered Alert */
        .not-registered-alert {
            display: flex;
            background-color: #fff3cd;
            border-left: 5px solid #ffc107;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            animation: fadeIn 0.5s ease;
        }
        
        .not-registered-icon {
            font-size: 2.5rem;
            color: #ffc107;
            margin-right: 20px;
            display: flex;
            align-items: center;
        }
        
        .not-registered-content {
            flex: 1;
        }
        
        .not-registered-content h5 {
            color: #856404;
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .not-registered-content p {
            color: #6c757d;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        @media (max-width: 576px) {
            .not-registered-alert {
                flex-direction: column;
                text-align: center;
            }
            
            .not-registered-icon {
                margin-right: 0;
                margin-bottom: 15px;
                justify-content: center;
            }
        }
        
        /* Tab Styles */
        .tabs-container {
            position: relative;
            margin-bottom: 20px;
        }
        
        .nav-tabs {
            border-bottom: none;
            margin-bottom: 20px;
            display: flex;
            justify-content: flex-start;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding-bottom: 5px;
            gap: 5px;
            width: 100%;
            -webkit-overflow-scrolling: touch;
            position: relative;
        }
        
        .nav-tabs::-webkit-scrollbar {
            display: none;
        }
        
        .nav-tabs {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .nav-tabs .nav-item {
            margin: 0;
            flex-shrink: 0;
        }
        
        .nav-tabs .nav-link {
            border: none;
            background-color: var(--light);
            color: var(--text-gray);
            border-radius: 10px;
            padding: 10px 15px;
            font-weight: 500;
            transition: var(--transition);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            white-space: nowrap;
            font-size: 0.9rem;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary) 0%, #4dd0e1 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(66, 195, 207, 0.3);
        }
        
        .nav-tabs .nav-link:hover:not(.active) {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }
        
        /* Scroll hint for tabs */
        @media (max-width: 767px) {
            .tabs-container::after {
                content: '';
                position: absolute;
                top: 0;
                right: 0;
                height: 100%;
                width: 40px;
                background: linear-gradient(to right, rgba(255,255,255,0), rgba(232,247,249,0.8));
                pointer-events: none;
                z-index: 10;
                animation: fadeInOut 2s infinite;
            }
            
            .nav-tabs {
                padding-right: 40px; /* Space for the gradient */
            }
            
            @keyframes fadeInOut {
                0% { opacity: 0; }
                50% { opacity: 1; }
                100% { opacity: 0; }
            }
            
            /* Bounce animation for tabs to indicate scrollability */
            .nav-tabs {
                animation: bounceHorizontal 2s ease-in-out;
                animation-iteration-count: 1;
            }
            
            @keyframes bounceHorizontal {
                0%, 100% { transform: translateX(0); }
                10% { transform: translateX(-10px); }
                30% { transform: translateX(5px); }
                50% { transform: translateX(-3px); }
                70% { transform: translateX(2px); }
                90% { transform: translateX(-1px); }
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive Styles */
        @media (min-width: 576px) {
            .container {
                max-width: 540px;
            }
        }
        
        @media (min-width: 768px) {
            .container {
                max-width: 720px;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .card-body {
                padding: 25px;
            }
            
            .detail-item {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            
            .detail-label {
                margin-bottom: 0;
            }
            
            .detail-value {
                text-align: right;
            }
            
            .order-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
            
            .nav-tabs {
                justify-content: center;
            }
        }
        
        @media (min-width: 992px) {
            .container {
                max-width: 960px;
            }
            
            .page-title {
                font-size: 2.2rem;
            }
        }
        
        @media (min-width: 1200px) {
            .container {
                max-width: 1140px;
            }
        }
        
        /* Mobile-specific styles */
        @media (max-width: 767px) {
            /* Hide regular table on mobile */
            .desktop-table {
                display: none;
            }
            
            /* Show mobile table cards */
            .mobile-table {
                display: block;
            }
        }

        /* Modal Styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1050;
            display: none;
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
        }

        .modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.9);
            background-color: white;
            border-radius: 15px;
            z-index: 1051;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            display: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .modal.show {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, #4dd0e1 100%);
            color: white;
            padding: 15px 20px;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
        }

        .modal-close {
            background: transparent;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            line-height: 1;
            transition: transform 0.2s ease;
        }

        .modal-close:hover {
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: flex-end;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .detail-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .detail-value {
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        .order-items {
            margin-top: 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            padding-top: 15px;
        }

        .order-item {
            background-color: var(--gray);
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .order-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .order-item-name {
            font-weight: 500;
            font-size: 0.9rem;
        }

        .order-item-price {
            font-weight: 600;
            color: var(--primary);
            font-size: 0.9rem;
        }

        .order-total {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            font-weight: 600;
        }

        .order-total-label {
            font-size: 1rem;
        }

        .order-total-value {
            font-size: 1rem;
            color: var(--primary);
        }

        /* Animation for modal */
        @keyframes fadeInModal {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInModal {
            from { transform: translate(-50%, -60%); opacity: 0; }
            to { transform: translate(-50%, -50%); opacity: 1; }
        }

        .modal-backdrop.show {
            display: block;
            animation: fadeInModal 0.3s forwards;
        }

        .modal.show {
            display: block;
            animation: slideInModal 0.3s forwards;
        }

        /* Loading spinner for modal */
        .spinner-border {
            display: inline-block;
            width: 2rem;
            height: 2rem;
            vertical-align: text-bottom;
            border: 0.25em solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spinner-border 0.75s linear infinite;
        }

        @keyframes spinner-border {
            to { transform: rotate(360deg); }
        }

        .loading-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 30px;
            text-align: center;
        }

        .loading-text {
            margin-top: 15px;
            color: var(--text-gray);
            font-size: 0.9rem;
        }

        /* History Section Styles */
        .history-section {
            margin-top: 30px;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-divider {
            height: 2px;
            background: linear-gradient(135deg, var(--primary) 0%, #4dd0e1 100%);
            border-radius: 2px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <section class="main-content">
        <div class="container">
            <div class="page-header">
                <!-- Store Status Indicator (non-floating) -->
                <?php if ($storeStatus == 'tutup'): ?>
                <div class="store-status-indicator closed">
                    <i class="fas fa-store-slash"></i>
                    <span>Mohon maaf, saat ini toko sedang TUTUP (Diperbarui: <?php echo $statusTime; ?>)</span>
                </div>
                <?php endif; ?>
                
                <div class="page-badge">
                    <i class="fas fa-user me-2"></i> Area Pelanggan
                </div>
                <h1 class="page-title">Selamat Datang di <span>Zeea Laundry</span></h1>
                <p class="page-subtitle">Lacak status cucian Anda atau pesan layanan antar jemput dengan mudah</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <!-- Navigation Tabs -->
                    <div class="tabs-container">
                        <ul class="nav nav-tabs" id="customerTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" href="index.php">
                                    <i class="fas fa-search me-2"></i> Cek Status
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link active" href="riwayat.php">
                                    <i class="fas fa-history me-2"></i> Riwayat
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" href="antar.php">
                                    <i class="fas fa-truck-loading me-2"></i> Antar Cucian
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" href="jemput.php">
                                    <i class="fas fa-truck-pickup me-2"></i> Jemput Cucian
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="customer-card">
                        <div class="card-header">
                            <i class="fas fa-history"></i> Riwayat Cucian & Antar Jemput
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <div class="mb-4">
                                    <label for="phone_number" class="form-label">Nomor Telepon</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="text" class="form-control" id="phone_number" name="phone_number" placeholder="Masukkan nomor telepon Anda" required>
                                    </div>
                                    <div class="form-text">Masukkan nomor telepon yang terdaftar untuk melihat riwayat pesanan dan layanan antar-jemput</div>
                                </div>
                                <div class="text-center">
                                    <button type="submit" name="view_history" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> Lihat Riwayat
                                    </button>
                                </div>
                            </form>
                            
                            <?php if (!empty($orderHistory) || !empty($antarJemputHistory)): ?>
                                
                                <!-- Laundry Order History -->
                                <?php if (!empty($orderHistory)): ?>
                                <div class="history-section">
                                    <h4 class="section-title">
                                        <i class="fas fa-tshirt"></i> Riwayat Pesanan Cucian
                                    </h4>
                                    <div class="section-divider"></div>
                                    
                                    <!-- Desktop Table (hidden on mobile) -->
                                    <div class="table-responsive desktop-table">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Kode Tracking</th>
                                                    <th>Tanggal</th>
                                                    <th>Jumlah Item</th>
                                                    <th>Total Harga</th>
                                                    <th>Status</th>
                                                    <th>Pembayaran</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($orderHistory as $order): ?>
                                                    <tr>
                                                        <td><?php echo $order['tracking_code']; ?></td>
                                                        <td><?php echo date('d/m/Y', strtotime($order['waktu'])); ?></td>
                                                        <td><?php echo count($order['items']); ?> item</td>
                                                        <td>Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?></td>
                                                        <td><?php echo getStatusBadge($order['status']); ?></td>
                                                        <td>
                                                            <span class="status-badge status-<?php echo $order['status_pembayaran']; ?>">
                                                                <?php 
                                                                    if ($order['status_pembayaran'] == 'belum_dibayar') echo 'Belum Dibayar';
                                                                    else echo 'Sudah Dibayar';
                                                                ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-primary detail-btn" data-tracking="<?php echo $order['tracking_code']; ?>">
                                                                <i class="fas fa-search"></i> Detail
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Mobile Cards (shown only on mobile) -->
                                    <div class="mobile-table">
                                        <?php foreach ($orderHistory as $order): ?>
                                            <div class="mobile-order-card">
                                                <div class="mobile-order-header">
                                                    <div class="mobile-order-id"><?php echo $order['tracking_code']; ?></div>
                                                    <div class="mobile-order-date"><?php echo date('d/m/Y', strtotime($order['waktu'])); ?></div>
                                                </div>
                                                <div class="mobile-order-detail">
                                                    <div class="mobile-order-label">Jumlah Item:</div>
                                                    <div class="mobile-order-value"><?php echo count($order['items']); ?> item</div>
                                                </div>
                                                <div class="mobile-order-detail">
                                                    <div class="mobile-order-label">Total Harga:</div>
                                                    <div class="mobile-order-value">Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?></div>
                                                </div>
                                                <div class="mobile-order-footer">
                                                    <?php echo getStatusBadge($order['status']); ?>
                                                    <span class="status-badge status-<?php echo $order['status_pembayaran']; ?>">
                                                        <?php 
                                                            if ($order['status_pembayaran'] == 'belum_dibayar') echo 'Belum Dibayar';
                                                            else echo 'Sudah Dibayar';
                                                        ?>
                                                    </span>
                                                </div>
                                                <div class="text-center mt-2">
                                                    <button type="button" class="btn btn-sm btn-primary w-100 detail-btn" data-tracking="<?php echo $order['tracking_code']; ?>">
                                                        <i class="fas fa-search"></i> Lihat Detail
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Antar Jemput History -->
                                <?php if (!empty($antarJemputHistory)): ?>
                                <div class="history-section">
                                    <h4 class="section-title">
                                        <i class="fas fa-truck"></i> Riwayat Layanan Antar Jemput
                                    </h4>
                                    <div class="section-divider"></div>
                                    
                                    <!-- Desktop Table (hidden on mobile) -->
                                    <div class="table-responsive desktop-table">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Kode Tracking</th>
                                                    <th>Tanggal</th>
                                                    <th>Layanan</th>
                                                    <th>Status</th>
                                                    <th>Harga</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($antarJemputHistory as $aj): ?>
                                                    <tr>
                                                        <td>
                                                            <?php 
                                                            echo $aj['tracking_code'] ? $aj['tracking_code'] : '-'; 
                                                            ?>
                                                        </td>
                                                        <td>
                                                            <?php 
                                                            $waktu_display = '';
                                                            if (!empty($aj['waktu_antar'])) {
                                                                $waktu_display = date('d/m/Y', strtotime($aj['waktu_antar']));
                                                            } elseif (!empty($aj['waktu_jemput'])) {
                                                                $waktu_display = date('d/m/Y', strtotime($aj['waktu_jemput']));
                                                            }
                                                            echo $waktu_display;
                                                            ?>
                                                        </td>
                                                        <td><?php echo getLayananBadge($aj['layanan']); ?></td>
                                                        <td>
                                                            <?php 
                                                            if (!empty($aj['deleted_at'])) {
                                                                echo '<span class="status-badge status-dibatalkan"><i class="fas fa-times-circle me-1"></i>Dibatalkan oleh Admin</span>';
                                                            } else {
                                                                echo getStatusBadge($aj['status']);
                                                            }
                                                            ?>
                                                        </td>
                                                        <td>Rp <?php echo number_format(!empty($aj['harga_custom']) ? $aj['harga_custom'] : 5000, 0, ',', '.'); ?></td>
                                                        <td>
                                                            <button type="button" class="btn btn-sm btn-primary aj-detail-btn" data-aj-id="<?php echo $aj['id']; ?>">
                                                                <i class="fas fa-search"></i> Detail
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Mobile Cards (shown only on mobile) -->
                                    <div class="mobile-table">
                                        <?php foreach ($antarJemputHistory as $aj): ?>
                                            <div class="mobile-order-card">
                                                <div class="mobile-order-header">
                                                    <div class="mobile-order-id">
                                                        <?php 
                                                        // Tampilkan kode tracking ZL... jika ada, jika tidak tampilkan strip
                                                        echo $aj['tracking_code'] ? $aj['tracking_code'] : '-'; 
                                                        ?>
                                                    </div>
                                                    <div class="mobile-order-date">
                                                        <?php 
                                                        $waktu_display = '';
                                                        if (!empty($aj['waktu_antar'])) {
                                                            $waktu_display = date('d/m/Y', strtotime($aj['waktu_antar']));
                                                        } elseif (!empty($aj['waktu_jemput'])) {
                                                            $waktu_display = date('d/m/Y', strtotime($aj['waktu_jemput']));
                                                        }
                                                        echo $waktu_display;
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="mobile-order-detail">
                                                    <div class="mobile-order-label">Layanan:</div>
                                                    <div class="mobile-order-value"><?php echo getLayananBadge($aj['layanan']); ?></div>
                                                </div>
                                                <div class="mobile-order-detail">
                                                    <div class="mobile-order-label">Status:</div>
                                                    <div class="mobile-order-value">
                                                        <?php 
                                                        if (!empty($aj['deleted_at'])) {
                                                            echo '<span class="status-badge status-dibatalkan"><i class="fas fa-times-circle me-1"></i>Dibatalkan oleh Admin</span>';
                                                        } else {
                                                            echo getStatusBadge($aj['status']);
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                                <div class="mobile-order-detail">
                                                    <div class="mobile-order-label">Harga:</div>
                                                    <div class="mobile-order-value">Rp <?php echo number_format(!empty($aj['harga_custom']) ? $aj['harga_custom'] : 5000, 0, ',', '.'); ?></div>
                                                </div>
                                                <div class="mobile-order-footer">
                                                    <button type="button" class="btn btn-sm btn-primary aj-detail-btn" data-aj-id="<?php echo $aj['id']; ?>">
                                                        <i class="fas fa-search"></i> Lihat Detail
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if (!empty($message)): ?>
                                <div class="mt-4">
                                    <?php echo $message; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="../" class="back-btn w-100">
                            <i class="fas fa-arrow-left"></i> Kembali ke Beranda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Order Detail Modal -->
    <div class="modal-backdrop" id="modalBackdrop"></div>
    <div class="modal" id="orderDetailModal">
        <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-receipt me-2"></i> Detail Pesanan</h5>
            <button type="button" class="modal-close" id="closeModal">&times;</button>
        </div>
        <div class="modal-body" id="modalContent">
            <div class="loading-container">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="loading-text">Memuat detail pesanan...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="closeModalBtn">Tutup</button>
        </div>
    </div>

    <!-- Antar Jemput Detail Modal -->
    <div class="modal-backdrop" id="ajModalBackdrop"></div>
    <div class="modal" id="ajDetailModal">
        <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-truck me-2"></i> Detail Antar Jemput</h5>
            <button type="button" class="modal-close" id="closeAjModal">&times;</button>
        </div>
        <div class="modal-body" id="ajModalContent">
            <div class="loading-container">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="loading-text">Memuat detail antar jemput...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="closeAjModalBtn">Tutup</button>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p class="footer-text">&copy; <?php echo date('Y'); ?> Zeea Laundry. Semua hak dilindungi.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Order Detail Modal
            const modal = document.getElementById('orderDetailModal');
            const modalBackdrop = document.getElementById('modalBackdrop');
            const closeModal = document.getElementById('closeModal');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const modalContent = document.getElementById('modalContent');
            const detailButtons = document.querySelectorAll('.detail-btn');

            // Antar Jemput Detail Modal
            const ajModal = document.getElementById('ajDetailModal');
            const ajModalBackdrop = document.getElementById('ajModalBackdrop');
            const closeAjModal = document.getElementById('closeAjModal');
            const closeAjModalBtn = document.getElementById('closeAjModalBtn');
            const ajModalContent = document.getElementById('ajModalContent');
            const ajDetailButtons = document.querySelectorAll('.aj-detail-btn');

            // Function to open order modal
            function openModal() {
                modalBackdrop.classList.add('show');
                modal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            // Function to close order modal
            function closeModalFunc() {
                modalBackdrop.classList.remove('show');
                modal.classList.remove('show');
                document.body.style.overflow = '';
                
                setTimeout(() => {
                    modalContent.innerHTML = `
                        <div class="loading-container">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="loading-text">Memuat detail pesanan...</p>
                        </div>
                    `;
                }, 300);
            }

            // Function to open antar jemput modal
            function openAjModal() {
                ajModalBackdrop.classList.add('show');
                ajModal.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            // Function to close antar jemput modal
            function closeAjModalFunc() {
                ajModalBackdrop.classList.remove('show');
                ajModal.classList.remove('show');
                document.body.style.overflow = '';
                
                setTimeout(() => {
                    ajModalContent.innerHTML = `
                        <div class="loading-container">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="loading-text">Memuat detail antar jemput...</p>
                        </div>
                    `;
                }, 300);
            }

            // Add click event to all order detail buttons
            detailButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const trackingCode = this.getAttribute('data-tracking');
                    openModal();
                    
                    fetch('riwayat.php?ajax_get_details=1&tracking_code=' + trackingCode)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const orderDate = new Date(data.order.waktu);
                                const formattedDate = orderDate.toLocaleDateString('id-ID', {
                                    day: '2-digit',
                                    month: '2-digit',
                                    year: 'numeric',
                                    hour: '2-digit',
                                    minute: '2-digit'
                                });

                                let statusText = 'Diproses';
                                if (data.order.status === 'selesai') statusText = 'Selesai';
                                else if (data.order.status === 'dibatalkan') statusText = 'Dibatalkan';

                                let paymentStatusText = 'Belum Dibayar';
                                if (data.order.status_pembayaran === 'sudah_dibayar') paymentStatusText = 'Sudah Dibayar';

                                let html = `
                                    <div class="order-details">
                                        <div class="detail-item">
                                            <div class="detail-label">Kode Tracking</div>
                                            <div class="detail-value">${data.order.tracking_code}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Tanggal Pesanan</div>
                                            <div class="detail-value">${formattedDate}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Nama Pelanggan</div>
                                            <div class="detail-value">${data.order.nama_pelanggan}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Nomor Telepon</div>
                                            <div class="detail-value">${data.order.telepon}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Status Pesanan</div>
                                            <div class="detail-value">
                                                <span class="status-badge status-${data.order.status}">${statusText}</span>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Status Pembayaran</div>
                                            <div class="detail-value">
                                                <span class="status-badge status-${data.order.status_pembayaran}">${paymentStatusText}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="order-items">
                                        <h6 class="mb-3">Item Pesanan</h6>
                                `;

                                data.items.forEach(item => {
                                    html += `
                                        <div class="order-item">
                                            <div class="order-item-header">
                                                <div class="order-item-name">${item.nama_paket}</div>
                                                <div class="order-item-price">Rp ${new Intl.NumberFormat('id-ID').format(item.harga)}</div>
                                            </div>
                                            <div class="order-item-details">
                                                <small>${item.berat} kg x Rp ${new Intl.NumberFormat('id-ID').format(item.harga_per_kg)}</small>
                                            </div>
                                        </div>
                                    `;
                                });

                                html += `
                                    <div class="order-total">
                                        <div class="order-total-label">Total</div>
                                        <div class="order-total-value">Rp ${new Intl.NumberFormat('id-ID').format(data.order.total_harga)}</div>
                                    </div>
                                `;

                                html += `</div>`;
                                
                                modalContent.innerHTML = html;
                            } else {
                                modalContent.innerHTML = `
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-circle me-2"></i> 
                                        Gagal memuat detail pesanan. ${data.message}
                                    </div>
                                `;
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching order details:', error);
                            modalContent.innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i> 
                                    Terjadi kesalahan saat memuat detail pesanan. Silakan coba lagi nanti.
                                </div>
                            `;
                        });
                });
            });

            // Add click event to all antar jemput detail buttons
            ajDetailButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const ajId = this.getAttribute('data-aj-id');
                    openAjModal();
                    
                    fetch('riwayat.php?ajax_get_aj_details=1&aj_id=' + ajId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const aj = data.antar_jemput;
                                
                                let waktuDisplay = '';
                                if (aj.waktu_antar) {
                                    const waktuAntar = new Date(aj.waktu_antar);
                                    waktuDisplay = waktuAntar.toLocaleDateString('id-ID', {
                                        day: '2-digit',
                                        month: '2-digit',
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    });
                                } else if (aj.waktu_jemput) {
                                    const waktuJemput = new Date(aj.waktu_jemput);
                                    waktuDisplay = waktuJemput.toLocaleDateString('id-ID', {
                                        day: '2-digit',
                                        month: '2-digit',
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    });
                                }

                                function getStatusText(status) {
                                    switch (status) {
                                        case 'menunggu': return 'Menunggu';
                                        case 'dalam perjalanan': return 'Dalam Perjalanan';
                                        case 'selesai': return 'Selesai';
                                        case 'dibatalkan': return 'Dibatalkan';
                                        default: return status;
                                    }
                                }

                                function getLayananText(layanan) {
                                    switch (layanan) {
                                        case 'antar': return 'Antar';
                                        case 'jemput': return 'Jemput';
                                        case 'antar-jemput': return 'Antar & Jemput';
                                        default: return layanan;
                                    }
                                }

                                let html = `
                                    <div class="order-details">
                                        <div class="detail-item">
                                            <div class="detail-label">Kode Tracking</div>
                                            <div class="detail-value">AJ-${String(aj.id).padStart(6, '0')}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Tanggal</div>
                                            <div class="detail-value">${waktuDisplay}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Nama Pelanggan</div>
                                            <div class="detail-value">${aj.nama_pelanggan_final}</div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Layanan</div>
                                            <div class="detail-value">
                                                <span class="layanan-badge layanan-${aj.layanan}">${getLayananText(aj.layanan)}</span>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-label">Status</div>
                                            <div class="detail-value">
                                                <span class="status-badge status-${aj.status}">${getStatusText(aj.status)}</span>
                                            </div>
                                        </div>
                                `;

                                if (aj.alamat_antar) {
                                    html += `
                                        <div class="detail-item">
                                            <div class="detail-label">Alamat Antar</div>
                                            <div class="detail-value">${aj.alamat_antar}</div>
                                        </div>
                                    `;
                                }

                                if (aj.alamat_jemput) {
                                    html += `
                                        <div class="detail-item">
                                            <div class="detail-label">Alamat Jemput</div>
                                            <div class="detail-value">${aj.alamat_jemput}</div>
                                        </div>
                                    `;
                                }

                                if (aj.pesanan_tracking_code) {
                                    html += `
                                        <div class="detail-item">
                                            <div class="detail-label">Terkait Pesanan</div>
                                            <div class="detail-value">${aj.pesanan_tracking_code}</div>
                                        </div>
                                    `;
                                }

                                html += `
                                        <div class="detail-item">
                                            <div class="detail-label">Harga Layanan</div>
                                            <div class="detail-value">Rp ${new Intl.NumberFormat('id-ID').format(aj.harga_custom || 5000)}</div>
                                        </div>
                                    </div>
                                `;
                                
                                ajModalContent.innerHTML = html;
                            } else {
                                ajModalContent.innerHTML = `
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-circle me-2"></i> 
                                        Gagal memuat detail antar jemput. ${data.message}
                                    </div>
                                `;
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching antar jemput details:', error);
                            ajModalContent.innerHTML = `
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i> 
                                    Terjadi kesalahan saat memuat detail antar jemput. Silakan coba lagi nanti.
                                </div>
                            `;
                        });
                });
            });

            // Close modal event listeners
            closeModal.addEventListener('click', closeModalFunc);
            closeModalBtn.addEventListener('click', closeModalFunc);
            closeAjModal.addEventListener('click', closeAjModalFunc);
            closeAjModalBtn.addEventListener('click', closeAjModalFunc);

            // Close modal when clicking outside
            modalBackdrop.addEventListener('click', function(event) {
                if (event.target === modalBackdrop) {
                    closeModalFunc();
                }
            });

            ajModalBackdrop.addEventListener('click', function(event) {
                if (event.target === ajModalBackdrop) {
                    closeAjModalFunc();
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key ===  'Escape') {
                    if (modal.classList.contains('show')) {
                        closeModalFunc();
                    }
                    if (ajModal.classList.contains('show')) {
                        closeAjModalFunc();
                    }
                }
            });
        });
    </script>
</body>
</html>

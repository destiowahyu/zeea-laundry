<?php
// Include database connection
include '../includes/db.php';

// Initialize variables
$trackingCode = "";
$orderDetails = null;
$orderHistory = [];
$message = "";
$pickupMessage = "";
$deliveryMessage = "";
$hasCompletedOrders = false;
$completedOrderId = 0;
$showPaymentInfo = false;
$redirectToTrack = false;
$processingOrderDetails = null;

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

// Check if antar-jemput service is active
$serviceStatusQuery = $conn->query("SELECT setting_value FROM settings WHERE setting_name = 'antar_jemput_active'");
$serviceActive = true; // Default to active if setting doesn't exist
if ($serviceStatusQuery && $serviceStatusQuery->num_rows > 0) {
    $serviceActive = ($serviceStatusQuery->fetch_assoc()['setting_value'] === 'active');
}

// Process AJAX request for delivery check
if (isset($_POST['check_delivery_ajax']) && isset($_POST['tracking_code'])) {
    $trackingCode = $_POST['tracking_code'];
    $response = array('success' => false);

    // Get order details from tracking code
    $stmt = $conn->prepare("SELECT p.id, p.id_pelanggan, p.status, pl.nama as nama_pelanggan 
                           FROM pesanan p 
                           JOIN pelanggan pl ON p.id_pelanggan = pl.id 
                           WHERE p.tracking_code = ?");
    $stmt->bind_param("s", $trackingCode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        
        // Check if this order already has a delivery request
        $checkDeliveryStmt = $conn->prepare("SELECT id FROM antar_jemput WHERE id_pesanan = ? AND layanan = 'antar'");
        $checkDeliveryStmt->bind_param("i", $order['id']);
        $checkDeliveryStmt->execute();
        $deliveryResult = $checkDeliveryStmt->get_result();
        
        if ($deliveryResult->num_rows > 0) {
            // Already has a delivery request
            $response['success'] = false;
            $response['already_requested'] = true;
            $response['message'] = "Pesanan ini sudah memiliki permintaan pengantaran.";
        } else if ($order['status'] == 'diproses') {
            $response['success'] = false;
            $response['processing'] = true;
            $response['order_id'] = $order['id'];
        } else if ($order['status'] == 'selesai') {
            $response['success'] = true;
            $response['order_id'] = $order['id'];
            $response['customer_name'] = $order['nama_pelanggan'];
        }
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Process tracking form
if (isset($_POST['track'])) {
    $trackingCode = $_POST['tracking_code'];

    $query = "SELECT p.*, pk.nama as nama_paket, pk.harga as harga_per_kg, pl.nama as nama_pelanggan, pl.no_hp as telepon 
         FROM pesanan p 
         JOIN paket pk ON p.id_paket = pk.id 
         JOIN pelanggan pl ON p.id_pelanggan = pl.id 
         WHERE p.tracking_code = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $trackingCode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Get the first order for customer details
        $orderDetails = $result->fetch_assoc();
        
        // Reset the result pointer
        $result->data_seek(0);
        
        // Store all order items
        $orderItems = [];
        $totalHarga = 0;
        while ($item = $result->fetch_assoc()) {
            $orderItems[] = $item;
            $totalHarga += $item['harga'];
        }

        // Check if the order is older than 7 days
        $orderDate = new DateTime($orderDetails['waktu']);
        $currentDate = new DateTime();
        $interval = $orderDate->diff($currentDate);
        
        if ($interval->days > 7) {
            $message = "Pesanan ini sudah lebih dari 7 hari. Silahkan buat pesanan baru ke Zeea Laundry.";
        }
        
        // Check if customer has completed orders for delivery
        if ($orderDetails['status'] == 'selesai') {
            $hasCompletedOrders = true;
            $completedOrderId = $orderDetails['id'];
        }
    } else {
        $message = "Kode tracking tidak ditemukan. Silakan periksa kembali kode tracking Anda.";
    }
}

// Process history form
if (isset($_POST['view_history'])) {
    $trackingCode = $_POST['history_tracking_code'];

    // Get order details from tracking code
    $query = "SELECT p.*, pk.nama as nama_paket, pk.harga as harga_per_kg, pl.nama as nama_pelanggan, pl.no_hp as telepon 
             FROM pesanan p 
             JOIN paket pk ON p.id_paket = pk.id 
             JOIN pelanggan pl ON p.id_pelanggan = pl.id 
             WHERE p.tracking_code = ? 
             LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $trackingCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        $customerId = $order['id_pelanggan'];
        
        // Get all unique tracking codes for this customer
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
        
        if (count($orderHistory) == 0) {
            $message = "Tidak ada riwayat pesanan ditemukan untuk kode tracking ini.";
        }
    } else {
        $message = "<div class='not-registered-alert'>
            <div class='not-registered-icon'>
                <i class='fas fa-exclamation-circle'></i>
            </div>
            <div class='not-registered-content'>
                <h5>Kode Tracking Tidak Ditemukan</h5>
                <p>Kode tracking yang Anda masukkan tidak terdaftar dalam sistem kami. Silakan periksa kembali kode tracking Anda atau gunakan layanan jemput cucian untuk mendaftar.</p>
                <button type='button' class='btn btn-primary mt-2' onclick=\"document.getElementById('pickup-tab').click()\">
                    <i class='fas fa-truck-pickup me-2'></i> Gunakan Layanan Jemput
                </button>
            </div>
        </div>";
    }
}

// Process delivery request
if (isset($_POST['request_delivery'])) {
    $trackingCode = $_POST['delivery_tracking_code'];
    $address = $_POST['delivery_address'];
    $delivery_time = $_POST['delivery_time'];
    $notes = $_POST['delivery_notes'];
    $order_id = $_POST['order_id'];

    // Check if delivery time is after 17:00
    $deliveryDateTime = new DateTime($delivery_time);
    $cutoffTime = clone $deliveryDateTime;
    $cutoffTime->setTime(17, 0, 0);
    
    if ($deliveryDateTime > $cutoffTime) {
        // If after 17:00, set to next day at 10:00
        $deliveryDateTime->modify('+1 day');
        $deliveryDateTime->setTime(10, 0, 0);
        $delivery_time = $deliveryDateTime->format('Y-m-d H:i:s');
        $deliveryMessage = "Waktu pengantaran yang Anda pilih melebihi batas waktu (17:00). Pesanan Anda akan diantar besok pukul 10:00.";
    }

    // Validate order exists and is completed
    $orderQuery = "SELECT id FROM pesanan WHERE id = ? AND status = 'selesai'";
    $stmt = $conn->prepare($orderQuery);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $orderResult = $stmt->get_result();

    if ($orderResult->num_rows > 0) {
        // Insert delivery request
        $stmt = $conn->prepare("INSERT INTO antar_jemput (id_pesanan, layanan, alamat_antar, status, waktu_antar) VALUES (?, 'antar', ?, 'menunggu', ?)");
        $stmt->bind_param("iss", $order_id, $address, $delivery_time);
        
        if ($stmt->execute()) {
            if (empty($deliveryMessage)) {
                $deliveryMessage = "Permintaan pengantaran berhasil dikirim. Tim kami akan mengantar cucian Anda sesuai jadwal.";
            } else {
                $deliveryMessage .= " Permintaan pengantaran berhasil dikirim.";
            }
            $showPaymentInfo = true;
        } else {
            $deliveryMessage = "Gagal mengirim permintaan. Silakan coba lagi. Error: " . $stmt->error;
        }
    } else {
        $deliveryMessage = "Pesanan tidak ditemukan atau belum selesai.";
    }
}

// Process pickup request
if (isset($_POST['request_pickup'])) {
    $name = $_POST['pickup_name'];
    $phone = $_POST['pickup_phone'];
    // Format phone number to ensure it starts with +62
    if (substr($phone, 0, 1) === '0') {
        $phone = '+62' . substr($phone, 1);
    }

    $address = $_POST['pickup_address'];
    $pickup_time = $_POST['pickup_time'];
    $notes = $_POST['pickup_notes'];

    // Check if pickup time is after 17:00
    $pickupDateTime = new DateTime($pickup_time);
    $cutoffTime = clone $pickupDateTime;
    $cutoffTime->setTime(17, 0, 0);
    
    if ($pickupDateTime > $cutoffTime) {
        // If after 17:00, set to next day at 10:00
        $pickupDateTime->modify('+1 day');
        $pickupDateTime->setTime(10, 0, 0);
        $pickup_time = $pickupDateTime->format('Y-m-d H:i:s');
        $pickupMessage = "Waktu penjemputan yang Anda pilih melebihi batas waktu (17:00). Pesanan Anda akan dijemput besok pukul 10:00.";
    }

    // Check if customer exists
    $stmt = $conn->prepare("SELECT id FROM pelanggan WHERE no_hp = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    $customerId = 0;

    if ($result->num_rows > 0) {
        $customer = $result->fetch_assoc();
        $customerId = $customer['id'];
    } else {
        // Create new customer
        $stmt = $conn->prepare("INSERT INTO pelanggan (no_hp, nama) VALUES (?, ?)");
        $stmt->bind_param("ss", $phone, $name);
        
        if ($stmt->execute()) {
            $customerId = $conn->insert_id;
        } else {
            $pickupMessage = "Gagal mendaftarkan pelanggan baru. Silakan coba lagi.";
        }
    }

    if ($customerId > 0) {
        // Insert pickup request
        $stmt = $conn->prepare("INSERT INTO antar_jemput (id_pesanan, layanan, alamat_jemput, status, waktu_jemput) VALUES (NULL, 'jemput', ?, 'menunggu', ?)");
        $stmt->bind_param("ss", $address, $pickup_time);
        
        if ($stmt->execute()) {
            if (empty($pickupMessage)) {
                $pickupMessage = "Permintaan penjemputan berhasil dikirim. Tim kami akan menjemput cucian Anda sesuai jadwal.";
            } else {
                $pickupMessage .= " Permintaan penjemputan berhasil dikirim.";
            }
            $showPaymentInfo = true;
        } else {
            $pickupMessage = "Gagal mengirim permintaan. Silakan coba lagi. Error: " . $stmt->error;
        }
    }
}

// Check if we need to redirect to track tab due to processing order
if (isset($_GET['redirect_to_track']) && isset($_GET['order_id'])) {
    $redirectToTrack = true;
    $processingOrderId = $_GET['order_id'];
    
    // Get order details
    $query = "SELECT p.*, pk.nama as nama_paket, pk.harga as harga_per_kg, pl.nama as nama_pelanggan, pl.no_hp as telepon 
             FROM pesanan p 
             JOIN paket pk ON p.id_paket = pk.id 
             JOIN pelanggan pl ON p.id_pelanggan = pl.id 
             WHERE p.id = ? 
             LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $processingOrderId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $processingOrderDetails = $result->fetch_assoc();
        $trackingCode = $processingOrderDetails['tracking_code'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Zeea Laundry - Pelanggan</title>
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
        
        /* Store Status Banner */
        .store-status-banner {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            padding: 15px;
            text-align: center;
            color: white;
            font-weight: 500;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.5s ease;
        }
        
        .store-status-banner.closed {
            background: linear-gradient(135deg, #dc3545, #e83e8c);
        }
        
        .store-status-banner.open {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .store-status-banner-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            z-index: 2;
        }
        
        .store-status-banner i {
            font-size: 1.5rem;
        }
        
        .store-status-banner-text {
            font-size: 1.1rem;
        }
        
        .store-status-banner-time {
            font-size: 0.85rem;
            opacity: 0.9;
            margin-left: 10px;
        }
        
        .store-status-banner-close {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.3);
            border: none;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        
        .store-status-banner-close:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        
        /* Animated background for closed banner */
        .store-status-banner.closed::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                45deg,
                rgba(0, 0, 0, 0.1),
                rgba(0, 0, 0, 0.1) 10px,
                rgba(0, 0, 0, 0.2) 10px,
                rgba(0, 0, 0, 0.2) 20px
            );
            animation: slide 20s linear infinite;
            z-index: 1;
        }
        
        @keyframes slide {
            from {
                background-position: 0 0;
            }
            to {
                background-position: 100% 0;
            }
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-100%);
            }
            to {
                transform: translateY(0);
            }
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(0);
            }
            to {
                transform: translateY(-100%);
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .store-status-banner {
                padding: 12px 10px;
            }
            
            .store-status-banner-content {
                flex-direction: column;
                gap: 5px;
            }
            
            .store-status-banner-time {
                margin-left: 0;
                font-size: 0.8rem;
            }
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
        
        /* Time Limit Notice */
        .time-limit-notice {
            background-color: rgba(255, 193, 7, 0.1);
            border-left: 4px solid var(--secondary);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 0 10px 10px 0;
        }
        
        .time-limit-notice h5 {
            color: var(--secondary-dark);
            font-size: 1rem;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .time-limit-notice p {
            color: var(--text-gray);
            font-size: 0.9rem;
            margin-bottom: 0;
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
        
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .btn-success:hover {
            background-color: #218838;
            border-color: #218838;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
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
        
        /* Order Card */
        .order-card {
            background-color: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-top: 20px;
            animation: fadeInUp 1s ease;
            width: 100%;
        }
        
        .order-header {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .order-id {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        .order-date {
            font-size: 0.85rem;
            color: var(--text-gray);
        }
        
        .order-details {
            margin-bottom: 15px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px dashed rgba(0, 0, 0, 0.1);
        }
        
        .detail-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 4px;
            font-size: 0.9rem;
        }
        
        .detail-value {
            color: var(--text-gray);
            text-align: left;
            font-size: 0.9rem;
        }
        
        .order-status {
            text-align: center;
            margin-top: 15px;
        }
        
        /* Payment Section */
        .payment-section {
            background-color: rgba(66, 195, 207, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            text-align: center;
        }
        
        .payment-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary);
        }
        
        .qris-container {
            max-width: 200px;
            margin: 0 auto 15px;
        }
        
        .qris-container img {
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .payment-instructions {
            font-size: 0.85rem;
            color: var(--text-gray);
            margin-bottom: 15px;
        }
        
        .payment-options {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .payment-options-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        .payment-option {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .payment-option i {
            color: var(--primary);
            margin-right: 10px;
            font-size: 1rem;
        }
        
        .payment-option-text {
            font-size: 0.9rem;
            color: var(--text-gray);
        }
        
        .confirm-payment-btn {
            font-weight: 400;
            font-size: 1rem;
            white-space: normal;
            height: auto;
            min-height: 48px;
            padding: 10px 15px;
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

        .table tr:hover .status-processing {
            animation: pulse-warning 1s infinite alternate;
        }

        .table tr:hover .payment-unpaid {
            animation: pulse-danger 1s infinite alternate;
        }

        .order-card:hover .status-processing {
            animation: pulse-warning 1s infinite alternate;
        }

        .order-card:hover .payment-unpaid {
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
        
        /* Processing Order Alert */
        .processing-order-alert {
            background-color: rgba(255, 193, 7, 0.1);
            border-left: 4px solid var(--secondary);
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 0 10px 10px 0;
            animation: fadeInUp 0.8s ease;
        }
        
        .processing-order-alert h5 {
            color: var(--secondary-dark);
            font-size: 1.1rem;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .processing-order-alert p {
            color: var(--text-gray);
            font-size: 0.95rem;
            margin-bottom: 15px;
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
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
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
            
            .tabs-container::after {
                display: none;
            }
        }
        
        @media (min-width: 992px) {
            .container {
                max-width: 960px;
            }
            
            .page-title {
                font-size: 2.2rem;
            }
            
            .nav-tabs {
                justify-content: center;
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
            
            .tab-content {
                width: 100%;
            }
            
            .input-group {
                width: 100%;
            }
            
            .input-group-text {
                padding: 0 10px;
            }
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

        /* QRIS Modal Styles */
        .qris-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.8);
            animation: fadeIn 0.3s ease;
        }

        .qris-modal-content {
            position: relative;
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border-radius: 15px;
            max-width: 90%;
            max-height: 90%;
            top: 50%;
            transform: translateY(-50%);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .qris-modal-close {
            position: absolute;
            top: 10px;
            right: 15px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            z-index: 2001;
        }

        .qris-modal-close:hover {
            color: #333;
        }

        .qris-modal-image {
            max-width: 100%;
            max-height: 70vh;
            margin-bottom: 20px;
            border-radius: 10px;
        }

        .qris-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 15px;
        }

        .qris-container {
            position: relative;
            max-width: 200px;
            margin: 0 auto 15px;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .qris-container:after {
            content: 'Klik untuk memperbesar atau mengunduh';
            display: block;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-gray);
            margin-top: 8px;
            font-style: italic;
        }

        .qris-container:hover {
            transform: scale(1.05);
        }

        .qris-container img {
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .download-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            font-size: 0.95rem;
        }

        .download-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(66, 195, 207, 0.3);
            color: white;
        }

        .paket-item {
            background-color: rgba(66, 195, 207, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary);
        }

        .paket-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed rgba(0, 0, 0, 0.1);
        }

        .paket-item-title {
            font-weight: bold;
            font-size: 16px;
            color: #333;
        }

        .paket-item-id {
            font-size: 0.85rem;
            color: var(--text-gray);
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

        .total-price-item {
            margin-top: 15px;
            padding: 15px;
            background-color: rgba(66, 195, 207, 0.1);
            border-radius: 10px;
            border-left: 5px solid var(--primary);
        }

        .total-price-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #28a745;
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <section class="main-content">
        <div class="container">
            <div class="page-header">
                <div class="page-badge">
                    <i class="fas fa-user me-2"></i> Area Pelanggan
                </div>
                <h1 class="page-title">Selamat Datang di <span>Zeea Laundry</span></h1>
                <p class="page-subtitle">Lacak status cucian Anda atau pesan layanan antar jemput dengan mudah</p>
            </div>
            
            <!-- Store Status Banner -->
            <?php if ($storeStatus == 'tutup'): ?>
            <div class="store-status-banner closed" id="storeStatusBanner">
                <div class="store-status-banner-content">
                    <i class="fas fa-store-slash"></i>
                    <div class="store-status-banner-text">
                        Mohon maaf, saat ini toko sedang TUTUP
                        <span class="store-status-banner-time">
                            (Diperbarui: <?php echo $statusTime; ?>)
                        </span>
                    </div>
                </div>
                <button class="store-status-banner-close" onclick="closeStatusBanner()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php elseif ($storeStatus == 'buka'): ?>
            <div class="store-status-banner open" id="storeStatusBanner">
                <div class="store-status-banner-content">
                    <i class="fas fa-store"></i>
                    <div class="store-status-banner-text">
                        Toko sedang BUKA dan siap melayani Anda
                    </div>
                </div>
                <button class="store-status-banner-close" onclick="closeStatusBanner()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endif; ?>
            
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="tabs-container">
                        <ul class="nav nav-tabs" id="customerTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= $redirectToTrack ? 'active' : '' ?>" id="track-tab" data-bs-toggle="tab" data-bs-target="#track" type="button" role="tab" aria-controls="track" aria-selected="<?= $redirectToTrack ? 'true' : 'false' ?>">
                                    <i class="fas fa-search me-2"></i> Cek Status Cucian
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab" aria-controls="history" aria-selected="false">
                                    <i class="fas fa-history me-2"></i> Riwayat Cucian
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= (!$redirectToTrack && !isset($_POST['track']) && !isset($_POST['view_history']) && !isset($_POST['request_pickup'])) ? 'active' : '' ?>" id="delivery-tab" data-bs-toggle="tab" data-bs-target="#delivery" type="button" role="tab" aria-controls="delivery" aria-selected="<?= (!$redirectToTrack && !isset($_POST['track']) && !isset($_POST['view_history']) && !isset($_POST['request_pickup'])) ? 'true' : 'false' ?>">
                                    <i class="fas fa-truck-loading me-2"></i> Antar Cucian
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= isset($_POST['request_pickup']) ? 'active' : '' ?>" id="pickup-tab" data-bs-toggle="tab" data-bs-target="#pickup" type="button" role="tab" aria-controls="pickup" aria-selected="<?= isset($_POST['request_pickup']) ? 'true' : 'false' ?>">
                                    <i class="fas fa-truck-pickup me-2"></i> Jemput Cucian
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="tab-content" id="customerTabsContent">
                        <!-- Tracking Tab -->
                        <div class="tab-pane fade <?= $redirectToTrack || isset($_POST['track']) ? 'show active' : '' ?>" id="track" role="tabpanel" aria-labelledby="track-tab">
                            <div class="customer-card">
                                <div class="card-header">
                                    <i class="fas fa-search"></i> Cek Status Cucian
                                </div>
                                <div class="card-body">
                                    <?php if ($redirectToTrack && $processingOrderDetails): ?>
                                    <div class="processing-order-alert">
                                        <h5><i class="fas fa-exclamation-circle me-2"></i> Cucian Anda Masih Diproses</h5>
                                        <p>Mohon maaf, cucian Anda dengan nomor pesanan #<?= $processingOrderDetails['id'] ?> masih dalam proses pengerjaan. <strong>Layanan antar cucian hanya tersedia untuk cucian yang sudah selesai diproses.</strong></p>
                                        <p>Silakan cek kembali status cucian Anda nanti. Terima kasih atas kesabaran Anda.</p>
                                        <div class="mt-3">
                                            <a href="index.php" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-redo-alt me-2"></i> Cek Kode Lain
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="order-card">
                                        <div class="order-header">
                                            <div class="order-id">Kode Tracking: <?php echo $processingOrderDetails['tracking_code']; ?></div>
                                            <div class="order-date"><?php echo date('d/m/Y H:i', strtotime($processingOrderDetails['waktu'])); ?></div>
                                        </div>
                                        <div class="order-details">
                                            <div class="detail-item">
                                                <div class="detail-label">Nama Pelanggan</div>
                                                <div class="detail-value"><?php echo $processingOrderDetails['nama_pelanggan']; ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Kode Tracking</div>
                                                <div class="detail-value"><?php echo $processingOrderDetails['tracking_code']; ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Paket Laundry</div>
                                                <div class="detail-value"><?php echo $processingOrderDetails['nama_paket']; ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Harga per Kilogram</div>
                                                <div class="detail-value">Rp <?php echo number_format($processingOrderDetails['harga_per_kg'], 0, ',', '.'); ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Berat Cucian</div>
                                                <div class="detail-value"><?php echo $processingOrderDetails['berat']; ?> kg</div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Total Harga</div>
                                                <div class="detail-value">Rp <?php echo number_format($processingOrderDetails['harga'], 0, ',', '.'); ?></div>
                                            </div>
                                            <?php if ($processingOrderDetails['harga_custom'] > 0): ?>
                                            <div class="detail-item">
                                                <div class="detail-label">Harga Custom</div>
                                                <div class="detail-value">Rp <?php echo number_format($processingOrderDetails['harga_custom'], 0, ',', '.'); ?></div>
                                            </div>
                                            <?php endif; ?>
                                            <div class="detail-item">
                                                <div class="detail-label">Status Pesanan</div>
                                                <div class="detail-value">
                                                    <span class="status-badge status-processing">
                                                        Diproses
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Status Pembayaran</div>
                                                <div class="detail-value">
                                                    <span class="status-badge <?= $processingOrderDetails['status_pembayaran'] == 'belum_dibayar' ? 'payment-unpaid' : '' ?>">
                                                        <?php 
                                                            if ($processingOrderDetails['status_pembayaran'] == 'belum_dibayar') echo 'Belum Dibayar';
                                                            else echo 'Sudah Dibayar';
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <form method="post" action="#track">
                                        <div class="mb-4">
                                            <label for="tracking_code" class="form-label">Kode Tracking</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                                <input type="text" class="form-control" id="tracking_code" name="tracking_code" value="<?php echo $trackingCode; ?>" placeholder="Masukkan kode tracking Anda" required>
                                            </div>
                                            <div class="form-text">Masukkan kode tracking yang tertera pada nota laundry Anda</div>
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" name="track" class="btn btn-primary w-100">
                                                <i class="fas fa-search"></i> Cek Status Cucian
                                            </button>
                                        </div>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($message)): ?>
                                        <div class="alert alert-info mt-4">
                                            <i class="fas fa-info-circle me-2"></i> <?php echo $message; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($orderDetails && empty($message)): ?>
                                    <div class="order-card">
                                        <div class="order-header">
                                            <div class="order-id">Kode Tracking: <?php echo $orderDetails['tracking_code']; ?></div>
                                            <div class="order-date"><?php echo date('d/m/Y H:i', strtotime($orderDetails['waktu'])); ?></div>
                                        </div>
                                        <div class="order-details">
                                            <div class="detail-item">
                                                <div class="detail-label">Nama Pelanggan</div>
                                                <div class="detail-value"><?php echo $orderDetails['nama_pelanggan']; ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Kode Tracking</div>
                                                <div class="detail-value"><?php echo $orderDetails['tracking_code']; ?></div>
                                            </div>
                                            
                                            <!-- Display all order items -->
                                            <div class="detail-item">
                                                <div class="detail-label">Daftar Paket Laundry</div>
                                                <div class="detail-value"></div>
                                            </div>
                                            
                                            <?php foreach ($orderItems as $index => $item): ?>
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
                                                            echo number_format($item['harga_per_kg'], 0, ',', '.');
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
                                            
                                            <div class="detail-item total-price-item">
                                                <div class="detail-label">Total Harga Keseluruhan</div>
                                                <div class="detail-value total-price-value">Rp <?php echo number_format($totalHarga, 0, ',', '.'); ?></div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Status Pesanan</div>
                                                <div class="detail-value">
                                                    <span class="status-badge status-<?php echo $orderDetails['status']; ?>">
                                                        <?php 
                                                            if ($orderDetails['status'] == 'diproses') echo 'Diproses';
                                                            elseif ($orderDetails['status'] == 'selesai') echo 'Selesai';
                                                            else echo 'Dibatalkan';
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-label">Status Pembayaran</div>
                                                <div class="detail-value">
                                                    <span class="status-badge status-<?php echo $orderDetails['status_pembayaran']; ?>">
                                                        <?php 
                                                            if ($orderDetails['status_pembayaran'] == 'belum_dibayar') echo 'Belum Dibayar';
                                                            else echo 'Sudah Dibayar';
                                                        ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                            
                                            <?php if ($orderDetails['status'] == 'selesai'): ?>
                                                <div class="order-status">
                                                    <p>Pesanan Anda telah selesai dan dapat diambil di Kios Zeea Laundry.</p>
                                                    <p>Atau, Anda dapat menggunakan layanan antar cucian kami.</p>
                                                    <button type="button" class="btn btn-primary w-100 mb-3" onclick="document.getElementById('delivery-tab').click()">
                                                        <i class="fas fa-truck-loading me-2"></i> Antar Cucian
                                                    </button>
                                                    
                                                    <?php if ($orderDetails['status_pembayaran'] == 'belum_dibayar'): ?>
                                                        <div class="alert alert-warning">
                                                            <i class="fas fa-exclamation-triangle me-2"></i> Pesanan Anda belum dibayar. Silakan lakukan pembayaran.
                                                        </div>
                                                        
                                                        <div class="payment-section">
                                                            <div class="payment-title">Pembayaran via QRIS</div>
                                                            <div class="qris-container">
                                                                <img src="../assets/images/qriszeealaundry.jpg" alt="QRIS Zeea Laundry">
                                                            </div>
                                                            <div class="payment-options">
                                                                <div class="payment-options-title">Pilihan Pembayaran:</div>
                                                                <div class="payment-option">
                                                                    <i class="fas fa-qrcode"></i>
                                                                    <div class="payment-option-text">Scan kode QR di atas untuk melakukan pembayaran online</div>
                                                                </div>
                                                                <div class="payment-option">
                                                                    <i class="fas fa-store"></i>
                                                                    <div class="payment-option-text">Bayar langsung di toko Zeea Laundry</div>
                                                                </div>
                                                            </div>
                                                            <div class="payment-instructions">
                                                                <p>Setelah melakukan pembayaran, silakan konfirmasi melalui WhatsApp</p>
                                                            </div>
                                                            <a href="https://wa.me/6285955196688?text=Halo%20Admin%20Zeea%20Laundry,%20saya%20ingin%20konfirmasi%20pembayaran%20untuk%20pesanan%20%23<?php echo $orderDetails['id']; ?>" target="_blank" class="btn btn-success w-100 confirm-payment-btn">
                                                                <i class="fab fa-whatsapp"></i> KONFIRMASI PEMBAYARAN
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="order-status">
                                                    <?php if ($orderDetails['status'] == 'diproses'): ?>
                                                        <p>Cucian Anda sedang dalam proses. Mohon tunggu hingga selesai.</p>
                                                    <?php elseif ($orderDetails['status'] == 'selesai'): ?>
                                                        <p>Cucian Anda sudah selesai.</p>
                                                    <?php else: ?>
                                                        <p>Pesanan ini telah dibatalkan.</p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- History Tab -->
                        <div class="tab-pane fade <?= isset($_POST['view_history']) ? 'show active' : '' ?>" id="history" role="tabpanel" aria-labelledby="history-tab">
                            <div class="customer-card">
                                <div class="card-header">
                                    <i class="fas fa-history"></i> Riwayat Cucian
                                </div>
                                <div class="card-body">
                                    <form method="post" action="#history">
                                        <div class="mb-4">
                                            <label for="history_tracking_code" class="form-label">Kode Tracking</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                                <input type="text" class="form-control" id="history_tracking_code" name="history_tracking_code" placeholder="Masukkan kode tracking Anda" required>
                                            </div>
                                            <div class="form-text">Masukkan kode tracking yang tertera pada nota laundry Anda</div>
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" name="view_history" class="btn btn-primary w-100">
                                                <i class="fas fa-search"></i> Lihat Riwayat
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <?php if (!empty($orderHistory)): ?>
                                        <div class="mt-4">
                                            <h4 class="mb-3"><i class="fas fa-list-alt me-2"></i> Riwayat Pesanan</h4>
                                            
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
                                                                <td>
                                                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                                                        <?php 
                                                                            if ($order['status'] == 'diproses') echo 'Diproses';
                                                                            elseif ($order['status'] == 'selesai') echo 'Selesai';
                                                                            else echo 'Dibatalkan';
                                                                        ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <span class="status-badge status-<?php echo $order['status_pembayaran']; ?>">
                                                                        <?php 
                                                                            if ($order['status_pembayaran'] == 'belum_dibayar') echo 'Belum Dibayar';
                                                                            else echo 'Sudah Dibayar';
                                                                        ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <form method="post" action="#track">
                                                                        <input type="hidden" name="tracking_code" value="<?php echo $order['tracking_code']; ?>">
                                                                        <button type="submit" name="track" class="btn btn-sm btn-primary">
                                                                            <i class="fas fa-search"></i> Detail
                                                                        </button>
                                                                    </form>
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
                                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                                <?php 
                                                                    if ($order['status'] == 'diproses') echo 'Diproses';
                                                                    elseif ($order['status'] == 'selesai') echo 'Selesai';
                                                                    else echo 'Dibatalkan';
                                                                ?>
                                                            </span>
                                                            <span class="status-badge status-<?php echo $order['status_pembayaran']; ?>">
                                                                <?php 
                                                                    if ($order['status_pembayaran'] == 'belum_dibayar') echo 'Belum Dibayar';
                                                                    else echo 'Sudah Dibayar';
                                                                ?>
                                                            </span>
                                                        </div>
                                                        <div class="text-center mt-2">
                                                            <form method="post" action="#track">
                                                                <input type="hidden" name="tracking_code" value="<?php echo $order['tracking_code']; ?>">
                                                                <button type="submit" name="track" class="btn btn-sm btn-primary w-100">
                                                                    <i class="fas fa-search"></i> Lihat Detail
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($message)): ?>
                                        <div class="alert alert-warning mt-4">
                                            <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $message; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Delivery Tab -->
                        <div class="tab-pane fade <?= (!$redirectToTrack && !isset($_POST['track']) && !isset($_POST['view_history']) && !isset($_POST['request_pickup'])) ? 'show active' : '' ?>" id="delivery" role="tabpanel" aria-labelledby="delivery-tab">
                            <div class="customer-card">
                                <div class="card-header">
                                    <i class="fas fa-truck-loading"></i> Antar Cucian
                                </div>
                                <div class="card-body">
                                    <!-- Time Limit Notice -->
                                    <div class="time-limit-notice">
                                        <h5><i class="fas fa-clock me-2"></i> Batas Waktu Pengantaran</h5>
                                        <p>Permintaan pengantaran yang dibuat setelah jam 17:00 akan diproses pada hari kerja berikutnya.</p>
                                    </div>
                                    
                                    <?php if (!empty($deliveryMessage)): ?>
                                        <div class="alert alert-success mb-3">
                                            <i class="fas fa-check-circle me-2"></i> <?php echo $deliveryMessage; ?>
                                        </div>
                                        
                                        <?php if ($showPaymentInfo): ?>
                                        <div class="payment-section">
                                            <div class="payment-title">Pembayaran Layanan Antar</div>
                                            <div class="qris-container">
                                                <img src="../assets/images/qriszeealaundry.jpg" alt="QRIS Zeea Laundry">
                                            </div>
                                            <div class="payment-options">
                                                <div class="payment-options-title">Pilihan Pembayaran:</div>
                                                <div class="payment-option">
                                                    <i class="fas fa-qrcode"></i>
                                                    <div class="payment-option-text">Scan kode QR di atas untuk melakukan pembayaran online</div>
                                                </div>
                                                <div class="payment-option">
                                                    <i class="fas fa-truck"></i>
                                                    <div class="payment-option-text">Bayar langsung ke petugas antar saat cucian diantar</div>
                                                </div>
                                            </div>
                                            <div class="payment-instructions">
                                                <p>Setelah melakukan pembayaran, silakan konfirmasi melalui WhatsApp</p>
                                            </div>
                                            <a href="https://wa.me/6285955196688?text=Halo%20Admin%20Zeea%20Laundry,%20saya%20ingin%20konfirmasi%20pembayaran%20layanan%20antar%20cucian" target="_blank" class="btn btn-success w-100 confirm-payment-btn">
                                                <i class="fab fa-whatsapp"></i> KONFIRMASI PEMBAYARAN
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($storeStatus == 'tutup'): ?>
                                    <div class="alert alert-danger mb-3">
                                        <i class="fas fa-store-slash me-2"></i> <strong>Toko Sedang Tutup:</strong> 
                                        <p class="mt-2 mb-0">Mohon maaf, layanan antar cucian tidak tersedia saat toko sedang tutup. Silakan kembali lagi saat toko sudah buka.</p>
                                    </div>
                                    <?php elseif (!$serviceActive): ?>
                                    <div class="alert alert-danger mb-3">
                                        <i class="fas fa-ban me-2"></i> <strong>Layanan Tidak Tersedia:</strong> 
                                        <p class="mt-2 mb-0">Mohon maaf, layanan antar jemput cucian sedang tidak tersedia saat ini. Silakan coba lagi nanti.</p>
                                    </div>
                                    <?php else: ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle me-2"></i> <strong>Informasi Layanan Antar:</strong> 
                                        <p class="mt-2 mb-0">Layanan antar cucian hanya tersedia untuk pesanan yang telah selesai. Silakan masukkan kode tracking Anda untuk memeriksa pesanan yang tersedia.</p>
                                    </div>
                                    
                                    <form method="post" action="#delivery" id="deliveryForm">
                                        <div class="mb-3">
                                            <label for="delivery_tracking_code" class="form-label">Kode Tracking</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                                <input type="text" class="form-control" id="delivery_tracking_code" name="delivery_tracking_code" placeholder="Masukkan kode tracking Anda" required>
                                            </div>
                                            <div class="form-text">Gunakan kode tracking yang tertera pada nota laundry Anda</div>
                                        </div>
                                        
                                        <div id="checkDeliveryBtn" class="text-center">
                                            <button type="button" class="btn btn-primary w-100" onclick="checkDeliveryEligibility()">
                                                <i class="fas fa-search"></i> Cek Pesanan
                                            </button>
                                        </div>
                                        
                                        <div id="deliveryFormContent" style="display: none;">
                                            <!-- Customer Verification -->
                                            <div id="customerVerification" class="alert alert-info mb-3" style="display: none;">
                                                <i class="fas fa-user-check me-2"></i> <strong>Verifikasi Pelanggan:</strong> 
                                                <p class="mt-2 mb-0">Pesanan ini atas nama <span id="customerName" class="fw-bold"></span>. Jika benar, silakan lanjutkan pengisian form.</p>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="delivery_address" class="form-label">Alamat Pengantaran</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                                    <textarea class="form-control" id="delivery_address" name="delivery_address" rows="3" placeholder="Isi alamat lengkap" required></textarea>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="delivery_time" class="form-label">Waktu Pengantaran</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                                    <input type="datetime-local" class="form-control" id="delivery_time" name="delivery_time" required>
                                                </div>
                                                <div class="form-text">Maksimal jam 17:00. Jika melebihi, akan diproses besok.</div>
                                            </div>
                                            <div class="mb-3">
                                                <label for="delivery_notes" class="form-label">Catatan</label>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-sticky-note"></i></span>
                                                    <textarea class="form-control" id="delivery_notes" name="delivery_notes" rows="3"></textarea>
                                                </div>
                                                <div class="form-text">Tambahkan informasi tambahan jika diperlukan</div>
                                            </div>
                                            <input type="hidden" id="order_id" name="order_id" value="">
                                            
                                            <div class="text-center">
                                                <button type="submit" name="request_delivery" class="btn btn-primary w-100">
                                                    <i class="fas fa-paper-plane"></i> Kirim Permintaan
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Already Requested Alert -->
                                        <div id="alreadyRequestedAlert" class="alert alert-warning mt-3" style="display: none;">
                                            <i class="fas fa-exclamation-triangle me-2"></i> <strong>Permintaan Sudah Ada:</strong>
                                            <p class="mt-2 mb-0">Pesanan dengan kode tracking ini sudah memiliki permintaan pengantaran. Silakan hubungi admin untuk informasi lebih lanjut.</p>
                                            <div class="mt-3">
                                                <button type="button" class="btn btn-outline-primary w-100" onclick="resetDeliveryForm()">
                                                    <i class="fas fa-redo-alt me-2"></i> Coba Kode Lain
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div id="noCompletedOrders" class="alert alert-info mt-3" style="display: none;">
                                            <i class="fas fa-info-circle me-2"></i> Anda tidak memiliki pesanan yang telah selesai. Layanan antar hanya tersedia untuk pesanan yang telah selesai.
                                            <div class="mt-3">
                                                <button type="button" class="btn btn-outline-primary w-100" onclick="resetDeliveryForm()">
                                                    <i class="fas fa-redo-alt me-2"></i> Coba Kode Lain
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Pickup Tab -->
                        <div class="tab-pane fade <?= isset($_POST['request_pickup']) ? 'show active' : '' ?>" id="pickup" role="tabpanel" aria-labelledby="pickup-tab">
                            <div class="customer-card">
                                <div class="card-header">
                                    <i class="fas fa-truck-pickup"></i> Jemput Cucian
                                </div>
                                <div class="card-body">
                                    <!-- Time Limit Notice -->
                                    <div class="time-limit-notice">
                                        <h5><i class="fas fa-clock me-2"></i> Batas Waktu Penjemputan</h5>
                                        <p>Permintaan penjemputan yang dibuat setelah jam 17:00 akan diproses pada hari kerja berikutnya.</p>
                                    </div>
                                    
                                    <?php if (!empty($pickupMessage)): ?>
                                        <div class="alert alert-success mb-3">
                                            <i class="fas fa-check-circle me-2"></i> <?php echo $pickupMessage; ?>
                                        </div>
                                        
                                        <?php if ($showPaymentInfo): ?>
                                        <div class="payment-section">
                                            <div class="payment-title">Pembayaran Layanan Jemput</div>
                                            <div class="qris-container">
                                                <img src="../assets/images/qriszeealaundry.jpg" alt="QRIS Zeea Laundry">
                                            </div>
                                            <div class="payment-options">
                                                <div class="payment-options-title">Pilihan Pembayaran:</div>
                                                <div class="payment-option">
                                                    <i class="fas fa-qrcode"></i>
                                                    <div class="payment-option-text">Scan kode QR di atas untuk melakukan pembayaran online</div>
                                                </div>
                                                <div class="payment-option">
                                                    <i class="fas fa-truck-pickup"></i>
                                                    <div class="payment-option-text">Bayar langsung ke petugas jemput saat cucian dijemput</div>
                                                </div>
                                            </div>
                                            <div class="payment-instructions">
                                                <p>Setelah melakukan pembayaran, silakan konfirmasi melalui WhatsApp</p>
                                            </div>
                                            <a href="https://wa.me/6285955196688?text=Halo%20Admin%20Zeea%20Laundry,%20saya%20ingin%20konfirmasi%20pembayaran%20layanan%20jemput%20cucian" target="_blank" class="btn btn-success w-100 confirm-payment-btn">
                                                <i class="fab fa-whatsapp"></i> KONFIRMASI PEMBAYARAN
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($storeStatus == 'tutup'): ?>
                                    <div class="alert alert-danger mb-3">
                                        <i class="fas fa-store-slash me-2"></i> <strong>Toko Sedang Tutup:</strong> 
                                        <p class="mt-2 mb-0">Mohon maaf, layanan jemput cucian tidak tersedia saat toko sedang tut
                                        <p class="mt-2 mb-0">Mohon maaf, layanan jemput cucian tidak tersedia saat toko sedang tutup. Silakan kembali lagi saat toko sudah buka.</p>
                                    </div>
                                    <?php elseif (!$serviceActive): ?>
                                    <div class="alert alert-danger mb-3">
                                        <i class="fas fa-ban me-2"></i> <strong>Layanan Tidak Tersedia:</strong> 
                                        <p class="mt-2 mb-0">Mohon maaf, layanan antar jemput cucian sedang tidak tersedia saat ini. Silakan coba lagi nanti.</p>
                                    </div>
                                    <?php else: ?>
                                    <form method="post" action="#pickup" id="pickupForm">
                                        <div class="mb-3">
                                            <label for="pickup_name" class="form-label">Nama Lengkap</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                                <input type="text" class="form-control" id="pickup_name" name="pickup_name" placeholder="Masukkan nama lengkap Anda" required>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="pickup_phone" class="form-label">Nomor Telepon</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                                <input type="text" class="form-control" id="pickup_phone" name="pickup_phone" placeholder="Masukkan nomor telepon Anda" required>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="pickup_address" class="form-label">Alamat Penjemputan</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                                <textarea class="form-control" id="pickup_address" name="pickup_address" rows="3" placeholder="Isi alamat lengkap" required></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="pickup_time" class="form-label">Waktu Penjemputan</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                                <input type="datetime-local" class="form-control" id="pickup_time" name="pickup_time" required>
                                            </div>
                                            <div class="form-text">Maksimal jam 17:00. Jika melebihi, akan diproses besok.</div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="pickup_notes" class="form-label">Catatan</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="fas fa-sticky-note"></i></span>
                                                <textarea class="form-control" id="pickup_notes" name="pickup_notes" rows="3"></textarea>
                                            </div>
                                            <div class="form-text">Tambahkan informasi tambahan jika diperlukan</div>
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" name="request_pickup" class="btn btn-primary w-100" id="submitPickupBtn">
                                                <i class="fas fa-paper-plane"></i> Kirim Permintaan
                                            </button>
                                        </div>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
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

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p class="footer-text">&copy; <?php echo date('Y'); ?> Zeea Laundry. Semua hak dilindungi.</p>
        </div>
    </footer>

    <!-- QRIS Modal -->
    <div id="qrisModal" class="qris-modal">
        <div class="qris-modal-content">
            <span class="qris-modal-close">&times;</span>
            <img id="qrisModalImage" class="qris-modal-image" src="../assets/images/qriszeealaundry.jpg" alt="QRIS Zeea Laundry">
            <div class="qris-actions">
                <a href="../assets/images/qriszeealaundry.jpg" download="QRIS_Zeea_Laundry.jpg" class="download-btn">
                    <i class="fas fa-download"></i> Download QRIS
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle form submissions to navigate to the correct tab
        document.addEventListener('DOMContentLoaded', function() {
            // Track form
            const trackForm = document.querySelector('#track form');
            if (trackForm) {
                trackForm.addEventListener('submit', function() {
                    this.action = '#track';
                    // Store the current tab in session storage
                    sessionStorage.setItem('activeTab', 'track');
                });
            }
            
            // History form
            const historyForm = document.querySelector('#history form');
            if (historyForm) {
                historyForm.addEventListener('submit', function() {
                    this.action = '#history';
                    // Store the current tab in session storage
                    sessionStorage.setItem('activeTab', 'history');
                });
            }
            
            // Delivery form
            const deliveryForm = document.querySelector('#delivery form');
            if (deliveryForm) {
                deliveryForm.addEventListener('submit', function() {
                    this.action = '#delivery';
                    // Store the current tab in session storage
                    sessionStorage.setItem('activeTab', 'delivery');
                });
            }
            
            // Pickup form
            const pickupForm = document.querySelector('#pickup form');
            if (pickupForm) {
                pickupForm.addEventListener('submit', function() {
                    this.action = '#pickup';
                    // Store the current tab in session storage
                    sessionStorage.setItem('activeTab', 'pickup');
                });
            }
            
            // Check if we have a stored active tab and navigate to it
            const storedTab = sessionStorage.getItem('activeTab');
            if (storedTab) {
                const tabElement = document.getElementById(storedTab + '-tab');
                if (tabElement) {
                    tabElement.click();
                }
            }
            
            // Update stored tab when clicking on tabs
            const tabLinks = document.querySelectorAll('.nav-link');
            tabLinks.forEach(function(tabLink) {
                tabLink.addEventListener('click', function() {
                    const tabId = this.getAttribute('id').replace('-tab', '');
                    sessionStorage.setItem('activeTab', tabId);
                });
            });
            
            // Set time limits for pickup and delivery
            const pickupTimeInput = document.getElementById('pickup_time');
            const deliveryTimeInput = document.getElementById('delivery_time');
            
            if (pickupTimeInput) {
                pickupTimeInput.addEventListener('change', function() {
                    validateTimeLimit(this);
                });
            }
            
            if (deliveryTimeInput) {
                deliveryTimeInput.addEventListener('change', function() {
                    validateTimeLimit(this);
                });
            }
            
            function validateTimeLimit(input) {
                const selectedTime = new Date(input.value);
                const hours = selectedTime.getHours();
                
                if (hours >= 17) {
                    alert('Waktu yang Anda pilih melebihi batas waktu (17:00). Pesanan akan diproses besok.');
                }
            }

            // Check if we need to activate the track tab due to URL parameters
            if (window.location.href.includes('redirect_to_track=1')) {
                const trackTab = document.getElementById('track-tab');
                if (trackTab) {
                    trackTab.click();
                }
            }
        });
        
        // Function to reset delivery form
        function resetDeliveryForm() {
            document.getElementById('noCompletedOrders').style.display = 'none';
            document.getElementById('alreadyRequestedAlert').style.display = 'none';
            document.getElementById('customerVerification').style.display = 'none';
            document.getElementById('checkDeliveryBtn').style.display = 'block';
            document.getElementById('deliveryFormContent').style.display = 'none';
            document.getElementById('delivery_tracking_code').value = '';
            document.getElementById('delivery_tracking_code').focus();
        }
        
        // Check delivery eligibility
        function checkDeliveryEligibility() {
            const trackingCode = document.getElementById('delivery_tracking_code').value;
            
            if (!trackingCode) {
                alert('Silakan masukkan kode tracking Anda');
                return;
            }
            
            // Send AJAX request to check if customer has completed orders
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (this.status === 200) {
                    try {
                        const response = JSON.parse(this.responseText);
                        
                        if (response.success) {
                            // Show customer verification
                            document.getElementById('customerVerification').style.display = 'block';
                            document.getElementById('customerName').textContent = response.customer_name;
                            document.getElementById('deliveryFormContent').style.display = 'block';
                            document.getElementById('checkDeliveryBtn').style.display = 'none';
                            document.getElementById('order_id').value = response.order_id;
                        } else if (response.processing) {
                            // Redirect to track tab with processing order
                            window.location.href = 'index.php?redirect_to_track=1&order_id=' + response.order_id;
                            
                            // Also trigger the track tab to be active
                            const trackTab = document.getElementById('track-tab');
                            if (trackTab) {
                                setTimeout(function() {
                                    trackTab.click();
                                }, 100);
                            }
                        } else if (response.already_requested) {
                            // Show already requested message
                            document.getElementById('alreadyRequestedAlert').style.display = 'block';
                            document.getElementById('checkDeliveryBtn').style.display = 'none';
                        } else {
                            document.getElementById('noCompletedOrders').style.display = 'block';
                            document.getElementById('checkDeliveryBtn').style.display = 'none';
                        }
                    } catch (e) {
                        console.error('Error parsing JSON response:', e);
                        alert('Terjadi kesalahan. Silakan coba lagi.');
                    }
                }
            };
            xhr.send('check_delivery_ajax=1&tracking_code=' + encodeURIComponent(trackingCode));
        }

        // Function to close the status banner
        function closeStatusBanner() {
            const banner = document.getElementById('storeStatusBanner');
            banner.style.animation = 'slideUp 0.5s ease forwards';
            setTimeout(() => {
                banner.style.display = 'none';
            }, 500);
        }
        
        // Auto-hide the "open" status banner after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const banner = document.getElementById('storeStatusBanner');
            if (banner && banner.classList.contains('open')) {
                setTimeout(() => {
                    banner.style.animation = 'slideUp 0.5s ease forwards';
                    setTimeout(() => {
                        banner.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        });

        // QRIS Modal functionality
        const qrisModal = document.getElementById('qrisModal');
        const qrisModalImage = document.getElementById('qrisModalImage');
        const qrisModalClose = document.querySelector('.qris-modal-close');
        const qrisContainers = document.querySelectorAll('.qris-container');

        // Open modal when clicking on QRIS image
        qrisContainers.forEach(container => {
            container.addEventListener('click', function() {
                const imgSrc = this.querySelector('img').src;
                qrisModalImage.src = imgSrc;
                qrisModal.style.display = 'block';
                document.body.style.overflow = 'hidden'; // Prevent scrolling when modal is open
            });
        });

        // Close modal when clicking on close button
        qrisModalClose.addEventListener('click', function() {
            qrisModal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Re-enable scrolling
        });

        // Close modal when clicking outside the image
        qrisModal.addEventListener('click', function(event) {
            if (event.target === qrisModal) {
                qrisModal.style.display = 'none';
                document.body.style.overflow = 'auto'; // Re-enable scrolling
            }
        });
    </script>
</body>
</html>

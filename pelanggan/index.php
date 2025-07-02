<?php
// Include database connection
include '../includes/db.php';

// Initialize variables
$trackingCode = "";
$phoneNumber = "";
$orderDetails = null;
$orderItems = [];
$deliveryDetails = null;
$message = "";
$totalHarga = 0;
$totalHargaWithDelivery = 0;
$hasCompletedOrders = false;
$completedOrderId = 0;
$showPaymentInfo = false;
$trackingType = "code"; // "code" or "phone"

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

// Process tracking form
if (isset($_POST['track'])) {
    $trackingCode = $_POST['tracking_code'];
    
    // First, check if it's a regular laundry order
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

        // Check for delivery service with the same tracking code
        $deliveryQuery = "SELECT aj.*, pl.nama as nama_pelanggan, pl.no_hp as telepon 
                         FROM antar_jemput aj 
                         LEFT JOIN pelanggan pl ON aj.id_pelanggan = pl.id 
                         WHERE aj.tracking_code = ? AND aj.deleted_at IS NULL";
        $deliveryStmt = $conn->prepare($deliveryQuery);
        $deliveryStmt->bind_param("s", $trackingCode);
        $deliveryStmt->execute();
        $deliveryResult = $deliveryStmt->get_result();
        
        if ($deliveryResult->num_rows > 0) {
            $deliveryDetails = $deliveryResult->fetch_assoc();
            // Add delivery price to total
            $totalHargaWithDelivery = $totalHarga + $deliveryDetails['harga'];
        } else {
            $totalHargaWithDelivery = $totalHarga;
        }

        // Check if the order is older than 7 days
        $orderDate = new DateTime($orderDetails['waktu']);
        $currentDate = new DateTime();
        $interval = $orderDate->diff($currentDate);
        
        if ($interval->days > 7) {
            $message = "Pesanan ini sudah lebih dari 7 hari. Silahkan buat pesanan baru ke Zeea Laundry.";
        } else {
            // Check if customer has completed orders for delivery
            if ($orderDetails['status'] == 'selesai') {
                $hasCompletedOrders = true;
                $completedOrderId = $orderDetails['id'];
            }
        }
    } else {
        // Check if it's a delivery tracking code (AJ-*) - for jemput service
        if (strpos($trackingCode, 'AJ-') === 0) {
            $query = "SELECT aj.*, pl.nama as nama_pelanggan, pl.no_hp as telepon 
                     FROM antar_jemput aj 
                     LEFT JOIN pelanggan pl ON aj.id_pelanggan = pl.id 
                     WHERE aj.tracking_code = ? AND aj.deleted_at IS NULL";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $trackingCode);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $deliveryDetails = $result->fetch_assoc();
                
                // Check if delivery is older than 7 days
                $deliveryDate = new DateTime($deliveryDetails['waktu_antar'] ?? $deliveryDetails['waktu_jemput']);
                $currentDate = new DateTime();
                $interval = $deliveryDate->diff($currentDate);
                
                if ($interval->days > 7) {
                    $message = "Layanan antar jemput ini sudah lebih dari 7 hari dan tidak berlaku lagi.";
                }
            } else {
                $message = "Kode tracking antar jemput tidak ditemukan. Silakan periksa kembali kode tracking Anda.";
            }
        } else {
            $message = "Kode tracking tidak ditemukan. Silakan periksa kembali kode tracking Anda.";
        }
    }
}

// Check if tracking code is provided in URL
if (isset($_GET['tracking_code']) && empty($trackingCode)) {
    $trackingCode = $_GET['tracking_code'];
    $trackingType = "code";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Zeea Laundry - Lacak Pesanan</title>
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
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.34);
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
        
        /* Clear button for input */
        .input-group {
            position: relative;
        }
        
        .clear-input {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            background: none;
            border: none;
            color: #aaa;
            cursor: pointer;
            font-size: 16px;
            display: none;
            padding: 0;
            width: 20px;
            height: 20px;
            line-height: 1;
            text-align: center;
        }
        
        .clear-input:hover {
            color: var(--danger-red);
        }
        
        .clear-input.visible {
            display: block;
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
            background-color: rgba(220, 53, 69, 0.2);
            color: #c82333;
            border: 1px solid rgba(220, 53, 69, 0.5);
        }

        .status-dalam_perjalanan {
            background-color: rgba(255, 193, 7, 0.2);
            color: #e0a800;
            border: 1px solid rgba(255, 193, 7, 0.5);
        }

        /* Status selesai untuk layanan antar jemput */
        .status-selesai-antar {
            background-color: rgba(40, 167, 69, 0.2);
            color: #218838;
            border: 1px solid rgba(40, 167, 69, 0.5);
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

        .delivery-service-item {
            background-color: rgba(255, 193, 7, 0.05);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--secondary);
        }

        .delivery-service-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed rgba(0, 0, 0, 0.1);
        }

        .delivery-service-title {
            font-weight: bold;
            font-size: 16px;
            color: #333;
        }

        .delivery-service-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
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
                                <a class="nav-link active" href="index.php">
                                    <i class="fas fa-search me-2"></i> Cek Status
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a class="nav-link" href="riwayat.php">
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
                            <i class="fas fa-search"></i> Cek Status Cucian
                        </div>
                        <div class="card-body">
                            <form method="post" action="" id="trackForm">
                                <div class="mb-4">
                                    <label for="tracking_code" class="form-label">Kode Tracking</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                                        <input type="text" class="form-control" id="tracking_code" name="tracking_code" value="<?php echo $trackingCode; ?>" placeholder="Kode tracking pesanan" required>
                                        <button type="button" class="clear-input" id="clearInput" title="Hapus">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Masukkan kode tracking pesanan laundry (ZL...) atau layanan jemput cucian (AJ...)</div>
                                </div>
                                <div class="text-center">
                                    <button type="submit" name="track" class="btn btn-primary w-100">
                                        <i class="fas fa-search"></i> Cek Status
                                    </button>
                                </div>
                            </form>
                            
                            <?php if (!empty($message)): ?>
                                <div class="alert alert-info mt-4">
                                    <i class="fas fa-info-circle me-2"></i> <?php echo $message; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Display Delivery Details First -->
                            <?php if ($deliveryDetails && empty($message)): ?>
                            <div class="order-card">
                                <div class="order-header">
                                    <div class="order-id">Kode Tracking : <?php echo $deliveryDetails['tracking_code']; ?></div>
                                    <div class="order-date"><?php echo date('d/m/Y H:i', strtotime($deliveryDetails['waktu_antar'] ?? $deliveryDetails['waktu_jemput'])); ?></div>
                                </div>
                                <div class="order-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Nama Pelanggan</div>
                                        <div class="detail-value"><?php echo $deliveryDetails['nama_pelanggan']; ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Jenis Layanan</div>
                                        <div class="detail-value">
                                            <?php 
                                                if ($deliveryDetails['layanan'] == 'antar') echo 'Antar Cucian';
                                                elseif ($deliveryDetails['layanan'] == 'jemput') echo 'Jemput Cucian';
                                                else echo 'Antar & Jemput';
                                            ?>
                                        </div>
                                    </div>
                                    <?php if ($deliveryDetails['layanan'] == 'antar' && !empty($deliveryDetails['alamat_antar'])): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Alamat Pengantaran</div>
                                        <div class="detail-value"><?php echo $deliveryDetails['alamat_antar']; ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($deliveryDetails['layanan'] == 'jemput' && !empty($deliveryDetails['alamat_jemput'])): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Alamat Penjemputan</div>
                                        <div class="detail-value"><?php echo $deliveryDetails['alamat_jemput']; ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($deliveryDetails['waktu_antar'])): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Waktu Pengantaran</div>
                                        <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($deliveryDetails['waktu_antar'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($deliveryDetails['waktu_jemput'])): ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Waktu Penjemputan</div>
                                        <div class="detail-value"><?php echo date('d/m/Y H:i', strtotime($deliveryDetails['waktu_jemput'])); ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="detail-item">
                                        <div class="detail-label">Status Layanan</div>
                                        <div class="detail-value">
                                            <?php 
                                                $statusClass = '';
                                                if ($deliveryDetails['status'] == 'menunggu') {
                                                    $statusClass = 'status-menunggu';
                                                    $statusText = 'Menunggu';
                                                } elseif ($deliveryDetails['status'] == 'dalam perjalanan') {
                                                    $statusClass = 'status-dalam_perjalanan';
                                                    $statusText = 'Dalam Perjalanan';
                                                } else {
                                                    $statusClass = 'status-selesai-antar';
                                                    $statusText = 'Selesai';
                                                }
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Biaya Layanan</div>
                                        <div class="detail-value total-price-value">Rp <?php echo number_format($deliveryDetails['harga'], 0, ',', '.'); ?></div></div>
                                    </div>
                                </div>
                                
                                <div class="order-status">
                                    <?php if ($deliveryDetails['status'] == 'menunggu'): ?>
                                        <p>Layanan Anda sedang menunggu untuk diproses. Tim kami akan segera menghubungi Anda.</p>
                                    <?php elseif ($deliveryDetails['status'] == 'dalam perjalanan'): ?>
                                        <p>Tim kami sedang dalam perjalanan menuju lokasi Anda.</p>
                                    <?php else: ?>
                                        <p>Layanan Anda telah selesai.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Display Order Details -->
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
                                    
                                    <!-- Display delivery service if exists -->
                                    <?php if ($deliveryDetails): ?>
                                    <div class="delivery-service-item">
                                        <div class="delivery-service-header">
                                            <div class="delivery-service-title">Layanan Antar Jemput</div>
                                        </div>
                                        <div class="delivery-service-details">
                                            <span class="paket-item-label">Jenis Layanan:</span>
                                            <span class="paket-item-value">
                                                <?php 
                                                    if ($deliveryDetails['layanan'] == 'antar') echo 'Antar Cucian';
                                                    elseif ($deliveryDetails['layanan'] == 'jemput') echo 'Jemput Cucian';
                                                    else echo 'Antar & Jemput';
                                                ?>
                                            </span>
                                        </div>
                                        <div class="delivery-service-details">
                                            <span class="paket-item-label">Biaya Layanan:</span>
                                            <span class="paket-item-value">Rp <?php echo number_format($deliveryDetails['harga'], 0, ',', '.'); ?></span>
                                        </div>
                                        <div class="delivery-service-details">
                                            <span class="paket-item-label">Status:</span>
                                            <span class="paket-item-value">
                                                <?php 
                                                    if ($deliveryDetails['status'] == 'menunggu') echo 'Menunggu';
                                                    elseif ($deliveryDetails['status'] == 'dalam perjalanan') echo 'Dalam Perjalanan';
                                                    else echo 'Selesai';
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="detail-item total-price-item">
                                        <div class="detail-label">Total Harga Keseluruhan</div>
                                        <div class="detail-value total-price-value">Rp <?php echo number_format($totalHargaWithDelivery, 0, ',', '.'); ?></div>
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
                                        <?php if ($deliveryDetails): ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-truck-loading me-2"></i> <strong>Layanan Antar Aktif:</strong> Pesanan Anda sedang dalam proses pengantaran.
                                            </div>
                                        <?php else: ?>
                                            <p>Pesanan Anda telah selesai dan dapat diambil di Kios Zeea Laundry.</p>
                                            <p>Atau, Anda dapat menggunakan layanan antar cucian kami.</p>
                                            <a href="antar.php?tracking_code=<?php echo $orderDetails['tracking_code']; ?>" class="btn btn-primary w-100 mb-3">
                                                <i class="fas fa-truck-loading me-2"></i> Antar Cucian
                                            </a>
                                        <?php endif; ?>
                                        
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
                                                    <div class="payment-option">
                                                        <i class="fa-solid fa-motorcycle"></i>
                                                        <div class="payment-option-text">Bayar langsung saat pesanan diantar</div>
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

        // Clear input button functionality
        document.addEventListener('DOMContentLoaded', function() {
            const inputField = document.getElementById('tracking_code');
            const clearButton = document.getElementById('clearInput');
            
            // Show/hide clear button based on input content
            function toggleClearButton(input, button) {
                if (input.value.length > 0) {
                    button.classList.add('visible');
                } else {
                    button.classList.remove('visible');
                }
            }
            
            // Initial state
            toggleClearButton(inputField, clearButton);
            
            // Add event listeners
            inputField.addEventListener('input', () => toggleClearButton(inputField, clearButton));
            
            clearButton.addEventListener('click', function() {
                inputField.value = '';
                toggleClearButton(inputField, clearButton);
                inputField.focus();
            });
        });

        // Auto-fill and auto-submit if 'code' param exists in URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const code = urlParams.get('code');
            if (code) {
                const inputField = document.getElementById('tracking_code');
                if (inputField) {
                    inputField.value = code;
                    // Tambahkan hidden input 'track' jika belum ada
                    let trackInput = document.querySelector('input[name="track"]');
                    if (!trackInput) {
                        trackInput = document.createElement('input');
                        trackInput.type = 'hidden';
                        trackInput.name = 'track';
                        trackInput.value = '1';
                        document.getElementById('trackForm').appendChild(trackInput);
                    }
                    // Hapus param 'code' dari URL agar tidak looping
                    urlParams.delete('code');
                    const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
                    window.history.replaceState({}, '', newUrl);
                    // Submit form
                    document.getElementById('trackForm').submit();
                }
            }
        });
    </script>
</body>
</html>

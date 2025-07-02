<?php
// Include database connection
include '../includes/db.php';

// Initialize variables
$pickupMessage = "";
$showPaymentInfo = false;
$trackingCode = "";

// Check if antar-jemput service is active
$serviceStatusQuery = $conn->query("SELECT status FROM antarjemput_status WHERE id = 1");
$serviceActive = true; // Default to active if setting doesn't exist
if ($serviceStatusQuery && $serviceStatusQuery->num_rows > 0) {
    $serviceActive = ($serviceStatusQuery->fetch_assoc()['status'] === 'active');
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

// Process pickup request
if (isset($_POST['request_pickup'])) {
    $name = trim($_POST['pickup_name']);
    $phone = trim($_POST['pickup_phone']);
    // Format phone number to ensure it starts with +62
    if (substr($phone, 0, 1) === '0') {
        $phone = '+62' . substr($phone, 1);
    }

    $address = trim($_POST['pickup_address']);
    $pickup_time = $_POST['pickup_time'];
    $notes = trim($_POST['pickup_notes']);

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
        
        // Update customer name if it's different
        $stmt = $conn->prepare("UPDATE pelanggan SET nama = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $customerId);
        $stmt->execute();
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
        // Insert pickup request with customer ID, name, and default price 5000
        $stmt = $conn->prepare("INSERT INTO antar_jemput (id_pesanan, id_pelanggan, nama_pelanggan, layanan, alamat_jemput, status, waktu_jemput, harga) VALUES (NULL, ?, ?, 'jemput', ?, 'menunggu', ?, 5000.00)");
        $stmt->bind_param("isss", $customerId, $name, $address, $pickup_time);
        
        if ($stmt->execute()) {
            $antarJemputId = $conn->insert_id;
            // Generate tracking code for antar-jemput service
            $trackingCode = 'AJ-' . str_pad($antarJemputId, 6, '0', STR_PAD_LEFT);
            
            // Update the tracking code in the database
            $updateStmt = $conn->prepare("UPDATE antar_jemput SET tracking_code = ? WHERE id = ?");
            $updateStmt->bind_param("si", $trackingCode, $antarJemputId);
            $updateStmt->execute();
            
            if (empty($pickupMessage)) {
                $pickupMessage = "Permintaan penjemputan berhasil dikirim dengan kode tracking: <strong>$trackingCode</strong>. Tim kami akan menjemput cucian Anda sesuai jadwal.";
            } else {
                $pickupMessage .= " Permintaan penjemputan berhasil dikirim dengan kode tracking: <strong>$trackingCode</strong>.";
            }
            $showPaymentInfo = true;
        } else {
            $pickupMessage = "Gagal mengirim permintaan. Silakan coba lagi. Error: " . $stmt->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Zeea Laundry - Jemput Cucian</title>
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
        
        /* Tracking Code Display */
        .tracking-code-display {
            background: linear-gradient(135deg, var(--primary) 0%, #4dd0e1 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(66, 195, 207, 0.3);
        }
        
        .tracking-code-display h5 {
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .tracking-code {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 2px;
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 20px;
            border-radius: 10px;
            display: inline-block;
            margin: 10px 0;
        }
        
        .tracking-instructions {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 10px;
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
                                <a class="nav-link active" href="jemput.php">
                                    <i class="fas fa-truck-pickup me-2"></i> Jemput Cucian
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="customer-card">
                        <div class="card-header">
                            <i class="fas fa-truck-pickup"></i> Jemput Cucian
                        </div>
                        <div class="card-body">
                            <?php if ($storeStatus == 'tutup'): ?>
                            <div class="alert alert-danger mb-3">
                                <i class="fas fa-store-slash me-2"></i> <strong>Toko Sedang Tutup:</strong> 
                                <p class="mt-2 mb-0">Mohon maaf, layanan jemput cucian tidak tersedia saat toko sedang tutup. Silakan kembali lagi saat toko sudah buka.</p>
                            </div>
                            <?php elseif (!$serviceActive): ?>
                            <div class="alert alert-danger mb-3">
                                <i class="fas fa-ban me-2"></i> <strong>Layanan Tidak Tersedia:</strong> 
                                <p class="mt-2 mb-0">Mohon maaf, layanan antar jemput cucian sedang tidak tersedia saat ini. Silakan coba lagi nanti.</p>
                            </div>
                            <?php else: ?>
                            <!-- Time Limit Notice - hanya tampil jika layanan tersedia -->
                            <div class="time-limit-notice">
                                <h5><i class="fas fa-clock me-2"></i> Batas Waktu Penjemputan</h5>
                                <p>Permintaan penjemputan yang dibuat setelah jam 17:00 akan diproses pada hari kerja berikutnya.</p>
                            </div>
                            
                            <?php if (!empty($pickupMessage)): ?>
                                <div class="alert alert-success mb-3">
                                    <i class="fas fa-check-circle me-2"></i> <?php echo $pickupMessage; ?>
                                </div>
                                
                                <?php if (!empty($trackingCode)): ?>
                                <div class="tracking-code-display">
                                    <h5><i class="fas fa-barcode me-2"></i>Kode Tracking Anda</h5>
                                    <div class="tracking-code"><?php echo $trackingCode; ?></div>
                                    <div class="tracking-instructions">
                                        Simpan kode ini untuk melacak status penjemputan Anda di halaman "Cek Status"
                                    </div>
                                </div>
                                <?php endif; ?>
                                
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
                                        <p>Biaya layanan jemput: <strong>Rp 5.000</strong></p>
                                        <p>Setelah melakukan pembayaran, silakan konfirmasi melalui WhatsApp</p>
                                    </div>
                                    <a href="https://wa.me/6285955196688?text=Halo%20Admin%20Zeea%20Laundry,%20saya%20ingin%20konfirmasi%20pembayaran%20layanan%20jemput%20cucian%20dengan%20kode%20tracking%20<?php echo urlencode($trackingCode); ?>" target="_blank" class="btn btn-success w-100 confirm-payment-btn">
                                        <i class="fab fa-whatsapp"></i> KONFIRMASI PEMBAYARAN
                                    </a>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <form method="post" action="" id="pickupForm">
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

        // Set minimum date and time for pickup
        document.addEventListener('DOMContentLoaded', function() {
            const pickupTimeInput = document.getElementById('pickup_time');
            if (pickupTimeInput) {
                const now = new Date();
                now.setHours(now.getHours() + 1); // Add 1 hour to current time
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                
                const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
                pickupTimeInput.min = minDateTime;
                pickupTimeInput.value = minDateTime;
            }
        });
    </script>
</body>
</html>

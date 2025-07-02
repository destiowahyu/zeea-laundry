<?php
// Get store status for the sidebar
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
                                <a href="index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                                    <i class="fas fa-search me-2"></i> Cek Status Cucian
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="riwayat.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'riwayat.php' ? 'active' : '' ?>">
                                    <i class="fas fa-history me-2"></i> Riwayat Cucian
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="antar.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'antar.php' ? 'active' : '' ?>">
                                    <i class="fas fa-truck-loading me-2"></i> Antar Cucian
                                </a>
                            </li>
                            <li class="nav-item" role="presentation">
                                <a href="jemput.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'jemput.php' ? 'active' : '' ?>">
                                    <i class="fas fa-truck-pickup me-2"></i> Jemput Cucian
                                </a>
                            </li>
                        </ul>
                    </div>

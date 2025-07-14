<?php
// Include database connection
include 'includes/db.php';

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

// Rest of your existing index.php code...
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zeea Laundry - Rapi, Bersih, Wangi</title>
    <link rel="icon" type="image/png" href="assets/images/zeea_laundry.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="preload" as="image" href="assets/images/zeea_laundry.png" type="image/png" fetchpriority="high">
    <link rel="preload" as="image" href="assets/images/favicon.png" type="image/png">
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <style>
        /* Initial loading state - hide everything until splash is ready */
        body { 
            visibility: hidden; 
            opacity: 0;
        }
        
        body.splash-ready {
            visibility: visible;
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            scroll-behavior: smooth;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            overflow-x: hidden;
        }
        
        /* Splash Screen */
        .splash-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
            will-change: opacity, visibility;
        }
        
        .splash-content {
            text-align: center;
            animation: fadeInUp 0.8s ease forwards;
        }
        
        .splash-loading {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .splash-logo {
            width: 180px;
            height: 180px;
            animation: pulse 2s infinite;
            object-fit: contain;
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
            will-change: transform;
            backface-visibility: hidden;
            transform: translateZ(0);
        }
        
        .splash-logo-container {
            width: 180px;
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            position: relative;
        }
        
        .splash-text {
            color: white;
            font-size: 1.8rem;
            font-weight: 600;
            margin-top: 20px;
            opacity: 0;
            animation: fadeIn 1s ease forwards 0.5s;
        }
        
        .splash-ready .splash-text {
            opacity: 1;
            animation: none;
        }
        
        .hidden { display: none !important; }
        
        /* Navbar */
        .navbar {
            padding: 15px 0;
            background-color: var(--light);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .navbar.scrolled {
            padding: 10px 0;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand img {
            height: 40px;
            transition: var(--transition);
        }
        
        .navbar-brand span {
            font-weight: 700;
            font-size: 20px;
            color: var(--primary);
            margin-left: 10px;
            transition: var(--transition);
        }
        
        .nav-link {
            font-weight: 500;
            color: var(--dark);
            margin: 0 10px;
            position: relative;
            transition: var(--transition);
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            background-color: var(--primary);
            bottom: -3px;
            left: 0;
            transition: var(--transition);
        }
        
        .nav-link:hover::after,
        .nav-link.active::after {
            width: 100%;
        }
        
        .nav-link:hover,
        .nav-link.active {
            color: var(--primary);
        }
        
        /* Hover Dropdown Styles */
        .hover-dropdown {
            position: relative;
        }

        .hover-dropdown-content {
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 250px;
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 10px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .hover-dropdown:hover .hover-dropdown-content {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .hover-dropdown-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-bottom: 5px;
        }

        .hover-dropdown-item:last-child {
            margin-bottom: 0;
        }

        .hover-dropdown-item:hover {
            background-color: var(--gray);
            transform: translateY(-2px);
        }

        .customer-item {
            color: var(--dark);
        }

        .customer-item:hover {
            color: var(--primary);
        }

        .admin-item {
            color: var(--dark);
        }

        .admin-item:hover {
            color: var(--secondary-dark);
        }

        .access-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
            font-size: 16px;
            transition: var(--transition);
        }

        .customer-icon {
            background: linear-gradient(135deg, var(--primary) 0%, #4dd0e1 100%);
        }

        .admin-icon {
            background: linear-gradient(135deg, var(--secondary) 0%, #ffd54f 100%);
        }

        .hover-dropdown-item:hover .access-icon {
            transform: rotate(10deg);
        }

        .access-info {
            display: flex;
            flex-direction: column;
        }

        .access-title {
            font-weight: 600;
            font-size: 14px;
            color: var(--dark);
        }

        .access-desc {
            font-size: 12px;
            color: var(--text-gray);
        }

        .hover-dropdown-divider {
            height: 1px;
            background-color: rgba(0, 0, 0, 0.1);
            margin: 8px 0;
        }

        /* Mobile Optimized Dropdown */
        @media (max-width: 991.98px) {
            .hover-dropdown-content {
                position: static;
                opacity: 1;
                visibility: visible;
                transform: none;
                box-shadow: none;
                padding: 0;
                margin-top: 10px;
                margin-bottom: 10px;
            }

            .hover-dropdown-item {
                padding: 10px;
            }

            .nav-item.hover-dropdown {
                padding-bottom: 10px;
            }
        }
        
        /* Access Buttons */
        .access-buttons {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .login-btn {
            background-color: var(--primary);
            color: white;
            border-radius: 30px;
            padding: 8px 20px;
            font-weight: 500;
            transition: var(--transition);
            border: 2px solid var(--primary);
        }
        
        .login-btn:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .admin-btn {
            background-color: var(--secondary);
            color: var(--dark);
            border-radius: 30px;
            padding: 8px 20px;
            font-weight: 500;
            transition: var(--transition);
            border: 2px solid var(--secondary);
        }
        
        .admin-btn:hover {
            background-color: var(--secondary-dark);
            border-color: var(--secondary-dark);
            color: var(--dark);
            transform: translateY(-2px);
        }

        /* Feature Banner */
        .feature-banner {
            background: linear-gradient(135deg, var(--primary) 0%, #4dd0e1 100%);
            padding: 12px 0;
            color: white;
            position: relative;
        }

        .feature-banner-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .feature-item {
            display: flex;
            align-items: center;
            font-size: 14px;
            font-weight: 500;
        }

        .feature-item i {
            margin-right: 8px;
            font-size: 16px;
        }

        .feature-banner-btn {
            background-color: white;
            color: var(--primary);
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
        }

        .feature-banner-btn i {
            margin-left: 5px;
        }

        .feature-banner-btn:hover {
            background-color: var(--dark);
            color: white;
            transform: translateY(-2px);
        }

        @media (max-width: 767.98px) {
            .feature-banner-content {
                flex-direction: column;
                gap: 10px;
                padding: 10px 0;
            }
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--primary-light) 0%, #f0fdff 100%);
            padding: 120px 0 80px;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background-color: rgba(66, 195, 207, 0.1);
        }
        
        .hero::after {
            content: '';
            position: absolute;
            bottom: -100px;
            left: -100px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background-color: rgba(66, 195, 207, 0.1);
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero-badge {
            display: inline-block;
            background-color: rgba(66, 195, 207, 0.1);
            color: var(--primary);
            font-weight: 500;
            font-size: 14px;
            padding: 8px 20px;
            border-radius: 30px;
            margin-bottom: 20px;
            animation: fadeInUp 0.8s ease;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 20px;
            line-height: 1.2;
            animation: fadeInUp 1s ease;
        }
        
        .hero-title span {
            color: var(--primary);
            position: relative;
            display: inline-block;
        }
        
        .hero-subtitle {
            font-size: 1.1rem;
            color: var(--text-gray);
            margin-bottom: 30px;
            line-height: 1.8;
            animation: fadeInUp 1.2s ease;
        }

        .section-subtitle span{
            font-weight: bold;
            color: var(--secondary);
        }
        
        .hero-btn {
            display: inline-flex;
            align-items: center;
            background-color: transparent;
            color: var(--primary);
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            gap: 10px;
            border: 2px solid var(--primary);
            animation: fadeInUp 1.4s ease;
        }
        
        .hero-btn:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(66, 195, 207, 0.3);
        }
        
        .hero-btn-secondary {
            background-color:  #ffd54f;
            color: white;
            border: 2px solid #ffd54f;
            margin-left: 15px;
        }
        
        .hero-btn-secondary:hover {
            background-color: transparent;
            color: var(--secondary);
            border-color: var(--secondary);
        }
        
        .hero-image {
            position: relative;
            animation: float 6s ease-in-out infinite;
        }
        
        .hero-image img {
            max-width: 90%;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            display: block;
        }
        
        .hero-shape {
            position: absolute;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: rgba(66, 195, 207, 0.1);
            z-index: -1;
        }
        
        .shape-1 {
            top: -30px;
            right: -30px;
        }
        
        .shape-2 {
            bottom: -30px;
            left: -30px;
        }

        /* Hero Features */
        .hero-features {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 30px;
        }

        .hero-feature-item {
            display: flex;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.8);
            padding: 10px 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }

        .hero-feature-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .hero-feature-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--primary) 0%, #4dd0e1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            margin-right: 12px;
        }

        .hero-feature-text {
            font-size: 14px;
            font-weight: 500;
            color: var(--dark);
        }

        @media (max-width: 767.98px) {
            .hero-features {
                flex-direction: column;
            }
        }
        
        /* Access Cards Section */
        .access-section {
            padding: 50px 0;
            background-color: var(--light);
            text-align: center;
        }
        
        .access-section-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 15px;
        }
        
        .access-section-subtitle {
            font-size: 1rem;
            color: var(--text-gray);
            margin-bottom: 40px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .access-cards {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .access-card {
            width: 280px;
            background-color: var(--light);
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        
        .access-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .access-card-image {
            width: 100%;
            height: 180px;
            overflow: hidden;
            position: relative;
        }
        
        .access-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .access-card:hover .access-card-image img {
            transform: scale(1.1);
        }
        
        .admin-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, rgba(255, 193, 7, 0.3), rgba(255, 193, 7, 0.85));
        }
        
        .customer-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, rgba(66, 195, 207, 0.3), rgba(66, 195, 207, 0.7));
        }
        
        .access-card-content {
            padding: 25px 20px;
            text-align: center;
        }
        
        .access-card-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .admin-title {
            color: var(--secondary);
        }
        
        .customer-title {
            color: var(--primary);
        }
        
        .access-card-text {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .access-card-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 10px 0;
            border-radius: 30px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .admin-access-btn {
            background-color: var(--secondary);
            color: var(--dark);
        }
        
        .admin-access-btn:hover {
            background-color: var(--secondary-dark);
            color: white;
        }
        
        .customer-access-btn {
            background-color: var(--primary);
            color: white;
        }
        
        .customer-access-btn:hover {
            background-color: var(--primary-dark);
        }

        /* Customer Benefits Section */
        .customer-benefits {
            padding: 80px 0;
            background-color: var(--primary-light);
            position: relative;
            overflow: hidden;
        }

        .customer-benefits::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background-color: rgba(66, 195, 207, 0.1);
            z-index: 0;
        }

        .customer-benefits::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background-color: rgba(66, 195, 207, 0.1);
            z-index: 0;
        }

        .benefit-card {
            background-color: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            height: 100%;
            transition: var(--transition);
            position: relative;
            z-index: 1;
        }

        .benefit-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }

        .benefit-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            margin-bottom: 20px;
            transition: var(--transition);
        }

        .benefit-card:hover .benefit-icon {
            transform: rotateY(180deg);
        }

        .benefit-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }

        .benefit-text {
            font-size: 0.95rem;
            color: var(--text-gray);
            line-height: 1.7;
            margin-bottom: 20px;
        }

        .benefit-btn {
            display: inline-flex;
            align-items: center;
            background-color: transparent;
            color: var(--text-gray);
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            gap: 8px;
            border: 2px solid var(--secondary);
        }

        .benefit-btn:hover {
            background-color: var(--secondary);
            color: white;
            transform: translateY(-3px);
        }
        
        /* About Section */
        .about {
            padding: 100px 0;
            position: relative;
            overflow: hidden;
        }
        
        .about::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background-color: rgba(66, 195, 207, 0.05);
            border-radius: 50%;
            z-index: -1;
        }
        
        .section-badge {
            display: inline-block;
            background-color: rgba(66, 195, 207, 0.1);
            color: var(--primary);
            font-weight: 500;
            font-size: 14px;
            padding: 8px 20px;
            border-radius: 30px;
            margin-bottom: 15px;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 15px;
            position: relative;
        }
        
        .section-title span {
            color: var(--primary);
            position: relative;
            display: inline-block;
        }
        
        .section-subtitle {
            font-size: 1rem;
            color: var(--text-gray);
            margin-bottom: 50px;
            max-width: 700px;
        }
        
        .feature-card {
            background-color: var(--light);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            height: 100%;
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
            top: 0;
            left: 0;
            transform: scaleX(0);
            transform-origin: left;
            transition: var(--transition);
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }
        
        .feature-card:hover::before {
            transform: scaleX(1);
        }
        
        .feature-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #faf7e0 0%, #faebe0 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            color: var(--secondary);
            font-size: 28px;
            transition: var(--transition);
        }
        
        .feature-card:hover .feature-icon {
            background: var(--secondary); 
            color: #faebe0;
            transform: rotateY(180deg);
        }
        
        .feature-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }
        
        .feature-text {
            font-size: 0.95rem;
            color: var(--text-gray);
            line-height: 1.7;
        }
        
        /* Services Section */
        .services {
            padding: 100px 0;
            background-color: var(--gray);
            position: relative;
            overflow: hidden;
        }
        
        .services::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 300px;
            height: 300px;
            background-color: rgba(66, 195, 207, 0.05);
            border-radius: 50%;
            z-index: 0;
        }
        
        .service-card {
            background-color: var(--light);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            height: 100%;
            position: relative;
        }
        
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .service-image-container {
            position: relative;
            overflow: hidden;
            height: 200px;
        }

        .service-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            transition: transform 0.5s ease;
        }

        .service-card:hover .service-image {
            transform: scale(1.05);
        }
        
        .service-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: var(--primary);
            color: white;
            font-size: 12px;
            font-weight: 500;
            padding: 5px 15px;
            border-radius: 30px;
            z-index: 1;
        }
        
        .service-content {
            padding: 25px;
        }
        
        .service-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        .service-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .service-price-badge {
            background-color: rgba(66, 195, 207, 0.1);
            color: var(--primary);
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 20px;
            margin-left: 10px;
        }
        
        .service-text {
            font-size: 0.95rem;
            color: var(--text-gray);
            line-height: 1.7;
            margin-bottom: 20px;
        }
        
        .service-features {
            list-style: none;
            padding: 0;
            margin: 0 0 20px;
        }
        
        .service-features li {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: var(--text-gray);
        }
        
        .service-features li i {
            color: var(--primary);
            margin-right: 10px;
        }
        
        .service-btn {
            display: inline-flex;
            align-items: center;
            background-color: transparent;
            color: var(--primary);
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            gap: 8px;
            border: 2px solid var(--primary);
        }
        
        .service-btn:hover {
            background-color: var(--primary);
            color: white;
            transform: translateY(-3px);
        }
        
        /* Location Section */
        .location {
            padding: 100px 0;
            position: relative;
            overflow: hidden;
        }
        
        .location::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 300px;
            height: 300px;
            background-color: rgba(66, 195, 207, 0.05);
            border-radius: 50%;
            z-index: -1;
        }
        
        .location-card {
            background-color: var(--light);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            height: 100%;
        }
        
        .map-container {
            width: 100%;
            height: 450px;
            border: none;
        }
        
        .location-content {
            padding: 40px;
        }
        
        .location-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 25px;
            color: var(--dark);
            position: relative;
            display: inline-block;
        }
        
        .location-title::after {
            content: '';
            position: absolute;
            width: 50%;
            height: 4px;
            background-color: var(--primary);
            bottom: -10px;
            left: 0;
        }
        
        .location-info {
            margin-bottom: 30px;
        }
        
        .info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .info-icon {
            width: 50px;
            height: 50px;
            background: #f7f1cf;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary);
            font-size: 20px;
            margin-right: 20px;
            flex-shrink: 0;
            transition: var(--transition);
        }
        
        .info-item:hover .info-icon {
            background: var(--secondary);
            color: white;
            transform: rotateY(180deg);
        }
        
        .info-text {
            font-size: 1rem;
            color: var(--text-gray);
            line-height: 1.7;
        }
        
        .info-text strong {
            display: block;
            color: var(--dark);
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .direction-btn {
            display: inline-flex;
            align-items: center;
            background-color: transparent;
            color: var(--secondary);
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            gap: 10px;
            border: 2px solid var(--secondary);
        }
        
        .direction-btn:hover {
            background-color: var(--secondary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(207, 179, 66, 0.3);
        }
        
        .whatsapp-btn {
            background-color: #40f181;
            color: white;
            border-color: #40f181;
            margin-left: 15px;
        }
        
        .whatsapp-btn:hover {
            color: #25D366;
            background-color: transparent;
        }
        
        /* Testimonials Section */
        .testimonials {
            padding: 100px 0;
            background-color: var(--gray);
            position: relative;
            overflow: hidden;
        }
        
        .testimonials::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background-color: rgba(66, 195, 207, 0.05);
            border-radius: 50%;
            z-index: 0;
        }
        
        .testimonial-card {
            background-color: var(--light);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            height: 100%;
            position: relative;
        }
        
        .testimonial-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .testimonial-quote {
            font-size: 50px;
            color: var(--primary);
            opacity: 0.2;
            position: absolute;
            top: 20px;
            right: 30px;
        }
        
        .testimonial-text {
            font-size: 1rem;
            color: var(--text-gray);
            line-height: 1.8;
            margin-bottom: 25px;
            position: relative;
            z-index: 1;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
        }
        
        .author-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
        }
        
        .author-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .author-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .author-info p {
            font-size: 0.9rem;
            color: var(--text-gray);
            margin: 0;
        }
        
        .testimonial-rating {
            color: #FFD700;
            font-size: 18px;
            margin-top: 5px;
        }
        
        /* CTA Section */
        .cta {
            padding: 80px 0;
            background: linear-gradient(135deg, var(--primary) 0%, #4dd0e1 100%);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .cta::before,
        .cta::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.05);
            z-index: 0;
        }
        
        .cta::before {
            top: -150px;
            right: -150px;
        }
        
        .cta::after {
            bottom: -150px;
            left: -150px;
        }
        
        .cta-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .cta-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 20px;
        }
        
        .cta-text {
            font-size: 1.1rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .cta-btn {
            display: inline-flex;
            align-items: center;
            background-color: white;
            color: var(--primary);
            padding: 15px 40px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            transition: var(--transition);
            gap: 10px;
        }
        
        .cta-btn:hover {
            background-color: var(--dark);
            color: white;
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* Footer */
        .footer {
            background-color: var(--dark);
            color: white;
            padding: 80px 0 30px;
            position: relative;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .footer-logo img {
            height: 50px;
        }
        
        .footer-logo span {
            font-weight: 700;
            font-size: 24px;
            margin-left: 10px;
        }
        
        .footer-text {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.8;
            margin-bottom: 25px;
        }
        
        .footer-social {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .social-link {
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            transition: var(--transition);
        }
        
        .social-link:hover {
            background-color: var(--primary);
            transform: translateY(-5px);
        }
        
        .footer-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer-title::after {
            content: '';
            position: absolute;
            width: 50px;
            height: 3px;
            background-color: var(--primary);
            bottom: 0;
            left: 0;
        }
        
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer-links li {
            margin-bottom: 15px;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 0.95rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .footer-links a:hover {
            color: white;
            transform: translateX(5px);
        }
        
        .footer-links a i {
            color: var(--primary);
        }
        
        .footer-contact-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .footer-contact-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 16px;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .footer-contact-text {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 30px;
            margin-top: 50px;
            text-align: center;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.5);
        }
        
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background-color: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            z-index: 99;
            transition: var(--transition);
            opacity: 0;
            visibility: hidden;
        }
        
        .back-to-top.active {
            opacity: 1;
            visibility: visible;
            bottom: 30px;
        }
        
        .back-to-top:hover {
            background-color: var(--dark);
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* Floating Action Buttons */
        .floating-buttons {
            position: fixed;
            right: 30px;
            bottom: 100px;
            display: flex;
            flex-direction: column;
            gap: 15px;
            z-index: 99;
        }
        
        .floating-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .floating-btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .floating-btn.admin {
            background-color: var(--secondary);
        }
        
        .floating-btn.customer {
            background-color: var(--primary);
        }
        
        .floating-btn-tooltip {
            position: absolute;
            right: 70px;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 5px 15px;
            border-radius: 5px;
            font-size: 14px;
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
            white-space: nowrap;
        }
        
        .floating-btn:hover .floating-btn-tooltip {
            opacity: 1;
            visibility: visible;
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
        
        @keyframes fadeInDown {
            from { 
                opacity: 0;
                transform: translateY(-20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Responsive Styles */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 3rem;
            }
            
            .section-title {
                font-size: 2.2rem;
            }
            
            .cta-title {
                font-size: 2.2rem;
            }
        }
        
        @media (max-width: 992px) {
            .hero {
                padding: 100px 0 60px;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1rem;
            }
            
            .hero-image {
                margin-top: 50px;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .about, .services, .location, .testimonials, .cta {
                padding: 70px 0;
            }
            
            .feature-card, .service-card, .testimonial-card {
                margin-bottom: 30px;
            }
            
            .location-content {
                padding: 30px;
            }
            
            .cta-title {
                font-size: 2rem;
            }
            
            .access-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .admin-btn {
                margin-left: 0;
            }
        }
        
        @media (max-width: 768px) {
            .navbar {
                padding: 10px 0;
            }
            
            .hero {
                padding: 80px 30px;
            }
            
            .hero-title {
                font-size: 2.2rem;
            }
            
            .hero-btn, .hero-btn-secondary {
                display: block;
                width: 100%;
                margin: 0 0 15px 0;
                text-align: center;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .section-subtitle {
                font-size: 0.95rem;
            }
            
            .about, .services, .location, .testimonials, .cta {
                padding: 60px 0;
            }
            
            .cta-title {
                font-size: 1.8rem;
            }
            
            .cta-text {
                font-size: 1rem;
            }
            
            .footer {
                padding: 60px 0 30px;
            }
            
            .footer-col {
                margin-bottom: 40px;
            }
            
            .direction-btn, .whatsapp-btn {
                display: block;
                width: 100%;
                margin: 0 0 15px 0;
                text-align: center;
            }
            
            .access-section {
                padding: 20px 30px;
            }

            .customer-benefits{
                padding: 20px 30px;
            }
            
            .access-section-title {
                font-size: 1.8rem;
            }
            
            .access-cards {
                flex-direction: column;
                align-items: center;
            }
            
            .access-card {
                width: 100%;
                max-width: 320px;
            }
        }
        
        @media (max-width: 576px) {
            .splash-logo {
                width: 150px;
                height: 150px;
            }
            
            .splash-logo-container {
                width: 150px;
                height: 150px;
            }
            
            .splash-text {
                font-size: 1.5rem;
            }
            
            .splash-loading {
                width: 30px;
                height: 30px;
            }
            
            .hero-title {
                font-size: 1.8rem;
            }
            
            .hero-badge, .section-badge {
                font-size: 12px;
                padding: 6px 15px;
            }
            
            .section-title {
                font-size: 1.6rem;
            }
            
            .feature-card, .service-card, .testimonial-card {
                padding: 25px;
            }
            
            .location-content {
                padding: 25px;
            }
            
            .cta-title {
                font-size: 1.6rem;
            }
            
            .cta-btn {
                padding: 12px 30px;
                font-size: 1rem;
            }
            
            .back-to-top {
                width: 40px;
                height: 40px;
                font-size: 16px;
                right: 20px;
                bottom: 20px;
            }
            
            .floating-buttons {
                right: 20px;
                bottom: 80px;
            }
            
            .floating-btn {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
        }
        
        /* Store Status Banner Styles */
        .store-status-banner {
            position: relative;
            width: 100%;
            padding: 15px 0;
            text-align: center;
            color: white;
            font-weight: 500;
            z-index: 1000;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
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
            font-size: 1.2rem;
        }
        
        .store-status-banner-text {
            font-size: 1rem;
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
    </style>
</head>
<body>
    <!-- Banner pemberitahuan tutup -->
    <?php if ($storeStatus == 'tutup'): ?>
    <div class="store-status-banner closed" id="storeStatusBanner">
        <div class="store-status-banner-content">
            <i class="fas fa-store-slash"></i>
            <div class="store-status-banner-text">
                Mohon maaf, saat ini toko sedang TUTUP / LIBUR
                <span class="store-status-banner-time">
                    (Diperbarui: <?= $statusTime ?>)
                </span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Splash Screen -->
    <div class="splash-screen" id="splashScreen">
        <div class="splash-content">
            <div class="splash-logo-container">
                <img src="assets/images/zeea_laundry.png" alt="Zeea Laundry Logo" class="splash-logo" id="splashLogo" onload="handleSplashImageLoad()" onerror="handleSplashImageError()" loading="eager" decoding="sync">
            </div>
            <div class="splash-text" id="splashText">Rapi, Bersih, Wangi</div>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg sticky-top" id="navbar">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="assets/images/favicon.png" alt="Zeea Laundry Logo">
                <span>Zeea Laundry</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#home">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">Tentang Kami</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#services">Layanan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#location">Lokasi</a>
                    </li>
                    <li class="nav-item hover-dropdown">
                        <a class="nav-link" href="#akses">
                            <i class="fas fa-sign-in-alt me-1"></i> Akses Sistem
                        </a>
                        <div class="hover-dropdown-content">
                            <a href="pelanggan/" class="hover-dropdown-item customer-item">
                                <div class="access-icon customer-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="access-info">
                                    <span class="access-title">Akses Pelanggan</span>
                                    <span class="access-desc">Pesan & lacak laundry Anda</span>
                                </div>
                            </a>
                            <div class="hover-dropdown-divider"></div>
                            <a href="admin/" class="hover-dropdown-item admin-item">
                                <div class="access-icon admin-icon">
                                    <i class="fas fa-lock"></i>
                                </div>
                                <div class="access-info">
                                    <span class="access-title">Akses Admin</span>
                                    <span class="access-desc">Kelola pesanan & pelanggan</span>
                                </div>
                            </a>
                        </div>
                    </li>
                </ul>

            </div>
        </div>
    </nav>

        <!-- Banner pemberitahuan atas -->
        <div class="feature-banner">
            <div class="container">
                <div class="feature-banner-content">
                    <div class="feature-item">
                        <i class="fas fa-store"></i>
                        Status Toko:  
                        <?php if ($storeStatus == 'buka'): ?>
                            <strong>BUKA</strong>
                        <?php else: ?>
                            <strong style="color: red; font-weight: bold; text-transform: uppercase;"> TUTUP</strong>
                        <?php endif; ?>
                    </div>
                    <div class="feature-item">
                        <i class="fas fa-clock"></i>
                        Jam Operasional: 06.30 - 21.00 WIB
                    </div>
                    <a href="pelanggan/" class="feature-banner-btn">
                        Akses Pelanggan <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>




    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <div class="hero-badge">
                         Rapi, Bersih, Wangi
                    </div>
                    <h1 class="hero-title"><span>Zeea Laundry</span> Solusi Terpercaya untuk Pakaian Anda</h1>
                    <p class="hero-subtitle">Zeea Laundry menyediakan layanan cuci dan setrika profesional dengan hasil rapi, bersih, dan wangi. Kami menggunakan deterjen berkualitas dan teknologi modern untuk merawat pakaian Anda.</p>
                    <div>
                        <a href="#services" class="hero-btn">
                            Lihat Layanan <i class="fas fa-arrow-right"></i>
                        </a>
                        <a href="#akses" class="hero-btn hero-btn-secondary">
                            <i class="fas fa-sign-in-alt"></i> Akses Sistem
                        </a>
                    </div>
                    
                    <div class="hero-features">
                        <div class="hero-feature-item">
                            <div class="hero-feature-icon">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="hero-feature-text">Layanan Antar Jemput</div>
                        </div>
                        <div class="hero-feature-item">
                            <div class="hero-feature-icon">
                                <i class="fas fa-search"></i>
                            </div>
                            <div class="hero-feature-text">Lacak Pesanan Online</div>
                        </div>
                        <div class="hero-feature-item">
                            <div class="hero-feature-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="hero-feature-text">Riwayat Transaksi</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image">
                        <div class="hero-shape shape-1"></div>
                        <img src="assets/images/hero.png" alt="Zeea Laundry Service" class="img-fluid">
                        <div class="hero-shape shape-2"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Keuntungan pelanggan -->
    <section class="customer-benefits" id="benefits">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mx-auto text-center">
                    <div class="section-badge">
                        <i class="fas fa-star me-2"></i> Keuntungan Pelanggan
                    </div>
                    <h2 class="section-title">Anda Seorang <span>Pelanggan?</span></h2>
                    <p class="section-subtitle">Semua kebutuhan pelanggan dapat diakses melalui halaman <span>akses sistem</span> pelanggan dibawah</p>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-4 mb-4">
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h3 class="benefit-title">Antar Jemput</h3>
                        <p class="benefit-text">Pesan layanan antar jemput langsung dari akun pelanggan Anda. Kami akan menjemput dan mengantar pakaian Anda tepat waktu.</p>
                        <a href="pelanggan/" class="benefit-btn">
                            <i class="fas fa-arrow-right"></i> Pesan Sekarang
                        </a>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h3 class="benefit-title">Lacak Pesanan</h3>
                        <p class="benefit-text">Pantau status pesanan laundry Anda secara real-time. Anda dapat melihat apakah pakaian sedang dicuci, disetrika, atau siap untuk diambil kapan saja.</p>
                        <a href="pelanggan/" class="benefit-btn">
                            <i class="fas fa-arrow-right"></i> Lacak Sekarang
                        </a>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="benefit-card">
                        <div class="benefit-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <h3 class="benefit-title">Riwayat Transaksi</h3>
                        <p class="benefit-text">Akses riwayat transaksi laundry Anda dengan mudah. Lihat detail pesanan sebelumnya dan pesan kembali layanan yang sama dengan cepat.</p>
                        <a href="pelanggan/" class="benefit-btn">
                            <i class="fas fa-arrow-right"></i> Lihat Riwayat
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- akses sistem -->
    <section class="access-section" id="akses">
                <div class="section-badge">
                    <i class="fas fa-sign-in me-2"></i> Masuk ke Sistem
                </div>
            <h2 class="section-title"><span>Akses Sistem</span> Zeea Laundry</h2>
            <p class="access-section-subtitle">Silahkan pilih akses sesuai dengan kebutuhan Anda</p>
            
            <div class="access-cards">
                <a href="pelanggan/" class="access-card">
                    <div class="access-card-image">
                        <img src="assets/images/gambarpelanggan.png" alt="Customer Access">
                        <div class="customer-overlay"></div>
                    </div>
                    <div class="access-card-content">
                        <h3 class="access-card-title customer-title">Pelanggan</h3>
                        <p class="access-card-text">Pesan layanan dan cek status</p>
                        <div class="access-card-btn customer-access-btn">
                            <i class="fas fa-sign-in-alt me-2"></i> Masuk
                        </div>
                    </div>
                </a>

                <a href="admin/" class="access-card">
                    <div class="access-card-image">
                        <img src="assets/images/gambaradmin.png" alt="Admin Access">
                        <div class="admin-overlay"></div>
                    </div>
                    <div class="access-card-content">
                        <h3 class="access-card-title admin-title">Admin</h3>
                        <p class="access-card-text">Kelola pesanan pelanggan</p>
                        <div class="access-card-btn admin-access-btn">
                            <i class="fas fa-sign-in-alt me-2"></i> Masuk
                        </div>
                    </div>
                </a>
            </div>
    </section>

    <!-- tentang kami -->
    <section class="about" id="about">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mx-auto text-center">
                    <div class="section-badge">
                        <i class="fas fa-info-circle me-2"></i> Tentang Kami
                    </div>
                    <h2 class="section-title">Kenapa Memilih <span>Zeea Laundry?</span></h2>
                    <p class="section-subtitle">Zeea Laundry adalah penyedia jasa laundry profesional yang berkomitmen memberikan layanan terbaik untuk semua kebutuhan laundry Anda.</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-tshirt"></i>
                        </div>
                        <h3 class="feature-title">Kualitas Terbaik</h3>
                        <p class="feature-text">Kami menggunakan deterjen berkualitas tinggi dan teknologi modern untuk memastikan pakaian Anda bersih, wangi, dan terawat dengan baik.</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h3 class="feature-title">Tepat Waktu</h3>
                        <p class="feature-text">Kami menghargai waktu Anda. Layanan kami menjamin pengerjaan dan pengiriman yang tepat waktu sesuai dengan jadwal yang dijanjikan.</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <h3 class="feature-title">Harga Terjangkau</h3>
                        <p class="feature-text">Kami menawarkan layanan berkualitas dengan harga yang terjangkau. Berbagai paket tersedia untuk memenuhi kebutuhan dan anggaran Anda.</p>
                    </div>
                </div>
            </div>
            
            <div class="row mt-4">
                <div class="col-md-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h3 class="feature-title">Antar Jemput</h3>
                        <p class="feature-text">Kami menyediakan layanan antar jemput untuk memudahkan Anda. Cukup hubungi kami, dan kami akan mengambil dan mengantar pakaian Anda.</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="feature-title">Aman & Terpercaya</h3>
                        <p class="feature-text">Keamanan pakaian Anda adalah prioritas kami. Kami menjamin pakaian Anda aman dan tidak akan tertukar dengan pelanggan lain.</p>
                    </div>
                </div>
                

            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mx-auto text-center">
                    <div class="section-badge">
                        <i class="fas fa-list-alt me-2"></i> Layanan Kami
                    </div>
                    <h2 class="section-title">Paket <span>Layanan</span> Kami</h2>
                    <p class="section-subtitle">Kami menyediakan berbagai layanan laundry untuk memenuhi kebutuhan Anda. Berikut adalah paket layanan yang kami tawarkan.</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="service-card">
                        <div class="service-image-container">
                            <img src="assets/images/cucicuci.png" alt="Cuci Kering" class="service-image">
                            <div class="service-badge">Populer</div>
                        </div>
                        <div class="service-content">
                            <h3 class="service-title">Cuci Kering</h3>
                            <div class="service-price">
                                Rp 4.500 / kg
                                <span class="service-price-badge">Hemat</span>
                            </div>
                            <p class="service-text">Layanan cuci dengan pengeringan tanpa setrika. Cocok untuk pakaian sehari-hari yang tidak memerlukan setrika.</p>
                            <ul class="service-features">
                                <li><i class="fas fa-check-circle"></i> Cuci dengan deterjen premium</li>
                                <li><i class="fas fa-check-circle"></i> Pengeringan sempurna</li>
                                <li><i class="fas fa-check-circle"></i> Pewangi pakaian</li>
                                <li><i class="fas fa-check-circle"></i> Lipat rapi</li>
                            </ul>
                            <a href="https://wa.me/62895395442010?text=Halo%20Zeea%20Laundry,%20saya%20ingin%20menggunakan%20layanan%20Cuci%20Kering" class="service-btn" target="_blank">
                                <i class="fab fa-whatsapp"></i> Pesan Sekarang
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="service-card">
                        <div class="service-image-container">
                            <img src="assets/images/cuci_setrika.png" alt="Cuci Setrika" class="service-image">
                            <div class="service-badge">Terlaris</div>
                        </div>
                        <div class="service-content">
                            <h3 class="service-title">Cuci Setrika</h3>
                            <div class="service-price">
                                Rp 6.500 / kg
                                <span class="service-price-badge">Terbaik</span>
                            </div>
                            <p class="service-text">Layanan cuci dengan pengeringan dan setrika. Pakaian Anda akan bersih, wangi, dan rapi siap digunakan.</p>
                            <ul class="service-features">
                                <li><i class="fas fa-check-circle"></i> Cuci dengan deterjen premium</li>
                                <li><i class="fas fa-check-circle"></i> Pengeringan sempurna</li>
                                <li><i class="fas fa-check-circle"></i> Setrika profesional</li>
                                <li><i class="fas fa-check-circle"></i> Pewangi pakaian premium</li>
                            </ul>
                            <a href="https://wa.me/62895395442010?text=Halo%20Zeea%20Laundry,%20saya%20ingin%20menggunakan%20layanan%20Cuci%20Setrika" class="service-btn" target="_blank">
                                <i class="fab fa-whatsapp"></i> Pesan Sekarang
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="service-card">
                        <div class="service-image-container">
                            <img src="assets/images/setrika2.png" alt="Setrika Saja" class="service-image">
                            <div class="service-badge">Praktis</div>
                        </div>
                        <div class="service-content">
                            <h3 class="service-title">Setrika Saja</h3>
                            <div class="service-price">
                                Rp 4.500 / kg
                                <span class="service-price-badge">Cepat</span>
                            </div>
                            <p class="service-text">Layanan setrika untuk pakaian yang sudah bersih. Membuat pakaian Anda rapi dan siap digunakan.</p>
                            <ul class="service-features">
                                <li><i class="fas fa-check-circle"></i> Setrika profesional</li>
                                <li><i class="fas fa-check-circle"></i> Lipat rapi</li>
                                <li><i class="fas fa-check-circle"></i> Pewangi pakaian</li>
                                <li><i class="fas fa-check-circle"></i> Pengemasan rapi</li>
                            </ul>
                            <a href="https://wa.me/62895395442010?text=Halo%20Zeea%20Laundry,%20saya%20ingin%20menggunakan%20layanan%20Setrika%20Saja" class="service-btn" target="_blank">
                                <i class="fab fa-whatsapp"></i> Pesan Sekarang
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Location Section -->
    <section class="location" id="location">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mx-auto text-center">
                    <div class="section-badge">
                        <i class="fas fa-map-marker-alt me-2"></i> Lokasi Kami
                    </div>
                    <h2 class="section-title">Kunjungi <span>Toko Kami</span></h2>
                    <p class="section-subtitle">Kunjungi toko kami atau hubungi kami untuk informasi lebih lanjut tentang layanan laundry kami.</p>
                </div>
            </div>
            
            <div class="row mt-5">
                <div class="col-lg-12">
                    <div class="location-card">
                        <div class="row">
                            <div class="col-lg-6">
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3962.4321846328426!2d111.39433237453791!3d-6.716997365672276!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e772392b8ed6ca3%3A0xb27a5004465a4933!2sZeea%20Laundry!5e0!3m2!1sid!2sid!4v1745457510648!5m2!1sid!2sid" width="600  width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                            </div>
                            <div class="col-lg-6">
                                <div class="location-content">
                                    <h3 class="location-title">Zeea Laundry</h3>
                                    
                                    <div class="location-info">
                                        <div class="info-item">
                                            <div class="info-icon">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </div>
                                            <div class="info-text">
                                                <strong>Alamat</strong>
                                                Dukuh Jambangan RT.02/RW.03, Padaran, Kabupaten Rembang, Jawa Tengah 59219
                                            </div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-icon">
                                                <i class="fas fa-phone-alt"></i>
                                            </div>
                                            <div class="info-text">
                                                <strong>Telepon atau Whatsapp</strong>
                                                +62 895-3954-42010
                                            </div>
                                        </div>
                                        
                                        <div class="info-item">
                                            <div class="info-icon">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                            <div class="info-text">
                                                <strong>Jam Operasional</strong>
                                                Senin - Minggu Jam 06.30 - 21.00 WIB
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <a href="https://maps.app.goo.gl/NwAPY4Y2SKSJEMvC8" target="_blank" class="direction-btn">
                                            <i class="fas fa-directions"></i> Petunjuk Arah
                                        </a>
                                        <a href="https://wa.me/62895395442010?text=Halo%20Zeea%20Laundry,%20saya%20ingin%20bertanya%20tentang%20layanan%20laundry" target="_blank" class="direction-btn whatsapp-btn">
                                            <i class="fab fa-whatsapp"></i> Hubungi Kami
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <div class="cta-content">
                <h2 class="cta-title">Siap Untuk Mencoba Layanan Kami?</h2>
                <p class="cta-text">Hubungi kami sekarang untuk mendapatkan layanan laundry terbaik. Kami siap melayani kebutuhan laundry Anda dengan profesional.</p>
                <a href="https://wa.me/62895395442010?text=Halo%20Zeea%20Laundry,%20saya%20ingin%20menggunakan%20layanan%20laundry" class="cta-btn" target="_blank">
                    <i class="fab fa-whatsapp me-2"></i> Hubungi Kami Sekarang
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 col-md-6 footer-col">
                    <div class="footer-logo">
                        <img src="assets/images/zeea_laundry.png" alt="Zeea Laundry Logo">
                        <span>Zeea Laundry</span>
                    </div>
                    <p class="footer-text">Zeea Laundry adalah penyedia jasa laundry profesional yang berkomitmen memberikan layanan terbaik untuk semua kebutuhan laundry Anda.</p>
                </div>
                
                <div class="col-lg-2 col-md-6 footer-col">
                    <h3 class="footer-title">Tautan</h3>
                    <ul class="footer-links">
                        <li><a href="#home"><i class="fas fa-chevron-right"></i> Beranda</a></li>
                        <li><a href="#about"><i class="fas fa-chevron-right"></i> Tentang Kami</a></li>
                        <li><a href="#services"><i class="fas fa-chevron-right"></i> Layanan</a></li>
                        <li><a href="#location"><i class="fas fa-chevron-right"></i> Lokasi</a></li>
                        <li><a href="#akses"><i class="fas fa-chevron-right"></i> Akses Sistem</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 footer-col">
                    <h3 class="footer-title">Layanan</h3>
                    <ul class="footer-links">
                        <li><a href="#services"><i class="fas fa-chevron-right"></i> Cuci Kering</a></li>
                        <li><a href="#services"><i class="fas fa-chevron-right"></i> Cuci Setrika</a></li>
                        <li><a href="#services"><i class="fas fa-chevron-right"></i> Setrika Saja</a></li>
                        <li><a href="#services"><i class="fas fa-chevron-right"></i> Cuci Custom</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3 col-md-6 footer-col">
                    <h3 class="footer-title">Kontak Kami</h3>
                    <div class="footer-contact-item">
                        <div class="footer-contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="footer-contact-text">
                            Dukuh Jambangan RT.02/RW.03, Padaran, Kabupaten Rembang, Jawa Tengah 59219
                        </div>
                    </div>
                    <div class="footer-contact-item">
                        <div class="footer-contact-icon">
                            <i class="fas fa-phone-alt"></i>
                        </div>
                        <div class="footer-contact-text">
                            +62 895-3954-42010
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Destio Wahyu - Zeea Laundry. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <div class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </div>
    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Polyfill for older browsers
        if (!window.requestAnimationFrame) {
            window.requestAnimationFrame = function(callback) {
                return setTimeout(callback, 16);
            };
        }
        // Global variables for splash screen
        let splashImageLoaded = false;
        let splashTimeout = null;
        
        function showSplashScreen() {
            document.body.classList.add('splash-ready');
        }
        
        function handleSplashImageLoad() {
            splashImageLoaded = true;
            showSplashScreen();
            setTimeout(hideSplashScreen, 2000); // tampil 2 detik
        }
        
        function handleSplashImageError() {
            showSplashScreen();
            setTimeout(hideSplashScreen, 2000);
        }
        
        function hideSplashScreen() {
            const splashScreen = document.getElementById('splashScreen');
            if (splashScreen) {
                splashScreen.style.opacity = '0';
                splashScreen.style.visibility = 'hidden';
                setTimeout(() => {
                    if (splashScreen.parentNode) splashScreen.parentNode.removeChild(splashScreen);
                }, 500);
            }
        }
        
        // Preload critical images
        function preloadImages() {
            const criticalImages = [
                'assets/images/zeea_laundry.png',
                'assets/images/favicon.png'
            ];
            
            criticalImages.forEach(src => {
                const img = new Image();
                img.onload = function() {
                    console.log('Preloaded:', src);
                };
                img.onerror = function() {
                    console.log('Failed to preload:', src);
                };
                img.src = src;
            });
        }
        
        // Start preloading images immediately
        preloadImages();
        
        document.addEventListener('DOMContentLoaded', function() {
            const splashLogo = document.getElementById('splashLogo');
            
            // Check if image is already loaded
            if (splashLogo && splashLogo.complete && splashLogo.naturalHeight !== 0) {
                splashImageLoaded = true;
                handleSplashImageLoad();
            } else if (splashLogo) {
                splashLogo.addEventListener('load', function() {
                    splashImageLoaded = true;
                    handleSplashImageLoad();
                });
                
                splashLogo.addEventListener('error', function() {
                    handleSplashImageError();
                });
            } else {
                // Fallback if image element not found
                setTimeout(handleSplashImageError, 1000);
            }
            
            // Fallback timeout to ensure page shows
            setTimeout(function() {
                if (!document.body.classList.contains('splash-ready')) {
                    console.log('Force showing page due to timeout');
                    showSplashScreen();
                    setTimeout(hideSplashScreen, 2000);
                }
            }, 5000); // Maximum 5 seconds

            
            // Navbar scroll effect
            const navbar = document.getElementById('navbar');
            window.addEventListener('scroll', function() {
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });
            
            // Active link highlighting
            const sections = document.querySelectorAll('section');
            const navLinks = document.querySelectorAll('.nav-link');
            
            window.addEventListener('scroll', () => {
                let current = '';
                
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;
                    
                    if (pageYOffset >= sectionTop - 150) {
                        current = section.getAttribute('id');
                    }
                });
                
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === '#' + current) {
                        link.classList.add('active');
                    }
                    
                    // Special case for home
                    if (current === 'home' && link.getAttribute('href') === '#home') {
                        link.classList.add('active');
                    }
                });
            });
            
            // Back to top button
            const backToTopButton = document.getElementById('backToTop');
            
            window.addEventListener('scroll', () => {
                if (window.scrollY > 300) {
                    backToTopButton.classList.add('active');
                } else {
                    backToTopButton.classList.remove('active');
                }
            });
            
            backToTopButton.addEventListener('click', () => {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        window.scrollTo({
                            top: target.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                });
            });

            // Mobile optimization for hover dropdown
            if (window.innerWidth < 992) {
                document.querySelectorAll('.hover-dropdown').forEach(dropdown => {
                    dropdown.addEventListener('click', function(e) {
                        if (e.target.classList.contains('nav-link')) {
                            e.preventDefault();
                            const content = this.querySelector('.hover-dropdown-content');
                            if (content.style.display === 'block') {
                                content.style.display = 'none';
                            } else {
                                content.style.display = 'block';
                            }
                        }
                    });
                });
            }
        });

        // Function to close the status banner
        function closeStatusBanner() {
            document.getElementById('storeStatusBanner').style.display = 'none';
        }
    </script>
</body>
</html>

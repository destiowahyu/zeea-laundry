<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zeea Laundry</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/zeea_laundry.png">
    <style>
        :root {
            --primary: #42c3cf;
            --primary-dark: #38adb8;
            --secondary: #ffc107;
            --secondary-dark: #e0a800;
            --dark: #333333;
            --light: #ffffff;
            --bg: #f8f9fa;
            --radius: 16px;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg);
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
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
        }
        
        .splash-content {
            text-align: center;
            animation: fadeInUp 0.8s ease forwards;
        }
        
        .splash-logo {
            width: 300px;
            height: 300px;
            animation: pulse 2s infinite;
        }
        
        .splash-text {
            color: white;
            font-size: 28px;
            margin-top: 20px;
            opacity: 0;
            animation: fadeIn 1s ease forwards 0.5s;
        }
        
        /* Main Content */
        .main-container {
            width: 100%;
            max-width: 1200px;
            padding: 20px;
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 1s ease, transform 1s ease;
        }
        
        .main-container.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .welcome-header {
            text-align: center;
            margin-bottom: 40px;
            padding-top: 15px;
            animation: fadeInUp 0.8s ease-out;
            animation: fadeInDown 0.8s ease-out;
        }
        
        .welcome-logo {
            width: 100px;
            height: 100px;
            margin-bottom: 15px;
            animation: float 6s ease-in-out infinite;
        }
        
        .welcome-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .welcome-subtitle {
            font-size: 1rem;
            color: #777;
            margin-bottom: 0;
        }
        
        /* Role Selection Cards */
        .role-container {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 30px 0 50px;
        }
        
        .role-card {
            width: 280px;
            height: 380px;
            background-color: var(--light);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            position: relative;
            cursor: pointer;
            transition: var(--transition);
            animation: fadeInUp 0.8s ease-out;
        }
        
        .role-card:nth-child(2) {
            animation-delay: 0.2s;
        }
        
        .role-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .role-image-container {
            width: 100%;
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        
        .role-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .role-card:hover .role-image {
            transform: scale(1.1);
        }
        
        .role-image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            transition: var(--transition);
        }
        
        .admin-overlay {
            background: linear-gradient(to bottom, rgba(255, 193, 7, 0.27), rgba(255, 193, 7, 0.85));
        }
        
        .customer-overlay {
            background: linear-gradient(to bottom, rgba(66, 195, 207, 0.29), rgba(66, 195, 207, 0.64));
        }
        
        .role-card:hover .role-image-overlay {
            opacity: 0.8;
        }
        
        .role-content {
            padding: 25px 20px 20px;
            text-align: center;
            position: relative;
        }
        
        .role-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 10px;
            transition: var(--transition);
        }
        
        .admin-title {
            color: var(--secondary);
        }
        
        .customer-title {
            color: var(--primary);
        }
        
        .role-card:hover .role-title {
            transform: translateY(-5px);
        }
        
        .role-description {
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 20px;
            line-height: 1.4;
            transition: var(--transition);
        }
        
        .role-card:hover .role-description {
            transform: translateY(-5px);
        }
        
        .role-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 25px;
            border-radius: 30px;
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            border: none;
            width: 100%;
            position: relative;
            overflow: hidden;
        }
        
        .role-btn:after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        
        .role-btn:hover:after {
            animation: ripple 1s ease-out;
        }
        
        .admin-btn {
            background-color: var(--secondary);
            color: var(--dark);
        }
        
        .admin-btn:hover {
            background-color: var(--secondary-dark);
            color: var(--dark);
            transform: translateY(-3px);
        }
        
        .customer-btn {
            background-color: var(--primary);
            color: white;
        }
        
        .customer-btn:hover {
            background-color: var(--primary-dark);
            color: white;
            transform: translateY(-3px);
        }
        
        .tap-hint {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(255, 255, 255, 0.9);
            color: var(--dark);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            opacity: 0;
            transition: var(--transition);
            pointer-events: none;
        }
        
        .role-card:hover .tap-hint {
            opacity: 1;
            animation: fadeInScale 0.5s ease forwards;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            color: #777;
            font-size: 0.9rem;
            padding-bottom: 30px;
            animation: fadeIn 1s ease-out;
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
        
        @keyframes fadeInScale {
            from { 
                opacity: 0;
                transform: translate(-50%, -50%) scale(0.8);
            }
            to { 
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            20% {
                transform: scale(25, 25);
                opacity: 0.5;
            }
            100% {
                opacity: 0;
                transform: scale(40, 40);
            }
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .role-container {
                flex-direction: column;
                align-items: center;
            }
            
            .role-card {
                width: 100%;
                max-width: 320px;
                margin-bottom: 20px;
            }
            
            .footer {
                padding-bottom: 50px;
            }
        }
        
        @media (max-width: 480px) {
            .welcome-title {
                font-size: 1.8rem;
            }
            
            .splash-logo {
                width: 200px;
                height: 200px;
            }
            
            .splash-text {
                font-size: 1.0rem;
            }
            
            .footer {
                padding-bottom: 70px;
            }
        }
    </style>
</head>
<body>
    <!-- Splash Screen -->
    <div class="splash-screen" id="splashScreen">
        <div class="splash-content">
            <img src="assets/images/zeea_laundry.png" alt="Zeea Laundry Logo" class="splash-logo">
            <div class="splash-text">Rapi, Bersih, Wangi</div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-container" id="mainContent">
        <div class="welcome-header">
            <h1 class="welcome-title">Zeea Laundry</h1>
            <p class="welcome-subtitle">Pilih peran Anda</p>
        </div>
        
        <div class="role-container">
            <!-- Admin Card -->
            <div class="role-card" onclick="window.location.href='admin/login.php'">
                <div class="role-image-container">
                    <img src="assets/images/gambaradmin.png" alt="Admin" class="role-image">
                    <div class="role-image-overlay admin-overlay"></div>
                    <div class="tap-hint">
                        <i class="fas fa-hand-pointer me-1"></i> Tap untuk masuk
                    </div>
                </div>
                <div class="role-content">
                    <h2 class="role-title admin-title">Admin</h2>
                    <p class="role-description">Kelola pesanan dan pelanggan</p>
                    <a href="admin/login.php" class="role-btn admin-btn">
                        <i class="fas fa-sign-in-alt me-2"></i> Masuk
                    </a>
                </div>
            </div>
            
            <!-- Customer Card -->
            <div class="role-card" onclick="window.location.href='pelanggan/dashboard.php'">
                <div class="role-image-container">
                    <img src="assets/images/gambarpelanggan.png" alt="Pelanggan" class="role-image">
                    <div class="role-image-overlay customer-overlay"></div>
                    <div class="tap-hint">
                        <i class="fas fa-hand-pointer me-1"></i> Tap untuk masuk
                    </div>
                </div>
                <div class="role-content">
                    <h2 class="role-title customer-title">Pelanggan</h2>
                    <p class="role-description">Pesan layanan dan cek status</p>
                    <a href="pelanggan/dashboard.php" class="role-btn customer-btn">
                        <i class="fas fa-sign-in-alt me-2"></i> Masuk
                    </a>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Destio Wahyu - Zeea Laundry. All Rights Reserved.</p>
        </div>
    </div>

    <script>
        // Splash screen functionality
        document.addEventListener('DOMContentLoaded', function() {
            const splashScreen = document.getElementById('splashScreen');
            const mainContent = document.getElementById('mainContent');
            
            // Show splash screen for 2.5 seconds
            setTimeout(function() {
                splashScreen.style.opacity = '0';
                splashScreen.style.visibility = 'hidden';
                mainContent.classList.add('visible');
            }, 1800);
        });
    </script>
</body>
</html>

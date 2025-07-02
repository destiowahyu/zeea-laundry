<?php
session_start();
require '../includes/db.php';


if (!isset($_SESSION['role']) && isset($_COOKIE['remember_admin'])) {
    $remembered_data = json_decode($_COOKIE['remember_admin'], true);
    

    $query = "SELECT * FROM admin WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $remembered_data['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        $_SESSION['role'] = 'admin';
        $_SESSION['username'] = $admin['username'];
        $_SESSION['id'] = $admin['id'];
        header("Location: ../splash.php");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;

    //Deteksi SQL Injection
    $blacklist_patterns = [
        "/('|--|;|`)/",      
        "/(union|select|insert|delete|update|drop|alter)/i",
        "/(\bor\b|\band\b)/i",    
    ];

    foreach ($blacklist_patterns as $pattern) {
        if (preg_match($pattern, $username) || preg_match($pattern, $password)) {
            header("Location: sqlinjection.php");
            exit;
        }
    }

    //Validasi username
    if (!preg_match("/^[a-zA-Z0-9_-]+$/", $username)) {
        header("Location: sqlinjection.php");
        exit;
    }

    //Validasi panjang input
    $max_length = 50;
    if (strlen($username) > $max_length || strlen($password) > $max_length) {
        header("Location: sqlinjection.php");
        exit;
    }

    // Logging aktivitas mencurigakan
    function logSuspiciousActivity($username, $reason) {
        $file = 'suspicious.log';
        $log = date("Y-m-d H:i:s") . " - Username: $username - Reason: $reason\n";
        file_put_contents($file, $log, FILE_APPEND);
    }

    $query = "SELECT * FROM admin WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['role'] = 'admin';
            $_SESSION['username'] = $admin['username'];
            $_SESSION['id'] = $admin['id'];
            
            if ($remember) {
                $cookie_data = json_encode([
                    'username' => $admin['username']
                ]);
                setcookie('remember_admin', $cookie_data, time() + (86400 * 30), "/");
            }
            
            header("Location: ../splash.php");
            exit;
        }
    }

    $error = "Username atau password salah!";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Zeea Laundry</title>
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #42c3cf;
            --primary-dark: #38adb8;
            --secondary: #ffc107;
            --secondary-dark: #e0a800;
            --dark: #333333;
            --light: #ffffff;
            --bg: #f8f9fa;
            --danger: #dc3545;
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
            position: relative;
        }
        
        .bubbles {
            position: absolute;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
            top: 0;
            left: 0;
        }
        
        .bubble {
            position: absolute;
            bottom: -100px;
            width: 40px;
            height: 40px;
            background: var(--secondary);
            border-radius: 50%;
            opacity: 0.5;
            animation: rise 10s infinite ease-in;
        }
        
        .bubble:nth-child(1) {
            width: 40px;
            height: 40px;
            left: 10%;
            animation-duration: 8s;
        }
        
        .bubble:nth-child(2) {
            width: 20px;
            height: 20px;
            left: 20%;
            animation-duration: 5s;
            animation-delay: 1s;
        }
        
        .bubble:nth-child(3) {
            width: 50px;
            height: 50px;
            left: 35%;
            animation-duration: 7s;
            animation-delay: 2s;
        }
        
        .bubble:nth-child(4) {
            width: 80px;
            height: 80px;
            left: 50%;
            animation-duration: 11s;
            animation-delay: 0s;
        }
        
        .bubble:nth-child(5) {
            width: 35px;
            height: 35px;
            left: 55%;
            animation-duration: 6s;
            animation-delay: 1s;
        }
        
        .bubble:nth-child(6) {
            width: 45px;
            height: 45px;
            left: 65%;
            animation-duration: 8s;
            animation-delay: 3s;
        }
        
        .bubble:nth-child(7) {
            width: 90px;
            height: 90px;
            left: 70%;
            animation-duration: 12s;
            animation-delay: 2s;
        }
        
        .bubble:nth-child(8) {
            width: 25px;
            height: 25px;
            left: 80%;
            animation-duration: 6s;
            animation-delay: 2s;
        }
        
        .bubble:nth-child(9) {
            width: 15px;
            height: 15px;
            left: 90%;
            animation-duration: 5s;
            animation-delay: 1s;
        }
        
        .bubble:nth-child(10) {
            width: 50px;
            height: 50px;
            left: 85%;
            animation-duration: 10s;
            animation-delay: 4s;
        }
        
        @keyframes rise {
            0% {
                bottom: -100px;
                transform: translateX(0);
            }
            50% {
                transform: translateX(100px);
            }
            100% {
                bottom: 1080px;
                transform: translateX(-200px);
            }
        }
        
        .login-container {
            width: 100%;
            margin: 30px;
            max-width: 1000px;
            background-color: var(--light);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            display: flex;
            min-height: 600px;
            position: relative;
            z-index: 1;
            animation: fadeIn 1s ease-out;
        }
        

        .login-illustration {
            flex: 1;
            background-color: var(--primary);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .illustration-content {
            position: relative;
            z-index: 2;
            text-align: center;
            animation: float 6s ease-in-out infinite;
        }
        
        .illustration-image {
            width: 80%;
            max-width: 300px;
            margin-bottom: 30px;
            filter: drop-shadow(0 10px 15px rgba(0, 0, 0, 0.1));
        }
        
        .illustration-title {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .illustration-text {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .illustration-features {
            display: flex;
            flex-direction: column;
            gap: 15px;
            text-align: left;
            width: 100%;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
            font-size: 0.95rem;
            animation: fadeInRight 0.5s ease-out;
            animation-fill-mode: both;
        }
        
        .feature-item:nth-child(1) {
            animation-delay: 0.3s;
        }
        
        .feature-item:nth-child(2) {
            animation-delay: 0.6s;
        }
        
        .feature-item:nth-child(3) {
            animation-delay: 0.9s;
        }
        
        .feature-icon {
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .feature-text {
            flex: 1;
        }
        
        .waves {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100px;
            background-image: url('data:image/svg+xml;utf8,<svg viewBox="0 0 1200 120" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none"><path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25" fill="%23FFFFFF"/><path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5" fill="%23FFFFFF"/><path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z" fill="%23FFFFFF"/></svg>');
            background-size: cover;
            background-repeat: no-repeat;
        }
        

        .login-form-container {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
            animation: fadeInDown 0.8s ease-out;
        }
        
        .login-logo {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }
        
        .login-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .login-subtitle {
            font-size: 1rem;
            color: #777;
            margin-bottom: 0;
        }
        
        .login-form {
            animation: fadeInUp 0.8s ease-out;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-control {
            height: 55px;
            padding: 10px 20px 10px 50px;
            font-size: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 30px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            border-radius: 30px;
            box-shadow: 0 0 0 0.2rem rgba(66, 195, 207, 0.25);
            transform: translateX(-3px);
          
        }
        
        .form-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
            font-size: 1.2rem;
            transition: var(--transition);
        }
        
        .form-control:focus + .form-icon {
            color: var(--primary);
        }
        
        /* Password Toggle Icon */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #adb5bd;
            font-size: 1.2rem;
            cursor: pointer;
            transition: var(--transition);
            background: none;
            border: none;
            padding: 0;
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        /* Remember Me Checkbox */
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .form-check-label {
            font-size: 0.9rem;
            color: #777;
            cursor: pointer;
        }
        
        .error-message {
            color: var(--danger);
            font-size: 0.9rem;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: shake 0.5s ease-in-out;
        }
        
        .btn-login {
            height: 55px;
            background-color: var(--primary);
            border: none;
            border-radius: 30px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            width: 100%;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(66, 195, 207, 0.3);
        }
        
        .btn-login:after {
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
        
        .btn-login:hover:after {
            animation: ripple 1s ease-out;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 30px;
            animation: fadeIn 1s ease-out;
        }
        
        .login-footer-text {
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 15px;
        }
        
        .login-footer-link {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .login-footer-link:hover {
            color: var(--primary-dark);
            transform: translateX(5px);
        }
        
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
        
        @keyframes fadeInRight {
            from { 
                opacity: 0;
                transform: translateX(-20px);
            }
            to { 
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
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
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
                max-width: 500px;
            }
            
            .login-illustration {
                padding: 30px;
            }
            
            .illustration-title {
                font-size: 1.5rem;
            }
            
            .illustration-text {
                font-size: 0.9rem;
            }
            
            .feature-item {
                font-size: 0.85rem;
            }
            
            .feature-icon {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .login-container {
                margin: 20px;
            }
            
            .login-illustration {
                padding: 20px;
            }
            
            .login-form-container {
                padding: 20px;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
            
            .login-subtitle {
                font-size: 0.9rem;
            }
            
            .illustration-features {
                display: none;
            }
            
            .illustration-image {
                width: 60%;
                margin-bottom: 15px;
            }
            
            .illustration-title {
                font-size: 1.3rem;
                margin-bottom: 10px;
            }
            
            .illustration-text {
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Background Bubbles -->
    <div class="bubbles">
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
        <div class="bubble"></div>
    </div>

    <div class="login-container">
        <div class="login-illustration">
            <div class="illustration-content">
                <img src="../assets/images/gambaradmin.png" alt="Admin Dashboard" class="illustration-image">
                <h2 class="illustration-title">Zeea Laundry</h2>
                <p class="illustration-text">Rapi, Bersih, Wangi</p>
            </div>

        </div>
        

        <div class="login-form-container">
            <div class="login-header">
                <img src="../assets/images/zeea_laundry.png" alt="Zeea Laundry Logo" class="login-logo">
                <h1 class="login-title">Login Admin</h1>
                <p class="login-subtitle">Masuk untuk mengelola Zeea Laundry</p>
            </div>
            
            <form class="login-form" method="POST" action="">
                <?php if (isset($error)): ?>
                <div class="error-message mb-2">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                    <i class="fas fa-user form-icon"></i>
                </div>
                
                <div class="form-group">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                    <i class="fas fa-lock form-icon"></i>
                    <button type="button" class="password-toggle" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label" for="remember">
                        Ingat saya
                    </label>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i> Masuk
                </button>
            </form>
            
            <div class="login-footer">
                <p class="login-footer-text">Bukan admin?</p>
                <a href="../pelanggan/" class="login-footer-link">
                    ke halaman pelanggan <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <script>
        document.querySelector('.btn-login').addEventListener('click', function(e) {
            const x = e.clientX - e.target.getBoundingClientRect().left;
            const y = e.clientY - e.target.getBoundingClientRect().top;
            
            const ripple = document.createElement('span');
            ripple.classList.add('ripple');
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;
            
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });

        // Toggle untuk liat password
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    </script>
</body>
</html>
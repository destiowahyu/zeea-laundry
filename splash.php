<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit;
}

// Redirect based on role
$redirect = '';
switch ($_SESSION['role']) {
    case 'admin':
        $redirect = 'admin/';
        break;
    default:
        $redirect = 'index.php';
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loading...</title>
    <link rel="icon" type="image/png" href="assets/images/loading.png">
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background:#eaf6f8;
            font-family: Arial, sans-serif;
        }
        .splash-container {
            text-align: center;
            animation: fadeIn 0.7s ease-in;
        }
        .logo {
            width: 250px;
            margin-bottom: 20px;
            animation: zoomIn 1s ease-in;
        }
        .author {
            font-size: 15px;
            color:#1e1e1e;
            margin-top: 20px;
            font-weight: bold;
        }
        .loading {
            width: 50px;
            height: 50px;

            border-top: 5px solid  #42c3cf;
            border-radius: 50%;
            animation: spin 0.3s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes fadeIn {
            from { opacity: 0.5; }
            to { opacity: 1; }
        }
        @keyframes zoomIn {
            from { transform: scale(0.9); }
            to { transform: scale(2.9); }
        }
        @media (max-width: 768px) {
            .logo {
                width: 50vw;
            }
        }
    </style>
</head>
<body>
    <div class="splash-container">
        <img src="assets/images/zeea_laundry.png" alt="Logo" class="logo">
        <div class="loading"></div>
        <div class="author">© Destio Wahyu. All Rights Reserved.</div>
    </div>
    <script>
        setTimeout(function() {
            window.location.href = '<?php echo $redirect; ?>';
        }, 500);
    </script>
</body>
</html>


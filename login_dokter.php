<?php
session_start();
require 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // 1. Deteksi SQL Injection
    $blacklist_patterns = [
        "/('|--|;|`)/",                  // Karakter berbahaya
        "/(union|select|insert|delete|update|drop|alter)/i", // Keyword SQL Injection
        "/(\bor\b|\band\b)/i",          // Keyword 'OR' atau 'AND'
    ];

    foreach ($blacklist_patterns as $pattern) {
        if (preg_match($pattern, $username) || preg_match($pattern, $password)) {
            header("Location: sqlinjection.php");
            exit;
        }
    }

    // 2. Validasi username
    // Hanya huruf, angka, underscore (_), dan tanda hubung (-)
    if (!preg_match("/^[a-zA-Z0-9_-]+$/", $username)) {
        header("Location: sqlinjection.php");
        exit;
    }

    // 3. Validasi panjang input
    $max_length = 50; // Maksimal panjang username dan password
    if (strlen($username) > $max_length || strlen($password) > $max_length) {
        header("Location: sqlinjection.php");
        exit;
    }

    // 4. Logging aktivitas mencurigakan
    function logSuspiciousActivity($username, $reason) {
        $file = 'suspicious.log';
        $log = date("Y-m-d H:i:s") . " - Username: $username - Reason: $reason\n";
        file_put_contents($file, $log, FILE_APPEND);
    }

    // Cek Admin
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
            header("Location: splash.php");
            exit;
        }
    }

    // Cek Dokter
    $query = "SELECT * FROM dokter WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $dokter = $result->fetch_assoc();
        if (password_verify($password, $dokter['password'])) {
            $_SESSION['role'] = 'dokter';
            $_SESSION['username'] = $dokter['username'];
            $_SESSION['id'] = $dokter['id'];
            header("Location: splash.php");
            exit;
        } else {
            $error = "Username atau password salah!";
        }
    } else {
        $error = "Username atau password salah!";
    }

    // Jika username dan password salah
    $error = "Username atau password salah!";
    
}
?>


<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link href="https://fonts.googleapis.com/css?family=Roboto:300,400&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/doctor-login.png">

    <link rel="stylesheet" href="assets/login/fonts/icomoon/style.css">
    <link rel="stylesheet" href="assets/login/css/owl.carousel.min.css">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="assets/login/css/bootstrap.min.css">
    
    <!-- Style -->
    <link rel="stylesheet" href="assets/login/css/style.css">

    <title>Login Dokter</title>
    <style>
      .login-pasien-link {
        color: #42c3cf;
        font-size: 14px;
        font-weight: bold;
        display: block;
        text-align: center;
        margin-top: 15px;
        transition: color 0.3s ease;
      }

      .login-pasien-link:hover {
        color: #35b5bf;
      }

      .doctor-image {
        min-width: 60%;
        max-width: 60%;
        margin: auto;
        display: block;
        text-align: center;
        align-items: center;
        justify-content: center;
      }
    </style>
  </head>
  <body>
    <div class="content">
      <div style="max-width: 70%;" class="container">
        <div class="row">
            <div class="col-md-5 d-flex flex-column justify-content-center align-items-center p-4">
                <img src="assets/login/images/doctor.svg" alt="Image" class="img-fluid doctor-image">
            </div>
          <div class="col-md-6 contents">

                <div class="mb-4">
                <h3><span><strong style="color: #42c3cf;">Login Dokter & Admin</strong></span></h3>
                  <p class="mb-4">Silahkan masukkan username & password dibawah :</p>
                </div>
                <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
                <form method="POST" action="">

                  <div class="form-group first">
                    <label for="username"></label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                  </div>

                  <div class="form-group last mb-4">
                    <label for="password"></label>
                    <input type="password" name="password" class="form-control" id="password" placeholder="Password" required class="form-control">
                  </div>

                  <input type="submit" value="Login" class="btn btn-login">
                </form>

                <!-- Teks Login Pasien -->
                <a href="login_pasien.php" class="login-pasien-link">Bukan Dokter? Login sebagai Pasien</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="js/jquery-3.3.1.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/main.js"></script>
  </body>
</html>


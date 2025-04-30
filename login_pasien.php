<?php
session_start();
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Query untuk memeriksa username dan password
    $query = "SELECT id, username, password FROM pasien WHERE username = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // Jika password yang disimpan menggunakan MD5, periksa dengan MD5 terlebih dahulu
        if (strlen($row['password']) === 32) { // MD5 panjangnya 32 karakter
            if (md5($password) === $row['password']) {
                // Jika cocok, ganti password dengan hash yang lebih aman
                $new_hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $updateQuery = "UPDATE pasien SET password = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("si", $new_hashed_password, $row['id']);
                $updateStmt->execute();

                // Set session dan arahkan ke halaman splash
                $_SESSION['role'] = 'pasien';
                $_SESSION['username'] = $row['username'];
                $_SESSION['id'] = $row['id'];
                header("Location: splash.php");
                exit;
            } else {
                $error = "Username atau password salah.";
            }
        } else {
            // Jika password sudah menggunakan hash yang aman, verifikasi dengan password_verify
            if (password_verify($password, $row['password'])) {
                $_SESSION['role'] = 'pasien';
                $_SESSION['username'] = $row['username'];
                $_SESSION['id'] = $row['id'];
                header("Location: splash.php");
                exit;
            } else {
                $error = "Username atau password salah.";
            }
        }
    } else {
        $error = "Username atau password salah.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Pasien</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/loginregisterpasien/styles.css">
    <link rel="icon" type="image/png" href="assets/images/patient-icon.png">
</head>
<body>
    <div class="container d-flex justify-content-center align-items-center min-vh-100">
        <div class="row" style="margin: 5%;">
            <div style="max-width: 350px;" class="col d-flex flex-column justify-content-center align-items-center">
                <img src="assets/images/patient-icon.png" alt="Logo" style="width: 100%;">
            </div>
            <div class="col-md-7 p-2">
                <h2 class="mb-4 text-green">Login Pasien</h2>
                <p class="mb-4">Silahkan masukkan username & password Anda:</p>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <button type="submit" style="border-radius: 30px;" class="btn btn-primary w-100 py-3 mt-2">Login</button>
                </form>
                <div class="text-center mt-3">
                    <a href="registrasi_pasien.php" class="registerpasien">Belum punya akun? Registrasi di sini</a><br>
                    <a href="login_dokter.php" class="logindokter">Login sebagai dokter</a>
                </div>
            </div>
            <div class="permanent-text">
            </div>
        </div>
    </div>
</body>
</html>


<?php
session_start();
include 'includes/db.php';

function generateNoRM($conn) {
    $yearMonth = date('Ym');
    $query = "SELECT COUNT(*) AS total FROM pasien WHERE no_rm LIKE ?";
    $likePattern = $yearMonth . "%";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $newNumber = $row['total'] + 1;
    return $yearMonth . '-' . $newNumber;
}

$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'];
    $alamat = $_POST['alamat'];
    $no_ktp = $_POST['no_ktp'];
    $no_hp = $_POST['no_hp'];
    $username = $_POST['username'];
    $password = $_POST['password']; // Simpan password secara langsung
    $no_rm = generateNoRM($conn);

    // First check KTP
    $check_ktp_query = "SELECT * FROM pasien WHERE no_ktp = ?";
    $check_ktp_stmt = $conn->prepare($check_ktp_query);
    $check_ktp_stmt->bind_param("s", $no_ktp);
    $check_ktp_stmt->execute();
    $check_ktp_result = $check_ktp_stmt->get_result();

    if ($check_ktp_result->num_rows > 0) {
        $error = "Nomor KTP sudah terdaftar.";
    } else {
        // Then check username if KTP is unique
        $check_username_query = "SELECT * FROM pasien WHERE username = ?";
        $check_username_stmt = $conn->prepare($check_username_query);
        $check_username_stmt->bind_param("s", $username);
        $check_username_stmt->execute();
        $check_username_result = $check_username_stmt->get_result();

        if ($check_username_result->num_rows > 0) {
            $error = "Username sudah terdaftar.";
        } else {
            // Hash password using password_hash (bcrypt by default)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // If both KTP and username are unique, proceed with registration
            $query = "INSERT INTO pasien (nama, alamat, no_ktp, no_hp, no_rm, username, password) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sssssss", $nama, $alamat, $no_ktp, $no_hp, $no_rm, $username, $hashed_password);

            if ($stmt->execute()) {
                $success = true;
            } else {
                $error = "Terjadi kesalahan saat registrasi.";
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Pasien</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/loginregisterpasien/styles.css">
    <link rel="icon" type="image/png" href="assets/images/pasien.png">
    <style>
        .min-vh-100 {
            min-height: 100vh !important;
            padding: 2rem 0;
        }
        
        .form-container {
            width: 77%;
            margin: auto;
        }
        
        @media (max-height: 800px) {
            .min-vh-100 {
                height: auto !important;
                min-height: auto !important;
            }
        }
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
        }
        .modal-content {
            border: none;
            border-radius: 8px;
        }
        .readonly-input {
            background-color: #f0f0f0; 
            color: #6c757d; 
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<div class="container justify-content-center align-items-center" style="max-width:900px;">
        <div class="row justify-content-center align-items-center" style="margin:5%;">
            <div class="col justify-content-center align-items-center">
                <h2 class="mb-4 text-green">Registrasi Pasien</h2>
                <p class="mb-4">Silahkan isi data diri Anda:</p>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label for="no_rm" class="form-label" disabled>Nomor RM</label>
                        <input type="text" id="no_rm" class="form-control readonly-input" value="<?php echo generateNoRM($conn); ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="nama" class="form-label">Nama Lengkap</label>
                        <input type="text" name="nama" id="nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="alamat" class="form-label">Alamat</label>
                        <textarea name="alamat" id="alamat" style="resize: none; height:100px;" class="form-control"required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="no_ktp" class="form-label">Nomor KTP</label>
                        <input type="text" name="no_ktp" id="no_ktp" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="no_hp" class="form-label">Nomor HP</label>
                        <input type="text" name="no_hp" id="no_hp" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                    </div>
                    <button type="submit" style="border-radius: 30px;" class="btn btn-green w-100 py-3 mt-3">Registrasi</button>
                </form>
                <div class="text-center mt-3">
                    <a href="login_pasien.php" class="registerpasien">Sudah punya akun? Login di sini</a>
                </div>
            </div>
            <div class="permanent-text"></div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div class="mb-3">
                        <svg width="100" height="100" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="48" fill="none" stroke="#4CAF50" stroke-width="4"/>
                            <path d="M30 50 L45 65 L70 35" fill="none" stroke="#4CAF50" stroke-width="4"/>
                        </svg>
                    </div>
                    <h4 class="mb-3">Regristrasi Pasien Berhasil!</h4>
                    <button type="button" class="btn btn-success px-4" onclick="redirectToLogin()">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php if ($success): ?>
            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
            <?php endif; ?>
        });

        function redirectToLogin() {
            window.location.href = 'login_pasien.php';
        }
    </script>
</body>
</html>


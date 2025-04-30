<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pasien') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
include '../includes/db.php';

// Ambil data pasien dari sesi
$pasienName = $_SESSION['username'];
$pasienData = $conn->query("SELECT * FROM pasien WHERE username = '$pasienName'")->fetch_assoc();

// Proses form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama = $conn->real_escape_string($_POST['nama']);
    $username = $conn->real_escape_string($_POST['username']);
    $no_ktp = $conn->real_escape_string($_POST['no_ktp']);
    $alamat = $conn->real_escape_string($_POST['alamat']);
    $no_hp = $conn->real_escape_string($_POST['no_hp']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $errors = [];

    // Validasi password
    if (!empty($password)) {
        if ($password !== $confirm_password) {
            $errors[] = "Password dan konfirmasi password tidak cocok.";
        } else {
            $password = md5($password);
        }
    } else {
        $password = $pasienData['password']; // Gunakan password lama jika tidak diubah
    }

    if (empty($errors)) {
        $updateQuery = "UPDATE pasien SET 
                        nama = '$nama', 
                        username = '$username',
                        no_ktp = '$no_ktp',
                        alamat = '$alamat', 
                        no_hp = '$no_hp', 
                        password = '$password' 
                        WHERE username = '$pasienName'";

        if ($conn->query($updateQuery) === TRUE) {
            $successMessage = "Profil berhasil diperbarui!";
            // Refresh pasien data
            $pasienData = $conn->query("SELECT * FROM pasien WHERE username = '$username'")->fetch_assoc();
            $_SESSION['username'] = $username; // Update session jika username berubah
        } else {
            $errors[] = "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Pasien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/pasien.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-HoAqzM0Ll3xdCEaOfhccTd36SpzvoD6B0T3OOcDjfGgDkXp24FdQYvpB3nsTmFCy" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .password-container {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 74%;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 10;
        }
        .profile-info {
            margin-bottom: 1.5rem;
        }
        .profile-info label {
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .profile-info p {
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .edit-button {
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <?php include 'sidebar_pasien.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <h1 class="mb-4">Profil Pasien</h1>
                    <div class="card-profildokter">
                        <div class="card-body">
                    <?php
                    if (isset($successMessage)) {
                        echo "<div class='alert alert-success'>$successMessage</div>";
                    }
                    if (!empty($errors)) {
                        foreach ($errors as $error) {
                            echo "<div class='alert alert-danger'>$error</div>";
                        }
                    }
                    ?>
                    <!-- View Profile -->
                    <div class="profile-info">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <label>No. RM</label>
                                <p><?= htmlspecialchars($pasienData['no_rm']) ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label>Username</label>
                                <p><?= htmlspecialchars($pasienData['username']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <label>Nama Lengkap</label>
                                <p><?= htmlspecialchars($pasienData['nama']) ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label>No. KTP</label>
                                <p><?= htmlspecialchars($pasienData['no_ktp']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <label>No. HP</label>
                                <p><?= htmlspecialchars($pasienData['no_hp']) ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <label>Alamat</label>
                                <p><?= htmlspecialchars($pasienData['alamat']) ?></p>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary py-2" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="fas fa-user-edit"></i> Edit Profil
                    </button>
                    
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="editProfileForm">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">No RM</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($pasienData['no_rm']) ?>" disabled>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($pasienData['username']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="nama" class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($pasienData['nama']) ?>" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="no_ktp" class="form-label">No KTP</label>
                                <input type="text" class="form-control" id="no_ktp" name="no_ktp" value="<?= htmlspecialchars($pasienData['no_ktp']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="no_hp" class="form-label">No HP</label>
                                <input type="text" class="form-control" id="no_hp" name="no_hp" value="<?= htmlspecialchars($pasienData['no_hp']) ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3" required><?= htmlspecialchars($pasienData['alamat']) ?></textarea>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="password-container">
                                    <label for="password" class="form-label">Password Baru (Kosongi jika tidak ingin merubah)</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <i class="fas fa-eye password-toggle" onclick="togglePasswordVisibility('password')"></i>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="password-container">
                                    <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                    <i class="fas fa-eye password-toggle" onclick="togglePasswordVisibility('confirm_password')"></i>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
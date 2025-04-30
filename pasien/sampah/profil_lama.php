<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pasien') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';

// Fetch patient's current data
$username = $_SESSION['username'];
$query = "SELECT * FROM pasien WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$pasien = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = array();
    
    // Get form data
    $nama = $_POST['nama'];
    $alamat = $_POST['alamat'];
    $no_ktp = $_POST['no_ktp'];
    $no_hp = $_POST['no_hp'];
    $new_username = $_POST['username'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Check if username is being changed
        if ($new_username !== $username) {
            $check_username = "SELECT id FROM pasien WHERE username = ? AND id != ?";
            $check_stmt = $conn->prepare($check_username);
            $check_stmt->bind_param("si", $new_username, $pasien['id']);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                throw new Exception('Username sudah digunakan');
            }
        }

        // Check if no_ktp is being changed and if it's unique
        if ($no_ktp !== $pasien['no_ktp']) {
            $check_ktp = "SELECT id FROM pasien WHERE no_ktp = ? AND id != ?";
            $check_ktp_stmt = $conn->prepare($check_ktp);
            $check_ktp_stmt->bind_param("si", $no_ktp, $pasien['id']);
            $check_ktp_stmt->execute();
            if ($check_ktp_stmt->get_result()->num_rows > 0) {
                throw new Exception('Nomor KTP sudah terdaftar');
            }
        }

        // Prepare update query
        $update_query = "UPDATE pasien SET nama = ?, alamat = ?, no_ktp = ?, no_hp = ?, username = ?";
        $param_types = "sssss";
        $param_values = [$nama, $alamat, $no_ktp, $no_hp, $new_username];

        // Add password update if provided
        if (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                throw new Exception('Password baru dan konfirmasi tidak cocok');
            }
            $update_query .= ", password = ?";
            $param_types .= "s";
            $param_values[] = md5($new_password);
        }

        $update_query .= " WHERE id = ?";
        $param_types .= "i";
        $param_values[] = $pasien['id'];

        $stmt = $conn->prepare($update_query);
        $stmt->bind_param($param_types, ...$param_values);

        if (!$stmt->execute()) {
            throw new Exception('Gagal memperbarui profil: ' . $stmt->error);
        }

        // Commit transaction
        $conn->commit();

        // Update session if username was changed
        if ($new_username !== $username) {
            $_SESSION['username'] = $new_username;
        }

        echo json_encode(['status' => 'success', 'message' => 'Profil berhasil diperbarui']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pasien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="icon" type="image/png" href="../assets/images/avatar-patient.png">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar" id="sidebar">
        <div class="avatar-container">
            <h4 id="admin-panel">Pasien Panel</h4>
            <img src="../assets/images/avatar-patient.png" class="admin-avatar" alt="Pasien">
            <h6 id="admin-name"><?= htmlspecialchars($username) ?></h6>
        </div>
        <a href="dashboard_pasien.php" class="<?php echo ($current_page == 'dashboard_pasien.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        <a href="daftar_poli.php" class="<?php echo ($current_page == 'daftar_poli.php') ? 'active' : ''; ?>">
            <i class="fas fa-stethoscope"></i> <span>Daftar Poli</span>
        </a>
        <a href="riwayat_periksa.php" class="<?php echo ($current_page == 'riwayat_periksa.php') ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> <span>Riwayat Periksa</span>
        </a>
        <a href="profil_pasien.php" class="<?php echo ($current_page == 'profil_pasien.php') ? 'active' : ''; ?>">
            <i class="fas fa-user"></i> <span>Profil</span>
        </a>
        <a href="../logout.php" class="<?php echo ($current_page == 'logout.php') ? 'active' : ''; ?>">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <h1 class="mb-4">Profil Pasien</h1>
            <div class="card-profilpasien">
                <div class="card-body">
                    <form id="profileForm" method="POST">
                        <div class="mb-3">
                            <label for="nama" class="form-label"><span><strong style="color: #42c3cf;">Nama Lengkap</strong></span></label>
                            <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($pasien['nama']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="alamat" class="form-label"><span><strong style="color: #42c3cf;">Alamat</strong></span></label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3" required><?= htmlspecialchars($pasien['alamat']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="no_ktp" class="form-label"><span><strong style="color: #42c3cf;">No. KTP</strong></span></label>
                            <input type="text" class="form-control" id="no_ktp" name="no_ktp" value="<?= htmlspecialchars($pasien['no_ktp']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="no_hp" class="form-label"><span><strong style="color: #42c3cf;">No. HP</strong></span></label>
                            <input type="tel" class="form-control" id="no_hp" name="no_hp" value="<?= htmlspecialchars($pasien['no_hp']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label"><span><strong style="color: #42c3cf;">Username</strong></span></label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($pasien['username']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label"><span><strong style="color: #42c3cf;">Password Baru (Kosongkan jika tidak ingin mengubah)</strong></span></label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label"><span><strong style="color: #42c3cf;">Konfirmasi Password Baru</strong></span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const content = document.getElementById('content');

        if (window.innerWidth > 768) {
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('collapsed');
        } else {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('show');
        }
    }

    $(document).ready(function() {
        $('#profileForm').on('submit', function(e) {
            e.preventDefault();

            $.ajax({
                url: 'profil_pasien.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: response.message,
                            showConfirmButton: false,
                            timer: 2000
                        }).then(() => {
                            // Reload the page to reflect changes
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message,
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error(xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Terjadi kesalahan. Silakan coba lagi.',
                        confirmButtonText: 'OK'
                    });
                }
            });
        });
    });
    </script>
</body>
</html>


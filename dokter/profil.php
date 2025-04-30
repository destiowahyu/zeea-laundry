<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dokter') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';

// Fetch doctor's current data
$username = $_SESSION['username'];
$query = "SELECT * FROM dokter WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$dokter = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = array();
    
    // Get form data
    $nama = $_POST['nama'];
    $alamat = $_POST['alamat'];
    $no_hp = $_POST['no_hp'];
    $new_username = $_POST['username'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Start building the update query
    $update_fields = array();
    $param_types = "";
    $param_values = array();

    // Add basic fields
    $update_fields[] = "nama = ?";
    $param_types .= "s";
    $param_values[] = $nama;

    $update_fields[] = "alamat = ?";
    $param_types .= "s";
    $param_values[] = $alamat;

    $update_fields[] = "no_hp = ?";
    $param_types .= "s";
    $param_values[] = $no_hp;

    // Check if username is being changed
    if ($new_username !== $username) {
        // Check if new username already exists
        $check_username = "SELECT id FROM dokter WHERE username = ? AND username != ?";
        $check_stmt = $conn->prepare($check_username);
        $check_stmt->bind_param("ss", $new_username, $username);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Username sudah digunakan']);
            exit;
        }
        $update_fields[] = "username = ?";
        $param_types .= "s";
        $param_values[] = $new_username;
    }

    // Check if password is being changed
    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            echo json_encode(['status' => 'error', 'message' => 'Password baru dan konfirmasi tidak cocok']);
            exit;
        }
        $hashed_password = md5($new_password);
        $update_fields[] = "password = ?";
        $param_types .= "s";
        $param_values[] = $hashed_password;
    }

    // Build and execute the update query
    $query = "UPDATE dokter SET " . implode(", ", $update_fields) . " WHERE username = ?";
    $param_types .= "s";
    $param_values[] = $username;

    $stmt = $conn->prepare($query);
    $stmt->bind_param($param_types, ...$param_values);

    if ($stmt->execute()) {
        // Update session if username was changed
        if ($new_username !== $username) {
            $_SESSION['username'] = $new_username;
        }
        echo json_encode(['status' => 'success', 'message' => 'Profil berhasil diperbarui']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal memperbarui profil']);
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
    <title>Profil Dokter</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/avatar-doctor.png">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <style>
        .password-container {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
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
    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <?php include 'sidebar_dokter.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <h1 class="mb-4">Profil Dokter</h1>
            <div class="card-profildokter">
                <div class="card-body">
                    <!-- View Profile -->
                    <div class="profile-info">
                        <div class="row">
                            <div class="col-md-6">
                                <label>Nama Lengkap</label>
                                <p><?= htmlspecialchars($dokter['nama']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <label>Username</label>
                                <p><?= htmlspecialchars($dokter['username']) ?></p>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label>Alamat</label>
                                <p><?= htmlspecialchars($dokter['alamat']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <label>No. HP</label>
                                <p><?= htmlspecialchars($dokter['no_hp']) ?></p>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary" style="border-radius: 30px; padding: 10px 20px;" data-bs-toggle="modal" data-bs-target="#editProfileModal">
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
                    <form id="profileForm" method="POST">
                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama" name="nama" value="<?= htmlspecialchars($dokter['nama']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="alamat" class="form-label">Alamat</label>
                            <textarea class="form-control" id="alamat" name="alamat" rows="3" required><?= htmlspecialchars($dokter['alamat']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="no_hp" class="form-label">No. HP</label>
                            <input type="tel" class="form-control" id="no_hp" name="no_hp" value="<?= htmlspecialchars($dokter['no_hp']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($dokter['username']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">Password Baru (Kosongkan jika tidak ingin mengubah)</label>
                            <div class="password-container">
                                <input type="password" class="form-control" id="new_password" name="new_password">
                                <i class="fas fa-eye password-toggle" onclick="togglePasswordVisibility('new_password')"></i>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                            <div class="password-container">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                <i class="fas fa-eye password-toggle" onclick="togglePasswordVisibility('confirm_password')"></i>
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

    <script>

    function togglePasswordVisibility(fieldId) {
        const passwordField = document.getElementById(fieldId);
        const icon = passwordField.nextElementSibling;
        
        if (passwordField.type === "password") {
            passwordField.type = "text";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        } else {
            passwordField.type = "password";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        }
    }

    $(document).ready(function() {
        // Form submission handling
        $('#profileForm').on('submit', function(e) {
            e.preventDefault();

            // Basic validation
            const newPassword = $('#new_password').val();
            const confirmPassword = $('#confirm_password').val();

            if (newPassword !== confirmPassword) {
                showNotification('Password baru dan konfirmasi tidak cocok', 'danger');
                return;
            }

            // Submit form via AJAX
            $.ajax({
                url: 'profil.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showNotification(response.message, 'success');
                        if ($('#username').val() !== '<?= $username ?>') {
                            $('#admin-name').text($('#username').val());
                        }
                        $('#new_password, #confirm_password').val('');
                        updateProfileDisplay();
                        $('#editProfileModal').modal('hide');
                    } else {
                        showNotification(response.message, 'error');
                    }
                },
                error: function() {
                    showNotification('Terjadi kesalahan. Silakan coba lagi.', 'error');
                }
            });
        });

        function showNotification(message, type = 'success') {
            Swal.fire({
                icon: type,
                title: message,
                showConfirmButton: false,
                timer: 1500
            });
        }

        function updateProfileDisplay() {
            $('.profile-info p').eq(0).text($('#nama').val());
            $('.profile-info p').eq(1).text($('#username').val());
            $('.profile-info p').eq(2).text($('#alamat').val());
            $('.profile-info p').eq(3).text($('#no_hp').val());
        }
    });
    </script>
</body>
</html>


<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
include '../includes/db.php';

$adminName = $_SESSION['username'];

// Handle messages for notifications
$message = '';
$type = '';




// TAMBAH DATA ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $current_admin_password = $_POST['current_admin_password'];
        
        // Verify current admin's password
        $admin_username = $_SESSION['username'];
        $verify_admin = $conn->prepare("SELECT password FROM admin WHERE username = ?");
        $verify_admin->bind_param("s", $admin_username);
        $verify_admin->execute();
        $verify_admin->store_result();
        $verify_admin->bind_result($hashed_current_password);
        $verify_admin->fetch();

        if (!$verify_admin->num_rows || !password_verify($current_admin_password, $hashed_current_password)) {
            $message = "Password admin saat ini salah!";
            $type = "error";
        } else if ($password !== $confirm_password) {
            $message = "Password dan konfirmasi password tidak cocok!";
            $type = "error";
        } else {
            // Cek apakah username sudah ada di database
            $cekUsername = $conn->prepare("SELECT id FROM admin WHERE BINARY username = ?");
            $cekUsername->bind_param("s", $username);
            $cekUsername->execute();
            $cekUsername->store_result();

            if ($cekUsername->num_rows > 0) {
                $message = "Username sudah digunakan!";
                $type = "error";
            } else {
                // Jika username belum ada, tambahkan data admin
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $username, $hashed_password);

                if ($stmt->execute()) {
                    $message = "Admin berhasil ditambahkan!";
                    $type = "success";
                } else {
                    $message = "Gagal menambahkan admin: " . $conn->error;
                    $type = "error";
                }
            }
        }
    }

    // EDIT DATA ADMIN
    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $username = $_POST['username'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $current_admin_password = $_POST['current_admin_password'];

        // Verify current admin's password
        $admin_username = $_SESSION['username'];
        $verify_admin = $conn->prepare("SELECT password FROM admin WHERE BINARY username = ?");
        $verify_admin->bind_param("s", $admin_username);
        $verify_admin->execute();
        $verify_admin->store_result();
        $verify_admin->bind_result($hashed_current_password);
        $verify_admin->fetch();

        if (!$verify_admin->num_rows || !password_verify($current_admin_password, $hashed_current_password)) {
            $message = "Password admin saat ini salah!";
            $type = "error";
        } else if (!empty($password) && $password !== $confirm_password) {
            $message = "Password dan konfirmasi password tidak cocok!";
            $type = "error";
        } else {
            // Cek apakah username sudah ada di database dan bukan milik admin ini
            $cekUsername = $conn->prepare("SELECT id FROM admin WHERE username = ? AND id != ?");
            $cekUsername->bind_param("si", $username, $id);
            $cekUsername->execute();
            $cekUsername->store_result();

            if ($cekUsername->num_rows > 0) {
                $message = "Username sudah digunakan oleh admin lain!";
                $type = "error";
            } else {
                // Jika username belum digunakan oleh admin lain, lanjutkan update
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE admin SET username=?, password=? WHERE id=?");
                    $stmt->bind_param("ssi", $username, $hashed_password, $id);
                } else {
                    $stmt = $conn->prepare("UPDATE admin SET username=? WHERE id=?");
                    $stmt->bind_param("si", $username, $id);
                }

                if ($stmt->execute()) {
                    $message = "Data admin berhasil diperbarui!";
                    $type = "success";
                } else {
                    $message = "Gagal memperbarui data admin: " . $conn->error;
                    $type = "error";
                }
            }
        }
    }

    // DELETE ADMIN
    if (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $current_admin_password = $_POST['current_admin_password'];

        // Verify current admin's password
        $admin_username = $_SESSION['username'];
        $verify_admin = $conn->prepare("SELECT password FROM admin WHERE username = ?");
        $verify_admin->bind_param("s", $admin_username);
        $verify_admin->execute();
        $verify_admin->store_result();
        $verify_admin->bind_result($hashed_current_password);
        $verify_admin->fetch();

        if (!$verify_admin->num_rows || !password_verify($current_admin_password, $hashed_current_password)) {
            $message = "Password admin saat ini salah!";
            $type = "error";
        } else {
            $stmt = $conn->prepare("DELETE FROM admin WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "Admin berhasil dihapus!";
                $type = "success";
            } else {
                $message = "Gagal menghapus admin: " . $conn->error;
                $type = "error";
            }
        }
    }
}

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$query = "SELECT * FROM admin WHERE username LIKE ?";
$stmt = $conn->prepare($query);
$searchParam = "%$search%";
$stmt->bind_param("s", $searchParam);
$stmt->execute();
$adminList = $stmt->get_result();

if (!$adminList) {
    die("Query gagal: " . $conn->error);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Admin - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="icon" type="image/png" href="../assets/images/admin.png">
    <style>
        .password-container {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
        }
    </style>
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
            <h4 id="admin-panel">Admin Panel</h4>
            <img src="../assets/images/admin.png" class="admin-avatar" alt="Admin">
            <h6 id="admin-name"><?= htmlspecialchars($adminName) ?></h6>
        </div>
        <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i> <span>Dashboard</span>
        </a>
        <a href="kelola_dokter.php" class="<?php echo ($current_page == 'kelola_dokter.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-md"></i> <span>Kelola Dokter</span>
        </a>
        <a href="kelola_pasien.php" class="<?php echo ($current_page == 'kelola_pasien.php') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> <span>Kelola Pasien</span>
        </a>
        <a href="kelola_poli.php" class="<?php echo ($current_page == 'kelola_poli.php') ? 'active' : ''; ?>">
            <i class="fas fa-hospital"></i> <span>Kelola Poli</span>
        </a>
        <a href="kelola_obat.php" class="<?php echo ($current_page == 'kelola_obat.php') ? 'active' : ''; ?>">
            <i class="fas fa-pills"></i> <span>Kelola Obat</span>
        </a>
        <a href="kelola_admin.php" class="<?php echo ($current_page == 'kelola_admin.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i> <span>Kelola Admin</span>
        </a>
        <a href="../logout.php" class="<?php echo ($current_page == 'logout.php') ? 'active' : ''; ?>">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="header">
            <div class="container">
                <h1 class="mb-4">Kelola Admin</h1>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="d-flex">
                        <input type="text" id="searchInput" class="form-control me-2" placeholder="Cari Admin..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addAdminModal"><i class="fas fa-user-plus"></i> Tambah Admin</button>

                <!-- ISI TABEL ADMIN -->
                <table class="table-kelola-admin table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Admin</th>
                            <th>Username</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="adminTableBody">
                        <?php 
                        $no = 1;
                        while ($row = $adminList->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td>
                                    <div class="tombol-aksi">
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editAdminModal<?= $row['id'] ?>">Edit</button>
                                        <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal" data-admin-id="<?= $row['id'] ?>">Hapus</button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editAdminModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form method="POST">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Admin</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Username</label>
                                                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($row['username']) ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Password Baru (Kosongkan jika tidak ingin diubah)</label>
                                                    <div class="password-container">
                                                        <input type="password" name="password" class="form-control">
                                                        <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Konfirmasi Password Baru</label>
                                                    <div class="password-container">
                                                        <input type="password" name="confirm_password" class="form-control">
                                                        <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Password Admin Saat Ini</label>
                                                    <div class="password-container">
                                                        <input type="password" name="current_admin_password" class="form-control" required>
                                                        <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="edit" class="btn btn-warning">Simpan</button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add Modal -->
            <div class="modal fade" id="addAdminModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Tambah Admin</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <div class="password-container">
                                        <input type="password" name="password" class="form-control" required>
                                        <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Konfirmasi Password</label>
                                    <div class="password-container">
                                        <input type="password" name="confirm_password" class="form-control" required>
                                        <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Password Admin Saat Ini</label>
                                    <div class="password-container">
                                        <input type="password" name="current_admin_password" class="form-control" required>
                                        <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="add" class="btn btn-primary">Simpan</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Confirmation Modal -->
            <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="id" id="deleteAdminId">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Konfirmasi Hapus Admin</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Apakah Anda yakin ingin menghapus admin ini?</p>
                                <div class="mb-3">
                                    <label class="form-label">Password Admin Saat Ini</label>
                                    <div class="password-container">
                                        <input type="password" name="current_admin_password" class="form-control" required>
                                        <i class="fas fa-eye password-toggle" onclick="togglePassword(this)"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" name="delete" class="btn btn-danger">Hapus</button>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- SweetAlert Notification -->
            <?php if ($message): ?>
            <script>
                Swal.fire({
                    icon: '<?= $type ?>',
                    title: '<?= $message ?>',
                    showConfirmButton: false,
                    timer: 1500
                });
            </script>
            <?php endif; ?>

            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

            <script>
                // SIDEBAR BAWAAN JANGAN DIHAPUS
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

                // Default sidebar state on load
                document.addEventListener('DOMContentLoaded', function () {
                    const sidebar = document.getElementById('sidebar');
                    if (window.innerWidth > 768) {
                        sidebar.classList.remove('open');
                    } else {
                        sidebar.classList.add('hidden');
                    }
                });

                // Realtime search
                document.getElementById('searchInput').addEventListener('input', function() {
                    const searchTerm = this.value;
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', 'kelola_admin.php?ajax_search=' + encodeURIComponent(searchTerm), true);
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            document.getElementById('adminTableBody').innerHTML = xhr.responseText;
                        }
                    };
                    xhr.send();
                });

                // Add this new function for password visibility toggle
                function togglePassword(icon) {
                    const input = icon.previousElementSibling;
                    if (input.type === "password") {
                        input.type = "text";
                        icon.classList.remove("fa-eye");
                        icon.classList.add("fa-eye-slash");
                    } else {
                        input.type = "password";
                        icon.classList.remove("fa-eye-slash");
                        icon.classList.add("fa-eye");
                    }
                }

                // Handle delete modal
                const deleteButtons = document.querySelectorAll('button[data-bs-target="#deleteConfirmationModal"]');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', () => {
                        const adminId = button.dataset.adminId;
                        document.getElementById('deleteAdminId').value = adminId;
                    });
                });
            </script>
        </div>
    </div>
</body>
</html>


<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);


include '../includes/db.php';

    // Handle messages for notifications
    $message = '';
    $type = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add'])) {
            $nama = $_POST['nama'];
            $alamat = $_POST['alamat'];
            $no_hp = $_POST['no_hp'];
            $id_poli = $_POST['id_poli'];
            $username = $_POST['username'];
            $password = md5($_POST['password']);

            $conn->query("INSERT INTO dokter (nama, alamat, no_hp, id_poli, username, password) 
                        VALUES ('$nama', '$alamat', '$no_hp', '$id_poli', '$username', '$password')");
            $message = 'Dokter berhasil ditambahkan!';
            $type = 'success';
        }

        if (isset($_POST['edit'])) {
            $id = $_POST['id'];
            $nama = $_POST['nama'];
            $alamat = $_POST['alamat'];
            $no_hp = $_POST['no_hp'];
            $id_poli = $_POST['id_poli'];
            $username = $_POST['username'];

            $conn->query("UPDATE dokter SET nama='$nama', alamat='$alamat', no_hp='$no_hp', 
                        id_poli='$id_poli', username='$username' WHERE id='$id'");
            $message = 'Dokter berhasil diperbarui!';
            $type = 'success';
        }

        if (isset($_POST['delete'])) {
            $id = $_POST['id'];
            $conn->query("DELETE FROM dokter WHERE id='$id'");
            $message = 'Dokter berhasil dihapus!';
            $type = 'success';
        }
    }

        $dokterList = $conn->query("SELECT dokter.*, poli.nama_poli FROM dokter 
                                    LEFT JOIN poli ON dokter.id_poli = poli.id");
        $poliList = $conn->query("SELECT * FROM poli");

        // Handle search
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $dokterList = $conn->query("SELECT dokter.*, poli.nama_poli 
                                    FROM dokter 
                                    LEFT JOIN poli ON dokter.id_poli = poli.id
                                    WHERE dokter.nama LIKE '%$search%'");
        if (!$dokterList) {
            die("Query gagal: " . $conn->error);
        }

        $adminName = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Dokter - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
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
        <h4 id="admin-panel">Admin Panel</h4>
        <img src="../assets/images/admin.png" class="admin-avatar" alt="Admin">
        <h6 id="admin-name"><?= htmlspecialchars($adminName) ?></h6>
    </div>
        <a href="dashboard.php" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-pie"></i></i> <span>Dashboard</span>
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
        <a href="../logout.php" class="<?php echo ($current_page == 'logout.php') ? 'active' : ''; ?>">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>

    <!-- Main Content -->

    <div class="content" id="content">
        <div class="header">

        </div>

                        <div class="container mt-5">
                    <h1 class="mb-4">Kelola Dokter</h1>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <form method="GET" class="d-flex">
                            <input type="text" name="search" class="form-control me-2" placeholder="Cari Dokter..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary">Cari</button>
                        </form>
                    </div>


                    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addDoctorModal">Tambah Dokter</button>




                    <!-- ISI TABEL DOKTER -->
                    <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Dokter</th>
                            <th>Nama</th>
                            <th>Alamat</th>
                            <th>No HP</th>
                            <th>Poli</th>
                            <th>Username</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1; // Inisialisasi nomor urut
                        while ($row = $dokterList->fetch_assoc()): ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= $row['id'] ?></td> 
                                <td><?= $row['nama'] ?></td>
                                <td><?= $row['alamat'] ?></td>
                                <td><?= $row['no_hp'] ?></td>
                                <td><?= $row['nama_poli'] ?></td>
                                <td><?= $row['username'] ?></td>
                                <td>
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editDoctorModal<?= $row['id'] ?>">Edit</button>
                                    <form method="POST" style="display:inline-block;">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="delete" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </td>
                            </tr>

                            <!-- Edit Modal -->
                            <div class="modal fade" id="editDoctorModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <form method="POST">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Dokter</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label">Nama</label>
                                                    <input type="text" name="nama" class="form-control" value="<?= $row['nama'] ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Alamat</label>
                                                    <input type="text" name="alamat" class="form-control" value="<?= $row['alamat'] ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">No HP</label>
                                                    <input type="text" name="no_hp" class="form-control" value="<?= $row['no_hp'] ?>">
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Poli</label>
                                                    <select name="id_poli" class="form-control">
                                                        <?php
                                                        $poliDropdown = $conn->query("SELECT * FROM poli");
                                                        while ($poli = $poliDropdown->fetch_assoc()):
                                                        ?>
                                                            <option value="<?= $poli['id'] ?>" <?= $row['id_poli'] == $poli['id'] ? 'selected' : '' ?>>
                                                                <?= $poli['nama_poli'] ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Username</label>
                                                    <input type="text" name="username" class="form-control" value="<?= $row['username'] ?>">
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
                <div class="modal fade" id="addDoctorModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <form method="POST">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Tambah Dokter</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Nama</label>
                                        <input type="text" name="nama" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Alamat</label>
                                        <input type="text" name="alamat" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">No HP</label>
                                        <input type="text" name="no_hp" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Poli</label>
                                        <select name="id_poli" class="form-control" required>
                                            <?php while ($row = $poliList->fetch_assoc()): ?>
                                                <option value="<?= $row['id'] ?>"><?= $row['nama_poli'] ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" name="username" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="password" class="form-control" required>
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
                </script>

</body>
</html>
 
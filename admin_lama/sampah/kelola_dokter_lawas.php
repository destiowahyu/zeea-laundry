<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

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
        $password = md5($_POST['password']); // Hash password menggunakan MD5

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
    <title>Kolola Dokter - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/admin/styles.css">

</head>
<body>
    <div class="sidebar" id="sidebar">
        <h3>Admin Panel</h3>
        <div class="profile">
        <img src="../assets/images/admin.png" alt="Avatar">
            <span><?= htmlspecialchars($adminName) ?></span>
        </div>
        <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
        <a href="kelola_dokter.php"><i class="fas fa-user-md"></i> Kelola Dokter</a>
        <a href="kelola_pasien.php"><i class="fas fa-user"></i> Kelola Pasien</a>
        <a href="kelola_poli.php"><i class="fas fa-clinic-medical"></i> Kelola Poli</a>
        <a href="kelola_obat.php"><i class="fas fa-pills"></i> Kelola Obat</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="content" id="content">
        <div class="header">
            <button class="toggle-btn" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </button>
            <h1>Kelola Dokter</h1>
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
                                <td><?= $no++ ?></td> <!-- Kolom No -->
                                <td><?= $row['id'] ?></td> <!-- Kolom ID Dokter -->
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
        const toggleBtn = document.getElementById('toggleSidebar');
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');

        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('hidden');
            content.classList.toggle('collapsed');
        });

        content.addEventListener('click', () => {
            if (!sidebar.classList.contains('hidden') && window.innerWidth <= 768) {
                sidebar.classList.add('hidden');
                content.classList.add('collapsed');
            }
        });
    </script>


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




</body>
</html>

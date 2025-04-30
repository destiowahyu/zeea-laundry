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
        $nama_poli = $_POST['nama_poli'];
        $keterangan = $_POST['keterangan'];

        $result = $conn->query("INSERT INTO poli (nama_poli, keterangan) 
                                VALUES ('$nama_poli', '$keterangan')");
        if ($result) {
            $message = 'Poli berhasil ditambahkan!';
            $type = 'success';
        } else {
            $message = 'Gagal menambahkan poli!';
            $type = 'error';
        }
    }

    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $nama_poli = $_POST['nama_poli'];
        $keterangan = $_POST['keterangan'];

        $result = $conn->query("UPDATE poli SET nama_poli='$nama_poli', keterangan='$keterangan' WHERE id='$id'");
        if ($result) {
            $message = 'Poli berhasil diperbarui!';
            $type = 'success';
        } else {
            $message = 'Gagal memperbarui poli!';
            $type = 'error';
        }
    }

    if (isset($_POST['delete'])) {
        $id = $_POST['id'];

        $result = $conn->query("DELETE FROM poli WHERE id='$id'");
        if ($result) {
            $message = 'Poli berhasil dihapus!';
            $type = 'success';
        } else {
            $message = 'Gagal menghapus poli!';
            $type = 'error';
        }
    }
}

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$poliList = $conn->query("SELECT * FROM poli WHERE nama_poli LIKE '%$search%'");
if (!$poliList) {
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
            <h1>Kelola Poli</h1>
        </div>
        
        


                        <div class="container mt-5">
                    <h1 class="mb-4">Kelola Poli</h1>






                    <!-- ISI TABEL -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <form method="GET" class="d-flex">
                            <input type="text" name="search" class="form-control me-2" placeholder="Cari Poli..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary">Cari</button>
                        </form>

                    </div>

                    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addPoliModal">Tambah Poli</button>

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>ID Poli</th>
                                <th>Nama Poli</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while ($row = $poliList->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= $row['nama_poli'] ?></td>
                                    <td><?= $row['keterangan'] ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editPoliModal<?= $row['id'] ?>">Edit</button>
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="delete" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editPoliModal<?= $row['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Poli</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label>Nama Poli</label>
                                                        <input type="text" name="nama_poli" class="form-control" value="<?= $row['nama_poli'] ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Keterangan</label>
                                                        <textarea name="keterangan" class="form-control"><?= $row['keterangan'] ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="submit" name="edit" class="btn btn-warning">Simpan</button>
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
                <div class="modal fade" id="addPoliModal" tabindex="-1">
                    <div class="modal-dialog">
                        <form method="POST">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Tambah Poli</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label>Nama Poli</label>
                                        <input type="text" name="nama_poli" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Keterangan</label>
                                        <textarea name="keterangan" class="form-control" required></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" name="add" class="btn btn-primary">Simpan</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

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

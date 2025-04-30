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
        $nama_obat = $_POST['nama_obat'];
        $kemasan = $_POST['kemasan'];
        $harga = $_POST['harga'];

        $result = $conn->query("INSERT INTO obat (nama_obat, kemasan, harga) 
                                VALUES ('$nama_obat', '$kemasan', '$harga')");
        if ($result) {
            $message = 'Obat berhasil ditambahkan!';
            $type = 'success';
        } else {
            $message = 'Gagal menambahkan obat!';
            $type = 'error';
        }
    }

    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $nama_obat = $_POST['nama_obat'];
        $kemasan = $_POST['kemasan'];
        $harga = $_POST['harga'];

        $result = $conn->query("UPDATE obat SET nama_obat='$nama_obat', kemasan='$kemasan', harga='$harga' WHERE id='$id'");
        if ($result) {
            $message = 'Obat berhasil diperbarui!';
            $type = 'success';
        } else {
            $message = 'Gagal memperbarui obat!';
            $type = 'error';
        }
    }

    if (isset($_POST['delete'])) {
        $id = $_POST['id'];

        $result = $conn->query("DELETE FROM obat WHERE id='$id'");
        if ($result) {
            $message = 'Obat berhasil dihapus!';
            $type = 'success';
        } else {
            $message = 'Gagal menghapus obat!';
            $type = 'error';
        }
    }
}

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$obatList = $conn->query("SELECT * FROM obat WHERE nama_obat LIKE '%$search%'");
if (!$obatList) {
    die("Query gagal: " . $conn->error);
}

$adminName = $_SESSION['username'];
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
            <h1>Kelola Obat</h1>
        </div>
        
        


                        <div class="container mt-5">
                    <h1 class="mb-4">Kelola Obat</h1>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <form method="GET" class="d-flex">
                            <input type="text" name="search" class="form-control me-2" placeholder="Cari Obat..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn btn-primary">Cari</button>
                        </form>
                    </div>


                    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addObatModal">Tambah Obat</button>




                    <!-- ISI TABEL -->
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>ID Obat</th>
                                <th>Nama Obat</th>
                                <th>Kemasan</th>
                                <th>Harga</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while ($row = $obatList->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= $row['id'] ?></td>
                                    <td><?= $row['nama_obat'] ?></td>
                                    <td><?= $row['kemasan'] ?></td>
                                    <td><?= $row['harga'] ?></td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editObatModal<?= $row['id'] ?>">Edit</button>
                                        <form method="POST" style="display:inline-block;">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="delete" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editObatModal<?= $row['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <form method="POST">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Obat</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label>Nama Obat</label>
                                                        <input type="text" name="nama_obat" class="form-control" value="<?= $row['nama_obat'] ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Kemasan</label>
                                                        <input type="text" name="kemasan" class="form-control" value="<?= $row['kemasan'] ?>">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label>Harga</label>
                                                        <input type="number" name="harga" class="form-control" value="<?= $row['harga'] ?>">
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
                <div class="modal fade" id="addObatModal" tabindex="-1">
                    <div class="modal-dialog">
                        <form method="POST">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Tambah Obat</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label>Nama Obat</label>
                                        <input type="text" name="nama_obat" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Kemasan</label>
                                        <input type="text" name="kemasan" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label>Harga</label>
                                        <input type="number" name="harga" class="form-control" required>
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

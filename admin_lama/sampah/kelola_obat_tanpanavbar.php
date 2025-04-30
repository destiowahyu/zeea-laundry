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
    <title>Kelola Obat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/styles/admin.css">
</head>
<body>
    <div class="sidebar">
        <h3>Admin Panel</h3>
        <div class="profile">
            <img src="../assets/images/user.svg" alt="Avatar" width="50">
            <span><?= htmlspecialchars($adminName) ?></span>
        </div>
        <a href="dashboard.php">Dashboard</a>
        <a href="kelola_dokter.php">Kelola Dokter</a>
        <a href="kelola_pasien.php">Kelola Pasien</a>
        <a href="kelola_poli.php">Kelola Poli</a>
        <a href="kelola_obat.php">Kelola Obat</a>
        <a href="../logout.php">Logout</a>
    </div>

    <div class="content">
        <h1>Kelola Obat</h1>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <form method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Cari obat..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">Cari</button>
            </form>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addObatModal">Tambah Obat</button>
        </div>

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
</body>
</html>

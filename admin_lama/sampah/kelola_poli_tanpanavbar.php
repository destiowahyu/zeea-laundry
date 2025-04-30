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
    <title>Kelola Poli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --main-bg-color: #f9fafa;
            --sidebar-bg-color: #ffffff;
            --text-color: #2ac4c2;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--main-bg-color);
        }

        .sidebar {
            height: 100vh;
            background-color: var(--sidebar-bg-color);
            border-right: 1px solid #ddd;
            padding: 20px;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
        }

        .content {
            margin-left: 270px;
            padding: 20px;
        }

        h1 {
            color: var(--text-color);
        }

        .btn-primary {
            background-color: #42c3cf;
            border-color: #42c3cf;
        }

        .btn-primary:hover {
            background-color: #35b5bf;
        }

        .btn-warning {
            background-color: #ffdd57;
        }

        .btn-danger {
            background-color: #f87171;
        }

        table thead {
            background-color: #42c3cf;
            color: #fff;
        }

        .modal-header {
            background-color: #42c3cf;
            color: #fff;
        }
    </style>
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
        <a href="../logout.php">Logout</a>
    </div>

    <div class="content">
        <h1>Kelola Poli</h1>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <form method="GET" class="d-flex">
                <input type="text" name="search" class="form-control me-2" placeholder="Cari poli..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-primary">Cari</button>
            </form>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPoliModal">Tambah Poli</button>
        </div>

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
</body>
</html>

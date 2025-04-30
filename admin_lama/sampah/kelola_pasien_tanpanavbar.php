<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';

// Fungsi untuk generate No RM
function generateNoRM($conn) {
    $tahunBulan = date('Ym');
    $countQuery = $conn->query("SELECT COUNT(*) AS total FROM pasien WHERE no_rm LIKE '$tahunBulan%'");
    $countResult = $countQuery->fetch_assoc();
    $urutan = $countResult['total'] + 1;
    return sprintf('%s-%d', $tahunBulan, $urutan);
}

$no_rm_generate = generateNoRM($conn);

// Handle messages for notifications
$message = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $nama = $_POST['nama'];
        $alamat = $_POST['alamat'];
        $no_ktp = $_POST['no_ktp'];
        $no_hp = $_POST['no_hp'];
        $username = $_POST['username'];
        $password = md5($_POST['password']);
        
        // Cek apakah No. KTP sudah ada di database
        $cekKTP = $conn->query("SELECT * FROM pasien WHERE no_ktp = '$no_ktp'");
        if ($cekKTP->num_rows > 0) {
            $data = $cekKTP->fetch_assoc();
            $message = "No. KTP sudah terdaftar! No. RM: " . $data['no_rm'];
            $type = 'warning';
        } else {
            $no_rm = generateNoRM($conn);
            $result = $conn->query("INSERT INTO pasien (nama, alamat, no_ktp, no_hp, no_rm, username, password) 
                                    VALUES ('$nama', '$alamat', '$no_ktp', '$no_hp', '$no_rm', '$username', '$password')");
            if ($result) {
                $message = 'Pasien berhasil ditambahkan!';
                $type = 'success';
            } else {
                $message = 'Gagal menambahkan pasien!';
                $type = 'error';
            }
        }
    }

    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $nama = $_POST['nama'];
        $alamat = $_POST['alamat'];
        $no_ktp = $_POST['no_ktp'];
        $no_hp = $_POST['no_hp'];
        $username = $_POST['username'];
        $password = !empty($_POST['password']) ? md5($_POST['password']) : null;

        $passwordUpdate = $password ? ", password='$password'" : "";
        $result = $conn->query("UPDATE pasien SET nama='$nama', alamat='$alamat', no_ktp='$no_ktp', no_hp='$no_hp', username='$username' $passwordUpdate WHERE id='$id'");
        if ($result) {
            $message = 'Pasien berhasil diperbarui!';
            $type = 'success';
        } else {
            $message = 'Gagal memperbarui pasien!';
            $type = 'error';
        }
    }

    if (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $result = $conn->query("DELETE FROM pasien WHERE id='$id'");
        if ($result) {
            $message = 'Pasien berhasil dihapus!';
            $type = 'success';
        } else {
            $message = 'Gagal menghapus pasien!';
            $type = 'error';
        }
    }
}

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$pasienList = $conn->query("SELECT * FROM pasien WHERE nama LIKE '%$search%'");
if (!$pasienList) {
    die("Query gagal: " . $conn->error);
}

$adminName = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pasien - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <style>
        .readonly-input {
            background-color: #f0f0f0; 
            color: #6c757d; 
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <h3>Admin Panel</h3>
        <div class="profile">
            <img src="../assets/images/man.png" alt="Avatar">
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
            <h1>Kelola Pasien</h1>
        </div>

        <div class="container mt-5">
            <h1 class="mb-4">Kelola Pasien</h1>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <form method="GET" class="d-flex">
                    <input type="text" name="search" class="form-control me-2" placeholder="Cari Pasien..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary">Cari</button>
                </form>
            </div>

            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addPatientModal">Tambah Pasien</button>

            <!-- Tabel Pasien -->
            <table class="table table-bordered">
    <thead>
        <tr>
            <th>No</th>
            <th>ID Pasien</th>
            <th>Nama</th>
            <th>Alamat</th>
            <th>No KTP</th>
            <th>No HP</th>
            <th>No RM</th>
            <th>Username</th>
            <th>Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php $no = 1; while ($row = $pasienList->fetch_assoc()): ?>
        <tr>
            <td><?= $no++ ?></td>
            <td><?= $row['id'] ?></td>
            <td><?= $row['nama'] ?></td>
            <td><?= $row['alamat'] ?></td>
            <td><?= $row['no_ktp'] ?></td>
            <td><?= $row['no_hp'] ?></td>
            <td><?= $row['no_rm'] ?></td>
            <td><?= $row['username'] ?></td>
            <td>
                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editPatientModal<?= $row['id'] ?>">Edit</button>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <button type="submit" name="delete" class="btn btn-danger btn-sm">Hapus</button>
                </form>
            </td>
        </tr>
        <!-- Modal Edit -->
        <div class="modal fade" id="editPatientModal<?= $row['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Pasien</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label>Nama</label>
                                <input type="text" name="nama" class="form-control" value="<?= $row['nama'] ?>">
                            </div>
                            <div class="mb-3">
                                <label>Alamat</label>
                                <input type="text" name="alamat" class="form-control" value="<?= $row['alamat'] ?>">
                            </div>
                            <div class="mb-3">
                                <label>No KTP</label>
                                <input type="text" name="no_ktp" class="form-control" value="<?= $row['no_ktp'] ?>">
                            </div>
                            <div class="mb-3">
                                <label>No HP</label>
                                <input type="text" name="no_hp" class="form-control" value="<?= $row['no_hp'] ?>">
                            </div>
                            <div class="mb-3">
                                <label>No RM</label>
                                <input type="text" class="form-control readonly-input" value="<?= $row['no_rm'] ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label>Username</label>
                                <input type="text" name="username" class="form-control" value="<?= $row['username'] ?>">
                            </div>
                            <div class="mb-3">
                                <label>Password (Kosongkan jika tidak ingin diubah)</label>
                                <input type="password" name="password" class="form-control">
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

<!-- Modal Tambah -->
<div class="modal fade" id="addPatientModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Pasien</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Nama</label>
                        <input type="text" name="nama" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Alamat</label>
                        <input type="text" name="alamat" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>No KTP</label>
                        <input type="text" name="no_ktp" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>No HP</label>
                        <input type="text" name="no_hp" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>No RM</label>
                        <input type="text" class="form-control readonly-input" value="<?= $no_rm_generate ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
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
        showConfirmButton: true,
    });
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
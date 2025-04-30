<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dokter') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';

// Ambil ID dokter dari session
$dokterId = $_SESSION['id'];

// Handle toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $jadwalId = $_POST['jadwal_id'];
    $newStatus = $_POST['status'] === 'Aktif' ? 'Tidak Aktif' : 'Aktif';

    // Set all other schedules to "Tidak Aktif" if toggling to "Aktif"
    if ($newStatus === 'Aktif') {
        $conn->query("UPDATE jadwal_periksa SET status = 'Tidak Aktif' WHERE id_dokter = '$dokterId'");
    }

    // Update the selected schedule's status
    $conn->query("UPDATE jadwal_periksa SET status = '$newStatus' WHERE id = '$jadwalId'");

    $message = "Status jadwal berhasil diubah!";
    $type = "success";
}

// Handle add schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_schedule'])) {
    $hari = $_POST['hari'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];

    $conn->query("INSERT INTO jadwal_periksa (id_dokter, hari, jam_mulai, jam_selesai, status) 
                  VALUES ('$dokterId', '$hari', '$jam_mulai', '$jam_selesai', 'Tidak Aktif')");

    $message = "Jadwal berhasil ditambahkan!";
    $type = "success";
}

// Handle edit schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_schedule'])) {
    $jadwalId = $_POST['jadwal_id'];
    $hari = $_POST['hari'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];

    $conn->query("UPDATE jadwal_periksa SET hari = '$hari', jam_mulai = '$jam_mulai', jam_selesai = '$jam_selesai' WHERE id = '$jadwalId'");

    $message = "Jadwal berhasil diperbarui!";
    $type = "success";
}

// Handle delete schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_schedule'])) {
    $jadwalId = $_POST['jadwal_id'];
    $conn->query("DELETE FROM jadwal_periksa WHERE id = '$jadwalId'");

    $message = "Jadwal berhasil dihapus!";
    $type = "success";
}

// Ambil data jadwal periksa dokter
$jadwalList = $conn->query("SELECT * FROM jadwal_periksa WHERE id_dokter = '$dokterId'");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Periksa - Dokter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .toggle-status {
            display: flex;
            align-items: center;
        }
        .form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
            margin-left: 0;
        }
        .form-switch .form-check-input:checked {
            background-color: #42c3cf;
            border-color: #42c3cf;
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <h3>Dokter Panel</h3>
        <div class="profile">
            <img src="../assets/images/avatar-doctor.png" alt="Avatar">
            <span><?= htmlspecialchars($_SESSION['username']) ?></span>
        </div>
        <a href="dashboard.php"><i class="fas fa-chart-pie"></i> Dashboard</a>
        <a href="jadwal_periksa.php" class="active"><i class="fas fa-calendar-check"></i> Jadwal Periksa</a>
        <a href="memeriksa_pasien.php"><i class="fas fa-user-md"></i> Memeriksa Pasien</a>
        <a href="riwayat_pasien.php"><i class="fas fa-history"></i> Riwayat Pasien</a>
        <a href="profil.php"><i class="fas fa-user"></i> Profil</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="content" id="content">
        <div class="header">
            <button class="toggle-btn" id="toggleSidebar">
                <i class="fas fa-bars"></i>
            </button>
            <h1>Jadwal Periksa</h1>
        </div>

        <div class="container mt-5">
            <h2 class="mb-4">Jadwal Periksa</h2>

            <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addScheduleModal">Tambah Jadwal</button>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Dokter</th>
                        <th>Hari</th>
                        <th>Jam Mulai</th>
                        <th>Jam Selesai</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($row = $jadwalList->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($_SESSION['username']) ?></td>
                            <td><?= htmlspecialchars($row['hari']) ?></td>
                            <td><?= htmlspecialchars($row['jam_mulai']) ?></td>
                            <td><?= htmlspecialchars($row['jam_selesai']) ?></td>
                            <td class="toggle-status">
                                <form method="POST" class="form-switch">
                                    <input type="hidden" name="jadwal_id" value="<?= $row['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $row['status'] ?>">
                                    <input class="form-check-input" type="checkbox" <?= $row['status'] === 'Aktif' ? 'checked' : '' ?> 
                                           onchange="this.form.submit()">
                                    <label class="ms-2">
                                        <?= $row['status'] === 'Aktif' ? 'Aktif' : 'Tidak Aktif' ?>
                                    </label>
                                    <input type="hidden" name="toggle_status" value="1">
                                </form>
                            </td>
                            <td>
                                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editScheduleModal<?= $row['id'] ?>">Edit</button>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="jadwal_id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="delete_schedule" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </td>
                        </tr>

                        <!-- Edit Schedule Modal -->
                        <div class="modal fade" id="editScheduleModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <form method="POST">
                                    <input type="hidden" name="jadwal_id" value="<?= $row['id'] ?>">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Jadwal</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label for="hari" class="form-label">Hari</label>
                                                <select name="hari" id="hari" class="form-control" required>
                                                    <option value="Senin" <?= $row['hari'] === 'Senin' ? 'selected' : '' ?>>Senin</option>
                                                    <option value="Selasa" <?= $row['hari'] === 'Selasa' ? 'selected' : '' ?>>Selasa</option>
                                                    <option value="Rabu" <?= $row['hari'] === 'Rabu' ? 'selected' : '' ?>>Rabu</option>
                                                    <option value="Kamis" <?= $row['hari'] === 'Kamis' ? 'selected' : '' ?>>Kamis</option>
                                                    <option value="Jumat" <?= $row['hari'] === 'Jumat' ? 'selected' : '' ?>>Jumat</option>
                                                    <option value="Sabtu" <?= $row['hari'] === 'Sabtu' ? 'selected' : '' ?>>Sabtu</option>
                                                    <option value="Minggu" <?= $row['hari'] === 'Minggu' ? 'selected' : '' ?>>Minggu</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label for="jam_mulai" class="form-label">Jam Mulai</label>
                                                <input type="time" name="jam_mulai" id="jam_mulai" class="form-control" value="<?= $row['jam_mulai'] ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="jam_selesai" class="form-label">Jam Selesai</label>
                                                <input type="time" name="jam_selesai" id="jam_selesai" class="form-control" value="<?= $row['jam_selesai'] ?>" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" name="edit_schedule" class="btn btn-warning">Simpan</button>
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

        <!-- Add Schedule Modal -->
        <div class="modal fade" id="addScheduleModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Tambah Jadwal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="hari" class="form-label">Hari</label>
                                <select name="hari" id="hari" class="form-control" required>
                                    <option value="Senin">Senin</option>
                                    <option value="Selasa">Selasa</option>
                                    <option value="Rabu">Rabu</option>
                                    <option value="Kamis">Kamis</option>
                                    <option value="Jumat">Jumat</option>
                                    <option value="Sabtu">Sabtu</option>
                                    <option value="Minggu">Minggu</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="jam_mulai" class="form-label">Jam Mulai</label>
                                <input type="time" name="jam_mulai" id="jam_mulai" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="jam_selesai" class="form-label">Jam Selesai</label>
                                <input type="time" name="jam_selesai" id="jam_selesai" class="form-control" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" name="add_schedule" class="btn btn-primary">Simpan</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (isset($message)): ?>
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
</body>
</html>

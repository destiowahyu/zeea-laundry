<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dokter') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

include '../includes/db.php';

// Ambil ID dokter dan username dari session
$dokterId = $_SESSION['id'];
$dokterUsername = $_SESSION['username'];

// Ambil data nama dokter dari database
$dokterData = $conn->query("SELECT nama FROM dokter WHERE id = '$dokterId'")->fetch_assoc();
$namaDokter = $dokterData['nama']; // Nama asli dokter dari database

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

    // Check if a schedule already exists for the same day and time
    $checkExisting = $conn->query("SELECT * FROM jadwal_periksa WHERE id_dokter = '$dokterId' AND hari = '$hari' AND 
                                  ((jam_mulai <= '$jam_mulai' AND jam_selesai > '$jam_mulai') OR 
                                   (jam_mulai < '$jam_selesai' AND jam_selesai >= '$jam_selesai') OR 
                                   (jam_mulai >= '$jam_mulai' AND jam_selesai <= '$jam_selesai'))");

    if ($checkExisting->num_rows > 0) {
        $message = "Jadwal sudah ada pada hari dan jam yang sama!";
        $type = "error";
    } else {
        $conn->query("INSERT INTO jadwal_periksa (id_dokter, hari, jam_mulai, jam_selesai, status) 
                      VALUES ('$dokterId', '$hari', '$jam_mulai', '$jam_selesai', 'Tidak Aktif')");

        $message = "Jadwal berhasil ditambahkan!";
        $type = "success";
    }
}

// Handle edit schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_schedule'])) {
    $jadwalId = $_POST['jadwal_id'];
    $hari = $_POST['hari'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];

    // Check if a schedule already exists for the same day and time (excluding the current schedule being edited)
    $checkExisting = $conn->query("SELECT * FROM jadwal_periksa WHERE id_dokter = '$dokterId' AND hari = '$hari' AND 
                                  ((jam_mulai <= '$jam_mulai' AND jam_selesai > '$jam_mulai') OR 
                                   (jam_mulai < '$jam_selesai' AND jam_selesai >= '$jam_selesai') OR 
                                   (jam_mulai >= '$jam_mulai' AND jam_selesai <= '$jam_selesai')) AND 
                                  id != '$jadwalId'");

    if ($checkExisting->num_rows > 0) {
        $message = "Jadwal sudah ada pada hari dan jam yang sama!";
        $type = "error";
    } else {
        $conn->query("UPDATE jadwal_periksa SET hari = '$hari', jam_mulai = '$jam_mulai', jam_selesai = '$jam_selesai' WHERE id = '$jadwalId'");

        $message = "Jadwal berhasil diperbarui!";
        $type = "success";
    }
}

// Handle delete schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_schedule'])) {
    $jadwalId = $_POST['jadwal_id'];
    $conn->query("DELETE FROM jadwal_periksa WHERE id = '$jadwalId'");

    $message = "Jadwal berhasil dihapus!";
    $type = "success";
}

// Ambil data jadwal periksa dokter beserta informasi poli
$jadwalList = $conn->query("
    SELECT jp.*, d.nama AS nama_dokter, p.nama_poli 
    FROM jadwal_periksa jp
    JOIN dokter d ON jp.id_dokter = d.id
    JOIN poli p ON d.id_poli = p.id
    WHERE jp.id_dokter = '$dokterId'
");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Periksa - Dokter</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" integrity="sha384-tViUnnbYAV00FLIhhi3v/dWt3Jxw4gZQcNoSCxCIFNJVCx7/D55/wXsrNIRANwdD" crossorigin="anonymous">
    <link rel="icon" type="image/png" href="../assets/images/avatar-doctor.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
</head>
<body>
    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <?php include 'sidebar_dokter.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="header">
        </div>

        <div class="container">
            <h1 class="mb-4">Jadwal Periksa</h1>

            <div class="welcome">
                    Jadwal Periksa : <span><strong style="color: #42c3cf;"><?= htmlspecialchars($namaDokter) ?>!</strong></span>
            </div>

            <button class="btn btn-primary mb-3 mt-4" style="border-radius: 30px; padding: 7px 20px;" data-bs-toggle="modal" data-bs-target="#addScheduleModal"><i class="bi bi-calendar-plus"></i> Tambah Jadwal</button>

            <table class="table-jadwalperiksa table-striped">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Dokter</th>
                        <th>Poli</th>
                        <th>Hari</th>
                        <th>Jam Mulai</th>
                        <th>Jam Selesai</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; while ($row = $jadwalList->fetch_assoc()): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= htmlspecialchars($dokterName) ?></td>
                            <td><?= htmlspecialchars($row['nama_poli']) ?></td>
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

</body>
</html>


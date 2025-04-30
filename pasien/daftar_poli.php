<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'pasien') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
include '../includes/db.php';

// Ambil data pasien dari sesi
$pasienName = $_SESSION['username'];
$pasienData = $conn->query("SELECT * FROM pasien WHERE username = '$pasienName'")->fetch_assoc();

// Pastikan nomor rekam medis tersedia di sesi
$query_no_rm = $conn->prepare("SELECT no_rm FROM pasien WHERE username = ?");
$query_no_rm->bind_param("s", $_SESSION['username']);
$query_no_rm->execute();
$result_no_rm = $query_no_rm->get_result();

if ($result_no_rm->num_rows > 0) {
    $row_no_rm = $result_no_rm->fetch_assoc();
    $nomor_rekam_medis = $row_no_rm['no_rm'];
} else {
    echo "Data pasien tidak ditemukan. Silakan login kembali.";
    exit();
}

// Ambil data poli dari database
$query_poli = "SELECT id, nama_poli FROM poli";
$result_poli = $conn->query($query_poli);

// Handle AJAX untuk mendapatkan jadwal dokter
if (isset($_GET['action']) && $_GET['action'] === 'get_jadwal') {
    $poliId = intval($_GET['poli_id']);
    $query_jadwal = "
        SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, d.nama AS nama_dokter
        FROM jadwal_periksa j
        JOIN dokter d ON j.id_dokter = d.id
        WHERE d.id_poli = ? AND j.status = 'Aktif'
    ";
    $stmt = $conn->prepare($query_jadwal);
    $stmt->bind_param("i", $poliId);
    $stmt->execute();
    $result = $stmt->get_result();

    $jadwal = [];
    while ($row = $result->fetch_assoc()) {
        $jadwal[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($jadwal);
    exit;
}

// Jika form dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_poli = intval($_POST['poli']);
    $jadwal_dokter = intval($_POST['jadwal']);
    $keluhan = htmlspecialchars($_POST['keluhan'], ENT_QUOTES, 'UTF-8');

    // Ambil id_pasien berdasarkan nomor_rekam_medis
    $query_pasien = "SELECT id FROM pasien WHERE no_rm = ?";
    $stmt_pasien = $conn->prepare($query_pasien);
    $stmt_pasien->bind_param("s", $nomor_rekam_medis);
    $stmt_pasien->execute();
    $result_pasien = $stmt_pasien->get_result();

    if ($result_pasien->num_rows > 0) {
        $id_pasien = $result_pasien->fetch_assoc()['id'];

        // Ambil nomor antrian terakhir untuk jadwal dokter dan tanggal saat ini
        $tanggal_hari_ini = date('Y-m-d');
        $query_antrian = "
            SELECT MAX(no_antrian) AS max_antrian 
            FROM daftar_poli 
            WHERE id_jadwal = ? AND DATE(created_at) = ?
        ";
        $stmt_antrian = $conn->prepare($query_antrian);
        $stmt_antrian->bind_param("is", $jadwal_dokter, $tanggal_hari_ini);
        $stmt_antrian->execute();
        $result_antrian = $stmt_antrian->get_result();

        // Debugging: pastikan query mengembalikan hasil yang benar
        if ($result_antrian) {
            $max_antrian = $result_antrian->fetch_assoc()['max_antrian'];
            $no_antrian = ($max_antrian !== null) ? $max_antrian + 1 : 1;

            // Simpan data ke database
            $stmt = $conn->prepare("INSERT INTO daftar_poli (id_pasien, id_jadwal, keluhan, no_antrian, status, created_at) VALUES (?, ?, ?, ?, 'Belum Diperiksa', NOW())");
            $stmt->bind_param("iisi", $id_pasien, $jadwal_dokter, $keluhan, $no_antrian);
            $stmt->execute();
            $stmt->close();
        } else {
            echo "Query nomor antrian gagal dijalankan.";
            exit();
        }
    } else {
        echo "Pasien tidak ditemukan.";
        exit();
    }
}

// Ambil riwayat daftar poli
$query_riwayat = "
    SELECT dp.id, po.nama_poli, d.nama AS nama_dokter, 
           j.hari, j.jam_mulai, j.jam_selesai, dp.no_antrian, dp.status, dp.created_at 
    FROM daftar_poli dp
    JOIN jadwal_periksa j ON dp.id_jadwal = j.id
    JOIN dokter d ON j.id_dokter = d.id
    JOIN poli po ON d.id_poli = po.id
    WHERE dp.id_pasien = (
        SELECT id FROM pasien WHERE no_rm = ?
    )
    ORDER BY dp.id DESC
";
$stmt_riwayat = $conn->prepare($query_riwayat);
$stmt_riwayat->bind_param("s", $nomor_rekam_medis);
$stmt_riwayat->execute();
$result_riwayat = $stmt_riwayat->get_result();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Poli - Pasien</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/pasien.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <!-- Bootstrap Icons CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-HoAqzM0Ll3xdCEaOfhccTd36SpzvoD6B0T3OOcDjfGgDkXp24FdQYvpB3nsTmFCy" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

</head>
<body>
        <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

        <!-- Sidebar -->
        <?php include 'sidebar_pasien.php'; ?>

    <!-- Main Content -->
                <div class="content" id="content">
                    <div class="header">
                    <div class="container">
        <h1 class="mb-2">Daftar Poli</h1>
        <form method="post" class="mt-4">
            <div class="mb-3">
                <label for="nomor_rekam_medis" class="form-label">Nomor Rekam Medis:</label>
                <input type="text" id="nomor_rekam_medis" class="form-control readonly-input" value="<?php echo $nomor_rekam_medis; ?>" disabled>
            </div>
            <div class="mb-3">
                <label for="poli" class="form-label">Pilih Poli:</label>
                <select name="poli" id="poli" class="form-select" required>
                    <option value="">Pilih Poli</option>
                    <?php while ($row = $result_poli->fetch_assoc()): ?>
                        <option value="<?php echo $row['id']; ?>"><?php echo $row['nama_poli']; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="jadwal" class="form-label">Pilih Jadwal Dokter:</label>
                <select name="jadwal" id="jadwal" class="form-select" required>
                    <option value="">Pilih Poli Terlebih Dahulu</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="keluhan" class="form-label">Tuliskan Keluhan Anda:</label>
                <textarea name="keluhan" id="keluhan" class="form-control" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100">Daftar</button>
        </form>

        <h1 class="mt-5">Riwayat Daftar Poli</h1>
        <table class="table-riwayatdaftarpoli table-striped">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Poli</th>
                    <th>Dokter</th>
                    <th>Jadwal</th>
                    <th>Nomor Antrian</th>
                    <th>Waktu Mendaftar</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1; 
                if ($result_riwayat->num_rows > 0):
                    while ($row = $result_riwayat->fetch_assoc()): 
                ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($row['nama_poli']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama_dokter']); ?></td>
                        <td><?php echo htmlspecialchars($row['hari'] . " (" . $row['jam_mulai'] . " - " . $row['jam_selesai'] . ")"); ?></td>
                        <td><?php echo $row['no_antrian']; ?></td>
                        <td><?php echo htmlspecialchars(date('d-m-Y H:i:s', strtotime($row['created_at']))); ?></td>
                        <td>
                            <?php if ($row['status'] === 'Belum Diperiksa'): ?>
                                <span class="badge" style="background-color:rgb(233, 71, 71); color: rgb(255, 255, 255); border-radius: 20px; padding: 10px;">Belum Diperiksa <i class="bi bi-x-lg"></i></span>
                            <?php else: ?>
                                <span class="badge" style="background-color:rgb(45, 165, 43); color: #fff; border-radius: 20px; padding: 10px;">Sudah Diperiksa <i class="bi bi-check2-all"></i></span>
                            <?php endif; ?>
                        </td>
                        <td><a href="detail_daftar_poli.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm"><i class="bi bi-box-arrow-up-right"></i> Detail</a></td>
                    </tr>
                <?php 
                    endwhile; 
                else: 
                ?>
                    <tr>
                        <td colspan="8" class="text-center">Belum ada riwayat pendaftaran.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.getElementById('poli').addEventListener('change', function() {
            var poliId = this.value;
            fetch('?action=get_jadwal&poli_id=' + poliId)
                .then(response => response.json())
                .then(data => {
                    var jadwalSelect = document.getElementById('jadwal');
                    jadwalSelect.innerHTML = '<option value="">Pilih Jadwal</option>';
                    data.forEach(jadwal => {
                        jadwalSelect.innerHTML += `<option value="${jadwal.id}">${jadwal.hari} (${jadwal.jam_mulai} - ${jadwal.jam_selesai}) - ${jadwal.nama_dokter}</option>`;
                    });
                });
        });
    </script>

</body>
</html>

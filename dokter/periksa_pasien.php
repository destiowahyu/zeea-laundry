<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dokter') {
    header("Location: ../index.php");
    exit();
}

$periksaPasien_page = 'periksa_pasien.php';

include '../includes/db.php';

// Ambil data dokter dari sesi
$dokterUsername = $_SESSION['username'];
$query_dokter = $conn->prepare("SELECT id, nama FROM dokter WHERE username = ?");
$query_dokter->bind_param("s", $dokterUsername);
$query_dokter->execute();
$result_dokter = $query_dokter->get_result();

if ($result_dokter->num_rows === 0) {
    echo "Data dokter tidak ditemukan. Silakan login kembali.";
    exit();
}

$dokterData = $result_dokter->fetch_assoc();
$dokterId = $dokterData['id'];
$dokterName = $dokterData['nama'];

// Ambil daftar pasien berdasarkan ID dokter
$query_pasien = "
    SELECT dp.id AS id_daftar, p.nama AS nama_pasien, dp.no_antrian, dp.keluhan, dp.status, dp.created_at 
    FROM daftar_poli dp
    JOIN jadwal_periksa jp ON dp.id_jadwal = jp.id
    JOIN pasien p ON dp.id_pasien = p.id
    WHERE jp.id_dokter = ?
    ORDER BY dp.created_at DESC
";
$stmt = $conn->prepare($query_pasien);
$stmt->bind_param("i", $dokterId);
$stmt->execute();
$result_pasien = $stmt->get_result();

$current_page = basename($_SERVER['PHP_SELF']);



// Ambil ID dokter dan username dari session
$dokterId = $_SESSION['id'];
$dokterUsername = $_SESSION['username'];

// Ambil data nama dokter dari database
$dokterData = $conn->query("SELECT nama FROM dokter WHERE id = '$dokterId'")->fetch_assoc();
$namaDokter = $dokterData['nama']; // Nama asli dokter dari database];

?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Dokter</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/avatar-doctor.png">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
</head>
<body>
    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <?php include 'sidebar_dokter.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <h1 class="mb-4">Periksa Pasien</h1>
                <div class="welcome">
                    Daftar Pasien <span><strong style="color: #42c3cf;"><?= htmlspecialchars($namaDokter) ?>!</strong></span>
                </div>
                <!-- Filter Tanggal dan Nama -->
                <div class="row mt-4 mb-3">
                    <div class="col-md-6">
                        <label for="search" class="form-label">Cari Nama Pasien:</label>
                        <input type="text" id="search" class="form-control" placeholder="Masukkan nama pasien">
                    </div>
                    <div class="col-md-6">
                        <label for="tanggal" class="form-label">Pilih Tanggal:</label>
                        <input type="date" id="tanggal" class="form-control">
                    </div>
                </div>

                <!-- Tabel Pasien -->
                <table class="table-periksapasien table-striped table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Pasien</th>
                            <th>Nomor Antrian</th>
                            <th>Keluhan</th>
                            <th>Tanggal Pendaftaran</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1; 
                        if ($result_pasien->num_rows > 0): 
                            while ($row = $result_pasien->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($row['nama_pasien']) ?></td>
                                <td><?= htmlspecialchars($row['no_antrian']) ?></td>
                                <td><?= htmlspecialchars($row['keluhan']) ?></td>
                                <td><?= htmlspecialchars(date('Y-m-d', strtotime($row['created_at']))) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'Belum Diperiksa'): ?>
                                        <span class="badge" style="background-color:rgb(215, 56, 56); color: #fff; border-radius: 20px; padding: 10px;">Belum Diperiksa &#10060;</span>
                                    <?php else: ?>
                                        <span class="badge" style="background-color:rgb(45, 165, 43); color: #fff; border-radius: 20px; padding: 10px;">Sudah Diperiksa &#9989;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'Belum Diperiksa'): ?>
                                        <a href="detail_periksa_pasien.php?id=<?= $row['id_daftar'] ?>" class="btn btn-primary btn-sm" style="border-radius: 30px; padding: 7px 20px;">
                                            <i class="fa fa-stethoscope"></i> Periksa
                                        </a>
                                    <?php else: ?>
                                        <a href="detail_periksa_pasien.php?id=<?= $row['id_daftar'] ?>" class="btn btn-secondary btn-sm" style="border-radius: 30px; padding: 7px 20px;">
                                            <i class="bi bi-pen"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php 
                            endwhile; 
                        else: 
                        ?>
                            <tr>
                                <td colspan="7" class="text-center">Belum ada pasien yang mendaftar.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- JavaScript untuk Filter Real-Time -->
            <script>
                $(document).ready(function() {
                    function filterTable() {
                        const tanggal = $('#tanggal').val();
                        const search = $('#search').val().toLowerCase();

                        $('.table-periksapasien tbody tr').each(function() {
                            const row = $(this);
                            const nama = row.find('td:eq(1)').text().toLowerCase();
                            const tanggalCreated = row.find('td:eq(4)').text();

                            const matchesTanggal = !tanggal || tanggalCreated.includes(tanggal);
                            const matchesSearch = !search || nama.includes(search);

                            row.toggle(matchesTanggal && matchesSearch);
                        });
                    }

                    // Event listener for filter inputs
                    $('#tanggal, #search').on('input', filterTable);

                    // Initial filter application
                    filterTable();
                });
            </script>

        </div>

    </div>

</body>
</html>


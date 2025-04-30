<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dokter') {
    header("Location: ../index.php");
    exit();
}

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Periksa Pasien - Dokter</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center">Periksa Pasien</h1>
        <h4 class="text-center">Dokter: <?= htmlspecialchars($dokterName) ?></h4>

        <!-- Filter Tanggal dan Nama -->
        <div class="row mt-4 mb-3">
            <div class="col-md-6">
                <label for="tanggal" class="form-label">Pilih Tanggal:</label>
                <input type="date" id="tanggal" class="form-control">
            </div>
            <div class="col-md-6">
                <label for="search" class="form-label">Cari Nama Pasien:</label>
                <input type="text" id="search" class="form-control" placeholder="Masukkan nama pasien">
            </div>
        </div>

        <!-- Tabel Pasien -->
        <table class="table table-bordered mt-3" id="table-pasien">
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
                                <span style="font-weight: bold; color: red;">&#10060; Belum diperiksa</span>
                            <?php else: ?>
                                <span style="font-weight: bold; color: green;">&#9989; Sudah diperiksa</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['status'] === 'Belum Diperiksa'): ?>
                                <a href="detail_periksa_pasien.php?id=<?= $row['id_daftar'] ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-clipboard2-plus"></i> Periksa
                                </a>
                            <?php else: ?>
                                <a href="detail_periksa_pasien.php?id=<?= $row['id_daftar'] ?>" class="btn btn-secondary btn-sm">
                                    <i class="bi bi-pencil-square"></i> Edit
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

                $('#table-pasien tbody tr').filter(function() {
                    const nama = $(this).find('td:eq(1)').text().toLowerCase();
                    const tanggalCreated = $(this).find('td:eq(4)').text(); // Kolom tanggal

                    $(this).toggle(
                        (!tanggal || tanggalCreated.includes(tanggal)) &&
                        (!search || nama.includes(search))
                    );
                });
            }

            // Event listener untuk filter tanggal dan nama
            $('#tanggal, #search').on('input', function() {
                filterTable();
            });
        });
    </script>
</body>
</html>

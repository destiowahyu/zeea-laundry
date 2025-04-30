<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'dokter') {
    header("Location: ../index.php");
    exit();
}

include '../includes/db.php';



// Handle AJAX request for patient details
if (isset($_POST['action']) && $_POST['action'] === 'getDetails' && isset($_POST['id'])) {
    $id_daftar = intval($_POST['id']);
    
    $query = "
        SELECT 
            p.nama AS nama_pasien,
            p.no_rm,
            COALESCE(pr.tgl_periksa, dp.created_at) as tgl_periksa,
            dp.keluhan,
            pl.nama_poli,
            d.nama AS nama_dokter,
            COALESCE(pr.catatan, 'Belum diperiksa') as catatan,
            COALESCE(pr.biaya_periksa, 0) as biaya_periksa,
            COALESCE(pr.id, 0) as id_periksa
        FROM 
            daftar_poli dp
        JOIN pasien p ON dp.id_pasien = p.id
        JOIN jadwal_periksa jp ON dp.id_jadwal = jp.id
        JOIN dokter d ON jp.id_dokter = d.id
        JOIN poli pl ON d.id_poli = pl.id
        LEFT JOIN periksa pr ON dp.id = pr.id_daftar_poli
        WHERE dp.id = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id_daftar);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
        exit;
    }

    $data = $result->fetch_assoc();

    // Get medicines if patient has been examined
    if ($data['id_periksa'] > 0) {
        $query_obat = "
            SELECT o.nama_obat, dp.jumlah
            FROM detail_periksa dp
            JOIN obat o ON dp.id_obat = o.id
            WHERE dp.id_periksa = ?
        ";

        $stmt_obat = $conn->prepare($query_obat);
        $stmt_obat->bind_param("i", $data['id_periksa']);
        $stmt_obat->execute();
        $result_obat = $stmt_obat->get_result();

        $obat_list = [];
        while ($row_obat = $result_obat->fetch_assoc()) {
            $obat_list[] = $row_obat['nama_obat'] . ' (' . $row_obat['jumlah'] . ')';
        }
    } else {
        $obat_list = ['Belum ada obat'];
    }

    $data['obat_list'] = implode(', ', $obat_list);
    $data['biaya_periksa_formatted'] = number_format($data['biaya_periksa'], 0, ',', '.');
    
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

// Pagination settings
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Fetch patient records with search and filter
$query = "
    SELECT 
        dp.id AS id_daftar,
        p.nama AS nama_pasien,
        p.no_rm,
        dp.created_at AS tanggal_pendaftaran,
        pl.nama_poli,
        d.nama AS nama_dokter,
        pr.id AS id_periksa
    FROM 
        daftar_poli dp
    JOIN pasien p ON dp.id_pasien = p.id
    JOIN jadwal_periksa jp ON dp.id_jadwal = jp.id
    JOIN dokter d ON jp.id_dokter = d.id
    JOIN poli pl ON d.id_poli = pl.id
    LEFT JOIN periksa pr ON dp.id = pr.id_daftar_poli
    WHERE 
        (? = '' OR p.nama LIKE CONCAT('%', ?, '%'))
        AND (? = '' OR DATE(dp.created_at) = ?)
    ORDER BY dp.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ssss", $search, $search, $date_filter, $date_filter);
$stmt->execute();
$result = $stmt->get_result();

// Count total filtered records
$total_records = $result->num_rows;
$total_pages = ceil($total_records / $limit);

// Apply limit and offset
$result->data_seek($offset);
$data = array();
for ($i = 0; $i < $limit && $row = $result->fetch_assoc(); $i++) {
    $data[] = $row;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pasien - Dokter</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/avatar-doctor.png">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
            <h1 class="mb-4">Riwayat Pasien</h1>
            <div class="welcome">
                Riwayat <span><strong style="color: #42c3cf;">Semua Pasien</strong></span>
            </div>

            <!-- Filter Tanggal dan Nama -->
            <form id="searchForm" method="GET" action="">
                <div class="row mt-4 mb-3">
                    <div class="col-md-6">
                        <label for="search" class="form-label">Cari Nama Pasien:</label>
                        <input type="text" id="searchInput" name="search" class="form-control" placeholder="Masukkan nama pasien" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="dateFilter" class="form-label">Pilih Tanggal:</label>
                        <input type="date" id="dateFilter" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                </div>
                <input type="hidden" name="limit" value="<?= $limit ?>">
                <button type="submit" class="btn btn-primary mb-5 w-100">Cari</button>
            </form>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table-riwayatpasien table-striped table-hover">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Pasien</th>
                            <th>No RM</th>
                            <th>Tanggal Pendaftaran</th>
                            <th>Poli</th>
                            <th>Dokter</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = $offset + 1;
                        foreach ($data as $row): 
                        ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($row['nama_pasien']) ?></td>
                                <td><?= htmlspecialchars($row['no_rm']) ?></td>
                                <td><?= date('Y-m-d', strtotime($row['tanggal_pendaftaran'])) ?></td>
                                <td><?= htmlspecialchars($row['nama_poli']) ?></td>
                                <td><?= htmlspecialchars($row['nama_dokter']) ?></td>
                                <td>
                                    <button class="btn btn-primary btn-sm" style="border-radius: 30px; padding: 7px 20px;" onclick="showDetails(<?= $row['id_daftar'] ?>)">
                                    <i class="bi bi-box-arrow-up-right"></i> Detail
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination and Limit Selection -->
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="d-flex align-items-center gap-2">
                    <select id="limitSelect" class="form-select" style="width: auto;" onchange="changeLimit()">
                        <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                        <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                        <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                    </select>
                    <nav aria-label="Page navigation" class="ms-2">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page-1 ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&date=<?= urlencode($date_filter) ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&date=<?= urlencode($date_filter) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page+1 ?>&limit=<?= $limit ?>&search=<?= urlencode($search) ?>&date=<?= urlencode($date_filter) ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Patient Details -->
    <div class="modal fade" id="patientDetailsModal" tabindex="-1" aria-labelledby="patientDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="patientDetailsModalLabel">Detail Riwayat Pasien</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Nama Pasien:</strong> <span id="modalNamaPasien"></span></p>
                                <p><strong>No RM:</strong> <span id="modalNoRM"></span></p>
                                <p><strong>Tanggal Periksa:</strong> <span id="modalTanggalPeriksa"></span></p>
                                <p><strong>Keluhan:</strong> <span id="modalKeluhan"></span></p>
                                <p><strong>Poli:</strong> <span id="modalPoli"></span></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Nama Dokter:</strong> <span id="modalDokter"></span></p>
                                <p><strong>Catatan Dokter:</strong> <span id="modalCatatan"></span></p>
                                <p><strong>Obat yang Diberikan:</strong> <span id="modalObat"></span></p>
                                <p><strong>Biaya Periksa:</strong> Rp <span id="modalBiaya"></span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    

    // Search and filter functionality
    $(document).ready(function() {
        $("#searchForm").on("submit", function(e) {
            e.preventDefault();
            var searchValue = $("#searchInput").val();
            var dateValue = $("#dateFilter").val();
            var limitValue = $("#limitSelect").val();
            
            window.location.href = `?search=${searchValue}&date=${dateValue}&limit=${limitValue}`;
        });
    });

    function showDetails(id) {
        $.ajax({
            url: 'riwayat_pasien.php',
            type: 'POST',
            data: { 
                action: 'getDetails',
                id: id 
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const data = response.data;
                    $('#modalNamaPasien').text(data.nama_pasien);
                    $('#modalNoRM').text(data.no_rm);
                    $('#modalTanggalPeriksa').text(data.tgl_periksa);
                    $('#modalKeluhan').text(data.keluhan);
                    $('#modalPoli').text(data.nama_poli);
                    $('#modalDokter').text(data.nama_dokter);
                    $('#modalCatatan').text(data.catatan);
                    $('#modalObat').text(data.obat_list);
                    $('#modalBiaya').text(data.biaya_periksa_formatted);
                    $('#patientDetailsModal').modal('show');
                } else {
                    alert('Data tidak ditemukan');
                }
            },
            error: function(xhr, status, error) {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat mengambil data pasien');
            }
        });
    }

    function changeLimit() {
        const limit = document.getElementById('limitSelect').value;
        const currentSearch = new URLSearchParams(window.location.search);
        currentSearch.set('limit', limit);
        currentSearch.set('page', '1');
        window.location.href = `?${currentSearch.toString()}`;
    }
    </script>
</body>
</html>
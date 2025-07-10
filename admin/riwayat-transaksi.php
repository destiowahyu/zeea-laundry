<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Set zona waktu ke Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

$current_page = basename($_SERVER['PHP_SELF']);
include '../includes/db.php';

// Ambil data admin dari sesi
$adminUsername = $_SESSION['username'];
$query_admin = $conn->prepare("SELECT id, username FROM admin WHERE username = ?");
$query_admin->bind_param("s", $adminUsername);
$query_admin->execute();
$result_admin = $query_admin->get_result();

if ($result_admin->num_rows === 0) {
    echo "Data admin tidak ditemukan. Silakan login kembali.";
    exit();
}

$adminData = $result_admin->fetch_assoc();
$adminId = $adminData['id'];
$adminName = $adminData['username'];

// Format tanggal dalam bahasa Indonesia
function formatTanggalIndonesia($tanggal) {
    $hari = array(
        'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
    );
    
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    
    $timestamp = strtotime($tanggal);
    $hari_ini = $hari[date('w', $timestamp)];
    $tanggal_ini = date('j', $timestamp);
    $bulan_ini = $bulan[date('n', $timestamp)];
    $tahun_ini = date('Y', $timestamp);
    $jam = date('H:i', $timestamp);
    
    return "$hari_ini, $tanggal_ini $bulan_ini $tahun_ini $jam";
}

$tanggal_sekarang = formatTanggalIndonesia(date('Y-m-d H:i:s'));

// Inisialisasi variabel filter
$periode_filter = isset($_GET['periode']) ? $_GET['periode'] : 'hari_ini';
$tanggal_dari = isset($_GET['tanggal_dari']) ? $_GET['tanggal_dari'] : '';
$tanggal_sampai = isset($_GET['tanggal_sampai']) ? $_GET['tanggal_sampai'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$pembayaran_filter = isset($_GET['pembayaran']) ? $_GET['pembayaran'] : '';
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES) : '';

// Ambil filter tanggal dari request
$filter_tanggal = '';
if ($periode_filter === 'hari_ini') {
    $filter_tanggal = date('Y-m-d');
} elseif ($periode_filter === 'custom' && !empty($tanggal_dari) && !empty($tanggal_sampai)) {
    $filter_tanggal = [$tanggal_dari, $tanggal_sampai];
} elseif ($periode_filter === 'bulan_ini') {
    $filter_tanggal = date('Y-m');
}

// Tentukan rentang tanggal berdasarkan periode
$where_condition = "WHERE 1=1";
$periode_text = "";

switch ($periode_filter) {
    case 'hari_ini':
        $where_condition .= " AND DATE(p.waktu) = CURDATE()";
        $periode_text = "Hari Ini - " . date('d F Y');
        break;
    case '7_hari':
        $where_condition .= " AND DATE(p.waktu) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()";
        $periode_text = "7 Hari Terakhir";
        break;
    case '30_hari':
        $where_condition .= " AND DATE(p.waktu) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()";
        $periode_text = "30 Hari Terakhir";
        break;
    case 'bulan_ini':
        $where_condition .= " AND MONTH(p.waktu) = MONTH(CURDATE()) AND YEAR(p.waktu) = YEAR(CURDATE())";
        $periode_text = "Bulan Ini - " . date('F Y');
        break;
    case 'custom':
        if (!empty($tanggal_dari) && !empty($tanggal_sampai)) {
            $where_condition .= " AND DATE(p.waktu) BETWEEN '$tanggal_dari' AND '$tanggal_sampai'";
            $periode_text = "Custom - " . date('d F Y', strtotime($tanggal_dari)) . " s/d " . date('d F Y', strtotime($tanggal_sampai));
        } else {
            $where_condition .= " AND DATE(p.waktu) = CURDATE()";
            $periode_text = "Custom - Pilih Tanggal";
        }
        break;
    default:
        $where_condition .= " AND DATE(p.waktu) = CURDATE()";
        $periode_text = "Hari Ini - " . date('d F Y');
}

// Tambahkan filter status jika ada
if (!empty($status_filter)) {
    $where_condition .= " AND p.status = '$status_filter'";
}

// Tambahkan filter pembayaran jika ada
if (!empty($pembayaran_filter)) {
    $where_condition .= " AND p.status_pembayaran = '$pembayaran_filter'";
}

// Tambahkan pencarian jika ada
if (!empty($search)) {
    $search_query = "%" . $search . "%";
    $where_condition .= " AND (pl.nama LIKE '$search_query' OR p.tracking_code LIKE '$search_query' OR pl.no_hp LIKE '$search_query')";
}

// Query untuk statistik
$stats_query = "SELECT 
    COUNT(*) as total_transaksi,
    SUM(p.harga) as total_nilai,
    SUM(CASE WHEN p.status = 'diproses' THEN 1 ELSE 0 END) as status_diproses,
    SUM(CASE WHEN p.status = 'selesai' THEN 1 ELSE 0 END) as status_selesai,
    SUM(CASE WHEN p.status_pembayaran = 'sudah_dibayar' THEN 1 ELSE 0 END) as sudah_dibayar,
    SUM(CASE WHEN p.status_pembayaran = 'belum_dibayar' THEN 1 ELSE 0 END) as belum_dibayar,
    SUM(CASE WHEN p.status_pembayaran = 'sudah_dibayar' THEN p.harga ELSE 0 END) as nilai_sudah_dibayar
FROM pesanan p 
JOIN pelanggan pl ON p.id_pelanggan = pl.id 
$where_condition AND p.deleted_at IS NULL";

$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// --- PERBAIKAN FILTER TANGGAL ---
// Query pesanan laundry (seperti sebelumnya, tambahkan sumber)
$transaksi_query = "SELECT 
    p.id,
    p.tracking_code,
    p.harga,
    p.status,
    p.status_pembayaran,
    p.waktu,
    pl.nama as nama_pelanggan,
    pl.no_hp,
    GROUP_CONCAT(pk.nama SEPARATOR ', ') as paket_list,
    MAX(COALESCE(aj.harga, 5000)) as harga_antar_jemput,
    MAX(aj.layanan) as layanan_antar_jemput,
    'laundry' as sumber
FROM pesanan p 
JOIN pelanggan pl ON p.id_pelanggan = pl.id 
JOIN paket pk ON p.id_paket = pk.id 
LEFT JOIN antar_jemput aj ON p.id = aj.id_pesanan
WHERE p.deleted_at IS NULL AND p.status_pembayaran = 'sudah_dibayar' ";
if ($periode_filter === 'hari_ini') {
    $transaksi_query .= " AND DATE(p.waktu) = CURDATE() ";
} elseif ($periode_filter === '7_hari') {
    $transaksi_query .= " AND DATE(p.waktu) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE() ";
} elseif ($periode_filter === '30_hari') {
    $transaksi_query .= " AND DATE(p.waktu) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE() ";
} elseif ($periode_filter === 'bulan_ini') {
    $transaksi_query .= " AND MONTH(p.waktu) = MONTH(CURDATE()) AND YEAR(p.waktu) = YEAR(CURDATE()) ";
} elseif ($periode_filter === 'custom' && !empty($tanggal_dari) && !empty($tanggal_sampai)) {
    $transaksi_query .= " AND DATE(p.waktu) BETWEEN '$tanggal_dari' AND '$tanggal_sampai' ";
}
$transaksi_query .= " GROUP BY p.id, p.tracking_code, p.harga, p.status, p.status_pembayaran, p.waktu, pl.nama, pl.no_hp
ORDER BY p.waktu DESC";

// Query antar_jemput selesai & sudah dibayar, gunakan selesai_at jika ada
$antar_query = "SELECT 
    aj.id,
    CONCAT('AJ-', LPAD(aj.id, 6, '0')) as tracking_code,
    aj.harga as harga,
    aj.status,
    aj.status_pembayaran,
    COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput)) as waktu,
    COALESCE(pl.nama, aj.nama_pelanggan, 'Pelanggan') as nama_pelanggan,
    COALESCE(pl.no_hp, '') as no_hp,
    '-' as paket_list,
    aj.harga as harga_antar_jemput,
    aj.layanan as layanan_antar_jemput,
    'antar_jemput' as sumber
FROM antar_jemput aj
LEFT JOIN pelanggan pl ON aj.id_pelanggan = pl.id
WHERE aj.status = 'selesai' AND aj.status_pembayaran = 'sudah_dibayar' AND aj.deleted_at IS NULL AND aj.id_pesanan IS NULL";
if ($periode_filter === 'hari_ini') {
    $antar_query .= " AND DATE(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) = CURDATE() ";
} elseif ($periode_filter === '7_hari') {
    $antar_query .= " AND DATE(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE() ";
} elseif ($periode_filter === '30_hari') {
    $antar_query .= " AND DATE(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE() ";
} elseif ($periode_filter === 'bulan_ini') {
    $antar_query .= " AND MONTH(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) = MONTH(CURDATE()) AND YEAR(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) = YEAR(CURDATE()) ";
} elseif ($periode_filter === 'custom' && !empty($tanggal_dari) && !empty($tanggal_sampai)) {
    $antar_query .= " AND DATE(COALESCE(aj.selesai_at, COALESCE(aj.waktu_antar, aj.waktu_jemput))) BETWEEN '$tanggal_dari' AND '$tanggal_sampai' ";
}

// Gabungkan hasil query
$transaksi_result = $conn->query($transaksi_query);
$antar_result = $conn->query($antar_query);

$all_transaksi = [];
if ($transaksi_result) {
    while ($row = $transaksi_result->fetch_assoc()) {
        $all_transaksi[] = $row;
    }
}
if ($antar_result) {
    while ($row = $antar_result->fetch_assoc()) {
        $all_transaksi[] = $row;
    }
}
// Urutkan semua transaksi berdasarkan waktu DESC
usort($all_transaksi, function($a, $b) {
    return strtotime($b['waktu']) - strtotime($a['waktu']);
});

// --- INISIALISASI VARIABEL STATISTIK (agar tidak undefined) ---
$stat_total_transaksi = 0;
$stat_total_nilai = 0;
$stat_status_diproses = 0;
$stat_status_selesai = 0;
$stat_sudah_dibayar = 0;
$stat_belum_dibayar = 0;
// --- PERBAIKAN STATISTIK: Hitung dari $all_transaksi (laundry + antar jemput) ---
$stat_total_transaksi = count($all_transaksi);
$stat_total_nilai = 0;
$stat_status_diproses = 0;
$stat_status_selesai = 0;
$stat_sudah_dibayar = 0;
$stat_belum_dibayar = 0;
foreach ($all_transaksi as $row) {
    $stat_total_nilai += (int)($row['harga'] ?? 0);
    if (isset($row['status']) && (strtolower($row['status']) === 'diproses' || strtolower($row['status']) === 'proses' || strtolower($row['status']) === 'sedang diproses')) {
        $stat_status_diproses++;
    }
    if (isset($row['status']) && (strtolower($row['status']) === 'selesai' || strtolower($row['status']) === 'completed')) {
        $stat_status_selesai++;
    }
    if (isset($row['status_pembayaran']) && (strtolower($row['status_pembayaran']) === 'sudah_dibayar' || strtolower($row['status_pembayaran']) === 'paid')) {
        $stat_sudah_dibayar++;
    }
    if (isset($row['status_pembayaran']) && (strtolower($row['status_pembayaran']) === 'belum_dibayar' || strtolower($row['status_pembayaran']) === 'unpaid')) {
        $stat_belum_dibayar++;
    }
}

// Fungsi format tanggal
function formatTanggalSingkat($tanggal) {
    return date('d/m/Y H:i', strtotime($tanggal));
}

// Fungsi format rupiah
function formatRupiah($angka) {
    $angka = $angka ?? 0;
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Fungsi badge status
function getStatusBadge($status) {
    // Bersihkan status dari whitespace dan ubah ke lowercase
    $status = trim(strtolower($status));
    
    switch ($status) {
        case 'menunggu':
            return '<span class="badge bg-warning text-dark">Menunggu</span>';
        case 'diproses':
        case 'proses':
        case 'sedang diproses':
            return '<span class="badge bg-info">Diproses</span>';
        case 'selesai':
        case 'completed':
            return '<span class="badge bg-success">Selesai</span>';
        case 'dibatalkan':
        case 'cancelled':
        case 'batal':
            return '<span class="badge bg-danger">Dibatalkan</span>';
        default:
            // Debug: tampilkan status asli untuk troubleshooting
            return '<span class="badge bg-secondary" title="Status: ' . htmlspecialchars($status) . '">Unknown (' . htmlspecialchars($status) . ')</span>';
    }
}

// Fungsi badge pembayaran
function getPembayaranBadge($status) {
    // Bersihkan status dari whitespace dan ubah ke lowercase
    $status = trim(strtolower($status));
    
    switch ($status) {
        case 'belum_dibayar':
        case 'belum dibayar':
        case 'unpaid':
            return '<span class="badge bg-danger">Belum Dibayar</span>';
        case 'sudah_dibayar':
        case 'sudah dibayar':
        case 'paid':
            return '<span class="badge bg-success">Sudah Dibayar</span>';
        default:
            return '<span class="badge bg-secondary" title="Status: ' . htmlspecialchars($status) . '">Unknown (' . htmlspecialchars($status) . ')</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
    <style>
        .current-date {
            font-size: 14px;
            color: #6c757d;
            text-align: right;
            margin-bottom: 15px;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #42c3cf;
            margin-bottom: 1rem;
        }

        .periode-subtitle {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        /* Statistics Dashboard */
        .stats-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.primary::before { background: #42c3cf; }
        .stat-card.success::before { background: #28a745; }
        .stat-card.warning::before { background: #ffc107; }
        .stat-card.info::before { background: #17a2b8; }
        .stat-card.danger::before { background: #dc3545; }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.25rem;
            color: white;
        }

        .stat-icon.primary { background: linear-gradient(135deg, #42c3cf, #36b5c0); }
        .stat-icon.success { background: linear-gradient(135deg, #28a745, #20c997); }
        .stat-icon.warning { background: linear-gradient(135deg, #ffc107, #e0a800); }
        .stat-icon.info { background: linear-gradient(135deg, #17a2b8, #138496); }
        .stat-icon.danger { background: linear-gradient(135deg, #dc3545, #c82333); }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filter Container */
        .filter-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .filter-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .filter-title i {
            margin-right: 0.75rem;
            color: #42c3cf;
        }

        .periode-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .periode-btn {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            border: 2px solid #e9ecef;
            background: white;
            color: #6c757d;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
        }

        .periode-btn.active {
            background: #42c3cf;
            color: white;
            border-color: #42c3cf;
            box-shadow: 0 2px 10px rgba(66, 195, 207, 0.3);
        }

        .periode-btn:hover:not(.active) {
            background: rgba(66, 195, 207, 0.1);
            color: #42c3cf;
            border-color: #42c3cf;
        }

        .custom-date-range {
            display: none;
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .custom-date-range.show {
            display: block;
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
            overflow-x: auto;
        }

        .table-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-title i {
            margin-right: 0.75rem;
            color: #42c3cf;
        }

        .table th {
            background: linear-gradient(135deg, #42c3cf, #36b5c0);
            color: white;
            font-weight: 500;
            border: none;
            padding: 1rem 0.75rem;
            font-size: 0.9rem;
        }

        .table th:first-child {
            border-top-left-radius: 15px;
        }

        .table th:last-child {
            border-top-right-radius: 15px;
        }

        .table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .table tr:hover {
            background-color: rgba(66, 195, 207, 0.05);
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .tracking-id {
            font-weight: 600;
            color: #42c3cf;
            background: rgba(66, 195, 207, 0.1);
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            border: 1px dashed #42c3cf;
            font-size: 0.85rem;
            display: inline-block;
        }

        .price-amount {
            font-weight: 700;
            color: #28a745;
            font-size: 1.1rem;
        }

        .customer-info {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .customer-phone {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .paket-info {
            font-size: 0.9rem;
            color: #495057;
            line-height: 1.4;
        }

        /* Mobile Cards */
        .mobile-cards {
            display: none;
        }

        .transaction-card {
            background: white;
            border-radius: 15px;
            margin-bottom: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #f0f0f0;
        }

        .transaction-card-header {
            background: linear-gradient(135deg, #42c3cf, #36b5c0);
            padding: 1rem;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .transaction-card-body {
            padding: 1rem;
        }

        .transaction-item {
            display: flex;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .transaction-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .transaction-label {
            font-weight: 600;
            min-width: 100px;
            color: #6c757d;
            font-size: 0.85rem;
        }

        .transaction-value {
            flex: 1;
        }

        .no-data {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }

            .stats-dashboard {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .filter-container,
            .table-container {
                padding: 1.5rem;
            }

            .desktop-table {
                display: none;
            }

            .mobile-cards {
                display: block;
            }

            .periode-buttons {
                justify-content: center;
            }

            .periode-btn {
                flex: 1;
                min-width: 120px;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .stats-dashboard {
                grid-template-columns: 1fr;
            }

            .periode-buttons {
                flex-direction: column;
            }

            .transaction-item {
                flex-direction: column;
            }

            .transaction-label {
                margin-bottom: 0.25rem;
                min-width: auto;
            }
        }

        @media (min-width: 769px) {
            .desktop-table {
                display: block;
            }

            .mobile-cards {
                display: none;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar-admin.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <div class="current-date">
                <i class="far fa-calendar-alt"></i> <?php echo $tanggal_sekarang; ?>
            </div>
            
            <h1 class="page-title">Riwayat Transaksi</h1>
            <div class="periode-subtitle">
                <i class="fas fa-history me-2"></i><?php echo $periode_text; ?>
            </div>

            <!-- Statistics Dashboard -->
            <div class="stats-dashboard">
    <div class="stat-card primary">
        <div class="stat-icon primary">
            <i class="fas fa-receipt"></i>
        </div>
        <div class="stat-number"><?php echo $stat_total_transaksi; ?></div>
        <div class="stat-label">Total Transaksi</div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-icon success">
            <i class="fas fa-money-bill-wave"></i>
        </div>
        <div class="stat-number"><?php echo formatRupiah($stat_total_nilai); ?></div>
        <div class="stat-label">Sudah Dibayar</div>
    </div>
    
    <div class="stat-card info">
        <div class="stat-icon info">
            <i class="fas fa-cog"></i>
        </div>
        <div class="stat-number"><?php echo $stat_status_diproses; ?></div>
        <div class="stat-label">Diproses</div>
    </div>
    
    <div class="stat-card success">
        <div class="stat-icon success">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-number"><?php echo $stat_status_selesai; ?></div>
        <div class="stat-label">Selesai</div>
    </div>
</div>

            <!-- Filter Container -->
            <div class="filter-container">
                <div class="filter-title">
                    <i class="fas fa-filter"></i>Filter Riwayat Transaksi
                </div>
                
                <!-- Periode Buttons -->
                <div class="periode-buttons">
                    <a href="?periode=hari_ini" class="periode-btn <?php echo $periode_filter === 'hari_ini' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-day me-1"></i>Hari Ini
                    </a>
                    <a href="?periode=7_hari" class="periode-btn <?php echo $periode_filter === '7_hari' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-week me-1"></i>7 Hari Terakhir
                    </a>
                    <a href="?periode=30_hari" class="periode-btn <?php echo $periode_filter === '30_hari' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt me-1"></i>30 Hari Terakhir
                    </a>
                    <a href="?periode=bulan_ini" class="periode-btn <?php echo $periode_filter === 'bulan_ini' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar me-1"></i>Bulan Ini
                    </a>
                    <button type="button" class="periode-btn <?php echo $periode_filter === 'custom' ? 'active' : ''; ?>" onclick="toggleCustomDate()">
                        <i class="fas fa-calendar-plus me-1"></i>Custom
                    </button>
                </div>
                
                <!-- Custom Date Range -->
                <div class="custom-date-range <?php echo $periode_filter === 'custom' ? 'show' : ''; ?>" id="customDateRange">
                    <form method="GET" action="">
                        <input type="hidden" name="periode" value="custom">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Tanggal Dari</label>
                                <input type="date" class="form-control" name="tanggal_dari" value="<?php echo $tanggal_dari; ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold">Tanggal Sampai</label>
                                <input type="date" class="form-control" name="tanggal_sampai" value="<?php echo $tanggal_sampai; ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Terapkan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Additional Filters -->
                <form method="GET" action="">
                    <input type="hidden" name="periode" value="<?php echo $periode_filter; ?>">
                    <?php if ($periode_filter === 'custom'): ?>
                        <input type="hidden" name="tanggal_dari" value="<?php echo $tanggal_dari; ?>">
                        <input type="hidden" name="tanggal_sampai" value="<?php echo $tanggal_sampai; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status Pesanan</label>
                                <select class="form-select" name="status">
    <option value="">Semua Status</option>
    <option value="diproses" <?php echo $status_filter === 'diproses' ? 'selected' : ''; ?>>Diproses</option>
    <option value="selesai" <?php echo $status_filter === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
</select>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status Pembayaran</label>
                                <select class="form-select" name="pembayaran">
                                    <option value="">Semua Pembayaran</option>
                                    <option value="belum_dibayar" <?php echo $pembayaran_filter === 'belum_dibayar' ? 'selected' : ''; ?>>Belum Dibayar</option>
                                    <option value="sudah_dibayar" <?php echo $pembayaran_filter === 'sudah_dibayar' ? 'selected' : ''; ?>>Sudah Dibayar</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Cari Transaksi</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="search" placeholder="Tracking/Nama/No.HP" value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Table Container -->
            <div class="table-container">
                <div class="table-title">
                    <span><i class="fas fa-table"></i>Detail Transaksi</span>
                    <div class="text-muted">
                        Total: <?php echo $stat_total_transaksi; ?> transaksi | 
                        Nilai: <?php echo formatRupiah($stat_total_nilai); ?>
                    </div>
                </div>

                <?php if (count($all_transaksi) > 0): ?>
                    <!-- Desktop Table -->
                    <div class="desktop-table">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="50">No</th>
                                        <th width="120">Tracking</th>
                                        <th width="150">Pelanggan</th>
                                        <th width="200">Paket</th>
                                        <th width="120">Harga</th>
                                        <th width="100">Status</th>
                                        <th width="120">Pembayaran</th>
                                        <th width="150">Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    foreach ($all_transaksi as $row): 
                                        $total_harga = $row['harga'];
                                        $is_antar = ($row['sumber'] === 'antar_jemput');
                                    ?>
                                        <tr>
                                            <td><?php echo $no++; ?></td>
                                            <td>
                                                <span class="tracking-id"><?php echo $row['tracking_code']; ?></span>
                                                <?php if ($is_antar): ?><br><small class="text-info">Antar/Jemput</small><?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="customer-info"><?php echo htmlspecialchars($row['nama_pelanggan']); ?></div>
                                                <div class="customer-phone"><?php echo $row['no_hp']; ?></div>
                                            </td>
                                            <td>
                                                <div class="paket-info"><?php echo htmlspecialchars($row['paket_list']); ?></div>
                                                <?php if (!empty($row['layanan_antar_jemput'])): ?>
                                                    <small class="text-info">+ <?php echo ucfirst($row['layanan_antar_jemput']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="price-amount"><?php echo formatRupiah($total_harga); ?></div>
                                            </td>
                                            <td><?php echo getStatusBadge($row['status']); ?></td>
                                            <td><?php echo getPembayaranBadge($row['status_pembayaran']); ?></td>
                                            <td><?php echo formatTanggalSingkat($row['waktu']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="mobile-cards">
                        <?php 
                        $no = 1;
                        foreach ($all_transaksi as $row): 
                            $total_harga = $row['harga'];
                            $is_antar = ($row['sumber'] === 'antar_jemput');
                        ?>
                            <div class="transaction-card">
                                <div class="transaction-card-header">
                                    <div><strong>No. <?php echo $no++; ?></strong></div>
                                    <div><?php echo getStatusBadge($row['status']); ?></div>
                                </div>
                                
                                <div class="transaction-card-body">
                                    <div class="transaction-item">
                                        <div class="transaction-label">Tracking:</div>
                                        <div class="transaction-value">
                                            <span class="tracking-id"><?php echo $row['tracking_code']; ?></span>
                                            <?php if ($is_antar): ?><br><small class="text-info">Antar/Jemput</small><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="transaction-item">
                                        <div class="transaction-label">Pelanggan:</div>
                                        <div class="transaction-value">
                                            <div class="customer-info"><?php echo htmlspecialchars($row['nama_pelanggan']); ?></div>
                                            <div class="customer-phone"><?php echo $row['no_hp']; ?></div>
                                        </div>
                                    </div>
                                    <div class="transaction-item">
                                        <div class="transaction-label">Paket:</div>
                                        <div class="transaction-value">
                                            <div class="paket-info"><?php echo htmlspecialchars($row['paket_list']); ?></div>
                                            <?php if (!empty($row['layanan_antar_jemput'])): ?>
                                                <small class="text-info">+ <?php echo ucfirst($row['layanan_antar_jemput']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="transaction-item">
                                        <div class="transaction-label">Harga:</div>
                                        <div class="transaction-value">
                                            <div class="price-amount"><?php echo formatRupiah($total_harga); ?></div>
                                        </div>
                                    </div>
                                    <div class="transaction-item">
                                        <div class="transaction-label">Pembayaran:</div>
                                        <div class="transaction-value"><?php echo getPembayaranBadge($row['status_pembayaran']); ?></div>
                                    </div>
                                    <div class="transaction-item">
                                        <div class="transaction-label">Waktu:</div>
                                        <div class="transaction-value"><?php echo formatTanggalSingkat($row['waktu']); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-history"></i>
                        <h5>Tidak Ada Riwayat Transaksi</h5>
                        <p>Tidak ada transaksi yang ditemukan untuk periode yang dipilih.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleCustomDate() {
            const customRange = document.getElementById('customDateRange');
            const isVisible = customRange.classList.contains('show');
            
            if (isVisible) {
                customRange.classList.remove('show');
            } else {
                customRange.classList.add('show');
            }
        }

        // Auto submit form when date changes in custom range
        document.addEventListener('DOMContentLoaded', function() {
            const dateInputs = document.querySelectorAll('#customDateRange input[type="date"]');
            dateInputs.forEach(input => {
                input.addEventListener('change', function() {
                    // Auto submit when both dates are filled
                    const tanggalDari = document.querySelector('input[name="tanggal_dari"]').value;
                    const tanggalSampai = document.querySelector('input[name="tanggal_sampai"]').value;
                    
                    if (tanggalDari && tanggalSampai) {
                        document.querySelector('#customDateRange form').submit();
                    }
                });
            });
        });
    </script>
</body>
</html>

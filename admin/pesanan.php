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

// Inisialisasi variabel pencarian dan filter
$search = isset($_GET['search']) ? htmlspecialchars($_GET['search'], ENT_QUOTES) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : '';

// Buat query untuk mendapatkan pesanan yang dikelompokkan berdasarkan pelanggan dan waktu
$query = "SELECT 
          MIN(p.id) as id,
          p.waktu,
          p.id_pelanggan,
          p.status,
          p.status_pembayaran,
          pl.nama as nama_pelanggan,
          pl.no_hp,
          COUNT(p.id) as jumlah_item,
          SUM(p.harga) as total_harga,
          GROUP_CONCAT(pk.nama SEPARATOR ', ') as paket_list,
          GROUP_CONCAT(p.berat SEPARATOR ', ') as berat_list
        FROM 
          pesanan p 
        JOIN 
          paket pk ON p.id_paket = pk.id 
        JOIN 
          pelanggan pl ON p.id_pelanggan = pl.id 
        WHERE 1=1";

// Tambahkan kondisi pencarian jika ada
if (!empty($search)) {
  $search_query = "%" . $search . "%";
  $query .= " AND (pl.nama LIKE '$search_query' OR pl.no_hp LIKE '$search_query' OR p.id LIKE '$search_query')";
}

// Tambahkan filter tanggal jika ada
if (!empty($date_from) && !empty($date_to)) {
  $query .= " AND DATE(p.waktu) BETWEEN '$date_from' AND '$date_to'";
} elseif (!empty($date_from)) {
  $query .= " AND DATE(p.waktu) >= '$date_from'";
} elseif (!empty($date_to)) {
  $query .= " AND DATE(p.waktu) <= '$date_to'";
}

// Tambahkan filter status jika ada
if (!empty($status_filter)) {
  $query .= " AND p.status = '$status_filter'";
}

// Tambahkan filter status pembayaran jika ada
if (!empty($payment_filter)) {
  $query .= " AND p.status_pembayaran = '$payment_filter'";
}

// Kelompokkan berdasarkan pelanggan dan waktu
$query .= " GROUP BY p.id_pelanggan, p.waktu";

// Tambahkan pengurutan
$query .= " ORDER BY p.waktu DESC";

// Eksekusi query
$result = $conn->query($query);

// Fungsi untuk mendapatkan badge status
function getStatusBadge($status) {
  switch ($status) {
      case 'diproses':
          return '<span class="badge bg-warning text-dark status-badge status-processing">Diproses</span>';
      case 'selesai':
          return '<span class="badge bg-success status-badge">Selesai</span>';
      case 'dibatalkan':
          return '<span class="badge bg-danger status-badge">Dibatalkan</span>';
      default:
          return '<span class="badge bg-secondary status-badge">Unknown</span>';
  }
}

// Fungsi untuk mendapatkan badge status pembayaran
function getPaymentStatusBadge($status) {
  switch ($status) {
      case 'belum_dibayar':
          return '<span class="badge bg-danger status-badge payment-unpaid">Belum Dibayar</span>';
      case 'sudah_dibayar':
          return '<span class="badge bg-success status-badge">Sudah Dibayar</span>';
      default:
          return '<span class="badge bg-secondary status-badge">Unknown</span>';
  }
}

// Fungsi untuk format tanggal singkat
function formatTanggalSingkat($tanggal) {
  $timestamp = strtotime($tanggal);
  return date('d/m/Y H:i', $timestamp);
}

// Fungsi untuk mendapatkan ringkasan paket
function getPaketSummary($paket_list, $berat_list) {
  $paket_array = explode(', ', $paket_list);
  $berat_array = explode(', ', $berat_list);
  
  $summary = '';
  for ($i = 0; $i < count($paket_array); $i++) {
      $summary .= $paket_array[$i] . ' (' . number_format((float)$berat_array[$i], 2, ',', '.') . ' kg)';
      if ($i < count($paket_array) - 1) {
          $summary .= '<br>';
      }
  }
  
  return $summary;
}

// Cek apakah ada filter aktif
$has_active_filters = !empty($search) || !empty($date_from) || !empty($date_to) || !empty($status_filter) || !empty($payment_filter);
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Daftar Pesanan - Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/admin/styles.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
  <style>
      .status-badge {
          padding: 6px 10px;
          border-radius: 20px;
          font-size: 0.85rem;
          font-weight: 500;
          display: inline-block;
          text-align: center;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
          max-width: 100%;
      }

      .table tr:hover .status-processing {
          animation: pulse-warning 1s infinite alternate;
      }

      .table tr:hover .payment-unpaid {
          animation: pulse-danger 1s infinite alternate;
      }

      .order-card:hover .status-processing {
          animation: pulse-warning 1s infinite alternate;
      }

      .order-card:hover .payment-unpaid {
          animation: pulse-danger 1s infinite alternate;
      }

      @keyframes pulse-warning {
          0% {
              transform: scale(1);
              box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7);
          }
          100% {
              transform: scale(1.05);
              box-shadow: 0 0 10px 5px rgba(255, 193, 7, 0.7);
          }
      }

      @keyframes pulse-danger {
          0% {
              transform: scale(1);
              box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
          }
          100% {
              transform: scale(1.05);
              box-shadow: 0 0 10px 5px rgba(220, 53, 69, 0.7);
          }
      }
      
      .search-filter-container {
          background-color: #f8f9fa;
          border-radius: 15px;
          padding: 20px;
          margin-bottom: 25px;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
          position: relative;
          z-index: 10;
      }
      
      .table-container {
          background-color: white;
          border-radius: 15px;
          padding: 20px;
          box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
          margin-bottom: 30px;
          overflow-x: auto;
          position: relative;
      }
      
      .table-responsive {
          overflow-x: auto;
          -webkit-overflow-scrolling: touch;
      }
      
      .table {
          min-width: 800px;
      }
      
      .table th {
          background-color: #42c3cf;
          color: white;
          font-weight: 500;
          border: none;
      }
      
      .table th:first-child {
          border-top-left-radius: 10px;
      }
      
      .table th:last-child {
          border-top-right-radius: 10px;
      }
      
      .table td {
          vertical-align: middle;
      }
      
      .table tr:hover {
          background-color: #f8f9fa;
      }
      
      .btn-action {
          padding: 5px 10px;
          margin: 0 2px;
          border-radius: 5px;
      }
      
      .btn-add-order {
          background-color: #42c3cf;
          color: white;
          border-radius: 45px;
          padding: 20px 20px;
          font-size: 16px;
          font-weight: 500;
          margin-bottom: 20px;
          box-shadow: 0 4px 10px rgba(66, 195, 207, 0.3);
          transition: all 0.3s ease;
          text-align: center;
      }

      .btn-info {
          background-color: #42c3cf;
          color: white;
          border-radius: 50px;
          font-weight: bold;
      }

      .btn-info:hover {
          background-color:rgb(56, 162, 172);
          color: white;
      }

      .btn-outline-secondary{
          border-radius: 25px;
      }
      
      .btn-add-order:hover {
          background-color: #38adb8;
          transform: translateY(-2px);
          box-shadow: 0 6px 15px rgba(66, 195, 207, 0.4);
          color: white;
      }
      
      .btn-add-order i {
          margin-right: 10px;
      }
      
      .current-date {
          font-size: 14px;
          color: #6c757d;
          text-align: right;
          margin-bottom: 15px;
      }
      
      .no-data {
          text-align: center;
          padding: 40px 20px;
          color: #6c757d;
      }
      
      .no-data i {
          font-size: 48px;
          margin-bottom: 15px;
          color: #dee2e6;
      }
      
      .no-data p {
          font-size: 16px;
          margin-bottom: 20px;
      }
      
      .filter-label {
          font-weight: 500;
          margin-bottom: 5px;
          color: #495057;
      }
      
      .date-range-container {
          display: flex;
          gap: 10px;
          align-items: center;
      }
      
      .date-range-container .form-control {
          flex: 1;
      }
      
      .date-range-container .date-separator {
          color: #6c757d;
          font-weight: 500;
      }
      
      .order-card {
          border: 1px solid #dee2e6;
          border-radius: 10px;
          margin-bottom: 10px;
          overflow: hidden;
      }
      
      .order-card-header {
          background-color: #42c3cf;
          padding: 8px 10px;
          border-bottom: 1px solid #dee2e6;
          display: flex;
          justify-content: space-between;
          align-items: center;
          color: white;
      }
      
      .order-card-body {
          padding: 10px;
      }
      
      .order-card-item {
          display: flex;
          margin-bottom: 5px;
          padding-bottom: 5px;
          align-items: flex-start;
          border-bottom: 1px solid #f0f0f0;
      }
      
      .order-card-label {
          font-weight: 500;
          min-width: 100px;
          color: #6c757d;
      }
      
      .order-card-value {
          flex: 1;
      }
      
      .order-card-footer {
          display: flex;
          justify-content: space-between;
          padding: 8px 10px;
          background-color: #f8f9fa;
          border-top: 1px solid #dee2e6;
      }
      
      .mobile-scroll-notice {
          display: none;
          text-align: center;
          font-size: 0.8rem;
          color: #6c757d;
          margin-bottom: 10px;
      }
      
      .filter-overlay {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          background-color: rgba(0, 0, 0, 0.73);
          z-index: 5;
          display: none;
      }
      
      .filter-reminder {
          position: fixed;
          bottom: 20px;
          left: 50%;
          transform: translateX(-50%);
          background-color: #42c3cf;
          color: white;
          padding: 12px 20px;
          border-radius: 35px;
          box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
          z-index: 1000;
          display: none;
          animation: bounce 1s infinite alternate;
          text-align: center;
          max-width: 90%;
          white-space: normal;
      }
      
      @keyframes bounce {
          from {
              transform: translateX(-50%) translateY(0);
          }
          to {
              transform: translateX(-50%) translateY(-10px);
          }
      }
      
      .apply-filter-btn {
          transition: all 0.3s ease;
      }
      
      .apply-filter-btn.highlight {
          transform: scale(1.05);
          box-shadow: 0 0 15px rgba(13, 110, 253, 0.5);
          animation: pulse 1.5s infinite;
      }
      
      @keyframes pulse {
          0% {
              box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.7);
          }
          70% {
              box-shadow: 0 0 0 10px rgba(13, 110, 253, 0);
          }
          100% {
              box-shadow: 0 0 0 0 rgba(13, 110, 253, 0);
          }
      }
      
      .close-overlay-btn {
          position: fixed;
          top: 20px;
          right: 20px;
          background-color: #fff;
          color: #333;
          border: none;
          border-radius: 50%;
          width: 40px;
          height: 40px;
          font-size: 20px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
          z-index: 1000;
          transition: all 0.3s ease;
          display: none;
      }

      .close-overlay-btn:hover {
          background-color: #42c3cf;
          color: #fff;
          transform: scale(1.1);
      }
      
      .cancel-filter-btn {
          display: none !important;
      }
      
      .multiple-items-badge {
          background-color: #ff9800;
          color: white;
          border-radius: 50%;
          width: 22px;
          height: 22px;
          font-size: 12px;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          margin-left: 5px;
          font-weight: bold;
      }
      
      .paket-list {
          margin-top: 5px;
          font-size: 0.85rem;
      }
      
      .mobile-filter-toggle {
          display: none;
          width: 100%;
          background-color: #f8f9fa;
          border: 1px solid #dee2e6;
          border-radius: 10px;
          padding: 10px 15px;
          margin-bottom: 15px;
          text-align: left;
          font-weight: 500;
          color: #495057;
          box-shadow: 0 1px 3px rgba(0,0,0,0.1);
          transition: all 0.2s ease;
      }
      
      .mobile-filter-toggle:hover, 
      .mobile-filter-toggle:focus {
          background-color: #e9ecef;
      }
      
      .mobile-filter-toggle i {
          margin-right: 8px;
          transition: transform 0.3s ease;
      }
      
      .mobile-filter-toggle.active i {
          transform: rotate(180deg);
      }
      
      .mobile-filter-toggle .badge {
          margin-left: 5px;
          background-color: #42c3cf;
          color: white;
      }
      
      .mobile-filter-container {
          display: none;
          background-color: #f8f9fa;
          border-radius: 10px;
          padding: 15px;
          margin-bottom: 15px;
          box-shadow: 0 2px 5px rgba(0,0,0,0.1);
          animation: slideDown 0.3s ease;
      }
      
      @keyframes slideDown {
          from {
              opacity: 0;
              transform: translateY(-10px);
          }
          to {
              opacity: 1;
              transform: translateY(0);
          }
      }
      
      .active-filters {
          display: flex;
          flex-wrap: wrap;
          gap: 5px;
          margin-bottom: 10px;
      }
      
      .active-filter-badge {
          display: inline-flex;
          align-items: center;
          background-color: #e9ecef;
          border-radius: 20px;
          padding: 3px 10px;
          font-size: 0.75rem;
          color: #495057;
      }
      
      .active-filter-badge i {
          margin-left: 5px;
          cursor: pointer;
          color: #6c757d;
      }
      
      .active-filter-badge i:hover {
          color: #dc3545;
      }
      
      @media (max-width: 768px) {
          .date-range-container {
              flex-direction: column;
              gap: 5px;
          }
          
          .date-range-container .date-separator {
              display: none;
          }
          
          .search-filter-container {
              padding: 12px;
              display: none;
          }
          
          .mobile-filter-toggle {
              display: flex;
              align-items: center;
              justify-content: space-between;
          }
          
          .mobile-filter-container {
              display: block;
          }
          
          .filter-label {
              font-size: 13px;
              margin-bottom: 3px;
          }
          
          .form-control, .form-select {
              font-size: 13px;
              padding: 6px 10px;
          }
          
          .input-group-text {
              padding: 6px 10px;
          }
          
          .btn {
              font-size: 13px;
              padding: 6px 12px;
          }
          
          .filter-reminder {
              font-size: 13px;
              padding: 8px 12px;
              bottom: 15px;
          }
          
          .date-picker {
              font-size: 13px;
          }
          
          .btn-action {
              padding: 6px 10px;
              font-size: 13px;
          }
          

          .desktop-table {
              display: none;
          }
          
          .mobile-cards {
              display: block;
          }
          

          .close-overlay-btn {
              top: 10px;
              right: 10px;
          }
          
          .status-badge {
              font-size: 0.7rem;
              padding: 3px 6px;
              display: inline-block;
              width: auto;
              min-width: 0;
              max-width: 100%;
              white-space: normal;
              height: auto;
              line-height: 1.2;
          }
          
          .order-card-value .status-badge {
              margin-top: 3px;
              display: inline-block;
              width: 100%;
              box-sizing: border-box;
          }
          
          .order-card-item {
              display: flex;
              flex-direction: column;
              margin-bottom: 8px;
              padding-bottom: 8px;
              border-bottom: 1px solid #f0f0f0;
          }
          
          .order-card-item:nth-last-child(-n+2) {
              margin-bottom: 12px;
          }
          
          .status-container {
              display: flex;
              flex-direction: column;
              margin-bottom: 10px;
          }
          
          .status-item {
              width: 100%;
              margin-bottom: 10px;
          }
          
          .status-value .status-badge {
              width: 100%;
              display: block;
              text-align: center;
              margin-bottom: 5px;
          }

          .mobile-filter-content {
              max-height: 0;
              overflow: hidden;
              transition: max-height 0.3s ease;
          }
          
          .mobile-filter-content.show {
              max-height: 1000px;
          }
          
          .quick-filters {
              display: flex;
              flex-wrap: wrap;
              gap: 5px;
              margin-bottom: 10px;
          }
          
          .quick-filter-btn {
              flex: 1;
              min-width: 80px;
              text-align: center;
              font-size: 0.75rem;
              padding: 5px 8px;
              border-radius: 20px;
              background-color: #f8f9fa;
              border: 1px solid #dee2e6;
              color: #495057;
              transition: all 0.2s ease;
          }
          
          .quick-filter-btn.active {
              background-color: #42c3cf;
              color: white;
              border-color: #42c3cf;
          }
      }
      
      @media (min-width: 769px) {
          .mobile-cards {
              display: none;
          }
          
          .desktop-table {
              display: block;
          }
          
          .mobile-filter-toggle,
          .mobile-filter-container {
              display: none;
          }
      }

      @media (max-width: 768px) {
          .status-container {
              display: flex;
              flex-direction: row;
              justify-content: space-between;
              margin-bottom: 10px;
              flex-wrap: wrap;
          }
          
          .status-item {
              width: 48%;
              margin-bottom: 8px;
          }
          
          .status-label {
              font-weight: 600;
              color: #555;
              font-size: 12px;
              margin-bottom: 3px;
              display: block;
          }
          
          .status-value {
              width: 100%;
          }
          
          .status-value .status-badge {
              width: 100%;
              display: block;
              text-align: center;
          }
      }

      @media (max-width: 768px) {
          .order-status-container {
              display: flex;
              flex-direction: column;
              margin-top: 10px;
              padding-top: 10px;
          }
          
          .order-status-container .order-card-item {
              margin-bottom: 10px;
          }
          
          .order-status-container .order-card-label {
              font-weight: 600;
              margin-bottom: 5px;
          }
          
          .status-badge {
              font-size: 0.75rem;
              padding: 4px 8px;
              white-space: normal;
              line-height: 1.2;
              height: auto;
              max-width: 100%;
              box-sizing: border-box;
              display: inline-block;
          }

          .order-card-value .status-badge {
              display: inline-block;
              width: auto;
              min-width: 80px;
          }
      }

@media (max-width: 360px) {
  .status-badge {
      font-size: 0.7rem;
      padding: 3px 5px;
  }
  
  .order-card-body {
      padding: 6px 8px;
  }
  
  .order-card-label {
      font-size: 11px;
  }
  
  .order-status-container .order-card-item {
      padding-bottom: 12px;
  }
  
  .status-container {
      margin-bottom: 15px;
  }
  
  .status-item {
      margin-bottom: 12px;
  }
}
  </style>
</head>
<body>
  <?php include 'sidebar_admin.php'; ?>
  

  <div class="filter-overlay" id="filterOverlay"></div>
  

  <button class="close-overlay-btn" id="closeOverlayBtn" title="Tutup filter">
      <i class="fas fa-times"></i>
  </button>

  <div class="filter-reminder" id="filterReminder" style="display: none;"></div>

  <!-- Main Content -->
  <div class="content" id="content">
      <div class="container">
          <div class="current-date">
              <i class="far fa-calendar-alt"></i> <?php echo $tanggal_sekarang; ?>
          </div>
          
          <div class="d-flex justify-content-between align-items-center mb-4">
              <h1 class="mb-0">Daftar Pesanan</h1>
          </div>

          <a href="tambah_pesanan.php" class="btn btn-add-order mb-4">
              <i class="fas fa-plus-circle"></i> Tambah Pesanan Baru
          </a>
          
          <!-- Mobile Filter Toggle Button -->
          <button class="mobile-filter-toggle d-md-none <?php echo $has_active_filters ? 'active' : ''; ?>" id="mobileFilterToggle">
              <div>
                  <i class="fas fa-filter"></i> Filter Pesanan
              </div>
              <?php if($has_active_filters): ?>
              <span class="badge"><?php echo count(array_filter([$search, $date_from, $date_to, $status_filter, $payment_filter])); ?></span>
              <?php endif; ?>
          </button>
          
          <!-- Mobile Filter Container -->
          <div class="mobile-filter-content d-md-none <?php echo $has_active_filters ? 'show' : ''; ?>" id="mobileFilterContent">
              <div class="mobile-filter-container">

                  <?php if($has_active_filters): ?>
                  <div class="active-filters">
                      <?php if(!empty($search)): ?>
                      <span class="active-filter-badge">
                          Cari: <?php echo htmlspecialchars($search); ?>
                          <i class="fas fa-times" data-filter="search"></i>
                      </span>
                      <?php endif; ?>
                      
                      <?php if(!empty($date_from) || !empty($date_to)): ?>
                      <span class="active-filter-badge">
                          Tanggal: <?php echo !empty($date_from) ? date('d/m/Y', strtotime($date_from)) : ''; ?> 
                          <?php echo (!empty($date_from) && !empty($date_to)) ? '-' : ''; ?> 
                          <?php echo !empty($date_to) ? date('d/m/Y', strtotime($date_to)) : ''; ?>
                          <i class="fas fa-times" data-filter="date"></i>
                      </span>
                      <?php endif; ?>
                      
                      <?php if(!empty($status_filter)): ?>
                      <span class="active-filter-badge">
                          Status: <?php 
                              switch($status_filter) {
                                  case 'diproses': echo 'Diproses'; break;
                                  case 'selesai': echo 'Selesai'; break;
                                  case 'dibatalkan': echo 'Dibatalkan'; break;
                              }
                          ?>
                          <i class="fas fa-times" data-filter="status"></i>
                      </span>
                      <?php endif; ?>
                      
                      <?php if(!empty($payment_filter)): ?>
                      <span class="active-filter-badge">
                          Pembayaran: <?php 
                              switch($payment_filter) {
                                  case 'belum_dibayar': echo 'Belum Dibayar'; break;
                                  case 'sudah_dibayar': echo 'Sudah Dibayar'; break;
                              }
                          ?>
                          <i class="fas fa-times" data-filter="payment"></i>
                      </span>
                      <?php endif; ?>
                  </div>
                  <?php endif; ?>
                  

                  <div class="quick-filters mb-3">
                      <button type="button" class="quick-filter-btn <?php echo empty($status_filter) ? 'active' : ''; ?>" data-filter="status" data-value="">Semua</button>
                      <button type="button" class="quick-filter-btn <?php echo $status_filter === 'diproses' ? 'active' : ''; ?>" data-filter="status" data-value="diproses">Diproses</button>
                      <button type="button" class="quick-filter-btn <?php echo $status_filter === 'selesai' ? 'active' : ''; ?>" data-filter="status" data-value="selesai">Selesai</button>
                      <button type="button" class="quick-filter-btn <?php echo $status_filter === 'dibatalkan' ? 'active' : ''; ?>" data-filter="status" data-value="dibatalkan">Dibatalkan</button>
                  </div>
                  

                  <div class="quick-filters mb-3">
                      <button type="button" class="quick-filter-btn <?php echo empty($payment_filter) ? 'active' : ''; ?>" data-filter="payment" data-value="">Semua Bayar</button>
                      <button type="button" class="quick-filter-btn <?php echo $payment_filter === 'belum_dibayar' ? 'active' : ''; ?>" data-filter="payment" data-value="belum_dibayar">Belum Bayar</button>
                      <button type="button" class="quick-filter-btn <?php echo $payment_filter === 'sudah_dibayar' ? 'active' : ''; ?>" data-filter="payment" data-value="sudah_dibayar">Sudah Bayar</button>
                  </div>
                  
                  <form method="GET" action="" id="mobileFilterForm">

                      <div class="mb-3">
                          <label for="mobile-search" class="filter-label">Cari Pesanan</label>
                          <div class="input-group">
                              <span class="input-group-text"><i class="fas fa-search"></i></span>
                              <input type="text" class="form-control" id="mobile-search" name="search" placeholder="Nama/No.HP" value="<?php echo htmlspecialchars($search); ?>">
                          </div>
                      </div>
                      

                      <div class="mb-3">
                          <label class="filter-label">Rentang Tanggal</label>
                          <div class="date-range-container">
                              <input type="text" class="form-control date-picker" id="mobile-date-from" name="date_from" placeholder="Dari" value="<?php echo htmlspecialchars($date_from); ?>">
                              <span class="date-separator d-block d-md-none mb-2">sampai</span>
                              <input type="text" class="form-control date-picker" id="mobile-date-to" name="date_to" placeholder="Sampai" value="<?php echo htmlspecialchars($date_to); ?>">
                          </div>
                      </div>
                      

                      <input type="hidden" id="mobile-status" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                      <input type="hidden" id="mobile-payment" name="payment" value="<?php echo htmlspecialchars($payment_filter); ?>">
                      

                      <div class="d-flex justify-content-between">
                          <a href="pesanan.php" class="btn btn-outline-secondary">Reset</a>
                          <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                      </div>
                  </form>
              </div>
          </div>
          
          <!-- Desktop Filter Container -->
          <div class="search-filter-container d-none d-md-block">
              <form method="GET" action="" id="filterForm">
                  <div class="row">
                      <div class="col-12 col-md-4">
                          <div class="mb-3">
                              <label for="search" class="filter-label">Cari Pesanan</label>
                              <div class="input-group">
                                  <span class="input-group-text"><i class="fas fa-search"></i></span>
                                  <input type="text" class="form-control filter-control" autocomplete="off" id="search" name="search" placeholder="Nama/No.HP" value="<?php echo htmlspecialchars($search); ?>">
                              </div>
                          </div>
                      </div>
                      
                      <div class="col-12 col-md-4">
                          <div class="mb-3">
                              <label class="filter-label">Rentang Tanggal</label>
                              <div class="date-range-container">
                                  <input type="text" class="form-control date-picker filter-control" id="date_from" name="date_from" placeholder="Dari" value="<?php echo htmlspecialchars($date_from); ?>">
                                  <span class="date-separator d-none d-md-block">-</span>
                                  <span class="date-separator d-block d-md-none mb-2">sampai</span>
                                  <input type="text" class="form-control date-picker filter-control" id="date_to" name="date_to" placeholder="Sampai" value="<?php echo htmlspecialchars($date_to); ?>">
                              </div>
                          </div>
                      </div>
                      
                      <div class="col-12 col-md-4">
                          <div class="row">
                              <div class="col-6">
                                  <div class="mb-3">
                                      <label for="status" class="filter-label">Status Pesanan</label>
                                      <select class="form-select filter-control" id="status" name="status">
                                          <option value="">Semua Status</option>
                                          <option value="diproses" <?php echo $status_filter === 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                          <option value="selesai" <?php echo $status_filter === 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                          <option value="dibatalkan" <?php echo $status_filter === 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                                      </select>
                                  </div>
                              </div>
                              <div class="col-6">
                                  <div class="mb-3">
                                      <label for="payment" class="filter-label">Status Pembayaran</label>
                                      <select class="form-select filter-control" id="payment" name="payment">
                                          <option value="">Semua Status</option>
                                          <option value="belum_dibayar" <?php echo $payment_filter === 'belum_dibayar' ? 'selected' : ''; ?>>Belum Dibayar</option>
                                          <option value="sudah_dibayar" <?php echo $payment_filter === 'sudah_dibayar' ? 'selected' : ''; ?>>Sudah Dibayar</option>
                                      </select>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
                  
                  <div class="d-flex justify-content-end position-relative">
                      <a href="pesanan.php" class="btn btn-outline-secondary me-2">Reset</a>
                      <div class="position-relative">
                          <button type="submit" class="btn btn-primary apply-filter-btn" id="applyFilterBtn">Terapkan Filter</button>
                      </div>
                  </div>
              </form>
          </div>
          
          <div class="table-container">
              <?php if ($result->num_rows > 0): ?>
                  <div class="desktop-table">
                      <div class="table-responsive">
                          <table class="table table-hover">
                              <thead>
                                  <tr>
                                      <th width="60">No</th>
                                      <th>Tanggal</th>
                                      <th>Pelanggan</th>
                                      <th>Paket</th>
                                      <th>Total</th>
                                      <th>Status</th>
                                      <th>Pembayaran</th>
                                      <th>Aksi</th>
                                  </tr>
                              </thead>
                              <tbody>
                                  <?php 
                                  $result->data_seek(0);
                                  $no = 1;
                                  while ($row = $result->fetch_assoc()): 
                                  ?>
                                      <tr>
                                          <td><?php echo $no++; ?></td>
                                          <td><?php echo formatTanggalSingkat($row['waktu']); ?></td>
                                          <td>
                                              <div><?php echo $row['nama_pelanggan']; ?></div>
                                              <small class="text-muted"><?php echo $row['no_hp']; ?></small>
                                          </td>
                                          <td>
                                              <?php if ($row['jumlah_item'] > 1): ?>
                                                  <div>
                                                      Beberapa Pesanan <span class="multiple-items-badge"><?php echo $row['jumlah_item']; ?></span>
                                                  </div>
                                                  <div class="paket-list">
                                                      <?php echo getPaketSummary($row['paket_list'], $row['berat_list']); ?>
                                                  </div>
                                              <?php else: ?>
                                                  <?php echo $row['paket_list']; ?>
                                              <?php endif; ?>
                                          </td>
                                          <td>Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></td>
                                          <td><?php echo getStatusBadge($row['status']); ?></td>
                                          <td><?php echo getPaymentStatusBadge($row['status_pembayaran']); ?></td>
                                          <td>
                                              <a href="detail_pesanan.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info btn-action" title="Detail">
                                                  <i class="fas fa-eye"></i>
                                              </a>
                                          </td>
                                      </tr>
                                  <?php endwhile; ?>
                              </tbody>
                          </table>
                      </div>
                  </div>
                  
                  <!-- Mobile Card View -->
                  <div class="mobile-cards">
                      <?php 

                      $result->data_seek(0);
                      $no = 1;
                      while ($row = $result->fetch_assoc()): 
                      ?>
                          <div class="order-card">
                              <div class="order-card-header">
                                  <div><strong>No. <?php echo $no++; ?></strong></div>
                                  <div><?php echo formatTanggalSingkat($row['waktu']); ?></div>
                              </div>
                              <div class="order-card-body">
                                  <div class="order-card-item">
                                      <div class="order-card-label">Pelanggan:</div>
                                      <div class="order-card-value"><?php echo $row['nama_pelanggan']; ?></div>
                                  </div>
                                  <div class="order-card-item">
                                      <div class="order-card-label">No. HP:</div>
                                      <div class="order-card-value"><?php echo $row['no_hp']; ?></div>
                                  </div>
                                  <div class="order-card-item">
                                      <div class="order-card-label">Paket:</div>
                                      <div class="order-card-value">
                                          <?php if ($row['jumlah_item'] > 1): ?>
                                              <div>
                                                  Beberapa Pesanan <span class="multiple-items-badge"><?php echo $row['jumlah_item']; ?></span>
                                              </div>
                                              <div class="paket-list">
                                                  <?php echo getPaketSummary($row['paket_list'], $row['berat_list']); ?>
                                              </div>
                                          <?php else: ?>
                                              <?php echo $row['paket_list']; ?>
                                          <?php endif; ?>
                                      </div>
                                  </div>
                                  <div class="order-card-item">
                                      <div class="order-card-label">Total Harga:</div>
                                      <div class="order-card-value">Rp <?php echo number_format($row['total_harga'], 0, ',', '.'); ?></div>
                                  </div>
                                  <div class="order-card-item">
                                      <div class="order-card-label">Status:</div>
                                      <div class="order-card-value"><?php echo getStatusBadge($row['status']); ?></div>
                                  </div>
                                  <div class="order-card-item">
                                      <div class="order-card-label">Pembayaran:</div>
                                      <div class="order-card-value"><?php echo getPaymentStatusBadge($row['status_pembayaran']); ?></div>
                                  </div>
                              </div>
                              <div class="order-card-footer">
                                  <a href="detail_pesanan.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info btn-action w-100" title="Detail">
                                      <i class="fas fa-eye"></i> Detail
                                  </a>
                              </div>
                          </div>
                      <?php endwhile; ?>
                  </div>
              <?php else: ?>
                  <div class="no-data">
                      <i class="fas fa-search"></i>
                      <p>Tidak ada pesanan yang ditemukan</p>
                      <a href="tambah_pesanan.php" class="btn btn-primary">Tambah Pesanan Baru</a>
                  </div>
              <?php endif; ?>
          </div>
      </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
      document.addEventListener('DOMContentLoaded', function() {
          flatpickr(".date-picker", {
              dateFormat: "Y-m-d",
              locale: "id",
              allowInput: true,
              altInput: true,
              altFormat: "d F Y",
              maxDate: "today"
          });
          

          const mobileFilterToggle = document.getElementById('mobileFilterToggle');
          const mobileFilterContent = document.getElementById('mobileFilterContent');
          
          if (mobileFilterToggle) {
              mobileFilterToggle.addEventListener('click', function() {
                  this.classList.toggle('active');
                  mobileFilterContent.classList.toggle('show');
                  

                  const icon = this.querySelector('i');
                  if (icon) {
                      if (mobileFilterContent.classList.contains('show')) {
                          icon.className = 'fas fa-chevron-up';
                      } else {
                          icon.className = 'fas fa-filter';
                      }
                  }
              });
          }
          

          const quickFilterBtns = document.querySelectorAll('.quick-filter-btn');
          quickFilterBtns.forEach(btn => {
              btn.addEventListener('click', function() {
                  const filter = this.getAttribute('data-filter');
                  const value = this.getAttribute('data-value');
                  
                  document.getElementById('mobile-' + filter).value = value;
                  
                  document.querySelectorAll(`.quick-filter-btn[data-filter="${filter}"]`).forEach(b => {
                      b.classList.remove('active');
                  });
                  this.classList.add('active');
                  
                  document.getElementById('mobileFilterForm').submit();
              });
          });
          

          const removeFilterBtns = document.querySelectorAll('.active-filter-badge i');
          removeFilterBtns.forEach(btn => {
              btn.addEventListener('click', function() {
                  const filter = this.getAttribute('data-filter');
                  
                  if (filter === 'search') {
                      document.getElementById('mobile-search').value = '';
                  } else if (filter === 'date') {
                      document.getElementById('mobile-date-from').value = '';
                      document.getElementById('mobile-date-to').value = '';
                  } else {
                      document.getElementById('mobile-' + filter).value = '';
                  }
                  
                  document.getElementById('mobileFilterForm').submit();
              });
          });
          
          let filterChanged = false;
          
          const filterControls = document.querySelectorAll('.filter-control');
          const filterOverlay = document.getElementById('filterOverlay');
          const applyFilterBtn = document.getElementById('applyFilterBtn');
          const closeOverlayBtn = document.getElementById('closeOverlayBtn');
          
          let filterReminder = document.getElementById('filterReminder');
          if (!filterReminder) {
              filterReminder = document.createElement('div');
              filterReminder.id = 'filterReminder';
              filterReminder.className = 'filter-reminder';
              document.body.appendChild(filterReminder);
          }

          filterControls.forEach(control => {
              control.setAttribute('data-initial-value', control.value);
          });
          
          filterControls.forEach(control => {
              const initialValue = control.value;
              
              control.addEventListener('focus', function() {
                  showFilterReminders();
              });
              
              control.addEventListener('input', function() {
                  showFilterReminders();
              });
              
              control.addEventListener('change', function() {
                  showFilterReminders();
              });
              
              if (control.tagName === 'SELECT') {
                  control.addEventListener('change', function() {
                      showFilterReminders();
                  });
              }
          });
          
          function showFilterReminders() {
              filterChanged = true;
              filterOverlay.style.display = 'block';
              
              if (window.innerWidth <= 768) {
                  filterReminder.innerHTML = '<i class="fas fa-arrow-up me-2"></i> Klik "Terapkan Filter"';
              } else {
                  filterReminder.innerHTML = 'Jangan lupa klik "Terapkan Filter" <i class="fas fa-arrow-up me-2"></i>';
              }
              
              filterReminder.style.display = 'block';
              closeOverlayBtn.style.display = 'flex';
              applyFilterBtn.classList.add('highlight');
              
              setTimeout(() => {
                  if (!isElementInViewport(applyFilterBtn)) {
                      window.scrollTo({
                          top: document.querySelector('.search-filter-container').offsetTop,
                          behavior: 'smooth'
                      });
                  }
              }, 300);
          }
          
          function hideFilterReminders() {
              filterOverlay.style.display = 'none';
              filterReminder.style.display = 'none';
              closeOverlayBtn.style.display = 'none';
              applyFilterBtn.classList.remove('highlight');
              filterChanged = false;
          }
          
          function isElementInViewport(el) {
              const rect = el.getBoundingClientRect();
              return (
                  rect.top >= 0 &&
                  rect.left >= 0 &&
                  rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                  rect.right <= (window.innerWidth || document.documentElement.clientWidth)
              );
          }
          
          document.getElementById('filterForm').addEventListener('submit', function() {
              hideFilterReminders();
          });

          if (filterOverlay) {
              filterOverlay.addEventListener('click', function(e) {
                  if (e.target === filterOverlay) {
                      resetFilterForm();
                      
                      hideFilterReminders();
                  }
              });
          }
          
          if (closeOverlayBtn) {
              closeOverlayBtn.addEventListener('click', function() {
                  resetFilterForm();
                  
                  hideFilterReminders();
              });
          }

          function resetFilterForm() {
              filterControls.forEach(control => {
                  const initialValue = control.getAttribute('data-initial-value');
                  if (initialValue !== null) {
                      control.value = initialValue;
                      
                      if (control.classList.contains('date-picker') && control._flatpickr) {
                          control._flatpickr.setDate(initialValue, true);
                      }
                  }
              });
          }
      });

      document.addEventListener('DOMContentLoaded', function() {
          const tableRows = document.querySelectorAll('.table tbody tr');
          tableRows.forEach(row => {
              row.addEventListener('mouseenter', function() {
                  const processingBadge = this.querySelector('.status-processing');
                  const unpaidBadge = this.querySelector('.payment-unpaid');
                  
                  if (processingBadge) {
                      processingBadge.style.animation = 'pulse-warning 1s infinite alternate';
                  }
                  
                  if (unpaidBadge) {
                      unpaidBadge.style.animation = 'pulse-danger 1s infinite alternate';
                  }
              });
              
              row.addEventListener('mouseleave', function() {
                  const processingBadge = this.querySelector('.status-processing');
                  const unpaidBadge = this.querySelector('.payment-unpaid');
                  
                  if (processingBadge) {
                      processingBadge.style.animation = '';
                  }
                  
                  if (unpaidBadge) {
                      unpaidBadge.style.animation = '';
                  }
              });
          });
          
          const orderCards = document.querySelectorAll('.order-card');
          orderCards.forEach(card => {
              card.addEventListener('mouseenter', function() {
                  const processingBadge = this.querySelector('.status-processing');
                  const unpaidBadge = this.querySelector('.payment-unpaid');
                  
                  if (processingBadge) {
                      processingBadge.style.animation = 'pulse-warning 1s infinite alternate';
                  }
                  
                  if (unpaidBadge) {
                      unpaidBadge.style.animation = 'pulse-danger 1s infinite alternate';
                  }
              });
              
              card.addEventListener('mouseleave', function() {
                  const processingBadge = this.querySelector('.status-processing');
                  const unpaidBadge = this.querySelector('.payment-unpaid');
                  
                  if (processingBadge) {
                      processingBadge.style.animation = '';
                  }
                  
                  if (unpaidBadge) {
                      unpaidBadge.style.animation = '';
                  }
              });
          });
      });
  </script>
</body>
</html>


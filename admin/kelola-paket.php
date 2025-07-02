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

// Inisialisasi variabel pencarian
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Proses tambah paket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_paket'])) {
  $nama = $_POST['nama'];
  $harga = $_POST['harga'];
  $keterangan = $_POST['keterangan'];
  
  // Handle file upload
  $icon = '';
  if (isset($_FILES['icon']) && $_FILES['icon']['error'] == 0) {
      // Validasi ekstensi file
      $allowed_extensions = ['png', 'jpg', 'jpeg'];
      $file_extension = strtolower(pathinfo($_FILES['icon']['name'], PATHINFO_EXTENSION));

      if (in_array($file_extension, $allowed_extensions)) {
          $upload_dir = '../assets/uploads/paket_icons/';
          if (!is_dir($upload_dir)) {
              mkdir($upload_dir, 0777, true); // Buat folder jika belum ada
          }

          // Generate nama file unik dan move file ke folder tujuan
          $icon = uniqid('icon_') . '.' . $file_extension;
          move_uploaded_file($_FILES['icon']['tmp_name'], $upload_dir . $icon);
      } else {
          $error_message = 'Hanya file gambar (PNG, JPG, JPEG) yang diperbolehkan!';
      }
  } else {
      $error_message = 'Harap pilih file icon untuk paket!';
  }

  if (!isset($error_message)) {
      $stmt = $conn->prepare("INSERT INTO paket (nama, harga, keterangan, icon) VALUES (?, ?, ?, ?)");
      $stmt->bind_param("siss", $nama, $harga, $keterangan, $icon);
      
      if ($stmt->execute()) {
          $success_message = "Paket berhasil ditambahkan.";
      } else {
          $error_message = "Gagal menambahkan paket: " . $conn->error;
      }
      
      $stmt->close();
  }
}

// Proses edit paket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_paket'])) {
  $id = $_POST['id'];
  $nama = $_POST['nama'];
  $harga = $_POST['harga'];
  $keterangan = $_POST['keterangan'];
  $current_icon = $_POST['current_icon'];
  
  // Handle file upload
  $icon = $current_icon; // Default to current icon if no new upload
  if (isset($_FILES['icon']) && $_FILES['icon']['error'] == 0) {
      // Validasi ekstensi file
      $allowed_extensions = ['png', 'jpg', 'jpeg'];
      $file_extension = strtolower(pathinfo($_FILES['icon']['name'], PATHINFO_EXTENSION));

      if (in_array($file_extension, $allowed_extensions)) {
          $upload_dir = '../assets/uploads/paket_icons/';
          if (!is_dir($upload_dir)) {
              mkdir($upload_dir, 0777, true); // Buat folder jika belum ada
          }

          // Generate nama file unik dan move file ke folder tujuan
          $icon = uniqid('icon_') . '.' . $file_extension;
          move_uploaded_file($_FILES['icon']['tmp_name'], $upload_dir . $icon);
          
          // Hapus file icon lama jika ada
          if (!empty($current_icon)) {
              $old_icon_path = $upload_dir . $current_icon;
              if (file_exists($old_icon_path)) {
                  unlink($old_icon_path);
              }
          }
      } else {
          $error_message = 'Hanya file gambar (PNG, JPG, JPEG) yang diperbolehkan!';
      }
  }

  if (!isset($error_message)) {
      $stmt = $conn->prepare("UPDATE paket SET nama = ?, harga = ?, keterangan = ?, icon = ? WHERE id = ?");
      $stmt->bind_param("sissi", $nama, $harga, $keterangan, $icon, $id);
      
      if ($stmt->execute()) {
          $success_message = "Paket berhasil diperbarui.";
      } else {
          $error_message = "Gagal memperbarui paket: " . $conn->error;
      }
      
      $stmt->close();
  }
}

// Proses hapus paket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_paket'])) {
  $id = $_POST['id'];
  
  // Periksa apakah paket sedang digunakan dalam pesanan
  $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM pesanan WHERE id_paket = ?");
  $check_stmt->bind_param("i", $id);
  $check_stmt->execute();
  $check_result = $check_stmt->get_result();
  $check_data = $check_result->fetch_assoc();
  
  if ($check_data['count'] > 0) {
      $error_message = "Paket tidak dapat dihapus karena sedang digunakan dalam pesanan.";
  } else {
      // Ambil nama icon sebelum menghapus paket
      $icon_stmt = $conn->prepare("SELECT icon FROM paket WHERE id = ?");
      $icon_stmt->bind_param("i", $id);
      $icon_stmt->execute();
      $icon_result = $icon_stmt->get_result();
      $icon_data = $icon_result->fetch_assoc();
      $icon_name = $icon_data['icon'];
      
      $stmt = $conn->prepare("DELETE FROM paket WHERE id = ?");
      $stmt->bind_param("i", $id);
      
      if ($stmt->execute()) {
          // Hapus file icon jika ada
          if (!empty($icon_name)) {
              $icon_path = '../assets/uploads/paket_icons/' . $icon_name;
              if (file_exists($icon_path)) {
                  unlink($icon_path);
              }
          }
          $success_message = "Paket berhasil dihapus.";
      } else {
          $error_message = "Gagal menghapus paket: " . $conn->error;
      }
      
      $stmt->close();
      $icon_stmt->close();
  }
  
  $check_stmt->close();
}

// Ambil semua data paket
// Ubah query untuk tidak menampilkan Paket Khusus
$query = "SELECT * FROM paket WHERE 1=1";

// Tambahkan kondisi pencarian jika ada
if (!empty($search)) {
  $search_query = "%$search%";
  $query .= " AND nama LIKE '$search_query'";
}

$query .= " AND nama != 'Paket Khusus' ORDER BY nama ASC";
$result = $conn->query($query);

// Cek apakah ada filter aktif
$has_active_filters = !empty($search);

// AJAX handler untuk pencarian real-time
if (isset($_GET['ajax_search'])) {
  $search = isset($_GET['search']) ? $_GET['search'] : '';
  
  // Buat query dasar untuk paket
  $query = "SELECT * FROM paket WHERE 1=1";
  
  // Tambahkan kondisi pencarian jika ada
  if (!empty($search)) {
      $search_query = "%$search%";
      $query .= " AND nama LIKE '$search_query'";
  }
  
  $query .= " AND nama != 'Paket Khusus' ORDER BY nama ASC";
  $result = $conn->query($query);
  
  // Check if we have results
  if ($result->num_rows > 0) {
      // Desktop Table View
      echo '<div class="desktop-table">
              <div class="table-responsive">
                  <table class="table table-hover" id="paketTable">
                      <thead>
                          <tr>
                              <th width="60">No</th>
                              <th width="20%">Nama Paket</th>
                              <th width="15%">Harga</th>
                              <th width="35%">Keterangan</th>
                              <th width="10%">Icon</th>
                              <th width="15%">Aksi</th>
                          </tr>
                      </thead>
                      <tbody>';
      
      $no = 1;
      while ($row = $result->fetch_assoc()) {
          echo '<tr data-id="' . $row['id'] . '">
                  <td>' . $no++ . '</td>
                  <td>' . htmlspecialchars($row['nama']) . '</td>
                  <td class="price-format">Rp ' . number_format($row['harga'], 0, ',', '.') . '</td>
                  <td>
                      <div class="keterangan-text" style="max-height: 100px; overflow-y: auto;">
                          ' . htmlspecialchars($row['keterangan']) . '
                      </div>
                  </td>
                  <td><img src="../assets/uploads/paket_icons/' . htmlspecialchars($row['icon']) . '" class="icon-preview" alt="' . htmlspecialchars($row['nama']) . '"></td>
                  <td>
                      <button type="button" class="btn btn-sm btn-warning btn-action edit-btn" 
                              data-id="' . $row['id'] . '"
                              data-nama="' . htmlspecialchars($row['nama']) . '"
                              data-harga="' . $row['harga'] . '"
                              data-keterangan="' . htmlspecialchars($row['keterangan']) . '"
                              data-icon="' . htmlspecialchars($row['icon']) . '">
                          <i class="fas fa-edit"></i>
                      </button>
                      <button type="button" class="btn btn-sm btn-danger btn-action delete-btn" 
                              data-id="' . $row['id'] . '"
                              data-nama="' . htmlspecialchars($row['nama']) . '">
                          <i class="fas fa-trash"></i>
                      </button>
                  </td>
              </tr>';
      }
      
      echo '</tbody>
          </table>
      </div>
  </div>';
      
      // Mobile Card View
      echo '<div class="mobile-cards">';
      
      // Reset pointer to beginning of result set
      $result->data_seek(0);
      $no = 1;
      
      while ($row = $result->fetch_assoc()) {
          echo '<div class="paket-card" data-id="' . $row['id'] . '">
                  <div class="paket-card-header">
                      No. ' . $no++ . '
                  </div>
                  <div class="paket-card-body">
                      <div class="paket-card-title">' . htmlspecialchars($row['nama']) . '</div>
                      <div class="paket-card-price">Rp ' . number_format($row['harga'], 0, ',', '.') . '</div>
                      
                      ' . (!empty($row['keterangan']) ? '<div class="paket-card-item">
                          <div class="paket-card-label">Keterangan:</div>
                          <div class="paket-card-description">
                              ' . htmlspecialchars($row['keterangan']) . '
                          </div>
                      </div>' : '') . '
                      
                      <div class="paket-card-item">
                          <div class="paket-card-label">Icon:</div>
                          <div class="paket-card-value">
                              <img src="../assets/uploads/paket_icons/' . htmlspecialchars($row['icon']) . '" class="icon-preview" alt="' . htmlspecialchars($row['nama']) . '">
                          </div>
                      </div>
                  </div>
                  <div class="paket-card-footer">
                      <button type="button" class="btn btn-sm btn-warning btn-action edit-btn" 
                              data-id="' . $row['id'] . '"
                              data-nama="' . htmlspecialchars($row['nama']) . '"
                              data-harga="' . $row['harga'] . '"
                              data-keterangan="' . htmlspecialchars($row['keterangan']) . '"
                              data-icon="' . htmlspecialchars($row['icon']) . '">
                          <i class="fas fa-edit"></i> Edit
                      </button>
                      <button type="button" class="btn btn-sm btn-danger btn-action delete-btn" 
                              data-id="' . $row['id'] . '"
                              data-nama="' . htmlspecialchars($row['nama']) . '">
                          <i class="fas fa-trash"></i> Hapus
                      </button>
                  </div>
              </div>';
      }
      
      echo '</div>';
  } else {
      // No results found
      echo '<div class="no-data">
              <i class="fas fa-box-open"></i>
              <p>Tidak ada paket yang ditemukan</p>
              <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahPaketModal">
                  Tambah Paket Baru
              </button>
          </div>';
  }
  
  exit; // Terminate script after AJAX response
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola Paket</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/admin/styles.css">
  <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
      .search-filter-container {
          background-color: #f8f9fa;
          border-radius: 15px;
          padding: 20px;
          margin-bottom: 25px;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      }
      
      .table-container {
          background-color: white;
          border-radius: 15px;
          padding: 20px;
          box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
          margin-bottom: 30px;
          overflow-x: auto;
      }
      
      .table-responsive {
          overflow-x: auto;
          -webkit-overflow-scrolling: touch;
      }
      
      .table {
          min-width: 600px;
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

      h5 {
          color: #42c3cf;
          font-weight: bold;
      }
      
      .btn-action {
          padding: 5px 10px;
          margin: 0 2px;
          border-radius: 5px;
          min-width: 36px;
      }
      
      .btn-add-paket {
          background-color: #42c3cf;
          color: white;
          border-radius: 40px;
          padding: 20px 25px;
          font-size: 16px;
          font-weight: 500;
          margin-bottom: 20px;
          box-shadow: 0 4px 10px rgba(66, 195, 207, 0.3);
          transition: all 0.3s ease;
          border: none;
      }
      
      .btn-add-paket:hover {
          background-color: #38adb8;
          transform: translateY(-2px);
          box-shadow: 0 6px 15px rgba(66, 195, 207, 0.4);
          color: white;
      }
      
      .btn-add-paket i {
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
      
      /* Mobile card view styles */
      .paket-card {
          border: 1px solid #dee2e6;
          border-radius: 10px;
          margin-bottom: 15px;
          overflow: hidden;
          background-color: white;
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      }
      
      .paket-card-header {
          background-color: #42c3cf;
          padding: 10px 15px;
          color: white;
          font-weight: bold;
          text-align: center;
      }
      
      .paket-card-body {
          padding: 15px;
      }
      
      .paket-card-title {
          font-size: 18px;
          font-weight: bold;
          margin-bottom: 5px;
      }
      
      .paket-card-price {
          font-size: 16px;
          font-weight: 600;
          color: #28a745;
          margin-bottom: 15px;
      }
      
      .paket-card-item {
          margin-bottom: 12px;
      }
      
      .paket-card-label {
          font-weight: bold;
          color: #495057;
          margin-bottom: 5px;
          display: block;
      }
      
      .paket-card-value {
          color: #343a40;
          line-height: 1.5;
      }
      
      .paket-card-description {
          background-color: #f8f9fa;
          padding: 10px;
          border-radius: 5px;
          border-left: 3px solid #42c3cf;
          margin-top: 5px;
          font-size: 14px;
          line-height: 1.6;
      }
      
      .paket-card-footer {
          display: flex;
          justify-content: space-between;
          gap: 10px;
          padding: 10px 15px;
          background-color: #f8f9fa;
          border-top: 1px solid #dee2e6;
      }
      
      .price-format {
          font-weight: 600;
          color: #28a745;
      }
      
      .icon-preview {
          width: 50px;
          height: 50px;
          object-fit: contain;
      }
      
      /* Estilos para el botón de filtro móvil */
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
      
      /* Estilos para el contenedor de filtros móvil */
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
      
      /* Estilos para los filtros activos */
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
      
      /* Estilos para el contenedor de filtros móvil */
      .mobile-filter-content {
          max-height: 0;
          overflow: hidden;
          transition: max-height 0.3s ease;
      }
      
      .mobile-filter-content.show {
          max-height: 1000px; /* Altura máxima suficiente para mostrar todo el contenido */
      }
      
      @media (max-width: 768px) {
          .search-filter-container .row > div {
              margin-bottom: 15px;
          }
          
          .search-filter-container {
              padding: 12px;
              display: none; /* Ocultar el contenedor de filtros en móvil por defecto */
          }
          
          /* Mostrar el botón de filtro en móvil */
          .mobile-filter-toggle {
              display: flex;
              align-items: center;
              justify-content: space-between;
          }
          
          /* Mostrar el contenedor de filtros móvil */
          .mobile-filter-container {
              display: block;
          }
          
          /* Hide desktop table on mobile */
          .desktop-table {
              display: none;
          }
          
          /* Show mobile cards on mobile */
          .mobile-cards {
              display: block;
          }
          
          .btn-action {
              flex: 1;
              justify-content: center;
          }
          
          .btn-action i {
              margin-right: 5px;
          }
      }
      
      @media (min-width: 769px) {
          /* Hide mobile cards on desktop */
          .mobile-cards {
              display: none;
          }
          
          /* Show desktop table on desktop */
          .desktop-table {
              display: block;
          }
          
          /* Ocultar elementos móviles en desktop */
          .mobile-filter-toggle,
          .mobile-filter-container {
              display: none;
          }
      }
      
      .highlight {
          background-color: #ffffe0; /* Light yellow */
          transition: background-color 1s ease;
      }
      
      /* Modal styles */
      .modal-content {
          border-radius: 15px;
          border: none;
          box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      }
      
      .modal-header {
          background-color: #42c3cf;
          color: white;
          border-top-left-radius: 15px;
          border-top-right-radius: 15px;
          border-bottom: none;
      }
      
      .modal-footer {
          border-top: none;
      }
      
      .form-label {
          font-weight: 500;
          color: #495057;
      }
      
      /* Alert styles */
      .alert {
          border-radius: 10px;
          margin-bottom: 20px;
      }
  </style>
</head>
<body>

<div class="wrapper">
  <!-- Sidebar -->
  <?php include 'sidebar-admin.php'; ?>

  <!-- Content Wrapper -->
  <div class="content-wrapper">
      <div class="content" id="content">
          <div class="container">
              <div class="current-date">
                  <i class="far fa-calendar-alt"></i> <?php echo $tanggal_sekarang; ?>
              </div>
              
              <div class="d-flex justify-content-between align-items-center mb-4">
                  <h1 class="mb-0">Kelola Paket</h1>
              </div>

              <button type="button" class="btn btn-add-paket" data-bs-toggle="modal" data-bs-target="#tambahPaketModal">
                  <i class="fas fa-plus-circle"></i> Tambah Paket Baru
              </button>
              
              <?php if (isset($success_message)): ?>
                  <div class="alert alert-success alert-dismissible fade show" role="alert">
                      <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>
              <?php endif; ?>
              
              <?php if (isset($error_message)): ?>
                  <div class="alert alert-danger alert-dismissible fade show" role="alert">
                      <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                  </div>
              <?php endif; ?>
              
              <!-- Mobile Filter Toggle Button -->
              <button class="mobile-filter-toggle d-md-none <?php echo $has_active_filters ? 'active' : ''; ?>" id="mobileFilterToggle">
                  <div>
                      <i class="fas fa-search"></i> Cari Paket
                  </div>
                  <?php if($has_active_filters): ?>
                  <span class="badge"><?php echo !empty($search) ? '1' : '0'; ?></span>
                  <?php endif; ?>
              </button>
              
              <!-- Mobile Filter Container -->
              <div class="mobile-filter-content d-md-none <?php echo $has_active_filters ? 'show' : ''; ?>" id="mobileFilterContent">
                  <div class="mobile-filter-container">
                      <!-- Active Filters -->
                      <?php if($has_active_filters): ?>
                      <div class="active-filters">
                          <?php if(!empty($search)): ?>
                          <span class="active-filter-badge">
                              Cari: <?php echo htmlspecialchars($search); ?>
                              <i class="fas fa-times" data-filter="search"></i>
                          </span>
                          <?php endif; ?>
                      </div>
                      <?php endif; ?>
                      
                      <form method="GET" action="" id="mobileFilterForm">
                          <!-- Search Input -->
                          <div class="mb-3">
                              <label for="mobile-search" class="filter-label">Cari Paket</label>
                              <div class="input-group">
                                  <span class="input-group-text"><i class="fas fa-search"></i></span>
                                  <input type="text" class="form-control" id="mobile-search" name="search" placeholder="Cari berdasarkan nama paket" value="<?php echo htmlspecialchars($search); ?>">
                              </div>
                          </div>
                          
                          <!-- Form Actions -->
                          <div class="d-flex justify-content-between">
                              <a href="kelola_paket.php" class="btn btn-outline-secondary">Reset</a>
                              <button type="submit" class="btn btn-primary">Cari</button>
                          </div>
                      </form>
                  </div>
              </div>
              
              <div class="table-container">
                  <!-- Desktop Search Filter Container -->
                  <div class="search-filter-container d-none d-md-block">
                      <div class="row">
                          <div class="col-md-12">
                              <div class="mb-3">
                                  <label for="searchInput" class="filter-label">Cari Paket</label>
                                  <div class="input-group">
                                      <span class="input-group-text"><i class="fas fa-search"></i></span>
                                      <input type="text" class="form-control" id="searchInput" placeholder="Cari berdasarkan nama paket..." value="<?php echo htmlspecialchars($search); ?>">
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
                  
                  <div class="card-header">
                      <h5 class="mb-3">Daftar Paket</h5>
                  </div>

                  <div id="paketData">
                      <!-- Desktop Table View -->
                      <div class="desktop-table">
                          <div class="table-responsive">
                              <table class="table table-hover" id="paketTable">
                                  <thead>
                                      <tr>
                                          <th width="60">No</th>
                                          <th width="20%">Nama Paket</th>
                                          <th width="15%">Harga</th>
                                          <th width="35%">Keterangan</th>
                                          <th width="10%">Icon</th>
                                          <th width="15%">Aksi</th>
                                      </tr>
                                  </thead>
                                  <tbody>
                                      <?php 
                                      if ($result->num_rows > 0) {
                                          $no = 1;
                                          while ($row = $result->fetch_assoc()) {
                                      ?>
                                          <tr data-id="<?php echo $row['id']; ?>">
                                              <td><?php echo $no++; ?></td>
                                              <td><?php echo htmlspecialchars($row['nama']); ?></td>
                                              <td class="price-format">Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></td>
                                              <td>
                                                  <div class="keterangan-text" style="max-height: 100px; overflow-y: auto;">
                                                      <?php echo htmlspecialchars($row['keterangan']); ?>
                                                  </div>
                                              </td>
                                              <td><img src="../assets/uploads/paket_icons/<?php echo htmlspecialchars($row['icon']); ?>" class="icon-preview" alt="<?php echo htmlspecialchars($row['nama']); ?>"></td>
                                              <td>
                                                  <button type="button" class="btn btn-sm btn-warning btn-action edit-btn" 
                                                          data-id="<?php echo $row['id']; ?>"
                                                          data-nama="<?php echo htmlspecialchars($row['nama']); ?>"
                                                          data-harga="<?php echo $row['harga']; ?>"
                                                          data-keterangan="<?php echo htmlspecialchars($row['keterangan']); ?>"
                                                          data-icon="<?php echo htmlspecialchars($row['icon']); ?>">
                                                      <i class="fas fa-edit"></i>
                                                  </button>
                                                  <button type="button" class="btn btn-sm btn-danger btn-action delete-btn" 
                                                          data-id="<?php echo $row['id']; ?>"
                                                          data-nama="<?php echo htmlspecialchars($row['nama']); ?>">
                                                      <i class="fas fa-trash"></i>
                                                  </button>
                                              </td>
                                          </tr>
                                      <?php 
                                          }
                                      } else {
                                      ?>
                                          <tr>
                                              <td colspan="6">
                                                  <div class="no-data">
                                                      <i class="fas fa-box-open"></i>
                                                      <p>Belum ada paket yang tersedia</p>
                                                      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahPaketModal">
                                                          Tambah Paket Baru
                                                      </button>
                                                  </div>
                                              </td>
                                          </tr>
                                      <?php
                                      }
                                      ?>
                                  </tbody>
                              </table>
                          </div>
                      </div>

                      <!-- Mobile Card View -->
                      <div class="mobile-cards">
                          <?php 
                          if ($result->num_rows > 0) {
                              // Reset pointer to beginning of result set
                              $result->data_seek(0);
                              $no = 1;
                              while ($row = $result->fetch_assoc()): 
                          ?>
                              <div class="paket-card" data-id="<?php echo $row['id']; ?>">
                                  <div class="paket-card-header">
                                      No. <?php echo $no++; ?>
                                  </div>
                                  <div class="paket-card-body">
                                      <div class="paket-card-title"><?php echo htmlspecialchars($row['nama']); ?></div>
                                      <div class="paket-card-price">Rp <?php echo number_format($row['harga'], 0, ',', '.'); ?></div>
                                      
                                      <?php if (!empty($row['keterangan'])): ?>
                                      <div class="paket-card-item">
                                          <div class="paket-card-label">Keterangan:</div>
                                          <div class="paket-card-description">
                                              <?php echo htmlspecialchars($row['keterangan']); ?>
                                          </div>
                                      </div>
                                      <?php endif; ?>
                                      
                                      <div class="paket-card-item">
                                          <div class="paket-card-label">Icon:</div>
                                          <div class="paket-card-value">
                                              <img src="../assets/uploads/paket_icons/<?php echo htmlspecialchars($row['icon']); ?>" class="icon-preview" alt="<?php echo htmlspecialchars($row['nama']); ?>">
                                          </div>
                                      </div>
                                  </div>
                                  <div class="paket-card-footer">
                                      <button type="button" class="btn btn-sm btn-warning btn-action edit-btn" 
                                              data-id="<?php echo $row['id']; ?>"
                                              data-nama="<?php echo htmlspecialchars($row['nama']); ?>"
                                              data-harga="<?php echo $row['harga']; ?>"
                                              data-keterangan="<?php echo htmlspecialchars($row['keterangan']); ?>"
                                              data-icon="<?php echo htmlspecialchars($row['icon']); ?>">
                                          <i class="fas fa-edit"></i> Edit
                                      </button>
                                      <button type="button" class="btn btn-sm btn-danger btn-action delete-btn" 
                                              data-id="<?php echo $row['id']; ?>"
                                              data-nama="<?php echo htmlspecialchars($row['nama']); ?>">
                                          <i class="fas fa-trash"></i> Hapus
                                      </button>
                                  </div>
                              </div>
                          <?php 
                              endwhile;
                          } else {
                          ?>
                              <div class="no-data">
                                  <i class="fas fa-box-open"></i>
                                  <p>Belum ada paket yang tersedia</p>
                                  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#tambahPaketModal">
                                      Tambah Paket Baru
                                  </button>
                              </div>
                          <?php
                          }
                          ?>
                      </div>
                  </div>
              </div>
          </div>
      </div>
  </div>
</div>

<!-- Modal Tambah Paket -->
<div class="modal fade" id="tambahPaketModal" tabindex="-1" aria-labelledby="tambahPaketModalLabel" aria-hidden="true">
  <div class="modal-dialog">
      <div class="modal-content">
          <div class="modal-header">
              <h5 class="modal-title text-white" id="tambahPaketModalLabel">Tambah Paket Baru</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
              <div class="modal-body">
                  <div class="mb-3">
                      <label for="namaPaket" class="form-label">Nama Paket <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="namaPaket" name="nama" required>
                  </div>
                  <div class="mb-3">
                      <label for="hargaPaket" class="form-label">Harga <span class="text-danger">*</span></label>
                      <input type="number" class="form-control" id="hargaPaket" name="harga" required>
                  </div>
                  <div class="mb-3">
                      <label for="keteranganPaket" class="form-label">Keterangan</label>
                      <textarea class="form-control" id="keteranganPaket" name="keterangan" rows="3"></textarea>
                  </div>
                  <div class="mb-3">
                      <label for="iconPaket" class="form-label">Icon Paket <span class="text-danger">*</span></label>
                      <input type="file" class="form-control" id="iconPaket" name="icon" accept="image/*" required>
                  </div>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                  <button type="submit" class="btn btn-primary" name="tambah_paket">Simpan</button>
              </div>
          </form>
      </div>
  </div>
</div>

<!-- Modal Edit Paket -->
<div class="modal fade" id="editPaketModal" tabindex="-1" aria-labelledby="editPaketModalLabel" aria-hidden="true">
  <div class="modal-dialog">
      <div class="modal-content">
          <div class="modal-header">
              <h5 class="modal-title text-white" id="editPaketModalLabel">Edit Paket</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
              <input type="hidden" name="id" id="editId">
              <input type="hidden" name="current_icon" id="editCurrentIcon">
              <div class="modal-body">
                  <div class="mb-3">
                      <label for="editNamaPaket" class="form-label">Nama Paket <span class="text-danger">*</span></label>
                      <input type="text" class="form-control" id="editNamaPaket" name="nama" required>
                  </div>
                  <div class="mb-3">
                      <label for="editHargaPaket" class="form-label">Harga <span class="text-danger">*</span></label>
                      <input type="number" class="form-control" id="editHargaPaket" name="harga" required>
                  </div>
                  <div class="mb-3">
                      <label for="editKeteranganPaket" class="form-label">Keterangan</label>
                      <textarea class="form-control" id="editKeteranganPaket" name="keterangan" rows="3"></textarea>
                  </div>
                  <div class="mb-3">
                      <label for="editIconPaket" class="form-label">Icon Paket</label>
                      <input type="file" class="form-control" id="editIconPaket" name="icon" accept="image/*">
                      <div class="mt-2">
                          <img src="/placeholder.svg" id="editIconPreview" alt="Current Icon" style="max-width: 100px; margin-top: 10px;">
                          <div class="form-text">Biarkan kosong jika tidak ingin mengubah icon</div>
                      </div>
                  </div>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                  <button type="submit" class="btn btn-primary" name="edit_paket">Simpan Perubahan</button>
              </div>
          </form>
      </div>
  </div>
</div>

<!-- Modal Hapus Paket -->
<div class="modal fade" id="hapusPaketModal" tabindex="-1" aria-labelledby="hapusPaketModalLabel" aria-hidden="true">
  <div class="modal-dialog">
      <div class="modal-content">
          <div class="modal-header">
              <h5 class="modal-title text-white" id="hapusPaketModalLabel">Konfirmasi Hapus Paket</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
              <input type="hidden" name="id" id="hapusId">
              <div class="modal-body">
                  <p>Apakah Anda yakin ingin menghapus paket <strong id="hapusNamaPaket"></strong>?</p>
                  <p class="text-danger">Tindakan ini tidak dapat dibatalkan.</p>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                  <button type="submit" class="btn btn-danger" name="hapus_paket">Hapus</button>
              </div>
          </form>
      </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
      // Toggle para el filtro móvil
      const mobileFilterToggle = document.getElementById('mobileFilterToggle');
      const mobileFilterContent = document.getElementById('mobileFilterContent');
      
      if (mobileFilterToggle) {
          // Establecer el ícono correcto al cargar la página
          const icon = mobileFilterToggle.querySelector('i');
          if (icon && mobileFilterContent.classList.contains('show')) {
              icon.className = 'fas fa-chevron-up';
          }
          
          mobileFilterToggle.addEventListener('click', function() {
              this.classList.toggle('active');
              mobileFilterContent.classList.toggle('show');
              
              // Cambiar el icono
              const icon = this.querySelector('i');
              if (icon) {
                  if (mobileFilterContent.classList.contains('show')) {
                      icon.className = 'fas fa-chevron-up';
                  } else {
                      icon.className = 'fas fa-search';
                  }
              }
          });
      }
      
      // Manejar los botones de eliminar filtro
      const removeFilterBtns = document.querySelectorAll('.active-filter-badge i');
      removeFilterBtns.forEach(btn => {
          btn.addEventListener('click', function() {
              const filter = this.getAttribute('data-filter');
              
              if (filter === 'search') {
                  document.getElementById('mobile-search').value = '';
              }
              
              // Enviar el formulario automáticamente
              document.getElementById('mobileFilterForm').submit();
          });
      });
      
      // Desktop search functionality
      const searchInput = document.getElementById('searchInput');
      
      if (searchInput) {
          let typingTimer;
          const doneTypingInterval = 500; // Tiempo en ms para esperar después de que el usuario deje de escribir
          
          searchInput.addEventListener('input', function() {
              clearTimeout(typingTimer);
              
              const searchTerm = this.value.trim();
              
              // Esperar a que el usuario deje de escribir antes de realizar la búsqueda
              typingTimer = setTimeout(function() {
                  fetchPakets(searchTerm);
              }, doneTypingInterval);
          });
          
          // Function to fetch pakets with AJAX
          function fetchPakets(searchTerm) {
              // Create XMLHttpRequest
              const xhr = new XMLHttpRequest();
              
              // Configure it
              xhr.open('GET', 'kelola_paket.php?ajax_search=1&search=' + encodeURIComponent(searchTerm), true);
              
              // Set up handler for when request finishes
              xhr.onload = function() {
                  if (xhr.status === 200) {
                      document.getElementById('paketData').innerHTML = xhr.responseText;
                  }
              };
              
              // Send the request
              xhr.send();
          }
      }
      
      // Mobile search functionality
      const mobileSearch = document.getElementById('mobile-search');
      
      if (mobileSearch) {
          mobileSearch.addEventListener('input', function() {
              // No hacemos nada aquí, ya que el formulario móvil se envía manualmente
              // Esto evita que el campo de búsqueda desaparezca mientras se escribe
          });
      }
      
      // Handle edit button click (for both desktop and mobile)
      $(document).on("click", ".edit-btn", function() {
          var id = $(this).data("id");
          var nama = $(this).data("nama");
          var harga = $(this).data("harga");
          var keterangan = $(this).data("keterangan");
          var icon = $(this).data("icon");
          
          $("#editId").val(id);
          $("#editNamaPaket").val(nama);
          $("#editHargaPaket").val(harga);
          $("#editKeteranganPaket").val(keterangan);
          $("#editCurrentIcon").val(icon);
          $("#editIconPreview").attr("src", "../assets/uploads/paket_icons/" + icon);
          
          $("#editPaketModal").modal("show");
      });
      
      // Handle delete button (for both desktop and mobile)
      $(document).on("click", ".delete-btn", function() {
          var id = $(this).data("id");
          var nama = $(this).data("nama");
          
          $("#hapusId").val(id);
          $("#hapusNamaPaket").text(nama);
          
          $("#hapusPaketModal").modal("show");
      });
      
      <?php if (isset($success_message)): ?>
          <?php if (isset($_POST['tambah_paket']) || isset($_POST['edit_paket'])): ?>
              <?php if (isset($_POST['tambah_paket'])): ?>
                  var affectedId = <?php echo $conn->insert_id; ?>;
              <?php else: ?>
                  var affectedId = <?php echo $_POST['id']; ?>;
              <?php endif; ?>
              
              $("tr[data-id='" + affectedId + "'], .paket-card[data-id='" + affectedId + "']").addClass("highlight");
              
              setTimeout(function() {
                  $("tr[data-id='" + affectedId + "'], .paket-card[data-id='" + affectedId + "']").removeClass("highlight");
              }, 3000);
          <?php endif; ?>
      <?php endif; ?>
  });
</script>
</body>
</html>


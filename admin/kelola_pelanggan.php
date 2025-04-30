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

// Format nomor telepon ke format +62
function formatPhoneNumber($phone) {
    // Hapus semua karakter non-digit
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Jika dimulai dengan 0, ganti dengan +62
    if (substr($phone, 0, 1) === '0') {
        $phone = '+62' . substr($phone, 1);
    } 
    // Jika belum ada kode negara, tambahkan +62
    elseif (substr($phone, 0, 2) !== '62' && substr($phone, 0, 3) !== '+62') {
        $phone = '+62' . $phone;
    }
    // Jika dimulai dengan 62 tanpa +, tambahkan +
    elseif (substr($phone, 0, 2) === '62') {
        $phone = '+' . $phone;
    }
    
    return $phone;
}

$tanggal_sekarang = formatTanggalIndonesia(date('Y-m-d H:i:s'));

// Inisialisasi variabel pencarian
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Buat query dasar untuk pelanggan (hanya ambil id, nama, dan no_hp)
$query = "SELECT id, nama, no_hp FROM pelanggan WHERE 1=1";

// Tambahkan kondisi pencarian jika ada
if (!empty($search)) {
    $search_query = "%$search%";
    $query .= " AND (nama LIKE '$search_query' OR no_hp LIKE '$search_query')";
}

// Tambahkan pengurutan
$query .= " ORDER BY nama ASC";

// Eksekusi query
$result = $conn->query($query);

// Proses tambah pelanggan jika ada request
if (isset($_POST['add_customer'])) {
    $nama = $_POST['nama'];
    $no_hp = formatPhoneNumber($_POST['no_hp']);
    
    // Validasi input
    $errors = [];
    
    if (empty($nama)) {
        $errors[] = "Nama pelanggan harus diisi";
    }
    
    if (empty($no_hp)) {
        $errors[] = "Nomor HP harus diisi";
    }
    
    // Cek apakah nomor HP sudah terdaftar
    $check_phone = $conn->prepare("SELECT id FROM pelanggan WHERE no_hp = ?");
    $check_phone->bind_param("s", $no_hp);
    $check_phone->execute();
    $check_result = $check_phone->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Nomor HP sudah terdaftar";
    }
    
    if (empty($errors)) {
        // Tambah pelanggan baru
        $insert_query = $conn->prepare("INSERT INTO pelanggan (nama, no_hp, tanggal_daftar) VALUES (?, ?, NOW())");
        $insert_query->bind_param("ss", $nama, $no_hp);
        
        if ($insert_query->execute()) {
            $success_message = "Pelanggan berhasil ditambahkan";
            // Refresh halaman untuk memperbarui data
            header("Location: kelola_pelanggan.php?added=true");
            exit();
        } else {
            $error_message = "Gagal menambahkan pelanggan: " . $conn->error;
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Proses update pelanggan jika ada request
if (isset($_POST['update_customer'])) {
    $id = $_POST['id'];
    $nama = $_POST['nama'];
    $no_hp = formatPhoneNumber($_POST['no_hp']);
    
    // Validasi input
    $errors = [];
    
    if (empty($nama)) {
        $errors[] = "Nama pelanggan harus diisi";
    }
    
    if (empty($no_hp)) {
        $errors[] = "Nomor HP harus diisi";
    }
    
    // Cek apakah nomor HP sudah terdaftar (kecuali untuk pelanggan yang sedang diedit)
    $check_phone = $conn->prepare("SELECT id FROM pelanggan WHERE no_hp = ? AND id != ?");
    $check_phone->bind_param("si", $no_hp, $id);
    $check_phone->execute();
    $check_result = $check_phone->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "Nomor HP sudah terdaftar untuk pelanggan lain";
    }
    
    if (empty($errors)) {
        // Update data pelanggan
        $update_query = $conn->prepare("UPDATE pelanggan SET nama = ?, no_hp = ? WHERE id = ?");
        $update_query->bind_param("ssi", $nama, $no_hp, $id);
        
        if ($update_query->execute()) {
            $success_message = "Data pelanggan berhasil diperbarui";
            // Refresh halaman untuk memperbarui data
            header("Location: kelola_pelanggan.php?updated=true");
            exit();
        } else {
            $error_message = "Gagal memperbarui data pelanggan: " . $conn->error;
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Proses hapus pelanggan jika ada request
if (isset($_POST['delete_id']) && !empty($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];
    
    // Periksa apakah pelanggan memiliki pesanan
    $check_query = $conn->prepare("SELECT COUNT(*) as count FROM pesanan WHERE id_pelanggan = ?");
    $check_query->bind_param("i", $delete_id);
    $check_query->execute();
    $check_result = $check_query->get_result();
    $check_data = $check_result->fetch_assoc();
    
    if ($check_data['count'] > 0) {
        $delete_error = "Pelanggan tidak dapat dihapus karena memiliki pesanan terkait.";
    } else {
        // Hapus pelanggan jika tidak memiliki pesanan
        $delete_query = $conn->prepare("DELETE FROM pelanggan WHERE id = ?");
        $delete_query->bind_param("i", $delete_id);
        
        if ($delete_query->execute()) {
            $delete_success = "Pelanggan berhasil dihapus.";
            // Refresh halaman untuk memperbarui data
            header("Location: kelola_pelanggan.php?deleted=true");
            exit();
        } else {
            $delete_error = "Gagal menghapus pelanggan: " . $conn->error;
        }
    }
}

// Cek apakah ada filter aktif
$has_active_filters = !empty($search);

// AJAX handler untuk pencarian real-time
if (isset($_GET['ajax_search'])) {
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Buat query dasar untuk pelanggan
    $query = "SELECT id, nama, no_hp FROM pelanggan WHERE 1=1";
    
    // Tambahkan kondisi pencarian jika ada
    if (!empty($search)) {
        $search_query = "%$search%";
        $query .= " AND (nama LIKE '$search_query' OR no_hp LIKE '$search_query')";
    }
    
    // Tambahkan pengurutan
    $query .= " ORDER BY nama ASC";
    
    // Eksekusi query
    $result = $conn->query($query);
    
    // Check if we have results
    if ($result->num_rows > 0) {
        // Desktop Table View
        echo '<div class="desktop-table">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="60">No</th>
                                <th width="40%">Nama</th>
                                <th width="150">No. HP</th>
                                <th width="120">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        $no = 1;
        while ($row = $result->fetch_assoc()) {
            echo '<tr>
                    <td>' . $no++ . '</td>
                    <td>' . $row['nama'] . '</td>
                    <td>' . $row['no_hp'] . '</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-warning btn-action" title="Edit" 
                                onclick="openEditModal(' . $row['id'] . ', \'' . addslashes($row['nama']) . '\', \'' . addslashes($row['no_hp']) . '\')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger btn-action" title="Hapus" 
                                onclick="confirmDelete(' . $row['id'] . ', \'' . addslashes($row['nama']) . '\')">
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
            echo '<div class="customer-card">
                    <div class="customer-card-header">
                        <div>No. ' . $no++ . '</div>
                    </div>
                    <div class="customer-card-body">
                        <div class="customer-card-item">
                            <div class="customer-card-label">Nama:</div>
                            <div class="customer-card-value">' . $row['nama'] . '</div>
                        </div>
                        <div class="customer-card-item">
                            <div class="customer-card-label">No. HP:</div>
                            <div class="customer-card-value">' . $row['no_hp'] . '</div>
                        </div>
                    </div>
                    <div class="customer-card-footer">
                        <button type="button" class="btn btn-sm btn-warning btn-action" title="Edit" 
                                onclick="openEditModal(' . $row['id'] . ', \'' . addslashes($row['nama']) . '\', \'' . addslashes($row['no_hp']) . '\')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button type="button" class="btn btn-sm btn-danger btn-action" title="Hapus" 
                                onclick="confirmDelete(' . $row['id'] . ', \'' . addslashes($row['nama']) . '\')">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>
                </div>';
        }
        
        echo '</div>';
    } else {
        // No results found
        echo '<div class="no-data">
                <i class="fas fa-users"></i>
                <p>Tidak ada pelanggan yang ditemukan</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                    Tambah Pelanggan Baru
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
    <title>Kelola Pelanggan</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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

        h5 {
            color: #42c3cf;
            font-weight: bold;
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
            min-width: 36px;
        }
        
        .btn-add-customer {
            background-color: #42c3cf;
            color: white;
            border-radius: 40px;
            padding: 20px 25px;
            font-size: 16px;
            font-weight: 500;
            margin-bottom: 20px;
            box-shadow: 0 4px 10px rgba(66, 195, 207, 0.3);
            transition: all 0.3s ease;
        }
        
        .btn-add-customer:hover {
            background-color: #38adb8;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(66, 195, 207, 0.4);
            color: white;
        }
        
        .btn-add-customer i {
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
        .customer-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .customer-card-header {
            background-color: #42c3cf;
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: bold;
        }
        
        .customer-card-body {
            padding: 15px;
        }
        
        .customer-card-item {
            display: flex;
            margin-bottom: 8px;
            align-items: flex-start;
        }
        
        .customer-card-label {
            font-weight: 500;
            min-width: 80px;
            color: #6c757d;
        }
        
        .customer-card-value {
            flex: 1;
        }
        
        .customer-card-footer {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 15px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
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
                text-align: center;
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
        
        /* Alert styles */
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
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
    </style>
</head>
<body>

    <!-- Sidebar -->
    <?php include 'sidebar_admin.php'; ?>

    <!-- Main Content -->
    <div class="content" id="content">
        <div class="container">
            <div class="current-date">
                <i class="far fa-calendar-alt"></i> <?php echo $tanggal_sekarang; ?>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="mb-0">Kelola Pelanggan</h1>
            </div>

            <button type="button" class="btn btn-add-customer" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                <i class="fas fa-user-plus"></i> Tambah Pelanggan Baru
            </button>
            
            <?php if (isset($_GET['added']) && $_GET['added'] == 'true'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> Pelanggan berhasil ditambahkan.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['updated']) && $_GET['updated'] == 'true'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> Data pelanggan berhasil diperbarui.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 'true'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> Pelanggan berhasil dihapus.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($delete_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $delete_error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Mobile Filter Toggle Button -->
            <button class="mobile-filter-toggle d-md-none <?php echo $has_active_filters ? 'active' : ''; ?>" id="mobileFilterToggle">
                <div>
                    <i class="fas fa-search"></i> Cari Pelanggan
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
                            <label for="mobile-search" class="filter-label">Cari Pelanggan</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="mobile-search" name="search" placeholder="Nama / No. HP" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between">
                            <a href="kelola_pelanggan.php" class="btn btn-outline-secondary">Reset</a>
                            <button type="submit" class="btn btn-primary">Cari</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Desktop Filter Container -->
            <div class="search-filter-container d-none d-md-block">
                <div class="row">
                    <div class="col-md-12">
                        <div class="mb-3">
                            <label for="search" class="filter-label">Cari Pelanggan</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="searchInput" name="search" placeholder="Masukkan Nama / No. HP" value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <div class="card-header">
                    <h5 class="mb-3">Daftar Nama Pelanggan</h5>
                </div>
                <div id="customerData">
                    <?php if ($result->num_rows > 0): ?>
                        <!-- Desktop Table View -->
                        <div class="desktop-table">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="60">No</th>
                                            <th width="40%">Nama</th>
                                            <th width="150">No. HP</th>
                                            <th width="120">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $no = 1;
                                        while ($row = $result->fetch_assoc()): 
                                        ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo $row['nama']; ?></td>
                                                <td><?php echo $row['no_hp']; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-warning btn-action" title="Edit" 
                                                            onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['nama']); ?>', '<?php echo addslashes($row['no_hp']); ?>')">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger btn-action" title="Hapus" 
                                                            onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes($row['nama']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
                            // Reset pointer to beginning of result set
                            $result->data_seek(0);
                            $no = 1;
                            while ($row = $result->fetch_assoc()): 
                            ?>
                                <div class="customer-card">
                                    <div class="customer-card-header">
                                        <div>No. <?php echo $no++; ?></div>
                                    </div>
                                    <div class="customer-card-body">
                                        <div class="customer-card-item">
                                            <div class="customer-card-label">Nama:</div>
                                            <div class="customer-card-value"><?php echo $row['nama']; ?></div>
                                        </div>
                                        <div class="customer-card-item">
                                                                                        <div class="customer-card-label">No. HP:</div>
                                            <div class="customer-card-value"><?php echo $row['no_hp']; ?></div>
                                        </div>
                                    </div>
                                    <div class="customer-card-footer">
                                        <button type="button" class="btn btn-sm btn-warning btn-action" title="Edit" 
                                                onclick="openEditModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['nama']); ?>', '<?php echo addslashes($row['no_hp']); ?>')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger btn-action" title="Hapus" 
                                                onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo addslashes($row['nama']); ?>')">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-users"></i>
                            <p>Tidak ada pelanggan yang ditemukan</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                                Tambah Pelanggan Baru
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Tambah Pelanggan -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1" aria-labelledby="addCustomerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-white" id="addCustomerModalLabel">Tambah Pelanggan Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Pelanggan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama" name="nama" required>
                        </div>
                        <div class="mb-3">
                            <label for="no_hp" class="form-label">Nomor HP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="no_hp" name="no_hp" required>
                            <div class="form-text">Format: +628xxxxxxxxxx atau 08xxxxxxxxxx (akan otomatis diubah ke format +62)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="add_customer" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit Pelanggan -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-white" id="editModalLabel">Edit Pelanggan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="mb-3">
                            <label for="edit_nama" class="form-label">Nama Pelanggan <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_nama" name="nama" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_no_hp" class="form-label">Nomor HP <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_no_hp" name="no_hp" required>
                            <div class="form-text">Format: +628xxxxxxxxxx atau 08xxxxxxxxxx (akan otomatis diubah ke format +62)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" name="update_customer" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Konfirmasi Hapus -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-white" id="deleteModalLabel">Konfirmasi Hapus</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus pelanggan <span id="customerName" class="fw-bold"></span>?</p>
                    <p class="text-danger">Tindakan ini tidak dapat dibatalkan.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <form method="POST" action="">
                        <input type="hidden" name="delete_id" id="deleteId">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openEditModal(id, nama, no_hp) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_no_hp').value = no_hp;
            
            var editModal = new bootstrap.Modal(document.getElementById('editModal'));
            editModal.show();
        }
        
        function confirmDelete(id, name) {
            document.getElementById('deleteId').value = id;
            document.getElementById('customerName').textContent = name;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
        
        // Real-time search functionality
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
                        fetchCustomers(searchTerm);
                    }, doneTypingInterval);
                });
                
                // Function to fetch customers with AJAX
                function fetchCustomers(searchTerm) {
                    // Create XMLHttpRequest
                    const xhr = new XMLHttpRequest();
                    
                    // Configure it
                    xhr.open('GET', 'kelola_pelanggan.php?ajax_search=1&search=' + encodeURIComponent(searchTerm), true);
                    
                    // Set up handler for when request finishes
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            document.getElementById('customerData').innerHTML = xhr.responseText;
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
        });
    </script>
</body>
</html>


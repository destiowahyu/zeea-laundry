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
$query_admin = $conn->prepare("SELECT id, username, foto_profil FROM admin WHERE username = ?");
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
$adminFoto = $adminData['foto_profil'];

// Simpan ID admin ke session jika belum ada
if (!isset($_SESSION['id'])) {
    $_SESSION['id'] = $adminId;
}

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

// Proses hapus foto profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_photo'])) {
    $current_foto = $_POST['current_foto'];
    
    // Hapus file foto jika ada
    if (!empty($current_foto)) {
        $foto_path = '../assets/uploads/profil_admin/' . $current_foto;
        if (file_exists($foto_path)) {
            unlink($foto_path);
        }
    }
    
    // Update database, set foto_profil menjadi NULL
    $update_query = $conn->prepare("UPDATE admin SET foto_profil = NULL WHERE id = ?");
    $update_query->bind_param("i", $adminId);
    
    if ($update_query->execute()) {
        $success_message = "Foto profil berhasil dihapus";
        // Refresh halaman untuk memperbarui data
        header("Location: profil.php?updated=true&message=foto_dihapus");
        exit();
    } else {
        $error_message = "Gagal menghapus foto profil: " . $conn->error;
    }
}

// Proses upload foto langsung (tanpa crop)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    $current_foto = $_POST['current_foto'];
    
    // Handle file upload
    if (isset($_FILES['foto_profil']) && $_FILES['foto_profil']['error'] == 0) {
        // Validasi ekstensi file
        $allowed_extensions = ['png', 'jpg', 'jpeg'];
        $file_extension = strtolower(pathinfo($_FILES['foto_profil']['name'], PATHINFO_EXTENSION));

        if (in_array($file_extension, $allowed_extensions)) {
            $upload_dir = '../assets/uploads/profil_admin/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true); // Buat folder jika belum ada
            }

            // Generate nama file unik
            $foto_profil = uniqid('admin_') . '.' . $file_extension;
            
            // Pindahkan file
            if (move_uploaded_file($_FILES['foto_profil']['tmp_name'], $upload_dir . $foto_profil)) {
                // Hapus file foto lama jika ada
                if (!empty($current_foto)) {
                    $old_foto_path = $upload_dir . $current_foto;
                    if (file_exists($old_foto_path)) {
                        unlink($old_foto_path);
                    }
                }
                
                // Update database
                $update_query = $conn->prepare("UPDATE admin SET foto_profil = ? WHERE id = ?");
                $update_query->bind_param("si", $foto_profil, $adminId);
                
                if ($update_query->execute()) {
                    $success_message = "Foto profil berhasil diperbarui";
                    // Refresh halaman untuk memperbarui data
                    header("Location: profil.php?updated=true&message=foto_diupload");
                    exit();
                } else {
                    $error_message = "Gagal memperbarui foto profil: " . $conn->error;
                }
            } else {
                $error_message = "Gagal mengupload file";
            }
        } else {
            $error_message = "Format file tidak didukung. Gunakan format PNG, JPG, atau JPEG";
        }
    } else {
        $error_message = "Pilih file foto terlebih dahulu";
    }
}

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $current_foto = $_POST['current_foto'];
    
    // Validasi input
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username harus diisi";
    }
    
    // Cek apakah username sudah digunakan oleh admin lain
    if ($username !== $adminName) {
        $check_username = $conn->prepare("SELECT id FROM admin WHERE username = ? AND id != ?");
        $check_username->bind_param("si", $username, $adminId);
        $check_username->execute();
        $check_result = $check_username->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "Username sudah digunakan oleh admin lain";
        }
    }
    
    // Handle cropped image upload
    $foto_profil = $current_foto; // Default to current photo if no new upload
    if (isset($_POST['cropped_image']) && !empty($_POST['cropped_image'])) {
        $upload_dir = '../assets/uploads/profil_admin/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); // Buat folder jika belum ada
        }
        
        // Decode base64 image
        $image_parts = explode(";base64,", $_POST['cropped_image']);
        $image_type_aux = explode("image/", $image_parts[0]);
        $image_type = $image_type_aux[1];
        $image_base64 = base64_decode($image_parts[1]);
        
        // Generate nama file unik
        $foto_profil = uniqid('admin_') . '.' . $image_type;
        $file_path = $upload_dir . $foto_profil;
        
        // Simpan file
        file_put_contents($file_path, $image_base64);
        
        // Hapus file foto lama jika ada
        if (!empty($current_foto)) {
            $old_foto_path = $upload_dir . $current_foto;
            if (file_exists($old_foto_path)) {
                unlink($old_foto_path);
            }
        }
    }
    
    if (empty($errors)) {
        // Update data admin
        if (!empty($password)) {
            // Jika password diisi, update username, password, dan foto
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_query = $conn->prepare("UPDATE admin SET username = ?, password = ?, foto_profil = ? WHERE id = ?");
            $update_query->bind_param("sssi", $username, $hashed_password, $foto_profil, $adminId);
        } else {
            // Jika password kosong, hanya update username dan foto
            $update_query = $conn->prepare("UPDATE admin SET username = ?, foto_profil = ? WHERE id = ?");
            $update_query->bind_param("ssi", $username, $foto_profil, $adminId);
        }
        
        if ($update_query->execute()) {
            // Update session username jika username berubah
            if ($username !== $adminName) {
                $_SESSION['username'] = $username;
            }
            
            $success_message = "Profil berhasil diperbarui";
            // Refresh halaman untuk memperbarui data
            header("Location: profil.php?updated=true");
            exit();
        } else {
            $error_message = "Gagal memperbarui profil: " . $conn->error;
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css">
    <link rel="stylesheet" href="../assets/css/admin/styles.css">
    <link rel="icon" type="image/png" href="../assets/images/zeea_laundry.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <style>
        .profile-container {
            background-color: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .profile-avatar-container {
            position: relative;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #42c3cf;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
            border-color:rgb(255, 255, 255);
            box-shadow: 0 0 15px rgba(66, 195, 207, 0.96), 0 0 30px rgba(66, 195, 207, 0.6);
        }
        
        .avatar-edit-btn {
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: #42c3cf;
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid white;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }
        
        .avatar-edit-btn:hover {
            background-color: #38adb8;
            transform: scale(1.1);
        }
        
        .avatar-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-avatar-action {
            padding: 5px 10px;
            font-size: 14px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .profile-name {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }
        
        .profile-role {
            font-size: 16px;
            color: #6c757d;
            margin-bottom: 15px;
            background-color: #e9f7f8;
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .profile-info {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            width: 120px;
            min-width: 120px;
        }
        
        .info-value {
            color: #212529;
            flex: 1;
        }
        
        .btn-edit-profile {
            background-color: #42c3cf;
            color: white;
            border-radius: 30px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            display: block;
            margin: 0 auto;
        }
        
        .btn-edit-profile:hover {
            background-color: #38adb8;
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(66, 195, 207, 0.3);
            color: white;
        }
        
        .btn-edit-profile i {
            margin-right: 8px;
        }
        
        .current-date {
            font-size: 14px;
            color: #6c757d;
            text-align: right;
            margin-bottom: 15px;
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
        
        /* Cropper styles */
        .img-container {
            max-height: 400px;
            margin-bottom: 20px;
        }
        
        .cropper-container {
            margin-top: 15px;
        }
        
        .preview-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 15px;
        }
        
        .preview-image {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #42c3cf;
            margin-bottom: 10px;
            overflow: hidden;
        }
        
        .cropper-view-box,
        .cropper-face {
            border-radius: 50%;
        }
        
        /* Alert styles */
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .profile-container {
                padding: 20px;
            }
            
            .profile-avatar {
                width: 120px;
                height: 120px;
            }
            
            .info-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .info-label {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .avatar-actions {
                flex-direction: column;
                align-items: center;
            }
        }

        .avatar-dropdown-menu {
            min-width: 180px;
            padding: 8px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(0, 0, 0, 0.1);
        }

        .avatar-dropdown-menu .dropdown-item {
            border-radius: 5px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        .avatar-dropdown-menu .dropdown-item:hover {
            background-color: #f0f7f8;
        }

        .avatar-dropdown-menu .dropdown-item.text-danger:hover {
            background-color: #fff5f5;
        }
        
        /* Fullscreen image modal */
        .fullscreen-modal .modal-dialog {
            max-width: 100%;
            margin: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .fullscreen-modal .modal-content {
            border: none;
            border-radius: 5%;
            height: auto;
            width: auto;
            max-width: 100%;
            max-height: 100vh;
        }
        
        .fullscreen-modal .modal-body {
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .fullscreen-modal img {
            max-width: 100%;
            max-height: 90vh;
            object-fit: contain;
        }
        
        .fullscreen-modal .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 30px;
            background: none;
            border: none;
            cursor: pointer;
            z-index: 1050;
            opacity: 0.7;
            transition: opacity 0.3s;
        }
        
        .fullscreen-modal .close-btn:hover {
            opacity: 1;
        }
        
        /* Posisi modal hapus foto */
        #deletePhotoModal .modal-dialog {
            margin-top: 10rem;
        }

        /* Posisi modal hapus foto */
        #editProfileModal .modal-dialog {
            margin-top: 10rem;
        }

        /* Estilos para el modal en pantalla completa en dispositivos móviles */
        @media (max-width: 768px) {
            .modal-dialog.mobile-fullscreen {
                max-width: 100%;
                margin: 0;
                height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
            }

            .modal-dialog.mobile-fullscreen .modal-content {
                border-radius: 0;
                height: auto;
                max-width: 100%;
                max-height: 100vh;
                overflow: auto;
            }

            .modal-dialog.mobile-fullscreen .modal-body {
                padding: 15px;
            }
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
                    <h1 class="mb-0">Profil</h1>
                </div>
                
                <?php if (isset($_GET['updated']) && $_GET['updated'] == 'true'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php if (isset($_GET['message']) && $_GET['message'] == 'foto_dihapus'): ?>
                            <i class="fas fa-check-circle me-2"></i> Foto profil berhasil dihapus.
                        <?php elseif (isset($_GET['message']) && $_GET['message'] == 'foto_diupload'): ?>
                            <i class="fas fa-check-circle me-2"></i> Foto profil berhasil diupload.
                        <?php else: ?>
                            <i class="fas fa-check-circle me-2"></i> Profil berhasil diperbarui.
                        <?php endif; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="profile-container">
                    <div class="profile-header">
                        <div class="profile-avatar-container">
                            <?php if (!empty($adminFoto)): ?>
                                <img src="../assets/uploads/profil_admin/<?php echo htmlspecialchars($adminFoto); ?>" alt="Foto Profil" class="profile-avatar" data-bs-toggle="modal" data-bs-target="#fullscreenImageModal">
                            <?php else: ?>
                                <img src="../assets/images/default-avatar.png" alt="Foto Profil Default" class="profile-avatar">
                            <?php endif; ?>
                            <div class="avatar-edit-btn" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-camera"></i>
                            </div>
                            <!-- Input file tersembunyi untuk langsung memilih file -->
                            <input type="file" id="hiddenFileInput" accept="image/*" style="display: none;">
                            
                            <ul class="dropdown-menu avatar-dropdown-menu">
                                <li><a class="dropdown-item" id="uploadPhotoBtn"><i class="fas fa-upload me-2"></i>Ganti Foto</a></li>
                                <?php if (!empty($adminFoto)): ?>
                                <li><a class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deletePhotoModal"><i class="fas fa-trash-alt me-2"></i>Hapus Foto</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <h2 class="profile-name"><?php echo htmlspecialchars($adminName); ?></h2>
                        <div class="profile-role">Administrator</div>
                    </div>
                    
                    <div class="profile-info">
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-user me-2"></i>Username:</div>
                            <div class="info-value"><?php echo htmlspecialchars($adminName); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-shield-alt me-2"></i>Role:</div>
                            <div class="info-value">Administrator</div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-edit-profile" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="fas fa-user-edit"></i> Edit Profil
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Profil -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-white" id="editProfileModalLabel">Edit Profil</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="current_foto" value="<?php echo htmlspecialchars($adminFoto); ?>">
                <input type="hidden" name="cropped_image" id="cropped_image_data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($adminName); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password">
                        <div class="form-text">Biarkan kosong jika tidak ingin mengubah password</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" name="update_profile">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Cambiar la estructura del modal para garantizar que los botones siempre sean visibles -->
<!-- Modificar el modal de carga de fotos para reducir la altura y mostrar los botones sin scroll -->

<!-- Reemplazar el modal de carga de fotos actual con esta versión optimizada -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1" aria-labelledby="uploadPhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-white" id="uploadPhotoModalLabel">Ubah Foto Profil</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="crop-step" id="cropStep">
                    <div class="img-container" style="height: 450px; max-height: 60vh;">
                        <img id="cropImage" src="#" alt="Gambar untuk di-crop" style="max-width: 100%;">
                    </div>
                </div>
                <!-- Controles de zoom y rotación -->
                <div class="py-2 text-center bg-light">
                    <button type="button" class="btn btn-secondary btn-sm mx-1" id="zoomInBtn"><i class="fas fa-search-plus"></i></button>
                    <button type="button" class="btn btn-secondary btn-sm mx-1" id="zoomOutBtn"><i class="fas fa-search-minus"></i></button>
                    <button type="button" class="btn btn-secondary btn-sm mx-1" id="rotateBtn"><i class="fas fa-sync-alt"></i></button>
                </div>
            </div>
            <div class="modal-footer" id="cropFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="saveButton">Simpan</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Hapus Foto Profil -->
<div class="modal fade" id="deletePhotoModal" tabindex="-1" aria-labelledby="deletePhotoModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-white" id="deletePhotoModalLabel">Hapus Foto Profil</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <input type="hidden" name="current_foto" value="<?php echo htmlspecialchars($adminFoto); ?>">
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin menghapus foto profil Anda?</p>
                    <p>Foto profil akan diganti dengan gambar default.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger" name="delete_photo">Hapus Foto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Fullscreen Image -->
<div class="modal fade fullscreen-modal" id="fullscreenImageModal" tabindex="-1" aria-labelledby="fullscreenImageModalLabel" aria-hidden="true">
    <button type="button" class="close-btn" data-bs-dismiss="modal" aria-label="Close">
        <i class="fas fa-times"></i>
    </button>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <?php if (!empty($adminFoto)): ?>
                    <img src="../assets/uploads/profil_admin/<?php echo htmlspecialchars($adminFoto); ?>" alt="Foto Profil" id="fullscreenImage">
                <?php else: ?>
                    <img src="../assets/images/default-avatar.png" alt="Foto Profil Default" id="fullscreenImage">
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Actualizar el script para ajustar la configuración del cropper
    let cropper;
    
    // Detectar si es dispositivo móvil
    const isMobile = window.matchMedia("(max-width: 768px)").matches;

    // Ketika tombol "Ganti Foto" diklik, langsung trigger input file
    document.getElementById('uploadPhotoBtn').addEventListener('click', function() {
        document.getElementById('hiddenFileInput').click();
    });

    // Ketika file dipilih melalui input tersembunyi
    document.getElementById('hiddenFileInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                // Tampilkan modal crop
                const uploadModal = new bootstrap.Modal(document.getElementById('uploadPhotoModal'));
                uploadModal.show();
                
                // Aplicar clase fullscreen en móviles
                if (isMobile) {
                    setTimeout(() => {
                        document.querySelector('#uploadPhotoModal .modal-dialog').classList.add('mobile-fullscreen');
                    }, 300);
                }
                
                // Set gambar untuk di-crop
                const cropImage = document.getElementById('cropImage');
                cropImage.src = event.target.result;
                
                // Esperar a que la imagen se cargue
                cropImage.onload = function() {
                    // Destroy cropper jika sudah ada
                    if (cropper) {
                        cropper.destroy();
                    }
                
                    // Configuración optimizada para escritorio y móviles
                    const cropperOptions = {
                        aspectRatio: 1,
                        viewMode: isMobile ? 0 : 1,
                        guides: true,
                        autoCropArea: 0.8,
                        responsive: true,
                        dragMode: 'move',
                        cropBoxResizable: true,
                        cropBoxMovable: true,
                        toggleDragModeOnDblclick: false,
                        minContainerWidth: 250,
                        minContainerHeight: 250,
                        minCropBoxWidth: 100,
                        minCropBoxHeight: 100,
                        wheelZoomRatio: 0.1,
                        background: true,
                        modal: true,
                        center: true
                    };
                
                    // Inisialisasi cropper
                    cropper = new Cropper(cropImage, cropperOptions);
                
                    // Ajustar tamaño después de inicializar
                    setTimeout(() => {
                        window.dispatchEvent(new Event('resize'));
                    }, 500);
                };
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Controles de zoom y rotación
    document.getElementById('zoomInBtn').addEventListener('click', function() {
        cropper.zoom(0.1);
    });
    
    document.getElementById('zoomOutBtn').addEventListener('click', function() {
        cropper.zoom(-0.1);
    });
    
    document.getElementById('rotateBtn').addEventListener('click', function() {
        cropper.rotate(90);
    });
    
    // Tombol simpan foto yang sudah di-crop
    document.getElementById('saveButton').addEventListener('click', function() {
        if (cropper) {
            // Get cropped canvas
            const canvas = cropper.getCroppedCanvas({
                width: 600,
                height: 600,
                minWidth: 100,
                minHeight: 100,
                maxWidth: 1000,
                maxHeight: 1000,
                fillColor: '#fff',
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            
            // Convert canvas to base64 string
            const croppedImageData = canvas.toDataURL('image/jpeg');
            
            // Submit the form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>';
            
            // Add hidden inputs
            const currentFotoInput = document.createElement('input');
            currentFotoInput.type = 'hidden';
            currentFotoInput.name = 'current_foto';
            currentFotoInput.value = '<?php echo htmlspecialchars($adminFoto); ?>';
            form.appendChild(currentFotoInput);
            
            const croppedImageInput = document.createElement('input');
            croppedImageInput.type = 'hidden';
            croppedImageInput.name = 'cropped_image';
            croppedImageInput.value = croppedImageData;
            form.appendChild(croppedImageInput);
            
            const usernameInput = document.createElement('input');
            usernameInput.type = 'hidden';
            usernameInput.name = 'username';
            usernameInput.value = '<?php echo htmlspecialchars($adminName); ?>';
            form.appendChild(usernameInput);
            
            const updateProfileInput = document.createElement('input');
            updateProfileInput.type = 'hidden';
            updateProfileInput.name = 'update_profile';
            updateProfileInput.value = '1';
            form.appendChild(updateProfileInput);
            
            // Append form to body and submit
            document.body.appendChild(form);
            form.submit();
        } else {
            alert('Silakan pilih gambar terlebih dahulu');
        }
    });
    
    // Reset modal saat ditutup
    document.getElementById('uploadPhotoModal').addEventListener('hidden.bs.modal', function() {
        // Reset input file
        document.getElementById('hiddenFileInput').value = '';
        
        // Destroy cropper
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        
        // Quitar clase fullscreen
        document.querySelector('#uploadPhotoModal .modal-dialog').classList.remove('mobile-fullscreen');
    });
    
    // Ajustar cuando cambia la orientación del dispositivo
    window.addEventListener('resize', function() {
        if (cropper) {
            cropper.resize();
        }
    });
</script>
</body>
</html>


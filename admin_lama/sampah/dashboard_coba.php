<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
        }
        .sidebar {
            height: 100vh;
            width: 250px;
            background-color:#ffffff;
            color: #104342;
            position: fixed;
            padding-top: 27px; /* Tambahkan padding agar tidak ketimpa tombol */
            transition: all 0.3s ease-in-out;
            overflow-y: auto;
            z-index: 1000;
        }
        .sidebar a {
            color: #104342;
            text-decoration: none;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
            transition: all 0.3s ease-in-out;
        }
        .sidebar a:hover, .sidebar a.active {
            background-color: #42c3cf; 

            color: #e0f7f7;
        }
        .sidebar a i {
            transition: transform 0.3s;
        }
        .sidebar a:hover i {
            transform: rotate(360deg);
        }
        .content {
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s ease-in-out;
        }
        .sidebar.collapsed {
            width: 60px;
            padding-top: 40px; /* Pastikan avatar tetap turun di sidebar collapsed */
        }
        .sidebar.collapsed a span {
            display: none;
        }
        .sidebar.collapsed .avatar-container {
            display: flex;
            justify-content: center;
            margin-bottom: 0;
        }
        .sidebar.collapsed .admin-avatar {
            width: 40px;
            height: 40px;
        }
        .sidebar.collapsed #admin-name {
            display: none;
        }
        .sidebar.collapsed #admin-panel {
            display: none;
        }
        .content.collapsed {
            margin-left: 70px;
        }
        .card {
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            border: none;
            text-align: center;
            background: linear-gradient(to bottom,rgb(224, 242, 247), #fff);
            transition: transform 0.3s ease-in-out;
        }
        .card:hover {
            transform: scale(1.05);
        }
        .card i {
            font-size: 2rem;
            color: #00b9b9;
        }
        .toggle-btn {
            position: absolute;
            top: 10px;
            left: 10px;
            background-color:rgb(255, 255, 255);
            color: #42c3cf; 
            border: none;
            cursor: pointer;
            padding: 5px 10px;
            font-size: 1.3rem;
            z-index: 1100;
            border-radius: 5px;
        }
        .admin-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }
        .avatar-container {
            text-align: center;
            margin-bottom: 20px;
            padding-top: 20px; /* Tambahkan jarak untuk avatar */
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar" id="sidebar">
        <div class="avatar-container">
            <h4 id=admin-panel>Admin Panel</h4>
            <img src="../assets/images/admin.png" class="admin-avatar" alt="Admin">
            <h6 class="mt-2 mb-0" id="admin-name">Admin</h6>
        </div>
        <a href="#" class="active"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="#"><i class="fas fa-user-md"></i> <span>Kelola Dokter</span></a>
        <a href="#"><i class="fas fa-users"></i> <span>Kelola Pasien</span></a>
        <a href="#"><i class="fas fa-hospital"></i> <span>Kelola Poli</span></a>
        <a href="#"><i class="fas fa-pills"></i> <span>Kelola Obat</span></a>
        <a href="#"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
    </div>

    <!-- Main Content -->
    <div class="content" id="content">
        <h2>Selamat Datang, admin!</h2>
        <div class="row">
            <!-- Total Dokter -->
            <div class="col-md-3 mb-4">
                <div class="card p-3">
                    <i class="fas fa-user-md mb-2"></i>
                    <h5>Total Dokter</h5>
                    <h3>4</h3>
                </div>
            </div>

            <!-- Total Pasien -->
            <div class="col-md-3 mb-4">
                <div class="card p-3">
                    <i class="fas fa-users mb-2"></i>
                    <h5>Total Pasien</h5>
                    <h3>16</h3>
                </div>
            </div>

            <!-- Total Poli -->
            <div class="col-md-3 mb-4">
                <div class="card p-3">
                    <i class="fas fa-hospital mb-2"></i>
                    <h5>Total Poli</h5>
                    <h3>4</h3>
                </div>
            </div>

            <!-- Total Obat -->
            <div class="col-md-3 mb-4">
                <div class="card p-3">
                    <i class="fas fa-pills mb-2"></i>
                    <h5>Total Obat</h5>
                    <h3>6</h3>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const content = document.getElementById('content');
            sidebar.classList.toggle('collapsed');
            content.classList.toggle('collapsed');
        }
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Temu Janji Pasien - Dokter</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

        <!-- Header -->
        <header class="bg-green">
            <h1 class="fw-bold">Sistem Temu Janji Pasien - Dokter</h1>
            <p class="lead">Bimbingan Karir 2024 Bidang Web</p>
        </header>


    <!-- Hero Image -->
    <div class="bg-image" style="background-image: url('assets/images/hospital.jpg'); height: 300px; background-size: cover; background-position: center;"></div>

    <!-- Main Content -->
    <main class="container py-5">
        <div class="row g-4 text-center">
            <!-- Card Login Pasien -->
            <div class="col-md-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <img src="assets/images/patient-icon.png" alt="Pasien Icon" class="mb-3" style="width: 50px;">
                        <h3 class="card-title">Login Sebagai Pasien</h3>
                        <p class="card-text">Jika Anda adalah Pasien, silakan login untuk mengakses sistem temu janji dengan Dokter.</p>
                        <a href="login_pasien.php" class="btn btn-primary">Login Pasien →</a>
                    </div>
                </div>
            </div>
            <!-- Card Login Dokter -->
            <div class="col-md-6">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <img src="assets/images/doctor-icon.png" alt="Dokter Icon" class="mb-3" style="width: 50px;">
                        <h3 class="card-title">Login Sebagai Dokter</h3>
                        <p class="card-text">Jika Anda adalah Dokter, silakan login untuk memulai melayani pasien.</p>
                        <a href="login_dokter.php" class="btn btn-primary">Login Dokter →</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="text-center py-3 bg-light">
        <p class="mb-0">© 2024 Sistem Temu Janji Pasien - Dokter. All Rights Reserved.</p>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

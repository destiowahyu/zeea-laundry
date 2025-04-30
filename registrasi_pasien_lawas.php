<?php
include 'includes/db.php';

function generateNoRM($conn) {
    $yearMonth = date('Ym');
    $query = "SELECT COUNT(*) AS total FROM pasien WHERE no_rm LIKE ?";
    $likePattern = $yearMonth . "%";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $newNumber = str_pad($row['total'] + 1, 3, "0", STR_PAD_LEFT);
    return $yearMonth . '-' . $newNumber;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = $_POST['nama'];
    $alamat = $_POST['alamat'];
    $no_ktp = $_POST['no_ktp'];
    $no_hp = $_POST['no_hp'];
    $username = $_POST['username'];
    $password = md5($_POST['password']);
    $no_rm = generateNoRM($conn);

    $query = "INSERT INTO pasien (nama, alamat, no_ktp, no_hp, no_rm, username, password) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssss", $nama, $alamat, $no_ktp, $no_hp, $no_rm, $username, $password);

    if ($stmt->execute()) {
        header("Location: login_pasien.php?success=1");
        exit;
    } else {
        $error = "Terjadi kesalahan saat registrasi.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <title>Registrasi Pasien</title>
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center">Registrasi Pasien</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="no_rm" class="form-label">Nomor RM</label>
            <input type="text" id="no_rm" class="form-control" value="<?php echo generateNoRM($conn); ?>" disabled>
        </div>
        <div class="mb-3">
            <label for="nama" class="form-label">Nama</label>
            <input type="text" name="nama" id="nama" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="alamat" class="form-label">Alamat</label>
            <input type="text" name="alamat" id="alamat" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="no_ktp" class="form-label">Nomor KTP</label>
            <input type="text" name="no_ktp" id="no_ktp" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="no_hp" class="form-label">Nomor HP</label>
            <input type="text" name="no_hp" id="no_hp" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" name="username" id="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" name="password" id="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Registrasi</button>
    </form>
</div>
</body>
</html>

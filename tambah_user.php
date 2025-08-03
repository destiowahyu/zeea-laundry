<?php
require 'includes/db.php'; 

// Username dan Password
$username = 'oke';
$password = 'oke';


$hashed_password = password_hash($password, PASSWORD_BCRYPT);


$query = "INSERT INTO admin (username, password) VALUES (?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $username, $hashed_password);


if ($stmt->execute()) {
    echo "Username dan password berhasil ditambahkan.";
} else {
    echo "Gagal menambahkan username dan password: " . $conn->error;
}

$stmt->close();
$conn->close();
?>

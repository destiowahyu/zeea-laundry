<?php
session_start();


if (isset($_COOKIE['remember_admin'])) {
    setcookie('remember_admin', '', time() - 3600, '/');
}


session_unset();
session_destroy();


header("Location: admin/login.php");
exit();
?>
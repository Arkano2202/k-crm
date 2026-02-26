<?php
/*
// logout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

session_destroy();
header("Location: ../login.php");
exit();*/

// logout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener la base URL del proyecto
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . "://" . $host;

session_destroy();

// Redireccionar a la raíz
header("Location: " . $base_url . "/");
exit();

?>
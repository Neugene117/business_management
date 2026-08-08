<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "ineza_african_mining";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>

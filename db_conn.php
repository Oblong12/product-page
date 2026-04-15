<?php
session_start();

/* 
    DATABASE CONNECTION SETTINGS 
    Integrated with Oblong Login System.
    Change these to match your environment.
*/
$host = 'localhost';   // Usually 'localhost'
$user = 'root';        // Your database username
$pass = '';            // Your database password
$db   = 'login';       // Your database name (Changed from 'products' to 'login' to match Oblong)

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");
?>
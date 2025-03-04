<?php
$host     = "sql105.infinityfree.com"; // Database Host
$user     = "if0_37772405"; // Database Username
$password = "hMCWvBjYOKjDE"; // Database's user Password
$database = "if0_37772405_cps"; // Database Name

$connect = new mysqli($host, $user, $password, $database);

// Checking Connection
if (mysqli_connect_errno()) {
    printf("Database connection failed: %s\n", mysqli_connect_error());
    exit();
}

mysqli_set_charset($connect, "utf8mb4");

// Settings
include "config_settings.php";
?>
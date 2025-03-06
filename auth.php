<?php
session_start();
ob_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1); // Remove in production

include 'config.php';

if ($connect->connect_error) {
    die(json_encode(['success' => false, 'message' => 'DB Error: ' . $connect->connect_error]));
}

// Create table
$create_table_query = "
    CREATE TABLE IF NOT EXISTS cps_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        reset_token VARCHAR(100) DEFAULT NULL,
        reset_expiry DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
";

if (!$connect->query($create_table_query)) {
    die(json_encode(['success' => false, 'message' => 'Table Error: ' . $connect->error]));
}

// Basic signup (simplified for testing)
if (isset($_POST['signup'])) {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Fill all fields']);
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $connect->prepare("INSERT INTO cps_users (username, email, password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $email, $hashed_password);
        if ($stmt->execute()) {
            $_SESSION['username'] = $username;
            $_SESSION['user_id'] = $connect->insert_id;
            echo json_encode(['success' => true, 'message' => 'Signup OK']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Insert Error: ' . $connect->error]);
        }
        $stmt->close();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
}

ob_end_flush();
$connect->close();
?>

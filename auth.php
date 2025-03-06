<?php
session_start();
ob_start(); // Start output buffering
header('Content-Type: application/json');

// Error handling for development (log errors, don’t display in production)
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 temporarily for debugging if needed
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

include 'db.php'; // Changed from config.php to use your db.php with PDO

try {
    // Create table if it doesn’t exist
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
    
    $dbh->exec($create_table_query);
} catch (PDOException $e) {
    error_log('Table creation failed: ' . $e->getMessage());
    send_json_response(false, 'Table creation failed');
    exit;
}

// Helper function to standardize JSON responses
function send_json_response($success, $message, $data = []) {
    $response = ['success' => $success, 'message' => $message];
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

// Signup Logic
if (isset($_POST['signup'])) {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        send_json_response(false, 'All fields are required');
    }

    if ($password !== $confirm_password) {
        send_json_response(false, 'Passwords do not match');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json_response(false, 'Invalid email address');
    }

    if (!preg_match("/^[a-zA-Z0-9_]{3,50}$/", $username)) {
        send_json_response(false, 'Username must be 3-50 characters (letters, numbers, underscore)');
    }

    try {
        $check_stmt = $dbh->prepare("SELECT id FROM cps_users WHERE username = :username OR email = :email");
        $check_stmt->execute([':username' => $username, ':email' => $email]);
        
        if ($check_stmt->rowCount() > 0) {
            send_json_response(false, 'Username or email already exists');
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $dbh->prepare("INSERT INTO cps_users (username, email, password) VALUES (:username, :email, :password)");
        $result = $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => $hashed_password
        ]);

        if ($result) {
            $_SESSION['username'] = $username;
            $_SESSION['user_id'] = $dbh->lastInsertId();
            session_regenerate_id(true);
            send_json_response(true, 'Signup successful');
        } else {
            send_json_response(false, 'Registration failed');
        }
    } catch (PDOException $e) {
        send_json_response(false, 'Database error: ' . $e->getMessage());
    }
}

// Login Logic
elseif (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        send_json_response(false, 'All fields are required');
    }

    try {
        $stmt = $dbh->prepare("SELECT id, username, password FROM cps_users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            session_regenerate_id(true);
            send_json_response(true, 'Login successful');
        } else {
            send_json_response(false, 'Invalid credentials');
        }
    } catch (PDOException $e) {
        send_json_response(false, 'Database error: ' . $e->getMessage());
    }
} else {
    send_json_response(false, 'No action specified');
}
?>

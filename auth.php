<?php
session_start();

// Start output buffering to catch any unintended output
ob_start();

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log errors to a file as a fallback
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

include 'config.php'; // Include your database configuration

// Test database connection explicitly
if ($connect->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $connect->connect_error]));
}

// Set JSON content type
header('Content-Type: application/json');

// Helper function to sanitize input (though we'll switch to prepared statements)
function sanitize($data) {
    global $connect;
    return mysqli_real_escape_string($connect, trim($data));
}

// Create table with error checking
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
    echo json_encode(['success' => false, 'message' => 'Error creating table: ' . $connect->error]);
    ob_end_flush();
    exit;
}

// Signup Handler with Prepared Statements
if (isset($_POST['signup'])) {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
        ob_end_flush();
        exit;
    }

    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        ob_end_flush();
        exit;
    }

    // Check if username or email exists
    $stmt = $connect->prepare("SELECT * FROM cps_users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or email already taken']);
        ob_end_flush();
        exit;
    }
    $stmt->close();

    // Hash password and insert user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    if (!$hashed_password) {
        echo json_encode(['success' => false, 'message' => 'Error hashing password']);
        ob_end_flush();
        exit;
    }

    $stmt = $connect->prepare("INSERT INTO cps_users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $hashed_password);
    if ($stmt->execute()) {
        $_SESSION['username'] = $username;
        $_SESSION['user_id'] = $connect->insert_id;
        echo json_encode(['success' => true, 'message' => 'Signup successful']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Signup failed: ' . $connect->error]);
    }
    $stmt->close();
    ob_end_flush();
    exit;
}

// Login Handler with Prepared Statements
if (isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
        ob_end_flush();
        exit;
    }

    $stmt = $connect->prepare("SELECT * FROM cps_users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            echo json_encode(['success' => true, 'message' => 'Login successful']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Username not found']);
    }
    $stmt->close();
    ob_end_flush();
    exit;
}

// Add similar prepared statement updates for reset_request and reset_password handlers if needed...

// If no valid action
echo json_encode(['success' => false, 'message' => 'Invalid request']);
ob_end_flush();
$connect->close();
?>

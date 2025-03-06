<?php
session_start();
include 'config.php'; // Include your database configuration

// Helper function to sanitize input
function sanitize($data) {
    global $connect;
    return mysqli_real_escape_string($connect, trim($data));
}

// Create cps_users table if it doesn’t exist
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

if (!mysqli_query($connect, $create_table_query)) {
    die("Error creating table: " . mysqli_error($connect));
}

// Login Handler
if (isset($_POST['login'])) {
    $username = sanitize($_POST['username']);
    $password = sanitize($_POST['password']);

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
        exit;
    }

    $query = "SELECT * FROM cps_users WHERE username = '$username'";
    $result = mysqli_query($connect, $query);

    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
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
    exit;
}

// Signup Handler
if (isset($_POST['signup'])) {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = sanitize($_POST['password']);
    $confirm_password = sanitize($_POST['confirm_password']);

    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
        exit;
    }

    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }

    // Check if username or email already exists
    $check_query = "SELECT * FROM cps_users WHERE username = '$username' OR email = '$email'";
    $check_result = mysqli_query($connect, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or email already taken']);
        exit;
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user
    $insert_query = "INSERT INTO cps_users (username, email, password) VALUES ('$username', '$email', '$hashed_password')";
    if (mysqli_query($connect, $insert_query)) {
        $_SESSION['username'] = $username;
        $_SESSION['user_id'] = mysqli_insert_id($connect);
        echo json_encode(['success' => true, 'message' => 'Signup successful']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Signup failed: ' . mysqli_error($connect)]);
    }
    exit;
}

// Password Reset Request Handler
if (isset($_POST['reset_request'])) {
    $email = sanitize($_POST['email']);

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Please enter your email']);
        exit;
    }

    $query = "SELECT * FROM cps_users WHERE email = '$email'";
    $result = mysqli_query($connect, $query);

    if (mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);
        $token = bin2hex(random_bytes(32)); // Generate a secure token
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour

        // Update user with reset token and expiry
        $update_query = "UPDATE cps_users SET reset_token = '$token', reset_expiry = '$expiry' WHERE email = '$email'";
        if (mysqli_query($connect, $update_query)) {
            // Send reset email (basic example - configure SMTP for production)
            $reset_link = "http://yourdomain.com/reset_password.php?token=$token";
            $subject = "Password Reset Request";
            $message = "Click this link to reset your password: $reset_link\nThis link expires in 1 hour.";
            $headers = "From: no-reply@yourdomain.com";

            if (mail($email, $subject, $message, $headers)) {
                echo json_encode(['success' => true, 'message' => 'Reset link sent to your email']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to send reset email']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error generating reset token']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
    }
    exit;
}

// Password Reset Confirmation Handler (for reset_password.php)
if (isset($_POST['reset_password'])) {
    $token = sanitize($_POST['token']);
    $new_password = sanitize($_POST['new_password']);
    $confirm_password = sanitize($_POST['confirm_password']);

    if (empty($token) || empty($new_password) || empty($confirm_password)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all fields']);
        exit;
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }

    $query = "SELECT * FROM cps_users WHERE reset_token = '$token' AND reset_expiry > NOW()";
    $result = mysqli_query($connect, $query);

    if (mysqli_num_rows($result) === 1) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_query = "UPDATE cps_users SET password = '$hashed_password', reset_token = NULL, reset_expiry = NULL WHERE reset_token = '$token'";
        
        if (mysqli_query($connect, $update_query)) {
            echo json_encode(['success' => true, 'message' => 'Password reset successful']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error resetting password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    }
    exit;
}

// Logout Handler (can be moved to main file if preferred)
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    session_unset();
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

mysqli_close($connect);
?>

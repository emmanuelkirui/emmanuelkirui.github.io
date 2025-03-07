<?php
session_start();
header('Content-Type: application/json');

// DB credentials
define('DB_HOST', 'sql105.infinityfree.com');
define('DB_USER', 'if0_37772405');
define('DB_PASS', 'hMCWvBjYOKjDE');
define('DB_NAME', 'if0_37772405_cps');

// Load PHPMailer
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// CSRF Token Validation
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper function to send JSON response
function sendResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// Function to send reset email using PHPMailer
function sendResetEmail($to, $resetLink) {
    $mail = new PHPMailer(true);

    try {
        // SMTP configuration (example using Gmail; adjust as needed)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Your SMTP host
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com'; // Your SMTP username
        $mail->Password = 'your-app-password'; // Your SMTP password or App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Sender and recipient
        $mail->setFrom('no-reply@yourdomain.com', 'CPS Football');
        $mail->addAddress($to);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request';
        $mail->Body = "Click here to reset your password: <a href='$resetLink'>$resetLink</a><br>This link expires in 1 hour.";
        $mail->AltBody = "Click here to reset your password: $resetLink\nThis link expires in 1 hour.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Handle incoming requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token (optional, add to form as hidden input if used)
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        sendResponse(false, 'Invalid CSRF token');
    }

    // Login
    if (isset($_POST['login'])) {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            sendResponse(false, 'Username and password are required');
        }

        $stmt = $pdo->prepare("SELECT * FROM cp_users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];
            sendResponse(true, 'Login successful');
        } else {
            sendResponse(false, 'Invalid username or password');
        }
    }

    // Signup
    if (isset($_POST['signup'])) {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
            sendResponse(false, 'All fields are required');
        }

        if ($password !== $confirmPassword) {
            sendResponse(false, 'Passwords do not match');
        }

        if (strlen($password) < 8) {
            sendResponse(false, 'Password must be at least 8 characters long');
        }

        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cp_users WHERE username = :username OR email = :email");
        $stmt->execute(['username' => $username, 'email' => $email]);
        if ($stmt->fetchColumn() > 0) {
            sendResponse(false, 'Username or email already taken');
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO cp_users (username, email, password) VALUES (:username, :email, :password)");
        $success = $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword
        ]);

        if ($success) {
            $_SESSION['username'] = $username;
            $_SESSION['user_id'] = $pdo->lastInsertId();
            sendResponse(true, 'Signup successful');
        } else {
            sendResponse(false, 'Signup failed. Please try again.');
        }
    }

    // Password Reset Request
    if (isset($_POST['reset_request'])) {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

        if (empty($email)) {
            sendResponse(false, 'Email is required');
        }

        $stmt = $pdo->prepare("SELECT * FROM cp_users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            sendResponse(false, 'No account found with this email');
        }

        // Generate reset token
        $resetToken = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $pdo->prepare("INSERT INTO cp_password_resets (user_id, token, expires) VALUES (:user_id, :token, :expires)");
        $stmt->execute([
            'user_id' => $user['id'],
            'token' => $resetToken,
            'expires' => $expires
        ]);

        // Send reset email using PHPMailer
        $resetLink = "http://yourdomain.com/reset_password.php?token=$resetToken";
        if (sendResetEmail($email, $resetLink)) {
            sendResponse(true, 'Password reset link sent to your email');
        } else {
            sendResponse(false, 'Failed to send reset email');
        }
    }

    // Logout (though handled via GET in your main file, included here for completeness)
    if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
        session_unset();
        session_destroy();
        sendResponse(true, 'Logged out successfully');
    }
} else {
    sendResponse(false, 'Invalid request method');
}
?>

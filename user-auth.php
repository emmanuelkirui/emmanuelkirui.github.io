<?php
session_start();

// Include database and PHPMailer
require_once 'config.php';
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize tables
$connect->query("CREATE TABLE IF NOT EXISTS cps_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    verification_token VARCHAR(255),
    verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$connect->query("CREATE TABLE IF NOT EXISTS cps_login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (user_id) REFERENCES cps_users(id)
)");

$connect->query("CREATE TABLE IF NOT EXISTS cps_password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (token)
)");

// Email sending function
function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Update with your SMTP host
        $mail->SMTPAuth = true;
        $mail->Username = 'your-email@gmail.com'; // Update with your email
        $mail->Password = 'your-app-password'; // Update with your app-specific password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('your-email@gmail.com', 'Your App');
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Mailer Error: " . $mail->ErrorInfo;
    }
}

// Process authentication requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = '';

    // Signup
    if (isset($_POST['signup_email'])) {
        $name = $connect->real_escape_string($_POST['signup_name']);
        $email = $connect->real_escape_string($_POST['signup_email']);
        $password = password_hash($_POST['signup_password'], PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));

        $sql = "INSERT INTO cps_users (full_name, email, password, verification_token) VALUES (?, ?, ?, ?)";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("ssss", $name, $email, $password, $token);
        
        if ($stmt->execute()) {
            $body = "Please verify your email: <a href='http://yourdomain.com/auth_user.php?verify=$token'>Click here</a>";
            $result = sendEmail($email, "Verify Your Email", $body);
            $response = $result === true ? "Signup successful! Please check your email to verify." : $result;
        } else {
            $response = "Signup failed: " . $connect->error;
        }
        $stmt->close();
    }

    // Login
    if (isset($_POST['login_email'])) {
        $email = $connect->real_escape_string($_POST['login_email']);
        $password = $_POST['login_password'];
        
        $sql = "SELECT * FROM cps_users WHERE email=?";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if ($user['verified'] && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                
                $ip = $_SERVER['REMOTE_ADDR'];
                $user_id = $user['id'];
                $connect->query("INSERT INTO cps_login_logs (user_id, ip_address) VALUES ($user_id, '$ip')");
                
                $response = "Login successful! Reloading...";
            } else {
                $response = "Invalid credentials or email not verified.";
            }
        } else {
            $response = "Email not found.";
        }
        $stmt->close();
    }

    // Reset Password Request
    if (isset($_POST['reset_email'])) {
        $email = $connect->real_escape_string($_POST['reset_email']);
        $sql = "SELECT * FROM cps_users WHERE email=?";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            $token = bin2hex(random_bytes(32));
            $sql = "INSERT INTO cps_password_resets (email, token) VALUES (?, ?)";
            $stmt = $connect->prepare($sql);
            $stmt->bind_param("ss", $email, $token);
            
            if ($stmt->execute()) {
                $body = "Reset your password: <a href='http://yourdomain.com/auth_user.php?reset=$token'>Click here</a>";
                $result = sendEmail($email, "Password Reset", $body);
                $response = $result === true ? "Reset link sent to your email." : $result;
            }
        } else {
            $response = "Email not found.";
        }
        $stmt->close();
    }

    // Update Password
    if (isset($_POST['new_password']) && isset($_GET['reset'])) {
        $token = $connect->real_escape_string($_GET['reset']);
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        
        $sql = "SELECT * FROM cps_password_resets WHERE token=?";
        $stmt = $connect->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $reset = $result->fetch_assoc();
            $email = $reset['email'];
            $sql = "UPDATE cps_users SET password=? WHERE email=?";
            $stmt = $connect->prepare($sql);
            $stmt->bind_param("ss", $new_password, $email);
            
            if ($stmt->execute()) {
                $connect->query("DELETE FROM cps_password_resets WHERE token='$token'");
                $response = "Password updated successfully! You can now login.";
            }
        } else {
            $response = "Invalid or expired reset link.";
        }
        $stmt->close();
    }

    // Output response as JSON for AJAX
    if ($response) {
        header('Content-Type: application/json');
        echo json_encode(['message' => $response, 'reload' => strpos($response, "successful") !== false]);
        exit;
    }
}

// Handle URL actions
if (isset($_GET['verify'])) {
    $token = $connect->real_escape_string($_GET['verify']);
    $sql = "UPDATE cps_users SET verified=1, verification_token=NULL WHERE verification_token=?";
    $stmt = $connect->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    
    $message = $stmt->affected_rows > 0 ? "Email verified! You can now login." : "Invalid or expired verification link.";
    echo "<script>alert('$message'); window.location.href='auth_user.php';</script>";
    $stmt->close();
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: auth_user.php");
    exit;
}

// Check reset token validity
$show_reset_form = false;
if (isset($_GET['reset'])) {
    $token = $connect->real_escape_string($_GET['reset']);
    $sql = "SELECT * FROM cps_password_resets WHERE token=?";
    $stmt = $connect->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $show_reset_form = $stmt->get_result()->num_rows > 0;
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { min-height: 100vh; display: flex; justify-content: center; align-items: center; font-family: Arial, sans-serif; background-color: #f0f2f5; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; padding: 10px; }
        .modal-content { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 400px; display: flex; flex-direction: column; }
        .close { align-self: flex-end; font-size: 24px; cursor: pointer; margin-bottom: 10px; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        .form-group label { margin-bottom: 5px; font-size: 14px; }
        .form-group input { padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
        .btn-group { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; flex: 1; }
        .btn-primary { background-color: #007bff; color: white; }
        .trigger-buttons { display: flex; gap: 10px; position: fixed; top: 20px; }
        .dashboard { position: fixed; top: 20px; right: 20px; display: flex; gap: 10px; align-items: center; }
        @media (max-width: 480px) {
            .modal-content { max-width: 90%; padding: 15px; }
            .btn { padding: 8px 15px; font-size: 12px; }
            .form-group input { font-size: 14px; }
            .trigger-buttons { flex-direction: column; align-items: center; width: 100%; padding: 0 10px; }
        }
        @media (max-width: 320px) { .modal-content { padding: 10px; } .btn-group { flex-direction: column; } }
    </style>
</head>
<body>
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="dashboard" id="auth-dashboard">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</span>
            <button class="btn btn-primary" onclick="alert('Settings feature TBD')">Settings</button>
            <a href="?logout" class="btn btn-primary">Logout</a>
        </div>
    <?php elseif ($show_reset_form): ?>
        <div id="updatePasswordModal" class="modal" style="display: flex;">
            <div class="modal-content">
                <span class="close" onclick="window.location.href='auth_user.php'">×</span>
                <h2>Update Password</h2>
                <form method="POST" action="auth_user.php?reset=<?php echo htmlspecialchars($_GET['reset']); ?>" onsubmit="return handleSubmit(this, 'updatePasswordModal')">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="trigger-buttons">
            <button class="btn btn-primary" onclick="openModal('loginModal')">Login</button>
            <button class="btn btn-primary" onclick="openModal('signupModal')">Signup</button>
        </div>

        <div id="loginModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('loginModal')">×</span>
                <h2>Login</h2>
                <form method="POST" action="auth_user.php" onsubmit="return handleSubmit(this, 'loginModal')">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="login_email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="login_password" required>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Login</button>
                        <button type="button" class="btn" onclick="openModal('resetModal'); closeModal('loginModal')">Forgot Password?</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="signupModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('signupModal')">×</span>
                <h2>Signup</h2>
                <form method="POST" action="auth_user.php" onsubmit="return handleSubmit(this, 'signupModal')">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="signup_name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="signup_email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="signup_password" required>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Signup</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="resetModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal('resetModal')">×</span>
                <h2>Reset Password</h2>
                <form method="POST" action="auth_user.php" onsubmit="return handleSubmit(this, 'resetModal')">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="reset_email" required>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }

        function handleSubmit(form, modalId) {
            event.preventDefault();
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form)
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.reload) {
                    window.location.reload();
                }
                closeModal(modalId);
            })
            .catch(error => alert('Error: ' + error));
            return false;
        }
    </script>
</body>
</html>

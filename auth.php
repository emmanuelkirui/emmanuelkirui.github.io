<?php
session_start();
header('Content-Type: application/json');

// DB credentials
define('DB_HOST', 'sql105.infinityfree.com');
define('DB_USER', 'if0_37772405');
define('DB_PASS', 'hMCWvBjYOKjDE');
define('DB_NAME', 'if0_37772405_cps');

// Include PHPMailer files
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

// Helper function to send JSON response
function sendResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// Function to get location and timezone from IP
function getLocationInfoFromIP() {
    $ip = $_SERVER['REMOTE_ADDR'];
    $result = [
        'location' => 'Unknown location',
        'timezone' => 'UTC'
    ];
    
    if ($ip === '127.0.0.1' || $ip === '::1') {
        $result['location'] = 'Localhost';
        return $result;
    }
    
    $url = "http://ip-api.com/json/{$ip}";
    $response = @file_get_contents($url);
    if ($response) {
        $data = json_decode($response, true);
        if ($data['status'] === 'success') {
            $result['location'] = "{$data['city']}, {$data['regionName']}, {$data['country']}";
            $result['timezone'] = $data['timezone'] ?? 'UTC';
        }
    }
    
    return $result;
}

// Function to get device and browser info from user agent
function getDeviceBrowserInfo() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $device = 'Unknown Device';
    $browser = 'Unknown Browser';

    if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
        $device = 'Mobile Device';
        if (preg_match('/Android/', $userAgent)) {
            $device = 'Android Device';
        } elseif (preg_match('/iPhone/', $userAgent)) {
            $device = 'iPhone';
        } elseif (preg_match('/iPad/', $userAgent)) {
            $device = 'iPad';
        }
    } elseif (preg_match('/Windows|Macintosh|Linux/', $userAgent)) {
        $device = 'Desktop';
    }

    if (preg_match('/Chrome/', $userAgent)) {
        $browser = 'Google Chrome';
    } elseif (preg_match('/Firefox/', $userAgent)) {
        $browser = 'Mozilla Firefox';
    } elseif (preg_match('/Safari/', $userAgent) && !preg_match('/Chrome/', $userAgent)) {
        $browser = 'Safari';
    } elseif (preg_match('/Edge/', $userAgent)) {
        $browser = 'Microsoft Edge';
    } elseif (preg_match('/MSIE|Trident/', $userAgent)) {
        $browser = 'Internet Explorer';
    }

    return [
        'device' => $device,
        'browser' => $browser
    ];
}

// Function to send notification email
function sendNotificationEmail($to, $type, $username, $location, $timezone, $device, $browser) {
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'emmanuelkirui042@gmail.com';
        $mail->Password = 'unwv yswa pqaq hefc';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('noreply@creativepulse.com', 'Creative Pulse Solutions');
        $mail->addAddress($to);

        date_default_timezone_set($timezone);
        $localTime = date('F j, Y \a\t h:i A');

        // Footer with social media icons and legal links
        $footer = '
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center;">
                <div style="margin-bottom: 15px;">
                    <a href="https://youtube.com/@emmanuelkirui9043" style="margin: 0 10px;">
                        <img src="https://cdn-icons-png.flaticon.com/512/1384/1384060.png" alt="YouTube" style="width: 24px; height: 24px;">
                    </a>
                    <a href="https://x.com/emmanuelkirui" style="margin: 0 10px;">
                        <img src="https://cdn-icons-png.flaticon.com/512/5969/5969020.png" alt="X" style="width: 24px; height: 24px;">
                    </a>
                    <a href="https://instagram.com/emmanuelkirui3" style="margin: 0 10px;">
                        <img src="https://cdn-icons-png.flaticon.com/512/174/174855.png" alt="Instagram" style="width: 24px; height: 24px;">
                    </a>
                    <a href="https://facebook.com/emmanuelkirui042" style="margin: 0 10px;">
                        <img src="https://cdn-icons-png.flaticon.com/512/124/124010.png" alt="Facebook" style="width: 24px; height: 24px;">
                    </a>
                    <a href="https://tiktok.com/@emmanuelkirui3" style="margin: 0 10px;">
                        <img src="https://cdn-icons-png.flaticon.com/512/3046/3046123.png" alt="TikTok" style="width: 24px; height: 24px;">
                    </a>
                </div>
                <div style="color: #666; font-size: 12px;">
                    <a href="https://creativepulse.42web.io/cps/privacy-policy" style="color: #007bff; text-decoration: none; margin: 0 10px;">Privacy Policy</a>
                    |
                    <a href="https://creativepulse.42web.io/cps/terms-conditions" style="color: #007bff; text-decoration: none; margin: 0 10px;">Terms & Conditions</a>
                </div>
                <p style="color: #999; font-size: 11px; margin-top: 10px;">© ' . date('Y') . ' Creative Pulse Solutions. All rights reserved.</p>
            </div>';

        $mail->isHTML(true);
        
        if ($type === 'login') {
            $mail->Subject = 'Security Alert: Successful Login to Your Account';
            $mail->Body = "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #2c3e50;'>Successful Login Notification</h2>
                        <p>Dear {$username},</p>
                        <p>We wanted to inform you that your Creative Pulse Solutions account was successfully accessed:</p>
                        <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                            <tr><td style='padding: 8px; border-bottom: 1px solid #eee;'><strong>Date & Time:</strong></td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$localTime} ({$timezone})</td></tr>
                            <tr><td style='padding: 8px; border-bottom: 1px solid #eee;'><strong>Location:</strong></td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$location}</td></tr>
                            <tr><td style='padding: 8px; border-bottom: 1px solid #eee;'><strong>Device:</strong></td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$device}</td></tr>
                            <tr><td style='padding: 8px;'><strong>Browser:</strong></td><td style='padding: 8px;'>{$browser}</td></tr>
                        </table>
                        <p>If this was you, no action is required. If you don't recognize this activity, please:</p>
                        <p><a href='https://creativepulse.42web.io/cps/reset_password.php' style='background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Your Password</a></p>
                        <p>Best regards,<br>The Creative Pulse Solutions Team</p>
                        <p style='font-size: 12px; color: #777;'>This is an automated message. Please do not reply directly to this email.</p>
                        {$footer}
                    </div>
                </body>
                </html>";
        } else {
            $mail->Subject = 'Welcome to Creative Pulse Solutions';
            $mail->Body = "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #2c3e50;'>Welcome to Creative Pulse Solutions</h2>
                        <p>Dear {$username},</p>
                        <p>Thank you for joining Creative Pulse Solutions! Your account was successfully created on:</p>
                        <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
                            <tr><td style='padding: 8px; border-bottom: 1px solid #eee;'><strong>Date & Time:</strong></td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$localTime} ({$timezone})</td></tr>
                            <tr><td style='padding: 8px; border-bottom: 1px solid #eee;'><strong>Location:</strong></td><td style='padding: 8px; border-bottom: 1px solid #eee;'>{$location}</td></tr>
                            <tr><td style='padding: 8px;'><strong>Device:</strong></td><td style='padding: 8px;'>{$device} via {$browser}</td></tr>
                        </table>
                        <p>We're excited to have you on board. Get started by:</p>
                        <ul>
                            <li>Exploring our services</li>
                            <li>Updating your profile</li>
                            <li>Contacting our support team if you need assistance</li>
                        </ul>
                        <p>Best regards,<br>The Creative Pulse Solutions Team</p>
                        <p style='font-size: 12px; color: #777;'>For support: support@creativepulse.com</p>
                        {$footer}
                    </div>
                </body>
                </html>";
        }
        
        $mail->AltBody = strip_tags($mail->Body);
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Function to send reset email using PHPMailer
function sendResetEmail($to, $resetLink) {
    $mail = new PHPMailer(true);

    try {
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'emmanuelkirui042@gmail.com';
        $mail->Password = 'unwv yswa pqaq hefc';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('noreply@creativepulse.com', 'Creative Pulse Solutions');
        $mail->addAddress($to);

        // Footer with social media icons and legal links
        $footer = '
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center;">
                <div style="margin-bottom: 15px;">
                    <a href="https://youtube.com/@emmanuelkirui9043" style="margin: 0 10px;">
                        <img src="https://cdn-icons-png.flaticon.com/512/1384/1384060.png" alt="YouTube" style="width: 24px; height: 24px;">
                    </a>
                    <a href="https://x.com/emmanuelkirui" style="margin: 0 10px;">
                        <img src="https://cdn-icons-png.flaticon.com/512/5969/5969020.png" alt="X" style="width: 24px; height: 24px;">
                    </a>
                    <a href="https://instagram.com/emmanuelkirui3" style="margin: 0 10px;">
                        <img src="https://cdn-icons-png.flaticon.com/512/174/174855.png" alt="Instagram" style="width: 24px; height: 24px;">
                    </a>
                    <a href="https://facebook.com/emmanuelkirui042" style="margin: 0 10px;">
                        <img src="https://cdn-icons-png.flaticon.com/512/124/124010.png" alt="Facebook" style="width: 24px; height: 24px;">
                    </a>
                    <a href="https://tiktok.com/@emmanuelkirui3" style="margin: 0 10px;">
                        <img src="https://cdn-icons-png.flaticon.com/512/3046/3046123.png" alt="TikTok" style="width: 24px; height: 24px;">
                    </a>
                </div>
                <div style="color: #666; font-size: 12px;">
                    <a href="https://creativepulse.42web.io/cps/privacy-policy" style="color: #007bff; text-decoration: none; margin: 0 10px;">Privacy Policy</a>
                    |
                    <a href="https://creativepulse.42web.io/cps/terms-conditions" style="color: #007bff; text-decoration: none; margin: 0 10px;">Terms & Conditions</a>
                </div>
                <p style="color: #999; font-size: 11px; margin-top: 10px;">© ' . date('Y') . ' Creative Pulse Solutions. All rights reserved.</p>
            </div>';

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - Creative Pulse Solutions';
        $mail->Body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #2c3e50;'>Password Reset Request</h2>
                    <p>Hello,</p>
                    <p>We received a request to reset your Creative Pulse Solutions account password. Click the button below to proceed:</p>
                    <p style='margin: 20px 0;'>
                        <a href='{$resetLink}' style='background-color: #3498db; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                    </p>
                    <p>This link will expire in 1 hour for security reasons. If you didn't request this reset, please contact our support team immediately.</p>
                    <p>Best regards,<br>The Creative Pulse Solutions Team</p>
                    <p style='font-size: 12px; color: #777;'>Support: support@creativepulse.42web.io | This is an automated message</p>
                    {$footer}
                </div>
            </body>
            </html>";
        $mail->AltBody = "Password Reset Request\n\nClick here to reset your password: {$resetLink}\nThis link expires in 1 hour.\n\nIf you didn't request this, contact support@creativepulse.com";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    session_unset();
    session_destroy();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    error_log("User logged out: " . ($_SESSION['username'] ?? 'Unknown') . " at " . date('Y-m-d H:i:s'));
    sendResponse(true, 'Logged out successfully', ['redirect' => 'valmanu.php']);
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            
            $locationInfo = getLocationInfoFromIP();
            $deviceBrowserInfo = getDeviceBrowserInfo();
            sendNotificationEmail(
                $user['email'],
                'login',
                $username,
                $locationInfo['location'],
                $locationInfo['timezone'],
                $deviceBrowserInfo['device'],
                $deviceBrowserInfo['browser']
            );
            
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
            
            $locationInfo = getLocationInfoFromIP();
            $deviceBrowserInfo = getDeviceBrowserInfo();
            sendNotificationEmail(
                $email,
                'signup',
                $username,
                $locationInfo['location'],
                $locationInfo['timezone'],
                $deviceBrowserInfo['device'],
                $deviceBrowserInfo['browser']
            );
            
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

        $resetToken = bin2hex(random_bytes(16));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $pdo->prepare("INSERT INTO cp_password_resets (user_id, token, expires) VALUES (:user_id, :token, :expires)");
        $stmt->execute([
            'user_id' => $user['id'],
            'token' => $resetToken,
            'expires' => $expires
        ]);

        $resetLink = "https://creativepulse.42web.io/cps/reset_password.php?token=$resetToken";
        if (sendResetEmail($email, $resetLink)) {
            sendResponse(true, 'Password reset link sent to your email');
        } else {
            sendResponse(false, 'Failed to send reset email');
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Invalid request method');
}
?>

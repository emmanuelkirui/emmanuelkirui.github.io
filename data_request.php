<?php
// data_request.php
session_start();
header('Content-Type: application/json');

// Include DB credentials and PHPMailer from auth.php
define('DB_HOST', 'sql105.infinityfree.com');
define('DB_USER', 'if0_37772405');
define('DB_PASS', 'hMCWvBjYOKjDE');
define('DB_NAME', 'if0_37772405_cps');

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Database connection
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

// Function to send data via email
function sendUserDataEmail($to, $username, $userData) {
    $mail = new PHPMailer(true);

    try {
        // SMTP Settings (same as auth.php)
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'emmanuelkirui042@gmail.com';
        $mail->Password = 'unwv yswa pqaq hefc';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('noreply@gmail.com', 'Creative Pulse Solutions (CEO)');
        $mail->addAddress($to);

        // Format user data
        $dataHtml = "<h2>Your Personal Data</h2>";
        $dataHtml .= "<table border='1' cellpadding='5'>";
        foreach ($userData as $key => $value) {
            $dataHtml .= "<tr><td>" . htmlspecialchars($key) . "</td><td>" . htmlspecialchars($value) . "</td></tr>";
        }
        $dataHtml .= "</table>";
        $dataHtml .= "<p>This is all the data we have associated with your account as of " . date('Y-m-d H:i:s') . "</p>";
        $dataHtml .= "<p>If you have any concerns, please contact our support team.</p>";

        $mail->isHTML(true);
        $mail->Subject = 'Your Data Request - Creative Pulse Solutions';
        $mail->Body = "Hello {$username},<br><br>Your data request has been processed. Below is all the information we have associated with your account:<br><br>" . $dataHtml;
        $mail->AltBody = "Hello {$username},\n\nYour data request has been processed. Here is your data:\n" . print_r($userData, true);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Rate limiting function
function checkRateLimit($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cp_data_requests WHERE user_id = :user_id AND request_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchColumn() < 3; // Limit to 3 requests per day
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if user is authenticated
    if (!isset($_SESSION['user_id'])) {
        sendResponse(false, 'Please login to request your data');
    }

    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'];

    // Check rate limit
    if (!checkRateLimit($pdo, $userId)) {
        sendResponse(false, 'You have exceeded the daily data request limit (3 requests per 24 hours)');
    }

    // Get user data
    try {
        $stmt = $pdo->prepare("SELECT username, email, created_at FROM cp_users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userData) {
            // Prepare data to send
            $dataToSend = [
                'Username' => $userData['username'],
                'Email' => $userData['email'],
                'Account Created' => $userData['created_at']
            ];

            // Log the request
            $stmt = $pdo->prepare("INSERT INTO cp_data_requests (user_id, request_time) VALUES (:user_id, NOW())");
            $stmt->execute(['user_id' => $userId]);

            // Send email
            if (sendUserDataEmail($userData['email'], $username, $dataToSend)) {
                sendResponse(true, 'Your data has been sent to your registered email address');
            } else {
                sendResponse(false, 'Failed to send data email');
            }
        } else {
            sendResponse(false, 'User data not found');
        }
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        sendResponse(false, 'An error occurred while processing your request');
    }
} else {
    // Serve HTML form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Data Request - Creative Pulse Solutions</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
                background-color: #f5f5f5;
            }
            .container {
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            h1 {
                color: #333;
                margin-bottom: 20px;
            }
            .btn {
                background-color: #007bff;
                color: white;
                padding: 10px 20px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 16px;
            }
            .btn:hover {
                background-color: #0056b3;
            }
            .message {
                margin-top: 20px;
                padding: 10px;
                border-radius: 4px;
            }
            .success {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }
            .error {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Request Your Data</h1>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <p>Please <a href="valmanu.php">login</a> to request your data.</p>
            <?php else: ?>
                <p>Click the button below to receive all data associated with your account via email.</p>
                <button class="btn" onclick="requestData()">Request My Data</button>
                <div id="message" class="message" style="display: none;"></div>
            <?php endif; ?>
        </div>

        <script>
        async function requestData() {
            const messageDiv = document.getElementById('message');
            messageDiv.style.display = 'none';
            
            try {
                const response = await fetch('data_request.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });
                
                const data = await response.json();
                
                messageDiv.style.display = 'block';
                messageDiv.textContent = data.message;
                messageDiv.className = 'message ' + (data.success ? 'success' : 'error');
                
            } catch (error) {
                messageDiv.style.display = 'block';
                messageDiv.textContent = 'An error occurred while processing your request';
                messageDiv.className = 'message error';
            }
        }
        </script>
    </body>
    </html>
    <?php
}

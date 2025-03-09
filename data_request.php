<?php
/**
 * Professional Data Request Handler
 * Handles user data requests with database-validated password and email
 * 
 * @author Emmanuel Kirui 
 * @version 1.5
 * @date March 08, 2025
 */

namespace CreativePulseSolutions;

session_start();
header('Content-Type: application/json');

// Configuration constants
const DB_CONFIG = [
    'host' => 'sql105.infinityfree.com',
    'user' => 'if0_37772405',
    'pass' => 'hMCWvBjYOKjDE',
    'name' => 'if0_37772405_cps'
];

// Dependencies
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Class DataRequestHandler
 * Manages data requests with database validation
 */
class DataRequestHandler {
    private $pdo;
    private $maxRequestsPerDay = 3;

    public function __construct() {
        try {
            $this->pdo = new \PDO(
                "mysql:host=" . DB_CONFIG['host'] . ";dbname=" . DB_CONFIG['name'] . ";charset=utf8mb4",
                DB_CONFIG['user'],
                DB_CONFIG['pass'],
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
                ]
            );
        } catch (\PDOException $e) {
            $this->sendJsonResponse(false, "Database connection failed: " . $e->getMessage());
        }
    }

    private function sendJsonResponse(bool $success, string $message, array $data = []): void {
        exit(json_encode(array_merge(['success' => $success, 'message' => $message], $data)));
    }

    private function validateCredentials(string $email, string $password): ?array {
        $stmt = $this->pdo->prepare(
            "SELECT id, username, password, email 
             FROM cp_users 
             WHERE email = :email"
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }

        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ];
    }

    private function sendUserDataEmail(string $to, string $username, array $userData): bool {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'emmanuelkirui042@gmail.com';
            $mail->Password = 'unwv yswa pqaq hefc';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('noreply@creativepulsesolutions.com', 'Creative Pulse Solutions');
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = 'Your Data Request - Creative Pulse Solutions';

            $dataHtml = $this->formatUserDataHtml($userData);
            $mail->Body = $this->getEmailBody($username, $dataHtml);
            $mail->AltBody = $this->getEmailPlainText($username, $userData);

            return $mail->send();
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }

    private function formatUserDataHtml(array $userData): string {
        $html = "<h2 style='color: #333;'>Your Personal Data</h2><table style='border-collapse: collapse; width: 100%;'>";
        foreach ($userData as $key => $value) {
            $html .= "<tr><td style='padding: 8px; border: 1px solid #ddd; background: #f8f9fa;'>" . htmlspecialchars($key) . "</td><td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($value) . "</td></tr>";
        }
        $html .= "</table>";
        $html .= "<p style='color: #666; font-size: 12px;'>Data as of " . date('Y-m-d H:i:s') . "</p>";
        return $html;
    }

    private function getEmailBody(string $username, string $dataHtml): string {
        return "<html><body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto;'>
                <h1 style='color: #007bff;'>Creative Pulse Solutions</h1>
                <p>Hello {$username},</p>
                <p>Your data request has been processed successfully. Below is the information associated with your account:</p>
                {$dataHtml}
                <p style='margin-top: 20px;'>If you have any questions, please contact our support team at <a href='mailto:support@creativepulsesolutions.com' style='color: #007bff;'>support@creativepulsesolutions.com</a>.</p>
                <p style='color: #666; font-size: 12px;'>This is an automated message, please do not reply directly to this email.</p>
            </div>
        </body></html>";
    }

    private function getEmailPlainText(string $username, array $userData): string {
        return "Creative Pulse Solutions\n\nHello {$username},\n\nYour data request has been processed.\n\nData:\n" . print_r($userData, true)
            . "\nContact support@creativepulse42web.io for assistance.";
    }

    private function checkRateLimit(int $userId): bool {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM cp_data_requests 
             WHERE user_id = :user_id 
             AND request_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchColumn() < $this->maxRequestsPerDay;
    }

    private function logRequest(int $userId): void {
        $stmt = $this->pdo->prepare(
            "INSERT INTO cp_data_requests (user_id, request_time) 
             VALUES (:user_id, NOW())"
        );
        $stmt->execute(['user_id' => $userId]);
    }

    public function handleRequest(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->serveForm();
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $password = $input['password'] ?? '';
        $email = $input['email'] ?? '';

        if (empty($password)) {
            $this->sendJsonResponse(false, 'Password is required');
        }
        if (empty($email)) {
            $this->sendJsonResponse(false, 'Email is required');
        }

        // Validate credentials and get user data
        $user = $this->validateCredentials($email, $password);
        if (!$user) {
            $this->sendJsonResponse(false, 'Invalid email or password');
        }

        $userId = $user['id'];
        $username = htmlspecialchars($user['username']);

        if (!$this->checkRateLimit($userId)) {
            $this->sendJsonResponse(false, "Rate limit exceeded ({$this->maxRequestsPerDay} requests per 24 hours)");
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT username, email, created_at 
                 FROM cp_users 
                 WHERE id = :id"
            );
            $stmt->execute(['id' => $userId]);
            $userData = $stmt->fetch();

            if (!$userData) {
                $this->sendJsonResponse(false, 'User data not found');
            }

            $dataToSend = [
                'Username' => $userData['username'],
                'Email' => $userData['email'],
                'Account Created' => $userData['created_at']
            ];

            $this->logRequest($userId);
            $emailSent = $this->sendUserDataEmail($email, $username, $dataToSend);

            $this->sendJsonResponse(
                $emailSent,
                $emailSent ? 'Data has been successfully sent to your email' : 'Failed to send email, please try again later'
            );
        } catch (\PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            $this->sendJsonResponse(false, 'An error occurred while processing your request');
        }
    }

    private function serveForm(): void {
        header('Content-Type: text/html');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Data Request | Creative Pulse Solutions</title>
            <style>
                :root {
                    --primary: #007bff;
                    --secondary: #6c757d;
                    --success: #28a745;
                    --danger: #dc3545;
                    --light: #f8f9fa;
                    --dark: #343a40;
                }
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Segoe UI', Arial, sans-serif;
                    background-color: var(--light);
                    color: var(--dark);
                    line-height: 1.6;
                }
                .container {
                    max-width: 480px;
                    margin: 40px auto;
                    padding: 30px;
                    background: white;
                    border-radius: 8px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .header h1 {
                    color: var(--primary);
                    font-size: 28px;
                    margin-bottom: 10px;
                }
                .header p {
                    color: var(--secondary);
                    font-size: 16px;
                }
                .form-group {
                    margin-bottom: 20px;
                }
                label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 500;
                    color: var(--dark);
                }
                input[type="password"],
                input[type="email"] {
                    width: 100%;
                    padding: 12px;
                    border: 1px solid #ced4da;
                    border-radius: 4px;
                    font-size: 16px;
                    transition: border-color 0.3s ease;
                }
                input[type="password"]:focus,
                input[type="email"]:focus {
                    outline: none;
                    border-color: var(--primary);
                    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
                }
                .btn {
                    display: inline-block;
                    width: 48%;
                    padding: 12px;
                    background: var(--primary);
                    color: white;
                    border: none;
                    border-radius: 4px;
                    font-size: 16px;
                    font-weight: 500;
                    cursor: pointer;
                    transition: background-color 0.3s ease;
                }
                .btn-secondary {
                    background: var(--secondary);
                    margin-right: 4%;
                }
                .btn:hover {
                    background: #0056b3;
                }
                .btn-secondary:hover {
                    background: #5a6268;
                }
                .btn:disabled {
                    background: var(--secondary);
                    cursor: not-allowed;
                }
                .message {
                    margin-top: 20px;
                    padding: 12px;
                    border-radius: 4px;
                    font-size: 14px;
                    text-align: center;
                }
                .success {
                    background: #d4edda;
                    color: var(--success);
                    border: 1px solid #c3e6cb;
                }
                .error {
                    background: #f8d7da;
                    color: var(--danger);
                    border: 1px solid #f5c6cb;
                }
                .button-group {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Data Request</h1>
                    <p>Creative Pulse Solutions</p>
                </div>

                <form id="dataRequestForm" onsubmit="requestData(event)">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required 
                            placeholder="Enter your email"
                        >
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            minlength="8"
                            placeholder="Enter your password"
                        >
                    </div>
                    <div class="button-group">
                        <button type="button" class="btn btn-secondary" onclick="history.back()">Back</button>
                        <button type="submit" class="btn" id="submitBtn">Request My Data</button>
                    </div>
                    <div id="message" class="message" style="display: none;"></div>
                </form>
            </div>

            <script>
            async function requestData(event) {
                event.preventDefault();
                
                const form = document.getElementById('dataRequestForm');
                const submitBtn = document.getElementById('submitBtn');
                const messageDiv = document.getElementById('message');
                const password = document.getElementById('password').value;
                const email = document.getElementById('email').value;

                messageDiv.style.display = 'none';
                
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processing...';

                try {
                    const response = await fetch('<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ 
                            password: password,
                            email: email 
                        })
                    });

                    const data = await response.json();
                    
                    messageDiv.style.display = 'block';
                    messageDiv.textContent = data.message;
                    messageDiv.className = `message ${data.success ? 'success' : 'error'}`;
                    
                    if (data.success) {
                        form.reset();
                    }
                } catch (error) {
                    messageDiv.style.display = 'block';
                    messageDiv.textContent = 'A network error occurred. Please try again.';
                    messageDiv.className = 'message error';
                    console.error('Request failed:', error);
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Request My Data';
                }
            }
            </script>
        </body>
        </html>
        <?php
        exit;
    }
}

// Execute
$handler = new DataRequestHandler();
$handler->handleRequest();

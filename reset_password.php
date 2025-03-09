<?php
/**
 * Password Reset Handler
 * Handles password reset requests via token validation
 * @author Emmanuel Kirui 
 * @version 1.0
 * @date March 09, 2025
 */

session_start();

// Database Configuration
const DB_CONFIG = [
    'host' => 'sql105.infinityfree.com',
    'username' => 'if0_37772405',
    'password' => 'hMCWvBjYOKjDE',
    'database' => 'if0_37772405_cps'
];

/**
 * Establishes database connection using PDO
 * @return PDO Database connection instance
 * @throws PDOException
 */
function initializeDatabase(): PDO {
    try {
        $dsn = "mysql:host=" . DB_CONFIG['host'] . ";dbname=" . DB_CONFIG['database'] . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_CONFIG['username'], DB_CONFIG['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        exit("Database connection failed: " . htmlspecialchars($e->getMessage()));
    }
}

/**
 * Processes password reset request
 * @param PDO $pdo Database connection
 * @param string $token Reset token from URL
 */
function processPasswordReset(PDO $pdo, string $token): void {
    // Validate token
    $stmt = $pdo->prepare("
        SELECT * FROM cp_password_resets 
        WHERE token = :token 
        AND expires > NOW()
    ");
    $stmt->execute(['token' => $token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        exit("Invalid or expired token.");
    }

    // Handle password update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
        
        if (empty($password)) {
            exit("Password cannot be empty.");
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE cp_users 
            SET password = :password 
            WHERE id = :user_id
        ");
        $stmt->execute([
            'password' => $hashedPassword,
            'user_id' => $reset['user_id']
        ]);

        // Clean up token
        $pdo->prepare("
            DELETE FROM cp_password_resets 
            WHERE token = :token
        ")->execute(['token' => $token]);

        displaySuccessMessage();
        exit;
    }

    // Display reset form
    displayResetForm();
}

/**
 * Displays success message after password reset
 */
function displaySuccessMessage(): void {
    $domain = 'https://creativepulse.42web.io/valmanu';
    echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Password Reset Success</title>
        </head>
        <body>
            <p>Password updated successfully!</p>
            <a href="{$domain}">Return to Main Page</a>
        </body>
        </html>
    HTML;
}

/**
 * Displays password reset form
 */
function displayResetForm(): void {
    echo <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Reset Password</title>
            <style>
                .container { max-width: 400px; margin: 50px auto; }
                input { width: 100%; padding: 8px; margin: 10px 0; }
                button { padding: 8px 16px; }
            </style>
        </head>
        <body>
            <div class="container">
                <form method="POST">
                    <input type="password" name="password" placeholder="Enter New Password" required>
                    <button type="submit">Reset Password</button>
                </form>
            </div>
        </body>
        </html>
    HTML;
}

// Main execution
try {
    $pdo = initializeDatabase();

    if (!isset($_GET['token'])) {
        exit("No token provided.");
    }

    $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
    processPasswordReset($pdo, $token);

} catch (Exception $e) {
    http_response_code(500);
    exit("An error occurred: " . htmlspecialchars($e->getMessage()));
}

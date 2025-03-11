<?php
/**
 * Password Reset Handler
 * Manages password reset requests through secure token validation
 *
 * @author Emmanuel Kirui
 * @version 1.0
 * @since March 09, 2025
 */

declare(strict_types=1);

session_start();

/**
 * Database configuration constants
 */
const DB_CONFIG = [
    'host'     => 'sql105.infinityfree.com',
    'username' => 'if0_37772405',
    'password' => 'hMCWvBjYOKjDE',
    'database' => 'if0_37772405_cps'
];

/**
 * Initializes a PDO database connection
 *
 * @return PDO Database connection instance
 * @throws PDOException If connection fails
 */
function initializeDatabase(): PDO
{
    try {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            DB_CONFIG['host'],
            DB_CONFIG['database']
        );
        
        $pdo = new PDO($dsn, DB_CONFIG['username'], DB_CONFIG['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]);
        
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Database connection failed: ' . htmlspecialchars($e->getMessage()));
    }
}

/**
 * Processes the password reset request
 *
 * @param PDO $pdo Database connection instance
 * @param string $token Password reset token
 * @return void
 */
function processPasswordReset(PDO $pdo, string $token): void
{
    // Validate reset token
    $stmt = $pdo->prepare('
        SELECT * FROM cp_password_resets 
        WHERE token = :token 
        AND expires > NOW()
    ');
    $stmt->execute(['token' => $token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        exit('Invalid or expired reset token.');
    }

    // Process password update on form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
        
        if (empty($password)) {
            exit('Password cannot be empty.');
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('
            UPDATE cp_users 
            SET password = :password 
            WHERE id = :user_id
        ');
        $stmt->execute([
            'password' => $hashedPassword,
            'user_id'  => $reset['user_id']
        ]);

        // Remove used token
        $pdo->prepare('
            DELETE FROM cp_password_resets 
            WHERE token = :token
        ')->execute(['token' => $token]);

        displaySuccessMessage();
        exit;
    }

    displayResetForm();
}

/**
 * Renders the success message after password reset
 *
 * @return void
 */
function displaySuccessMessage(): void
{
    $domain = 'https://creativepulse.42web.io/valmanu';
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Successful</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f5f5f5;
        }
        .success-container {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <h2>Success</h2>
        <p>Your password has been updated successfully.</p>
        <a href="{$domain}">Return to Main Page</a>
    </div>
</body>
</html>
HTML;
}

/**
 * Renders the password reset form
 *
 * @return void
 */
function displayResetForm(): void
{
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            margin-top: 0;
            color: #333;
        }
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            margin: 0.5rem 0 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 0.75rem;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        <form method="POST">
            <input type="password" name="password" placeholder="Enter new password" required>
            <button type="submit">Reset Password</button>
        </form>
    </div>
</body>
</html>
HTML;
}

// Main execution block
try {
    $pdo = initializeDatabase();

    if (!isset($_GET['token'])) {
        exit('No reset token provided.');
    }

    $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
    processPasswordReset($pdo, $token);

} catch (Exception $e) {
    http_response_code(500);
    exit('An error occurred: ' . htmlspecialchars($e->getMessage()));
}

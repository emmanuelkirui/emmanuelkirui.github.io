<?php
session_start();

// DB credentials
define('DB_HOST', 'sql105.infinityfree.com');
define('DB_USER', 'if0_37772405');
define('DB_PASS', 'hMCWvBjYOKjDE');
define('DB_NAME', 'if0_37772405_cps');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $pdo->prepare("SELECT * FROM cp_password_resets WHERE token = :token AND expires > NOW()");
    $stmt->execute(['token' => $token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reset) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $password = $_POST['password'];
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE cp_users SET password = :password WHERE id = :user_id");
            $stmt->execute(['password' => $hashedPassword, 'user_id' => $reset['user_id']]);
            $pdo->prepare("DELETE FROM cp_password_resets WHERE token = :token")->execute(['token' => $token]);
            echo "Password updated successfully! <a href='http://yourdomain.com/your_main_file.php'>Go back</a>";
            exit;
        }
        // Show reset form
        ?>
        <!DOCTYPE html>
        <html>
        <body>
            <form method="POST">
                <input type="password" name="password" placeholder="New Password" required>
                <button type="submit">Reset Password</button>
            </form>
        </body>
        </html>
        <?php
    } else {
        echo "Invalid or expired token.";
    }
} else {
    echo "No token provided.";
}
?>

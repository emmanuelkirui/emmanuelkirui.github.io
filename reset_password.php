<?php
session_start();
include 'config.php';

$token = isset($_GET['token']) ? sanitize($_GET['token']) : '';

if (empty($token)) {
    die("Invalid token");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background-color: #f4f4f4; }
        .reset-container { width: 300px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
        button { width: 100%; padding: 10px; background: #4caf50; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #45a049; }
        .message { margin-top: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2>Reset Password</h2>
        <form id="resetForm">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="password" name="new_password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <button type="submit">Reset Password</button>
            <div class="message" id="message"></div>
        </form>
    </div>

    <script>
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('reset_password', true);

            fetch('auth.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messageDiv = document.getElementById('message');
                messageDiv.textContent = data.message;
                if (data.success) {
                    setTimeout(() => window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>', 2000);
                }
            })
            .catch(error => console.error('Error:', error));
        });
    </script>
</body>
</html>

<?php
function sanitize($data) {
    global $connect;
    return mysqli_real_escape_string($connect, trim($data));
}
?>

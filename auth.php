<?php
// login.php
session_start();

class Auth {
    private $dbFile = 'users.json';
    
    private function loadUsers() {
        if (!file_exists($this->dbFile)) {
            file_put_contents($this->dbFile, json_encode([]));
        }
        return json_decode(file_get_contents($this->dbFile), true);
    }
    
    private function saveUsers($users) {
        file_put_contents($this->dbFile, json_encode($users, JSON_PRETTY_PRINT));
    }
    
    public function signup($username, $email, $password) {
        $users = $this->loadUsers();
        
        if (isset($users[$username])) {
            return ['success' => false, 'message' => 'Username already exists'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        $users[$username] = [
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $this->saveUsers($users);
        $_SESSION['user'] = $username;
        return ['success' => true, 'message' => 'Registration successful'];
    }
    
    public function login($username, $password) {
        $users = $this->loadUsers();
        
        if (!isset($users[$username]) || !password_verify($password, $users[$username]['password'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        $_SESSION['user'] = $username;
        return ['success' => true, 'message' => 'Login successful'];
    }
    
    public function logout() {
        unset($_SESSION['user']);
        session_destroy();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user']);
    }
    
    public function getCurrentUser() {
        return $_SESSION['user'] ?? null;
    }
}

$auth = new Auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    switch ($action) {
        case 'signup':
            echo json_encode($auth->signup($username, $email, $password));
            break;
        case 'login':
            echo json_encode($auth->login($username, $password));
            break;
        case 'logout':
            $auth->logout();
            echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
            break;
    }
    exit;
}

// Handle direct form submission (non-AJAX fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) {
    $action = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $result = $action === 'signup' 
        ? $auth->signup($username, $email, $password)
        : $auth->login($username, $password);
    
    if ($result['success']) {
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
    $error_message = $result['message'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CPS Football</title>
    <style>
        :root {
            --primary-color: #2ecc71;
            --secondary-color: #3498db;
            --text-color: #333;
            --bg-color: #f4f4f4;
            --card-bg: #fff;
            --shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        [data-theme="dark"] {
            --primary-color: #27ae60;
            --secondary-color: #2980b9;
            --text-color: #ecf0f1;
            --bg-color: #2c3e50;
            --card-bg: #34495e;
            --shadow: 0 4px 6px rgba(0,0,0,0.3);
        }

        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            transition: all 0.3s ease;
        }

        .login-container {
            background-color: var(--card-bg);
            padding: 40px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 400px;
            margin: 20px;
        }

        .login-container h1 {
            text-align: center;
            color: var(--primary-color);
            margin-bottom: 30px;
        }

        .auth-tabs {
            display: flex;
            border-bottom: 2px solid var(--primary-color);
            margin-bottom: 20px;
        }

        .tab-btn {
            flex: 1;
            padding: 10px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-color);
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-radius: 5px 5px 0 0;
        }

        .auth-form {
            display: none;
        }

        .auth-form.active {
            display: block;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-color);
        }

        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--text-color);
            border-radius: 5px;
            background-color: var(--bg-color);
            color: var(--text-color);
            box-sizing: border-box;
        }

        button[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        button[type="submit"]:hover {
            background-color: var(--secondary-color);
        }

        .auth-message {
            margin-top: 10px;
            text-align: center;
            color: var(--text-color);
        }

        .error-message {
            color: #dc3545;
            text-align: center;
            margin-bottom: 15px;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--primary-color);
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>CPS Football</h1>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <div class="auth-tabs">
            <button class="tab-btn active" data-tab="login">Login</button>
            <button class="tab-btn" data-tab="signup">Sign Up</button>
        </div>

        <form id="loginForm" class="auth-form active" method="POST">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label for="login-username">Username</label>
                <input type="text" id="login-username" name="username" required>
            </div>
            <div class="form-group">
                <label for="login-password">Password</label>
                <input type="password" id="login-password" name="password" required>
            </div>
            <button type="submit">Login</button>
            <div id="login-message" class="auth-message"></div>
        </form>

        <form id="signupForm" class="auth-form" method="POST">
            <input type="hidden" name="action" value="signup">
            <div class="form-group">
                <label for="signup-username">Username</label>
                <input type="text" id="signup-username" name="username" required>
            </div>
            <div class="form-group">
                <label for="signup-email">Email</label>
                <input type="email" id="signup-email" name="email" required>
            </div>
            <div class="form-group">
                <label for="signup-password">Password</label>
                <input type="password" id="signup-password" name="password" required>
            </div>
            <button type="submit">Sign Up</button>
            <div id="signup-message" class="auth-message"></div>
        </form>

        <a href="javascript:history.back()" class="back-link">Back to Predictions</a>
    </div>

    <script>
        // Tab switching
        const tabBtns = document.querySelectorAll('.tab-btn');
        const forms = document.querySelectorAll('.auth-form');

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                forms.forEach(form => {
                    form.classList.toggle('active', form.id === `${btn.dataset.tab}Form`);
                });
            });
        });

        // AJAX form handling
        const loginForm = document.getElementById('loginForm');
        const signupForm = document.getElementById('signupForm');

        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('ajax', 'true');

            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messageDiv = document.getElementById('login-message');
                messageDiv.textContent = data.message;
                if (data.success) {
                    setTimeout(() => window.location.href = '<?php echo dirname($_SERVER['PHP_SELF']); ?>/index.php', 1000);
                }
            })
            .catch(error => {
                document.getElementById('login-message').textContent = 'An error occurred. Please try again.';
                console.error('Login error:', error);
            });
        });

        signupForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('ajax', 'true');

            fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messageDiv = document.getElementById('signup-message');
                messageDiv.textContent = data.message;
                if (data.success) {
                    setTimeout(() => window.location.href = '<?php echo dirname($_SERVER['PHP_SELF']); ?>/index.php', 1000);
                }
            })
            .catch(error => {
                document.getElementById('signup-message').textContent = 'An error occurred. Please try again.';
                console.error('Signup error:', error);
            });
        });

        // Theme handling
        window.onload = function() {
            const theme = document.cookie.split('; ')
                .find(row => row.startsWith('theme='))
                ?.split('=')[1];
            if (theme) {
                document.body.setAttribute('data-theme', theme);
            }
        };
    </script>
</body>
</html>

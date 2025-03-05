<?php
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
?>

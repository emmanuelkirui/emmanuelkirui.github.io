<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to manage your settings']);
    exit;
}

// DB credentials
define('DB_HOST', 'sql105.infinityfree.com');
define('DB_USER', 'if0_37772405');
define('DB_PASS', 'hMCWvBjYOKjDE');
define('DB_NAME', 'if0_37772405_cps');

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

// Check if current user is admin
function isAdmin($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT user_type FROM cp_users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user && $user['user_type'] === 'admin';
}

// Get current user data
function getUserData($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, user_type FROM cp_users WHERE id = :id");
    $stmt->execute(['id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all users (for admin)
function getAllUsers($pdo) {
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, user_type FROM cp_users ORDER BY id");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $isAdminUser = isAdmin($pdo, $userId);

    // Admin-specific endpoints
    if ($isAdminUser) {
        // Get all users
        if (isset($_POST['get_all_users'])) {
            $users = getAllUsers($pdo);
            sendResponse(true, 'Users retrieved successfully', ['users' => $users]);
        }

        // Update any user's data
        if (isset($_POST['admin_update_user'])) {
            $targetUserId = filter_input(INPUT_POST, 'target_user_id', FILTER_VALIDATE_INT);
            $newUsername = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
            $newEmail = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
            $newFullName = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING);
            $newUserType = filter_input(INPUT_POST, 'user_type', FILTER_SANITIZE_STRING);

            if (!$targetUserId) {
                sendResponse(false, 'Invalid user ID');
            }

            $updates = [];
            $params = ['id' => $targetUserId];

            if ($newUsername) {
                if (strlen($newUsername) < 3) {
                    sendResponse(false, 'Username must be at least 3 characters long');
                }
                $updates[] = "username = :username";
                $params['username'] = $newUsername;
            }
            if ($newEmail) {
                $updates[] = "email = :email";
                $params['email'] = $newEmail;
            }
            if ($newFullName) {
                if (strlen($newFullName) < 2) {
                    sendResponse(false, 'Full name must be at least 2 characters long');
                }
                $updates[] = "full_name = :full_name";
                $params['full_name'] = $newFullName;
            }
            if ($newUserType) {
                // Validate user_type (add your allowed values)
                $allowedTypes = ['admin', 'user']; // Modify as needed
                if (!in_array($newUserType, $allowedTypes)) {
                    sendResponse(false, 'Invalid user type');
                }
                $updates[] = "user_type = :user_type";
                $params['user_type'] = $newUserType;
            }

            if (empty($updates)) {
                sendResponse(false, 'No changes specified');
            }

            $query = "UPDATE cp_users SET " . implode(', ', $updates) . " WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $success = $stmt->execute($params);

            if ($success) {
                error_log("Admin updated user {$targetUserId} at " . date('Y-m-d H:i:s'));
                sendResponse(true, 'User updated successfully');
            } else {
                sendResponse(false, 'Failed to update user');
            }
        }

        // Delete any user
        if (isset($_POST['admin_delete_user'])) {
            $targetUserId = filter_input(INPUT_POST, 'target_user_id', FILTER_VALIDATE_INT);
            
            if (!$targetUserId) {
                sendResponse(false, 'Invalid user ID');
            }

            if ($targetUserId === $userId) {
                sendResponse(false, 'Cannot delete your own admin account');
            }

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("DELETE FROM cp_login_history WHERE user_id = :id");
                $stmt->execute(['id' => $targetUserId]);

                $stmt = $pdo->prepare("DELETE FROM cp_password_resets WHERE user_id = :id");
                $stmt->execute(['id' => $targetUserId]);

                $stmt = $pdo->prepare("DELETE FROM cp_users WHERE id = :id");
                $stmt->execute(['id' => $targetUserId]);

                $pdo->commit();
                error_log("Admin deleted user {$targetUserId} at " . date('Y-m-d H:i:s'));
                sendResponse(true, 'User deleted successfully');
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("User deletion failed for user {$targetUserId}: " . $e->getMessage());
                sendResponse(false, 'Failed to delete user: ' . $e->getMessage());
            }
        }
    }

    // Regular user endpoints
    if (isset($_POST['update_username'])) {
        $newUsername = filter_input(INPUT_POST, 'new_username', FILTER_SANITIZE_STRING);
        
        if (empty($newUsername)) {
            sendResponse(false, 'Username cannot be empty');
        }

        if (strlen($newUsername) < 3) {
            sendResponse(false, 'Username must be at least 3 characters long');
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cp_users WHERE username = :username AND id != :id");
        $stmt->execute(['username' => $newUsername, 'id' => $userId]);
        if ($stmt->fetchColumn() > 0) {
            sendResponse(false, 'Username is already taken');
        }

        $stmt = $pdo->prepare("UPDATE cp_users SET username = :username WHERE id = :id");
        $success = $stmt->execute([
            'username' => $newUsername,
            'id' => $userId
        ]);

        if ($success) {
            $_SESSION['username'] = $newUsername;
            error_log("User {$userId} changed username to {$newUsername} at " . date('Y-m-d H:i:s'));
            sendResponse(true, 'Username updated successfully', ['new_username' => $newUsername]);
        } else {
            sendResponse(false, 'Failed to update username');
        }
    }

    if (isset($_POST['update_full_name'])) {
        $newFullName = filter_input(INPUT_POST, 'new_full_name', FILTER_SANITIZE_STRING);
        
        if (empty($newFullName)) {
            sendResponse(false, 'Full name cannot be empty');
        }

        if (strlen($newFullName) < 2) {
            sendResponse(false, 'Full name must be at least 2 characters long');
        }

        $stmt = $pdo->prepare("UPDATE cp_users SET full_name = :full_name WHERE id = :id");
        $success = $stmt->execute([
            'full_name' => $newFullName,
            'id' => $userId
        ]);

        if ($success) {
            $_SESSION['full_name'] = $newFullName;
            error_log("User {$userId} changed full name to {$newFullName} at " . date('Y-m-d H:i:s'));
            sendResponse(true, 'Full name updated successfully', ['new_full_name' => $newFullName]);
        } else {
            sendResponse(false, 'Failed to update full name');
        }
    }

    if (isset($_POST['update_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            sendResponse(false, 'All password fields are required');
        }

        if ($newPassword !== $confirmPassword) {
            sendResponse(false, 'New passwords do not match');
        }

        if (strlen($newPassword) < 8) {
            sendResponse(false, 'New password must be at least 8 characters long');
        }

        $stmt = $pdo->prepare("SELECT password FROM cp_users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($currentPassword, $user['password'])) {
            sendResponse(false, 'Current password is incorrect');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE cp_users SET password = :password WHERE id = :id");
        $success = $stmt->execute([
            'password' => $hashedPassword,
            'id' => $userId
        ]);

        if ($success) {
            error_log("User {$userId} changed password at " . date('Y-m-d H:i:s'));
            sendResponse(true, 'Password updated successfully');
        } else {
            sendResponse(false, 'Failed to update password');
        }
    }

    if (isset($_POST['delete_account'])) {
        $password = $_POST['password'] ?? '';

        if (empty($password)) {
            sendResponse(false, 'Password is required to delete account');
        }

        $stmt = $pdo->prepare("SELECT password FROM cp_users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!password_verify($password, $user['password'])) {
            sendResponse(false, 'Incorrect password');
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM cp_login_history WHERE user_id = :id");
            $stmt->execute(['id' => $userId]);

            $stmt = $pdo->prepare("DELETE FROM cp_password_resets WHERE user_id = :id");
            $stmt->execute(['id' => $userId]);

            $stmt = $pdo->prepare("DELETE FROM cp_users WHERE id = :id");
            $stmt->execute(['id' => $userId]);

            $pdo->commit();

            error_log("User {$userId} deleted their account at " . date('Y-m-d H:i:s'));
            session_unset();
            session_destroy();

            sendResponse(true, 'Account deleted successfully', ['redirect' => 'valmanu.php']);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Account deletion failed for user {$userId}: " . $e->getMessage());
            sendResponse(false, 'Failed to delete account: ' . $e->getMessage());
        }
    }

    if (isset($_POST['get_settings'])) {
        $userData = getUserData($pdo, $userId);
        if ($userData) {
            sendResponse(true, 'Settings retrieved successfully', $userData);
        } else {
            sendResponse(false, 'Failed to retrieve settings');
        }
    }
} else {
    sendResponse(false, 'Invalid request method');
}
?>

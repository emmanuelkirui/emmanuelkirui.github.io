<?php
// index.php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Modals</title>
    <style>
        /* Modal Styling */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            width: 400px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
        }

        .close {
            float: right;
            font-size: 24px;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
        }

        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-google {
            background-color: #4285f4;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Trigger Buttons -->
    <button onclick="openModal('loginModal')">Login</button>
    <button onclick="openModal('signupModal')">Signup</button>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('loginModal')">&times;</span>
            <h2>Login</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="login_email" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="login_password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Login</button>
                    <button type="button" class="btn" onclick="openModal('resetModal'); closeModal('loginModal')">Forgot Password?</button>
                </div>
                <div class="form-group">
                    <button type="button" class="btn btn-google">Sign in with Google</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Signup Modal -->
    <div id="signupModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('signupModal')">&times;</span>
            <h2>Signup</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="signup_name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="signup_email" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="signup_password" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Signup</button>
                    <button type="button" class="btn btn-google">Signup with Google</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('resetModal')">&times;</span>
            <h2>Reset Password</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="reset_email" required>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }
    </script>

    <?php
    // Process form submissions (to be connected to database later)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['login_email'])) {
            // Login processing
            $email = $_POST['login_email'];
            $password = $_POST['login_password'];
            // Add database logic here
            echo "<script>alert('Login processing placeholder');</script>";
        }
        
        if (isset($_POST['signup_email'])) {
            // Signup processing
            $name = $_POST['signup_name'];
            $email = $_POST['signup_email'];
            $password = $_POST['signup_password'];
            // Add database logic here
            echo "<script>alert('Signup processing placeholder');</script>";
        }
        
        if (isset($_POST['reset_email'])) {
            // Reset password processing
            $email = $_POST['reset_email'];
            // Add database logic here
            echo "<script>alert('Reset password processing placeholder');</script>";
        }
    }
    ?>
</body>
</html>

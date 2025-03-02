<?php
// auth.php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, sans-serif;
            background-color: #f0f2f5;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            padding: 10px;
        }

        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            display: flex;
            flex-direction: column;
        }

        .close {
            align-self: flex-end;
            font-size: 24px;
            cursor: pointer;
            margin-bottom: 10px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }

        .form-group label {
            margin-bottom: 5px;
            font-size: 14px;
        }

        .form-group input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            flex: 1;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-google {
            background-color: #4285f4;
            color: white;
        }

        .trigger-buttons {
            display: flex;
            gap: 10px;
            position: fixed;
            top: 20px;
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .modal-content {
                max-width: 90%;
                padding: 15px;
            }

            .btn {
                padding: 8px 15px;
                font-size: 12px;
            }

            .form-group input {
                font-size: 14px;
            }

            .trigger-buttons {
                flex-direction: column;
                align-items: center;
                width: 100%;
                padding: 0 10px;
            }
        }

        @media (max-width: 320px) {
            .modal-content {
                padding: 10px;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Trigger Buttons -->
    <div class="trigger-buttons">
        <button class="btn btn-primary" onclick="openModal('loginModal')">Login</button>
        <button class="btn btn-primary" onclick="openModal('signupModal')">Signup</button>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('loginModal')">×</span>
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
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Login</button>
                    <button type="button" class="btn" onclick="openModal('resetModal'); closeModal('loginModal')">Forgot Password?</button>
                    <button type="button" class="btn btn-google">Sign in with Google</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Signup Modal -->
    <div id="signupModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('signupModal')">×</span>
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
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Signup</button>
                    <button type="button" class="btn btn-google">Signup with Google</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('resetModal')">×</span>
            <h2>Reset Password</h2>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="reset_email" required>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

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
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['login_email'])) {
            $email = $_POST['login_email'];
            $password = $_POST['login_password'];
            echo "<script>alert('Login processing placeholder');</script>";
        }
        
        if (isset($_POST['signup_email'])) {
            $name = $_POST['signup_name'];
            $email = $_POST['signup_email'];
            $password = $_POST['signup_password'];
            echo "<script>alert('Signup processing placeholder');</script>";
        }
        
        if (isset($_POST['reset_email'])) {
            $email = $_POST['reset_email'];
            echo "<script>alert('Reset password processing placeholder');</script>";
        }
    }
    ?>
</body>
</html>

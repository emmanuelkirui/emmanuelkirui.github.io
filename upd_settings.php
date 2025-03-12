<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            line-height: 1.6;
        }

        h2 {
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        h3 {
            color: #555;
            margin-top: 30px;
        }

        .section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .info-item {
            margin: 10px 0;
        }

        .muted {
            color: #666;
            font-style: italic;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            max-width: 300px;
            padding: 8px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        input[disabled] {
            background: #f5f5f5;
            color: #777;
        }

        button {
            background: #007bff;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.3s;
        }

        button:hover {
            background: #0056b3;
        }

        .danger-button {
            background: #dc3545;
        }

        .danger-button:hover {
            background: #b02a37;
        }

        @media (max-width: 600px) {
            .section {
                padding: 15px;
            }
            input[type="text"],
            input[type="password"] {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <h2>Account Settings</h2>

    <div class="section" id="userInfo">
        <div class="info-item">Username: <span id="username"></span></div>
        <div class="info-item">Email: <span id="email" class="muted"></span> <span class="muted">(cannot be changed)</span></div>
        <div class="info-item">Full Name: <span id="full_name"></span></div>
    </div>

    <div class="section">
        <h3>Update Username</h3>
        <input type="text" id="new_username" placeholder="New Username" required>
        <button onclick="updateUsername()">Update Username</button>
    </div>

    <div class="section">
        <h3>Update Full Name</h3>
        <input type="text" id="new_full_name" placeholder="New Full Name" required>
        <button onclick="updateFullName()">Update Full Name</button>
    </div>

    <div class="section">
        <h3>Change Password</h3>
        <input type="password" id="current_password" placeholder="Current Password" required>
        <input type="password" id="new_password" placeholder="New Password" required>
        <input type="password" id="confirm_password" placeholder="Confirm New Password" required>
        <button onclick="updatePassword()">Update Password</button>
    </div>

    <div class="section">
        <h3>Delete Account</h3>
        <input type="password" id="delete_password" placeholder="Enter Password" required>
        <button class="danger-button" onclick="deleteAccount()">Delete Account</button>
    </div>

    <script>
        // JavaScript remains the same as in your original code
        window.onload = function() {
            fetch('acc_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'get_settings=true'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('username').textContent = data.username;
                    document.getElementById('email').textContent = data.email;
                    document.getElementById('full_name').textContent = data.full_name;
                }
            });
        };

        function updateUsername() {
            const newUsername = document.getElementById('new_username').value;
            fetch('acc_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'update_username=true&new_username=' + encodeURIComponent(newUsername)
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    document.getElementById('username').textContent = data.new_username;
                }
            });
        }

        function updateFullName() {
            const newFullName = document.getElementById('new_full_name').value;
            fetch('acc_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'update_full_name=true&new_full_name=' + encodeURIComponent(newFullName)
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    document.getElementById('full_name').textContent = data.new_full_name;
                }
            });
        }

        function updatePassword() {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            fetch('acc_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'update_password=true¤t_password=' + encodeURIComponent(currentPassword) +
                      '&new_password=' + encodeURIComponent(newPassword) +
                      '&confirm_password=' + encodeURIComponent(confirmPassword)
            })
            .then(response => response.json())
            .then(data => alert(data.message));
        }

        function deleteAccount() {
            const password = document.getElementById('delete_password').value;
            if (confirm('Are you sure you want to delete your account? This cannot be undone.')) {
                fetch('acc_settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'delete_account=true&password=' + encodeURIComponent(password)
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success && data.redirect) {
                        window.location.href = data.redirect;
                    }
                });
            }
        }
    </script>
</body>
</html>

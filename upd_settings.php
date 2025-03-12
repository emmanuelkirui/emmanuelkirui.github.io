<!DOCTYPE html>
<html>
<head>
    <title>Account Settings</title>
    <style>
        .muted { color: #777; }
        input[disabled] { background: #f0f0f0; }
    </style>
</head>
<body>
    <h2>Account Settings</h2>

    <div id="userInfo">
        <p>Username: <span id="username"></span></p>
        <p>Email: <span id="email" class="muted"></span> (cannot be changed)</p>
        <p>Full Name: <span id="full_name"></span></p>
    </div>

    <div>
        <h3>Update Username</h3>
        <input type="text" id="new_username" placeholder="New Username">
        <button onclick="updateUsername()">Update</button>
    </div>

    <div>
        <h3>Update Full Name</h3>
        <input type="text" id="new_full_name" placeholder="New Full Name">
        <button onclick="updateFullName()">Update</button>
    </div>

    <div>
        <h3>Change Password</h3>
        <input type="password" id="current_password" placeholder="Current Password"><br>
        <input type="password" id="new_password" placeholder="New Password"><br>
        <input type="password" id="confirm_password" placeholder="Confirm New Password"><br>
        <button onclick="updatePassword()">Update Password</button>
    </div>

    <div>
        <h3>Delete Account</h3>
        <input type="password" id="delete_password" placeholder="Enter Password">
        <button onclick="deleteAccount()">Delete Account</button>
    </div>

    <script>
        // Load current settings on page load
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
                body: 'update_password=true&current_password=' + encodeURIComponent(currentPassword) +
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

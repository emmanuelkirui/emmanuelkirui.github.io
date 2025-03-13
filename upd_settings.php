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
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        h2 {
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        h3 {
            color: #555;
            margin-top: 30px;
            margin-bottom: 15px;
        }

        .section {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }

        .muted {
            color: #666;
            font-style: italic;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 100%;
            max-width: 300px;
        }

        input[type="text"],
        input[type="password"],
        select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            flex: 1;
        }

        input[disabled], button[disabled] {
            background: #f5f5f5;
            color: #777;
            cursor: not-allowed;
        }

        button {
            background: #007bff;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            align-self: flex-start;
        }

        button:hover:not([disabled]) {
            background: #0056b3;
        }

        .danger-button {
            background: #dc3545;
        }

        .danger-button:hover:not([disabled]) {
            background: #b02a37;
        }

        .input-button-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            margin-top: 15px;
            -webkit-overflow-scrolling: touch;
        }

        .user-table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
            background: #fff;
        }

        .user-table th, 
        .user-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            white-space: nowrap;
        }

        .user-table th {
            background: #f5f5f5;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .user-table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        .user-table tbody tr:hover {
            background: #f0f0f0;
        }

        .table-container::-webkit-scrollbar {
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .pagination {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 15px;
        }

        .search-container {
            margin-bottom: 15px;
        }

        @media (min-width: 600px) {
            .input-button-group {
                flex-direction: row;
                align-items: flex-end;
            }

            .form-group {
                flex: 1;
            }

            button {
                margin-left: 10px;
            }
        }

        @media (max-width: 600px) {
            .section {
                padding: 15px;
            }

            .form-group {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <button onclick="history.back()" style="margin-bottom: 20px;">Back to Previous Page</button>
    <h2>Account Settings</h2>

    <div class="section" id="userInfo">
        <div class="info-item">Username: <span id="username"></span></div>
        <div class="info-item">Email: <span id="email" class="muted"></span> <span class="muted">(cannot be changed)</span></div>
        <div class="info-item">Full Name: <span id="full_name"></span></div>
        <div class="info-item">User Type: <span id="user_type"></span></div>
    </div>

    <div class="section">
        <h3>Update Username</h3>
        <div class="input-button-group">
            <div class="form-group">
                <input type="text" id="new_username" placeholder="New Username" required>
            </div>
            <button onclick="updateUsername()">Update Username</button>
        </div>
    </div>

    <div class="section">
        <h3>Update Full Name</h3>
        <div class="input-button-group">
            <div class="form-group">
                <input type="text" id="new_full_name" placeholder="New Full Name" required>
            </div>
            <button onclick="updateFullName()">Update Full Name</button>
        </div>
    </div>

    <div class="section">
        <h3>Change Password</h3>
        <div class="form-group">
            <input type="password" id="current_password" placeholder="Current Password" required>
            <input type="password" id="new_password" placeholder="New Password" required>
            <input type="password" id="confirm_password" placeholder="Confirm New Password" required>
            <button onclick="updatePassword()">Update Password</button>
        </div>
    </div>

    <div class="section">
        <h3>Delete Account</h3>
        <div class="input-button-group">
            <div class="form-group">
                <input type="password" id="delete_password" placeholder="Enter Password" required>
            </div>
            <button id="deleteAccountBtn" class="danger-button" onclick="deleteAccount()">Delete Account</button>
        </div>
    </div>

    <div class="section" id="adminSection" style="display: none;">
        <h3>Admin: Manage Users</h3>
        <div class="search-container">
            <input type="text" id="userSearch" placeholder="Search users..." oninput="searchUsers()">
        </div>
        <div class="table-container">
            <table class="user-table" id="userTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Full Name</th>
                        <th>User Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="pagination" id="pagination"></div>
        <h4>Update User</h4>
        <div class="form-group">
            <input type="text" id="admin_user_id" placeholder="User ID" readonly disabled required>
            <input type="text" id="admin_username" placeholder="New Username">
            <input type="email" id="admin_email" placeholder="New Email">
            <input type="text" id="admin_full_name" placeholder="New Full Name">
            <select id="admin_user_type">
                <option value="user">User</option>
                <option value="admin">Admin</option>
            </select>
            <button onclick="adminUpdateUser()">Update User</button>
        </div>
    </div>

    <script>
        let isAdmin = false;
        let currentUserId = null;
        let allUsers = [];
        let currentPage = 1;
        const itemsPerPage = 10;

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
                    document.getElementById('user_type').textContent = data.user_type;
                    currentUserId = data.id;

                    isAdmin = data.user_type === 'admin';
                    if (isAdmin) {
                        document.getElementById('adminSection').style.display = 'block';
                        document.getElementById('deleteAccountBtn').disabled = true;
                        loadAllUsers();
                    }
                }
            });
        };

        function loadAllUsers() {
            fetch('acc_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'get_all_users=true'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allUsers = data.users;
                    renderTable(1);
                }
            });
        }

        function renderTable(page) {
            currentPage = page;
            const tbody = document.querySelector('#userTable tbody');
            tbody.innerHTML = '';

            const searchTerm = document.getElementById('userSearch').value.toLowerCase();
            const filteredUsers = allUsers.filter(user => 
                user.username.toLowerCase().includes(searchTerm) ||
                user.email.toLowerCase().includes(searchTerm) ||
                user.full_name.toLowerCase().includes(searchTerm) ||
                user.id.toString().includes(searchTerm) ||
                user.user_type.toLowerCase().includes(searchTerm)
            );

            const start = (page - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const paginatedUsers = filteredUsers.slice(start, end);

            paginatedUsers.forEach(user => {
                const tr = document.createElement('tr');
                const isCurrentUser = parseInt(user.id) === parseInt(currentUserId);
                tr.innerHTML = `
                    <td>${user.id}</td>
                    <td>${user.username}</td>
                    <td>${user.email}</td>
                    <td>${user.full_name}</td>
                    <td>${user.user_type}</td>
                    <td>
                        <button onclick="fillUpdateForm(${user.id}, '${user.username}', '${user.email}', '${user.full_name}', '${user.user_type}')">Edit</button>
                        <button class="danger-button" onclick="adminDeleteUser(${user.id})" ${isCurrentUser ? 'disabled' : ''}>Delete</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });

            updatePagination(filteredUsers.length);
        }

        function updatePagination(totalItems) {
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            const pagination = document.getElementById('pagination');
            pagination.innerHTML = '';

            if (totalPages <= 1) return;

            // Previous button
            const prevBtn = document.createElement('button');
            prevBtn.textContent = 'Previous';
            prevBtn.disabled = currentPage === 1;
            prevBtn.onclick = () => renderTable(currentPage - 1);
            pagination.appendChild(prevBtn);

            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.textContent = i;
                pageBtn.disabled = i === currentPage;
                pageBtn.onclick = () => renderTable(i);
                pagination.appendChild(pageBtn);
            }

            // Next button
            const nextBtn = document.createElement('button');
            nextBtn.textContent = 'Next';
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.onclick = () => renderTable(currentPage + 1);
            pagination.appendChild(nextBtn);
        }

        function searchUsers() {
            renderTable(1);
        }

        function fillUpdateForm(id, username, email, fullName, userType) {
            document.getElementById('admin_user_id').value = id;
            document.getElementById('admin_username').value = username;
            document.getElementById('admin_email').value = email;
            document.getElementById('admin_full_name').value = fullName;
            document.getElementById('admin_user_type').value = userType;
        }

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
            
            if (newPassword !== confirmPassword) {
                alert('New password and confirmation do not match');
                return;
            }
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
            if (isAdmin) {
                alert('Admin accounts cannot delete themselves');
                return;
            }
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

        function adminUpdateUser() {
            const userId = document.getElementById('admin_user_id').value;
            const username = document.getElementById('admin_username').value;
            const email = document.getElementById('admin_email').value;
            const fullName = document.getElementById('admin_full_name').value;
            const userType = document.getElementById('admin_user_type').value;

            let body = 'admin_update_user=true&target_user_id=' + encodeURIComponent(userId);
            if (username) body += '&username=' + encodeURIComponent(username);
            if (email) body += '&email=' + encodeURIComponent(email);
            if (fullName) body += '&full_name=' + encodeURIComponent(fullName);
            if (userType) body += '&user_type=' + encodeURIComponent(userType);

            fetch('acc_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    loadAllUsers();
                }
            });
        }

        function adminDeleteUser(userId) {
            if (parseInt(userId) === parseInt(currentUserId)) {
                alert('You cannot delete your own admin account');
                return;
            }
            if (confirm('Are you sure you want to delete this user?')) {
                fetch('acc_settings.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'admin_delete_user=true&target_user_id=' + encodeURIComponent(userId)
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        loadAllUsers();
                        document.getElementById('admin_user_id').value = '';
                        document.getElementById('admin_username').value = '';
                        document.getElementById('admin_email').value = '';
                        document.getElementById('admin_full_name').value = '';
                    }
                });
            }
        }
    </script>
</body>
</html>

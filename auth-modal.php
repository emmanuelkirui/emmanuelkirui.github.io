<div id="auth-modal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <div class="authforums.com/auth-form-container">
            <div class="auth-tabs">
                <button class="tab-btn active" data-tab="login">Login</button>
                <button class="tab-btn" data-tab="signup">Sign Up</button>
            </div>
            
            <div class="auth-form" id="login-form">
                <h2>Login</h2>
                <form id="loginForm">
                    <div class="form-group">
                        <label for="login-username">Username</label>
                        <input type="text" id="login-username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <input type="password" id="login-password" name="password" required>
                    </div>
                    <button type="submit">Login</button>
                </form>
                <div id="login-message" class="auth-message"></div>
            </div>
            
            <div class="auth-form" id="signup-form" style="display: none;">
                <h2>Sign Up</h2>
                <form id="signupForm">
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
                </form>
                <div id="signup-message" class="auth-message"></div>
            </div>
        </div>
    </div>
</div>

<style>
.modal {
    display: none;
    position: fixed;
    z-index: 1001;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: var(--card-bg);
    margin: 15% auto;
    padding: 20px;
    border-radius: 10px;
    width: 90%;
    max-width: 400px;
    position: relative;
    box-shadow: var(--shadow);
}

.close-modal {
    position: absolute;
    right: 15px;
    top: 10px;
    font-size: 24px;
    cursor: pointer;
    color: var(--text-color);
}

.auth-form-container {
    padding: 20px;
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

.auth-form h2 {
    margin: 0 0 20px;
    color: var(--primary-color);
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
}

.auth-form button {
    width: 100%;
    padding: 10px;
    background-color: var(--primary-color);
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.auth-form button:hover {
    background-color: var(--secondary-color);
}

.auth-message {
    margin-top: 10px;
    text-align: center;
    color: var(--text-color);
}
</style>

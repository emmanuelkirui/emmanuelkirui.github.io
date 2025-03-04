<?php
// recaptcha_handler.php

// Configuration
define('RECAPTCHA_SITE_KEY', '6Les-YkqAAAAAKbEePt6uo07ZvJAw5-_4ProGXtN');     // Replace with your Site Key
define('RECAPTCHA_SECRET_KEY', '6Les-YkqAAAAAEYqVJL4skWPrbLatjcgZ6-sWapW'); // Replace with your Secret Key (store securely in production)
define('VERIFICATION_DURATION', 1800); // 30 minutes in seconds, adjust as needed

class RecaptchaHandler {
    private $siteKey;
    private $secretKey;
    private $result = ['success' => false, 'message' => ''];
    private static $instance = null;
    private $processed = false;
    
    private function __construct() {
        $this->siteKey = RECAPTCHA_SITE_KEY;
        $this->secretKey = RECAPTCHA_SECRET_KEY;
        
        // Ensure session starts successfully
        if (session_status() === PHP_SESSION_NONE) {
            if (!session_start()) {
                error_log('Failed to start session in RecaptchaHandler');
                $this->result['message'] = 'Internal server error. Please try again later.';
                $this->processed = true;
                $this->outputResult();
                exit;
            }
        }
        
        // Store redirect URL if not already set
        if (!isset($_SESSION['redirect_url'])) {
            $_SESSION['redirect_url'] = $_SERVER['HTTP_REFERER'] ?? '/';
        }
        
        $this->handleRequest();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function handleRequest() {
        $token = $_POST['g-recaptcha-response'] ?? ''; // Restrict to POST only
        if ($token) {
            $token = filter_var($token, FILTER_SANITIZE_STRING); // Basic sanitization
            $this->result = $this->verify($token);
            $this->processed = true;
            $this->outputResult();
        }
        if (!$this->isVerified()) {
            $this->displayOverlay();
        }
    }
    
    private function verify($recaptcha_response) {
        $result = ['success' => false, 'message' => ''];
        
        if (!$this->checkRateLimit()) {
            $result['message'] = 'Rate limit exceeded (5 attempts/hour). Please try again later.';
            return $result;
        }
        
        if (empty($recaptcha_response)) {
            $result['message'] = 'Please complete the CAPTCHA verification';
            return $result;
        }
        
        try {
            $url = 'https://www.google.com/recaptcha/api/siteverify';
            $data = [
                'secret' => $this->secretKey,
                'response' => $recaptcha_response,
                'remoteip' => $this->getClientIp()
            ];
            
            // Use cURL instead of file_get_contents for reliability
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            if ($response === false) {
                throw new Exception('cURL error: ' . curl_error($ch));
            }
            curl_close($ch);
            
            $responseData = json_decode($response);
            if ($responseData && $responseData->success) {
                $result['success'] = true;
                $result['message'] = 'Verification completed successfully';
                $result['redirect_url'] = $_SESSION['redirect_url']; // Include redirect URL
                $_SESSION['recaptcha_verified'] = true;
                $_SESSION['recaptcha_verified_time'] = time();
            } else {
                $result['message'] = 'CAPTCHA verification failed. Please try again';
            }
        } catch (Exception $e) {
            error_log('reCAPTCHA error: ' . $e->getMessage());
            $result['message'] = 'Temporary verification error. Please try again';
        }
        return $result;
    }
    
    private function outputResult() {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($this->result);
            exit;
        }
    }
    
    private function getClientIp() {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ipList[0]);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    public function displayOverlay() {
        if ($this->processed && $this->result['success']) {
            return;
        }
        
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['recaptcha_nonce'] = $nonce;
        $clientIp = htmlspecialchars($this->getClientIp(), ENT_QUOTES, 'UTF-8');
        
        // Add CSP header with nonce
        header("Content-Security-Policy: script-src 'nonce-$nonce' https://www.google.com; object-src 'none';");
        
        echo '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>creativepulse.42web.io - Security Verification</title>
            <style>
                body {
                    margin: 0;
                    font-family: "Poppins", sans-serif;
                    background: linear-gradient(135deg, #1e3c72, #2a5298);
                    overflow: hidden;
                }
                #recaptcha-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.3);
                    backdrop-filter: blur(5px);
                    -webkit-backdrop-filter: blur(5px);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    z-index: 10000;
                    color: #fff;
                }
                h2 {
                    color: #fff;
                    font-size: 28px;
                    margin-bottom: 10px;
                    font-weight: 700;
                    letter-spacing: 1px;
                }
                .verify-text {
                    color: #ddd;
                    font-size: 14px;
                    margin-bottom: 20px;
                    font-style: italic;
                }
                .info-section {
                    color: #eee;
                    font-size: 13px;
                    margin-top: 25px;
                    line-height: 1.8;
                    background: rgba(245, 247, 250, 0.1);
                    padding: 15px;
                    border-radius: 10px;
                    border-left: 4px solid #2a5298;
                    max-width: 450px;
                }
                .info-section p {
                    margin: 5px 0;
                }
                .highlight {
                    color: #e67e22;
                    font-weight: 600;
                }
                #recaptcha-message {
                    margin-top: 20px;
                    font-size: 14px;
                    color: #e74c3c;
                }
                .g-recaptcha {
                    margin: 20px auto;
                    transform: scale(1.05);
                    transform-origin: center;
                }
                #reload-message {
                    display: none;
                    margin-top: 20px;
                    font-size: 14px;
                    color: #f1c40f;
                }
                #reload-message a {
                    color: #3498db;
                    text-decoration: none;
                    font-weight: 600;
                }
                #reload-message a:hover {
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div id="recaptcha-overlay">
                <h2>creativepulse.42web.io</h2>
                <p class="verify-text">Verifying you are human. This may take a few seconds.</p>
                <div id="recaptcha-container">
                    <input type="hidden" name="nonce" value="' . $nonce . '">
                    <div class="g-recaptcha" data-sitekey="' . $this->siteKey . '" data-callback="onRecaptchaSuccess"></div>
                </div>
                <div id="recaptcha-message"></div>
                <div id="reload-message">
                    Taking too long? <a href="' . htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') . '">Reload the page</a>
                </div>
                <div class="info-section">
                    <p><span class="highlight">creativepulse.42web.io</span> needs to review the security of your connection before proceeding</p>
                    <p>Powered By: <span class="highlight">Google reCAPTCHA</span></p>
                    <p>Your IP: <span class="highlight">' . $clientIp . '</span></p>
                </div>
            </div>
            <script nonce="' . $nonce . '" src="https://www.google.com/recaptcha/api.js" async defer></script>
            <script nonce="' . $nonce . '">
                function onRecaptchaSuccess(token) {
                    fetch(window.location.href, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                            "X-Requested-With": "XMLHttpRequest"
                        },
                        body: "g-recaptcha-response=" + encodeURIComponent(token)
                    })
                    .then(response => response.json())
                    .then(data => {
                        let container = document.getElementById("recaptcha-message");
                        if (data.success) {
                            container.innerHTML = "<div style=\'color: #27ae60; font-size: 14px;\'>Success! Redirecting...</div>";
                            setTimeout(() => window.location.href = data.redirect_url || "/", 1000);
                        } else {
                            container.innerHTML = "<div style=\'color: #e74c3c; font-size: 14px;\'>" + data.message + "</div>";
                            grecaptcha.reset();
                        }
                    })
                    .catch(error => {
                        console.error("Verification Error:", error);
                        document.getElementById("recaptcha-message").innerHTML = "<div style=\'color: #e74c3c;\'>Error occurred. Please try again.</div>";
                        grecaptcha.reset();
                    });
                }

                // Show reload message if verification takes too long
                setTimeout(() => {
                    const reloadMessage = document.getElementById("reload-message");
                    const recaptchaMessage = document.getElementById("recaptcha-message");
                    if (!recaptchaMessage.innerHTML) { // Only show if no success/error message yet
                        reloadMessage.style.display = "block";
                    }
                }, 10000); // 10 seconds delay
            </script>
        </body>
        </html>';
        exit;
    }
    
    public function isVerified() {
        if (!isset($_SESSION['recaptcha_verified']) || $_SESSION['recaptcha_verified'] !== true) {
            return false;
        }
        
        // Check if verification has expired
        if (isset($_SESSION['recaptcha_verified_time'])) {
            $elapsed = time() - $_SESSION['recaptcha_verified_time'];
            if ($elapsed > VERIFICATION_DURATION) {
                unset($_SESSION['recaptcha_verified']);
                unset($_SESSION['recaptcha_verified_time']);
                return false;
            }
        }
        return true;
    }
    
    private function checkRateLimit() {
        // Use IP-based rate limiting combined with session
        $attempt_key = 'recaptcha_attempts_' . date('YmdH') . '_' . md5($this->getClientIp());
        $_SESSION[$attempt_key] = isset($_SESSION[$attempt_key]) ? $_SESSION[$attempt_key] + 1 : 1;
        return $_SESSION[$attempt_key] <= 5;
    }
}

$recaptcha = RecaptchaHandler::getInstance();
if (!$recaptcha->isVerified()) {
    $recaptcha->displayOverlay();
}
?>

<?php
// recaptcha_handler.php
(ini_set('display_errors', 1);

// Configuration
define('RECAPTCHA_SITE_KEY', '6Les-YkqAAAAAKbEePt6uo07ZvJAw5-_4ProGXtN');     // Replace with your Site Key
define('RECAPTCHA_SECRET_KEY', '6Les-YkqAAAAAEYqVJL4skWPrbLatjcgZ6-sWapW'); // Replace with your Secret Key (store securely)
define('VERIFICATION_DURATION', 1800); // 30 minutes in seconds

class RecaptchaHandler {
    private $siteKey;
    private $secretKey;
    private $result = ['success' => false, 'message' => ''];
    private static $instance = null;
    private $processed = false;
    
    private function __construct() {
        $this->siteKey = RECAPTCHA_SITE_KEY;
        $this->secretKey = RECAPTCHA_SECRET_KEY;
        
        // Start session with error handling
        if (session_status() === PHP_SESSION_NONE) {
            if (!session_start()) {
                error_log('Failed to start session in RecaptchaHandler');
                $this->result['message'] = 'Internal server error. Please try again later.';
                $this->processed = true;
                $this->outputResult();
                exit;
            }
        }
        
        // Store redirect URL
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
        $token = $_POST['g-recaptcha-response'] ?? '';
        if ($token) {
            $token = filter_var($token, FILTER_SANITIZE_STRING);
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
            
            // Check if cURL is available; fall back to file_get_contents if not
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($ch);
                if ($response === false) {
                    throw new Exception('cURL error: ' . curl_error($ch));
                }
                curl_close($ch);
            } else {
                $options = [
                    'http' => [
                        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method' => 'POST',
                        'content' => http_build_query($data)
                    ]
                ];
                $context = stream_context_create($options);
                $response = file_get_contents($url, false, $context);
                if ($response === false) {
                    throw new Exception('Failed to contact verification server');
                }
            }
            
            $responseData = json_decode($response);
            if ($responseData && $responseData->success) {
                $result['success'] = true;
                $result['message'] = 'Verification completed successfully';
                $result['redirect_url'] = $_SESSION['redirect_url'];
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
        
        // Attempt CSP header (may not work on InfinityFree)
        header("Content-Security-Policy: script-src 'nonce-$nonce' https://www.google.com; object-src 'none';");
        
        echo '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Security Verification</title>
            <style>
                body {
                    margin: 0;
                    font-family: "Segoe UI", Arial, sans-serif;
                    background: #f4f7fa;
                }
                #recaptcha-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.75);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                }
                .recaptcha-box {
                    background: #ffffff;
                    padding: 40px;
                    border-radius: 12px;
                    max-width: 420px;
                    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
                    text-align: center;
                    animation: fadeIn 0.3s ease-in-out;
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(-20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                h2 {
                    color: #2c3e50;
                    font-size: 24px;
                    margin-bottom: 25px;
                    font-weight: 600;
                }
                .info-section {
                    color: #7f8c8d;
                    font-size: 13px;
                    margin-top: 20px;
                    line-height: 1.6;
                    background: #f9fafb;
                    padding: 15px;
                    border-radius: 8px;
                }
                .info-section p {
                    margin: 5px 0;
                }
                .highlight {
                    color: #2980b9;
                    font-weight: 500;
                }
                #recaptcha-message {
                    margin-top: 20px;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div id="recaptcha-overlay">
                <div class="recaptcha-box">
                    <h2>Security Verification</h2>
                    <div id="recaptcha-container">
                        <input type="hidden" name="nonce" value="' . $nonce . '">
                        <div class="g-recaptcha" data-sitekey="' . $this->siteKey . '" data-callback="onRecaptchaSuccess"></div>
                    </div>
                    <div id="recaptcha-message"></div>
                    <div class="info-section">
                        <p>Security Performed By: <span class="highlight">Google reCAPTCHA v2</span></p>
                        <p>Site: <span class="highlight">creativepulse.42web.io</span></p>
                        <p>Maintained By: <span class="highlight">xAI Security Team</span></p>
                        <p>Your IP Address: <span class="highlight">' . $clientIp . '</span></p>
                    </div>
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
                            window.location.href = data.redirect_url || "/";
                        } else {
                            container.innerHTML = "<div style=\'color: #e74c3c; font-size: 14px;\'>" + data.message + "</div>";
                            grecaptcha.reset();
                        }
                    })
                    .catch(error => {
                        console.error("Verification Error:", error);
                        grecaptcha.reset();
                    });
                }
            </script>
        </body>
        </html>';
        exit;
    }
    
    public function isVerified() {
        if (!isset($_SESSION['recaptcha_verified']) || $_SESSION['recaptcha_verified'] !== true) {
            return false;
        }
        
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

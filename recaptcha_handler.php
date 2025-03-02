<?php
// recaptcha_handler.php

// Configuration
define('RECAPTCHA_SITE_KEY', '6Les-YkqAAAAAKbEePt6uo07ZvJAw5-_4ProGXtN');     // Replace with your Site Key
define('RECAPTCHA_SECRET_KEY', '6Les-YkqAAAAAEYqVJL4skWPrbLatjcgZ6-sWapW'); // Replace with your Secret Key
class RecaptchaHandler {
    private $siteKey;
    private $secretKey;
    private $result = ['success' => false, 'message' => ''];
    private static $instance = null;
    private $processed = false;
    
    private function __construct() {
        $this->siteKey = RECAPTCHA_SITE_KEY;
        $this->secretKey = RECAPTCHA_SECRET_KEY;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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
        $token = $_POST['g-recaptcha-response'] ?? $_GET['token'] ?? '';
        if ($token) {
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
            $result['message'] = 'Too many attempts. Please try again later.';
            return $result;
        }
        if (empty($recaptcha_response)) {
            $result['message'] = 'No CAPTCHA response provided';
            return $result;
        }
        try {
            $url = 'https://www.google.com/recaptcha/api/siteverify';
            $data = [
                'secret' => $this->secretKey,
                'response' => $recaptcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            ];
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
                throw new Exception('Failed to contact reCAPTCHA server');
            }
            $responseData = json_decode($response);
            if ($responseData->success) {
                $result['success'] = true;
                $result['message'] = 'Verification successful';
                $_SESSION['recaptcha_verified'] = true; // Set verification flag
            } else {
                $result['message'] = 'CAPTCHA verification failed';
            }
        } catch (Exception $e) {
            error_log('reCAPTCHA error: ' . $e->getMessage());
            $result['message'] = 'Verification error occurred';
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
    
    public function displayOverlay() {
        if ($this->processed && $this->result['success']) {
            return; // Don't display if verified
        }
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['recaptcha_nonce'] = $nonce;
        
        echo '
        <div id="recaptcha-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center;">
            <div style="background: white; padding: 20px; border-radius: 5px; text-align: center;">
                <h2>Please Verify</h2>
                <div id="recaptcha-container">
                    <input type="hidden" name="nonce" value="' . $nonce . '">
                    <div class="g-recaptcha" data-sitekey="' . $this->siteKey . '" data-callback="onRecaptchaSuccess"></div>
                </div>
                <div id="recaptcha-message"></div>
            </div>
        </div>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        <script>
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
                        document.getElementById("recaptcha-overlay").remove();
                    } else {
                        container.innerHTML = "<div style=\'color: red;\'>Error: " + data.message + "</div>";
                        grecaptcha.reset();
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    grecaptcha.reset();
                });
            }
        </script>';
        exit; // Stop further processing
    }
    
    public function isVerified() {
        return isset($_SESSION['recaptcha_verified']) && $_SESSION['recaptcha_verified'] === true;
    }
    
    private function checkRateLimit() {
        $attempt_key = 'recaptcha_attempts_' . date('YmdH');
        $_SESSION[$attempt_key] = isset($_SESSION[$attempt_key]) ? $_SESSION[$attempt_key] + 1 : 1;
        return $_SESSION[$attempt_key] <= 5;
    }
}

// Usage example in your page
$recaptcha = RecaptchaHandler::getInstance();
if (!$recaptcha->isVerified()) {
    // This will display the overlay and stop execution
    $recaptcha->displayOverlay();
}

// Your protected content goes here
echo "Welcome to the protected page!";

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
            if ($this->result['success']) {
                $_SESSION['recaptcha_verified'] = true;
            } else {
                $_SESSION['recaptcha_verified'] = false;
            }
            $this->outputResult();
        }
        register_shutdown_function([$this, 'displayWidget']);
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
        header('Content-Type: application/json');
        echo json_encode($this->result);
        exit;
    }
    
    public function displayWidget() {
        if ($this->processed) {
            return;
        }
        $nonce = bin2hex(random_bytes(16));
        $_SESSION['recaptcha_nonce'] = $nonce;

        echo '
        <div id="recaptcha-container">
            <input type="hidden" name="nonce" value="' . $nonce . '">
            <div class="g-recaptcha" data-sitekey="' . $this->siteKey . '" data-callback="onRecaptchaSuccess"></div>
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            <script>
                function onRecaptchaSuccess(token) {
                    fetch("recaptcha_handler.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                            "X-Requested-With": "XMLHttpRequest"
                        },
                        body: "g-recaptcha-response=" + encodeURIComponent(token)
                    })
                    .then(response => response.json())
                    .then(data => {
                        let container = document.getElementById("recaptcha-container");
                        if (data.success) {
                            container.innerHTML = "<div style=\'color: green;\'>Success: " + data.message + "</div>";
                            enableActions();
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

                function disableActions() {
                    document.getElementById("submit-button").disabled = true;
                    document.getElementById("user-input").disabled = true;
                }

                function enableActions() {
                    document.getElementById("submit-button").disabled = false;
                    document.getElementById("user-input").disabled = false;
                }

                function showRecaptchaPopup() {
                    disableActions();
                    grecaptcha.reset();
                    grecaptcha.execute();
                }

                disableActions();
                setInterval(showRecaptchaPopup, 300000);
            </script>
        </div>';
    }
    
    private function checkRateLimit() {
        $attempt_key = 'recaptcha_attempts_' . date('YmdH');
        $_SESSION[$attempt_key] = isset($_SESSION[$attempt_key]) ? $_SESSION[$attempt_key] + 1 : 1;
        return $_SESSION[$attempt_key] <= 5;
    }
}

// Auto-initialize
$recaptcha = RecaptchaHandler::getInstance();

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header('HTTP/1.0 403 Forbidden');
    exit('Direct access not allowed');
}

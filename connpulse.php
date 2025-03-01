<?php
set_time_limit(15);
ini_set('default_socket_timeout', 5);

function checkConnection() {
    return connection_status() === CONNECTION_NORMAL;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    $startTime = time();
    $maxExecutionTime = 10;
    
    try {
        while (true) {
            if (!checkConnection()) {
                throw new Exception('Connection lost during processing');
            }
            
            if ((time() - $startTime) > $maxExecutionTime) {
                header('HTTP/1.1 504 Gateway Timeout');
                throw new Exception('Server timeout after 10s');
            }
            
            sleep(1);
            break;
        }
        
        echo json_encode(['status' => 'success', 'message' => 'Processed successfully']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Connection Handler</title>
    <style>
        .status-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 15px 25px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            display: none;
            z-index: 1000;
            transition: opacity 0.3s ease;
        }
        .success { 
            background-color: #e6ffe6; 
            color: green; 
            border: 1px solid green;
        }
        .error { 
            background-color: #ffe6e6; 
            color: maroon; 
            border: 1px solid maroon;
        }
        body { text-align: center; }
    </style>
</head>
<body>
    <div id="status-output" class="status-container"></div>

    <script>
    class ConnectionHandler {
        constructor() {
            this.timeout = 8000;
            this.interval = 5000; // Check every 5 seconds
            this.init();
        }

        init() {
            this.monitorConnection();
            this.processRequest(); // Automatically start the first check
            setInterval(() => this.processRequest(), this.interval); // Periodic checks
        }

        async processRequest() {
            const outputElement = document.getElementById('status-output');
            this.showStatus(outputElement, 'Checking connection...', 'neutral');

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), this.timeout);

            try {
                const response = await fetch('?ajax=1', {
                    method: 'POST',
                    signal: controller.signal,
                    headers: { 'Content-Type': 'application/json' }
                });

                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    throw new Error(`Server error: ${response.status}`);
                }

                const data = await response.json();
                this.showStatus(outputElement, `${data.status.toUpperCase()}: ${data.message}`, data.status);
            } catch (error) {
                const errorMessage = error.message === 'Failed to fetch' ? 'Connection lost or server not responding' :
                                    error.message === 'AbortError' ? 'Client timeout after 8s' : error.message;
                this.showStatus(outputElement, `ERROR: ${errorMessage}`, 'error');
            }
        }

        showStatus(element, message, status) {
            element.textContent = message;
            element.className = `status-container ${status}`;
            element.style.display = 'block';
            element.style.opacity = '1';

            // Auto-hide after 3 seconds
            setTimeout(() => {
                element.style.opacity = '0';
                setTimeout(() => element.style.display = 'none', 300);
            }, 3000);
        }

        monitorConnection() {
            const outputElement = document.getElementById('status-output');
            window.addEventListener('offline', () => {
                this.showStatus(outputElement, 'ERROR: Network connection lost', 'error');
            });
            window.addEventListener('online', () => {
                this.showStatus(outputElement, 'SUCCESS: Network connection restored', 'success');
            });
        }
    }

    new ConnectionHandler();
    </script>
</body>
</html>

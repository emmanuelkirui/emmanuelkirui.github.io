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
    
    while (true) {
        if (!checkConnection()) {
            echo json_encode(['status' => 'error', 'message' => 'Connection lost during processing']);
            exit;
        }
        
        if ((time() - $startTime) > $maxExecutionTime) {
            header('HTTP/1.1 504 Gateway Timeout');
            echo json_encode(['status' => 'error', 'message' => 'Server timeout after 10s']);
            exit;
        }
        
        sleep(1);
        break;
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Processed successfully']);
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
        button { 
            margin: 20px; 
            padding: 8px 15px; 
            cursor: pointer;
        }
        body { text-align: center; }
    </style>
</head>
<body>
    <button id="fetchBtn">Test Connection</button>
    <div id="status-output" class="status-container"></div>

    <script>
    class ConnectionHandler {
        constructor() {
            this.timeout = 8000;
        }

        async processRequest() {
            const outputElement = document.getElementById('status-output');
            outputElement.textContent = 'Processing...';
            outputElement.className = 'status-container'; // Reset to neutral
            outputElement.style.display = 'block';
            outputElement.style.opacity = '1';

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
                outputElement.className = `status-container ${data.status === 'success' ? 'success' : 'error'}`;
                outputElement.textContent = `${data.status.toUpperCase()}: ${data.message}`;
                
                // Auto-hide after 3 seconds
                setTimeout(() => {
                    outputElement.style.opacity = '0';
                    setTimeout(() => outputElement.style.display = 'none', 300);
                }, 3000);
            } catch (error) {
                outputElement.className = 'status-container error';
                outputElement.textContent = 'ERROR: ' + (
                    error.message === 'Failed to fetch' ? 'Connection lost or server not responding' :
                    error.message === 'AbortError' ? 'Client timeout after 8s' :
                    error.message
                );
                setTimeout(() => {
                    outputElement.style.opacity = '0';
                    setTimeout(() => outputElement.style.display = 'none', 300);
                }, 3000);
            }
        }

        monitorConnection() {
            const outputElement = document.getElementById('status-output');
            window.addEventListener('offline', () => {
                outputElement.className = 'status-container error';
                outputElement.textContent = 'ERROR: Network connection lost';
                outputElement.style.display = 'block';
                outputElement.style.opacity = '1';
            });
            window.addEventListener('online', () => {
                outputElement.className = 'status-container success';
                outputElement.textContent = 'SUCCESS: Network connection restored';
                outputElement.style.display = 'block';
                outputElement.style.opacity = '1';
                setTimeout(() => {
                    outputElement.style.opacity = '0';
                    setTimeout(() => outputElement.style.display = 'none', 300);
                }, 3000);
            });
        }
    }

    const handler = new ConnectionHandler();
    handler.monitorConnection();
    document.getElementById('fetchBtn').addEventListener('click', () => {
        handler.processRequest();
    });
    </script>
</body>
</html>

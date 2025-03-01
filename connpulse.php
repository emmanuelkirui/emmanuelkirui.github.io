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
            margin: 20px;
            padding: 10px;
            border: 1px solid #ccc;
        }
        .success { color: green; }
        .error { color: maroon; background-color: #ffe6e6; padding: 5px; }
        button { margin: 10px 0; padding: 5px 10px; }
    </style>
</head>
<body>
    <div class="status-container">
        <div id="status-output"></div>
    </div>
    <button id="fetchBtn">Test Connection</button>

    <script>
    class ConnectionHandler {
        constructor() {
            this.timeout = 8000;
        }

        async processRequest() {
            const outputElement = document.getElementById('status-output');
            outputElement.textContent = 'Processing...';
            outputElement.className = '';

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
                outputElement.className = data.status === 'success' ? 'success' : 'error';
                outputElement.textContent = `${data.status.toUpperCase()}: ${data.message}`;
            } catch (error) {
                outputElement.className = 'error';
                outputElement.textContent = 'ERROR: ' + (
                    error.message === 'Failed to fetch' ? 'Connection lost or server not responding' :
                    error.message === 'AbortError' ? 'Client timeout after 8s' :
                    error.message
                );
            }
        }

        monitorConnection() {
            const outputElement = document.getElementById('status-output');
            window.addEventListener('offline', () => {
                outputElement.className = 'error';
                outputElement.textContent = 'ERROR: Network connection lost';
            });
            window.addEventListener('online', () => {
                outputElement.className = '';
                outputElement.textContent = 'Network connection restored';
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

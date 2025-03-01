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
    <title>Connection Monitor</title>
    <style>
        .status-container {
            margin: 20px;
            padding: 10px;
            border: 1px solid #ccc;
        }
        .success { color: green; }
        .error { color: maroon; background-color: #ffe6e6; padding: 5px; }
    </style>
</head>
<body>
    <div class="status-container">
        <div id="status-output">Checking connection...</div>
    </div>

    <script>
    class ConnectionMonitor {
        constructor() {
            this.timeout = 8000;
            this.checkConnection();
            this.monitorNetwork();
        }

        async checkConnection() {
            while (true) {
                const outputElement = document.getElementById('status-output');
                outputElement.textContent = 'Checking connection...';
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

                await new Promise(resolve => setTimeout(resolve, 5000)); // Check every 5 seconds
            }
        }

        monitorNetwork() {
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

    new ConnectionMonitor();
    </script>
</body>
</html>

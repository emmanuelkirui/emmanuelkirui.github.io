<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    set_time_limit(15);
    ini_set('default_socket_timeout', 5);

    function checkConnection() {
        return connection_status() === CONNECTION_NORMAL;
    }

    $startTime = time();
    $maxExecutionTime = 10;

    while (true) {
        if (!checkConnection()) {
            echo json_encode(['status' => 'error', 'message' => 'Connection lost']);
            die();
        }

        if ((time() - $startTime) > $maxExecutionTime) {
            http_response_code(504);
            echo json_encode(['status' => 'error', 'message' => 'Server timeout']);
            die();
        }

        sleep(1);
        break;
    }

    echo json_encode(['status' => 'ok']); // Prevents "Unexpected token '<'"
    die();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Connection Monitor</title>
    <style>
        .error { color: maroon; background-color: #ffe6e6; padding: 5px; }
    </style>
</head>
<body>
    <div id="status-output">Checking connection...</div>

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
                    if (data.status === 'error') {
                        outputElement.className = 'error';
                        outputElement.textContent = `ERROR: ${data.message}`;
                    }
                } catch (error) {
                    outputElement.className = 'error';
                    outputElement.textContent = 'ERROR: ' + (
                        error.message.includes('Failed to fetch') ? 'Connection lost' :
                        error.message.includes('AbortError') ? 'Client timeout' :
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
                outputElement.textContent = 'ERROR: Network lost';
            });
            window.addEventListener('online', () => {
                outputElement.textContent = '';
            });
        }
    }

    new ConnectionMonitor();
    </script>
</body>
</html>

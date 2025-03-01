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

    echo json_encode(['status' => 'success', 'message' => 'Connection stable']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Network Connection Monitor</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f4f4;
            margin: 0;
        }
        .status-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            width: 300px;
        }
        .status-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        .success {
            color: green;
        }
        .error {
            color: maroon;
            background-color: #ffe6e6;
            padding: 10px;
            border-radius: 5px;
        }
        .status-message {
            font-size: 16px;
            font-weight: bold;
        }
        .fade {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>

    <div class="status-container">
        <div id="status-icon" class="status-icon">🔄</div>
        <div id="status-message" class="status-message">Checking connection...</div>
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
                const statusMessage = document.getElementById('status-message');
                const statusIcon = document.getElementById('status-icon');
                
                statusMessage.textContent = 'Checking connection...';
                statusIcon.innerHTML = '🔄';
                statusMessage.className = 'status-message fade';

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
                    statusMessage.className = 'status-message fade ' + (data.status === 'success' ? 'success' : 'error');
                    statusIcon.innerHTML = data.status === 'success' ? '✔️' : '⚠️';
                    statusMessage.textContent = data.message;
                } catch (error) {
                    statusMessage.className = 'status-message fade error';
                    statusIcon.innerHTML = '⚠️';
                    statusMessage.textContent = 'ERROR: ' + (
                        error.message === 'Failed to fetch' ? 'Connection lost or server not responding' :
                        error.message === 'AbortError' ? 'Client timeout after 8s' :
                        error.message
                    );
                }

                await new Promise(resolve => setTimeout(resolve, 5000)); // Check every 5 seconds
            }
        }

        monitorNetwork() {
            const statusMessage = document.getElementById('status-message');
            const statusIcon = document.getElementById('status-icon');
            window.addEventListener('offline', () => {
                statusMessage.className = 'status-message fade error';
                statusIcon.innerHTML = '⚠️';
                statusMessage.textContent = 'ERROR: Network connection lost';
            });
            window.addEventListener('online', () => {
                statusMessage.className = 'status-message fade success';
                statusIcon.innerHTML = '✔️';
                statusMessage.textContent = 'Network connection restored';
            });
        }
    }

    new ConnectionMonitor();
    </script>
</body>
</html>

<?php
set_time_limit(15);
ini_set('default_socket_timeout', 5);

function checkConnection() {
    return connection_status() === CONNECTION_NORMAL;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    if (!checkConnection()) {
        echo json_encode(['status' => 'error', 'message' => 'Connection lost']);
        exit;
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
    <title>Connection Monitor</title>
    <style>
        body {
            font-family: Arial, sans-serif;
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
            font-size: 16px;
            font-weight: bold;
        }
        .success { color: green; }
        .error { color: maroon; background-color: #ffe6e6; padding: 5px; border-radius: 5px; }
    </style>
</head>
<body>

    <div class="status-container">
        <div id="status-output">Checking connection...</div>
    </div>

    <script>
    async function checkConnection() {
        const output = document.getElementById('status-output');
        output.textContent = 'Checking connection...';
        output.className = '';

        try {
            const response = await fetch('?ajax=1', { method: 'POST', headers: { 'Content-Type': 'application/json' } });

            if (!response.ok) throw new Error(`Server error: ${response.status}`);

            const data = await response.json();
            output.className = data.status === 'success' ? 'success' : 'error';
            output.textContent = data.message;
        } catch (error) {
            output.className = 'error';
            output.textContent = 'ERROR: ' + (error.message.includes('Failed to fetch') ? 'Connection lost or server not responding' : error.message);
        }
    }

    window.addEventListener('load', checkConnection);
    setInterval(checkConnection, 5000); // Auto-refresh every 5 seconds

    window.addEventListener('offline', () => {
        const output = document.getElementById('status-output');
        output.className = 'error';
        output.textContent = 'ERROR: Network connection lost';
    });

    window.addEventListener('online', () => {
        checkConnection();
    });
    </script>

</body>
</html>

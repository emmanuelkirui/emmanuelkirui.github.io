<?php
// PHP Backend Logic
set_time_limit(15); // Set script execution time limit to 15 seconds
ini_set('default_socket_timeout', 5); // Set default socket timeout to 5 seconds

/**
 * Check if the connection is still active.
 * @return bool Returns true if the connection is normal, otherwise false.
 */
function isConnectionActive(): bool {
    return connection_status() === CONNECTION_NORMAL;
}

// Handle AJAX POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    $startTime = time();
    $maxExecutionTime = 10; // Maximum execution time in seconds

    // Simulate a long-running process
    while (true) {
        // Check if the connection is still active
        if (!isConnectionActive()) {
            echo json_encode(['status' => 'error', 'message' => 'Connection lost during processing']);
            exit;
        }

        // Check if the maximum execution time has been exceeded
        if ((time() - $startTime) > $maxExecutionTime) {
            header('HTTP/1.1 504 Gateway Timeout');
            echo json_encode(['status' => 'error', 'message' => 'Server timeout after 10 seconds']);
            exit;
        }

        // Simulate processing delay
        sleep(1);
        break;
    }

    // Return success response
    echo json_encode(['status' => 'success', 'message' => 'Processed successfully']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connection Handler</title>
    <style>
        .status-container {
            margin: 20px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .success { color: green; }
        .error { color: maroon; background-color: #ffe6e6; padding: 5px; border-radius: 3px; }
        button {
            margin: 10px 0;
            padding: 8px 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="status-container">
        <div id="status-output">Ready to test connection.</div>
    </div>
    <button id="fetchBtn">Test Connection</button>

    <script>
        class ConnectionHandler {
            constructor() {
                this.timeout = 8000; // Client-side timeout in milliseconds
            }

            /**
             * Process the AJAX request and handle the response.
             */
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
                        error.message === 'AbortError' ? 'Client timeout after 8 seconds' :
                        error.message
                    );
                }
            }

            /**
             * Monitor the network connection status.
             */
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

        // Initialize the ConnectionHandler
        const handler = new ConnectionHandler();
        handler.monitorConnection();

        // Add event listener to the button
        document.getElementById('fetchBtn').addEventListener('click', () => {
            handler.processRequest();
        });
    </script>
</body>
</html>

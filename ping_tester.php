<?php
// Process form submission
$results = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addresses'])) {
    $input = trim($_POST['addresses']);
    $addressList = array_filter(array_map('trim', explode("\n", $input)));
    
    if (empty($addressList)) {
        $errors[] = "Please enter at least one address to ping.";
    } else {
        foreach ($addressList as $address) {
            if (!empty($address)) {
                // Basic validation
                if (!preg_match('/^[a-zA-Z0-9\.\-]+$/', $address)) {
                    $errors[] = "Invalid address format: " . htmlspecialchars($address);
                    continue;
                }
                
                // Ping command based on OS (InfinityFree uses Linux)
                $command = "ping -c 4 "; // Linux-based, as InfinityFree uses Linux servers
                exec($command . escapeshellarg($address), $output, $return_var);
                
                // Parse TTL and response time from output
                $ttl = 'N/A';
                $responseTime = 'N/A';
                foreach ($output as $line) {
                    if (preg_match('/ttl=(\d+)/i', $line, $matches)) {
                        $ttl = $matches[1];
                    }
                    if (preg_match('/time=(\d+\.\d+)/', $line, $matches)) {
                        $responseTime = $matches[1];
                    }
                }
                
                $results[$address] = [
                    'output' => implode("\n", $output),
                    'status' => ($return_var === 0) ? 'Reachable' : 'Unreachable',
                    'ttl' => $ttl,
                    'response_time' => $responseTime
                ];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ping Tester with TTL - CreativePulse</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        body {
            margin: 0;
            padding: 20px;
            background: #f5f6f5;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-top: 0;
        }
        .form-group {
            margin-bottom: 20px;
        }
        textarea {
            width: 100%;
            min-height: 150px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }
        button {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #2980b9;
        }
        .results {
            margin-top: 20px;
        }
        .result-card {
            border: 1px solid #eee;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            background: #fafafa;
        }
        .status-reachable { color: #27ae60; }
        .status-unreachable { color: #c0392b; }
        .error {
            color: #c0392b;
            background: #ffebee;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .example {
            color: #7f8c8d;
            font-style: italic;
            margin-top: 5px;
        }
        .ttl, .response-time {
            color: #8e44ad;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Ping Tester with TTL - CreativePulse</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?>
            </div>
        <?php endif; ?>

        <form method="post" class="form-group">
            <label for="addresses">Enter addresses to ping (one per line):</label>
            <textarea name="addresses" id="addresses" placeholder="8.8.8.8
google.com
facebook.com"><?php echo isset($_POST['addresses']) ? htmlspecialchars($_POST['addresses']) : ''; ?></textarea>
            <div class="example">Example: Enter IP addresses or domain names, one per line</div>
            <button type="submit">Run Ping Test</button>
        </form>

        <?php if (!empty($results)): ?>
            <div class="results">
                <h2>Results</h2>
                <?php foreach ($results as $address => $result): ?>
                    <div class="result-card">
                        <h3><?php echo htmlspecialchars($address); ?></h3>
                        <p>Status: 
                            <span class="status-<?php echo strtolower($result['status']); ?>">
                                <?php echo $result['status']; ?>
                            </span>
                        </p>
                        <p>TTL: <span class="ttl"><?php echo $result['ttl']; ?></span></p>
                        <p>Avg Response Time: <span class="response-time"><?php echo $result['response_time']; ?> <?php echo $result['response_time'] !== 'N/A' ? 'ms' : ''; ?></span></p>
                        <pre><?php echo htmlspecialchars($result['output']); ?></pre>
                    </div>
                <?php endforeach; ?>
                <p>Test completed: <?php echo date('Y-m-d H:i:s'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

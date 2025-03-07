<?php
$url = 'https://api.football-data.org/v4/competitions/SA/scorers';
$token = 'd2ef1a157a0d4c83ba4023d1fbd28b5c';

ini_set('default_socket_timeout', 30); // Set PHP timeout to 30 seconds

$options = [
    'http' => [
        'header' => "X-Auth-Token: $token\r\n",
        'timeout' => 30,
        'ignore_errors' => true,
        'method' => 'GET'
    ],
    'ssl' => [
        'verify_peer' => false,  // Temporary for testing
        'verify_peer_name' => false
    ]
];

$context = stream_context_create($options);
$body = @file_get_contents($url, false, $context); // @ to suppress warnings
$httpCode = $http_response_header[0] ?? 'No response';
$headers = implode("\n", array_slice($http_response_header ?? [], 0));

// Error handling
$error = false;
if ($body === false) {
    $error = error_get_last()['message'] ?? 'Unknown error occurred';
}

?>
<!DOCTYPE html>
<html>
<head><title>API Test</title></head>
<body>
    <h1>Football-Data API Test</h1>
    <p><strong>URL:</strong> <?php echo htmlspecialchars($url); ?></p>
    <p><strong>HTTP Status:</strong> <?php echo htmlspecialchars($httpCode); ?></p>
    <?php if ($error): ?>
        <p><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <h2>Response:</h2>
    <?php if ($body !== false && strpos($httpCode, '200') !== false): ?>
        <pre><?php echo json_encode(json_decode($body), JSON_PRETTY_PRINT); ?></pre>
    <?php else: ?>
        <p><strong>Error Response:</strong> <?php echo htmlspecialchars($body ?? 'No response received'); ?></p>
        <p><strong>Headers:</strong></p>
        <pre><?php echo htmlspecialchars($headers); ?></pre>
    <?php endif; ?>
</body>
</html>

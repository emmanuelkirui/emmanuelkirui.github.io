<?php
$url = 'https://api.football-data.org/v4/competitions/SA/scorers';
$token = 'd2ef1a157a0d4c83ba4023d1fbd28b5c';

try {
    $client = new http\Client;
    $request = new http\Client\Request('GET', $url, [
        'X-Auth-Token' => $token
    ]);
    
    $client->setOptions([
        'timeout' => 30,
        'connecttimeout' => 10,
        'verifypeer' => false,  // For testing only
        'verifyhost' => false
    ]);
    
    $client->enqueue($request)->send();
    $response = $client->getResponse();
    
    $httpCode = $response->getResponseCode();
    $headers = implode("\n", $response->getHeaders());
    $body = $response->getBody();

} catch (Exception $e) {
    $error = $e->getMessage();
    $httpCode = 0;
}

?>
<!DOCTYPE html>
<html>
<head><title>API Test</title></head>
<body>
    <h1>Football-Data API Test</h1>
    <p><strong>URL:</strong> <?php echo htmlspecialchars($url); ?></p>
    <p><strong>HTTP Status Code:</strong> <?php echo $httpCode; ?></p>
    <?php if (isset($error)): ?>
        <p><strong>Error:</strong> <?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <h2>Response:</h2>
    <?php if ($httpCode == 200): ?>
        <pre><?php echo json_encode(json_decode($body), JSON_PRETTY_PRINT); ?></pre>
    <?php else: ?>
        <p><strong>Error Response:</strong> <?php echo htmlspecialchars($body ?? ''); ?></p>
        <p><strong>Headers:</strong></p>
        <pre><?php echo htmlspecialchars($headers ?? ''); ?></pre>
    <?php endif; ?>
</body>
</html>

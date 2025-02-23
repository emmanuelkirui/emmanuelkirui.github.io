<?php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize cURL session
$curl = curl_init();

// Set cURL options
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://v3.football.api-sports.io/leagues?current=true', // Fetch current leagues
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
    CURLOPT_HTTPHEADER => array(
        'x-apisports-key: f8be56e9365110d1887b69f11f3db11c', // Replace with your API key
    ),
));
// Execute cURL request and get the response
$response = curl_exec($curl);

// Check for cURL errors
if ($response === false) {
    die('Curl error: ' . curl_error($curl));
}

// Close cURL session
curl_close($curl);

// Decode the JSON response
$leagues = json_decode($response, true);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Football Leagues Dropdown</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        select {
            width: 220px;
            padding: 8px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
            background-color: #f9f9f9;
            transition: border-color 0.3s;
        }
        select:focus {
            border-color: #007BFF;
            outline: none;
        }
    </style>
</head>
<body>
<?php
// Check if the response contains data
if (isset($leagues['response'])) {
    echo '<select name="leagues">';
    foreach ($leagues['response'] as $league) {
        echo '<option value="' . $league['league']['id'] . '">' . $league['league']['name'] . '</option>';
    }
    echo '</select>';
} else {
    echo "No leagues found or an error occurred.";
}
?>

</body>
</html>

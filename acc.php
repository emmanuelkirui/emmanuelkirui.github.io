<?php

// Initialize cURL session
$curl = curl_init();

// Set cURL options
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://v3.football.api-sports.io/fixtures?season=2024&league=39', // Example for Premier League 2024
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

// Close cURL session
curl_close($curl);

// Decode the JSON response
$fixtures = json_decode($response, true);

// Check if the response contains data
if (isset($fixtures['response'])) {
    // Loop through the fixtures and display them
    foreach ($fixtures['response'] as $fixture) {
        echo "Match: " . $fixture['teams']['home']['name'] . " vs " . $fixture['teams']['away']['name'] . "\n";
        echo "Date: " . $fixture['fixture']['date'] . "\n";
        echo "Status: " . $fixture['fixture']['status']['long'] . "\n";
        echo "---------------------------------\n";
    }
} else {
    echo "No fixtures found or an error occurred.\n";
}

?>

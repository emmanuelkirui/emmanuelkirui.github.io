<?php
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
<?php

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
        'x-apisports-key: YOUR_API_KEY_HERE', // Replace with your API key
    ),
));

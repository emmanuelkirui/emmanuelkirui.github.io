<?php
// Path to the JSON file
$jsonFile = 'quotes.json';

// Read the JSON file
if (!file_exists($jsonFile)) {
    die("Quotes file not found!");
}

$jsonData = file_get_contents($jsonFile);
$quotes = json_decode($jsonData, true);

// Validate JSON structure
if (!is_array($quotes)) {
    die("Invalid JSON format in quotes file!");
}

// Get the current date
$currentDate = date("Y-m-d");

// Get the current day of the year (1-365/366)
$currentDayOfYear = date("z") + 1;

// Get the total days in the current year
$totalDaysOfYear = date("L") ? 366 : 365;

// Select a daily quote based on the current day of the year
$quoteIndex = $currentDayOfYear % count($quotes);
$dailyQuote = $quotes[$quoteIndex]['quote'];

// Output as HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Quote</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
            background: #f4f4f9;
            color: #333;
        }
        h1 {
            color: #007bff;
        }
        .quote {
            font-style: italic;
            margin: 20px 0;
        }
        .info {
            font-size: 1.2em;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>Daily Quote</h1>
    <p class="info"><strong>Date:</strong> <?= $currentDate; ?></p>
    <p class="info"><strong>Day:</strong> <?= $currentDayOfYear; ?> / <?= $totalDaysOfYear; ?></p>
    <p class="quote"><strong>Quote of the Day:</strong> "<?= htmlspecialchars($dailyQuote, ENT_QUOTES, 'UTF-8'); ?>"</p>
</body>
</html>

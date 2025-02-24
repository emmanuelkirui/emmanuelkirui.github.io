<?php
// API key from football-data.org
$apiKey = 'd2ef1a157a0d4c83ba4023d1fbd28b5c'; // Replace with your API key

// Base URL for the API
$baseUrl = 'https://api.football-data.org/v4/';

// Set default date filter to "Today"
$dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'today';

// Set default league to "Premier League"
$league = isset($_GET['league']) ? $_GET['league'] : 'PL';

// Function to fetch data from the API
function fetchData($url, $apiKey) {
    $context = stream_context_create([
        'http' => [
            'header' => "X-Auth-Token: $apiKey\r\n"
        ]
    ]);
    $response = file_get_contents($url, false, $context);
    if ($response === FALSE) {
        die('Error fetching data from the API.');
    }
    return json_decode($response, true);
}

// Fetch available leagues
$leaguesUrl = $baseUrl . 'competitions';
$leaguesData = fetchData($leaguesUrl, $apiKey);
$leagues = [];
foreach ($leaguesData['competitions'] as $comp) {
    if (isset($comp['code'])) {
        $leagues[$comp['code']] = $comp['name'];
    }
}

// Determine the date range based on the filter
switch ($dateFilter) {
    case 'yesterday':
        $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'tomorrow':
        $dateFrom = $dateTo = date('Y-m-d', strtotime('+1 day'));
        break;
    case 'custom':
        $dateFrom = isset($_GET['custom_date_from']) ? $_GET['custom_date_from'] : date('Y-m-d');
        $dateTo = isset($_GET['custom_date_to']) ? $_GET['custom_date_to'] : date('Y-m-d');
        break;
    default: // today
        $dateFrom = $dateTo = date('Y-m-d');
}

// Fetch matches based on the date range and league
$matchesUrl = $baseUrl . 'matches?competitions=' . $league . '&dateFrom=' . $dateFrom . '&dateTo=' . $dateTo;
$matchesData = fetchData($matchesUrl, $apiKey);

// Function to generate predictions based on shared opponents
function generatePredictions($matches) {
    $teamPerformance = [];

    // Analyze each match
    foreach ($matches as $match) {
        $homeTeam = $match['homeTeam']['name'];
        $awayTeam = $match['awayTeam']['name'];
        $homeGoals = $match['score']['fullTime']['home'] ?? 0;
        $awayGoals = $match['score']['fullTime']['away'] ?? 0;

        // Initialize team performance data if not already set
        if (!isset($teamPerformance[$homeTeam])) {
            $teamPerformance[$homeTeam] = ['goalsScored' => 0, 'goalsConceded' => 0, 'wins' => 0, 'losses' => 0];
        }
        if (!isset($teamPerformance[$awayTeam])) {
            $teamPerformance[$awayTeam] = ['goalsScored' => 0, 'goalsConceded' => 0, 'wins' => 0, 'losses' => 0];
        }

        // Update home team performance
        $teamPerformance[$homeTeam]['goalsScored'] += $homeGoals;
        $teamPerformance[$homeTeam]['goalsConceded'] += $awayGoals;
        if ($homeGoals > $awayGoals) {
            $teamPerformance[$homeTeam]['wins'] += 1;
        } elseif ($homeGoals < $awayGoals) {
            $teamPerformance[$homeTeam]['losses'] += 1;
        }

        // Update away team performance
        $teamPerformance[$awayTeam]['goalsScored'] += $awayGoals;
        $teamPerformance[$awayTeam]['goalsConceded'] += $homeGoals;
        if ($awayGoals > $homeGoals) {
            $teamPerformance[$awayTeam]['wins'] += 1;
        } elseif ($awayGoals < $homeGoals) {
            $teamPerformance[$awayTeam]['losses'] += 1;
        }
    }

    // Generate predictions for upcoming matches
    $predictions = [];
    foreach ($matches as $match) {
        $homeTeam = $match['homeTeam']['name'];
        $awayTeam = $match['awayTeam']['name'];

        // Skip if performance data is not available for either team
        if (!isset($teamPerformance[$homeTeam]) || !isset($teamPerformance[$awayTeam])) {
            continue;
        }

        // Calculate prediction based on performance
        $homeStrength = ($teamPerformance[$homeTeam]['wins'] + $teamPerformance[$homeTeam]['goalsScored']) - $teamPerformance[$homeTeam]['losses'];
        $awayStrength = ($teamPerformance[$awayTeam]['wins'] + $teamPerformance[$awayTeam]['goalsScored']) - $teamPerformance[$awayTeam]['losses'];

        if ($homeStrength > $awayStrength) {
            $prediction = "$homeTeam is likely to win against $awayTeam.";
        } elseif ($homeStrength < $awayStrength) {
            $prediction = "$awayTeam is likely to win against $homeTeam.";
        } else {
            $prediction = "The match between $homeTeam and $awayTeam is likely to be a draw.";
        }

        $predictions[] = $prediction;
    }

    return $predictions;
}

// Generate predictions
$predictions = generatePredictions($matchesData['matches'] ?? []);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Football Predictions</title>
</head>
<body>
    <h1>Football Predictions</h1>
    <form method="GET">
        <label for="date_filter">Date Filter:</label>
        <select name="date_filter" id="date_filter">
            <option value="today" <?php echo $dateFilter == 'today' ? 'selected' : ''; ?>>Today</option>
            <option value="yesterday" <?php echo $dateFilter == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
            <option value="tomorrow" <?php echo $dateFilter == 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
            <option value="custom" <?php echo $dateFilter == 'custom' ? 'selected' : ''; ?>>Custom</option>
        </select>

        <div id="custom_date" style="<?php echo $dateFilter == 'custom' ? 'display:block;' : 'display:none;'; ?>">
            <label for="custom_date_from">From:</label>
            <input type="date" name="custom_date_from" id="custom_date_from" value="<?php echo $dateFrom; ?>">
            <label for="custom_date_to">To:</label>
            <input type="date" name="custom_date_to" id="custom_date_to" value="<?php echo $dateTo; ?>">
        </div>

        <label for="league">League:</label>
        <select name="league" id="league">
            <?php foreach ($leagues as $code => $name): ?>
                <option value="<?php echo $code; ?>" <?php echo $league == $code ? 'selected' : ''; ?>><?php echo $name; ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Filter</button>
    </form>

    <h2>Predictions</h2>
    <?php if (!empty($predictions)): ?>
        <ul>
            <?php foreach ($predictions as $prediction): ?>
                <li><?php echo $prediction; ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No predictions available for the selected date range and league.</p>
    <?php endif; ?>

    <script>
        // Show/hide custom date fields based on the selected date filter
        document.getElementById('date_filter').addEventListener('change', function() {
            var customDateDiv = document.getElementById('custom_date');
            if (this.value == 'custom') {
                customDateDiv.style.display = 'block';
            } else {
                customDateDiv.style.display = 'none';
            }
        });
    </script>
</body>
</html>

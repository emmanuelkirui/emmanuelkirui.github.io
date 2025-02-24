<?php
// API key from football-data.org
$apiKey = 'd2ef1a157a0d4c83ba4023d1fbd28b5c'; // Replace with your API key

// Base URL for the API
$baseUrl = 'https://api.football-data.org/v4/';

// Set default date filter and league
$dateFilter = $_GET['date_filter'] ?? 'today';
$league = $_GET['league'] ?? 'PL';

// Function to fetch data from API
function fetchData($url, $apiKey) {
    $context = stream_context_create([
        'http' => ['header' => "X-Auth-Token: $apiKey\r\n"]
    ]);
    $response = file_get_contents($url, false, $context);
    return $response ? json_decode($response, true) : null;
}

// Fetch available leagues
$leaguesData = fetchData($baseUrl . 'competitions', $apiKey);
$leagues = [];
foreach ($leaguesData['competitions'] as $comp) {
    if (isset($comp['code'])) {
        $leagues[$comp['code']] = $comp['name'];
    }
}

// Set date range based on filter
switch ($dateFilter) {
    case 'yesterday': $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day')); break;
    case 'tomorrow':  $dateFrom = $dateTo = date('Y-m-d', strtotime('+1 day')); break;
    case 'custom':
        $dateFrom = $_GET['custom_date_from'] ?? date('Y-m-d');
        $dateTo = $_GET['custom_date_to'] ?? date('Y-m-d');
        break;
    default: // today
        $dateFrom = $dateTo = date('Y-m-d');
}

// Fetch matches
$matchesUrl = "{$baseUrl}matches?competitions={$league}&dateFrom={$dateFrom}&dateTo={$dateTo}";
$matchesData = fetchData($matchesUrl, $apiKey);

// Generate predictions
function generatePredictions($matches) {
    $teamStats = [];

    // Process past results
    foreach ($matches as $match) {
        $home = $match['homeTeam']['name'];
        $away = $match['awayTeam']['name'];
        $homeGoals = $match['score']['fullTime']['home'] ?? 0;
        $awayGoals = $match['score']['fullTime']['away'] ?? 0;

        if (!isset($teamStats[$home])) $teamStats[$home] = ['scored' => 0, 'conceded' => 0, 'wins' => 0, 'losses' => 0];
        if (!isset($teamStats[$away])) $teamStats[$away] = ['scored' => 0, 'conceded' => 0, 'wins' => 0, 'losses' => 0];

        $teamStats[$home]['scored'] += $homeGoals;
        $teamStats[$home]['conceded'] += $awayGoals;
        if ($homeGoals > $awayGoals) $teamStats[$home]['wins']++;
        if ($homeGoals < $awayGoals) $teamStats[$home]['losses']++;

        $teamStats[$away]['scored'] += $awayGoals;
        $teamStats[$away]['conceded'] += $homeGoals;
        if ($awayGoals > $homeGoals) $teamStats[$away]['wins']++;
        if ($awayGoals < $homeGoals) $teamStats[$away]['losses']++;
    }

    // Generate predictions
    $predictions = [];
    foreach ($matches as $match) {
        $home = $match['homeTeam']['name'];
        $away = $match['awayTeam']['name'];

        if (!isset($teamStats[$home]) || !isset($teamStats[$away])) continue;

        $homeStrength = ($teamStats[$home]['wins'] + $teamStats[$home]['scored']) - $teamStats[$home]['losses'];
        $awayStrength = ($teamStats[$away]['wins'] + $teamStats[$away]['scored']) - $teamStats[$away]['losses'];

        if ($homeStrength > $awayStrength) {
            $prediction = "$home is likely to win.";
        } elseif ($homeStrength < $awayStrength) {
            $prediction = "$away is likely to win.";
        } else {
            $prediction = "Likely a draw.";
        }

        $predictions[$home . ' vs ' . $away] = $prediction;
    }

    return $predictions;
}

// Generate predictions based on fixtures
$matches = $matchesData['matches'] ?? [];
$predictions = generatePredictions($matches);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Football Fixtures & Predictions</title>
</head>
<body>
    <h1>Football Fixtures & Predictions</h1>
    <form method="GET">
        <label for="date_filter">Date Filter:</label>
        <select name="date_filter" id="date_filter">
            <option value="today" <?= $dateFilter == 'today' ? 'selected' : ''; ?>>Today</option>
            <option value="yesterday" <?= $dateFilter == 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
            <option value="tomorrow" <?= $dateFilter == 'tomorrow' ? 'selected' : ''; ?>>Tomorrow</option>
            <option value="custom" <?= $dateFilter == 'custom' ? 'selected' : ''; ?>>Custom</option>
        </select>

        <div id="custom_date" style="<?= $dateFilter == 'custom' ? 'display:block;' : 'display:none;'; ?>">
            <label for="custom_date_from">From:</label>
            <input type="date" name="custom_date_from" id="custom_date_from" value="<?= $dateFrom; ?>">
            <label for="custom_date_to">To:</label>
            <input type="date" name="custom_date_to" id="custom_date_to" value="<?= $dateTo; ?>">
        </div>

        <label for="league">League:</label>
        <select name="league" id="league">
            <?php foreach ($leagues as $code => $name): ?>
                <option value="<?= $code; ?>" <?= $league == $code ? 'selected' : ''; ?>><?= $name; ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Filter</button>
    </form>

    <h2>Fixtures & Predictions</h2>
    <table border="1">
        <thead>
            <tr>
                <th>Date</th>
                <th>Home Team</th>
                <th>Away Team</th>
                <th>Prediction</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($matches)): ?>
                <?php foreach ($matches as $match): ?>
                    <tr>
                        <td><?= date('Y-m-d', strtotime($match['utcDate'])); ?></td>
                        <td><?= $match['homeTeam']['name']; ?></td>
                        <td><?= $match['awayTeam']['name']; ?></td>
                        <td><?= $predictions[$match['homeTeam']['name'] . ' vs ' . $match['awayTeam']['name']] ?? 'No prediction'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4">No matches found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        document.getElementById('date_filter').addEventListener('change', function() {
            document.getElementById('custom_date').style.display = this.value == 'custom' ? 'block' : 'none';
        });
    </script>
</body>
</html>

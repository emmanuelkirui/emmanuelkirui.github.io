<?php
// Function to fetch and analyze goal data for a given season
function analyze_goals($year) {
    $uri = 'http://api.football-data.org/v4/competitions/BL1/matches?season=' . $year;
    $headers = array(
        'X-Auth-Token: d2ef1a157a0d4c83ba4023d1fbd28b5c' // Replace with your API token
    );

    // Fetch data from the API
    $options = array(
        'http' => array(
            'header'  => $headers,
            'method'  => 'GET'
        )
    );
    $context  = stream_context_create($options);
    $response = file_get_contents($uri, false, $context);

    if ($response === FALSE) {
        die("Error fetching data for season $year.");
    }

    $data = json_decode($response, true);
    $matches = $data['matches'];

    // Filter matches up to matchday 17 (since matchday < 18)
    $matchesUntilMatchdayX = array_filter($matches, function($match) {
        return $match['matchday'] < 18;
    });

    // Calculate total goals
    $totalGoals = 0;
    foreach ($matchesUntilMatchdayX as $match) {
        $totalGoals += $match['score']['fullTime']['homeTeam'] + $match['score']['fullTime']['awayTeam'];
    }

    // Calculate averages
    $avgPerMatchday = round($totalGoals / 17, 2); // 17 matchdays
    $avgPerGame = round($totalGoals / count($matchesUntilMatchdayX), 2);

    // Output results
    echo "<h3>Season $year</h3>";
    echo "<p>Total goals scored: $totalGoals</p>";
    echo "<p>Average per matchday: $avgPerMatchday</p>";
    echo "<p>Average per game: $avgPerGame</p>";
    echo "<hr>";
}

// Main script
echo "<h1>Bundesliga Goal Analysis</h1>";
echo "<p>This script analyzes the total goals scored in the Bundesliga (BL1) up to matchday 17 for the specified seasons.</p>";

// Analyze goals for the specified seasons
$seasons = range(2018, 2016, -1); // Seasons 2018, 2017, 2016
foreach ($seasons as $year) {
    analyze_goals($year);
}
?>

<?php
// Function to fetch available competitions
function get_available_competitions() {
    $uri = 'https://api.football-data.org/v4/competitions';
    $headers = array(
        'X-Auth-Token: d2ef1a157a0d4c83ba4023d1fbd28b5c', // Replace with your API token
        'Accept-Encoding: '
    );

    $options = array(
        'http' => array(
            'header'  => $headers,
            'method'  => 'GET'
        )
    );
    $context  = stream_context_create($options);
    $response = file_get_contents($uri, false, $context);

    if ($response === FALSE) {
        die("Error fetching available competitions.");
    }

    $competitions = json_decode($response, true);
    $available_competitions = array();

    // Filter active competitions
    foreach ($competitions as $comp) {
        if (isset($comp['code'])) { // Include all competitions with a code
            array_push($available_competitions, $comp['code']);
        }
    }

    return $available_competitions;
}

// Function to fetch available seasons for a competition
function get_available_seasons($competition_code) {
    $uri = 'https://api.football-data.org/v4/competitions/' . $competition_code;
    $headers = array(
        'X-Auth-Token: d2ef1a157a0d4c83ba4023d1fbd28b5c', // Replace with your API token
        'Accept-Encoding: '
    );

    $options = array(
        'http' => array(
            'header'  => $headers,
            'method'  => 'GET'
        )
    );
    $context  = stream_context_create($options);
    $response = file_get_contents($uri, false, $context);

    if ($response === FALSE) {
        die("Error fetching available seasons for $competition_code.");
    }

    $data = json_decode($response, true);
    $seasons = array();

    // Extract available seasons
    foreach ($data['seasons'] as $season) {
        if ($season['current'] === false) { // Exclude the current season
            array_push($seasons, $season['year']);
        }
    }

    return $seasons;
}

// Function to analyze goal data for a competition and season
function analyze_goals($competition_code, $year) {
    $uri = 'https://api.football-data.org/v4/competitions/' . $competition_code . '/matches?season=' . $year;
    $headers = array(
        'X-Auth-Token: d2ef1a157a0d4c83ba4023d1fbd28b5c', // Replace with your API token
        'Accept-Encoding: '
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
        die("Error fetching data for $competition_code in season $year.");
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

    // Return results
    return array(
        'totalGoals' => $totalGoals,
        'avgPerMatchday' => $avgPerMatchday,
        'avgPerGame' => $avgPerGame
    );
}

// Main script
echo "<h1>Goal Analysis for All Competitions</h1>";
echo "<p>This script analyzes the total goals scored in all available competitions up to matchday 17 for the specified seasons.</p>";

// Get available competitions
$competitions = get_available_competitions();

// Analyze goals for each competition and season
foreach ($competitions as $competition_code) {
    echo "<h2>Competition: $competition_code</h2>";

    $seasons = get_available_seasons($competition_code); // Get available seasons for the competition
    foreach ($seasons as $year) {
        $results = analyze_goals($competition_code, $year);

        echo "<h3>Season $year</h3>";
        echo "<p>Total goals scored: " . $results['totalGoals'] . "</p>";
        echo "<p>Average per matchday: " . $results['avgPerMatchday'] . "</p>";
        echo "<p>Average per game: " . $results['avgPerGame'] . "</p>";
        echo "<hr>";
    }
}
?>

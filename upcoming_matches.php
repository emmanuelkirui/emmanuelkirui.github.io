<?php
// Set headers for the API request
$uri = 'https://api.football-data.org/v4/matches';
$headers = array(
    'X-Auth-Token: d2ef1a157a0d4c83ba4023d1fbd28b5c', // Replace with your API key
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
    die("Error fetching data from the API.");
}

$data = json_decode($response, true);
$upcoming_matches = $data['matches'];

// Initialize variables to track highest odds
$highest_odds = array("homeWin" => 0.0, "draw" => 0.0, "awayWin" => 0.0);
$matches = array("homeWin" => null, "draw" => null, "awayWin" => null);

// Analyze odds for each match
foreach ($upcoming_matches as $m) {
    if (isset($m['odds'])) {
        if (isset($m['odds']['homeWin']) && $m['odds']['homeWin'] > $highest_odds['homeWin']) {
            $highest_odds['homeWin'] = $m['odds']['homeWin'];
            $matches['homeWin'] = $m;
        }
        if (isset($m['odds']['draw']) && $m['odds']['draw'] > $highest_odds['draw']) {
            $highest_odds['draw'] = $m['odds']['draw'];
            $matches['draw'] = $m;
        }
        if (isset($m['odds']['awayWin']) && $m['odds']['awayWin'] > $highest_odds['awayWin']) {
            $highest_odds['awayWin'] = $m['odds']['awayWin'];
            $matches['awayWin'] = $m;
        }
    } else {
        echo "You need to enable Odds in User-Panel.<br>";
        break;
    }
}

// Output the results
echo "<h1>Highest Odds for Upcoming Matches</h1>";
echo "<p>Here are the matches with the highest odds for home win, draw, and away win:</p>";

if ($matches['homeWin']) {
    echo "<p><strong>Home Win:</strong> " . $matches['homeWin']['homeTeam']['name'] . " vs " . $matches['homeWin']['awayTeam']['name'] . " (" . $highest_odds['homeWin'] . ")</p>";
} else {
    echo "<p>No home win odds available.</p>";
}

if ($matches['draw']) {
    echo "<p><strong>Draw:</strong> " . $matches['draw']['homeTeam']['name'] . " vs " . $matches['draw']['awayTeam']['name'] . " (" . $highest_odds['draw'] . ")</p>";
} else {
    echo "<p>No draw odds available.</p>";
}

if ($matches['awayWin']) {
    echo "<p><strong>Away Win:</strong> " . $matches['awayWin']['homeTeam']['name'] . " vs " . $matches['awayWin']['awayTeam']['name'] . " (" . $highest_odds['awayWin'] . ")</p>";
} else {
    echo "<p>No away win odds available.</p>";
}
?>

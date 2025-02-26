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

    // Filter active competitions with match data
    foreach ($competitions as $comp) {
        if (isset($comp['code']) && $comp['plan'] === 'TIER_ONE') { // Only include top-tier leagues
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

// Function to fetch home win percentages for a competition
function get_home_wins_for_competition($competition_code, $seasons) {
    $base_uri = 'https://api.football-data.org/v4/competitions/' . $competition_code . '/matches';
    $headers = array(
        'X-Auth-Token: d2ef1a157a0d4c83ba4023d1fbd28b5c', // Replace with your API token
        'Accept-Encoding: '
    );
    $retVal = array();

    foreach ($seasons as $year) {
        $uri = $base_uri . "?season=" . $year;
        $options = array(
            'http' => array(
                'header'  => $headers,
                'method'  => 'GET'
            )
        );
        $context  = stream_context_create($options);
        $response = file_get_contents($uri, false, $context);

        if ($response === FALSE) {
            die("Error fetching data for $competition_code in season $year");
        }

        $matches = json_decode($response, true)['matches'];
        $home_wins = 0;
        $match_counter = 0;

        foreach ($matches as $m) {
            if ($m['score']['winner'] == "HOME_TEAM") {
                $home_wins += 1;
            }
            if ($m['score']['winner'] !== null && $m['score']['winner'] != "DRAW") {
                $match_counter += 1;
            }
        }

        if ($match_counter > 0) {
            array_push($retVal, round(($home_wins / $match_counter) * 100, 2));
        } else {
            array_push($retVal, 0);
        }
    }

    return $retVal;
}

// Get available competitions
$competitions = get_available_competitions();
$values = array();

// Fetch data for each competition
foreach ($competitions as $key) {
    $seasons = get_available_seasons($key); // Get available seasons for the competition
    $values[$key] = get_home_wins_for_competition($key, $seasons);
}

// Output HTML and JavaScript
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Wins Analysis</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <h1>Home Wins Analysis</h1>
    <canvas id="myChart" width="800" height="400"></canvas>

    <script>
        // Pass PHP data to JavaScript
        const values = <?php echo json_encode($values); ?>;

        // Create the chart
        const ctx = document.getElementById('myChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: Object.keys(values).length > 0 ? Object.keys(values[Object.keys(values)[0]]) : [], // Use seasons as labels
                datasets: Object.keys(values).map(competition => ({
                    label: competition,
                    data: values[competition],
                    borderWidth: 2
                }))
            },
            options: {
                scales: {
                    y: {
                        min: 40,
                        max: 70,
                        title: {
                            display: true,
                            text: '% of Home Wins'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Season'
                        }
                    }
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Less Home Wins During COVID'
                    }
                }
            }
        });
    </script>
</body>
</html>

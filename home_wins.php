<?php
// Function to fetch home win percentages for a competition
function get_home_wins_for_competition($competition_code) {
    $base_uri = 'https://api.football-data.org/v2/competitions/' . $competition_code . '/matches';
    $headers = array(
        'X-Auth-Token: d2ef1a157a0d4c83ba4023d1fbd28b5c', // Replace with your API token
        'Accept-Encoding: '
    );
    $retVal = array();
    $seasons = range(2010, 2019);

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

// Competitions to analyze
$competitions = array('BL1', 'PL', 'PD', 'SA', 'FL1', 'DED');
$seasons = range(2010, 2019);
$values = array();

// Fetch data for each competition
foreach ($competitions as $key) {
    $values[$key] = get_home_wins_for_competition($key);
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
        const seasons = <?php echo json_encode($seasons); ?>;
        const values = <?php echo json_encode($values); ?>;

        // Create the chart
        const ctx = document.getElementById('myChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: seasons,
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

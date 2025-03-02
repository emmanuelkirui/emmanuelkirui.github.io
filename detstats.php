<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Football Win Percentages</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .league { margin-bottom: 20px; padding: 10px; border: 1px solid #ccc; }
        .bar-container { width: 100%; background: #f0f0f0; height: 20px; margin: 5px 0; }
        .bar { height: 100%; text-align: right; color: white; padding-right: 5px; }
        .home { background: #4CAF50; }
        .draw { background: #FFC107; }
        .away { background: #F44336; }
        select { padding: 5px; margin-bottom: 10px; }
    </style>
    <!-- Optional: Include Chart.js for charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <h1>Football Win Percentages by League</h1>

    <?php
    // Your football-data.org API key
    $apiKey = 'd2ef1a157a0d4c83ba4023d1fbd28b5c'; // Your API key

    // Function to fetch data from API
    function fetchData($url, $apiKey) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Auth-Token: ' . $apiKey));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 429) {
            echo "<p>Rate limit exceeded. Please wait a minute and try again.</p>";
            return null;
        }
        return json_decode($response, true);
    }

    // Get all available competitions (free tier limits to top competitions)
    $competitionsUrl = "http://api.football-data.org/v4/competitions/";
    $competitions = fetchData($competitionsUrl, $apiKey);

    // Dropdown form
    if (isset($competitions['competitions'])) {
        echo "<form method='GET'>";
        echo "<label for='competition'>Select a Competition: </label>";
        echo "<select name='compId' id='competition' onchange='this.form.submit()'>";
        echo "<option value=''>-- Select a League --</option>";
        foreach ($competitions['competitions'] as $competition) {
            if ($competition['plan'] !== 'TIER_ONE') continue; // Free tier only
            $compId = $competition['id'];
            $compName = $competition['name'];
            $selected = (isset($_GET['compId']) && $_GET['compId'] == $compId) ? 'selected' : '';
            echo "<option value='$compId' $selected>$compName</option>";
        }
        echo "</select>";
        echo "</form>";
    } else {
        echo "<p>Unable to fetch competitions. Check your API key or network.</p>";
        exit;
    }

    // Process selected competition
    if (isset($_GET['compId']) && !empty($_GET['compId'])) {
        $compId = $_GET['compId'];
        $selectedComp = array_filter($competitions['competitions'], function($comp) use ($compId) {
            return $comp['id'] == $compId;
        });
        $selectedComp = reset($selectedComp); // Get first match
        $compName = $selectedComp['name'];

        // Fetch standings for the selected competition
        $standingsUrl = "http://api.football-data.org/v4/competitions/$compId/standings";
        $standings = fetchData($standingsUrl, $apiKey);

        if ($standings && isset($standings['standings'])) {
            $homeWins = 0;
            $draws = 0;
            $awayWins = 0;
            $totalGames = 0;

            // Process standings (using TOTAL standings for simplicity and accuracy)
            foreach ($standings['standings'] as $standing) {
                if ($standing['type'] === 'TOTAL') { // Use TOTAL standings
                    foreach ($standing['table'] as $team) {
                        $homeWins += $team['won'] + $team['lost'] + $team['draw'] > 0 ? $team['won'] : 0; // Total wins
                        $draws += $team['draw'];
                        $totalGames += $team['playedGames'];
                    }
                }
            }

            // Adjust for double-counting in TOTAL standings
            $totalGames = $totalGames / 2; // Each match is counted twice (home and away)
            $homeWins = $totalGames > 0 ? $homeWins - $awayWins : 0; // Correct home wins

            // Calculate percentages
            $homeWinPerc = $totalGames > 0 ? round(($homeWins / $totalGames) * 100, 2) : 0;
            $drawPerc = $totalGames > 0 ? round(($draws / $totalGames) * 100, 2) : 0;
            $awayWinPerc = $totalGames > 0 ? round(($awayWins / $totalGames) * 100, 2) : 0;

            // Display results
            echo "<div class='league'>";
            echo "<h2>$compName</h2>";
            echo "<p>Total Games: $totalGames</p>";
            echo "<div>Home Win: $homeWinPerc% ($homeWins wins)</div>";
            echo "<div class='bar-container'><div class='bar home' style='width: $homeWinPerc%'>$homeWinPerc%</div></div>";
            echo "<div>Draw: $drawPerc% ($draws draws)</div>";
            echo "<div class='bar-container'><div class='bar draw' style='width: $drawPerc%'>$drawPerc%</div></div>";
            echo "<div>Away Win: $awayWinPerc% ($awayWins wins)</div>";
            echo "<div class='bar-container'><div class='bar away' style='width: $awayWinPerc%'>$awayWinPerc%</div></div>";

            // Chart.js visualization
            echo "<canvas id='chart-$compId' width='400' height='200'></canvas>";
            echo "<script>";
            echo "var ctx = document.getElementById('chart-$compId').getContext('2d');";
            echo "new Chart(ctx, {";
            echo "    type: 'bar',";
            echo "    data: {";
            echo "        labels: ['Home Win', 'Draw', 'Away Win'],";
            echo "        datasets: [{";
            echo "            label: 'Percentage',";
            echo "            data: [$homeWinPerc, $drawPerc, $awayWinPerc],";
            echo "            backgroundColor: ['#4CAF50', '#FFC107', '#F44336']";
            echo "        }]";
            echo "    },";
            echo "    options: { scales: { y: { beginAtZero: true, max: 100 } } }";
            echo "});";
            echo "</script>";

            echo "</div>";
        } else {
            echo "<p>No standings data available for $compName or rate limit exceeded.</p>";
        }
    } else {
        echo "<p>Please select a competition from the dropdown.</p>";
    }
    ?>
</body>
</html>

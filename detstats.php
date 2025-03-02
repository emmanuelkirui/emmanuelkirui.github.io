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
        #retryCountdown { color: red; font-weight: bold; }
        .team-list { margin-top: 10px; }
        .team-list ul { list-style-type: none; padding: 0; }
        .team-list li { margin: 5px 0; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function startCountdown(seconds, elementId) {
            let timeLeft = seconds;
            const countdownElement = document.getElementById(elementId);
            countdownElement.innerHTML = `Retrying in ${timeLeft} seconds...`;
            const interval = setInterval(() => {
                timeLeft--;
                countdownElement.innerHTML = `Retrying in ${timeLeft} seconds...`;
                if (timeLeft <= 0) {
                    clearInterval(interval);
                    window.location.reload();
                }
            }, 1000);
        }
    </script>
</head>
<body>
    <h1>Football Win Percentages by League</h1>

    <?php
    $apiKey = 'd2ef1a157a0d4c83ba4023d1fbd28b5c';
    $defaultCompId = 2021; // Premier League

    function fetchData($url, $apiKey, $maxRetries = 5, $retryDelay = 60) {
        $attempt = 0;
        while ($attempt < $maxRetries) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-Auth-Token: ' . $apiKey));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 200) {
                return json_decode($response, true);
            } elseif ($httpCode == 429) {
                $attempt++;
                if ($attempt == $maxRetries) {
                    echo "<p>Max retries reached. Please try again later.</p>";
                    return null;
                }
                echo "<p>Rate limit exceeded (429). Attempt $attempt of $maxRetries.</p>";
                echo "<div id='retryCountdown'></div>";
                echo "<script>startCountdown($retryDelay, 'retryCountdown');</script>";
                sleep($retryDelay);
                continue;
            } else {
                echo "<p>API error (HTTP $httpCode). Unable to fetch data.</p>";
                return null;
            }
        }
        return null;
    }

    $competitionsUrl = "http://api.football-data.org/v4/competitions/";
    $competitions = fetchData($competitionsUrl, $apiKey);

    if (isset($competitions['competitions'])) {
        echo "<form method='GET'>";
        echo "<label for='competition'>Select a Competition: </label>";
        echo "<select name='compId' id='competition' onchange='this.form.submit()'>";
        echo "<option value=''>-- Select a League --</option>";
        foreach ($competitions['competitions'] as $competition) {
            if ($competition['plan'] !== 'TIER_ONE') continue;
            $compId = $competition['id'];
            $compName = $competition['name'];
            $selected = (!isset($_GET['compId']) && $compId == $defaultCompId) || (isset($_GET['compId']) && $_GET['compId'] == $compId) ? 'selected' : '';
            echo "<option value='$compId' $selected>$compName</option>";
        }
        echo "</select>";
        echo "</form>";
    } else {
        echo "<p>Unable to fetch competitions. Check your API key or network.</p>";
        exit;
    }

    $compId = isset($_GET['compId']) && !empty($_GET['compId']) ? $_GET['compId'] : $defaultCompId;
    $selectedComp = array_filter($competitions['competitions'], function($comp) use ($compId) {
        return $comp['id'] == $compId;
    });
    $selectedComp = reset($selectedComp);
    $compName = $selectedComp['name'];

    $standingsUrl = "http://api.football-data.org/v4/competitions/$compId/standings";
    $standings = fetchData($standingsUrl, $apiKey);

    if ($standings && isset($standings['standings'])) {
        $homeWins = 0;
        $draws = 0;
        $awayWins = 0;
        $totalGames = 0;

        $homeTeams = [];
        $awayTeams = [];
        $drawTeams = [];

        foreach ($standings['standings'] as $standing) {
            if ($standing['type'] === 'TOTAL') {
                foreach ($standing['table'] as $team) {
                    $totalGames += $team['playedGames'];
                    $homeWins += $team['won'];
                    $draws += $team['draw'];
                }
            }
            if ($standing['type'] === 'HOME') {
                foreach ($standing['table'] as $team) {
                    $teamName = $team['team']['name'];
                    $played = $team['playedGames'];
                    $won = $team['won'];
                    $drawn = $team['draw'];
                    $homeWinPerc = $played > 0 ? round(($won / $played) * 100, 2) : 0;
                    $drawPerc = $played > 0 ? round(($drawn / $played) * 100, 2) : 0;
                    $homeTeams[$teamName] = ['winPerc' => $homeWinPerc, 'drawPerc' => $drawPerc];
                }
            }
            if ($standing['type'] === 'AWAY') {
                foreach ($standing['table'] as $team) {
                    $teamName = $team['team']['name'];
                    $played = $team['playedGames'];
                    $won = $team['won'];
                    $awayWinPerc = $played > 0 ? round(($won / $played) * 100, 2) : 0;
                    $awayTeams[$teamName] = $awayWinPerc;
                }
            }
        }

        $totalGames = $totalGames / 2;
        $awayWins = $totalGames - $homeWins - $draws;

        $homeWinPerc = $totalGames > 0 ? round(($homeWins / $totalGames) * 100, 2) : 0;
        $drawPerc = $totalGames > 0 ? round(($draws / $totalGames) * 100, 2) : 0;
        $awayWinPerc = $totalGames > 0 ? round(($awayWins / $totalGames) * 100, 2) : 0;

        echo "<div class='league'>";
        echo "<h2>$compName</h2>";
        echo "<p>Total Games: $totalGames</p>";
        echo "<div>Home Win: $homeWinPerc% ($homeWins wins)</div>";
        echo "<div class='bar-container'><div class='bar home' style='width: $homeWinPerc%'>$homeWinPerc%</div></div>";
        echo "<div>Draw: $drawPerc% ($draws draws)</div>";
        echo "<div class='bar-container'><div class='bar draw' style='width: $drawPerc%'>$drawPerc%</div></div>";
        echo "<div>Away Win: $awayWinPerc% ($awayWins wins)</div>";
        echo "<div class='bar-container'><div class='bar away' style='width: $awayWinPerc%'>$awayWinPerc%</div></div>";

        echo "<canvas id='chart-$compId' width='400' height='200'></canvas>";
        echo "<script>";
        echo "var ctx = document.getElementById('chart-$compId').getContext('2d');";
        echo "new Chart(ctx, { type: 'bar', data: { labels: ['Home Win', 'Draw', 'Away Win'], datasets: [{ label: 'Percentage', data: [$homeWinPerc, $drawPerc, $awayWinPerc], backgroundColor: ['#4CAF50', '#FFC107', '#F44336'] }] }, options: { scales: { y: { beginAtZero: true, max: 100 } } } });";
        echo "</script>";

        // Categorize teams
        $likelyHomeWinners = array_filter($homeTeams, function($stats) {
            return $stats['winPerc'] > 50;
        });
        $likelyAwayWinners = array_filter($awayTeams, function($perc) {
            return $perc > 40;
        });
        $likelyDrawTeams = array_filter($homeTeams, function($stats) {
            return $stats['drawPerc'] > 30;
        });

        // Display team names only
        echo "<div class='team-list'>";
        echo "<h3>Likely to Win at Home (>50%)</h3>";
        echo "<ul>";
        foreach ($likelyHomeWinners as $teamName => $stats) {
            echo "<li>$teamName</li>";
        }
        echo "</ul>";

        echo "<h3>Likely to Win Away (>40%)</h3>";
        echo "<ul>";
        foreach ($likelyAwayWinners as $teamName => $perc) {
            echo "<li>$teamName</li>";
        }
        echo "</ul>";

        echo "<h3>Likely to Draw (>30%)</h3>";
        echo "<ul>";
        foreach ($likelyDrawTeams as $teamName => $stats) {
            echo "<li>$teamName</li>";
        }
        echo "</ul>";
        echo "</div>";

        echo "</div>";
    } else {
        echo "<p>No standings data available for $compName or failed to fetch after retries.</p>";
    }
    ?>
</body>
</html>

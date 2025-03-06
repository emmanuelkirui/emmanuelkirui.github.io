<?php
// API credentials
$user = "emmanuelkirui042";
$token = "d33e93c3e1c101feed7585ee731406b6";
$baseUrl = "https://api.soccersapi.com/v2.2";

// Function to fetch data from API
function getSoccerData($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Get current date in Nairobi time (UTC+3)
date_default_timezone_set('Africa/Nairobi');
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// Handle date filter
$selectedDate = $today; // Default to today
if (isset($_GET['date_filter'])) {
    $filter = $_GET['date_filter'];
    if ($filter === 'yesterday') {
        $selectedDate = $yesterday;
    } elseif ($filter === 'tomorrow') {
        $selectedDate = $tomorrow;
    } elseif ($filter === 'custom' && !empty($_GET['custom_date'])) {
        $selectedDate = $_GET['custom_date'];
    }
}

// Fetch leagues
$leagueUrl = "$baseUrl/leagues/?user=$user&token=$token&t=list";
$leagueData = getSoccerData($leagueUrl);

// Fetch teams and fixtures for each league
$leagues = [];
if (isset($leagueData['data']) && !empty($leagueData['data'])) {
    foreach ($leagueData['data'] as $league) {
        $leagueId = $league['id'];
        $seasonId = $league['current_season_id'];

        // Fetch teams for the league
        $teamsUrl = "$baseUrl/teams/?user=$user&token=$token&t=list&league_id=$leagueId";
        $teamsData = getSoccerData($teamsUrl);
        $teams = isset($teamsData['data']) ? $teamsData['data'] : [];

        // Fetch fixtures for the selected date
        $fixturesUrl = "$baseUrl/fixtures/?user=$user&token=$token&t=schedule&league_id=$leagueId&season_id=$seasonId&date=$selectedDate";
        $fixturesData = getSoccerData($fixturesUrl);
        $fixtures = isset($fixturesData['data']) ? $fixturesData['data'] : [];

        $leagues[] = [
            'league' => $league,
            'teams' => $teams,
            'fixtures' => $fixtures
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soccer API Leagues</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .pricing-info {
            background-color: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .filter-form {
            margin-bottom: 20px;
        }
        .leagues-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .leagues-table th, .leagues-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .leagues-table th {
            background-color: #4CAF50;
            color: white;
        }
        .meta-info {
            margin-top: 20px;
            font-size: 0.9em;
            color: #666;
        }
        .teams-list, .fixtures-list {
            margin-left: 20px;
        }
    </style>
</head>
<body>
    <h1>Soccer API Leagues</h1>
    
    <div class="pricing-info">
        <h2>Affordable Price & Free Plan</h2>
        <p>Soccer's API offers the most affordable price out there in relation to the quality and quantity of data. Also we offer a Free Plan Forever which includes three major leagues (A-League, Tipico Bundesliga and SuperLiga).</p>
    </div>

    <!-- Date Filter Form -->
    <div class="filter-form">
        <form method="GET">
            <label>Date Filter:</label>
            <select name="date_filter" onchange="if(this.value === 'custom') document.getElementById('custom_date').style.display = 'inline'; else document.getElementById('custom_date').style.display = 'none';">
                <option value="yesterday" <?php echo $selectedDate === $yesterday ? 'selected' : ''; ?>>Yesterday (<?php echo $yesterday; ?>)</option>
                <option value="today" <?php echo $selectedDate === $today ? 'selected' : ''; ?>>Today (<?php echo $today; ?>)</option>
                <option value="tomorrow" <?php echo $selectedDate === $tomorrow ? 'selected' : ''; ?>>Tomorrow (<?php echo $tomorrow; ?>)</option>
                <option value="custom">Custom</option>
            </select>
            <input type="date" id="custom_date" name="custom_date" value="<?php echo $selectedDate; ?>" style="display: <?php echo $selectedDate !== $yesterday && $selectedDate !== $today && $selectedDate !== $tomorrow ? 'inline' : 'none'; ?>;">
            <button type="submit">Filter</button>
        </form>
        <p>Current Time in Nairobi (EAT): <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>

    <?php if (!empty($leagues)): ?>
        <table class="leagues-table">
            <thead>
                <tr>
                    <th>League Name</th>
                    <th>Country</th>
                    <th>Continent</th>
                    <th>Current Season ID</th>
                    <th>Teams</th>
                    <th>Fixtures (<?php echo $selectedDate; ?>)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leagues as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['league']['name']); ?></td>
                        <td><?php echo htmlspecialchars($item['league']['country_name']); ?> (<?php echo htmlspecialchars($item['league']['cc']); ?>)</td>
                        <td><?php echo htmlspecialchars($item['league']['continent_name']); ?></td>
                        <td><?php echo htmlspecialchars($item['league']['current_season_id']); ?></td>
                        <td>
                            <div class="teams-list">
                                <?php if (!empty($item['teams'])): ?>
                                    <ul>
                                        <?php foreach ($item['teams'] as $team): ?>
                                            <li><?php echo htmlspecialchars($team['name']); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    No teams available
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="fixtures-list">
                                <?php if (!empty($item['fixtures'])): ?>
                                    <ul>
                                        <?php foreach ($item['fixtures'] as $fixture): ?>
                                            <li>
                                                <?php 
                                                $homeTeam = htmlspecialchars($fixture['home']['name']);
                                                $awayTeam = htmlspecialchars($fixture['away']['name']);
                                                $score = isset($fixture['score']) ? htmlspecialchars($fixture['score']['ft']) : 'N/A';
                                                $status = htmlspecialchars($fixture['status']);
                                                $time = date('H:i', strtotime($fixture['date'] . ' UTC')); // Convert to EAT
                                                echo "$homeTeam vs $awayTeam - Score: $score - Status: $status - Time: $time (EAT)";
                                                ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    No fixtures for this date
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="meta-info">
            <p>Requests Left: <?php echo $leagueData['meta']['requests_left']; ?></p>
            <p>User: <?php echo htmlspecialchars($leagueData['meta']['user']); ?></p>
            <p>Plan: <?php echo htmlspecialchars($leagueData['meta']['plan']); ?></p>
        </div>
    <?php else: ?>
        <p>Error: Unable to fetch league data from the API.</p>
    <?php endif; ?>
</body>
</html>

<?php
// Football-Data API Configuration
$api_key = "d2ef1a157a0d4c83ba4023d1fbd28b5c"; // Replace with your API key
$competitions_url = "https://api.football-data.org/v4/competitions"; // List all competitions

// Start session to store competitions and their data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to fetch data from the API
function fetchAPI($url, $api_key, $retries = 3) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "X-Auth-Token: $api_key"
        ],
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($http_code == 429) {
        if ($retries > 0) {
            $wait_time = pow(2, 4 - $retries);
            echo "
            <div style='text-align: center; font-family: Arial, sans-serif; margin-top: 50px;'>
                <h2 style='color: red;'>Too Many Requests (429)</h2>
                <p>Retrying in <span id='countdown' style='font-weight: bold; color: blue;'>$wait_time</span> seconds...</p>
            </div>";
            echo "
            <script>
                let timeLeft = $wait_time;
                const countdownElement = document.getElementById('countdown');
                const interval = setInterval(() => {
                    timeLeft--;
                    countdownElement.textContent = timeLeft;
                    if (timeLeft <= 0) {
                        clearInterval(interval);
                        window.location.reload();
                    }
                }, 1000);
            </script>";
            flush();
            sleep($wait_time);
            return fetchAPI($url, $api_key, $retries - 1);
        } else {
            header('Location: error');
            exit;
        }
    }

    if ($http_code != 200) {
        header('Location: error');
        exit;
    }

    return json_decode($response, true);
}

// Fetch all competitions only once and store in session
if (!isset($_SESSION['competitions'])) {
    $_SESSION['competitions'] = fetchAPI($competitions_url, $api_key)['competitions'];
}

// Function to ensure data completeness (Automatic Loading of Incomplete Data)
function ensureDataComplete($competition_id, $api_key) {
    $standings_url = "https://api.football-data.org/v4/competitions/$competition_id/standings";
    $fixtures_url = "https://api.football-data.org/v4/competitions/$competition_id/matches";

    if (!isset($_SESSION["standings_$competition_id"]) || !isset($_SESSION["fixtures_$competition_id"])) {
        $standings_data = fetchAPI($standings_url, $api_key);
        $fixtures_data = fetchAPI($fixtures_url, $api_key);
        $_SESSION["standings_$competition_id"] = $standings_data;
        $_SESSION["fixtures_$competition_id"] = $fixtures_data;
    } else {
        $standings_data = $_SESSION["standings_$competition_id"];
        $fixtures_data = $_SESSION["fixtures_$competition_id"];
    }

    if (!$standings_data || !$fixtures_data) {
        $standings_data = fetchAPI($standings_url, $api_key);
        $fixtures_data = fetchAPI($fixtures_url, $api_key);
        $_SESSION["standings_$competition_id"] = $standings_data;
        $_SESSION["fixtures_$competition_id"] = $fixtures_data;
    }

    return [$standings_data, $fixtures_data];
}

// Function to calculate team metrics
function getTeamMetrics($standings_data) {
    $metrics = [];
    foreach ($standings_data['standings'][0]['table'] as $team) {
        $team_name = $team['team']['name'];
        $played = $team['playedGames'];
        $wins = isset($team['won']) ? $team['won'] : 0;
        $goals_for = isset($team['goalsFor']) ? $team['goalsFor'] : 0;
        $goals_against = isset($team['goalsAgainst']) ? $team['goalsAgainst'] : 0;
        $draws = isset($team['draws']) ? $team['draws'] : 0;
        $losses = isset($team['lost']) ? $team['lost'] : 0;

        $metrics[$team_name] = [
            'crest' => $team['team']['crest'],
            'played' => $played,
            'win_ratio' => $played > 0 ? round($wins / $played, 2) : 0,
            'avg_goals_scored' => $played > 0 ? round($goals_for / $played, 2) : 0,
            'avg_goals_conceded' => $played > 0 ? round($goals_against / $played, 2) : 0,
            'avg_goals_difference' => $played > 0 ? round(($goals_for - $goals_against) / $played, 2) : 0,
            'draw_ratio' => $played > 0 ? round($draws / $played, 2) : 0,
            'loss_ratio' => $played > 0 ? round($losses / $played, 2) : 0,
        ];
    }
    return $metrics;
}

// Function to get standings position, goal difference, points, and goals scored
function getStandingsData($standings_data) {
    $standings = [];
    foreach ($standings_data['standings'][0]['table'] as $team) {
        $team_name = $team['team']['name'];
        $position = $team['position'];
        $goal_difference = $team['goalDifference'];
        $points = $team['points'];
        $goals_scored = $team['goalsFor'];
        $standings[$team_name] = [
            'position' => $position,
            'goal_difference' => $goal_difference,
            'points' => $points,
            'goals_scored' => $goals_scored
        ];
    }
    return $standings;
}

// Function to get prediction suggestion
function getPredictionSuggestion($home_team, $away_team, $standings, $home_last6, $away_last6) {
    $home_position = $standings[$home_team]['position'] ?? 20;
    $home_gd = $standings[$home_team]['goal_difference'] ?? 0;
    $home_gs = $standings[$home_team]['goals_scored'] ?? 0;
    $home_points = $standings[$home_team]['points'] ?? 0;
    $home_form_weight = calculateRecentFormWeight($home_last6);

    $away_position = $standings[$away_team]['position'] ?? 20;
    $away_gd = $standings[$away_team]['goal_difference'] ?? 0;
    $away_gs = $standings[$away_team]['goals_scored'] ?? 0;
    $away_points = $standings[$away_team]['points'] ?? 0;
    $away_form_weight = calculateRecentFormWeight($away_last6);

    if ($home_position < $away_position && $home_form_weight > $away_form_weight) {
        $decision = "Home Win";
        $reason = "Home team is higher in the table and in better form.";
    } elseif ($home_position > $away_position && $home_form_weight < $away_form_weight) {
        $decision = "Away Win";
        $reason = "Away team is higher in the table and in better form.";
    } elseif ($home_gd > $away_gd && $home_gs > $away_gs) {
        $decision = "Home Win";
        $reason = "Home team has a stronger goal difference and scoring record.";
    } elseif ($home_gd < $away_gd && $home_gs < $away_gs) {
        $decision = "Away Win";
        $reason = "Away team has a stronger goal difference and scoring record.";
    } elseif ($home_points > $away_points) {
        $decision = "Home Win";
        $reason = "Home team has more points in the standings.";
    } elseif ($home_points < $away_points) {
        $decision = "Away Win";
        $reason = "Away team has more points in the standings.";
    } else {
        $decision = "Draw";
        $reason = "Teams are evenly matched based on current data.";
    }

    return [
        'decision' => $decision,
        'reason' => $reason
    ];
}

// Helper function to calculate a numeric weight for recent form
function calculateRecentFormWeight($recent_form) {
    $form_weights = [
        'W' => 3,
        'D' => 1,
        'L' => 0
    ];
    $total_weight = 0;

    for ($i = 0; $i < strlen($recent_form); $i++) {
        $match_result = strtoupper($recent_form[$i]);
        $total_weight += isset($form_weights[$match_result]) ? $form_weights[$match_result] : 0;
    }

    return strlen($recent_form) > 0 ? $total_weight / strlen($recent_form) : 0;
}

// Function to calculate home/away advantage
function calculateHomeAwayAdvantage($fixtures_data) {
    $home_points = 0;
    $away_points = 0;
    $total_home_matches = 0;
    $total_away_matches = 0;

    foreach ($fixtures_data['matches'] as $match) {
        if ($match['status'] === 'FINISHED' && isset($match['score']['fullTime']['home'], $match['score']['fullTime']['away'])) {
            $home_score = $match['score']['fullTime']['home'];
            $away_score = $match['score']['fullTime']['away'];

            if ($home_score > $away_score) {
                $home_points += 3;
            } elseif ($home_score < $away_score) {
                $away_points += 3;
            } else {
                $home_points += 1;
                $away_points += 1;
            }

            $total_home_matches++;
            $total_away_matches++;
        }
    }

    if ($total_home_matches == 0 || $total_away_matches == 0) {
        return ['home_advantage' => 1.2, 'away_advantage' => 1.0];
    }

    $avg_home_points = $home_points / $total_home_matches;
    $avg_away_points = $away_points / $total_away_matches;

    $home_advantage = $avg_home_points / $avg_away_points;
    $away_advantage = $avg_away_points / $avg_home_points;

    return ['home_advantage' => $home_advantage, 'away_advantage' => $away_advantage];
}

// Function to predict match outcome with revised weights
function predictMatch($home_metrics, $away_metrics, $advantages) {
    $home_advantage = $advantages['home_advantage'];
    $away_advantage = $advantages['away_advantage'];

    $home_recent_form_weight = calculateRecentFormWeight($home_metrics['recent_form'] ?? '');
    $away_recent_form_weight = calculateRecentFormWeight($away_metrics['recent_form'] ?? '');

    $home_score = ($home_metrics['win_ratio'] * 1.3)  
                + ($home_metrics['avg_goals_scored'] * 1.2) 
                - ($home_metrics['avg_goals_conceded'] * 0.8)
                + ($home_recent_form_weight * 0.7)
                + $home_advantage;

    $away_score = ($away_metrics['win_ratio'] * 1.3) 
                + ($away_metrics['avg_goals_scored'] * 1.2) 
                - ($away_metrics['avg_goals_conceded'] * 0.8)
                + ($away_recent_form_weight * 0.7)
                + $away_advantage;

    $score_difference = $home_score - $away_score;

    if ($score_difference > 0.8) {  
        return "Win for Home";
    } elseif ($score_difference < -0.8) {  
        return "Win for Away";
    } else {
        return "Draw";
    }
}

// Function to predict goals
function predictGoals($home_metrics, $away_metrics, $advantages) {
    $home_advantage = $advantages['home_advantage'];
    $away_advantage = $advantages['away_advantage'];

    $home_recent_form_weight = calculateRecentFormWeight($home_metrics['recent_form'] ?? '');
    $away_recent_form_weight = calculateRecentFormWeight($away_metrics['recent_form'] ?? '');

    $home_score = ($home_metrics['win_ratio'] * 1.3)  
                + ($home_metrics['avg_goals_scored'] * 1.2) 
                - ($home_metrics['avg_goals_conceded'] * 0.8)
                + ($home_recent_form_weight * 0.7)
                + $home_advantage;

    $away_score = ($away_metrics['win_ratio'] * 1.3) 
                + ($away_metrics['avg_goals_scored'] * 1.2) 
                - ($away_metrics['avg_goals_conceded'] * 0.8)
                + ($away_recent_form_weight * 0.7)
                + $away_advantage;

    $predicted_home_goals = max(0, round($home_score));
    $predicted_away_goals = max(0, round($away_score));

    return [
        'home_goals' => $predicted_home_goals,
        'away_goals' => $predicted_away_goals
    ];
}

// Function to get last 6 matches for a team
function getLast6Matches($team_name, $fixtures) {
    $results = [];
    $fixtures = array_reverse($fixtures);
    
    foreach ($fixtures as $match) {
        if (strcasecmp($match['homeTeam']['name'], $team_name) === 0 || strcasecmp($match['awayTeam']['name'], $team_name) === 0) {
            if ($match['status'] === 'FINISHED' && isset($match['score']['fullTime']['home'], $match['score']['fullTime']['away'])) {
                $home_score = $match['score']['fullTime']['home'];
                $away_score = $match['score']['fullTime']['away'];

                if (strcasecmp($match['homeTeam']['name'], $team_name) === 0) {
                    if ($home_score > $away_score) {
                        $results[] = ['result' => 'W', 'color' => 'green'];
                    } elseif ($home_score < $away_score) {
                        $results[] = ['result' => 'L', 'color' => 'red'];
                    } else {
                        $results[] = ['result' => 'D', 'color' => 'blue'];
                    }
                } else {
                    if ($away_score > $home_score) {
                        $results[] = ['result' => 'W', 'color' => 'green'];
                    } elseif ($away_score < $home_score) {
                        $results[] = ['result' => 'L', 'color' => 'red'];
                    } else {
                        $results[] = ['result' => 'D', 'color' => 'blue'];
                    }
                }
            }
        }

        if (count($results) >= 6) {
            break;
        }
    }

    $results = array_reverse($results);

    if (!empty($results)) {
        $formatted_results = '';
        foreach ($results as $index => $result) {
            $style = $index === count($results) - 1 ? 'font-weight: bold; text-decoration: underline;' : '';
            $formatted_results .= "<span style='color: {$result['color']}; $style; display: inline-block; line-height: 1; padding: 0; margin: 0;'>{$result['result']}</span>";
        }
        return $formatted_results;
    }

    return "N/A";
}

// Function to calculate date range filter
function filterMatchesByDate($matches, $filter, $start_date = null, $end_date = null) {
    $filtered_matches = [];
    $now = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
    $today = $now->format('Y-m-d');
    $yesterday = (clone $now)->modify('-1 day')->format('Y-m-d');
    $tomorrow = (clone $now)->modify('+1 day')->format('Y-m-d');
    $start_of_week = (clone $now)->modify('last Monday')->format('Y-m-d');
    $end_of_week = (clone $now)->modify('next Sunday')->format('Y-m-d');
    $start_of_month = $now->format('Y-m-01');
    $end_of_month = (clone $now)->modify('last day of this month')->format('Y-m-d');
    $start_of_last_month = (clone $now)->modify('first day of last month')->format('Y-m-d');
    $end_of_last_month = (clone $now)->modify('last day of last month')->format('Y-m-d');

    foreach ($matches as $match) {
        $match_date = (new DateTime($match['utcDate'], new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Africa/Nairobi'))
            ->format('Y-m-d');

        switch ($filter) {
            case 'yesterday':
                if ($match_date == $yesterday) $filtered_matches[] = $match;
                break;
            case 'today':
                if ($match_date == $today) $filtered_matches[] = $match;
                break;
            case 'tomorrow':
                if ($match_date == $tomorrow) $filtered_matches[] = $match;
                break;
            case 'this_week':
                if ($match_date >= $start_of_week && $match_date <= $end_of_week) $filtered_matches[] = $match;
                break;
            case 'last_week':
                $last_week_start = (clone $now)->modify('-1 week')->modify('last Monday')->format('Y-m-d');
                $last_week_end = (clone $now)->modify('-1 week')->modify('next Sunday')->format('Y-m-d');
                if ($match_date >= $last_week_start && $match_date <= $last_week_end) $filtered_matches[] = $match;
                break;
            case 'this_month':
                if ($match_date >= $start_of_month && $match_date <= $end_of_month) $filtered_matches[] = $match;
                break;
            case 'last_month':
                if ($match_date >= $start_of_last_month && $match_date <= $end_of_last_month) $filtered_matches[] = $match;
                break;
            case 'custom':
                if ($start_date && $end_date && $match_date >= $start_date && $match_date <= $end_date) $filtered_matches[] = $match;
                break;
            default:
                $filtered_matches[] = $match;
                break;
        }
    }
    return $filtered_matches;
}

// Function to search matches by team
function searchMatchesByTeam($matches, $team) {
    $filtered_matches = [];
    foreach ($matches as $match) {
        if (strpos(strtolower($match['homeTeam']['name']), strtolower($team)) !== false || 
            strpos(strtolower($match['awayTeam']['name']), strtolower($team)) !== false) {
            $filtered_matches[] = $match;
        }
    }
    return $filtered_matches;
}

// Convert UTC time to EAT (UTC +3)
function convertToEAT($utcDate) {
    $date = new DateTime($utcDate, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Africa/Nairobi'));
    return $date->format('Y-m-d H:i:s');
}

// Get selected competition and fetch its data
$selected_competition = isset($_GET['competition']) ? $_GET['competition'] : 'PL';
$view_preference = isset($_GET['view']) ? $_GET['view'] : 'table';

// Fetch data for the selected competition with automatic loading
list($standings_data, $fixtures_data) = ensureDataComplete($selected_competition, $api_key);
$team_metrics = getTeamMetrics($standings_data);
$standings = getStandingsData($standings_data);

// Display the competition dropdown
echo "<h1>Football Match Predictions</h1>";
echo '<link rel="stylesheet" type="text/css" href="css/liv.css">';
echo '<link rel="stylesheet" type="text/css" href="css/network-status.css">';

// Status light
echo '<!-- Status Light -->
<div id="status_light" class="status">
    <div class="status_light">
        <div class="status_light_ring"></div>
        <div class="status_light_led"></div>
    </div>
    <span class="status_message">Processing</span>
</div>';
echo '<style>
.status { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.9); display: none; justify-content: center; align-items: center; z-index: 1000; flex-direction: column; text-align: center; font-family: "Roboto", sans-serif; font-size: 1.2em; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); transition: background-color 0.3s ease, transform 0.3s ease; }
.status_light { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 10px; transition: transform 0.3s ease-in-out; }
.status_light_ring { width: 70px; height: 70px; border: 6px solid #3498db; border-radius: 50%; position: absolute; background: linear-gradient(45deg, rgba(52, 152, 219, 0.3), rgba(142, 68, 173, 0.3)); animation: pulse 1.5s infinite ease-in-out, rotateRing 4s infinite linear; box-shadow: 0 0 15px rgba(52, 152, 219, 0.5); }
.status_light_led { width: 25px; height: 25px; background-color: #fff; border-radius: 50%; position: absolute; z-index: 1; box-shadow: 0 0 10px rgba(255, 255, 255, 0.6); animation: glow 1.5s infinite alternate ease-in-out; }
@keyframes pulse { 0% { transform: scale(1); opacity: 1; box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.4); } 50% { transform: scale(1.2); opacity: 0.8; box-shadow: 0 0 20px 10px rgba(52, 152, 219, 0.2); } 100% { transform: scale(1); opacity: 1; box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.4); } }
@keyframes rotateRing { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes glow { 0% { background-color: #fff; box-shadow: 0 0 10px rgba(255, 255, 255, 0.6); } 100% { background-color: #3498db; box-shadow: 0 0 15px rgba(52, 152, 219, 0.8); } }
.status_message { margin-top: 20px; color: #2c3e50; font-weight: 600; font-size: 1.4em; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); animation: fadeIn 1s ease-in-out; }
@keyframes fadeIn { 0% { opacity: 0; transform: translateY(-20px); } 100% { opacity: 1; transform: translateY(0); } }
.status_message:after { content: ""; display: inline-block; width: 6px; height: 6px; border-radius: 50%; background-color: #3498db; margin-left: 6px; animation: dots 1.5s infinite steps(1) forwards; }
@keyframes dots { 0% { content: "."; } 33% { content: ".."; } 66% { content: "..."; } 100% { content: "."; } }
.status:hover .status_light_ring { box-shadow: 0 0 20px rgba(52, 152, 219, 0.8); }
.status:hover .status_light_led { background-color: #3498db; box-shadow: 0 0 20px rgba(52, 152, 219, 0.8); }
</style>';

echo '<script>
function showStatusLight() {
    document.getElementById("status_light").style.display = "flex";
}
document.addEventListener("DOMContentLoaded", function () {
    const competitionForm = document.getElementById("competitionForm");
    const searchForm = document.getElementById("searchForm");
    if (competitionForm) {
        competitionForm.addEventListener("submit", function () {
            showStatusLight();
        });
    }
    if (searchForm) {
        searchForm.addEventListener("submit", function () {
            showStatusLight();
        });
    }
});
window.onload = function () {
    const statusLight = document.getElementById("status_light");
    if (statusLight) {
        statusLight.style.display = "none";
    }
};
</script>';

echo '<form id="searchForm" method="GET" action="">';
$default_competition = 'PL';
$default_date_filter = 'all';
$selected_competition = isset($_GET['competition']) ? $_GET['competition'] : $default_competition;
$selected_date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : $default_date_filter;
$selected_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$selected_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

echo '<label for="competition">Select Competition:</label>
      <select name="competition" id="competition">
          <option value="">-- Select Competition --</option>';
foreach ($_SESSION['competitions'] as $competition) {
    $competition_id = $competition['code'];
    $competition_name = $competition['name'];
    echo "<option value='$competition_id' " . ($selected_competition == $competition_id ? 'selected' : '') . ">$competition_name</option>";
}
if (!$selected_competition || !in_array($selected_competition, array_column($_SESSION['competitions'], 'code'))) {
    echo "<option value='$default_competition' selected>Premier League</option>";
}
echo '</select>';

echo '<label for="view">View Preference:</label>';
echo '<select name="view" id="view">';
echo "<option value='table' " . ($view_preference == 'table' ? 'selected' : '') . ">Table View</option>";
echo "<option value='card' " . ($view_preference == 'card' ? 'selected' : '') . ">Card View</option>";
echo '</select>';

echo '<label for="date_filter">Filter by Date:</label>
      <select name="date_filter" id="date_filter" onchange="toggleCustomRange(this.value)">';
$date_filters = [
    'all' => 'All Matches',
    'yesterday' => 'Yesterday',
    'today' => 'Today',
    'tomorrow' => 'Tomorrow',
    'this_week' => 'This Week',
    'last_week' => 'Last Week',
    'this_month' => 'This Month',
    'last_month' => 'Last Month',
    'custom' => 'Custom Range'
];
foreach ($date_filters as $value => $label) {
    echo "<option value='$value' " . ($selected_date_filter == $value ? 'selected' : '') . ">$label</option>";
}
echo '</select>';

echo '<div id="custom_range" style="' . ($selected_date_filter == 'custom' ? 'display: block;' : 'display: none;') . '">
          <label for="start_date">Start Date:</label>
          <input type="date" name="start_date" id="start_date" value="' . $selected_start_date . '">
          <label for="end_date">End Date:</label>
          <input type="date" name="end_date" id="end_date" value="' . $selected_end_date . '">
      </div>';

echo '<input type="submit" value="Search" />
      </form>';

echo "<script>
function toggleCustomRange(value) {
    const customRange = document.getElementById('custom_range');
    customRange.style.display = value === 'custom' ? 'block' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    const viewDropdown = document.getElementById('view');
    viewDropdown.addEventListener('change', function() {
        const form = document.getElementById('searchForm');
        showStatusLight();
        form.submit();
    });

    const dateFilter = document.getElementById('date_filter').value;
    toggleCustomRange(dateFilter);
});
</script>";

if ($selected_competition && $fixtures_data) {
    $date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

    $filtered_matches = filterMatchesByDate($fixtures_data['matches'], $date_filter, $start_date, $end_date);

    echo "<h2>" . $_SESSION['competitions'][array_search($selected_competition, array_column($_SESSION['competitions'], 'code'))]['name'] . "</h2>";

    if (empty($filtered_matches)) {
        echo "<p style='color: red; font-weight: bold;'>No matches found for the selected date range.</p>";
    } else {
        echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">';

        // Define styles based on view preference
        echo '<style>
        .match-display { 
            display: flex; 
            flex-direction: column; 
            width: 100%; 
            margin: 0 auto; 
            padding: 0; 
            gap: 20px; 
            max-width: 1200px;
        }
        .table-view table {
            width: 100%;
            border-collapse: collapse;
            font-family: "Arial", sans-serif;
        }
        .table-view th, .table-view td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .table-view th {
            background-color: #f2f2f2;
        }
        .card-view {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }
        .card {
            display: flex;
            flex-direction: column;
            width: 100%;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            font-family: "Arial", sans-serif;
            border: 1px solid #e0e0e0;
            box-sizing: border-box;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-header h3 {
            font-size: 20px;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 0;
        }
        .card-header img {
            width: 35px;
            height: 35px;
            object-fit: contain;
        }
        .card-content {
            display: flex;
            flex-direction: column;
            gap: 12px;
            padding-top: 15px;
        }
        .card-content .row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
        }
        .card-content .label {
            font-weight: 600;
            color: #222;
            flex: 1;
            min-width: 120px;
        }
        .card-content .value {
            color: #555;
            flex: 2;
            text-align: left;
        }
        .card-content .prediction {
            background: #f5f5f5;
            padding: 6px 12px;
            border-radius: 5px;
            font-size: 14px;
            color: #2c3e50;
            text-align: center;
            display: inline-block;
        }
        .card-content .status-finished { color: #28a745; font-weight: bold; }
        .card-content .status-scheduled { color: #e67e22; font-weight: bold; }
        .card-content .match-result span { font-size: 20px; }

        @media (max-width: 768px) {
            .match-display {
                padding: 0 10px;
            }
            .card {
                border-radius: 8px;
                padding: 15px;
            }
            .card-header h3 {
                font-size: 16px;
            }
            .card-header img {
                width: 25px;
                height: 25px;
            }
            .card-content .row {
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }
            .card-content .label, .card-content .value {
                text-align: left;
                font-size: 14px;
            }
            .card-content .prediction {
                font-size: 13px;
            }
            .table-view th, .table-view td {
                font-size: 12px;
                padding: 6px;
            }
        }
        </style>';

        echo '<div class="match-display ' . ($view_preference == 'card' ? 'card-view' : 'table-view') . '">';
        
        if ($view_preference == 'table') {
            // Table View (unchanged as requested)
            echo "<table border='1' cellpadding='5' cellspacing='0'>";
            echo "<tr><th>Date (EAT)</th><th>Home Team</th><th>Away Team</th><th>Status</th><th>Score</th><th>Prediction</th><th>Match Result</th><th>Matchday</th></tr>";
            foreach ($filtered_matches as $match) {
                $advantages = calculateHomeAwayAdvantage($fixtures_data);
                $date_eat = convertToEAT($match['utcDate']);
                $home_team = $match['homeTeam']['name'];
                $away_team = $match['awayTeam']['name'];
                $status = $match['status'];
                $score = isset($match['score']['fullTime']) ? "{$match['score']['fullTime']['home']} - {$match['score']['fullTime']['away']}" : "N/A";
                $matchday = $match['matchday'] ?? "Unknown";
                $last6_home = getLast6Matches($home_team, $fixtures_data['matches']);
                $last6_away = getLast6Matches($away_team, $fixtures_data['matches']);
                $home_crest = $team_metrics[$home_team]['crest'] ?? '';
                $away_crest = $team_metrics[$away_team]['crest'] ?? '';
                $prediction = $team_metrics[$home_team] && $team_metrics[$away_team] ? predictMatch($team_metrics[$home_team], $team_metrics[$away_team], $advantages) : "N/A";
                $predicted_goals = $team_metrics[$home_team] && $team_metrics[$away_team] ? predictGoals($team_metrics[$home_team], $team_metrics[$away_team], $advantages) : ['home_goals' => 'N/A', 'away_goals' => 'N/A'];
                $match_result = ($status == 'FINISHED' && $score != 'N/A') ? (predictMatchResult($prediction, $score) ? "<span style='color: green;'>✓</span>" : "<span style='color: red;'>✕</span>") : "";

                echo "<tr>
                    <td>$date_eat</td>
                    <td><img src='$home_crest' style='height: 30px; width: 30px;' /> $home_team ($last6_home)</td>
                    <td><img src='$away_crest' style='height: 30px; width: 30px;' /> $away_team ($last6_away)</td>
                    <td>$status</td>
                    <td>$score</td>
                    <td>$prediction<br><span style='font-size: 12px; color: gray;'>Predicted Goals: {$predicted_goals['home_goals']} - {$predicted_goals['away_goals']}</span></td>
                    <td>$match_result</td>
                    <td>$matchday</td>
                </tr>";
            }
            echo "</table>";
        } else {
            // Card View
            foreach ($filtered_matches as $match) {
                $advantages = calculateHomeAwayAdvantage($fixtures_data);
                $date_eat = convertToEAT($match['utcDate']);
                $home_team = $match['homeTeam']['name'];
                $away_team = $match['awayTeam']['name'];
                $status = $match['status'];
                $score = isset($match['score']['fullTime']) ? "{$match['score']['fullTime']['home']} - {$match['score']['fullTime']['away']}" : "N/A";
                $venue = $match['matchday'] ?? "Unknown";
                $last6_home = getLast6Matches($match['homeTeam']['name'], $fixtures_data['matches']);
                $last6_away = getLast6Matches($match['awayTeam']['name'], $fixtures_data['matches']);
                $home_crest = $team_metrics[$home_team]['crest'] ?? '';
                $away_crest = $team_metrics[$away_team]['crest'] ?? '';
                $prediction = getPredictionSuggestion($home_team, $away_team, $standings, $last6_home, $last6_away);
                $decision = $prediction['decision'];
                $reason = $prediction['reason'];

                $home_position = $standings[$home_team]['position'] ?? 'N/A';
                $home_goal_diff = $standings[$home_team]['goal_difference'] ?? 'N/A';
                $home_points = $standings[$home_team]['points'] ?? 'N/A';
                $home_goals_scored = $standings[$home_team]['goals_scored'] ?? 'N/A';
                $away_position = $standings[$away_team]['position'] ?? 'N/A';
                $away_goal_diff = $standings[$away_team]['goal_difference'] ?? 'N/A';
                $away_points = $standings[$away_team]['points'] ?? 'N/A';
                $away_goals_scored = $standings[$away_team]['goals_scored'] ?? 'N/A';

                $prediction = '';
                $match_result = '';
                $predicted_goals = '';
                if (isset($team_metrics[$home_team]) && isset($team_metrics[$away_team])) {
                    $home_metrics = $team_metrics[$home_team];
                    $away_metrics = $team_metrics[$away_team];
                    $prediction = predictMatch($home_metrics, $away_metrics, $advantages);
                    $predicted_goals_data = predictGoals($home_metrics, $away_metrics, $advantages);
                    $predicted_goals = "{$predicted_goals_data['home_goals']} - {$predicted_goals_data['away_goals']}";

                    if ($status == 'FINISHED' && $score != 'N/A') {
                        $score_home = explode(" - ", $score)[0];
                        $score_away = explode(" - ", $score)[1];
                        $match_result = ($prediction == "Win for Home" && $score_home > $score_away) || 
                                        ($prediction == "Win for Away" && $score_away > $score_home) || 
                                        ($prediction == "Draw" && $score_home == $score_away) ? 
                                        "<span style='color: green;'>✓</span>" : "<span style='color: red;'>✕</span>";
                    }
                }

                echo "<div class='card'>
                    <div class='card-header'>
                        <h3><img src='$home_crest' alt='$home_team' /> $home_team</h3>
                        <h3><img src='$away_crest' alt='$away_team' /> $away_team</h3>
                    </div>
                    <div class='card-content'>
                        <div class='row'>
                            <span class='label'>Date (EAT):</span>
                            <span class='value'>$date_eat</span>
                        </div>
                        <div class='row'>
                            <span class='label'>Home Stats:</span>
                            <span class='value'>Form: $last6_home | Pos: $home_position | GD: $home_goal_diff | Pts: $home_points | GS: $home_goals_scored</span>
                        </div>
                        <div class='row'>
                            <span class='label'>Away Stats:</span>
                            <span class='value'>Form: $last6_away | Pos: $away_position | GD: $away_goal_diff | Pts: $away_points | GS: $away_goals_scored</span>
                        </div>
                        <div class='row'>
                            <span class='label'>Status:</span>
                            <span class='value status-" . strtolower($status) . "'>$status</span>
                        </div>
                        <div class='row'>
                            <span class='label'>Score:</span>
                            <span class='value'>$score</span>
                        </div>
                        <div class='row'>
                            <span class='label'>Prediction:</span>
                            <span class='value prediction'>$prediction (Goals: $predicted_goals)</span>
                        </div>
                        <div class='row'>
                            <span class='label'>Match Result:</span>
                            <span class='value match-result'>$match_result</span>
                        </div>
                        <div class='row'>
                            <span class='label'>Matchday:</span>
                            <span class='value'>$venue</span>
                        </div>
                        <div class='row'>
                            <span class='label'>Decision:</span>
                            <span class='value'>$decision - <em>$reason</em></span>
                        </div>
                    </div>
                </div>";
            }
        }
        echo "</div>";

        echo '<div style="text-align: right; margin-bottom: 10px;">
                <button id="shareButton" style="background: none; border: none; cursor: pointer; margin-right: 10px;">
                    <i class="fas fa-share-alt" style="font-size: 24px; color: #007bff;"></i>
                </button>
                <button id="downloadButton" style="background: none; border: none; cursor: pointer;">
                    <i class="fas fa-download" style="font-size: 24px; color: #28a745;"></i>
                </button>
              </div>';

        echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function getFormattedTimestamp() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const day = String(now.getDate()).padStart(2, "0");
    const hours = String(now.getHours()).padStart(2, "0");
    const minutes = String(now.getMinutes()).padStart(2, "0");
    const seconds = String(now.getSeconds()).padStart(2, "0");
    return `cps_${year}${month}${day}_${hours}${minutes}${seconds}.png`;
}

function captureTable(callback) {
    const table = document.querySelector(".match-display");
    setTimeout(() => {
        html2canvas(table, {
            scale: 3,
            useCORS: true,
            backgroundColor: "#ffffff",
            width: table.scrollWidth,
            height: table.scrollHeight
        }).then(canvas => callback(canvas))
          .catch(error => {
              console.error("Error capturing table:", error);
              showFeedbackMessage("Failed to capture table.", false);
          });
    }, 200);
}

function startCountdown(action, duration) {
    let countdown = duration;
    const countdownElement = document.createElement("div");
    countdownElement.style.position = "fixed";
    countdownElement.style.top = "50%";
    countdownElement.style.left = "50%";
    countdownElement.style.transform = "translate(-50%, -50%)";
    countdownElement.style.backgroundColor = "rgba(0, 0, 0, 0.8)";
    countdownElement.style.color = "#fff";
    countdownElement.style.padding = "20px";
    countdownElement.style.borderRadius = "10px";
    countdownElement.style.zIndex = "1000";
    countdownElement.style.fontSize = "24px";
    countdownElement.style.fontFamily = "Arial, sans-serif";
    countdownElement.textContent = `Download/Share will start in ${countdown} seconds...`;
    document.body.appendChild(countdownElement);

    const interval = setInterval(() => {
        countdown--;
        countdownElement.textContent = `Download/Share will start in ${countdown} seconds...`;
        if (countdown <= 0) {
            clearInterval(interval);
            document.body.removeChild(countdownElement);
            action();
        }
    }, 1000);
}

function showFeedbackMessage(message, isSuccess) {
    const feedbackElement = document.createElement("div");
    feedbackElement.style.position = "fixed";
    feedbackElement.style.top = "50%";
    feedbackElement.style.left = "50%";
    feedbackElement.style.transform = "translate(-50%, -50%)";
    feedbackElement.style.backgroundColor = isSuccess ? "rgba(0, 128, 0, 0.8)" : "rgba(255, 0, 0, 0.8)";
    feedbackElement.style.color = "#fff";
    feedbackElement.style.padding = "20px";
    feedbackElement.style.borderRadius = "10px";
    feedbackElement.style.zIndex = "1000";
    feedbackElement.style.fontSize = "20px";
    feedbackElement.style.fontFamily = "Arial, sans-serif";
    feedbackElement.textContent = message;
    document.body.appendChild(feedbackElement);

    setTimeout(() => {
        document.body.removeChild(feedbackElement);
    }, 3000);
}

document.getElementById("shareButton").addEventListener("click", async function() {
    startCountdown(() => {
        captureTable(canvas => {
            canvas.toBlob(blob => {
                if (!blob) {
                    console.error("Failed to create blob from canvas.");
                    showFeedbackMessage("Failed to create image.", false);
                    return;
                }

                const fileName = getFormattedTimestamp();
                const file = new File([blob], fileName, { type: "image/png" });

                if (!navigator.share || !navigator.canShare({ files: [file] })) {
                    showFeedbackMessage("Web Share API not supported or cannot share images.", false);
                    return;
                }

                navigator.share({
                    files: [file],
                    title: "Captured Table",
                    text: "Here is a table snapshot"
                }).then(() => {
                    showFeedbackMessage("Table shared successfully!", true);
                }).catch(error => {
                    console.error("Error sharing:", error);
                    showFeedbackMessage("Failed to share table.", false);
                });
            }, "image/png");
        });
    }, 5);
});

document.getElementById("downloadButton").addEventListener("click", function() {
    startCountdown(() => {
        captureTable(canvas => {
            const link = document.createElement("a");
            link.href = canvas.toDataURL("image/png");
            link.download = getFormattedTimestamp();
            link.click();
            showFeedbackMessage("Table downloaded successfully!", true);
        });
    }, 5);
});
</script>';
    }
}
}

// Helper function to check prediction accuracy (used in original table view)
function predictMatchResult($prediction, $score) {
    list($home_score, $away_score) = explode(" - ", $score);
    if ($prediction == "Win for Home" && $home_score > $away_score) return true;
    if ($prediction == "Win for Away" && $away_score > $home_score) return true;
    if ($prediction == "Draw" && $home_score == $away_score) return true;
    return false;
}
?>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<script>
function toggleCustomRange(value) {
    const customRange = document.getElementById('custom_range');
    customRange.style.display = (value === 'custom') ? 'block' : 'none';
}
document.addEventListener('DOMContentLoaded', function() {
    const dateFilter = document.getElementById('date_filter').value;
    toggleCustomRange(dateFilter);
});
</script>
<script src="network-status.js"></script>
<?php include 'back-to-top.php'; ?>

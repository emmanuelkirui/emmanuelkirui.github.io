<?php
// Football-Data API Configuration
$api_key = "d2ef1a157a0d4c83ba4023d1fbd28b5c"; // Replace with your API key
$base_url = "https://api.football-data.org/v4/";
$competitions_url = $base_url . "competitions";

// Start session to store competitions and rate-limiting data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize persistent API state
if (!isset($_SESSION['api_state'])) {
    $_SESSION['api_state'] = [
        'request_timestamps' => [],
        'last_reset_time' => time(),
        'processed_competitions' => [],
        'rate_limit_reset' => 60, // Seconds until reset (Football-Data.org free tier: 1 minute)
        'max_requests' => 10,    // Max requests per minute (Football-Data.org free tier)
        'queue' => []
    ];
}

if (!isset($_SESSION['competition_data'])) {
    $_SESSION['competition_data'] = [];
}

// Function to enforce rate limit with automatic reset
function enforceRateLimit(&$state) {
    $current_time = time();
    $window = $state['rate_limit_reset'];

    // Clean up timestamps older than the window
    $state['request_timestamps'] = array_filter(
        $state['request_timestamps'],
        function ($timestamp) use ($current_time, $window) {
            return ($current_time - $timestamp) <= $window;
        }
    );

    // Check if we've exceeded the rate limit
    if (count($state['request_timestamps']) >= $state['max_requests']) {
        $oldest_request = min($state['request_timestamps']);
        $wait_time = $window - ($current_time - $oldest_request) + 1; // Add 1 sec buffer

        if ($wait_time > 0) {
            echo "<div style='text-align: center; font-family: Arial, sans-serif; margin-top: 50px;'>";
            echo "<h2 style='color: orange;'>Rate Limit Reached</h2>";
            echo "<p>Waiting <span id='countdown' style='font-weight: bold; color: blue;'>$wait_time</span> seconds...</p>";
            echo "</div>";

            echo "<script>
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
        }
    }

    // Add current request timestamp
    $state['request_timestamps'][] = $current_time;
    $_SESSION['api_state'] = $state;
}

// Function to fetch data from the API with rate limiting and retries
function fetchAPI($url, $api_key, &$state, $retries = 3) {
    enforceRateLimit($state);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["X-Auth-Token: $api_key"],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($http_code == 429) {
        if ($retries > 0) {
            $wait_time = pow(2, 4 - $retries); // Exponential backoff: 8, 4, 2 seconds
            echo "<div style='text-align: center; font-family: Arial, sans-serif; margin-top: 50px;'>";
            echo "<h2 style='color: red;'>Too Many Requests (429)</h2>";
            echo "<p>Retrying in <span id='countdown' style='font-weight: bold; color: blue;'>$wait_time</span> seconds...</p>";
            echo "</div>";

            echo "<script>
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
            return fetchAPI($url, $api_key, $state, $retries - 1);
        } else {
            array_push($state['queue'], $url); // Queue for later processing
            logError("Max retries reached for $url");
            return false;
        }
    }

    if ($http_code != 200 || $error) {
        array_push($state['queue'], $url); // Queue for later processing
        logError("API Error: HTTP $http_code - $error for $url");
        return false;
    }

    return json_decode($response, true);
}

// Function to log errors
function logError($message) {
    file_put_contents('api_errors.log', date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Process queued API requests
function processQueue(&$state, $api_key) {
    while (!empty($state['queue'])) {
        $url = array_shift($state['queue']);
        $data = fetchAPI($url, $api_key, $state);
        if ($data) {
            if (strpos($url, 'competitions') === false) {
                $comp_id = extractCompetitionId($url);
                if ($comp_id) {
                    if (strpos($url, 'standings')) {
                        $_SESSION['competition_data'][$comp_id]['standings'] = $data;
                    } elseif (strpos($url, 'matches')) {
                        $_SESSION['competition_data'][$comp_id]['matches'] = $data;
                    }
                    $state['processed_competitions'][] = $comp_id;
                }
            } else {
                $_SESSION['competitions'] = $data['competitions'];
            }
        }
    }
}

function extractCompetitionId($url) {
    preg_match('/competitions\/([^\/]+)/', $url, $matches);
    return $matches[1] ?? null;
}

// Initialize data loading
function initializeData($api_key, &$state) {
    if (!isset($_SESSION['competitions'])) {
        $data = fetchAPI($competitions_url, $api_key, $state);
        if ($data) {
            $_SESSION['competitions'] = $data['competitions'];
            foreach ($data['competitions'] as $comp) {
                $comp_id = $comp['code'];
                if (!in_array($comp_id, $state['processed_competitions']) && $comp_id) {
                    $state['queue'][] = "{$GLOBALS['base_url']}competitions/{$comp_id}/standings";
                    $state['queue'][] = "{$GLOBALS['base_url']}competitions/{$comp_id}/matches";
                }
            }
        }
    }
    processQueue($state, $api_key);
}

// Navigation bar
if (!isset($_GET['ajax'])) {
    echo "<nav class='navbar'>";
    echo "<div class='hamburger' onclick='toggleMenu()'>
            <span class='bar'></span>
            <span class='bar'></span>
            <span class='bar'></span>
          </div>";
    echo "<div class='nav-menu' id='navMenu'>";
    echo "<a href='liv' class='nav-link'>Home</a>";
    echo "<a href='valmanu' class='nav-link'>More Predictions</a>";
    echo "<a href='javascript:history.back()' class='nav-link'>Back</a>";
    echo "</div>";
    echo "</nav>";

    echo "<style>
        .navbar { width: 100%; padding: 15px 25px; box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05); position: fixed; top: 0; left: 0; z-index: 1000; display: flex; align-items: center; justify-content: space-between; height: 70px; box-sizing: border-box; }
        .hamburger { display: flex; flex-direction: column; justify-content: space-between; width: 35px; height: 25px; cursor: pointer; padding: 10px; }
        .hamburger .bar { width: 100%; height: 4px; background-color: #2c3e50; border-radius: 5px; transition: all 0.4s ease; }
        .hamburger.active .bar:nth-child(1) { transform: translateY(10px) rotate(45deg); }
        .hamburger.active .bar:nth-child(2) { opacity: 0; }
        .hamburger.active .bar:nth-child(3) { transform: translateY(-10px) rotate(-45deg); }
        .nav-menu { display: none; position: absolute; top: 70px; left: 0; width: 100%; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); flex-direction: column; gap: 5px; padding: 15px 0; box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2); transition: all 0.4s ease; }
        .nav-link { color: #ecf0f1; text-decoration: none; font-family: 'Roboto', sans-serif; font-size: 18px; font-weight: 600; padding: 15px 20px; transition: all 0.3s ease; width: 100%; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .nav-link:last-child { border-bottom: none; }
        .nav-link:hover { background-color: #3498db; color: #fff; transform: translateX(5px); }
        .nav-link:active { background-color: #2980b9; transform: translateX(0); }
        .nav-menu.active { display: flex; }
        body { margin: 0; padding-top: 70px; font-family: 'Roboto', sans-serif; }
    </style>";

    echo "<script>
        function toggleMenu() {
            const menu = document.getElementById('navMenu');
            const hamburger = document.querySelector('.hamburger');
            menu.classList.toggle('active');
            hamburger.classList.toggle('active');
        }
        window.addEventListener('resize', function() {
            const menu = document.getElementById('navMenu');
            const hamburger = document.querySelector('.hamburger');
            menu.style.display = '';
            hamburger.style.display = 'flex';
        });
        window.addEventListener('load', function() {
            const hamburger = document.querySelector('.hamburger');
            hamburger.style.display = 'flex';
        });
    </script>";
}

// Team Metrics and Standings Functions
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

function getStandingsData($standings_data) {
    $standings = [];
    foreach ($standings_data['standings'][0]['table'] as $team) {
        $team_name = $team['team']['name'];
        $standings[$team_name] = [
            'position' => $team['position'],
            'goal_difference' => $team['goalDifference'],
            'points' => $team['points'],
            'goals_scored' => $team['goalsFor']
        ];
    }
    return $standings;
}

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
        return ['decision' => "Home Win", 'reason' => "Home team is higher in the table and in better form."];
    } elseif ($home_position > $away_position && $home_form_weight < $away_form_weight) {
        return ['decision' => "Away Win", 'reason' => "Away team is higher in the table and in better form."];
    } elseif ($home_gd > $away_gd && $home_gs > $away_gs) {
        return ['decision' => "Home Win", 'reason' => "Home team has a stronger goal difference and scoring record."];
    } elseif ($home_gd < $away_gd && $home_gs < $away_gs) {
        return ['decision' => "Away Win", 'reason' => "Away team has a stronger goal difference and scoring record."];
    } elseif ($home_points > $away_points) {
        return ['decision' => "Home Win", 'reason' => "Home team has more points in the standings."];
    } elseif ($home_points < $away_points) {
        return ['decision' => "Away Win", 'reason' => "Away team has more points in the standings."];
    } else {
        return ['decision' => "Draw", 'reason' => "Teams are evenly matched based on current data."];
    }
}

function calculateRecentFormWeight($recent_form) {
    $form_weights = ['W' => 3, 'D' => 1, 'L' => 0];
    $total_weight = 0;
    for ($i = 0; $i < strlen($recent_form); $i++) {
        $match_result = strtoupper($recent_form[$i]);
        $total_weight += $form_weights[$match_result] ?? 0;
    }
    return strlen($recent_form) > 0 ? $total_weight / strlen($recent_form) : 0;
}

function calculateHomeAwayAdvantage($fixtures_data) {
    $home_points = $away_points = $total_home_matches = $total_away_matches = 0;
    foreach ($fixtures_data['matches'] as $match) {
        if ($match['status'] === 'FINISHED' && isset($match['score']['fullTime']['home'], $match['score']['fullTime']['away'])) {
            $home_score = $match['score']['fullTime']['home'];
            $away_score = $match['score']['fullTime']['away'];
            if ($home_score > $away_score) $home_points += 3;
            elseif ($home_score < $away_score) $away_points += 3;
            else {$home_points += 1; $away_points += 1;}
            $total_home_matches++;
            $total_away_matches++;
        }
    }
    if ($total_home_matches == 0 || $total_away_matches == 0) {
        return ['home_advantage' => 1.2, 'away_advantage' => 1.0];
    }
    $avg_home_points = $home_points / $total_home_matches;
    $avg_away_points = $away_points / $total_away_matches;
    return [
        'home_advantage' => $avg_home_points / $avg_away_points,
        'away_advantage' => $avg_away_points / $avg_home_points
    ];
}

function predictMatch($home_metrics, $away_metrics, $advantages) {
    $home_advantage = $advantages['home_advantage'];
    $away_advantage = $advantages['away_advantage'];
    $home_recent_form_weight = calculateRecentFormWeight($home_metrics['recent_form'] ?? '');
    $away_recent_form_weight = calculateRecentFormWeight($away_metrics['recent_form'] ?? '');

    $home_score = ($home_metrics['win_ratio'] * 1.3) + ($home_metrics['avg_goals_scored'] * 1.2) 
                - ($home_metrics['avg_goals_conceded'] * 0.8) + ($home_recent_form_weight * 0.7) + $home_advantage;
    $away_score = ($away_metrics['win_ratio'] * 1.3) + ($away_metrics['avg_goals_scored'] * 1.2) 
                - ($away_metrics['avg_goals_conceded'] * 0.8) + ($away_recent_form_weight * 0.7) + $away_advantage;

    $score_difference = $home_score - $away_score;
    if ($score_difference > 0.8) return "Win for Home";
    elseif ($score_difference < -0.8) return "Win for Away";
    else return "Draw";
}

function predictGoals($home_metrics, $away_metrics, $advantages) {
    $home_advantage = $advantages['home_advantage'];
    $away_advantage = $advantages['away_advantage'];
    $home_recent_form_weight = calculateRecentFormWeight($home_metrics['recent_form'] ?? '');
    $away_recent_form_weight = calculateRecentFormWeight($away_metrics['recent_form'] ?? '');

    $home_score = ($home_metrics['win_ratio'] * 1.3) + ($home_metrics['avg_goals_scored'] * 1.2) 
                - ($home_metrics['avg_goals_conceded'] * 0.8) + ($home_recent_form_weight * 0.7) + $home_advantage;
    $away_score = ($away_metrics['win_ratio'] * 1.3) + ($away_metrics['avg_goals_scored'] * 1.2) 
                - ($away_metrics['avg_goals_conceded'] * 0.8) + ($away_recent_form_weight * 0.7) + $away_advantage;

    return [
        'home_goals' => max(0, round($home_score)),
        'away_goals' => max(0, round($away_score))
    ];
}

function getLast6Matches($team_name, $fixtures) {
    $results = [];
    $fixtures = array_reverse($fixtures);
    foreach ($fixtures as $match) {
        if (strcasecmp($match['homeTeam']['name'], $team_name) === 0 || strcasecmp($match['awayTeam']['name'], $team_name) === 0) {
            if ($match['status'] === 'FINISHED' && isset($match['score']['fullTime']['home'], $match['score']['fullTime']['away'])) {
                $home_score = $match['score']['fullTime']['home'];
                $away_score = $match['score']['fullTime']['away'];
                if (strcasecmp($match['homeTeam']['name'], $team_name) === 0) {
                    $result = $home_score > $away_score ? 'W' : ($home_score < $away_score ? 'L' : 'D');
                    $color = $home_score > $away_score ? 'green' : ($home_score < $away_score ? 'red' : 'blue');
                } else {
                    $result = $away_score > $home_score ? 'W' : ($away_score < $home_score ? 'L' : 'D');
                    $color = $away_score > $home_score ? 'green' : ($away_score < $home_score ? 'red' : 'blue');
                }
                $results[] = ['result' => $result, 'color' => $color];
            }
        }
        if (count($results) >= 6) break;
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
            case 'yesterday': if ($match_date == $yesterday) $filtered_matches[] = $match; break;
            case 'today': if ($match_date == $today) $filtered_matches[] = $match; break;
            case 'tomorrow': if ($match_date == $tomorrow) $filtered_matches[] = $match; break;
            case 'this_week': if ($match_date >= $start_of_week && $match_date <= $end_of_week) $filtered_matches[] = $match; break;
            case 'last_week':
                $last_week_start = (clone $now)->modify('-1 week')->modify('last Monday')->format('Y-m-d');
                $last_week_end = (clone $now)->modify('-1 week')->modify('next Sunday')->format('Y-m-d');
                if ($match_date >= $last_week_start && $match_date <= $last_week_end) $filtered_matches[] = $match; break;
            case 'this_month': if ($match_date >= $start_of_month && $match_date <= $end_of_month) $filtered_matches[] = $match; break;
            case 'last_month': if ($match_date >= $start_of_last_month && $match_date <= $end_of_last_month) $filtered_matches[] = $match; break;
            case 'custom': if ($start_date && $end_date && $match_date >= $start_date && $match_date <= $end_date) $filtered_matches[] = $match; break;
            default: $filtered_matches[] = $match; break;
        }
    }
    return $filtered_matches;
}

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

function convertToEAT($utcDate) {
    $date = new DateTime($utcDate, new DateTimeZone('UTC'));
    $date->setTimezone(new DateTimeZone('Africa/Nairobi'));
    return $date->format('Y-m-d H:i:s');
}

// Main execution
$state = $_SESSION['api_state'];
initializeData($api_key, $state);

// Get selected competition
$selected_competition = isset($_GET['competition']) ? $_GET['competition'] : 'PL';

if ($selected_competition) {
    $standings_data = $_SESSION['competition_data'][$selected_competition]['standings'] ?? null;
    $fixtures_data = $_SESSION['competition_data'][$selected_competition]['matches'] ?? null;

    if (!$standings_data || !$fixtures_data) {
        $state['queue'][] = "{$base_url}competitions/$selected_competition/standings";
        $state['queue'][] = "{$base_url}competitions/$selected_competition/matches";
        processQueue($state, $api_key);
        $standings_data = $_SESSION['competition_data'][$selected_competition]['standings'] ?? null;
        $fixtures_data = $_SESSION['competition_data'][$selected_competition]['matches'] ?? null;
    }

    $team_metrics = $standings_data ? getTeamMetrics($standings_data) : null;
    $standings = $standings_data ? getStandingsData($standings_data) : null;
}

// UI Rendering
echo "<h1>Football Match Predictions</h1>";
echo '<link rel="preconnect" href="https://api.football-data.org">';
echo '<link rel="stylesheet" type="text/css" href="css/liv.css">';
echo '<link rel="stylesheet" type="text/css" href="css/network-status.css">';

echo "<?php include('search-form.php'); ?>";

// Status Light
echo '<!-- Status Light -->
<div id="status_light" class="status">
    <div class="status_light">
        <div class="status_light_ring"></div>
        <div class="status_light_led"></div>
    </div>
    <span class="status_message">Processing</span>
</div>';

echo '<style>
/* Status light container */
.status {
    position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255, 255, 255, 0.9);
    display: none; justify-content: center; align-items: center; z-index: 1000; flex-direction: column;
    text-align: center; font-family: "Roboto", sans-serif; font-size: 1.2em; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: background-color 0.3s ease, transform 0.3s ease;
}
.status_light { position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 10px; transition: transform 0.3s ease-in-out; }
.status_light_ring { width: 70px; height: 70px; border: 6px solid #3498db; border-radius: 50%; position: absolute;
    background: linear-gradient(45deg, rgba(52, 152, 219, 0.3), rgba(142, 68, 173, 0.3)); animation: pulse 1.5s infinite ease-in-out, rotateRing 4s infinite linear;
    box-shadow: 0 0 15px rgba(52, 152, 219, 0.5); }
.status_light_led { width: 25px; height: 25px; background-color: #fff; border-radius: 50%; position: absolute; z-index: 1;
    box-shadow: 0 0 10px rgba(255, 255, 255, 0.6); animation: glow 1.5s infinite alternate ease-in-out; }
@keyframes pulse {
    0% { transform: scale(1); opacity: 1; box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.4); }
    50% { transform: scale(1.2); opacity: 0.8; box-shadow: 0 0 20px 10px rgba(52, 152, 219, 0.2); }
    100% { transform: scale(1); opacity: 1; box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.4); }
}
@keyframes rotateRing { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
@keyframes glow {
    0% { background-color: #fff; box-shadow: 0 0 10px rgba(255, 255, 255, 0.6); }
    100% { background-color: #3498db; box-shadow: 0 0 15px rgba(52, 152, 219, 0.8); }
}
.status_message { margin-top: 20px; color: #2c3e50; font-weight: 600; font-size: 1.4em; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); animation: fadeIn 1s ease-in-out; }
@keyframes fadeIn { 0% { opacity: 0; transform: translateY(-20px); } 100% { opacity: 1; transform: translateY(0); } }
.status_message:after { content: ""; display: inline-block; width: 6px; height: 6px; border-radius: 50%; background-color: #3498db;
    margin-left: 6px; animation: dots 1.5s infinite steps(1) forwards; }
@keyframes dots { 0% { content: "."; } 33% { content: ".."; } 66% { content: "..."; } 100% { content: "."; } }
.status:hover .status_light_ring { box-shadow: 0 0 20px rgba(52, 152, 219, 0.8); }
.status:hover .status_light_led { background-color: #3498db; box-shadow: 0 0 20px rgba(52, 152, 219, 0.8); }
</style>';

echo '<script>
function showStatusLight() { document.getElementById("status_light").style.display = "flex"; }
document.addEventListener("DOMContentLoaded", function () {
    const competitionForm = document.getElementById("competitionForm");
    const searchForm = document.getElementById("searchForm");
    if (competitionForm) competitionForm.addEventListener("submit", showStatusLight);
    if (searchForm) searchForm.addEventListener("submit", showStatusLight);
});
window.onload = function () { document.getElementById("status_light").style.display = "none"; };
</script>';

// Competition dropdown
$default_competition = 'PL';
$default_date_filter = 'today';
$selected_competition = isset($_GET['competition']) ? $_GET['competition'] : $default_competition;
$selected_date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : $default_date_filter;
$selected_start_date = isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '';
$selected_end_date = isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '';

echo '<form id="searchForm" method="GET" action="">';
echo '<label for="competition">Select Competition:</label>
      <select name="competition" id="competition">
          <option value="">-- Select Competition --</option>';
foreach ($_SESSION['competitions'] ?? [] as $competition) {
    $competition_id = $competition['code'];
    $competition_name = $competition['name'];
    echo "<option value='$competition_id' " . ($selected_competition == $competition_id ? 'selected' : '') . ">$competition_name</option>";
}
if (!$selected_competition || !in_array($selected_competition, array_column($_SESSION['competitions'] ?? [], 'code'))) {
    echo "<option value='$default_competition' selected>Premier League</option>";
}
echo '</select>';

echo '<label for="date_filter">Filter by Date:</label>
      <select name="date_filter" id="date_filter" onchange="toggleCustomRange(this.value)">';
$date_filters = [
    'all' => 'All Matches', 'yesterday' => 'Yesterday', 'today' => 'Today', 'tomorrow' => 'Tomorrow',
    'this_week' => 'This Week', 'last_week' => 'Last Week', 'this_month' => 'This Month', 'last_month' => 'Last Month', 'custom' => 'Custom Range'
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
echo '<input type="submit" value="Search" /></form>';

echo "<script>
function toggleCustomRange(value) {
    const customRange = document.getElementById('custom_range');
    customRange.style.display = value === 'custom' ? 'block' : 'none';
}
</script>";

if ($selected_competition && $fixtures_data) {
    $date_filter = $_GET['date_filter'] ?? 'all';
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    $filtered_matches = filterMatchesByDate($fixtures_data['matches'], $date_filter, $start_date, $end_date);

    echo "<h2>" . ($_SESSION['competitions'][array_search($selected_competition, array_column($_SESSION['competitions'], 'code'))]['name'] ?? 'Selected Competition') . "</h2>";

    if (empty($filtered_matches)) {
        echo "<p style='color: red; font-weight: bold;'>No matches found for the selected date range.</p>";
    } else {
        echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">';
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
    const table = document.querySelector("table");
    setTimeout(() => {
        html2canvas(table, { scale: 3, useCORS: true, backgroundColor: "#ffffff", width: table.scrollWidth, height: table.scrollHeight })
            .then(canvas => callback(canvas))
            .catch(error => console.error("Error capturing table:", error));
    }, 200);
}

function startCountdown(action, duration) {
    let countdown = duration;
    const countdownElement = document.createElement("div");
    countdownElement.style.cssText = "position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: rgba(0, 0, 0, 0.8); color: #fff; padding: 20px; border-radius: 10px; z-index: 1000; font-size: 24px; font-family: Arial, sans-serif;";
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
    feedbackElement.style.cssText = "position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: " + (isSuccess ? "rgba(0, 128, 0, 0.8)" : "rgba(255, 0, 0, 0.8)") + "; color: #fff; padding: 20px; border-radius: 10px; z-index: 1000; font-size: 20px; font-family: Arial, sans-serif; text-align: center;";
    feedbackElement.textContent = message;
    document.body.appendChild(feedbackElement);
    setTimeout(() => document.body.removeChild(feedbackElement), 3000);
}

document.getElementById("shareButton").addEventListener("click", async function() {
    startCountdown(() => {
        captureTable(canvas => {
            canvas.toBlob(blob => {
                if (!blob) { showFeedbackMessage("Failed to create image.", false); return; }
                const file = new File([blob], getFormattedTimestamp(), { type: "image/png" });
                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    navigator.share({ files: [file], title: "Captured Table", text: "Here is a table snapshot" })
                        .then(() => showFeedbackMessage("Table shared successfully!", true))
                        .catch(error => { console.error("Error sharing:", error); showFeedbackMessage("Failed to share table.", false); });
                } else {
                    showFeedbackMessage("Web Share API not supported.", false);
                }
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

        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>Date (EAT)</th><th>Home Team</th><th>Away Team</th><th>Status</th><th>Score</th><th>Prediction</th><th>Match Result</th><th>Matchday</th></tr>";

        foreach ($filtered_matches as $match) {
            $advantages = calculateHomeAwayAdvantage($fixtures_data);
            $date_eat = convertToEAT($match['utcDate']);
            $home_team = $match['homeTeam']['name'];
            $away_team = $match['awayTeam']['name'];
            $status = $match['status'];
            $score = isset($match['score']['fullTime']) ? "{$match['score']['fullTime']['home']} - {$match['score']['fullTime']['away']}" : "N/A";
            $venue = isset($match['matchday']) ? $match['matchday'] : "Unknown";

            $last6_home = getLast6Matches($home_team, $fixtures_data['matches']);
            $last6_away = getLast6Matches($away_team, $fixtures_data['matches']);
            $home_crest = $team_metrics[$home_team]['crest'] ?? '';
            $away_crest = $team_metrics[$away_team]['crest'] ?? '';

            $prediction_data = getPredictionSuggestion($home_team, $away_team, $standings, $last6_home, $last6_away);
            $decision = $prediction_data['decision'];
            $reason = $prediction_data['reason'];

            $home_position = $standings[$home_team]['position'] ?? 'N/A';
            $home_goal_diff = $standings[$home_team]['goal_difference'] ?? 'N/A';
            $home_points = $standings[$home_team]['points'] ?? 'N/A';
            $home_goals_scored = $standings[$home_team]['goals_scored'] ?? 'N/A';
            $away_position = $standings[$away_team]['position'] ?? 'N/A';
            $away_goal_diff = $standings[$away_team]['goal_difference'] ?? 'N/A';
            $away_points = $standings[$away_team]['points'] ?? 'N/A';
            $away_goals_scored = $standings[$away_team]['goals_scored'] ?? 'N/A';

            $prediction = $match_result = $predicted_goals = '';
            if (isset($team_metrics[$home_team]) && isset($team_metrics[$away_team])) {
                $home_metrics = $team_metrics[$home_team];
                $away_metrics = $team_metrics[$away_team];
                $prediction = predictMatch($home_metrics, $away_metrics, $advantages);
                $predicted_goals_data = predictGoals($home_metrics, $away_metrics, $advantages);
                $predicted_goals = "{$predicted_goals_data['home_goals']} - {$predicted_goals_data['away_goals']}";

                if ($status == 'FINISHED' && $score != 'N/A') {
                    list($score_home, $score_away) = explode(" - ", $score);
                    $match_result = (($prediction == "Win for Home" && $score_home > $score_away) || 
                                    ($prediction == "Win for Away" && $score_away > $score_home) || 
                                    ($prediction == "Draw" && $score_home == $score_away)) 
                                    ? "<span style='color: green;'>✓</span>" 
                                    : "<span style='color: red;'>✕</span>";
                }
            }

            echo "<tr>
                <td>$date_eat</td>
                <td><div style='display: flex; align-items: center; gap: 8px;'>
                    <img src='$home_crest' alt='$home_team' style='height: 30px; width: 30px;' />
                    <a href='#' style='text-decoration: none; color: inherit;'>
                        <span style='font-weight: bold; font-size: 14px; color: #2c3e50;'>$home_team</span>
                        <span style='font-size: 12px; color: #7f8c8d; margin-left: 4px; font-style: italic;'>($last6_home)</span>
                        <div style='font-size: 10px; color: #555; margin-top: 2px; white-space: nowrap;'>Pos: $home_position | GD: $home_goal_diff | PTS: $home_points | GS: $home_goals_scored</div>
                        <div style='font-size: 5px; color: #777; font-style: italic; margin-top: 2px;'><strong>$decision</strong><br><strong>$reason</strong></div>
                    </a></div></td>
                <td><div style='display: flex; align-items: center; gap: 8px;'>
                    <img src='$away_crest' alt='$away_team' style='height: 30px; width: 30px;' />
                    <a href='#' style='text-decoration: none; color: inherit;'>
                        <span style='font-weight: bold; font-size: 14px; color: #2980b9;'>$away_team</span>
                        <span style='font-size: 12px; color: #7f8c8d; margin-left: 4px; font-style: italic;'>($last6_away)</span>
                        <div style='font-size: 10px; color: #555; margin-top: 2px; white-space: nowrap;'>Pos: $away_position | GD: $away_goal_diff | PTS: $away_points | GS: $away_goals_scored</div>
                        <div style='font-size: 5px; color: #777; font-style: italic; margin-top: 2px;'><strong>$decision</strong><br><strong>$reason</strong></div>
                    </a></div></td>
                <td>$status</td>
                <td>$score</td>
                <td>$prediction<div style='font-size: 12px; color: gray; font-style: italic; margin-top: 5px;'>Predicted Goals: $predicted_goals</div></td>
                <td>$match_result</td>
                <td>$venue</td>
            </tr>";
        }
        echo "</table>";
    }
}

echo "<script src='network-status.js'></script>";
echo "<?php include 'back-to-top.php'; ?>";

$_SESSION['api_state'] = $state;
?>

<?php
// Football-Data API Configuration
$api_key = "d2ef1a157a0d4c83ba4023d1fbd28b5c"; // Replace with your API key
$competitions_url = "https://api.football-data.org/v4/competitions"; // List all competitions

// Start session to store competitions and their data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to fetch data from the API
function fetchAPI($url, $api_key) {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "X-Auth-Token: $api_key"
        ],
    ]);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE); // Get the HTTP response code
    curl_close($curl);

    if ($http_code != 200) { // Check if the HTTP code is not OK (200)
        header('Location: error');
        exit;
    }

    return json_decode($response, true);
}

// Fetch all competitions only once and store in session
if (!isset($_SESSION['competitions'])) {
    $_SESSION['competitions'] = fetchAPI($competitions_url, $api_key)['competitions'];
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
            'crest' => $team['team']['crest'], // Add team crest
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

// Helper function to calculate a numeric weight for recent form
function calculateRecentFormWeight($recent_form) {
    $form_weights = [
        'W' => 3,  // Win = 3 points
        'D' => 1,  // Draw = 1 point
        'L' => 0   // Loss = 0 points
    ];
    $total_weight = 0;

    // Calculate weight based on the recent form string (e.g., "WWLDLD")
    for ($i = 0; $i < strlen($recent_form); $i++) {
        $match_result = strtoupper($recent_form[$i]);
        $total_weight += isset($form_weights[$match_result]) ? $form_weights[$match_result] : 0;
    }

    // Average weight across last 6 matches
    return strlen($recent_form) > 0 ? $total_weight / strlen($recent_form) : 0;
}

function calculateHomeAwayAdvantage($fixtures_data) {
    $home_points = 0;
    $away_points = 0;
    $total_home_matches = 0;
    $total_away_matches = 0;

    foreach ($fixtures_data['matches'] as $match) {
        if ($match['status'] === 'FINISHED' && isset($match['score']['fullTime']['home'], $match['score']['fullTime']['away'])) {
            $home_score = $match['score']['fullTime']['home'];
            $away_score = $match['score']['fullTime']['away'];

            // Calculate points for home and away teams
            if ($home_score > $away_score) {
                $home_points += 3; // Home win
            } elseif ($home_score < $away_score) {
                $away_points += 3; // Away win
            } else {
                $home_points += 1; // Draw
                $away_points += 1; // Draw
            }

            $total_home_matches++;
            $total_away_matches++;
        }
    }

    // Avoid division by zero
    if ($total_home_matches == 0 || $total_away_matches == 0) {
        return ['home_advantage' => 1.2, 'away_advantage' => 1.0]; // Default values
    }

    // Calculate average points per match for home and away teams
    $avg_home_points = $home_points / $total_home_matches;
    $avg_away_points = $away_points / $total_away_matches;

    // Home and away advantage factors
    $home_advantage = $avg_home_points / $avg_away_points;
    $away_advantage = $avg_away_points / $avg_home_points; // Inverse of home advantage

    return ['home_advantage' => $home_advantage, 'away_advantage' => $away_advantage];
}

// Function to predict match outcome with revised weights
function predictMatch($home_metrics, $away_metrics, $advantages) {
    $home_advantage = $advantages['home_advantage'];
    $away_advantage = $advantages['away_advantage'];

    // Calculate recent form weights
    $home_recent_form_weight = calculateRecentFormWeight($home_metrics['recent_form'] ?? '');
    $away_recent_form_weight = calculateRecentFormWeight($away_metrics['recent_form'] ?? '');

    // Calculate home and away scores with both advantages
    $home_score = ($home_metrics['win_ratio'] * 1.3)  
                + ($home_metrics['avg_goals_scored'] * 1.2) 
                - ($home_metrics['avg_goals_conceded'] * 0.8)
                + ($home_recent_form_weight * 0.7)
                + $home_advantage; // Include home advantage

    $away_score = ($away_metrics['win_ratio'] * 1.3) 
                + ($away_metrics['avg_goals_scored'] * 1.2) 
                - ($away_metrics['avg_goals_conceded'] * 0.8)
                + ($away_recent_form_weight * 0.7)
                + $away_advantage; // Include away advantage

    // Adjust thresholds with added consideration for draws
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

    // Calculate recent form weights
    $home_recent_form_weight = calculateRecentFormWeight($home_metrics['recent_form'] ?? '');
    $away_recent_form_weight = calculateRecentFormWeight($away_metrics['recent_form'] ?? '');

    // Calculate home and away scores with both advantages
    $home_score = ($home_metrics['win_ratio'] * 1.3)  
                + ($home_metrics['avg_goals_scored'] * 1.2) 
                - ($home_metrics['avg_goals_conceded'] * 0.8)
                + ($home_recent_form_weight * 0.7)
                + $home_advantage; // Include home advantage

    $away_score = ($away_metrics['win_ratio'] * 1.3) 
                + ($away_metrics['avg_goals_scored'] * 1.2) 
                - ($away_metrics['avg_goals_conceded'] * 0.8)
                + ($away_recent_form_weight * 0.7)
                + $away_advantage; // Include away advantage

    // Use the scores to predict goals
    $predicted_home_goals = max(0, round($home_score)); // Round to whole number
    $predicted_away_goals = max(0, round($away_score)); // Round to whole number

    return [
        'home_goals' => $predicted_home_goals,
        'away_goals' => $predicted_away_goals
    ];
}

// Function to get last 6 matches for a team
function getLast6Matches($team_name, $fixtures) {
    $results = []; // To store the results of the last 6 matches
    
    // Reverse the fixtures to get the latest matches first
    $fixtures = array_reverse($fixtures);
    
    foreach ($fixtures as $match) {
        // Check if the team is part of this match
        if (strcasecmp($match['homeTeam']['name'], $team_name) === 0 || strcasecmp($match['awayTeam']['name'], $team_name) === 0) {
            // Ensure the match is finished and has a valid score
            if ($match['status'] === 'FINISHED' && isset($match['score']['fullTime']['home'], $match['score']['fullTime']['away'])) {
                $home_score = $match['score']['fullTime']['home'];
                $away_score = $match['score']['fullTime']['away'];

                // Determine if the team won, lost, or drew
                if (strcasecmp($match['homeTeam']['name'], $team_name) === 0) {
                    // Team is the home team
                    if ($home_score > $away_score) {
                        $results[] = ['result' => 'W', 'color' => 'green'];
                    } elseif ($home_score < $away_score) {
                        $results[] = ['result' => 'L', 'color' => 'red'];
                    } else {
                        $results[] = ['result' => 'D', 'color' => 'blue'];
                    }
                } else {
                    // Team is the away team
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

        // Stop once we have 6 results
        if (count($results) >= 6) {
            break;
        }
    }

    // Reverse the results array to have the most recent match at the end
    $results = array_reverse($results);

    // Format results for display without spaces
    if (!empty($results)) {
        $formatted_results = '';
        foreach ($results as $index => $result) {
            $style = $index === count($results) - 1 ? 'font-weight: bold; text-decoration: underline;' : ''; // Highlight the latest match
            $formatted_results .= "<span style='color: {$result['color']}; $style; display: inline-block; line-height: 1; padding: 0; margin: 0;'>{$result['result']}</span>";
        }
        return $formatted_results;
    }

    // Return "N/A" if no matches found
    return "N/A";
}

// Function to calculate date range filter (Yesterday, Today, Tomorrow)
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
                $filtered_matches[] = $match; // Default to all matches
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
    $date->setTimezone(new DateTimeZone('Africa/Nairobi')); // Nairobi is in EAT (UTC+3)
    return $date->format('Y-m-d H:i:s');
}

// Get selected competition and fetch its data
$selected_competition = isset($_GET['competition']) ? $_GET['competition'] : 'PL'; // Default to Premier League

// Fetch data for the selected competition
if ($selected_competition) {
    $competition_id = $selected_competition;
    $standings_url = "https://api.football-data.org/v4/competitions/$competition_id/standings";
    $fixtures_url = "https://api.football-data.org/v4/competitions/$competition_id/matches";
    $standings_data = fetchAPI($standings_url, $api_key);
    $fixtures_data = fetchAPI($fixtures_url, $api_key);
    $team_metrics = getTeamMetrics($standings_data);
    $standings = getStandingsData($standings_data); // Get standings data
} else {
    $fixtures_data = null;
    $team_metrics = null;
    $standings = null;
}

// Display the competition dropdown
echo "<h1>Football Match Predictions</h1>";

// Add link to external CSS file
echo '<link rel="stylesheet" type="text/css" href="css/liv.css">';
echo '<link rel="stylesheet" type="text/css" href="css/network-status.css">';

echo "<?php include('search-form.php'); ?>";

// Retrieve selected values from the query string
$selected_competition = isset($_GET['competition']) ? $_GET['competition'] : '';
$selected_date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$selected_start_date = isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '';
$selected_end_date = isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : '';

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
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.8);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    flex-direction: column;
    text-align: center;
    font-family: "Roboto", sans-serif;
    font-size: 1.2em;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: background-color 0.3s ease, transform 0.3s ease;
}

/* Light ring and LED */
.status_light {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin-bottom: 10px;
    transition: transform 0.3s ease-in-out;
}

.status_light_ring {
    width: 70px;
    height: 70px;
    border: 6px solid #3498db;
    border-radius: 50%;
    position: absolute;
    background: linear-gradient(45deg, rgba(52, 152, 219, 0.3), rgba(142, 68, 173, 0.3));
    animation: pulse 1.5s infinite, rotateRing 4s infinite linear;
    box-shadow: 0 0 10px rgba(52, 152, 219, 0.4);
}

.status_light_led {
    width: 25px;
    height: 25px;
    background-color: #fff;
    border-radius: 50%;
    position: absolute;
    z-index: 1;
    box-shadow: 0 0 10px rgba(255, 255, 255, 0.6);
}

@keyframes pulse {
    0% {
        transform: scale(1);
        opacity: 1;
    }
    100% {
        transform: scale(1.5);
        opacity: 0;
    }
}

@keyframes rotateRing {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

/* Status message */
.status_message {
    margin-top: 20px;
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.4em;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    animation: fadeIn 1s ease-in-out;
}

@keyframes fadeIn {
    0% {
        opacity: 0;
        transform: translateY(-20px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Dot animation for status message */
.status_message:after {
    content: "";
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #3498db;
    margin-left: 6px;
    animation: dots 1.5s infinite steps(1) forwards;
}

@keyframes dots {
    0% {
        content: ".";
    }
    33% {
        content: "..";
    }
    66% {
        content: "...";
    }
    100% {
        content: ".";
    }
}

/* Add a glowing effect on hover for the status light */
.status:hover .status_light_ring {
    box-shadow: 0 0 15px rgba(52, 152, 219, 0.5);
}

.status:hover .status_light_led {
    background-color: #3498db;
    box-shadow: 0 0 15px rgba(52, 152, 219, 0.6);
}
</style>';

echo '<script>
// Function to show the status light
function showStatusLight() {
    document.getElementById("status_light").style.display = "flex";
}

// Attach event listeners to forms
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

// Hide the status light after page load (optional)
window.onload = function () {
    const statusLight = document.getElementById("status_light");
    if (statusLight) {
        statusLight.style.display = "none";
    }
};
</script>';

// Competition dropdown
echo '<form id="searchForm" method="GET" action="">';
// Default selected competition and date filter values
$default_competition = 'PL';
$default_date_filter = 'all';

// Set selected values from GET request or defaults
$selected_competition = isset($_GET['competition']) ? $_GET['competition'] : $default_competition;
$selected_date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : $default_date_filter;
$selected_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$selected_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Competition dropdown
echo '<label for="competition">Select Competition:</label>
      <select name="competition" id="competition">
          <option value="">-- Select Competition --</option>';

foreach ($_SESSION['competitions'] as $competition) {
    $competition_id = $competition['code'];
    $competition_name = $competition['name'];
    echo "<option value='$competition_id' " . ($selected_competition == $competition_id ? 'selected' : '') . ">$competition_name</option>";
}

// Add default option if not already selected
if (!$selected_competition || !in_array($selected_competition, array_column($_SESSION['competitions'], 'code'))) {
    echo "<option value='$default_competition' selected>Premier League</option>";
}

echo '</select>';

// Date filter dropdown
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

// Custom date range inputs
echo '<div id="custom_range" style="' . ($selected_date_filter == 'custom' ? 'display: block;' : 'display: none;') . '">
          <label for="start_date">Start Date:</label>
          <input type="date" name="start_date" id="start_date" value="' . $selected_start_date . '">
          <label for="end_date">End Date:</label>
          <input type="date" name="end_date" id="end_date" value="' . $selected_end_date . '">
      </div>';

// Submit button
echo '<input type="submit" value="Search" />
      </form>';

// JavaScript for toggling the custom date range
echo "<script>
function toggleCustomRange(value) {
    const customRange = document.getElementById('custom_range');
    customRange.style.display = value === 'custom' ? 'block' : 'none';
}
</script>";

if ($selected_competition && $fixtures_data) {
    // Filter matches by date
    $date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'all';
    $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

    $filtered_matches = filterMatchesByDate($fixtures_data['matches'], $date_filter, $start_date, $end_date);

    echo "<h2>" . $_SESSION['competitions'][array_search($selected_competition, array_column($_SESSION['competitions'], 'code'))]['name'] . "</h2>";

    if (empty($filtered_matches)) {
        // Display message if no matches found
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

                  // Make sure the table is fully loaded before capturing
                  setTimeout(() => {
                      html2canvas(table, {
                          scale: 3, // Increase scale for better quality
                          useCORS: true, // Fixes cross-origin issues if images are inside the table
                          backgroundColor: "#ffffff", // Ensures a white background
                          width: table.scrollWidth, // Capture full table width
                          height: table.scrollHeight // Capture full table height
                      }).then(canvas => callback(canvas));
                  }, 200); // Small delay to ensure rendering is done
              }

              document.getElementById("shareButton").addEventListener("click", async function() {
                  captureTable(canvas => {
                      canvas.toBlob(blob => {
                          const fileName = getFormattedTimestamp();
                          const file = new File([blob], fileName, { type: "image/png" });

                          if (navigator.canShare && navigator.canShare({ files: [file] })) {
                              navigator.share({
                                  files: [file],
                                  title: "Captured Table",
                                  text: "Here is a table snapshot"
                              }).catch(error => console.error("Error sharing:", error));
                          } else {
                              alert("Web Share API not supported or cannot share images.");
                          }
                      }, "image/png");
                  });
              });

              document.getElementById("downloadButton").addEventListener("click", function() {
                  captureTable(canvas => {
                      const link = document.createElement("a");
                      link.href = canvas.toDataURL("image/png");
                      link.download = getFormattedTimestamp();
                      link.click();
                  });
              });
              </script>';

        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr>
                <th>Date (EAT)</th>
                <th>Home Team</th>
                <th>Away Team</th>
                <th>Status</th>
                <th>Score</th>
                <th>Prediction</th>
                <th>Match Result</th>
                <th>Matchday</th>
              </tr>";

        foreach ($filtered_matches as $match) {
            // Calculate home and away advantage dynamically
            $advantages = calculateHomeAwayAdvantage($fixtures_data);
            $date_utc = $match['utcDate'];
            $date_eat = convertToEAT($date_utc); // Convert UTC to EAT
            $home_team = $match['homeTeam']['name'];
            $away_team = $match['awayTeam']['name'];
            $status = $match['status'];
            $score = isset($match['score']['fullTime']) ? "{$match['score']['fullTime']['home']} - {$match['score']['fullTime']['away']}" : "N/A";
            $venue = isset($match['matchday']) ? $match['matchday'] : "Unknown"; // Some APIs may not provide a venue

            // Fetch last 6 matches for home and away teams
            $last6_home = getLast6Matches($match['homeTeam']['name'], $fixtures_data['matches']);
            $last6_away = getLast6Matches($match['awayTeam']['name'], $fixtures_data['matches']);

            // Team crests
            $home_crest = isset($team_metrics[$home_team]['crest']) ? $team_metrics[$home_team]['crest'] : '';
            $away_crest = isset($team_metrics[$away_team]['crest']) ? $team_metrics[$away_team]['crest'] : '';

            // Get standings position, goal difference, points, and goals scored
            $home_position = isset($standings[$home_team]['position']) ? $standings[$home_team]['position'] : 'N/A';
            $home_goal_diff = isset($standings[$home_team]['goal_difference']) ? $standings[$home_team]['goal_difference'] : 'N/A';
            $home_points = isset($standings[$home_team]['points']) ? $standings[$home_team]['points'] : 'N/A';
            $home_goals_scored = isset($standings[$home_team]['goals_scored']) ? $standings[$home_team]['goals_scored'] : 'N/A';
            $away_position = isset($standings[$away_team]['position']) ? $standings[$away_team]['position'] : 'N/A';
            $away_goal_diff = isset($standings[$away_team]['goal_difference']) ? $standings[$away_team]['goal_difference'] : 'N/A';
            $away_points = isset($standings[$away_team]['points']) ? $standings[$away_team]['points'] : 'N/A';
            $away_goals_scored = isset($standings[$away_team]['goals_scored']) ? $standings[$away_team]['goals_scored'] : 'N/A';

            // Check if score matches prediction
            $prediction = '';
            $match_result = '';
            $predicted_goals = ''; // Initialize predicted goals variable
            if (isset($team_metrics[$home_team]) && isset($team_metrics[$away_team])) {
                $home_metrics = $team_metrics[$home_team];
                $away_metrics = $team_metrics[$away_team];
                // Call predictMatch for outcome prediction
                $prediction = predictMatch($home_metrics, $away_metrics, $advantages);

                // Call predictGoals for goal prediction
                $predicted_goals_data = predictGoals($home_metrics, $away_metrics, $advantages);
                $predicted_goals = "{$predicted_goals_data['home_goals']} - {$predicted_goals_data['away_goals']}";

                if ($status == 'FINISHED' && $score != 'N/A') {
                    $score_home = explode(" - ", $score)[0];
                    $score_away = explode(" - ", $score)[1];

                    if (($prediction == "Win for Home" && $score_home > $score_away) || 
                        ($prediction == "Win for Away" && $score_away > $score_home) || 
                        ($prediction == "Draw" && $score_home == $score_away)) {
                        $match_result = "<span style='color: green;'>&#10003;</span>";
                    } else {
                        $match_result = "<span style='color: red;'>&#10005;</span>";
                    }
                }
            }

            echo "<tr>
                <td>$date_eat</td>
                <td>
                    <div style='display: flex; align-items: center; gap: 8px;'>
                        <img src='$home_crest' alt='$home_team' style='height: 30px; width: 30px;' />
                        <a href='#' style='text-decoration: none; color: inherit;'>
                            <span style='font-weight: bold; font-size: 14px; color: #2c3e50;'>$home_team</span>
                            <span style='font-size: 12px; color: #7f8c8d; margin-left: 4px; font-style: italic;'>($last6_home)</span>
                            <div style='font-size: 10px; color: #555; margin-top: 2px; white-space: nowrap;'>Pos: $home_position | GD: $home_goal_diff | PTS: $home_points | GS: $home_goals_scored</div>
                        </a>
                    </div>
                </td>
                <td>
                    <div style='display: flex; align-items: center; gap: 8px;'>
                        <img src='$away_crest' alt='$away_team' style='height: 30px; width: 30px;' />
                        <a href='#' style='text-decoration: none; color: inherit;'>
                            <span style='font-weight: bold; font-size: 14px; color: #2980b9;'>$away_team</span>
                            <span style='font-size: 12px; color: #7f8c8d; margin-left: 4px; font-style: italic;'>($last6_away)</span>
                            <div style='font-size: 10px; color: #555; margin-top: 2px; white-space: nowrap;'>Pos: $away_position | GD: $away_goal_diff | PTS: $away_points | GS: $away_goals_scored</div>
                        </a>
                    </div>
                </td>
                <td>$status</td>
                <td>$score</td>
                <td>$prediction
                    <div style='font-size: 12px; color: gray; font-style: italic; margin-top: 5px;'>Predicted Goals: $predicted_goals</div>
                     <!-- Prediction Section -->
    <div style='text-align: center; font-size: 12px; color: #27ae60; font-weight: bold; margin-top: 5px;'>
        <?php
            function calculate_form_score($form) {
                $form_points = 0;
                $matches = str_split($form);
                foreach ($matches as $match) {
                    if ($match === 'W') $form_points += 3;
                    elseif ($match === 'D') $form_points += 1;
                }
                return $form_points;
            }

            $home_form_score = calculate_form_score($last6_home);
            $away_form_score = calculate_form_score($last6_away);

            if ($home_points > $away_points + 3 && $home_form_score >= $away_form_score) {
                echo "Prediction: $home_team to win";
            } elseif ($away_points > $home_points + 3 && $away_form_score >= $home_form_score) {
                echo "Prediction: $away_team to win";
            } elseif ($home_goal_diff > $away_goal_diff && $home_form_score >= $away_form_score) {
                echo "Prediction: Slight edge for $home_team";
            } elseif ($away_goal_diff > $home_goal_diff && $away_form_score >= $home_form_score) {
                echo "Prediction: Slight edge for $away_team";
            } else {
                echo "Prediction: Draw";
            }
        ?>
    </div>
                </td>
                <td>$match_result</td>
                <td>$venue</td>
            </tr>";
        }

        echo "</table>"; 
    }
}

?>

<script>
function toggleCustomRange(value) {
    const customRange = document.getElementById('custom_range');
    customRange.style.display = (value === 'custom') ? 'block' : 'none';
}
</script>
<script src="network-status.js"></script>
<?php include 'back-to-top.php'; ?>

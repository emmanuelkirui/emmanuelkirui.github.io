<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");

    $apiKey = "d2ef1a157a0d4c83ba4023d1fbd28b5c";

    function apiRequest($endpoint) {
        global $apiKey;
        $url = "https://api.football-data.org/v4/" . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: $apiKey"]);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    if (isset($_GET['action'])) {
        $action = $_GET['action'];

        // Get available leagues
        if ($action === "getLeagues") {
            $data = apiRequest("competitions");
            echo json_encode($data['competitions'] ?? []);
            exit;
        }

        // Get matches based on league & date filter
        if ($action === "getMatches") {
            $league = $_GET['league'] ?? 'PL'; 
            $dateFilter = $_GET['dateFilter'] ?? 'today';

            $today = date("Y-m-d");
            $tomorrow = date("Y-m-d", strtotime("+1 day"));
            $yesterday = date("Y-m-d", strtotime("-1 day"));

            if ($dateFilter == "today") {
                $dateFrom = $dateTo = $today;
            } elseif ($dateFilter == "tomorrow") {
                $dateFrom = $dateTo = $tomorrow;
            } elseif ($dateFilter == "yesterday") {
                $dateFrom = $dateTo = $yesterday;
            } elseif ($dateFilter == "custom") {
                $dateFrom = $_GET['fromDate'] ?? $today;
                $dateTo = $_GET['toDate'] ?? $today;
            } else {
                $dateFrom = $dateTo = $today;
            }

            $data = apiRequest("matches?competitions=$league&dateFrom=$dateFrom&dateTo=$dateTo");

            echo json_encode($data['matches'] ?? []);
            exit;
        }

        // Predict match outcome
        if ($action === "predictMatch") {
            $team1 = $_GET['team1'] ?? "";
            $team2 = $_GET['team2'] ?? "";

            if (!$team1 || !$team2) {
                echo json_encode(["error" => "Teams not selected."]);
                exit;
            }

            $matches = apiRequest("matches?dateFrom=" . date("Y-m-d", strtotime("-30 days")) . "&dateTo=" . date("Y-m-d"));
            $shared_opponents = [];
            $team1_matches = [];
            $team2_matches = [];

            foreach ($matches['matches'] as $match) {
                if ($match['homeTeam']['name'] == $team1 || $match['awayTeam']['name'] == $team1) {
                    $team1_matches[] = $match;
                }
                if ($match['homeTeam']['name'] == $team2 || $match['awayTeam']['name'] == $team2) {
                    $team2_matches[] = $match;
                }
            }

            foreach ($team1_matches as $match1) {
                foreach ($team2_matches as $match2) {
                    if ($match1['homeTeam']['name'] == $match2['homeTeam']['name'] ||
                        $match1['awayTeam']['name'] == $match2['awayTeam']['name']) {
                        $shared_opponents[] = $match1;
                    }
                }
            }

            if (empty($shared_opponents)) {
                echo json_encode(["error" => "No shared opponent data found."]);
                exit;
            }

            $team1_goals = 0;
            $team2_goals = 0;
            $count = count($shared_opponents);

            foreach ($shared_opponents as $match) {
                if ($match['homeTeam']['name'] == $team1) {
                    $team1_goals += $match['score']['fullTime']['home'];
                    $team2_goals += $match['score']['fullTime']['away'];
                } elseif ($match['awayTeam']['name'] == $team1) {
                    $team1_goals += $match['score']['fullTime']['away'];
                    $team2_goals += $match['score']['fullTime']['home'];
                }
            }

            $predicted_score1 = round($team1_goals / $count);
            $predicted_score2 = round($team2_goals / $count);

            echo json_encode([
                "team1" => $team1,
                "team2" => $team2,
                "predicted_score1" => $predicted_score1,
                "predicted_score2" => $predicted_score2
            ]);
            exit;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Football Match Predictor</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; }
        select, button { padding: 8px; margin: 5px; }
    </style>
</head>
<body>

<h2>Football Match Predictor</h2>

<select id="league"></select>
<select id="dateFilter">
    <option value="today">Today</option>
    <option value="yesterday">Yesterday</option>
    <option value="tomorrow">Tomorrow</option>
    <option value="custom">Custom</option>
</select>
<input type="date" id="fromDate" style="display:none;">
<input type="date" id="toDate" style="display:none;">
<button onclick="fetchMatches()">Get Matches</button>

<h3>Matches</h3>
<ul id="matchList"></ul>

<h3>Predict Match</h3>
<select id="team1"></select> vs <select id="team2"></select>
<button onclick="predictMatch()">Predict</button>
<p id="prediction"></p>

<script>
document.addEventListener("DOMContentLoaded", function() {
    fetchLeagues();
    fetchMatches();
});

function fetchLeagues() {
    fetch("?action=getLeagues")
        .then(res => res.json())
        .then(data => {
            let dropdown = document.getElementById("league");
            dropdown.innerHTML = data.map(league => `<option value="${league.code}">${league.name}</option>`).join("");
        });
}

function fetchMatches() {
    let league = document.getElementById("league").value;
    let dateFilter = document.getElementById("dateFilter").value;

    fetch(`?action=getMatches&league=${league}&dateFilter=${dateFilter}`)
        .then(res => res.json())
        .then(data => {
            let list = document.getElementById("matchList");
            list.innerHTML = data.map(match => 
                `<li>${match.homeTeam.name} vs ${match.awayTeam.name}</li>`).join("");
        });
}

function predictMatch() {
    let team1 = document.getElementById("team1").value;
    let team2 = document.getElementById("team2").value;

    fetch(`?action=predictMatch&team1=${team1}&team2=${team2}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById("prediction").textContent = 
                `Predicted Score: ${data.team1} ${data.predicted_score1} - ${data.predicted_score2} ${data.team2}`;
        });
}
</script>

</body>
</html>

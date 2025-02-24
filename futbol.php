<?php
$api_key = "d2ef1a157a0d4c83ba4023d1fbd28b5c";
$base_url = "https://api.football-data.org/v4";

// Fetch available leagues
function fetchLeagues() {
    global $api_key, $base_url;
    $url = "$base_url/competitions";
    return apiRequest($url);
}

// Fetch matches by league & date
function fetchMatches($league, $date) {
    global $api_key, $base_url;
    $url = "$base_url/matches?competitions=$league&dateFrom=$date&dateTo=$date";
    return apiRequest($url);
}

// Fetch past results for prediction
function fetchPastResults($team) {
    global $api_key, $base_url;
    $url = "$base_url/teams/$team/matches?status=FINISHED&limit=14";
    return apiRequest($url);
}

// API request function
function apiRequest($url) {
    global $api_key;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ["X-Auth-Token: $api_key"]);
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($http_code !== 200) {
        return ["error" => "API error! Check your key or limit exceeded."];
    }
    return json_decode($response, true);
}

// Handle AJAX requests
if (isset($_GET["action"])) {
    if ($_GET["action"] == "getLeagues") {
        echo json_encode(fetchLeagues());
    } elseif ($_GET["action"] == "getMatches" && isset($_GET["league"]) && isset($_GET["date"])) {
        echo json_encode(fetchMatches($_GET["league"], $_GET["date"]));
    } elseif ($_GET["action"] == "predict" && isset($_GET["team1"]) && isset($_GET["team2"])) {
        $team1_results = fetchPastResults($_GET["team1"]);
        $team2_results = fetchPastResults($_GET["team2"]);
        echo json_encode(predictScore($team1_results, $team2_results));
    }
    exit;
}

// Prediction Logic
function predictScore($team1_results, $team2_results) {
    if (isset($team1_results["error"]) || isset($team2_results["error"])) {
        return ["error" => "Could not fetch past match data"];
    }

    $team1_goals = 0;
    $team2_goals = 0;
    $matches = min(count($team1_results["matches"]), count($team2_results["matches"]));

    if ($matches == 0) {
        return ["prediction" => "Insufficient data for prediction"];
    }

    for ($i = 0; $i < $matches; $i++) {
        $team1_goals += $team1_results["matches"][$i]["score"]["fullTime"]["home"] ?? 0;
        $team2_goals += $team2_results["matches"][$i]["score"]["fullTime"]["away"] ?? 0;
    }

    $avg_team1 = round($team1_goals / $matches, 1);
    $avg_team2 = round($team2_goals / $matches, 1);

    return ["prediction" => "Predicted Score: $avg_team1 - $avg_team2"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Football Predictor</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; text-align: center; }
        h2 { color: #333; }
        select, button, input { margin: 10px; padding: 8px; font-size: 16px; }
        table { width: 80%; margin: 20px auto; background: white; border-collapse: collapse; }
        th, td { padding: 10px; border: 1px solid #ddd; }
        th { background: #333; color: white; }
        button { cursor: pointer; background: #28a745; color: white; border: none; }
        button:hover { background: #218838; }
        #error { color: red; font-weight: bold; }
    </style>
</head>
<body>
    <h2>Football Match Predictor</h2>

    <label>Select League:</label>
    <select id="league"></select>

    <label>Select Date:</label>
    <select id="dateFilter">
        <option value="yesterday">Yesterday</option>
        <option value="today">Today</option>
        <option value="tomorrow">Tomorrow</option>
        <option value="custom">Custom Date</option>
    </select>
    <input type="date" id="customDate" style="display: none;">
    
    <button onclick="fetchMatches()">Get Matches</button>

    <h3>Available Matches</h3>
    <table id="matchesTable">
        <thead>
            <tr>
                <th>Match</th>
                <th>Score</th>
                <th>Date</th>
                <th>Predict</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <h3>Prediction Result</h3>
    <p id="prediction"></p>
    <p id="error"></p>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            fetchLeagues();
            document.getElementById("dateFilter").addEventListener("change", toggleCustomDate);
        });

        function fetchLeagues() {
            fetch("?action=getLeagues")
            .then(res => res.json())
            .then(data => {
                let leagueSelect = document.getElementById("league");
                leagueSelect.innerHTML = "";
                data.competitions.forEach(league => {
                    let option = document.createElement("option");
                    option.value = league.code;
                    option.textContent = league.name;
                    leagueSelect.appendChild(option);
                });
            })
            .catch(() => document.getElementById("error").textContent = "Error loading leagues");
        }

        function fetchMatches() {
            let league = document.getElementById("league").value;
            let date = getSelectedDate();

            fetch(`?action=getMatches&league=${league}&date=${date}`)
            .then(res => res.json())
            .then(data => {
                let matchesTable = document.querySelector("#matchesTable tbody");
                matchesTable.innerHTML = "";
                data.matches.forEach(match => {
                    let row = document.createElement("tr");
                    row.innerHTML = `
                        <td>${match.homeTeam.shortName} vs ${match.awayTeam.shortName}</td>
                        <td>${match.score.fullTime.home ?? "?"} - ${match.score.fullTime.away ?? "?"}</td>
                        <td>${match.utcDate.split("T")[0]}</td>
                        <td><button onclick="predict('${match.homeTeam.id}', '${match.awayTeam.id}')">Predict</button></td>
                    `;
                    matchesTable.appendChild(row);
                });
            })
            .catch(() => document.getElementById("error").textContent = "Error loading matches");
        }

        function getSelectedDate() {
            let filter = document.getElementById("dateFilter").value;
            let today = new Date();
            if (filter === "yesterday") today.setDate(today.getDate() - 1);
            else if (filter === "tomorrow") today.setDate(today.getDate() + 1);
            else if (filter === "custom") return document.getElementById("customDate").value;
            return today.toISOString().split("T")[0];
        }

        function toggleCustomDate() {
            document.getElementById("customDate").style.display =
                document.getElementById("dateFilter").value === "custom" ? "inline" : "none";
        }

        function predict(team1, team2) {
            fetch(`?action=predict&team1=${team1}&team2=${team2}`)
            .then(res => res.json())
            .then(data => document.getElementById("prediction").textContent = data.prediction)
            .catch(() => document.getElementById("error").textContent = "Prediction error");
        }
    </script>
</body>
</html>

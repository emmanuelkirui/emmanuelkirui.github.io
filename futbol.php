<?php
$api_key = "d2ef1a157a0d4c83ba4023d1fbd28b5c";
$base_url = "https://api.football-data.org/v4";

// Function to call API
function apiRequest($url) {
    global $api_key;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Auth-Token: $api_key"]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Fetch past matches for prediction
if (isset($_GET["action"]) && $_GET["action"] == "predict" && isset($_GET["home"]) && isset($_GET["away"]) && isset($_GET["league"])) {
    $home_id = $_GET["home"];
    $away_id = $_GET["away"];
    $league = $_GET["league"];

    // Get last 10 matches for both teams
    $home_matches = apiRequest("$base_url/teams/$home_id/matches?competitions=$league&limit=10");
    $away_matches = apiRequest("$base_url/teams/$away_id/matches?competitions=$league&limit=10");

    if (!$home_matches || !$away_matches) {
        echo json_encode(["error" => "No past match data found"]);
        exit;
    }

    // Process Data
    $home_goals = 0;
    $away_goals = 0;
    $home_conceded = 0;
    $away_conceded = 0;
    $count = 0;

    // Compare performance against common teams
    foreach ($home_matches["matches"] as $h_match) {
        foreach ($away_matches["matches"] as $a_match) {
            if ($h_match["homeTeam"]["id"] == $a_match["homeTeam"]["id"] || 
                $h_match["awayTeam"]["id"] == $a_match["awayTeam"]["id"]) {
                
                // Home team stats
                $home_goals += ($h_match["homeTeam"]["id"] == $home_id) ? $h_match["score"]["fullTime"]["home"] : $h_match["score"]["fullTime"]["away"];
                $home_conceded += ($h_match["homeTeam"]["id"] == $home_id) ? $h_match["score"]["fullTime"]["away"] : $h_match["score"]["fullTime"]["home"];

                // Away team stats
                $away_goals += ($a_match["homeTeam"]["id"] == $away_id) ? $a_match["score"]["fullTime"]["home"] : $a_match["score"]["fullTime"]["away"];
                $away_conceded += ($a_match["homeTeam"]["id"] == $away_id) ? $a_match["score"]["fullTime"]["away"] : $a_match["score"]["fullTime"]["home"];

                $count++;
            }
        }
    }

    if ($count == 0) {
        echo json_encode(["error" => "No common opponents found"]);
        exit;
    }

    // Calculate average goals
    $predicted_home = round($home_goals / $count);
    $predicted_away = round($away_goals / $count);

    echo json_encode(["prediction" => "$predicted_home - $predicted_away"]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Football Fixture Predictor</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            background-color: #f4f4f4;
            padding: 20px;
        }
        select, input, button {
            padding: 10px;
            margin: 10px;
            font-size: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
        }
        th {
            background: #007BFF;
            color: white;
        }
        tr:nth-child(even) {
            background: #f2f2f2;
        }
        #error {
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <h1>Football Fixture Predictor</h1>

    <label for="league">Select League:</label>
    <select id="league" onchange="fetchMatches()"></select>

    <label for="date">Select Date:</label>
    <input type="date" id="date" onchange="fetchMatches()">

    <button onclick="fetchMatches()">Get Matches</button>

    <p id="error"></p>

    <table id="matchesTable">
        <thead>
            <tr>
                <th>Fixture</th>
                <th>Score</th>
                <th>Date</th>
                <th>Predict</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            fetchLeagues();
            document.getElementById("date").value = new Date().toISOString().split("T")[0]; 
        });

        function fetchLeagues() {
            fetch("?action=getLeagues")
            .then(res => res.json())
            .then(data => {
                let leagueDropdown = document.getElementById("league");
                leagueDropdown.innerHTML = ""; 

                if (data.error) {
                    document.getElementById("error").textContent = "⚠️ " + data.error;
                    return;
                }

                data.competitions.forEach(league => {
                    let option = document.createElement("option");
                    option.value = league.code;
                    option.textContent = league.name;
                    leagueDropdown.appendChild(option);
                });

                leagueDropdown.value = "PL"; 
                fetchMatches();
            })
            .catch(err => document.getElementById("error").textContent = "Error: " + err);
        }

        function fetchMatches() {
            let league = document.getElementById("league").value;
            let date = document.getElementById("date").value;

            fetch(`?action=getMatches&league=${league}&date=${date}`)
            .then(res => res.json())
            .then(data => {
                let matchesTable = document.querySelector("#matchesTable tbody");
                matchesTable.innerHTML = "";

                if (data.error) {
                    document.getElementById("error").textContent = "⚠️ " + data.error;
                    return;
                }

                if (!data.matches || data.matches.length === 0) {
                    document.getElementById("error").textContent = "⚠️ No matches found.";
                    return;
                }

                document.getElementById("error").textContent = "";
                data.matches.forEach(match => {
                    let row = document.createElement("tr");
                    row.innerHTML = `
                        <td>${match.homeTeam.shortName} vs ${match.awayTeam.shortName}</td>
                        <td>${match.score.fullTime.home ?? "?"} - ${match.score.fullTime.away ?? "?"}</td>
                        <td>${match.utcDate.split("T")[0]}</td>
                        <td><button onclick="predict('${match.homeTeam.id}', '${match.awayTeam.id}', '${league}')">Predict</button></td>
                    `;
                    matchesTable.appendChild(row);
                });
            })
            .catch(err => document.getElementById("error").textContent = "Error: " + err);
        }

        function predict(homeId, awayId, league) {
            fetch(`?action=predict&home=${homeId}&away=${awayId}&league=${league}`)
            .then(res => res.json())
            .then(data => alert(data.prediction ? `Predicted Score: ${data.prediction}` : "⚠️ " + data.error))
            .catch(err => alert("Error: " + err));
        }
    </script>

</body>
</html>

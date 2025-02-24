<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Football Fixture Predictor</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .opponent-group {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            background-color: #fafafa;
        }
        .add-opponent {
            margin-bottom: 15px;
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 16px;
        }
        .add-opponent:hover {
            background-color: #218838;
        }
        .result {
            margin-top: 20px;
            padding: 20px;
            background-color: #f4f4f4;
            border-radius: 5px;
        }
        .result h2 {
            color: #333;
            margin-bottom: 10px;
        }
        .result p {
            font-size: 18px;
            color: #555;
        }
        .table-container {
            overflow-x: auto;
            margin-top: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .toggle-button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 14px;
            margin-top: 10px;
        }
        .toggle-button:hover {
            background-color: #0056b3;
        }
        .data-string {
            display: none;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Football Fixture Predictor</h1>
        <form method="post" id="predictionForm">
            <div class="form-group">
                <label for="team1">Team 1</label>
                <input type="text" id="team1" name="team1" required>
            </div>
            <div class="form-group">
                <label for="team2">Team 2</label>
                <input type="text" id="team2" name="team2" required>
            </div>

            <!-- League Selection Dropdown -->
            <div class="form-group">
                <label for="competition">Competition</label>
                <select id="competition" name="competition" required>
                    <!-- Options will be populated dynamically -->
                </select>
            </div>

            <!-- Dynamic Opponent Inputs -->
            <div id="opponentsContainer">
                <!-- Opponent groups will be populated dynamically -->
            </div>

            <button type="button" class="add-opponent" onclick="fetchSharedOpponents()">Find Shared Opponents</button>
            <button type="submit">Predict</button>
        </form>

        <?php
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $team1 = $_POST['team1'];
            $team2 = $_POST['team2'];
            $opponents = $_POST['opponents'];
            $scores = $_POST['scores'];
            $locations = $_POST['locations'];

            // Combine data into a string (e.g., JSON)
            $data = [
                'team1' => $team1,
                'team2' => $team2,
                'opponents' => $opponents,
                'scores' => $scores,
                'locations' => $locations,
            ];
            $dataString = json_encode($data, JSON_PRETTY_PRINT);

            // Parse scores and calculate totals
            $team1_total_goals = 0;
            $team2_total_goals = 0;

            for ($i = 0; $i < count($scores); $i += 2) {
                // Team 1's score vs opponent
                list($team1_goals, $opponent_goals) = explode('-', $scores[$i]);
                $team1_total_goals += (int)$team1_goals;

                // Team 2's score vs opponent
                list($opponent_goals, $team2_goals) = explode('-', $scores[$i + 1]);
                $team2_total_goals += (int)$team2_goals;
            }

            // Simple prediction logic
            if ($team1_total_goals > $team2_total_goals) {
                $prediction = "$team1 is predicted to win against $team2.";
            } elseif ($team1_total_goals < $team2_total_goals) {
                $prediction = "$team2 is predicted to win against $team1.";
            } else {
                $prediction = "The match between $team1 and $team2 is predicted to be a draw.";
            }

            echo "<div class='result'>
                    <h2>Prediction Result</h2>
                    <p>$prediction</p>
                    <h3>Summary Table</h3>
                    <div class='table-container'>
                        <table>
                            <tr>
                                <th>Opponent</th>
                                <th>Team 1 Score</th>
                                <th>Team 1 Location</th>
                                <th>Team 2 Score</th>
                                <th>Team 2 Location</th>
                            </tr>";

            for ($i = 0; $i < count($opponents); $i++) {
                echo "<tr>
                        <td>{$opponents[$i]}</td>
                        <td>{$scores[$i * 2]}</td>
                        <td>{$locations[$i * 2]}</td>
                        <td>{$scores[$i * 2 + 1]}</td>
                        <td>{$locations[$i * 2 + 1]}</td>
                      </tr>";
            }

            echo "</table>
                  </div>
                  <button class='toggle-button' onclick='toggleData()'>Show/Hide Data</button>
                  <div class='data-string' id='dataString'>
                      <h3>Data Saved as String:</h3>
                      <pre>" . htmlspecialchars($dataString) . "</pre>
                  </div>
                  </div>";
        }
        ?>

        <script>
            const apiKey = 'd2ef1a157a0d4c83ba4023d1fbd28b5c'; // Your API key

            // Function to fetch competitions from the API
            async function fetchCompetitions() {
                const url = 'https://api.football-data.org/v4/competitions';

                try {
                    const response = await fetch(url, {
                        headers: {
                            'X-Auth-Token': apiKey
                        }
                    });
                    const data = await response.json();

                    // Populate the dropdown
                    const competitionDropdown = document.getElementById('competition');
                    data.competitions.forEach(competition => {
                        const option = document.createElement('option');
                        option.value = competition.id;
                        option.textContent = competition.name;
                        if (competition.name === 'Premier League') {
                            option.selected = true; // Set Premier League as default
                        }
                        competitionDropdown.appendChild(option);
                    });
                } catch (error) {
                    console.error('Error fetching competitions:', error);
                }
            }

            // Function to fetch shared opponents
            async function fetchSharedOpponents() {
                const team1 = document.getElementById('team1').value.trim();
                const team2 = document.getElementById('team2').value.trim();
                const competitionId = document.getElementById('competition').value;

                if (!team1 || !team2) {
                    alert('Please enter both Team 1 and Team 2.');
                    return;
                }

                try {
                    // Fetch team IDs for Team 1 and Team 2
                    const teamsResponse = await fetch(`https://api.football-data.org/v4/competitions/${competitionId}/teams`, {
                        headers: {
                            'X-Auth-Token': apiKey
                        }
                    });
                    const teamsData = await teamsResponse.json();

                    const team1Data = teamsData.teams.find(team => team.name.toLowerCase() === team1.toLowerCase());
                    const team2Data = teamsData.teams.find(team => team.name.toLowerCase() === team2.toLowerCase());

                    if (!team1Data || !team2Data) {
                        alert('One or both teams not found in the selected competition.');
                        return;
                    }

                    // Fetch fixtures for Team 1 and Team 2
                    const team1Fixtures = await fetchFixtures(team1Data.id, competitionId);
                    const team2Fixtures = await fetchFixtures(team2Data.id, competitionId);

                    // Find shared opponents
                    const sharedOpponents = findSharedOpponents(team1Fixtures, team2Fixtures, team1Data.id, team2Data.id);

                    // Populate the form with shared opponents
                    populateOpponents(sharedOpponents);
                } catch (error) {
                    console.error('Error fetching shared opponents:', error);
                }
            }

            // Function to fetch fixtures for a team
            async function fetchFixtures(teamId, competitionId) {
                const url = `https://api.football-data.org/v4/teams/${teamId}/matches?status=FINISHED&competitions=${competitionId}`;
                const response = await fetch(url, {
                    headers: {
                        'X-Auth-Token': apiKey
                    }
                });
                const data = await response.json();
                return data.matches;
            }

            // Function to find shared opponents
            function findSharedOpponents(team1Fixtures, team2Fixtures, team1Id, team2Id) {
                const team1Opponents = new Set();
                const team2Opponents = new Set();

                // Get opponents for Team 1
                team1Fixtures.forEach(match => {
                    const opponent = match.homeTeam.id === team1Id ? match.awayTeam : match.homeTeam;
                    team1Opponents.add(opponent);
                });

                // Get opponents for Team 2
                team2Fixtures.forEach(match => {
                    const opponent = match.homeTeam.id === team2Id ? match.awayTeam : match.homeTeam;
                    team2Opponents.add(opponent);
                });

                // Find intersection (shared opponents)
                const sharedOpponents = [];
                team1Opponents.forEach(opponent => {
                    if (team2Opponents.has(opponent)) {
                        sharedOpponents.push(opponent);
                    }
                });

                return sharedOpponents;
            }

            // Function to populate the form with shared opponents
            function populateOpponents(sharedOpponents) {
                const container = document.getElementById('opponentsContainer');
                container.innerHTML = ''; // Clear existing content

                sharedOpponents.forEach(opponent => {
                    const opponentGroup = document.createElement('div');
                    opponentGroup.className = 'opponent-group';
                    opponentGroup.innerHTML = `
                        <div class="form-group">
                            <label>Shared Opponent</label>
                            <input type="text" name="opponents[]" value="${opponent.name}" readonly>
                        </div>
                        <div class="form-group">
                            <label>Score (Team 1 vs ${opponent.name})</label>
                            <input type="text" name="scores[]" placeholder="e.g., 2-1" required>
                        </div>
                        <div class="form-group">
                            <label>Team 1 Played</label>
                            <select name="locations[]" required>
                                <option value="home">Home</option>
                                <option value="away">Away</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Score (Team 2 vs ${opponent.name})</label>
                            <input type="text" name="scores[]" placeholder="e.g., 1-2" required>
                        </div>
                        <div class="form-group">
                            <label>Team 2 Played</label>
                            <select name="locations[]" required>
                                <option value="home">Home</option>
                                <option value="away">Away</option>
                            </select>
                        </div>
                    `;
                    container.appendChild(opponentGroup);
                });
            }

            // Call the function to fetch competitions on page load
            fetchCompetitions();
        </script>
    </div>
</body>
</html>

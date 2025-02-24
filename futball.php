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
            <!-- Competition Selection Dropdown -->
            <div class="form-group">
                <label for="competition">Competition</label>
                <select id="competition" name="competition" required>
                    <!-- Options will be populated dynamically -->
                </select>
            </div>

            <!-- Date Filters -->
            <div class="form-group">
                <label for="dateFrom">Date From</label>
                <input type="date" id="dateFrom" name="dateFrom" required>
            </div>
            <div class="form-group">
                <label for="dateTo">Date To</label>
                <input type="date" id="dateTo" name="dateTo" required>
            </div>

            <!-- Team Selection Dropdowns -->
            <div class="form-group">
                <label for="team1">Team 1</label>
                <select id="team1" name="team1" required>
                    <!-- Options will be populated dynamically -->
                </select>
            </div>
            <div class="form-group">
                <label for="team2">Team 2</label>
                <select id="team2" name="team2" required>
                    <!-- Options will be populated dynamically -->
                </select>
            </div>

            <!-- Dynamic Opponent Inputs -->
            <div id="opponentsContainer">
                <!-- Opponent groups will be populated dynamically -->
            </div>

            <button type="button" class="add-opponent" onclick="fetchFixturesAndOpponents()">Find Fixtures and Opponents</button>
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

                    // Log the response status
                    console.log('Response Status:', response.status);

                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }

                    const data = await response.json();

                    // Log the fetched data
                    console.log('Fetched Competitions:', data);

                    // Populate the dropdown
                    const competitionDropdown = document.getElementById('competition');
                    competitionDropdown.innerHTML = ''; // Clear existing options

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
                    alert('Failed to fetch competitions. Check the console for details.');
                }
            }

            // Function to fetch fixtures and opponents
            async function fetchFixturesAndOpponents() {
                const competitionId = document.getElementById('competition').value;
                const dateFrom = document.getElementById('dateFrom').value;
                const dateTo = document.getElementById('dateTo').value;

                if (!competitionId || !dateFrom || !dateTo) {
                    alert('Please select a competition and date range.');
                    return;
                }

                try {
                    // Fetch fixtures for the selected competition and date range
                    const fixturesResponse = await fetch(
                        `https://api.football-data.org/v4/competitions/${competitionId}/matches?dateFrom=${dateFrom}&dateTo=${dateTo}`,
                        {
                            headers: {
                                'X-Auth-Token': apiKey
                            }
                        }
                    );

                    // Log the response status
                    console.log('Fixtures Response Status:', fixturesResponse.status);

                    if (!fixturesResponse.ok) {
                        throw new Error(`HTTP error! Status: ${fixturesResponse.status}`);
                    }

                    const fixturesData = await fixturesResponse.json();

                    // Log the fetched fixtures
                    console.log('Fetched Fixtures:', fixturesData);

                    // Extract teams and opponents from fixtures
                    const teams = new Set();
                    const opponents = new Map(); // Key: team, Value: list of opponents

                    fixturesData.matches.forEach(match => {
                        const homeTeam = match.homeTeam.name;
                        const awayTeam = match.awayTeam.name;

                        teams.add(homeTeam);
                        teams.add(awayTeam);

                        if (!opponents.has(homeTeam)) {
                            opponents.set(homeTeam, []);
                        }
                        if (!opponents.has(awayTeam)) {
                            opponents.set(awayTeam, []);
                        }

                        opponents.get(homeTeam).push(awayTeam);
                        opponents.get(awayTeam).push(homeTeam);
                    });

                    // Populate team dropdowns
                    const team1Dropdown = document.getElementById('team1');
                    const team2Dropdown = document.getElementById('team2');
                    team1Dropdown.innerHTML = '';
                    team2Dropdown.innerHTML = '';

                    teams.forEach(team => {
                        const option1 = document.createElement('option');
                        option1.value = team;
                        option1.textContent = team;
                        team1Dropdown.appendChild(option1);

                        const option2 = document.createElement('option');
                        option2.value = team;
                        option2.textContent = team;
                        team2Dropdown.appendChild(option2);
                    });

                    // Populate opponents container
                    const container = document.getElementById('opponentsContainer');
                    container.innerHTML = ''; // Clear existing content

                    opponents.forEach((opponentList, team) => {
                        const opponentGroup = document.createElement('div');
                        opponentGroup.className = 'opponent-group';
                        opponentGroup.innerHTML = `
                            <div class="form-group">
                                <label>Team: ${team}</label>
                                <input type="text" name="teams[]" value="${team}" readonly>
                            </div>
                            <div class="form-group">
                                <label>Opponents</label>
                                <input type="text" name="opponents[]" value="${opponentList.join(', ')}" readonly>
                            </div>
                        `;
                        container.appendChild(opponentGroup);
                    });
                } catch (error) {
                    console.error('Error fetching fixtures and opponents:', error);
                    alert('Failed to fetch fixtures and opponents. Check the console for details.');
                }
            }

            // Call the function to fetch competitions on page load
            fetchCompetitions();
        </script>
    </div>
</body>
</html>

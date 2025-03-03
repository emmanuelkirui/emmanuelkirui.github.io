<?php
// Set the timezone to East Africa Time
date_default_timezone_set('Africa/Nairobi'); // EAT (UTC+3)

// API Configuration
$apiBaseUrl = "https://api.football-data.org/v4/competitions";
$apiKey = "d2ef1a157a0d4c83ba4023d1fbd28b5c"; // Replace with your API key

// Fetch competitions dynamically from the API
$competitionsList = [];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiBaseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Auth-Token: $apiKey"
]);
$response = curl_exec($ch);

// Check for cURL errors
if ($response === false) {
    $error = 'Error fetching competitions from the API.';
    header("Location: ../error.php?code=fetch_error");
    exit;
}

// Check HTTP status code
$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpStatusCode == 429) {
    echo "<div style='text-align: center; font-family: Arial, sans-serif; margin-top: 50px;'>
            <h2 style='color: red;'>Too Many Requests (429)</h2>
            <p>Retrying in <span id='countdown' style='font-weight: bold; color: blue;'>5</span> seconds...</p>
          </div>
          <script>
            let timeLeft = 5;
            let countdownTimer = setInterval(() => {
                timeLeft--;
                document.getElementById('countdown').textContent = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(countdownTimer);
                    location.reload();
                }
            }, 1000);
          </script>";
    exit;
}

if ($httpStatusCode >= 400) {
    // Redirect to error.php with the status code as a parameter
    header("Location: ../error.php?code=$httpStatusCode");
    exit;
}

// Decode the response and process data
$data = json_decode($response, true);

if (isset($data['error'])) {
    $error = $data['message'] ?? 'An error occurred while fetching the competitions.';
    header("Location: ../error.php?code=api_error");
    exit;
}

foreach ($data['competitions'] as $competition) {
    // Add competition name and code to the list
    if (isset($competition['name'], $competition['code'])) {
        $competitionsList[$competition['name']] = $competition['code'];
    }
}




// Default settings
$defaultCompetition = 'PL'; // Default to Primeira Liga if no selection
$defaultDateFilter = 'all'; // Show all matches by default

// Get user inputs or set defaults
$competition = isset($_GET['competition']) ? $_GET['competition'] : $defaultCompetition;
$dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : $defaultDateFilter;
$customDate = isset($_GET['custom_date']) ? $_GET['custom_date'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Current page
$itemsPerPage = 10; // Matches per page

// Construct API URL
$apiUrl = "$apiBaseUrl/$competition/matches";

// Fetch matches from the API
$matches = [];
$matchError = '';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Auth-Token: $apiKey"
]);
$response = curl_exec($ch);

// Check for cURL errors
if ($response === false) {
    header("Location: ../error.php?code=fetch_error");
    exit;
}

// Check HTTP status code
$httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpStatusCode >= 400) {
    header("Location: ../error.php?code=$httpStatusCode");
    exit;
}

// Decode the response and process data
$data = json_decode($response, true);

if (isset($data['error'])) {
    header("Location: ../error.php?code=api_error");
    exit;
}

// Filter matches based on date filter
$matches = array_filter($data['matches'], function ($match) use ($dateFilter, $customDate) {
    $matchDate = date('Y-m-d', strtotime($match['utcDate']));
    if ($dateFilter === 'yesterday') {
        return $matchDate === date('Y-m-d', strtotime('-1 day'));
    } elseif ($dateFilter === 'today') {
        return $matchDate === date('Y-m-d');
    } elseif ($dateFilter === 'tomorrow') {
        return $matchDate === date('Y-m-d', strtotime('+1 day'));
    } elseif ($dateFilter === 'custom' && !empty($customDate)) {
        return $matchDate === $customDate;
    }
    return true; // 'all' shows all matches
});





// Pagination logic
$totalItems = count($matches);
$totalPages = ceil($totalItems / $itemsPerPage);
$offset = ($page - 1) * $itemsPerPage;
$paginatedMatches = array_slice($matches, $offset, $itemsPerPage);
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Scores</title>
    <!-- Preconnect to Football Data API -->
    <link rel="preconnect" href="https://api.football-data.org">
    <link rel="stylesheet" href="css/bulma.min.css"> <!-- Bulma CSS -->
    <link rel="stylesheet" href="css/styles.css"> <!-- Custom Styles -->
    <link rel="stylesheet" href="../css/network-status.css">
   <script src="../network-status.js"></script>
    <script>
        function reloadPage() {
            const competition = document.getElementById('competition').value;
            const dateFilter = document.getElementById('date_filter').value;
            const customDate = document.getElementById('custom_date').value;

            if (dateFilter === 'custom' && customDate) {
                window.location.href = `?competition=${competition}&date_filter=custom&custom_date=${customDate}`;
            } else {
                window.location.href = `?competition=${competition}&date_filter=${dateFilter}`;
            }
        }

        function toggleCustomDateInput() {
            const dateFilter = document.getElementById('date_filter').value;
            const customDateInput = document.getElementById('custom_date_input');
            customDateInput.style.display = (dateFilter === 'custom') ? 'inline-block' : 'none';
        }
    </script>
    <script>
    function toggleH2H(rowId, homeId, awayId, buttonType) {
        const h2hContainer = document.getElementById(`h2h-${rowId}`);
        const h2hContainerA = document.getElementById(`h2hA-${rowId}`);
        
        // Determine which button was clicked and toggle visibility
        if (buttonType === 'home') {
            if (h2hContainer.style.display === 'none' || h2hContainer.innerHTML === '') {
                // Show the home container and fetch data if it's empty
                h2hContainer.style.display = 'block';
                if (h2hContainer.innerHTML === '') {
                    fetch(`h2h.php?home_id=${homeId}&away_id=${awayId}`)
                        .then(response => response.text())
                        .then(data => h2hContainer.innerHTML = data)
                        .catch(() => h2hContainer.innerHTML = '<p>Error loading H2H data.</p>');
                }
                // Hide the away container if visible
                h2hContainerA.style.display = 'none';
            } else {
                h2hContainer.style.display = 'none';
            }
        } else if (buttonType === 'away') {
            if (h2hContainerA.style.display === 'none' || h2hContainerA.innerHTML === '') {
                // Show the away container and fetch data if it's empty
                h2hContainerA.style.display = 'block';
                if (h2hContainerA.innerHTML === '') {
                    fetch(`h2h.php?away_id=${homeId}&home_id=${awayId}`)
                        .then(response => response.text())
                        .then(data => h2hContainerA.innerHTML = data)
                        .catch(() => h2hContainerA.innerHTML = '<p>Error loading H2H data.</p>');
                }
                // Hide the home container if visible
                h2hContainer.style.display = 'none';
            } else {
                h2hContainerA.style.display = 'none';
            }
        }
    }
</script>


</head>
<body>
 <!-- Navigation Bar -->
<nav class="navbar is-primary">
    <div class="navbar-brand">
        <a class="navbar-item" href="index.php">
            <strong>CSP Football</strong>
        </a>
        <a href="#" role="button" class="navbar-burger" aria-label="menu" aria-expanded="false" data-target="navbarBasicExample">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </a>
    </div>
    <div id="navbarBasicExample" class="navbar-menu">
        <div class="navbar-start">
            <a class="navbar-item" href="../index">Home</a>
             <a class="navbar-item" href="livescores.php">Livescores</a>
                <a class="navbar-item" href="index.php">Games</a>
                <a class="navbar-item" href="top-scorers.php">Top-Scorer</a>
            <a class="navbar-item" href="../display_matches">Matches</a>
            <a class="navbar-item" href="../standings">About</a>
            <a class="navbar-item" href="highlights.php">Highlights</a>
        </div>
        <div class="navbar-end">
            <a class="navbar-item button is-light" href="../index">Back to Home</a>
        </div>
    </div>
</nav>

<script>
    // Get navbar burger and menu
    const burger = document.querySelector('.navbar-burger');
    const menu = document.querySelector('#navbarBasicExample');

    // Toggle navbar menu visibility on burger click
    burger.addEventListener('click', () => {
        burger.classList.toggle('is-active');
        menu.classList.toggle('is-active');
    });
</script>
    <section class="section">
    <!-- Status Light -->
    <div id="status_light" class="status" style="display: none;">
        <div class="status_light">
            <div class="status_light_ring"></div>
            <div class="status_light_led"></div>
        </div>
        <span class="status_message">Processing...</span>
    </div>
    <script>
        // Function to show the status light
        function showStatusLight() {
            document.getElementById('status_light').style.display = 'flex';
        }

        // Attach event listeners to forms
        document.getElementById('competitionForm').addEventListener('submit', function () {
            showStatusLight();
        });

        document.getElementById('searchForm').addEventListener('submit', function () {
            showStatusLight();
        });
        // Hide the status light after page load (optional)
     window.onload = function () {
        const statusLight = document.getElementById('status_light');
        if (statusLight) {
            statusLight.style.display = 'none';
        }
    };
    </script>
    <div class="container">
        <h1 class="title has-text-centered">Live Scores</h1>
        <form class="field" id="competitionForm" onchange="showStatusLight()">
            <div class="control">
                <label for="competition">Select Competition:</label>
                <div class="select">
                    <select id="competition" name="competition" onchange="reloadPage()" required>
                        <?php foreach ($competitionsList as $displayName => $code): ?>
                            <option value="<?php echo $code; ?>" <?php echo ($competition == $code) ? 'selected' : ''; ?>>
                                <?php echo $displayName; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="control">
                <label for="date_filter">Select Date:</label>
                <div class="select">
                    <select id="date_filter" name="date_filter" onchange="toggleCustomDateInput(); reloadPage();" required>
                        <option value="all" <?php echo ($dateFilter == 'all') ? 'selected' : ''; ?>>All</option>
                        <option value="yesterday" <?php echo ($dateFilter == 'yesterday') ? 'selected' : ''; ?>>Yesterday</option>
                        <option value="today" <?php echo ($dateFilter == 'today') ? 'selected' : ''; ?>>Today</option>
                        <option value="tomorrow" <?php echo ($dateFilter == 'tomorrow') ? 'selected' : ''; ?>>Tomorrow</option>
                        <option value="custom" <?php echo ($dateFilter == 'custom') ? 'selected' : ''; ?>>Custom</option>
                    </select>
                </div>
            </div>
            <div class="control" id="custom_date_input" style="display: <?php echo ($dateFilter === 'custom') ? 'inline-block' : 'none'; ?>;">
                <label for="custom_date">Pick a Date:</label>
                <input class="input" type="date" id="custom_date" name="custom_date" value="<?php echo $customDate; ?>" onchange="reloadPage()">
            </div>
        </form>

        <?php if (!empty($error)): ?>
            <div class="notification is-danger"><?php echo $error; ?></div>
        <?php else: ?>
            <?php if (!empty($paginatedMatches)): ?>
                <div class="table-container">
                    <table class="table is-striped is-hoverable is-fullwidth">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Home Team</th>
                                <th>Away Team</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Match Date</th>
                                <th>H2H</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($paginatedMatches as $index => $match): ?>
    <?php 
        $rowId = $offset + $index + 1;
        $homeTeam = $match['homeTeam'] ?? ['name' => 'Unknown', 'id' => ''];
        $awayTeam = $match['awayTeam'] ?? ['name' => 'Unknown', 'id' => ''];
        $homeTeamId = $homeTeam['id'] ?? '';
        $awayTeamId = $awayTeam['id'] ?? '';
        
        
       
    ?>
    <tr>
        <td><?= $rowId; ?></td>
       <!-- Home Team Column -->
<td>
    <!-- Home Team Name -->
   
    <div>
        <a href="team_matches.php?team_id=<?= $homeTeam['id']; ?>" class="team-link">
            <?= htmlspecialchars($homeTeam['name']); ?>
        </a>
    </div>
    
    
    <!-- View Squad Button (Small) -->
    <div style="margin-top: 10px;">
        <button class="button is-link is-small" 
                onclick="openModal(<?= $homeTeamId; ?>)" 
                aria-label="View <?= htmlspecialchars($homeTeam['name']); ?> Squad" 
                title="Click to view squad details for <?= htmlspecialchars($homeTeam['name']); ?>">
<?= strtoupper(htmlspecialchars(substr($homeTeam['name'], 0, 3))); ?> Squad

        </button>
    </div>
</td>

<!-- Away Team Column -->
<td>
    <!-- Away Team Name -->
    <div>
    
        <a href="team_matches.php?team_id=<?= $awayTeam['id']; ?>" class="team-link">
            <?= htmlspecialchars($awayTeam['name']); ?>
        </a>
    </div>
   
    <!-- View Squad Button (Small) -->
    <div style="margin-top: 10px;">
        <button class="button is-link is-small" 
                onclick="openModal(<?= $awayTeamId; ?>)" 
                aria-label="View <?= htmlspecialchars($awayTeam['name']); ?> Squad" 
                title="Click to view squad details for <?= htmlspecialchars($awayTeam['name']); ?>">
<?= strtoupper(htmlspecialchars(substr($awayTeam['name'], 0, 3))); ?> Squad

        </button>
    </div>
</td>

<!-- Modal Structure -->
<div id="teamSquadModal" class="modal">
    <div class="modal-background"></div>
    <div class="modal-content">
        <div class="box">
            <!-- Content will be loaded here -->
        </div>
    </div>
    <button class="modal-close is-large" aria-label="close"></button>
</div>

<script>
    // Function to open the modal and load team squad data dynamically
    const openModal = (teamId) => {
        fetch(`team_squad.php?team_id=${teamId}`)
            .then(response => response.text())
            .then(html => {
                document.getElementById('teamSquadModal').querySelector('.modal-content .box').innerHTML = html;
                document.getElementById('teamSquadModal').classList.add('is-active');
            });
    };

    // Close the modal when the close button is clicked
    document.querySelector('.modal-close').addEventListener('click', () => {
        document.getElementById('teamSquadModal').classList.remove('is-active');
    });

    // Close the modal when the background is clicked
    document.querySelector('.modal-background').addEventListener('click', () => {
        document.getElementById('teamSquadModal').classList.remove('is-active');
    });
</script>

<!-- Bulma CSS & Custom styles for responsiveness -->
<style>
    /* Ensure the modal takes full screen width on smaller screens */
    @media (max-width: 768px) {
        .modal-content {
            width: 90%;
            height: 90%;
            max-height: 80%;
        }
    }

    /* Adjust the modal content for larger screens */
    @media (min-width: 769px) {
        .modal-content {
            width: 70%;
            max-width: 900px;
            height: auto;
        }
    }

    /* Ensuring the modal has proper padding and scroll behavior for small screens */
    .modal-content .box {
        overflow-y: auto;
        max-height: 80vh; /* Keep content scrollable if it's too tall */
        padding: 1.5rem;
    }
</style>


        <td><?= $match['score']['fullTime']['home'] ?? '-'; ?> - <?= $match['score']['fullTime']['away'] ?? '-'; ?></td>
        <td><?= $match['status']; ?></td>
        <td><?= date('Y-m-d H:i', strtotime($match['utcDate'])); ?></td>
        <td>
    <?php if (!empty($homeTeamId) && !empty($awayTeamId)): ?>
        <button class="button is-small is-info" style="margin-right: 10px;" onclick="toggleH2H('<?= $rowId; ?>', '<?= $homeTeamId; ?>', '<?= $awayTeamId; ?>', 'home')">
            Show Home H2H
        </button>
        <button class="button is-small is-info" onclick="toggleH2H('<?= $rowId; ?>', '<?= $homeTeamId; ?>', '<?= $awayTeamId; ?>', 'away')">
            Show Away H2H
        </button>
    <?php else: ?>
        <span>N/A</span>
    <?php endif; ?>
</td>


</tr>
<tr>
    <td colspan="7">
        <div id="h2h-<?= $rowId; ?>" style="padding: 10px; background-color: #f5f5f5;"></div>
        <div id="h2hA-<?= $rowId; ?>" style="padding: 10px; background-color: #f5f5f5;"></div>
    </td>
</tr>

<?php endforeach; ?>

                        </tbody>
                    </table>
                </div>
                <nav class="pagination is-centered" role="navigation">
    <a class="pagination-previous" href="?competition=<?php echo $competition; ?>&date_filter=<?php echo $dateFilter; ?>&custom_date=<?php echo $customDate; ?>&page=1" <?php echo ($page <= 1) ? 'disabled' : ''; ?>>First</a>
    <a class="pagination-previous" href="?competition=<?php echo $competition; ?>&date_filter=<?php echo $dateFilter; ?>&custom_date=<?php echo $customDate; ?>&page=<?php echo max(1, $page - 1); ?>" <?php echo ($page <= 1) ? 'disabled' : ''; ?>>Previous</a>
    <a class="pagination-next" href="?competition=<?php echo $competition; ?>&date_filter=<?php echo $dateFilter; ?>&custom_date=<?php echo $customDate; ?>&page=<?php echo min($totalPages, $page + 1); ?>" <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>>Next</a>
    <a class="pagination-next" href="?competition=<?php echo $competition; ?>&date_filter=<?php echo $dateFilter; ?>&custom_date=<?php echo $customDate; ?>&page=<?php echo $totalPages; ?>" <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>>Last</a>
</nav>

            <?php else: ?>
                <div class="notification is-warning">No matches found for the selected filter.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php include '../back-to-top.php'; ?>

</body>
</html>

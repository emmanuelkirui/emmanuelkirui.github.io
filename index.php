<?php
// Include config settings
$settings = include('config_settings.php');
include "core.php";
// Include the CSRF protection file
include 'csrf.php';
// Include reCAPTCHA file
include_once 'recaptcha.php';
head();

// Start session
session_start();
// Error handling and logging setup
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
// Check if the user is logged in
$userLoggedIn = isset($_SESSION['sec-username']);
$userName = $userLoggedIn ? $_SESSION['sec-username'] : "Guest"; // Use session variable or default to "Guest"

// If the sidebar is positioned on the left, include the sidebar
if ($settings['sidebar_position'] == 'Left') {
    sidebar();
}

// Hugging Face API setup
$huggingFaceAPIKey = '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'; // Replace with your actual API key
$huggingFaceAPIUrl = 'https://api-inference.huggingface.co/models/microsoft/DialoGPT';

// Initialize or retrieve previous conversation from session
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// Function to interact with Hugging Face's chatbot
function getChatbotResponse($message) {
    global $huggingFaceAPIKey, $huggingFaceAPIUrl;

    // Adding conversation context to the message
    $conversationHistory = [];
    if (count($_SESSION['chat_history']) > 0) {
        $conversationHistory = $_SESSION['chat_history'];
    }

    // Add the new user message to the conversation history
    $conversationHistory[] = ['role' => 'user', 'content' => $message];

    // Prepare the conversation history for the request
    $data = json_encode(['inputs' => $conversationHistory]);

    $headers = [
        'Authorization: Bearer ' . $huggingFaceAPIKey,
        'Content-Type: application/json',
    ];

    $ch = curl_init($huggingFaceAPIUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

// Server-side validation for reCAPTCHA
function validateRecaptcha($recaptchaResponse) {
    global $settings;

    $recaptchaSecret = $settings['gcaptcha_secretkey'];  // Get secret key from config
    $url = 'https://www.google.com/recaptcha/api/siteverify';
    
    // Send a POST request to verify the reCAPTCHA response
    $response = file_get_contents($url . '?secret=' . $recaptchaSecret . '&response=' . $recaptchaResponse);
    $responseKeys = json_decode($response, true);
    
    return $responseKeys["success"];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_message'])) {
    $userMessage = $_POST['user_message'];
    $recaptchaResponse = $_POST['g-recaptcha-response'];  // Get the reCAPTCHA response
    
    
    // Validate reCAPTCHA
    if (!validateRecaptcha($recaptchaResponse)) {
        $error = "Please verify that you are not a robot.";
    } else {
        // Get chatbot's response
        $chatbotResponse = getChatbotResponse($userMessage);
        $responseData = json_decode($chatbotResponse, true);
        $botMessage = $responseData[0]['generated_text'] ?? "Sorry, I couldn't understand that.";

        // Save the user and bot message to the session for continuity
        $_SESSION['chat_history'][] = ['role' => 'bot', 'content' => $botMessage];

        // Add the user message to the history as well
        $_SESSION['chat_history'][] = ['role' => 'user', 'content' => $userMessage];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creative Pulse Solutions</title>

    <!-- CSS Links -->
    <link href="css/list-page.css" rel="stylesheet">
    <link href="css/loader.css" rel="stylesheet">
</head>
<!-- Loader HTML -->
<body onload="pageLoad()" class="d-flex flex-column min-vh-100">
    <div id="loader-wrapper">
        <img src="img/logo.png" id="loader-logo">
        <div class="loader">
            <div class="loader__bar"></div>
            <div class="loader__bar"></div>
            <div class="loader__bar"></div>
            <div class="loader__bar"></div>
            <div class="loader__bar"></div>
            <div class="loader__ball"></div>
        </div>
    </div>

<div class="col-md-8 mb-3">

    <!-- Header Ad Section -->
    <!-- Section for Generic Content -->
<div class="ad-container" style="margin: 20px 0; text-align: center;">
  <!-- Dynamic Content Block -->
  <script type="text/javascript">
    atOptions = {
      'key' : '6c330ed2db899b8867378fb54deaa881',
      'format' : 'iframe',
      'height' : 50,
      'width' : 320,
      'params' : {}
    };
  </script>
  <script type="text/javascript" src="//axisdoctrine.com/6c330ed2db899b8867378fb54deaa881/invoke.js"></script>
</div>


    <!-- Subscribe Button Section -->
<div class="text-center my-4">
    <a href="payment_page.php" class="btn btn-primary btn-lg">Subscribe Now with Paypal</a>
</div>

    <!-- Carousel for Featured Posts -->
    <?php
    $mt3_i = "";
    $run = mysqli_query($connect, "SELECT * FROM `posts` WHERE active='Yes' AND featured='Yes' ORDER BY id DESC");
    $count = mysqli_num_rows($run);
    if ($count > 0) {
        $i = 0;
        $mt3_i = "mt-3";
    ?>
    <div id="carouselExampleCaptions" class="col-md-12 carousel slide" data-bs-ride="carousel">
        <div class="carousel-indicators">
        <?php
        while ($row = mysqli_fetch_assoc($run)) {
            $active1 = "";
            if ($i == 0) {
                $active1 = 'class="active" aria-current="true"';
            }

            echo '<button type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide-to="' . $i . '" '. $active1 .' aria-label="' . $row['title'] . '"></button>';
            $i++;
        }
        ?>
        </div>
        <div class="carousel-inner rounded">
        <?php
        $j = 0;
        $run2 = mysqli_query($connect, "SELECT * FROM `posts` WHERE active='Yes' AND featured='Yes' ORDER BY id DESC");
        while ($row2 = mysqli_fetch_assoc($run2)) {
            $active = "";
            if ($j == 0) {
                $active = " active";
            }

            $image = "";
            if($row2['image'] != "") {
                $image = '<img src="' . $row2['image'] . '" alt="' . $row2['title'] . '" class="d-block w-100" height="400">';
            } else {
                $image = '<svg class="bd-placeholder-img bd-placeholder-img-lg d-block w-100" height="400" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="No Image" preserveAspectRatio="xMidYMid slice" focusable="false">
                            <title>' . $row2['title'] . '</title>
                            <rect width="100%" height="100%" fill="#555"></rect>
                            <text x="45%" y="50%" fill="black" dy=".3em">No Image</text></svg>';
            }

            echo '
            <div class="carousel-item'. $active .'">
                <a href="post?name=' . $row2['slug'] . '">' . $image . '</a>
                <div class="carousel-caption d-md-block">
                    <h5>
                        <a href="post?name=' . $row2['slug'] . '" class="text-light" style="text-shadow: 1px 1px black;">' . $row2['title'] . '</a>
                    </h5>
                    <p class="text-light" style="text-shadow: 1px 1px black;">
                        <i class="fas fa-calendar"></i> ' . date($settings['date_format'], strtotime($row2['date'])) . ', ' . $row2['time'] . '
                    </p>
                </div>
            </div>';
            $j++;
        }
        ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleCaptions" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>
    <?php
    }
    ?>
    <!-- Middle Ad Section -->
<div class="ad-container text-center my-4" style="text-align: center; margin: 20px 0;">
  <script type="text/javascript">
    atOptions = {
        'key' : '6c330ed2db899b8867378fb54deaa881',
        'format' : 'iframe',
        'height' : 50,
        'width' : 320,
        'params' : {}
    };
  </script>
  <script type="text/javascript" src="//axisdoctrine.com/6c330ed2db899b8867378fb54deaa881/invoke.js"></script>
</div>
 <!-- Floating Chat Button -->
<div id="chatButton" class="chat-button">
    <button onclick="toggleChat()">Chat with us</button>
</div>

<!-- WhatsApp-style Chatbot UI (Initially Hidden) -->
<div id="chatContainer" class="chatbot-container my-4" style="display: none;">
    <h5>Chat with our AI</h5>
    <div id="chatbox" class="border p-3" style="max-height: 400px; overflow-y: scroll; background-color: #f8f9fa;">
        <div id="messages">
            <?php
            // Display previous chat history from the session
            if (count($_SESSION['chat_history']) > 0) {
                foreach ($_SESSION['chat_history'] as $message) {
                    // Display sender's name and message based on the role (user or bot)
                    if ($message['role'] == 'user') {
                        echo '<div class="message user-message mb-3"><strong>' . htmlspecialchars($message['sender']) . ':</strong> ' . htmlspecialchars($message['content']) . '</div>';
                    } else {
                        echo '<div class="message bot-message mb-3"><strong>CPS:</strong> ' . htmlspecialchars($message['content']) . '</div>';
                    }
                }
            }
            ?>
        </div>
    </div>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form id="chatForm" method="POST">
        <div class="input-group mt-3">
            <input type="text" class="form-control" name="user_message" id="userMessage" placeholder="Type your message..." required>
            <button type="submit" class="btn btn-primary">Send</button>
        </div>

        <!-- Google reCAPTCHA v2 -->
        <div class="g-recaptcha" data-sitekey="<?= $settings['gcaptcha_sitekey']; ?>"></div> <!-- Dynamically get the site key -->
    </form>
</div>

<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<script>
    // Scroll chatbox to the bottom when new message is added
    function scrollToBottom() {
        const chatbox = document.getElementById('chatbox');
        chatbox.scrollTop = chatbox.scrollHeight;
    }

    // Toggle the chat visibility
    function toggleChat() {
        const chatContainer = document.getElementById('chatContainer');
        if (chatContainer.style.display === 'none') {
            chatContainer.style.display = 'block'; // Show chat container
        } else {
            chatContainer.style.display = 'none'; // Hide chat container
        }
    }

    // Handle form submission
    document.getElementById('chatForm').addEventListener('submit', function(event) {
        event.preventDefault();

        const userMessage = document.getElementById('userMessage').value;
        if (userMessage.trim() !== '') {
            // Get the logged-in user's name (or "Guest" if not logged in)
            const userName = '<?php echo isset($_SESSION['sec-username']) ? $_SESSION['sec-username'] : "Guest"; ?>';
            
            // Display the user's message with their name
            const userMessageDiv = document.createElement('div');
            userMessageDiv.classList.add('message', 'user-message');
            userMessageDiv.innerHTML = '<strong>' + userName + ':</strong> ' + userMessage;
            document.getElementById('messages').appendChild(userMessageDiv);

            // Scroll to the bottom after new message is added
            scrollToBottom();

            // Send the message to the server and get the response from the chatbot
            fetch('', {
                method: 'POST',
                body: new URLSearchParams({
                    user_message: userMessage
                }),
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            })
            .then(response => response.text())
            .then(response => {
                const botMessage = response.match(/botMessage: "([^"]+)"/);
                if (botMessage) {
                    // Display the bot's response with "CPS" as the sender name
                    const botMessageDiv = document.createElement('div');
                    botMessageDiv.classList.add('message', 'bot-message');
                    botMessageDiv.innerHTML = '<strong>CPS:</strong> ' + botMessage[1];
                    document.getElementById('messages').appendChild(botMessageDiv);

                    // Scroll to the bottom after bot's message is added
                    scrollToBottom();
                }
            });
        }

        // Clear the input field after message is sent
        document.getElementById('userMessage').value = '';
    });
</script>

<!-- Custom CSS -->
<style>
    .chat-button button {
    background-color: #25D366;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 50px;
    font-size: 14px;
    cursor: pointer;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
}

.chat-button {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
}


   .chatbot-container {
    width: 100%;
    max-width: 600px;
    position: fixed;
    bottom: 80px;
    right: 20px;
    z-index: 9999;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

#chatbox {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 10px;
    max-height: 400px;
    overflow-y: auto;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}


    #chatbox {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 10px;
        height: 400px;
        max-height: 400px;
        overflow-y: auto;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .message {
        display: block;
        max-width: 70%;
        margin-bottom: 10px;
        padding: 10px;
        border-radius: 15px;
        font-size: 14px;
        word-wrap: break-word; /* Ensures long messages break into new lines */
    }

    .user-message {
        background-color: #dcf8c6;
        align-self: flex-start;
        margin-left: 5%;
        margin-right: 0;
    }

    .bot-message {
        background-color: #ffffff;
        align-self: flex-end;
        margin-left: 0;
        margin-right: 5%;
    }

    .input-group input {
        border-radius: 20px 0 0 20px;
    }

    .input-group button {
        border-radius: 0 20px 20px 0;
    }
    @media (max-width: 768px) {
    .chat-button {
        bottom: 15px;
        right: 15px;
    }

    .chat-button button {
        padding: 8px 15px;
        font-size: 12px;
    }

    .chatbot-container {
        width: 90%; /* Reduce width for mobile */
        bottom: 70px;
        right: 5%;
    }

    #chatbox {
        height: 300px; /* Adjust height for smaller screens */
    }

    .message {
        font-size: 12px;
    }
}

@media (max-width: 480px) {
    .chat-button button {
        padding: 5px 10px;
        font-size: 10px;
    }

    .chatbot-container {
        width: 95%;
        bottom: 60px;
        right: 2.5%;
    }

    #chatbox {
        height: 250px;
    }

    .message {
        font-size: 10px;
    }
}
.ad-container {
  margin: 20px 0;
  text-align: center;
  background-color: #f0f0f0; /* Optional: Add background color for better visibility */
  padding: 10px; /* Optional: Add some padding */
  border-radius: 8px; /* Optional: Add rounded corners */
  box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1); /* Optional: Add a subtle shadow */
}

</style>
<div style="background-color: #f9f9f9; padding: 20px; text-align: center; margin: 20px 0; border: 1px solid #ccc; border-radius: 10px;">
  <!-- YouTube Banner Section -->
  <h2 style="color: #ff0000; font-family: Arial, sans-serif;">Subscribe to Our YouTube Channel</h2>
  <p>Stay updated with our latest videos on graphics, motion design, and predictions!</p>
  <a href="https://www.youtube.com/@emmanuelkirui9043?sub_confirmation=1" target="_blank" style="text-decoration: none;">
    <button style="background-color: #ff0000; color: white; border: none; padding: 10px 20px; font-size: 16px; border-radius: 5px; cursor: pointer;">
      Subscribe Now
    </button>
  </a>

  <!-- YouTube Subscribe Widget Button -->
  <div class="text-center my-4">
      <script src="https://apis.google.com/js/platform.js"></script>
      <div class="g-ytsubscribe" data-channelid="UCowmIx_sYZ6qi8PtaAtCGUg" data-layout="full" data-count="default"></div>
  </div>

  <!-- Latest Videos Section -->
  <div id="youtube-videos" style="margin-top: 30px;">
    <h3>Most Viewed Videos</h3>
    <div id="video-list" style="display: flex; flex-wrap: wrap; justify-content: center; gap: 15px;"></div>
  </div>

  <!-- Adsterra Ad Code -->








</div>

<script>
  const apiKey = 'AIzaSyB8Z2g9N8VrPycbFVj4utcig69gkPT5ZJw'; // Replace with your API key
  const channelId = 'UCowmIx_sYZ6qi8PtaAtCGUg'; // Replace with your channel ID
  const videoList = document.getElementById('video-list');
  let videoCache = []; // Cache to store fetched videos
  let currentIndex = 0; // Index to track the current set of videos
  const maxResults = 4; // Number of videos to display at a time
  const slideInterval = 2 * 60 * 1000; // 2 minutes in milliseconds

  // Fetch videos sorted by view count
  async function fetchVideos() {
    const url = `https://www.googleapis.com/youtube/v3/search?key=${apiKey}&channelId=${channelId}&part=snippet&type=video&order=viewCount&maxResults=50`;
    try {
      const response = await fetch(url);
      const data = await response.json();
      videoCache = data.items; // Store videos in the cache
      displayVideos(); // Display the first set of videos
    } catch (error) {
      console.error('Error fetching videos:', error);
    }
  }

  // Display a set of videos based on the current index
  function displayVideos() {
    videoList.innerHTML = ''; // Clear previous content
    const videosToShow = videoCache.slice(currentIndex, currentIndex + maxResults);

    videosToShow.forEach(video => {
      const videoId = video.id.videoId;
      const thumbnail = video.snippet.thumbnails.medium.url;
      const title = video.snippet.title;
      const videoLink = `https://www.youtube.com/watch?v=${videoId}`;

      const videoCard = document.createElement('div');
      videoCard.style.width = '200px';
      videoCard.innerHTML = `
        <a href="${videoLink}" target="_blank" style="text-decoration: none; color: inherit;">
          <img src="${thumbnail}" alt="${title}" style="width: 100%; border-radius: 5px;">
          <p style="font-size: 14px; margin: 5px 0;">${title}</p>
        </a>
      `;
      videoList.appendChild(videoCard);
    });

    // Update the current index for the next set
    currentIndex = (currentIndex + maxResults) % videoCache.length;
  }

  // Automatically rotate videos every 2 minutes
  function startSlideshow() {
    setInterval(displayVideos, slideInterval);
  }

  fetchVideos(); // Fetch videos from YouTube
  startSlideshow(); // Start the slideshow
</script>





    <!-- Recent Posts Section -->
    <div class="row <?php echo $mt3_i; ?>">
        <h5><i class="fa fa-list"></i> Recent Posts</h5>

        <?php
        $run = mysqli_query($connect, "SELECT * FROM `posts` WHERE active='Yes' ORDER BY id DESC LIMIT 8");
        $count = mysqli_num_rows($run);
        if ($count <= 0) {
            echo '<p>There are no published posts</p>';
        } else {
            while ($row = mysqli_fetch_assoc($run)) {
                
                $image = "";
                if($row['image'] != "") {
                    $image = '<img src="' . $row['image'] . '" alt="' . $row['title'] . '" class="card-img-top" width="100%" height="208em" />';
                } else {
                    $image = '<svg class="bd-placeholder-img card-img-top" width="100%" height="13em" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Placeholder: Thumbnail" preserveAspectRatio="xMidYMid slice" focusable="false">
                    <title>No Image</title><rect width="100%" height="100%" fill="#55595c"/>
                    <text x="40%" y="50%" fill="#eceeef" dy=".3em">No Image</text></svg>';
                }

                echo '
                        <div class="';
                if ($settings['posts_per_row'] == 3) {
                    echo 'col-md-4';
                } else {
                    echo 'col-md-6';
                }
                echo ' mb-3"> 
                            <div class="card shadow-sm">
                                <a href="post?name=' . $row['slug'] . '">
                                    '. $image .'
                                </a>
                                <div class="card-body">
                                    <a href="post?name=' . $row['slug'] . '"><h6 class="card-title">' . $row['title'] . '</h6></a>
                                    <p class="card-text"><small class="text-muted">
                                    <i class="fas fa-calendar"></i> ' . date($settings['date_format'], strtotime($row['date'])) . ', ' . $row['time'] . '</small></p>
                                    <a href="post?name=' . $row['slug'] . '" class="btn btn-sm btn-outline-dark">Read More</a>
                                </div>
                            </div>
                        </div>';
            }
        }
        ?>

    </div>
</div>

<?php
if ($settings['sidebar_position'] == 'Right') {
    sidebar();
}
footer();
?>
<!-- JavaScript Links -->
    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
        // Show loader when the page starts loading
        window.onload = function() {
            // Hide the loader after the page is fully loaded
            document.getElementById("loader-wrapper").style.display = "none";
            document.getElementById("main-content").style.display = "block";
        };

        // Show loader before the page unloads (refresh or navigation)
        window.onbeforeunload = function() {
            document.getElementById("loader-wrapper").style.display = "block";
            document.getElementById("main-content").style.display = "none";
        };

        // Loader for async data loading (example)
        function fetchData() {
            // Show loader
            document.getElementById("loader-wrapper").style.display = "block";
            document.getElementById("main-content").style.display = "none";

            // Simulate async operation (e.g., AJAX request)
            setTimeout(function() {
                // Hide loader after data is loaded
                document.getElementById("loader-wrapper").style.display = "none";
                document.getElementById("main-content").style.display = "block";
            }, 2000); // Simulating 2-second data load time
        }
    </script>
</body>
</html>
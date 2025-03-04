<?php
// Include PHPMailer files and database connection
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';
require '../PHPMailer/src/Exception.php';
require '../db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $subject = $_POST['subject'];  // Subject from form
    $message = $_POST['message'];  // Message content from TinyMCE
    $gif = $_FILES['gif'];

    // Ensure that message content is valid and clean
    $message = htmlspecialchars($message);  // Sanitize HTML content

    // Fetch subscriber emails and usernames from the database
    try {
        $stmt = $dbh->prepare("
            SELECT 
                newsletter.email, 
                users.username 
            FROM 
                newsletter 
            INNER JOIN 
                users 
            ON 
                newsletter.email = users.email
        ");
        $stmt->execute();
        $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo 'Error fetching subscribers: ' . $e->getMessage();
        exit;
    }

    if ($subscribers) {
        // Loop through each subscriber and send the email
        foreach ($subscribers as $subscriber) {
            $recipient = $subscriber['email'];
            $username = $subscriber['username'];

            // Create a new PHPMailer instance
            $mail = new PHPMailer(true);

            try {
                // SMTP Settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'emmanuelkirui042@gmail.com'; // Your Gmail address
                $mail->Password = 'unwv yswa pqaq hefc'; // Your App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Sender and recipient settings
                $mail->setFrom('noreply@gmail.com', 'Creative Pulse Solutions (CEO)');
                $mail->addAddress($recipient); // Recipient email from the database

                // If a GIF is attached, add it
                if (!empty($gif['name'])) {
                    $mail->addAttachment($gif['tmp_name'], $gif['name']);
                    $message .= "<br><img src='cid:" . $gif['name'] . "' alt='Image'>";
                }

                // Add social media links dynamically with icons
                $youtubeLink = $_POST['youtube'];
                $facebookLink = $_POST['facebook'];
                $twitterLink = $_POST['twitter'];
                $instagramLink = $_POST['instagram'];
                $tiktokLink = $_POST['tiktok']; // TikTok Link

                // Decorated email content with emojis and improved styles
                $emailBody = "
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                    <style>
                        /* Responsive Styling */
                        @media only screen and (max-width: 600px) {
                            .container { width: 100% !important; padding: 10px; }
                            .header h1 { font-size: 22px; }
                            .content, .offers, .services { padding: 15px; }
                            .button { padding: 10px 20px; font-size: 14px; }
                        }
                    </style>
                </head>
                <body style='margin:0; padding:0; font-family: Arial, sans-serif; background-color: #f9f9f9; color: #333333;'>
                    <div class='container' style='width: 100%; max-width: 600px; margin: auto; background-color: #ffffff; border-radius: 10px; overflow: hidden;'>
                        <!-- Header -->
                        <div class='header' style='background-color: #0d6efd; color: #ffffff; text-align: center; padding: 20px;'>
                            <h1 style='margin: 0; font-size: 26px;'>Creative Pulse Solutions 🤗</h1>
                        </div>

                        <!-- Content -->
                        <div class='content' style='padding: 20px;'>
                            <p style='margin: 0 0 15px;'>Hello <strong>$username</strong>,</p>
                            <p style='margin: 0 0 15px;'>$message</p>
                            <p style='margin: 0 0 15px;'>We are so glad to have you with us. Keep shining, keep growing! 💃</p>
                            <a href='#' style='display: inline-block; background-color: #0d6efd; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold; text-align: center;'>
                                Visit Our Website
                            </a>
                        </div>

                        <!-- Offers Section -->
                        <div class='offers' style='background-color: #f0f8ff; padding: 20px; border-radius: 10px; margin: 20px;'>
                            <h2 style='color: #0d6efd; font-size: 22px;'>Special Offers Just for You!</h2>
                            <p style='margin: 0 0 10px;'>Check out our latest promotions and exclusive deals to help you grow your business and brand:</p>
                            <ul style='list-style: none; padding: 0; margin: 0;'>
                                <li style='margin-bottom: 10px;'>✨ Get 20% off on all Motion Graphics Design packages!</li>
                                <li style='margin-bottom: 10px;'>✨ Free music production consultation for your next big project!</li>
                                <li style='margin-bottom: 10px;'>✨ Free website audit with any new Web Design package!</li>
                                <li style='margin-bottom: 10px;'>✨ Exclusive football predictions for next week's big matches!</li>
                            </ul>
                        </div>

                        <!-- Services Section -->
                        <div class='services' style='padding: 20px; border: 1px solid #0d6efd; border-radius: 10px; margin: 20px;'>
                            <h2 style='text-align: center; color: #0d6efd; font-size: 22px;'>Our Services</h2>
                            <ul style='list-style: none; padding: 0; margin: 0;'>
                                <li style='margin-bottom: 10px;'>🎨 <strong>Graphics & Motion Design:</strong> Stunning visuals and motion graphics.</li>
                                <li style='margin-bottom: 10px;'>🎵 <strong>Music Production:</strong> Crafting unique soundscapes.</li>
                                <li style='margin-bottom: 10px;'>💻 <strong>Web Designing:</strong> Responsive, user-friendly websites.</li>
                                <li style='margin-bottom: 10px;'>⚽ <strong>Football Predictions:</strong> Data-driven predictions to keep you ahead.</li>
                            </ul>
                        </div>

                        <!-- Footer -->
                        <div class='footer' style='background-color: #0d6efd; color: #ffffff; text-align: center; padding: 15px;'>
                            <p style='margin: 0; font-size: 14px;'>&copy; 2024 Creative Pulse Solutions. All rights reserved.</p>
                            <div style='margin-top: 10px;'>
                                <a href='$youtubeLink' style='color: #ffffff; margin-right: 10px; text-decoration: none;'>YouTube</a>
                                <a href='$facebookLink' style='color: #ffffff; margin-right: 10px; text-decoration: none;'>Facebook</a>
                                <a href='$twitterLink' style='color: #ffffff; margin-right: 10px; text-decoration: none;'>Twitter</a>
                                <a href='$instagramLink' style='color: #ffffff; margin-right: 10px; text-decoration: none;'>Instagram</a>
                                <a href='$tiktokLink' style='color: #ffffff; text-decoration: none;'>TikTok</a>
                            </div>
                        </div>
                    </div>
                </body>
                </html>";

                // Set email content
                $mail->isHTML(true);
                $mail->Subject = $subject; // Subject from form
                $mail->Body = $emailBody; // HTML styled content
                $mail->AltBody = strip_tags($emailBody); // Plain text for non-HTML clients

                // Send email
                $mail->send();
            } catch (Exception $e) {
                echo "Error sending email to $recipient: {$mail->ErrorInfo}\n";
            }
        }
        echo 'Emails sent successfully!';
        // Redirect to dashboard after sending emails
        header("Location: dashboard.php");
        exit;  // Don't forget to call exit() to stop further script execution
    } else {
        echo 'No subscribers found.';
    }
} else {
    echo 'Invalid request method.';
}
?>

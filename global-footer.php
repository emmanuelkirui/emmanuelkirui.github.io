<?php
$year = date("Y"); // Auto-updating year
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Footer</title>

    <!-- Free Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        /* Footer Styles */
        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 20px;
            margin: 20px; /* Adds space around */
            border-top: 3px solid #f39c12;
        }

        .footer-container {
            max-width: 1200px;
            margin: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Footer Links */
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 15px 0;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .footer-links li {
            margin: 0;
        }

        .footer-links a {
            color: #f39c12;
            text-decoration: none;
            font-size: 16px;
            font-weight: bold;
            transition: color 0.3s ease-in-out;
        }

        .footer-links a:hover {
            color: #e67e22;
            text-decoration: underline;
        }

        /* Social Media Icons */
        .social-icons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 2px solid #f39c12; /* Separator */
        }

        .social-icons a {
            color: #f39c12;
            font-size: 24px;
            transition: color 0.3s ease;
        }

        .social-icons a:hover {
            color: #e67e22;
        }

        /* Gambling Disclaimer */
        .gambling-disclaimer {
            font-size: 14px;
            background: #222;
            color: #ffcc00;
            padding: 10px;
            border-radius: 5px;
            margin-top: 15px;
        }

        /* Cookie Consent */
        #cookieConsent {
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px;
            text-align: center;
            display: none;
            border-radius: 5px;
        }

        #cookieConsent button {
            background: #f39c12;
            color: white;
            border: none;
            padding: 5px 10px;
            margin-left: 10px;
            cursor: pointer;
            border-radius: 3px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .footer-links {
                flex-direction: column;
                align-items: center;
            }
            
            .footer-links a {
                font-size: 14px;
            }
            
            .disclaimer, .gambling-disclaimer {
                font-size: 12px;
            }
            
            .social-icons {
                gap: 10px;
            }

            .social-icons a {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>

<!-- Footer -->
<footer class="footer">
    <div class="footer-container">
        <p>&copy; <?php echo $year; ?> Emmanuel Kirui. All rights reserved.</p>
        
        <ul class="footer-links">
          <li><a href="terms-and-conditions.php"><i class="fas fa-file-contract"></i> Terms & Conditions</a></li>
          <li><a href="privacy-policy.php"><i class="fas fa-user-shield"></i> Privacy Policy</a></li>
          <li><a href="third-party.php"><i class="fas fa-handshake"></i> Third-Party Services</a></li>
          <li><a href="docs.php"><i class="fas fa-folder-open"></i> Documentation</a></li>
          <li><a href="aboutus.php"><i class="fas fa-info-circle"></i> About Us</a></li>
          <li><a href="contactus.php"><i class="fas fa-envelope"></i> Contact Us</a></li><ul class="footer-links" aria-label="Footer Navigation">
        </ul>

        <p class="gambling-disclaimer">
            <i class="fa fa-exclamation-triangle"></i> Responsible Gambling: This site is for **18+ users** only. 
            If you or someone you know has a gambling problem, seek help. Play responsibly.
        </p>

        <p class="disclaimer">
            This site uses third-party services, cookies, and advertisements. These third parties have their own policies, and we are not responsible for their actions. Please review their terms.
        </p>

        <!-- Social Media Icons (Moved to Bottom) -->
        <div class="social-icons">
            <a href="https://facebook.com/emmanuelkirui042" target="_blank"><i class="fab fa-facebook"></i></a>
            <a href="#" target="_blank"><i class="fab fa-twitter"></i></a>
            <a href="#" target="_blank"><i class="fab fa-instagram"></i></a>
            <a href="https://youtube.com/@emmanuelkirui9043" target="_blank"><i class="fab fa-youtube"></i></a>
            <a href="https://tiktok.com/@emmanuelkirui3" target="_blank"><i class="fab fa-tiktok"></i></a>
        </div>
    </div>
</footer>

<!-- Cookie Consent -->
<div id="cookieConsent">
    <p><i class="fa fa-cookie-bite"></i> This site uses cookies to improve user experience. 
        <a href="privacy-policy.php">No-Manage</a>.
    </p>
    <button id="acceptCookies">Accept</button>
</div>

<!-- Cookie Consent Script -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    if (!localStorage.getItem("cookiesAccepted")) {
        document.getElementById("cookieConsent").style.display = "block";
    }
    document.getElementById("acceptCookies").addEventListener("click", function () {
        localStorage.setItem("cookiesAccepted", "true");
        document.getElementById("cookieConsent").style.display = "none";
    });
});
</script>

</body>
</html>

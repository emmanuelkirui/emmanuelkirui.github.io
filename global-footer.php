<?php
$year = date("Y"); // Auto-updating year

// Set the previous_page cookie to the current page URL if not already set and not on recaptcha_handler.php
if (!isset($_COOKIE['previous_page']) && !str_contains($_SERVER['REQUEST_URI'], 'recaptcha_handler.php')) {
    $currentPage = $_SERVER['REQUEST_URI'];
    setcookie('previous_page', $currentPage, time() + 3600, '/'); // Expires in 1 hour, site-wide path
}
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
            padding: 20px 10px; /* Reduced side padding */
            border-top: 3px solid #f39c12;
            font-family: Arial, sans-serif;
            width: 100%; /* Ensure footer spans full width */
            box-sizing: border-box; /* Include padding in width */
        }

        .footer-container {
            max-width: 1200px; /* Adjusted for better fit */
            margin: 0 auto; /* Centered with no extra top/bottom margin */
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px; /* Consistent spacing between elements */
        }

        /* Footer Links */
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0; /* Removed unnecessary margin */
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px; /* Controlled spacing */
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
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #f39c12;
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
            margin: 0; /* Removed top margin */
            width: 100%; /* Full width within container */
            box-sizing: border-box;
        }

        /* Disclaimer */
        .disclaimer {
            font-size: 14px;
            color: #666;
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
            width: 100%; /* Full width within container */
            box-sizing: border-box;
        }

        .disclaimer a {
            color: #007bff;
            text-decoration: none;
            font-weight: normal;
        }

        .disclaimer a:hover {
            text-decoration: underline;
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

        /* Media Queries */

        /* Mobile Devices (up to 768px) */
        @media (max-width: 768px) {
            .footer {
                padding: 15px 5px; /* Further reduced padding */
            }

            .footer-container {
                max-width: 100%; /* Full width on mobile */
                padding: 0 10px; /* Controlled padding */
            }

            .footer-links {
                flex-direction: column;
                align-items: center;
                gap: 10px;
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

            #cookieConsent {
                bottom: 10px;
                left: 10px;
                right: 10px;
                padding: 8px;
            }

            #cookieConsent p {
                font-size: 12px;
            }

            #cookieConsent button {
                padding: 4px 8px;
                font-size: 12px;
            }
        }

        /* Tablets (769px to 1024px) */
        @media (min-width: 769px) and (max-width: 1024px) {
            .footer-container {
                max-width: 90%; /* Slightly reduced width */
            }

            .footer-links {
                gap: 20px;
            }

            .footer-links a {
                font-size: 15px;
            }

            .social-icons a {
                font-size: 22px;
            }

            .disclaimer, .gambling-disclaimer {
                font-size: 13px;
            }
        }

        /* Desktops (1025px and above) */
        @media (min-width: 1025px) {
            .footer {
                padding: 25px 15px; /* Adjusted padding */
            }

            .footer-container {
                max-width: 1200px; /* Consistent max-width */
            }

            .footer-links {
                gap: 25px;
            }

            .footer-links a {
                font-size: 18px;
            }

            .social-icons {
                gap: 20px;
            }

            .social-icons a {
                font-size: 26px;
            }

            .disclaimer, .gambling-disclaimer {
                font-size: 16px;
            }

            #cookieConsent {
                bottom: 30px;
                left: 50%;
                transform: translateX(-50%);
                max-width: 600px;
                right: auto;
                padding: 15px;
            }

            #cookieConsent p {
                font-size: 16px;
            }

            #cookieConsent button {
                padding: 6px 12px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

<!-- Footer -->
<footer class="footer">
    <div class="footer-container">
        <p>© <?php echo $year; ?> Emmanuel Kirui. All rights reserved.</p>

        <ul class="footer-links">
            <li><a href="terms-conditions.php"><i class="fas fa-file-contract"></i> Terms & Conditions</a></li>
            <li><a href="privacy-policy.php"><i class="fas fa-user-shield"></i> Privacy Policy</a></li>
            <li><a href="third-party.php"><i class="fas fa-handshake"></i> Third-Party Services</a></li>
            <li><a href="docs.php"><i class="fas fa-folder-open"></i> Documentation</a></li>
            <li><a href="aboutus.php"><i class="fas fa-info-circle"></i> About Us</a></li>
            <li><a href="contactus.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
            <li><a href="global-blog.php"><i class="fas fa-blog"></i> Blog</a></li>
            <li><a href="our-team.php"><i class="fas fa-users"></i> Our Team</a></li>
            <li><a href="data_request.php"><i class="fas fa-database"></i> Data Requests</a></li>
        </ul>

        <p class="gambling-disclaimer">
            <i class="fa fa-exclamation-triangle"></i> Responsible Gambling: This site is for <strong style="color: #ff0000;">18+</strong> users only.
            If you or someone you know has a gambling problem, seek help. Play responsibly.
        </p>

        <p class="disclaimer">
            This site uses third-party services, cookies, and advertisements. These third parties have their own policies, and we are not responsible for their actions. Please review their terms.
            <a href="privacy-policy.php">View our Privacy Policy</a>
        </p>

        <!-- Social Media Icons -->
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
        <a href="privacy-policy.php">Learn More</a>.
    </p>
    <button id="acceptCookies">Accept</button>
</div>

<!-- Cookie Consent Script with Expiration -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const cookieConsent = document.getElementById("cookieConsent");
    const acceptCookiesButton = document.getElementById("acceptCookies");
    const cookieKey = "cookiesAccepted";
    const expirationDays = 30;

    function isCookieConsentValid() {
        const consentData = JSON.parse(localStorage.getItem(cookieKey));
        if (!consentData) return false;

        const expirationDate = new Date(consentData.expires);
        return new Date() < expirationDate;
    }

    if (!isCookieConsentValid()) {
        cookieConsent.style.display = "block";
    }

    acceptCookiesButton.addEventListener("click", function () {
        const expirationDate = new Date();
        expirationDate.setDate(expirationDate.getDate() + expirationDays);

        const consentData = {
            accepted: true,
            expires: expirationDate.toISOString()
        };
        localStorage.setItem(cookieKey, JSON.stringify(consentData));

        cookieConsent.style.display = "none";
    });
});
</script>

</body>
</html>

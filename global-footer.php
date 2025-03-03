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
            margin: 20px; /* Kept original margin */
            border-top: 3px solid #f39c12;
            font-family: Arial, sans-serif;
        }

        .footer-container {
            margin: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%; /* Allow dynamic width adjustment */
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
            margin-top: 15px;
        }

        /* Disclaimer */
        .disclaimer {
            font-size: 14px;
            color: #666;
            background-color: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-family: inherit;
        }

        .disclaimer a {
            font-size: inherit;
            font-family: inherit;
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
                padding: 10px;
                margin: 10px;
            }

            .footer-container {
                max-width: 100%;
                padding: 0 10px; /* Add slight padding for content */
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
                padding: 8px;
                max-width: 90%; /* Prevent overflow */
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
            .footer {
                padding: 15px;
                margin: 15px;
            }

            .footer-container {
                max-width: 90%; /* Dynamic width adjustment */
                padding: 0 15px;
            }

            .footer-links {
                gap: 20px;
                flex-direction: row; /* Keep horizontal layout */
                flex-wrap: wrap; /* Allow wrapping if needed */
            }

            .footer-links a {
                font-size: 15px;
            }

            .social-icons {
                gap: 15px;
            }

            .social-icons a {
                font-size: 22px;
            }

            .disclaimer, .gambling-disclaimer {
                font-size: 13px;
                max-width: 85%; /* Slightly narrower than mobile */
            }

            #cookieConsent {
                bottom: 15px;
                left: 15px;
                right: 15px;
                padding: 12px;
            }

            #cookieConsent p {
                font-size: 14px;
            }
        }

        /* Desktops (1025px and above) */
        @media (min-width: 1025px) {
            .footer {
                padding: 20px;
                margin: 20px;
            }

            .footer-container {
                max-width: 1200px; /* Fixed max-width like cookie consent */
                padding: 0 20px;
            }

            .footer-links {
                gap: 25px;
                flex-direction: row;
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
                padding: 15px;
                max-width: 80%; /* Comfortable width for desktops */
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
        </ul>

        <p class="gambling-disclaimer">
            <i class="fa fa-exclamation-triangle"></i> Responsible Gambling: This site is for **18+ users** only.
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
        <a href="privacy-policy.php">Manage</a>.
    </p>
    <button id="acceptCookies">Accept</button>
</div>

<!-- Cookie Consent Script with Expiration -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const cookieConsent = document.getElementById("cookieConsent");
    const acceptCookiesButton = document.getElementById("acceptCookies");
    const cookieKey = "cookiesAccepted";
    const expirationDays = 30; // Set expiration to 30 days (adjust as needed)

    // Function to check if cookie consent is still valid
    function isCookieConsentValid() {
        const consentData = JSON.parse(localStorage.getItem(cookieKey));
        if (!consentData) return false;

        const expirationDate = new Date(consentData.expires);
        return new Date() < expirationDate; // Check if current date is before expiration
    }

    // Show cookie consent popup if not accepted or expired
    if (!isCookieConsentValid()) {
        cookieConsent.style.display = "block";
    }

    // Handle the "Accept" button click
    acceptCookiesButton.addEventListener("click", function () {
        // Calculate expiration date
        const expirationDate = new Date();
        expirationDate.setDate(expirationDate.getDate() + expirationDays);

        // Store consent with expiration in localStorage
        const consentData = {
            accepted: true,
            expires: expirationDate.toISOString()
        };
        localStorage.setItem(cookieKey, JSON.stringify(consentData));

        // Hide the popup
        cookieConsent.style.display = "none";
    });
});
</script>

</body>
</html>

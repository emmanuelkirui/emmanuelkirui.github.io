<?php
$year = date("Y"); // Auto-updating year
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Footer for Emmanuel Kirui's website - Links to key pages, social media, and privacy notices.">
    <meta name="author" content="Emmanuel Kirui">
    <meta name="keywords" content="footer, Emmanuel Kirui, terms, privacy, contact, social media">
    <title>Footer | Emmanuel Kirui</title>

    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">

    <!-- Free Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1vW6TfH6PfnEX7uL9r6Qz1D8rW9V8eB5eJ5eJ7eK9rL6rW8eL5fJ6eJ7rW9V8eB5eJ5eJ7eK9rL6r==" crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        /* Footer Styles */
        .footer {
            background: #2d3436;
            color: #dfe6e9;
            text-align: center;
            padding: 30px 20px;
            margin: 20px 0 0; /* Adjusted for integration with pages */
            border-top: 4px solid #f39c12;
            font-family: 'Roboto', Arial, sans-serif;
        }
        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        }

        /* Footer Links */
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .footer-links li {
            margin: 0;
        }
        .footer-links a {
            color: #f39c12;
            text-decoration: none;
            font-size: 16px;
            font-weight: 700;
            transition: color 0.3s ease-in-out;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .footer-links a:hover {
            color: #e67e22;
            text-decoration: underline;
        }

        /* Social Media Icons */
        .social-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 2px solid #f39c12;
        }
        .social-icons a {
            color: #f39c12;
            font-size: 26px;
            transition: color 0.3s ease, transform 0.3s ease;
        }
        .social-icons a:hover {
            color: #e67e22;
            transform: translateY(-3px);
        }

        /* Gambling Disclaimer */
        .gambling-disclaimer {
            font-size: 14px;
            background: #222;
            color: #ffcc00;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
            max-width: 800px;
            line-height: 1.6;
        }

        /* General Disclaimer */
        .disclaimer {
            font-size: 14px;
            color: #b2bec3;
            margin-top: 10px;
            max-width: 800px;
        }

        /* Cookie Consent */
        #cookieConsent {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            max-width: 600px;
            width: 90%;
            background: rgba(0, 0, 0, 0.85);
            color: #dfe6e9;
            padding: 15px;
            text-align: center;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            display: none;
            z-index: 1000;
        }
        #cookieConsent p {
            margin: 0 0 10px;
            font-size: 14px;
        }
        #cookieConsent a {
            color: #f39c12;
            text-decoration: underline;
        }
        #cookieConsent a:hover {
            color: #e67e22;
        }
        #cookieConsent button {
            background: #f39c12;
            color: #ffffff;
            border: none;
            padding: 8px 15px;
            margin-left: 10px;
            cursor: pointer;
            border-radius: 5px;
            font-weight: 700;
            transition: background 0.3s ease;
        }
        #cookieConsent button:hover {
            background: #e67e22;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .footer-links {
                flex-direction: column;
                gap: 15px;
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
            .gambling-disclaimer, .disclaimer {
                font-size: 13px;
            }
            #cookieConsent {
                bottom: 10px;
                padding: 10px;
            }
            #cookieConsent p {
                font-size: 13px;
            }
        }
        @media (max-width: 480px) {
            .footer {
                padding: 20px 15px;
            }
            .footer-links a {
                font-size: 14px;
            }
            .social-icons a {
                font-size: 20px;
            }
            .gambling-disclaimer, .disclaimer {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>

<!-- Footer -->
<footer class="footer" role="contentinfo">
    <div class="footer-container">
        <p>© <?php echo $year; ?> Emmanuel Kirui. All rights reserved.</p>

        <ul class="footer-links" aria-label="Footer Navigation">
            <li><a href="terms-and-conditions.php"><i class="fas fa-file-contract"></i> Terms & Conditions</a></li>
            <li><a href="privacy-policy.php"><i class="fas fa-user-shield"></i> Privacy Policy</a></li>
            <li><a href="third-party.php"><i class="fas fa-handshake"></i> Third-Party Services</a></li>
            <li><a href="docs.php"><i class="fas fa-folder-open"></i> Documentation</a></li>
            <li><a href="aboutus.php"><i class="fas fa-info-circle"></i> About Us</a></li>
            <li><a href="contactus.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
        </ul>

        <p class="gambling-disclaimer">
            <i class="fas fa-exclamation-triangle"></i> <strong>Responsible Gambling:</strong> This site is intended for users aged <strong>18+</strong> only. 
            Gambling can be addictive. If you or someone you know struggles with gambling, please seek professional help. Play responsibly.
        </p>

        <p class="disclaimer">
            This site uses third-party services, cookies, and advertisements. These third parties operate under their own policies, and we are not liable for their practices. Please review our <a href="third-party.php">Third-Party Services</a> page for more information.
        </p>

        <div class="social-icons" aria-label="Social Media Links">
            <a href="https://facebook.com/emmanuelkirui042" target="_blank" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
            <a href="#" target="_blank" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
            <a href="#" target="_blank" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
            <a href="https://youtube.com/@emmanuelkirui9043" target="_blank" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
            <a href="https://tiktok.com/@emmanuelkirui3" target="_blank" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
        </div>
    </div>
</footer>

<!-- Cookie Consent -->
<div id="cookieConsent" role="alert" aria-live="polite">
    <p><i class="fas fa-cookie-bite"></i> We use cookies to enhance your experience on our site. 
        Learn more in our <a href="privacy-policy.php">Privacy Policy</a>.
    </p>
    <button id="acceptCookies" aria-label="Accept Cookies">Accept</button>
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

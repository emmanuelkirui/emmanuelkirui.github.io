<?php
$year = date("Y"); // Auto-updating year
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Footer</title>

    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/YOUR-KIT-ID.js" crossorigin="anonymous"></script>

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
        }
    </style>
</head>
<body>

<!-- Footer -->
<footer class="footer">
    <div class="footer-container">
        <p>&copy; <?php echo $year; ?> Emmanuel Kirui. All rights reserved.</p>
        
        <ul class="footer-links">
            <li><a href="#"><i class="fa-solid fa-file-contract"></i> Terms</a></li>
            <li><a href="#"><i class="fa-solid fa-user-shield"></i> Privacy</a></li>
            <li><a href="#"><i class="fa-solid fa-handshake"></i> Third-Party</a></li>
        </ul>

        <ul class="footer-links">
            <li><a href="#" target="_blank"><i class="fa-brands fa-facebook"></i> Facebook</a></li>
            <li><a href="#" target="_blank"><i class="fa-brands fa-x-twitter"></i> Twitter</a></li>
            <li><a href="#" target="_blank"><i class="fa-brands fa-instagram"></i> Instagram</a></li>
        </ul>

        <p class="gambling-disclaimer">
            <i class="fa-solid fa-triangle-exclamation"></i> Responsible Gambling: This site is for **18+ users** only. 
            If you or someone you know has a gambling problem, seek help. Play responsibly.
        </p>

        <p class="disclaimer">
            This site uses third-party services, cookies, and advertisements. These third parties have their own policies, and we are not responsible for their actions. Please review their terms.
        </p>
    </div>
</footer>

<!-- Cookie Consent -->
<div id="cookieConsent">
    <p><i class="fa-solid fa-cookie-bite"></i> This site uses cookies to improve user experience. 
        <a href="privacy-policy.html">Learn more</a>.
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

<?php
$year = date("Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Footer Section</title>
    
    <!-- Font Awesome (Online) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        /* Footer Styles */
        .footer {
            background-color: #333;
            color: #fff;
            padding: 20px;
            text-align: center;
            margin: 20px;
            border-radius: 10px;
        }
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .footer-links a {
            color: #f39c12;
            text-decoration: none;
            font-size: 16px;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        .footer-links a:hover {
            color: #e67e22;
            text-decoration: underline;
        }
        .social-icons {
            margin-top: 10px;
        }
        .social-icons a {
            color: #f39c12;
            font-size: 24px;
            margin: 0 15px;
            transition: color 0.3s ease;
        }
        .social-icons a:hover {
            color: #e67e22;
        }
        .disclaimer {
            font-size: 14px;
            color: #ddd;
            margin-top: 15px;
            line-height: 1.6;
        }
        /* Cookie Consent Banner */
        .cookie-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 15px;
            text-align: center;
            display: none;
            z-index: 1000;
        }
        .cookie-banner button {
            background: #f39c12;
            border: none;
            color: white;
            padding: 8px 15px;
            margin-left: 10px;
            cursor: pointer;
            font-weight: bold;
            border-radius: 5px;
        }
        .cookie-banner button:hover {
            background: #e67e22;
        }
    </style>
</head>
<body>

<!-- Footer Section -->
<footer class="footer">
    <p>&copy; <?php echo $year; ?> Emmanuel Kirui. All rights reserved.</p>
    <ul class="footer-links">
        <li><a href="#" onclick="openModal('termsModal')">Terms & Conditions</a></li>
        <li><a href="#" onclick="openModal('privacyModal')">Privacy Policy</a></li>
        <li><a href="#" onclick="openModal('thirdPartyModal')">Third-Party Services</a></li>
    </ul>
    
    <!-- Social Media Icons -->
    <div class="social-icons">
        <a href="https://www.youtube.com/@emmanuelkirui9043" target="_blank"><i class="fab fa-youtube"></i></a>
        <a href="https://www.tiktok.com/@emmanuelkirui3" target="_blank"><i class="fab fa-tiktok"></i></a>
    </div>
    
    <p class="disclaimer">
        This site is for users **18+** and may involve **gambling content**. Gamble responsibly.  
        We use third-party services that have their own terms & privacy policies. Review them before using.
    </p>
</footer>

<!-- Cookie Consent Banner -->
<div class="cookie-banner" id="cookieBanner">
    <p>This website uses cookies to ensure you get the best experience. By continuing, you agree to our 
        <a href="#" onclick="openModal('privacyModal')" style="color: #f39c12;">Privacy Policy</a>.
    </p>
    <button onclick="acceptCookies()">Accept</button>
    <button onclick="declineCookies()">Decline</button>
</div>

<!-- Modals -->
<div id="termsModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('termsModal')">&times;</span>
        <h2>Terms & Conditions</h2>
        <p>Welcome to our website. These terms and conditions outline the rules and regulations for the use of our services.</p>
    </div>
</div>

<div id="privacyModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('privacyModal')">&times;</span>
        <h2>Privacy Policy</h2>
        <p>Your privacy is important to us. This policy explains how we collect, use, and protect your information.</p>
    </div>
</div>

<div id="thirdPartyModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('thirdPartyModal')">&times;</span>
        <h2>Third-Party Services</h2>
        <p>We use third-party services. They have their own terms and privacy policies. Please review them before using.</p>
    </div>
</div>

<!-- JavaScript -->
<script>
    // Show Cookie Banner if not accepted before
    window.onload = function() {
        if (!localStorage.getItem("cookiesAccepted")) {
            document.getElementById("cookieBanner").style.display = "block";
        }
    };

    function acceptCookies() {
        localStorage.setItem("cookiesAccepted", "true");
        document.getElementById("cookieBanner").style.display = "none";
    }

    function declineCookies() {
        alert("You have declined cookies. Some features may not work properly.");
        document.getElementById("cookieBanner").style.display = "none";
    }

    // Open Modal
    function openModal(id) {
        document.getElementById(id).style.display = "block";
    }

    // Close Modal
    function closeModal(id) {
        document.getElementById(id).style.display = "none";
    }
</script>

</body>
</html>

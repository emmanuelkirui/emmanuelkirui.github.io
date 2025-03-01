<?php
$year = date("Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Privacy Policy for Emmanuel Kirui's website - Learn how we collect, use, and protect your personal information.">
    <meta name="author" content="Emmanuel Kirui">
    <title>Privacy Policy | Emmanuel Kirui</title>

    <!-- Google Font & Icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Roboto', Arial, sans-serif;
            margin: 0;
            padding: 40px 20px;
            background: linear-gradient(135deg, #f4f4f4, #e0e0e0);
            color: #333;
            line-height: 1.8;
        }
        .container {
            max-width: 900px;
            background: #ffffff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            margin: 0 auto;
            text-align: left;
        }
        h1 {
            color: #f39c12;
            font-size: 32px;
            text-align: center;
            margin-bottom: 30px;
        }
        h2 {
            color: #e67e22;
            font-size: 24px;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 2px solid #f39c12;
            padding-bottom: 5px;
        }
        p {
            font-size: 16px;
            margin: 10px 0;
            text-align: justify;
        }
        ul {
            margin: 10px 0 20px 20px;
            padding-left: 20px;
        }
        li {
            margin-bottom: 10px;
            font-size: 16px;
        }
        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: bold;
            color: #fff;
            background: #f39c12;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease-in-out;
            margin-top: 30px;
        }
        .back-button i {
            margin-right: 8px;
        }
        .back-button:hover {
            background: #e67e22;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .footer {
            margin-top: 40px;
            font-size: 14px;
            color: #777;
            text-align: center;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .contact-link {
            color: #f39c12;
            text-decoration: none;
        }
        .contact-link:hover {
            text-decoration: underline;
        }
        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }
            h1 {
                font-size: 28px;
            }
            h2 {
                font-size: 20px;
            }
            p, li {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h1><i class="fas fa-user-shield"></i> Privacy Policy</h1>
    <p>Last Updated: March 01, 2025</p>
    <p>Welcome to Emmanuel Kirui's Privacy Policy. Your privacy is of utmost importance to us. This policy outlines how we collect, use, disclose, and safeguard your personal information when you visit our website or interact with our services.</p>

    <h2>1. Information We Collect</h2>
    <p>We may collect the following types of information:</p>
    <ul>
        <li><strong>Personal Information:</strong> Name, email address, and other details you voluntarily provide when contacting us or subscribing to updates.</li>
        <li><strong>Usage Data:</strong> Information about how you interact with our website, such as IP address, browser type, pages visited, and time spent on the site.</li>
        <li><strong>Cookies:</strong> Small data files stored on your device to enhance your experience and track site performance (see Section 4 for details).</li>
    </ul>

    <h2>2. How We Use Your Information</h2>
    <p>Your information is used to:</p>
    <ul>
        <li>Improve our website and tailor content to your preferences.</li>
        <li>Respond to inquiries, provide support, or send updates if you’ve subscribed.</li>
        <li>Analyze site usage to enhance security and performance.</li>
        <li>Comply with legal obligations when necessary.</li>
    </ul>
    <p>We do not sell, trade, or otherwise misuse your personal data for unauthorized purposes.</p>

    <h2>3. How We Protect Your Information</h2>
    <p>We implement a variety of security measures, including:</p>
    <ul>
        <li>Encryption of sensitive data during transmission.</li>
        <li>Regular security audits and updates to protect against unauthorized access.</li>
        <li>Restricted access to personal information to authorized personnel only.</li>
    </ul>
    <p>However, no method of transmission over the internet is 100% secure, and we cannot guarantee absolute security.</p>

    <h2>4. Cookies and Tracking Technologies</h2>
    <p>We use cookies to enhance your browsing experience. Cookies help us:</p>
    <ul>
        <li>Remember your preferences.</li>
        <li>Analyze traffic and improve site functionality.</li>
    </ul>
    <p>You can disable cookies through your browser settings, but this may affect your experience on our site.</p>

    <h2>5. Third-Party Disclosure</h2>
    <p>We do not share your personal information with third parties except:</p>
    <ul>
        <li>When required by law or to protect our rights.</li>
        <li>With trusted service providers (e.g., hosting or analytics) who adhere to strict privacy standards.</li>
    </ul>

    <h2>6. Your Rights</h2>
    <p>You have the right to:</p>
    <ul>
        <li>Request access to the personal data we hold about you.</li>
        <li>Request correction or deletion of your data.</li>
        <li>Opt-out of communications at any time by unsubscribing.</li>
    </ul>
    <p>To exercise these rights, please contact us at <a href="mailto:privacy@emmanuelkirui.com" class="contact-link">privacy@emmanuelkirui.com</a>.</p>

    <h2>7. Changes to This Privacy Policy</h2>
    <p>We may update this policy periodically. Changes will be posted on this page with an updated "Last Updated" date. We encourage you to review this policy regularly.</p>

    <!-- Back Button -->
    <button class="back-button" onclick="history.back()">
        <i class="fas fa-arrow-left"></i> Return to Previous Page
    </button>

    <p class="footer">© <?php echo $year; ?> Emmanuel Kirui. All rights reserved.<br>Contact us at <a href="mailto:info@emmanuelkirui.com" class="contact-link">info@emmanuelkirui.com</a> for any inquiries.</p>
</div>

</body>
</html>

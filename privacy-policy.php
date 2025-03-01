<?php
$year = date("Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy</title>

    <!-- Google Font & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f4f4f4;
            color: #333;
            text-align: center;
        }
        .container {
            max-width: 800px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            margin: auto;
        }
        h2 {
            color: #f39c12;
            margin-bottom: 15px;
        }
        p {
            font-size: 16px;
            line-height: 1.6;
            margin: 10px 0;
        }
        .back-button {
            display: inline-block;
            padding: 10px 15px;
            font-size: 16px;
            font-weight: bold;
            color: white;
            background: #f39c12;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s ease-in-out;
            margin-top: 20px;
        }
        .back-button:hover {
            background: #e67e22;
        }
        .footer {
            margin-top: 20px;
            font-size: 14px;
            color: #777;
        }
    </style>
</head>
<body>

<div class="container">
    <h2><i class="fas fa-user-shield"></i> Privacy Policy</h2>
    <p>Your privacy is important to us. This policy explains how we collect, use, and protect your information.</p>
    <p>We collect data to improve user experience and ensure security. We do not sell or misuse personal data.</p>

    <!-- Back Button -->
    <button class="back-button" onclick="history.back()">
        <i class="fas fa-arrow-left"></i> Go Back
    </button>

    <p class="footer">&copy; <?php echo $year; ?> Emmanuel Kirui. All rights reserved.</p>
</div>

</body>
</html>

<?php
$year = date("Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Third-Party Services</title>

    <!-- Google Font & Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        /* General Styles */
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

        /* Back Button */
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

        /* Footer */
        .footer {
            margin-top: 20px;
            font-size: 14px;
            color: #777;
        }

        /* Responsive Design */
        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <h2><i class="fas fa-handshake"></i> Third-Party Services</h2>
    <p>We use third-party services, and they have their own terms and privacy policies. Please review them before using our services.</p>

    <!-- Back Button -->
    <button class="back-button" onclick="history.back()">
        <i class="fas fa-arrow-left"></i> Go Back
    </button>

    <!-- Footer -->
    <p class="footer">&copy; <?php echo $year; ?> Emmanuel Kirui. All rights reserved.</p>
</div>

</body>
</html>

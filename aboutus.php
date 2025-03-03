<?php
$year = date("Y");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Learn about Emmanuel Kirui - Our mission, values, and commitment to providing valuable services and content.">
    <meta name="author" content="Emmanuel Kirui">
    <meta name="keywords" content="about us, Emmanuel Kirui, mission, values, services">
    <meta name="robots" content="index, follow">
    <title>About Us | Emmanuel Kirui</title>

    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">

    <!-- External Stylesheets -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,700;1,400&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1vW6TfH6PfnEX7uL9r6Qz1D8rW9V8eB5eJ5eJ7eK9rL6rW8eL5fJ6eJ7rW9V8eB5eJ5eJ7eK9rL6r==" crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #2d3436;
            line-height: 1.8;
            padding: 50px 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            max-width: 960px;
            margin: 0 auto;
            background: #ffffff;
            padding: 50px;
            border-radius: 20px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease-in-out;
        }
        .container:hover { transform: translateY(-5px); }
        h1 {
            color: #f39c12;
            font-size: 36px;
            text-align: center;
            margin-bottom: 35px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        h2 {
            color: #e67e22;
            font-size: 26px;
            margin: 40px 0 20px;
            border-bottom: 3px solid #f39c12;
            padding-bottom: 8px;
            font-weight: 700;
        }
        p {
            font-size: 17px;
            margin: 15px 0;
            text-align: justify;
        }
        ul {
            margin: 15px 0 25px 25px;
            padding-left: 20px;
        }
        li {
            font-size: 17px;
            margin-bottom: 12px;
            position: relative;
            padding-left: 15px;
        }
        li::before {
            content: "•";
            color: #f39c12;
            position: absolute;
            left: 0;
            font-size: 20px;
        }
        a {
            color: #f39c12;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        a:hover {
            color: #e67e22;
            text-decoration: underline;
        }
        .back-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 25px;
            font-size: 17px;
            font-weight: 700;
            color: #ffffff;
            background: #f39c12;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-top: 40px;
        }
        .back-button i { margin-right: 10px; }
        .back-button:hover {
            background: #e67e22;
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .footer {
            margin-top: 50px;
            font-size: 15px;
            color: #6c757d;
            text-align: center;
            border-top: 1px solid #dee2e6;
            padding-top: 25px;
        }
        .contact-link {
            color: #f39c12;
            font-weight: 700;
        }
        .contact-link:hover { color: #e67e22; text-decoration: underline; }
        @media (max-width: 768px) {
            .container { padding: 30px; max-width: 100%; }
            h1 { font-size: 30px; }
            h2 { font-size: 22px; }
            p, li { font-size: 15px; }
            .back-button { padding: 12px 20px; font-size: 15px; }
        }
        @media (max-width: 480px) {
            body { padding: 30px 15px; }
            .container { padding: 20px; }
            h1 { font-size: 26px; }
            h2 { font-size: 20px; }
            p, li { font-size: 14px; }
        }
    </style>
</head>
<body>
    <main class="container" role="main">
        <header>
            <h1><i class="fas fa-info-circle"></i> About Us</h1>
        </header>

        <section>
            <p>Welcome to Emmanuel Kirui’s website (<a href="http://creativepulse.42web.io" class="contact-link">creativepulse.42web.io</a>)! I’m Emmanuel Kirui, a passionate developer, writer, and innovator dedicated to creating meaningful digital experiences. This platform reflects my commitment to sharing knowledge, building innovative solutions, and connecting with a global community.</p>
        </section>

        <section>
            <h2>Our Mission</h2>
            <p>My mission is to empower individuals with accessible tools, resources, and insights that inspire growth and creativity. Through this website, I aim to deliver value by providing high-quality content, fostering collaboration, and promoting transparency.</p>
        </section>

        <section>
            <h2>Our Values</h2>
            <p>We operate based on the following core principles:</p>
            <ul>
                <li><strong>Integrity:</strong> Upholding honesty and ethical standards in all interactions.</li>
                <li><strong>Innovation:</strong> Embracing creativity and forward-thinking solutions.</li>
                <li><strong>Community:</strong> Building a supportive and inclusive environment for all users.</li>
                <li><strong>Excellence:</strong> Striving for quality and continuous improvement in everything we do.</li>
            </ul>
        </section>

        <section>
            <h2>What We Offer</h2>
            <p>This website serves as a hub for educational content(Projects), professional services, personal projects, Football Predictions. Whether you’re here to learn, collaborate, or explore, I’m dedicated to ensuring your experience is both enriching and seamless.</p>
        </section>

        <section>
            <h2>Get in Touch</h2>
            <p>I’d love to hear from you! For questions, feedback, or collaboration opportunities, please reach out via our <a href="contactus" class="contact-link">Contact Us</a> page or email me directly at <a href="mailto:emmanuelkirui042@gmail.com" class="contact-link">emmanuelkirui042@gmail.com</a>.</p>
        </section>

        <button class="back-button" onclick="history.back()" aria-label="Return to Previous Page">
            <i class="fas fa-arrow-left"></i> Return to Previous Page
        </button>

        <footer class="footer">
            <p>© <?php echo $year; ?> Emmanuel Kirui. All rights reserved.<br>Contact us at <a href="mailto:emmanuelkirui042@gmail.com" class="contact-link">emmanuelkirui042@gmail.com</a> for inquiries.</p>
        </footer>
    </main>
</body>
</html>

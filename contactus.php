<?php
$year = date("Y");

// Basic form submission handling (for demonstration purposes)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $message = htmlspecialchars($_POST['message']);
    $response = "Thank you, $name! Your message has been received. We’ll get back to you at $email soon.";
    // In a real scenario, add email sending logic or database storage here.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contact Emmanuel Kirui - Reach out for inquiries, feedback, or collaboration opportunities.">
    <meta name="author" content="Emmanuel Kirui">
    <meta name="keywords" content="contact us, Emmanuel Kirui, feedback, support, inquiries">
    <meta name="robots" content="index, follow">
    <title>Contact Us | Emmanuel Kirui</title>

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
        a {
            color: #f39c12;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        a:hover {
            color: #e67e22;
            text-decoration: underline;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 17px;
            margin-bottom: 5px;
            color: #2d3436;
        }
        input, textarea {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            transition: border-color 0.3s ease;
        }
        input:focus, textarea:focus {
            border-color: #f39c12;
            outline: none;
        }
        textarea { resize: vertical; min-height: 120px; }
        .submit-button {
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
            transition: all 0.3s ease;
        }
        .submit-button:hover {
            background: #e67e22;
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
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
        .response {
            color: #28a745;
            font-weight: 700;
            margin-top: 20px;
            text-align: center;
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
            p, label, input, textarea { font-size: 15px; }
            .submit-button, .back-button { padding: 12px 20px; font-size: 15px; }
        }
        @media (max-width: 480px) {
            body { padding: 30px 15px; }
            .container { padding: 20px; }
            h1 { font-size: 26px; }
            h2 { font-size: 20px; }
            p, label, input, textarea { font-size: 14px; }
        }
    </style>
</head>
<body>
    <main class="container" role="main">
        <header>
            <h1><i class="fas fa-envelope"></i> Contact Us</h1>
        </header>

        <section>
            <p>We’re here to assist you! Whether you have questions, feedback, or collaboration ideas, feel free to get in touch with Emmanuel Kirui. Use the form below or reach out directly via email.</p>
        </section>

        <section>
            <h2>Contact Form</h2>
            <?php if (isset($response)): ?>
                <p class="response"><?php echo $response; ?></p>
            <?php else: ?>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" required aria-required="true">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required aria-required="true">
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" required aria-required="true"></textarea>
                    </div>
                    <button type="submit" class="submit-button">Send Message</button>
                </form>
            <?php endif; ?>
        </section>

        <section>
            <h2>Alternative Contact Methods</h2>
            <p>Email: <a href="mailto:emmanuelkirui042@gmail.com" class="contact-link">emmanuelkirui042@gmail.com</a><br>
            City: Nairobi Kenya</p>
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

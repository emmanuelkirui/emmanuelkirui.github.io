<?php
// Dynamic variables
$year = date("Y"); // Auto-updates the year
$site_name = "Emmanuel Kirui#CPS"; // Change your site name here
$footer_links = [
    "#" => "Terms and Conditions",
    "#" => "Privacy Policy",
    "#" => "Third-Party Service Providers",
    "#" => "Responsible Gambling"
];
?>

<footer>
    <style>
        footer {
            background-color: #333;
            color: #ddd;
            padding: 20px 10px;
            text-align: center;
            border-top: 2px solid #f39c12;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .footer-content {
            max-width: 1200px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        /* Footer Links */
        .footer-links {
            list-style: none;
            padding: 0;
            margin: 10px 0;
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
            font-size: 1rem;
            font-weight: 600;
            transition: color 0.3s ease-in-out, text-decoration 0.3s ease-in-out;
        }

        .footer-links a:hover {
            color: #e67e22;
            text-decoration: underline;
        }

        /* Gambling Disclaimer */
        .gambling-disclaimer {
            background-color: #222;
            color: #ffcc00;
            font-size: 0.9rem;
            padding: 10px;
            margin-top: 10px;
            width: 100%;
            text-align: center;
            font-weight: 600;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .gambling-disclaimer img {
            width: 50px;
            margin-bottom: 5px;
        }

        /* Footer Disclaimer */
        .disclaimer {
            font-size: 0.9rem;
            color: #bbb;
            margin-top: 10px;
            line-height: 1.6;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .footer-links {
                flex-direction: column;
                align-items: center;
            }

            .footer-links a {
                font-size: 0.9rem;
            }

            .disclaimer, .gambling-disclaimer {
                font-size: 0.85rem;
            }

            .gambling-disclaimer img {
                width: 40px;
            }
        }
    </style>

    <div class="footer-content">
        <p>&copy; <?php echo $year . " " . $site_name; ?>. All rights reserved.</p>
        <ul class="footer-links">
            <?php foreach ($footer_links as $link => $text): ?>
                <li><a href="<?php echo $link; ?>"><?php echo $text; ?></a></li>
            <?php endforeach; ?>
        </ul>

        <!-- Gambling Disclaimer -->
        <div class="gambling-disclaimer">
            <img src="18plus.png" alt="18+ Only">
            <p>Strictly 18+ | Gamble Responsibly</p>
            <p>If you have a gambling problem, seek help from a professional service.</p>
        </div>

        <p class="disclaimer">
            This site uses third-party services, cookies, and advertisements. These third parties have their own terms and conditions, and we are not responsible for their actions or policies. You are encouraged to review the terms and conditions of any third-party service providers.
        </p>
    </div>
</footer>

<!-- Footer -->
<footer>
    <style>
        footer {
            background-color: #333;
            color: #ddd;
            padding: 20px 10px;
            text-align: center;
            border-top: 2px solid #f39c12;
            width: 100%;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
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

            .disclaimer {
                font-size: 0.85rem;
            }
        }
    </style>

    <div class="footer-content">
        <p>&copy; 2025 Emmanuel Kirui. All rights reserved.</p>
        <ul class="footer-links">
            <li><a href="#">Terms and Conditions</a></li>
            <li><a href="#">Privacy Policy</a></li>
            <li><a href="#">Third-Party Service Providers</a></li>
        </ul>
        <p class="disclaimer">
            This site uses third-party services, cookies, and advertisements. These third parties have their own terms and conditions, and we are not responsible for their actions or policies. You are encouraged to review the terms and conditions of any third-party service providers.
        </p>
    </div>
</footer>

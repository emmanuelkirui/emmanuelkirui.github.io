<!-- index.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Official documentation for Creative Pulse Solutions - Football Predictions and Creative Services">
    <meta name="author" content="Emmanuel Kirui">
    <title>Creative Pulse Solutions - Official Documentation</title>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --text-color: #333;
            --light-bg: #f5f6fa;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.8;
            color: var(--text-color);
            background-color: var(--light-bg);
        }

        /* Header */
        header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        /* Hamburger Menu */
        .hamburger {
            display: none;
            font-size: 2rem;
            cursor: pointer;
            padding: 10px;
        }

        .nav-menu {
            display: flex;
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            transition: background-color 0.3s ease;
        }

        .nav-menu a:hover {
            background-color: var(--secondary-color);
        }

        /* Main Layout */
        .container {
            max-width: 1200px;
            margin: 80px auto 40px;
            padding: 0 20px;
            display: flex;
            gap: 30px;
        }

        .toc {
            width: 300px;
            position: sticky;
            top: 100px;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .toc h2 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        .toc ul {
            list-style: none;
        }

        .toc li {
            margin: 10px 0;
        }

        .toc a {
            color: var(--text-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .toc a:hover {
            color: var(--secondary-color);
        }

        .content {
            flex: 1;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .content h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .content h2 {
            color: var(--secondary-color);
            font-size: 1.8rem;
            margin: 30px 0 15px;
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 5px;
        }

        .content p {
            margin-bottom: 15px;
        }

        .content ul {
            margin-left: 20px;
            margin-bottom: 15px;
        }

        .feature-box {
            background-color: var(--light-bg);
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }

        /* Footer */
        footer {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            text-align: center;
            margin-top: 40px;
        }

        /* Responsive Design */
        @media screen and (max-width: 768px) {
            .hamburger {
                display: block;
            }

            .nav-menu {
                display: none;
                position: absolute;
                top: 60px;
                left: 0;
                width: 100%;
                background-color: var(--primary-color);
            }

            .nav-menu.active {
                display: block;
            }

            .nav-menu a {
                display: block;
                padding: 15px;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }

            .container {
                flex-direction: column;
                margin-top: 60px;
            }

            .toc {
                width: 100%;
                position: static;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-content">
            <h1>Creative Pulse Solutions</h1>
            <div class="hamburger">☰</div>
            <nav class="nav-menu">
                <a href="#introduction">Home</a>
                <a href="#football">Football Predictions</a>
                <a href="#graphics">Graphics Design</a>
                <a href="#motion">Motion Design</a>
                <a href="#lyrics">Lyrics Making</a>
                <a href="#animation">Video Animation</a>
                <a href="#editing">Video Editing</a>
            </nav>
        </div>
    </header>

    <!-- Main Container -->
    <div class="container">
        <!-- Table of Contents -->
        <aside class="toc">
            <h2>Documentation Contents</h2>
            <ul>
                <li><a href="#introduction">1. Introduction</a></li>
                <li><a href="#football">2. Football Predictions</a></li>
                <li><a href="#graphics">3. Graphics Design</a></li>
                <li><a href="#motion">4. Motion Design</a></li>
                <li><a href="#lyrics">5. Lyrics Making</a></li>
                <li><a href="#animation">6. Video Animation</a></li>
                <li><a href="#editing">7. Video Editing</a></li>
                <li><a href="#process">8. Our Process</a></li>
                <li><a href="#contact">9. Contact Information</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="content">
            <section id="introduction">
                <h1>Creative Pulse Solutions Documentation</h1>
                <p>Welcome to the official documentation of Creative Pulse Solutions, a professional service provider founded and managed by Emmanuel Kirui. We specialize in football predictions and offer a comprehensive suite of creative services designed to meet diverse client needs.</p>
                <div class="feature-box">
                    <p><strong>Established:</strong> March 15, 2025 | <strong>Owner:</strong> Emmanuel Kirui</p>
                </div>
            </section>

            <section id="football">
                <h2>Football Predictions</h2>
                <p>Our football prediction service provides detailed analysis and insights for sports enthusiasts and bettors. We combine statistical analysis with expert knowledge to deliver reliable predictions.</p>
                <ul>
                    <li>Daily match previews and analysis</li>
                    <li>Statistical modeling and probability calculations</li>
                    <li>Expert commentary from seasoned analysts</li>
                    <li>Historical data analysis</li>
                    <li>Live updates during match days</li>
                </ul>
                <div class="feature-box">
                    <p><strong>Accuracy Rate:</strong> Consistently above industry standards | <strong>Update Frequency:</strong> Daily</p>
                </div>
            </section>

            <section id="graphics">
                <h2>Graphics Design</h2>
                <p>Our graphics design services cater to businesses and individuals seeking high-quality visual content.</p>
                <ul>
                    <li>Professional logo design and branding</li>
                    <li>Marketing collateral (flyers, brochures, banners)</li>
                    <li>Digital illustrations and artwork</li>
                    <li>UI/UX design for web and mobile applications</li>
                    <li>Custom graphics packages</li>
                </ul>
            </section>

            <section id="motion">
                <h2>Motion Design</h2>
                <p>We bring static designs to life through professional motion design services.</p>
                <ul>
                    <li>Animated logos and intros</li>
                    <li>Promotional video content</li>
                    <li>Motion graphics for social media</li>
                    <li>Visual effects for video production</li>
                    <li>Kinetic typography animations</li>
                </ul>
            </section>

            <section id="lyrics">
                <h2>Lyrics Making</h2>
                <p>Our lyrics creation service offers bespoke songwriting solutions.</p>
                <ul>
                    <li>Original song lyrics across genres</li>
                    <li>Custom poetry and spoken word</li>
                    <li>Commercial jingles and ad copy</li>
                    <li>Rap verses and hooks</li>
                    <li>Lyrics editing and refinement</li>
                </ul>
            </section>

            <section id="animation">
                <h2>Video Animation</h2>
                <p>Professional animation services to enhance your visual storytelling.</p>
                <ul>
                    <li>2D character animation</li>
                    <li>3D modeling and animation</li>
                    <li>Whiteboard animation videos</li>
                    <li>Storyboarding and concept development</li>
                    <li>Animated explainer videos</li>
                </ul>
            </section>

            <section id="editing">
                <h2>Video Editing</h2>
                <p>Comprehensive video editing services to polish your content.</p>
                <ul>
                    <li>Professional video cutting and sequencing</li>
                    <li>Color grading and correction</li>
                    <li>Audio enhancement and mixing</li>
                    <li>Special effects integration</li>
                    <li>Multi-format export options</li>
                </ul>
            </section>

            <section id="process">
                <h2>Our Process</h2>
                <p>We follow a structured approach to ensure quality delivery:</p>
                <ol>
                    <li>Consultation: Understanding client needs</li>
                    <li>Planning: Project scope and timeline definition</li>
                    <li>Execution: Professional implementation</li>
                    <li>Review: Client feedback and revisions</li>
                    <li>Delivery: Final product submission</li>
                </ol>
            </section>

            <section id="contact">
                <h2>Contact Information</h2>
                <p>For inquiries or service requests, please contact:</p>
                <ul>
                    <li><strong>Owner:</strong> Emmanuel Kirui</li>
                    <li><strong>Email:</strong> <a href="mailto:emmanuelkirui042@gmail.com">emmanuelkirui042@gmail.com</a></li>
                    <li><strong>Website:</strong> Creative Pulse Solutions</li>
                    <li><strong>Availability:</strong> Monday - Friday, 9:00 AM - 5:00 PM EAT</li>
                </ul>
            </section>
        </main>
    </div>

    <!-- Footer -->
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Creative Pulse Solutions. All rights reserved. | Designed and maintained by Emmanuel Kirui</p>
    </footer>

    <!-- JavaScript -->
    <script>
        // Hamburger menu toggle
        const hamburger = document.querySelector('.hamburger');
        const navMenu = document.querySelector('.nav-menu');

        hamburger.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });

        // Smooth scrolling for TOC links
        document.querySelectorAll('.toc a').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                document.querySelector(targetId).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>

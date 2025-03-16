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
            --dark-bg: #1a1a1a;
            --dark-text: #e0e0e0;
            --dark-primary: #34495e;
            --dark-secondary: #2980b9;
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
            padding-top: 80px; /* Adjusted for fixed header height */
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        body.dark-mode {
            background-color: var(--dark-bg);
            color: var(--dark-text);
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
            height: 80px;
            transition: background-color 0.3s ease;
        }

        body.dark-mode header {
            background-color: var(--dark-primary);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            height: 100%;
        }

        .header-content h1 {
            font-size: 1.5rem;
        }

        /* Back Button */
        .back-btn {
            background-color: var(--secondary-color);
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 0.9rem;
        }

        body.dark-mode .back-btn {
            background-color: var(--dark-secondary);
        }

        .back-btn:hover {
            background-color: #2980b9;
        }

        /* Hamburger Menu */
        .hamburger {
            display: none;
            font-size: 2rem;
            cursor: pointer;
            padding: 10px;
            z-index: 1001;
        }

        .close-btn {
            display: none;
            font-size: 2rem;
            cursor: pointer;
            color: white;
            padding: 10px;
            position: absolute;
            right: 20px;
            top: 20px;
            z-index: 1001;
        }

        /* Nav Menu as Sidebar */
        .nav-menu {
            width: 280px;
            background-color: var(--primary-color);
            color: white;
            position: fixed;
            top: 80px;
            left: -280px;
            height: calc(100vh - 80px);
            transition: left 0.3s ease;
            z-index: 999;
            box-shadow: 2px 0 10px rgba(0,0,0,0.2);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .nav-menu.active {
            left: 0;
        }

        body.dark-mode .nav-menu {
            background-color: var(--dark-primary);
        }

        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 15px 25px;
            transition: background-color 0.3s ease;
            font-size: 1.1rem;
            display: block;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .nav-menu a:hover {
            background-color: var(--secondary-color);
        }

        body.dark-mode .nav-menu a:hover {
            background-color: var(--dark-secondary);
        }

        /* Theme Toggle */
        .theme-toggle {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
            margin: 0 10px;
        }

        .theme-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .theme-toggle .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: background-color 0.4s ease;
            border-radius: 34px;
        }

        .theme-toggle .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: transform 0.4s ease;
            border-radius: 50%;
        }

        .theme-toggle input:checked + .slider {
            background-color: #2196F3;
        }

        .theme-toggle input:checked + .slider:before {
            transform: translateX(26px);
        }

        .theme-toggle .slider:after {
            content: '☀️';
            position: absolute;
            top: 50%;
            left: 8px;
            transform: translateY(-50%);
            color: #fff;
            font-size: 16px;
            transition: opacity 0.4s ease;
            opacity: 1;
        }

        .theme-toggle input:checked + .slider:after {
            content: '🌙';
            left: auto;
            right: 8px;
            opacity: 1;
        }

        .theme-toggle .slider:hover {
            opacity: 0.9;
        }
       
        /* Main Layout */
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
            display: flex;
            gap: 30px;
        }

        .toc {
            width: 300px;
            position: sticky;
            top: 120px;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: background-color 0.3s ease;
        }

        body.dark-mode .toc {
            background-color: #2c2c2c;
        }

        .toc h2 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        body.dark-mode .toc h2 {
            color: var(--dark-secondary);
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

        body.dark-mode .toc a {
            color: var(--dark-text);
        }

        .toc a:hover {
            color: var(--secondary-color);
        }

        body.dark-mode .toc a:hover {
            color: var(--dark-secondary);
        }

        .content {
            flex: 1;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: background-color 0.3s ease;
        }

        body.dark-mode .content {
            background-color: #2c2c2c;
        }

        .content h1 {
            color: var(--primary-color);
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        body.dark-mode .content h1 {
            color: var(--dark-text);
        }

        .content h2 {
            color: var(--secondary-color);
            font-size: 1.8rem;
            margin: 30px 0 15px;
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 5px;
        }

        body.dark-mode .content h2 {
            color: var(--dark-secondary);
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
            transition: background-color 0.3s ease;
        }

        body.dark-mode .feature-box {
            background-color: #333;
        }

        /* Footer */
        footer {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            text-align: center;
            margin-top: 40px;
            transition: background-color 0.3s ease;
        }

        body.dark-mode footer {
            background-color: var(--dark-primary);
        }

        /* Responsive Design */
        @media screen and (max-width: 768px) {
            .hamburger {
                display: block;
            }

            .container {
                flex-direction: column;
            }

            .toc {
                width: 100%;
                position: static;
            }

            .back-btn {
                margin-bottom: 20px;
            }
            
        }

        @media screen and (min-width: 769px) {
            .hamburger {
                display: block;
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
            <span class="close-btn">✕</span>
            <nav class="nav-menu">
                <a href="#introduction">Home</a>
                <a href="#football">Football Predictions</a>
                <a href="#graphics">Graphics Design</a>
                <a href="#motion">Motion Design</a>
                <a href="#lyrics">Lyrics Making</a>
                <a href="#animation">Video Animation</a>
                <a href="#editing">Video Editing</a>
                <a href="#features" title="Features">Features</a>
                <a href="#requirements" title="Requirements">Requirements</a>
                <a href="#installation" title="Installation">Installation</a>
                <a href="#recaptcha" title="ReCaptcha v2">ReCaptcha v2 - Configuration</a>
			    <a href="#sources_and_credits" title="Sources and Credits">Sources and Credits</a>
                 
               <div>
                 <label class="theme-toggle">
                    <input type="checkbox" id="theme-switch">
                    <span class="slider"></span>
                </label>
                </div>
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
                <li><a href="#features" title="Features">9.Features</a></li>
                <li><a href="#requirements" title="Requirements">10.Requirements</a></li>
                <li><a href="#installation" title="Installation">11.Installation</a></li>
                <li><a href="#recaptcha" title="ReCaptcha v2">12.ReCaptcha v2 - Configuration</a></li>
			    <li><a href="#sources_and_credits" title="Sources and Credits">13.Sources and Credits</a></li>
                <li><a href="#contact">14. Contact Information</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="content">
            <button class="back-btn" onclick="window.history.back()">← Back</button>
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
            <section id="features">
                <h2>Features</h2>
                
                <ol>
                <li><strong>Posts</strong><br />
Posts can be added, edited and deleted. HTML Editor is integrated.</li>
<li><strong>Categories</strong><br />
Posts can be organized into Categories.</li>
<li><strong>Comments System</strong><br />
Users can share their opinion in the Comments Section of the Posts.</li>
<li><strong>Gallery</strong><br />
Gallery allows you to create a full portfolio of your images and works.</li>
<li><strong>Albums</strong><br />
Gallery images can be organized into Albums.</li>
<li><strong>Users Login and Registration</strong><br />
Users module provides user-friendly registration form, login form and profile page.</li>
<li><strong>Custom Pages</strong><br />
Custom Pages can be added, edited and deleted. HTML Codes are supported.</li>
<li><strong>Widgets</strong><br />
Custom Widgets can be added, edited and deleted.</li>
<li><strong>Search Module</strong><br />
Searches for posts and displays results as a list.</li>
<li><strong>Newsletter</strong><br />
Keep in touch with your site visitors and encourage them to return to your site.</li>
<li><strong>Themes</strong><br />
The site can be redesigned in different themes. Many themes are added by default.</li>
<li><strong>Menu Editor</strong><br />
Menu can be managed dynamically. Menus can be added, edited and deleted.</li>
<li><strong>Contact Page</strong><br />
The Contact Form is providing the best ways to get in touch.</li>
<li><strong>Powerful Admin Panel</strong><br />
The whole website can be managed via the Admin Panel.</li>
<li><strong>Dashboard with Stats</strong><br />
On the Dashboard you can check the Statistics.</li>
<li><strong>File Manager</strong><br />
File Manager allows you to upload, view and delete files.</li>
<li><strong>RSS Feed</strong><br />
Web feed that allows users and applications to access updates to your website in a standardized, computer-readable format.</li>
<li><strong>XML Sitemap</strong><br />
Sitemap is XML file that lists your website’s pages, making sure search engines can find and crawl them.</li>
<li><strong>Secure</strong><br />
The script is integrated with special security functions borrowed from Project SECURITY to protect the whole site and its users.</li>
<li><strong>Very Optimized</strong><br />
The script is very lightweight and optimized.</li>
<li><strong>Responsive Layout</strong><br />
Looks good on many devices and screen resolutions.</li>
<li><strong>Easy to setup</strong><br />
The script is integrated with Installation Wizard that will help you to install the app.</li>
</ul>
<em>and many more...</em>
                </ol>
            </section>
            <section id="requirements">
                <h2>Requirements</h2>
                
                <ol>
                  <li>PHP</li>
                  <li>MySQLi</li>
                </ol>
            </section>
            <section id="installation">
                <h2>Installation</h2>
               
                <ol>
                <li>Upload the files from the &quot;Source&quot; folder of the script on your host</li>
                <li>Create a MySQL database (Your hosting provider can assist)</li>
                <li>Visit your website where you uploaded the files (For example: yourwebsite.com/)</li>
                <li>The Installation Wizard will open automatically, just follow the steps</li>
				<li>Add ReCaptcha v2 keys on the Settings page of the Admin Panel.</li>
                </ol><br />
            
            <p><b>Note:</b> If you are updating the script replace all files with the updated. 
			
			Run the following SQL queries via PHPMyAdmin (depending on the version upgrade):</p>
			<p>- v1.4 to v1.5 update SQL queries:</p>
            <code>
            ALTER TABLE `posts` ADD `author_id` INT(11) NOT NULL DEFAULT '1' AFTER `content`;
            <br />
            ALTER TABLE `posts` ADD `featured` VARCHAR(3) NOT NULL DEFAULT 'No' AFTER `active`;
            <br />
            ALTER TABLE `settings` ADD `date_format` VARCHAR(50) NOT NULL DEFAULT 'd.m.Y' AFTER `gcaptcha_secretkey`, ADD `background_image` VARCHAR(255) NOT NULL AFTER `date_format`, ADD `rtl` VARCHAR(3) NOT NULL DEFAULT 'No' AFTER `youtube`;
            </code>
            
			
			<p>- v1.5 to v1.6 update SQL queries:</p>
			<code>
			ALTER TABLE `settings` ADD `head_customcode` text CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL AFTER `gcaptcha_secretkey`;
			<br />
			ALTER TABLE `widgets` ADD `position` VARCHAR(10) NOT NULL DEFAULT 'Sidebar' AFTER `content`;
			</code>
			
			<p>- v1.7 to v1.8 update SQL queries:</p>
			<code>
			ALTER TABLE `settings` ADD `linkedin` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `youtube`;
			<br />
			ALTER TABLE `settings` ADD `latestposts_bar` VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Enabled' AFTER `date_format`;
			<br />
			ALTER TABLE `gallery` ADD `album_id` INT(11) NOT NULL DEFAULT 1 AFTER `id`;
			<br />
			CREATE TABLE `albums` ( `id` INT NOT NULL , `title` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL ) ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
			<br />
			INSERT INTO `albums` (`id`, `title`) VALUES (1, 'General');
			<br />
			ALTER TABLE `albums`
			ADD PRIMARY KEY (`id`);
			<br />
			ALTER TABLE `albums`
			MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
			</code>
            </section>
            <section id="recaptcha">
                <h2>ReCaptcha v2 - Configuration</h2>
                <p>The Site Key and Secret Key can be generated on this page: 
                </p>
                <p>Choose reCAPTCHA v2 -> “I’m not a robot” Checkbox. Then fill the other details and submit the form.</p>
                <br />
			<p>Finally the needed Keys can be found on this page:</p>
			<img src="assets/img/recaptcha.png" width="70%" />
            </section>
            <section id="sources_and_credits">
                <h2>Sources and Credits</h2>
                <p>Used resources:</p>
                <ol>
                <li>Font Awesome Icons -&nbsp;<a href="http://fontawesome.io" target="_blank">FontAwesome.io</a></li>
                <li>Bootstrap Framework -&nbsp;<a href="https://getbootstrap.com" target="_blank">GetBootstrap.com</a></li>
                <li>DataTables -&nbsp;<a href="https://datatables.net" target="_blank">DataTables.net</a></li>
                <li>jQuery - <a href="https://jquery.com" target="_blank">jQuery.com</a></li>
				<li>CKEditor - <a href="https://ckeditor.com" target="_blank">CKEditor.com</a></li>
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
        <p>© <?php echo date("Y"); ?> Creative Pulse Solutions. All rights reserved. | Designed and maintained by Emmanuel Kirui</p>
    </footer>

    <!-- JavaScript -->
    <script>
        // Hamburger menu toggle
        const hamburger = document.querySelector('.hamburger');
        const closeBtn = document.querySelector('.close-btn');
        const navMenu = document.querySelector('.nav-menu');

        hamburger.addEventListener('click', () => {
            navMenu.classList.add('active');
            hamburger.style.display = 'none';
            closeBtn.style.display = 'block';
        });

        closeBtn.addEventListener('click', () => {
            navMenu.classList.remove('active');
            hamburger.style.display = 'block';
            closeBtn.style.display = 'none';
        });

        // Smooth scrolling for TOC and nav links
        document.querySelectorAll('.toc a, .nav-menu a').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                document.querySelector(targetId).scrollIntoView({
                    behavior: 'smooth'
                });
                if (window.innerWidth <= 768) {
                    navMenu.classList.remove('active');
                    hamburger.style.display = 'block';
                    closeBtn.style.display = 'none';
                }
            });
        });

        // Theme toggle functionality
        const themeSwitch = document.getElementById('theme-switch');
        const body = document.body;

        // Load saved theme from localStorage
        if (localStorage.getItem('theme') === 'dark') {
            body.classList.add('dark-mode');
            themeSwitch.checked = true;
        }

        themeSwitch.addEventListener('change', () => {
            body.classList.toggle('dark-mode');
            if (body.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
            } else {
                localStorage.setItem('theme', 'light');
            }
        });
    </script>
</body>
</html>

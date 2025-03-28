<!-- portfolio.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Professional portfolio of Emmanuel Kirui - Creative Pulse Solutions">
    <meta name="author" content="Emmanuel Kirui">
    <title>Emmanuel Kirui - Portfolio</title>
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
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--light-bg);
            transition: background-color 0.3s ease, color 0.3s ease;
            padding-top: 80px; /* Space for fixed header */
        }

        body.dark-mode {
            background-color: var(--dark-bg);
            color: var(--dark-text);
        }

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
            font-size: 1.8rem;
        }

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

        nav {
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
        }

        nav.active {
            left: 0;
        }

        body.dark-mode nav {
            background-color: var(--dark-primary);
        }

        nav ul {
            list-style: none;
            display: flex;
            flex-direction: column;
        }

        nav ul li {
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 15px 25px;
            transition: background-color 0.3s ease;
            font-size: 1.1rem;
            font-weight: 500;
        }

        nav ul li a:hover {
            background-color: var(--secondary-color);
        }

        body.dark-mode nav ul li a:hover {
            background-color: var(--dark-secondary);
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

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        section {
            background-color: white;
            padding: 40px;
            margin-bottom: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: background-color 0.3s ease;
        }

        body.dark-mode section {
            background-color: #2c2c2c;
        }

        section h2 {
            color: var(--primary-color);
            font-size: 2rem;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 10px;
        }

        body.dark-mode section h2 {
            color: var(--dark-secondary);
        }

        #about {
            text-align: center;
        }

        #about p {
            max-width: 800px;
            margin: 0 auto 20px;
        }

        .profile-img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin-bottom: 20px;
            border: 4px solid var(--secondary-color);
        }

        .skills-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .skill-item {
            background-color: var(--light-bg);
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            transition: background-color 0.3s ease;
        }

        body.dark-mode .skill-item {
            background-color: #333;
        }

        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .project-card {
            background-color: var(--light-bg);
            padding: 20px;
            border-radius: 5px;
            transition: transform 0.3s ease, background-color 0.3s ease;
        }

        body.dark-mode .project-card {
            background-color: #333;
        }

        .project-card:hover {
            transform: translateY(-5px);
        }

        .project-card h3 {
            color: var(--secondary-color);
            margin-bottom: 10px;
        }

        body.dark-mode .project-card h3 {
            color: var(--dark-secondary);
        }

        #contact ul {
            list-style: none;
        }

        #contact ul li {
            margin: 10px 0;
        }

        #contact a {
            color: var(--secondary-color);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        body.dark-mode #contact a {
            color: var(--dark-secondary);
        }

        #contact a:hover {
            text-decoration: underline;
        }

        footer {
            background-color: var(--primary-color);
            color: white;
            text-align: center;
            padding: 20px;
            transition: background-color 0.3s ease;
        }

        body.dark-mode footer {
            background-color: var(--dark-primary);
        }

        @media (max-width: 768px) {
            .hamburger {
                display: block;
            }

            nav {
                display: block; /* Always present, just off-screen */
            }

            nav ul li a {
                padding: 15px 25px;
            }

            .container {
                margin-top: 20px;
            }

            section {
                padding: 20px;
            }
        }

        @media (min-width: 769px) {
            nav {
                width: auto;
                position: static;
                background-color: transparent;
                box-shadow: none;
                height: auto;
                overflow-y: visible;
            }

            nav ul {
                flex-direction: row;
            }

            nav ul li {
                border-bottom: none;
            }

            nav ul li a {
                padding: 15px;
            }

            .hamburger, .close-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <h1>Emmanuel Kirui</h1>
            <div class="hamburger">☰</div>
            <span class="close-btn">✕</span>
            <nav>
                <ul>
                    <li><a href="#about">About</a></li>
                    <li><a href="#skills">Skills</a></li>
                    <li><a href="#projects">Projects</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li>
                        <div>
                        <label class="theme-toggle">
                            <input type="checkbox" id="theme-switch">
                            <span class="slider"></span>
                        </label>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
    <button class="back-btn" onclick="window.history.back()">← Back</button>
        <section id="about">
            <img src="logo/LOGO2.png" alt="Profile Picture" class="profile-img">
            <h2>About Me</h2>
            <p>Hello! I'm Emmanuel Kirui, the founder of Creative Pulse Solutions. With a passion for creativity and technology, I specialize in football predictions and a wide range of creative services including graphics design, motion design, video animation, and more.</p>
            <p>Based in Kenya, I bring a unique blend of analytical skills and artistic vision to every project, delivering professional results tailored to client needs.</p>
        </section>

        <section id="skills">
            <h2>Skills</h2>
            <div class="skills-grid">
                <div class="skill-item">Football Predictions</div>
                <div class="skill-item">Graphics Design</div>
                <div class="skill-item">Motion Design</div>
                <div class="skill-item">Video Animation</div>
                <div class="skill-item">Video Editing</div>
                <div class="skill-item">Lyrics Writing</div>
            </div>
        </section>

        <section id="projects">
            <h2>Projects</h2>
            <div class="projects-grid">
                <div class="project-card">
                    <h3>Football Prediction Platform</h3>
                    <p>A comprehensive platform offering daily football predictions with high accuracy using statistical analysis and expert insights.</p>
                </div>
                <div class="project-card">
                    <h3>Branding for Local Business</h3>
                    <p>Designed a full branding package including logo, flyers, and digital assets for a local startup.</p>
                </div>
                <div class="project-card">
                    <h3>Animated Explainer Video</h3>
                    <p>Created a 2D animated explainer video for a tech company to showcase their product features.</p>
                </div>
            </div>
        </section>

        <section id="contact">
            <h2>Contact Me</h2>
            <ul>
                <li><strong>Email:</strong> <a href="mailto:emmanuelkirui042@gmail.com">emmanuelkirui042@gmail.com</a></li>
                <li><strong>Phone:</strong> +254742994437</li>
                <li><strong>LinkedIn:</strong> <a href="#">linkedin.com/in/emmanuel-kirui</a></li>
                <li><strong>Portfolio:</strong> <a href="#">creativepulsesolutions.com</a></li>
            </ul>
        </section>
    </div>

    <footer>
        <p>© <?php echo date("Y"); ?> Emmanuel Kirui | Creative Pulse Solutions. All rights reserved.</p>
    </footer>

    <script>
        const hamburger = document.querySelector('.hamburger');
        const closeBtn = document.querySelector('.close-btn');
        const nav = document.querySelector('nav');

        hamburger.addEventListener('click', () => {
            nav.classList.add('active');
            hamburger.style.display = 'none';
            closeBtn.style.display = 'block';
        });

        closeBtn.addEventListener('click', () => {
            nav.classList.remove('active');
            hamburger.style.display = 'block';
            closeBtn.style.display = 'none';
        });

        document.querySelectorAll('nav a').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                document.querySelector(targetId).scrollIntoView({
                    behavior: 'smooth'
                });
                if (window.innerWidth <= 768) {
                    nav.classList.remove('active');
                    hamburger.style.display = 'block';
                    closeBtn.style.display = 'none';
                }
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            const themeSwitch = document.getElementById('theme-switch');
            const body = document.body;

            if (localStorage.getItem('theme') === 'dark') {
                body.classList.add('dark-mode');
                themeSwitch.checked = true;
            }

            themeSwitch.addEventListener('change', () => {
                body.classList.toggle('dark-mode');
                localStorage.setItem('theme', body.classList.contains('dark-mode') ? 'dark' : 'light');
            });
        });
    </script>
</body>
</html>
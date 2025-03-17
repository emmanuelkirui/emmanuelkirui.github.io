<!-- portfolio.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Professional portfolio of Emmanuel Kirui - Creative Pulse Solutions">
    <meta name="author" content="Emmanuel Kirui">
    <title>Emmanuel Kirui - Professional Portfolio</title>
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
            transition: all 0.3s ease;
            padding-top: 80px;
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
        }

        .hamburger {
            display: none;
            font-size: 2rem;
            cursor: pointer;
            padding: 10px;
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
        }

        nav {
            transition: left 0.3s ease;
        }

        nav.active {
            left: 0;
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            padding: 15px 25px;
            transition: background-color 0.3s ease;
        }

        nav ul li a:hover {
            background-color: var(--secondary-color);
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

        /* Slideshow */
        .slideshow-container {
            position: relative;
            max-width: 100%;
            margin: auto;
        }

        .slides {
            display: none;
        }

        .slides img {
            width: 100%;
            border-radius: 5px;
        }

        .prev, .next {
            cursor: pointer;
            position: absolute;
            top: 50%;
            width: auto;
            padding: 16px;
            margin-top: -22px;
            color: white;
            font-weight: bold;
            font-size: 18px;
            transition: 0.6s ease;
            border-radius: 0 3px 3px 0;
            user-select: none;
            background-color: rgba(0,0,0,0.5);
        }

        .next {
            right: 0;
            border-radius: 3px 0 0 3px;
        }

        .prev:hover, .next:hover {
            background-color: rgba(0,0,0,0.8);
        }

        /* Project Dropdown */
        .project-card {
            position: relative;
            cursor: pointer;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: var(--light-bg);
            min-width: 100%;
            box-shadow: 0px 8px 16px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 5px;
            padding: 15px;
        }

        body.dark-mode .dropdown-content {
            background-color: #333;
        }

        .project-card:hover .dropdown-content {
            display: block;
        }

        .dropdown-content img {
            max-width: 100%;
            border-radius: 5px;
            margin-top: 10px;
        }

        /* Badges */
        .badges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .badge-item {
            text-align: center;
        }

        .badge-item img {
            width: 100px;
            height: 100px;
            margin-bottom: 10px;
        }

        /* Skills */
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
        }

        .skill-level {
            margin-top: 5px;
            font-size: 0.9rem;
            color: var(--secondary-color);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hamburger {
                display: block;
            }

            nav {
                width: 280px;
                position: fixed;
                top: 80px;
                left: -280px;
                height: calc(100vh - 80px);
                background-color: var(--primary-color);
            }
        }

        @media (min-width: 769px) {
            nav {
                position: static;
                background-color: transparent;
                box-shadow: none;
            }

            nav ul {
                flex-direction: row;
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
                    <li><a href="#languages">Languages</a></li>
                    <li><a href="#projects">Projects</a></li>
                    <li><a href="#internships">Internships</a></li>
                    <li><a href="#badges">Badges</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><label class="theme-toggle">
                        <input type="checkbox" id="theme-switch">
                        <span class="slider"></span>
                    </label></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <section id="about">
            <img src="logo/LOGO2.png" alt="Profile Picture" class="profile-img">
            <h2>About Me</h2>
            <p>I am Emmanuel Kirui, the founder of Creative Pulse Solutions, based in Kenya. With a strong passion for blending creativity and technology, I specialize in football predictions, graphics design, motion design, video animation, and more. My goal is to deliver innovative, high-quality solutions tailored to meet client needs.</p>
        </section>

        <section id="skills">
            <h2>Technical & Creative Skills</h2>
            <div class="skills-grid">
                <div class="skill-item">Football Predictions <span class="skill-level">Expert</span></div>
                <div class="skill-item">Graphics Design <span class="skill-level">Advanced</span></div>
                <div class="skill-item">Motion Design <span class="skill-level">Advanced</span></div>
                <div class="skill-item">Video Animation <span class="skill-level">Advanced</span></div>
                <div class="skill-item">Video Editing <span class="skill-level">Intermediate</span></div>
                <div class="skill-item">Lyrics Writing <span class="skill-level">Intermediate</span></div>
            </div>
        </section>

        <section id="languages">
            <h2>Programming Languages</h2>
            <div class="skills-grid">
                <div class="skill-item">PHP <span class="skill-level">Intermediate</span></div>
                <div class="skill-item">HTML/CSS <span class="skill-level">Advanced</span></div>
                <div class="skill-item">JavaScript <span class="skill-level">Intermediate</span></div>
            </div>
        </section>

        <section id="projects">
            <h2>Projects</h2>
            <div class="slideshow-container">
                <div class="slides">
                    <img src="https://via.placeholder.com/800x400?text=Football+Prediction" alt="Football Prediction">
                </div>
                <div class="slides">
                    <img src="https://via.placeholder.com/800x400?text=Branding" alt="Branding">
                </div>
                <div class="slides">
                    <img src="https://via.placeholder.com/800x400?text=Animated+Video" alt="Animated Video">
                </div>
                <a class="prev" onclick="plusSlides(-1)">❮</a>
                <a class="next" onclick="plusSlides(1)">❯</a>
            </div>
            <div class="projects-grid">
                <div class="project-card">
                    <h3>Football Prediction Platform</h3>
                    <p>A platform offering daily football predictions.</p>
                    <div class="dropdown-content">
                        <p><strong>Details:</strong> Built with PHP and statistical analysis tools. Achieved 85% accuracy rate.</p>
                        <img src="https://via.placeholder.com/300x200?text=Prediction+Screenshot" alt="Prediction Screenshot">
                    </div>
                </div>
                <div class="project-card">
                    <h3>Branding for Local Business</h3>
                    <p>Full branding package for a startup.</p>
                    <div class="dropdown-content">
                        <p><strong>Details:</strong> Included logo, flyers, and digital assets using Adobe Suite.</p>
                        <img src="https://via.placeholder.com/300x200?text=Branding+Sample" alt="Branding Sample">
                    </div>
                </div>
                <div class="project-card">
                    <h3>Animated Explainer Video</h3>
                    <p>2D animation for a tech company.</p>
                    <div class="dropdown-content">
                        <p><strong>Details:</strong> Created using After Effects, 1-minute duration.</p>
                        <img src="https://via.placeholder.com/300x200?text=Animation+Frame" alt="Animation Frame">
                    </div>
                </div>
            </div>
        </section>

        <section id="internships">
            <h2>Internship Experience</h2>
            <div class="projects-grid">
                <div class="project-card">
                    <h3>Creative Intern - XYZ Agency</h3>
                    <p><strong>Duration:</strong> June 2023 - August 2023</p>
                    <p>Assisted in designing marketing materials and video content for clients.</p>
                </div>
            </div>
        </section>

        <section id="badges">
            <h2>Certifications & Badges</h2>
            <div class="badges-grid">
                <div class="badge-item">
                    <img src="https://via.placeholder.com/100?text=Badge+1" alt="Badge 1">
                    <p><a href="https://www.credly.com/badges/example1" target="_blank">Graphic Design Certification</a></p>
                </div>
                <div class="badge-item">
                    <img src="https://via.placeholder.com/100?text=Badge+2" alt="Badge 2">
                    <p><a href="https://www.credly.com/badges/example2" target="_blank">Video Editing Badge</a></p>
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
        let slideIndex = 1;
        showSlides(slideIndex);

        function plusSlides(n) {
            showSlides(slideIndex += n);
        }

        function showSlides(n) {
            let i;
            let slides = document.getElementsByClassName("slides");
            if (n > slides.length) {slideIndex = 1}
            if (n < 1) {slideIndex = slides.length}
            for (i = 0; i < slides.length; i++) {
                slides[i].style.display = "none";
            }
            slides[slideIndex-1].style.display = "block";
        }

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
                document.querySelector(this.getAttribute('href')).scrollIntoView({ behavior: 'smooth' });
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

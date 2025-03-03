<?php
$year = date("Y");
// Blog posts array covering all services with detailed content
$blog_posts = [
    [
        "title" => "Top Football Predictions for the Weekend",
        "date" => "March 01, 2025",
        "excerpt" => "Get the latest insights and predictions for this weekend's football matches, backed by expert analysis and stats.",
        "details" => "Our expert team has analyzed upcoming matches considering team form, player injuries, and historical data. This weekend, expect Manchester City to dominate with a predicted 3-1 win over Arsenal, while Liverpool might face a tough 2-2 draw against Chelsea. Detailed stats and betting odds included.",
        "category" => "Football Predictions",
        "link" => "#"
    ],
    [
        "title" => "Creating Stunning Graphics for Social Media",
        "date" => "February 28, 2025",
        "excerpt" => "Learn tips and tricks to design eye-catching graphics that boost engagement on your social platforms.",
        "details" => "Explore tools like Canva and Photoshop, master color theory, and learn layout techniques. This guide includes 5 practical examples to increase your click-through rates by 30% with optimized visuals for Instagram and Twitter.",
        "category" => "Design Graphics",
        "link" => "#"
    ],
    [
        "title" => "Crafting Engaging Motion Content: A Step-by-Step Guide",
        "date" => "February 27, 2025",
        "excerpt" => "Discover how to write compelling motion content that captivates your audience.",
        "details" => "Learn the process of scripting and structuring motion content for videos, including pacing, tone, and audience targeting. Includes a breakdown of a successful promotional video script that increased conversions by 25%.",
        "category" => "Motion Content Writing",
        "link" => "#"
    ],
    [
        "title" => "Building a Responsive Website from Scratch",
        "date" => "February 26, 2025",
        "excerpt" => "A beginner’s guide to designing and coding a fully responsive website.",
        "details" => "Step-by-step tutorial covering HTML, CSS, and JavaScript to create a responsive site. Features media queries, flexbox, and a sample project with code snippets that adapts to mobile, tablet, and desktop screens.",
        "category" => "Web Design",
        "link" => "#"
    ],
    [
        "title" => "Producing Your First Track: Tools and Techniques",
        "date" => "February 25, 2025",
        "excerpt" => "Explore the essentials of music production, from software to mixing tips.",
        "details" => "Dive into DAWs like Ableton Live and FL Studio, understand EQ, compression, and reverb, and follow a beginner’s workflow to produce a track. Includes recommended plugins and a sample beat breakdown.",
        "category" => "Music Production",
        "link" => "#"
    ],
    [
        "title" => "Writing Lyrics That Resonate: A Beginner’s Guide",
        "date" => "February 24, 2025",
        "excerpt" => "Learn the art of crafting lyrics that connect with listeners, blending structure and emotion.",
        "details" => "Master rhyme schemes, storytelling, and emotional hooks with examples from popular songs. Includes exercises to write your first verse and chorus, plus tips to avoid common pitfalls.",
        "category" => "Lyrics Making",
        "link" => "#"
    ],
    [
        "title" => "Animating Your Ideas: Tips for Stunning Video Animations",
        "date" => "February 23, 2025",
        "excerpt" => "Bring your concepts to life with these video animation techniques.",
        "details" => "Learn keyframe animation in After Effects, storyboarding basics, and timing tricks to create smooth, engaging visuals. Features a case study of a 30-second animated explainer video.",
        "category" => "Video Animation",
        "link" => "#"
    ],
    [
        "title" => "Editing Videos Like a Pro: Software and Strategies",
        "date" => "February 22, 2025",
        "excerpt" => "Master video editing with these tools and approaches for polished results.",
        "details" => "Compare Premiere Pro and DaVinci Resolve, learn cutting techniques, color grading, and audio syncing. Includes a workflow to edit a 5-minute vlog with professional transitions and effects.",
        "category" => "Video Editing",
        "link" => "#"
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Blog by Emmanuel Kirui - Insights on football predictions, graphic design, motion content writing, web design, music production, lyrics making, video animation, and editing.">
    <meta name="author" content="Emmanuel Kirui">
    <meta name="keywords" content="blog, Emmanuel Kirui, football predictions, graphic design, motion content, web design, music production, lyrics making, video animation, video editing">
    <meta name="robots" content="index, follow">
    <title>Blog | Emmanuel Kirui</title>

    <!-- Preconnect for Performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">

    <!-- External Stylesheets -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,700;1,400&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1vW6TfH6PfnEX7uL9r6Qz1D8rW9V8eB5eJ5eJ7eK9rL6rW8eL5fJ6eJ7rW9V8eB5eJ5eJ7eK9rL6r==" crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #2d3436;
            line-height: 1.6;
            padding: 40px 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #ffffff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #f39c12;
            font-size: 34px;
            text-align: center;
            margin-bottom: 30px;
            font-weight: 700;
        }
        .blog-post {
            border-bottom: 1px solid #dee2e6;
            padding: 20px 0;
            transition: transform 0.2s ease-in-out;
        }
        .blog-post:hover {
            transform: translateX(5px);
        }
        .blog-post:last-child {
            border-bottom: none;
        }
        .blog-post h2 {
            color: #e67e22;
            font-size: 24px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .blog-post .meta {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        .blog-post .category {
            background: #f39c12;
            color: #ffffff;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            text-transform: uppercase;
        }
        .blog-post p {
            font-size: 16px;
            margin-bottom: 15px;
            text-align: justify;
        }
        .blog-details {
            display: none;
            font-size: 16px;
            margin-bottom: 15px;
            text-align: justify;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .read-more-btn {
            color: #f39c12;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
            transition: color 0.3s ease;
            margin-right: 20px;
        }
        .read-more-btn:hover {
            color: #e67e22;
            text-decoration: underline;
        }
        .full-article {
            color: #f39c12;
            text-decoration: none;
            font-weight: 700;
            transition: color 0.3s ease;
        }
        .full-article:hover {
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
            margin: 30px 0;
        }
        .back-button i {
            margin-right: 10px;
        }
        .back-button:hover {
            background: #e67e22;
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .footer {
            margin-top: 40px;
            font-size: 14px;
            color: #6c757d;
            text-align: center;
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
        }
        .contact-link {
            color: #f39c12;
            font-weight: 700;
        }
        .contact-link:hover {
            color: #e67e22;
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 25px;
            }
            h1 {
                font-size: 28px;
            }
            .blog-post h2 {
                font-size: 20px;
            }
            .blog-post p {
                font-size: 15px;
            }
            .back-button {
                padding: 12px 20px;
                font-size: 15px;
            }
        }
        @media (max-width: 480px) {
            body {
                padding: 20px 10px;
            }
            .container {
                padding: 20px;
            }
            h1 {
                font-size: 24px;
            }
            .blog-post h2 {
                font-size: 18px;
            }
            .blog-post p {
                font-size: 14px;
            }
            .back-button {
                padding: 10px 15px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <main class="container" role="main">
        <header>
            <h1><i class="fas fa-blog"></i> Blog</h1>
        </header>

        <section>
            <?php foreach ($blog_posts as $index => $post): ?>
                <article class="blog-post">
                    <h2><?php echo $post['title']; ?></h2>
                    <div class="meta">
                        <span><i class="fas fa-calendar-alt"></i> <?php echo $post['date']; ?></span> | 
                        <span class="category"><?php echo $post['category']; ?></span>
                    </div>
                    <p class="excerpt"><?php echo $post['excerpt']; ?></p>
                    <p class="blog-details" id="details-<?php echo $index; ?>"><?php echo $post['details']; ?></p>
                    <div>
                        <span class="read-more-btn" onclick="toggleDetails(<?php echo $index; ?>)">Read More <i class="fas fa-arrow-right"></i></span>
                        <a href="<?php echo $post['link']; ?>" class="full-article" target="_blank">Full Article <i class="fas fa-external-link-alt"></i></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <!-- Back Button -->
        <button class="back-button" onclick="history.back()" aria-label="Return to Previous Page">
            <i class="fas fa-arrow-left"></i> Back to Previous Page
        </button>

        <footer class="footer">
            <p>© <?php echo $year; ?> Emmanuel Kirui. All rights reserved.<br>For inquiries or collaborations, contact us at <a href="mailto:emmanuelkirui042@gmail.com" class="contact-link">emmanuelkirui042@gmail.com</a>.</p>
        </footer>
    </main>

    <script>
        function toggleDetails(index) {
            const details = document.getElementById(`details-${index}`);
            const excerpt = details.previousElementSibling; // The excerpt paragraph
            const button = details.nextElementSibling.firstElementChild; // The read more button

            if (details.style.display === "none" || details.style.display === "") {
                details.style.display = "block";
                excerpt.style.display = "none";
                button.innerHTML = 'Show Less <i class="fas fa-arrow-up"></i>';
            } else {
                details.style.display = "none";
                excerpt.style.display = "block";
                button.innerHTML = 'Read More <i class="fas fa-arrow-right"></i>';
            }
        }
    </script>
</body>
</html>

<?php
$year = date("Y");
// Blog posts array with full_content field
$blog_posts = [
    [
        "title" => "Top Football Predictions for the Weekend",
        "date" => "March 01, 2025",
        "excerpt" => "Get the latest insights and predictions for this weekend's football matches, backed by expert analysis and stats.",
        "details" => "Our expert team has analyzed upcoming matches considering team form, player injuries, and historical data. This weekend, expect Manchester City to dominate with a predicted 3-1 win over Arsenal, while Liverpool might face a tough 2-2 draw against Chelsea. Detailed stats and betting odds included.",
        "full_content" => "This weekend’s football predictions are based on extensive analysis. Manchester City vs. Arsenal: City’s recent form (5 wins in 6) and Arsenal’s injury concerns suggest a 3-1 victory. Liverpool vs. Chelsea: Both teams are evenly matched, with Salah and Havartz in top form, likely ending in a 2-2 draw. Additional insights include Tottenham’s potential upset against United (2-1) with detailed statistical breakdowns and odds from top bookmakers.",
        "category" => "Football Predictions",
        "learn_more_link" => "#"
    ],
    [
        "title" => "Creating Stunning Graphics for Social Media",
        "date" => "February 28, 2025",
        "excerpt" => "Learn tips and tricks to design eye-catching graphics that boost engagement on your social platforms.",
        "details" => "Explore tools like Canva and Photoshop, master color theory, and learn layout techniques. This guide includes 5 practical examples to increase your click-through rates by 30% with optimized visuals for Instagram and Twitter.",
        "full_content" => "Design stunning graphics with this in-depth guide. Use Canva for quick templates or Photoshop for custom designs. Master color theory with complementary palettes, and optimize layouts with the rule of thirds. Five examples: 1) Instagram carousel (CTR +35%), 2) Twitter infographic (+20% shares), 3) Bold quote post, 4) Animated GIF teaser, 5) Story poll graphic. Includes step-by-step tutorials and free resource links.",
        "category" => "Design Graphics",
        "learn_more_link" => "#"
    ],
    [
        "title" => "Crafting Engaging Motion Content: A Step-by-Step Guide",
        "date" => "February 27, 2025",
        "excerpt" => "Discover how to write compelling motion content that captivates your audience.",
        "details" => "Learn the process of scripting and structuring motion content for videos, including pacing, tone, and audience targeting. Includes a breakdown of a successful promotional video script that increased conversions by 25%.",
        "full_content" => "Craft motion content that hooks viewers in seconds. Start with a strong opener, pace your script at 120 words/minute, and tailor tone to your audience (e.g., casual for Gen Z). Breakdown of a promo script: 10-sec hook, 30-sec problem-solution pitch, 20-sec call-to-action—boosted conversions by 25%. Full sample script and pacing chart included, plus tips for voiceovers and captions.",
        "category" => "Motion Content Writing",
        "learn_more_link" => "#"
    ],
    [
        "title" => "Building a Responsive Website from Scratch",
        "date" => "February 26, 2025",
        "excerpt" => "A beginner’s guide to designing and coding a fully responsive website.",
        "details" => "Step-by-step tutorial covering HTML, CSS, and JavaScript to create a responsive site. Features media queries, flexbox, and a sample project with code snippets that adapts to mobile, tablet, and desktop screens.",
        "full_content" => "Build a responsive website with this comprehensive guide. Start with semantic HTML5, style with CSS flexbox and grid, and enhance with JavaScript for interactivity. Includes media queries (@media (max-width: 768px)) for mobile-first design, a sample portfolio site code (header, nav, gallery), and debugging tips for cross-browser compatibility. Full project files available.",
        "category" => "Web Design",
        "learn_more_link" => "#"
    ],
    [
        "title" => "Producing Your First Track: Tools and Techniques",
        "date" => "February 25, 2025",
        "excerpt" => "Explore the essentials of music production, from software to mixing tips.",
        "details" => "Dive into DAWs like Ableton Live and FL Studio, understand EQ, compression, and reverb, and follow a beginner’s workflow to produce a track. Includes recommended plugins and a sample beat breakdown.",
        "full_content" => "Produce your first track with this detailed walkthrough. Set up Ableton Live or FL Studio, layer drums (kick at 808Hz), synths, and vocals. Apply EQ (cut below 100Hz), compression (4:1 ratio), and reverb (wet 20%). Workflow: 1) Beat creation, 2) Melody layering, 3) Mixing, 4) Mastering. Includes plugin recs (Serum, OTT) and a full hip-hop beat breakdown.",
        "category" => "Music Production",
        "learn_more_link" => "#"
    ],
    [
        "title" => "Writing Lyrics That Resonate: A Beginner’s Guide",
        "date" => "February 24, 2025",
        "excerpt" => "Learn the art of crafting lyrics that connect with listeners, blending structure and emotion.",
        "details" => "Master rhyme schemes, storytelling, and emotional hooks with examples from popular songs. Includes exercises to write your first verse and chorus, plus tips to avoid common pitfalls.",
        "full_content" => "Write resonant lyrics with this beginner’s roadmap. Use AABB rhyme schemes or ABAB for variety, weave a story (verse: setup, chorus: payoff), and hook with emotion (‘tears fall like rain’). Examples: Billie Eilish’s ‘Bad Guy’ structure, exercises for a 16-bar verse, and chorus crafting. Avoid clichés with a 10-tip checklist and refine with a sample love song draft.",
        "category" => "Lyrics Making",
        "learn_more_link" => "#"
    ],
    [
        "title" => "Animating Your Ideas: Tips for Stunning Video Animations",
        "date" => "February 23, 2025",
        "excerpt" => "Bring your concepts to life with these video animation techniques.",
        "details" => "Learn keyframe animation in After Effects, storyboarding basics, and timing tricks to create smooth, engaging visuals. Features a case study of a 30-second animated explainer video.",
        "full_content" => "Animate your ideas with pro tips. Master After Effects keyframes (easing at 75%), storyboard a 3-act flow, and time movements (0.1s bounce). Case study: 30-sec explainer—5-sec intro, 20-sec demo, 5-sec outro—drove 40% engagement. Full storyboard template, timing chart, and resource list (e.g., VideoHive assets) included.",
        "category" => "Video Animation",
        "learn_more_link" => "#"
    ],
    [
        "title" => "Editing Videos Like a Pro: Software and Strategies",
        "date" => "February 22, 2025",
        "excerpt" => "Master video editing with these tools and approaches for polished results.",
        "details" => "Compare Premiere Pro and DaVinci Resolve, learn cutting techniques, color grading, and audio syncing. Includes a workflow to edit a 5-minute vlog with professional transitions and effects.",
        "full_content" => "Edit videos like a pro with this deep dive. Premiere Pro for timeline precision, DaVinci Resolve for color grading (LUTs at 50% opacity). Techniques: J-cuts, cross-dissolves (1s), and audio sync (-3dB ducking). Workflow for a 5-min vlog: 1) Rough cut, 2) Transitions, 3) Grade, 4) Audio mix. Full settings guide and sample project breakdown included.",
        "category" => "Video Editing",
        "learn_more_link" => "#"
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
        .blog-details, .blog-full-content {
            display: none;
            font-size: 16px;
            margin-bottom: 15px;
            text-align: justify;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .read-more-btn, .full-article-btn, .learn-more-btn {
            color: #f39c12;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
            transition: color 0.3s ease;
            margin-right: 20px;
        }
        .read-more-btn:hover, .full-article-btn:hover, .learn-more-btn:hover {
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
                    <p class="blog-full-content" id="full-content-<?php echo $index; ?>"><?php echo $post['full_content']; ?></p>
                    <div>
                        <span class="read-more-btn" onclick="toggleDetails(<?php echo $index; ?>)">Read More <i class="fas fa-arrow-right"></i></span>
                        <span class="full-article-btn" onclick="toggleFullContent(<?php echo $index; ?>)">Full Article <i class="fas fa-arrow-down"></i></span>
                        <a href="<?php echo $post['learn_more_link']; ?>" class="learn-more-btn" target="_blank">Learn More <i class="fas fa-external-link-alt"></i></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <!-- Back Button -->
        <button class="back-button" onclick="window.history.back()" aria-label="Return to Previous Page">
            <i class="fas fa-arrow-left"></i> Back to Previous Page
        </button>

        <footer class="footer">
            <p>© <?php echo $year; ?> Emmanuel Kirui. All rights reserved.<br>For inquiries or collaborations, contact us at <a href="mailto:emmanuelkirui042@gmail.com" class="contact-link">emmanuelkirui042@gmail.com</a>.</p>
        </footer>
    </main>

    <script>
        function toggleDetails(index) {
            const details = document.getElementById(`details-${index}`);
            const fullContent = document.getElementById(`full-content-${index}`);
            const excerpt = details.previousElementSibling;
            const readMoreBtn = details.nextElementSibling.nextElementSibling.firstElementChild;

            if (details.style.display === "none" || details.style.display === "") {
                details.style.display = "block";
                excerpt.style.display = "none";
                fullContent.style.display = "none";
                readMoreBtn.innerHTML = 'Show Less <i class="fas fa-arrow-up"></i>';
            } else {
                details.style.display = "none";
                excerpt.style.display = "block";
                readMoreBtn.innerHTML = 'Read More <i class="fas fa-arrow-right"></i>';
            }
        }

        function toggleFullContent(index) {
            const fullContent = document.getElementById(`full-content-${index}`);
            const details = document.getElementById(`details-${index}`);
            const excerpt = details.previousElementSibling;
            const fullArticleBtn = fullContent.nextElementSibling.children[1];
            const readMoreBtn = fullContent.nextElementSibling.firstElementChild;

            if (fullContent.style.display === "none" || fullContent.style.display === "") {
                fullContent.style.display = "block";
                details.style.display = "none";
                excerpt.style.display = "none";
                fullArticleBtn.innerHTML = 'Collapse <i class="fas fa-arrow-up"></i>';
                readMoreBtn.innerHTML = 'Show Less <i class="fas fa-arrow-up"></i>';
            } else {
                fullContent.style.display = "none";
                excerpt.style.display = "block";
                fullArticleBtn.innerHTML = 'Full Article <i class="fas fa-arrow-down"></i>';
                readMoreBtn.innerHTML = 'Read More <i class="fas fa-arrow-right"></i>';
            }
        }
    </script>
</body>
</html>

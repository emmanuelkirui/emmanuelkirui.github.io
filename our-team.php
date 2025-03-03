<?php
$year = date("Y");

// Team members array (you can add more later)
$team_members = [
    [
        'name' => 'Emmanuel Kirui',
        'role' => 'Founder & Lead Developer',
        'profile_pic' => 'uploads/avatars/logov1.1.png',
        'bio' => 'Passionate about creating innovative solutions and leading the team to success.'
    ],
    [
        'name' => 'Valentine Cheptoo',
        'role' => 'Creative Director',
        'profile_pic' => 'uploads/avatars/valm.png',
        'bio' => 'Expert in design and branding, bringing creativity to every project.'
    ]
    // Add more team members here later
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Team | Creative Pulse</title>

    <!-- Preconnect to external domains -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">

    <!-- External Stylesheets -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,700;1,400&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            line-height: 1.6;
            padding: 40px 20px;
            min-height: 100vh;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }
        h1 {
            color: #f39c12;
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background: #f39c12;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 700;
            transition: background 0.3s ease;
            margin-bottom: 2rem;
        }
        .back-button:hover {
            background: #e67e22;
        }
        .back-button i {
            margin-right: 8px;
        }
        .team-container {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            justify-content: center;
            padding: 0 1rem;
        }
        .team-member {
            flex: 1 1 300px;
            max-width: 350px;
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid #eee;
        }
        .team-member:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }
        .profile-pic-container {
            width: 180px;
            height: 180px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #f39c12;
            background: #f8f9fa;
        }
        .profile-pic {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            display: block;
        }
        .member-name {
            color: #2d3436;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .member-role {
            color: #f39c12;
            font-size: 1.1rem;
            font-weight: 400;
            margin-bottom: 1rem;
        }
        .member-bio {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .footer {
            text-align: center;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .contact-link {
            color: #f39c12;
            text-decoration: none;
            font-weight: 700;
            transition: color 0.3s ease;
        }
        .contact-link:hover {
            color: #e67e22;
            text-decoration: underline;
        }

        /* Responsive Design */
        @media (max-width: 900px) {
            .team-member {
                flex: 1 1 250px;
                max-width: 300px;
            }
            .profile-pic-container {
                width: 150px;
                height: 150px;
            }
            h1 {
                font-size: 2rem;
            }
        }
        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }
            .team-container {
                gap: 1.5rem;
            }
            .team-member {
                flex: 1 1 100%;
                max-width: 100%;
            }
            .profile-pic-container {
                width: 120px;
                height: 120px;
            }
            h1 {
                font-size: 1.75rem;
            }
            .member-name {
                font-size: 1.25rem;
            }
            .member-role {
                font-size: 1rem;
            }
            .back-button {
                width: 100%;
                text-align: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="javascript:history.back()" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to History
        </a>
        
        <h1><i class="fas fa-users"></i> Our Team</h1>
        
        <div class="team-container">
            <?php foreach ($team_members as $member): ?>
                <div class="team-member">
                    <div class="profile-pic-container">
                        <img src="<?php echo htmlspecialchars($member['profile_pic']); ?>" 
                             alt="<?php echo htmlspecialchars($member['name']); ?>'s Profile Picture" 
                             class="profile-pic">
                    </div>
                    <div class="member-name"><?php echo htmlspecialchars($member['name']); ?></div>
                    <div class="member-role"><?php echo htmlspecialchars($member['role']); ?></div>
                    <div class="member-bio"><?php echo htmlspecialchars($member['bio']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <footer class="footer">
            <p>© <?php echo $year; ?> Creative Pulse. All rights reserved.<br>
            Contact us at <a href="mailto:emmanuelkirui042@gmail.com" class="contact-link">emmanuelkirui042@gmail.com</a></p>
        </footer>
    </div>
</body>
</html>

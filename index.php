<?php
session_start();
require_once 'config/database.php';

// Get latest news (3 articles)
$latestNews = $pdo->query("
    SELECT id, title, excerpt, image, category, created_at 
    FROM news 
    WHERE is_published = TRUE 
    ORDER BY created_at DESC 
    LIMIT 3
")->fetchAll();

// Get latest quizzes (6 active quizzes)
$latestQuizzes = $pdo->query("
    SELECT id, title, category, duration, 
           (SELECT COUNT(*) FROM questions WHERE quiz_id = quizzes.id) as question_count
    FROM quizzes 
    WHERE is_active = TRUE 
    ORDER BY created_at DESC 
    LIMIT 6
")->fetchAll();

// Get quiz categories with counts
$categories = $pdo->query("
    SELECT category, COUNT(*) as quiz_count
    FROM quizzes 
    WHERE is_active = TRUE 
    GROUP BY category 
    ORDER BY quiz_count DESC 
    LIMIT 8
")->fetchAll();

// Get most popular quizzes (by views if you have view tracking)
$popularQuizzes = $pdo->query("
    SELECT id, title, category, 
           (SELECT COUNT(*) FROM questions WHERE quiz_id = quizzes.id) as question_count
    FROM quizzes 
    WHERE is_active = TRUE 
    ORDER BY RAND() 
    LIMIT 4
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StudyHub - Learn, Quiz, Succeed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #7209b7;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #4cc9f0;
            --warning-color: #f72585;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }
        
        .navbar-brand {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--primary-color) !important;
        }
        
        .hero-section {
            background: linear-gradient(rgba(67, 97, 238, 0.9), rgba(58, 12, 163, 0.9)), url('https://images.unsplash.com/photo-1434030216411-0b793f4b4173?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            margin-bottom: 60px;
            border-radius: 0 0 40px 40px;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .btn-hero {
            background: var(--warning-color);
            color: white;
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-hero:hover {
            background: #d40c6a;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .section-title {
            position: relative;
            margin-bottom: 40px;
            padding-bottom: 15px;
            color: var(--secondary-color);
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--accent-color);
            border-radius: 2px;
        }
        
        .news-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .news-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .news-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        
        .news-category {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--primary-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .quiz-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .quiz-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(67, 97, 238, 0.1);
        }
        
        .quiz-category {
            display: inline-block;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 15px;
        }
        
        .category-card {
            text-align: center;
            padding: 30px 20px;
            border-radius: 15px;
            background: white;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .category-card:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-10px);
        }
        
        .category-card:hover .category-icon {
            background: white;
            color: var(--primary-color);
        }
        
        .category-icon {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: var(--light-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .stat-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .footer {
            background: var(--dark-color);
            color: white;
            padding: 60px 0 30px;
            margin-top: 80px;
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 20px;
        }
        
        .news-date {
            font-size: 0.85rem;
            color: #6c757d;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .badge-new {
            background: var(--warning-color);
            color: white;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-left: 10px;
        }
        
        .gradient-text {
            background: linear-gradient(45deg, var(--primary-color), var(--warning-color));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: 700;
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white py-3 shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-journal-bookmark-fill me-2"></i>StudyHub
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="news.php">News</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="quizzes.php">Quizzes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">Categories</a>
                    </li>
                    <!-- <li class="nav-item">
                        <a class="nav-link" href="admin.php">Admin</a>
                    </li> -->
                </ul>
                
                <div class="ms-lg-3 mt-3 mt-lg-0">
                    <?php if (isset($_SESSION['admin_id'])): ?>
                        <a href="admin_dashboard.php" class="btn btn-primary">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                    <?php else: ?>
                        <!-- <a href="admin.php" class="btn btn-outline-primary">
                            <i class="bi bi-lock me-2"></i>Admin Login
                        </a> -->
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="hero-title">Learn Smart, <span class="gradient-text">Quiz Hard</span></h1>
                    <p class="hero-subtitle">Discover latest educational news, test your knowledge with interactive quizzes, and master various subjects through our comprehensive learning platform.</p>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="#quizzes" class="btn btn-hero">
                            <i class="bi bi-play-circle me-2"></i>Start Quizzing
                        </a>
                        <a href="#news" class="btn btn-outline-light">
                            <i class="bi bi-newspaper me-2"></i>Read News
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 mt-5 mt-lg-0">
                    <div class="text-center floating-animation">
                        <img src="https://images.unsplash.com/photo-1588072432836-e10032774350?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" 
                             alt="Learning" class="img-fluid rounded-circle" style="max-height: 400px; border: 5px solid white;">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="container mb-5">
        <div class="row">
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-number">
                        <?php echo count($latestQuizzes); ?>+
                    </div>
                    <div class="stat-label">Active Quizzes</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-number">
                        <?php echo count($categories); ?>+
                    </div>
                    <div class="stat-label">Categories</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-number">
                        <?php 
                        $totalQuestions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
                        echo $totalQuestions; ?>+
                    </div>
                    <div class="stat-label">Questions</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-number">
                        <?php echo count($latestNews); ?>+
                    </div>
                    <div class="stat-label">News Articles</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Latest News Section -->
    <section id="news" class="container mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title">Latest Education News</h2>
            <a href="news.php" class="btn btn-link text-decoration-none">
                View All <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        
        <div class="row">
            <?php if (empty($latestNews)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>No news articles published yet.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($latestNews as $news): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="news-card">
                            <div class="position-relative">
                                <?php if ($news['image']): ?>
                                    <img src="uploads/news/<?php echo htmlspecialchars($news['image']); ?>" 
                                         class="news-image" alt="<?php echo htmlspecialchars($news['title']); ?>">
                                <?php else: ?>
                                    <img src="https://images.unsplash.com/photo-1586769852044-692eb51d3d6e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" 
                                         class="news-image" alt="News Image">
                                <?php endif; ?>
                                <span class="news-category"><?php echo htmlspecialchars($news['category']); ?></span>
                            </div>
                            <div class="card-body p-4">
                                <h5 class="card-title mb-3">
                                    <?php echo htmlspecialchars($news['title']); ?>
                                    <?php if (strtotime($news['created_at']) > strtotime('-3 days')): ?>
                                        <span class="badge-new">NEW</span>
                                    <?php endif; ?>
                                </h5>
                                <p class="card-text text-muted mb-4">
                                    <?php echo htmlspecialchars($news['excerpt']); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="news-date">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        <?php echo date('M d, Y', strtotime($news['created_at'])); ?>
                                    </span>
                                    <a href="news_single.php?id=<?php echo $news['id']; ?>" class="btn btn-sm btn-outline-primary">
                                        Read More
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Popular Categories -->
    <section class="container mb-5">
        <h2 class="section-title">Browse by Category</h2>
        <div class="row">
            <?php if (empty($categories)): ?>
                <div class="col-12">
                    <div class="alert alert-info">No categories available yet.</div>
                </div>
            <?php else: ?>
                <?php 
                $categoryIcons = [
                    'Mathematics' => 'bi-calculator',
                    'Science' => 'bi-egg-fried',
                    'Geography' => 'bi-globe',
                    'History' => 'bi-clock-history',
                    'English' => 'bi-translate',
                    'Programming' => 'bi-code-slash',
                    'General' => 'bi-lightbulb',
                    'Technology' => 'bi-cpu'
                ];
                
                foreach ($categories as $category): 
                    $icon = $categoryIcons[$category['category']] ?? 'bi-book';
                ?>
                    <div class="col-lg-3 col-md-4 col-sm-6">
                        <a href="category.php?name=<?php echo urlencode($category['category']); ?>" 
                           class="text-decoration-none text-dark">
                            <div class="category-card">
                                <div class="category-icon">
                                    <i class="bi <?php echo $icon; ?>"></i>
                                </div>
                                <h5><?php echo htmlspecialchars($category['category']); ?></h5>
                                <p class="text-muted mb-0">
                                    <?php echo $category['quiz_count']; ?> Quizzes
                                </p>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Latest Quizzes -->
    <section id="quizzes" class="container mb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title">Latest Quizzes</h2>
            <a href="quizzes.php" class="btn btn-link text-decoration-none">
                View All <i class="bi bi-arrow-right"></i>
            </a>
        </div>
        
        <div class="row">
            <?php if (empty($latestQuizzes)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>No quizzes available yet.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($latestQuizzes as $quiz): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="quiz-card">
                            <span class="quiz-category">
                                <?php echo htmlspecialchars($quiz['category']); ?>
                            </span>
                            <h4 class="mb-3"><?php echo htmlspecialchars($quiz['title']); ?></h4>
                            <p class="text-muted mb-4">
                                <i class="bi bi-clock me-1"></i> <?php echo $quiz['duration']; ?> min &nbsp;&nbsp;
                                <i class="bi bi-question-circle me-1"></i> <?php echo $quiz['question_count']; ?> questions
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-star-fill text-warning me-1"></i>
                                    <small>4.8</small>
                                </div>
                                <a href="take_quiz.php?id=<?php echo $quiz['id']; ?>" 
                                   class="btn btn-primary btn-sm">
                                    <i class="bi bi-play-fill me-1"></i> Start Quiz
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Features Section -->
    <section class="bg-light py-5 mt-5">
        <div class="container">
            <h2 class="section-title text-center mb-5">Why Choose StudyHub?</h2>
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="text-center p-4">
                        <div class="feature-icon mx-auto">
                            <i class="bi bi-lightning-charge"></i>
                        </div>
                        <h4 class="mb-3">Instant Results</h4>
                        <p class="text-muted">Get immediate feedback on your quiz performance with detailed explanations.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="text-center p-4">
                        <div class="feature-icon mx-auto">
                            <i class="bi bi-newspaper"></i>
                        </div>
                        <h4 class="mb-3">Daily News</h4>
                        <p class="text-muted">Stay updated with the latest educational news and learning trends.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="text-center p-4">
                        <div class="feature-icon mx-auto">
                            <i class="bi bi-bar-chart"></i>
                        </div>
                        <h4 class="mb-3">Track Progress</h4>
                        <p class="text-muted">Monitor your learning journey with detailed analytics and progress tracking.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="text-center p-4">
                        <div class="feature-icon mx-auto">
                            <i class="bi bi-device-ssd"></i>
                        </div>
                        <h4 class="mb-3">Mobile Friendly</h4>
                        <p class="text-muted">Access our platform anytime, anywhere on any device with responsive design.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h3 class="mb-4">
                        <i class="bi bi-journal-bookmark-fill me-2"></i>StudyHub
                    </h3>
                    <p class="mb-4">Your ultimate destination for interactive learning, knowledge testing, and educational growth.</p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white"><i class="bi bi-facebook fs-5"></i></a>
                        <a href="#" class="text-white"><i class="bi bi-twitter fs-5"></i></a>
                        <a href="#" class="text-white"><i class="bi bi-instagram fs-5"></i></a>
                        <a href="#" class="text-white"><i class="bi bi-linkedin fs-5"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-white-50 text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="news.php" class="text-white-50 text-decoration-none">News</a></li>
                        <li class="mb-2"><a href="quizzes.php" class="text-white-50 text-decoration-none">Quizzes</a></li>
                        <li class="mb-2"><a href="categories.php" class="text-white-50 text-decoration-none">Categories</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="mb-4">Categories</h5>
                    <div class="row">
                        <?php 
                        $footerCategories = array_slice($categories, 0, 4);
                        foreach ($footerCategories as $cat): 
                        ?>
                            <div class="col-6 mb-2">
                                <a href="category.php?name=<?php echo urlencode($cat['category']); ?>" 
                                   class="text-white-50 text-decoration-none">
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="mb-4">Contact Us</h5>
                    <p class="text-white-50 mb-2">
                        <i class="bi bi-envelope me-2"></i> info@studyhub.com
                    </p>
                    <p class="text-white-50 mb-2">
                        <i class="bi bi-telephone me-2"></i> +1 234 567 890
                    </p>
                    <p class="text-white-50">
                        <i class="bi bi-geo-alt me-2"></i> 123 Learning Street
                    </p>
                </div>
            </div>
            <hr class="bg-white-50 my-4">
            <div class="row">
                <div class="col-md-6">
                    <p class="text-white-50 mb-0">
                        &copy; <?php echo date('Y'); ?> StudyHub. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="#" class="text-white-50 me-3 text-decoration-none">Privacy Policy</a>
                    <a href="#" class="text-white-50 text-decoration-none">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                }
            });
        }, observerOptions);

        // Observe elements to animate
        document.querySelectorAll('.news-card, .quiz-card, .category-card').forEach(el => {
            observer.observe(el);
        });
    </script>
</body>
</html>
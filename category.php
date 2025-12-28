<?php
session_start();
require_once 'config/database.php';

// Check if category name is provided
if (!isset($_GET['name']) || empty($_GET['name'])) {
    header("Location: categories.php");
    exit();
}

$category_name = urldecode($_GET['name']);

// Get quizzes for this category from 'quizzes' table (plural)
$quizzes_query = $pdo->prepare("
    SELECT id, title, category, duration, created_at
    FROM quizzes 
    WHERE category = ? 
    ORDER BY created_at DESC
");

$quizzes_query->execute([$category_name]);
$quizzes = $quizzes_query->fetchAll();

// Get question count for each quiz
foreach ($quizzes as &$quiz) {
    $question_count = $pdo->prepare("
        SELECT COUNT(*) 
        FROM questions 
        WHERE quiz_id = ?
    ");
    $question_count->execute([$quiz['id']]);
    $quiz['question_count'] = $question_count->fetchColumn();
}

$total_quizzes = count($quizzes);

// Get all unique categories for sidebar
$categories_query = $pdo->query("
    SELECT category, COUNT(*) as quiz_count
    FROM quizzes 
    WHERE category IS NOT NULL AND category != ''
    GROUP BY category 
    ORDER BY category
");
$all_categories = $categories_query->fetchAll();

// Calculate totals
$total_questions = 0;
$total_duration = 0;
foreach ($quizzes as $quiz) {
    $total_questions += $quiz['question_count'];
    $total_duration += $quiz['duration'] ?? 15; // Default to 15 if null
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($category_name); ?> Quizzes - StudyHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #7209b7;
            --warning-color: #f72585;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f8f9fa;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Poppins', sans-serif;
        }
        
        .category-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 60px 0;
            border-radius: 0 0 30px 30px;
            margin-bottom: 40px;
        }
        
        .quiz-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .quiz-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(67, 97, 238, 0.15);
        }
        
        .quiz-category {
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .quiz-stats {
            display: flex;
            gap: 20px;
            margin: 15px 0;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .stat-icon {
            color: var(--primary-color);
        }
        
        .difficulty-badge {
            padding: 3px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .difficulty-easy {
            background: #d4edda;
            color: #155724;
        }
        
        .difficulty-medium {
            background: #fff3cd;
            color: #856404;
        }
        
        .difficulty-hard {
            background: #f8d7da;
            color: #721c24;
        }
        
        .category-sidebar {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .category-link {
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }
        
        .category-link:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .category-link.active {
            background: var(--primary-color);
            color: white;
            font-weight: 500;
        }
        
        .category-count {
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.8rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .take-quiz-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .take-quiz-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
            color: white;
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
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="news.php">News</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="quizzes.php">Quizzes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="categories.php">Categories</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Category Header -->
    <section class="category-header">
        <div class="container">
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb" style="--bs-breadcrumb-divider-color: rgba(255,255,255,0.7);">
                    <li class="breadcrumb-item"><a href="index.php" class="text-white-50">Home</a></li>
                    <li class="breadcrumb-item"><a href="categories.php" class="text-white-50">Categories</a></li>
                    <li class="breadcrumb-item active text-white" aria-current="page"><?php echo htmlspecialchars($category_name); ?></li>
                </ol>
            </nav>
            
            <h1 class="display-4 fw-bold mb-3"><?php echo htmlspecialchars($category_name); ?></h1>
            <p class="lead mb-0">
                <?php echo $total_quizzes; ?> quiz<?php echo $total_quizzes != 1 ? 'zes' : ''; ?> 
                • <?php echo $total_questions; ?> questions 
                • <?php echo $total_duration; ?> total minutes
            </p>
        </div>
    </section>

    <div class="container">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-9">
                <!-- Stats Row -->
                <div class="row mb-5">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $total_quizzes; ?></div>
                            <div class="text-muted">Total Quizzes</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $total_questions; ?></div>
                            <div class="text-muted">Total Questions</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-number">
                                <?php echo $total_quizzes > 0 ? round($total_questions / $total_quizzes, 1) : 0; ?>
                            </div>
                            <div class="text-muted">Avg Questions/Quiz</div>
                        </div>
                    </div>
                </div>

                <!-- Quizzes Section -->
                <h3 class="mb-4">
                    <i class="bi bi-question-square me-2"></i>Available Quizzes
                    <span class="badge bg-primary rounded-pill ms-2"><?php echo $total_quizzes; ?></span>
                </h3>
                
                <?php if ($total_quizzes > 0): ?>
                    <?php foreach ($quizzes as $quiz): 
                        // Determine difficulty based on question count
                        $difficulty = 'easy';
                        $difficulty_class = 'difficulty-easy';
                        if ($quiz['question_count'] > 15) {
                            $difficulty = 'medium';
                            $difficulty_class = 'difficulty-medium';
                        }
                        if ($quiz['question_count'] > 30) {
                            $difficulty = 'hard';
                            $difficulty_class = 'difficulty-hard';
                        }
                    ?>
                        <div class="quiz-card">
                            <span class="quiz-category"><?php echo htmlspecialchars($quiz['category']); ?></span>
                            
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h4 class="mb-0"><?php echo htmlspecialchars($quiz['title']); ?></h4>
                                <span class="<?php echo $difficulty_class; ?> difficulty-badge">
                                    <?php echo ucfirst($difficulty); ?>
                                </span>
                            </div>
                            
                            <div class="quiz-stats">
                                <div class="stat-item">
                                    <i class="bi bi-clock stat-icon"></i>
                                    <span><?php echo $quiz['duration'] ?? 15; ?> minutes</span>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-question-circle stat-icon"></i>
                                    <span><?php echo $quiz['question_count']; ?> questions</span>
                                </div>
                                <div class="stat-item">
                                    <i class="bi bi-calendar3 stat-icon"></i>
                                    <span><?php echo date('M d, Y', strtotime($quiz['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-4">
                                  <div>
                                  <!--  <small class="text-muted">
                                        Quiz ID: <?php echo $quiz['id']; ?>
                                    </small> -->
                                </div> 
                                <a href="take_quiz.php?id=<?php echo $quiz['id']; ?>" 
                                   class="take-quiz-btn text-decoration-none">
                                    <i class="bi bi-play-fill me-1"></i> Start Quiz
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-question-circle"></i>
                        </div>
                        <h3 class="mb-3">No Quizzes Found</h3>
                        <p class="text-muted mb-4">
                            There are no quizzes available in the "<?php echo htmlspecialchars($category_name); ?>" category yet.
                        </p>
                        <a href="categories.php" class="btn btn-primary">
                            <i class="bi bi-grid-3x3-gap me-2"></i> Browse All Categories
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="sticky-top" style="top: 20px;">
                    <!-- Categories List -->
                    <div class="category-sidebar">
                        <h5 class="mb-4">
                            <i class="bi bi-grid-3x3-gap me-2"></i>All Categories
                        </h5>
                        
                        <?php if (!empty($all_categories)): ?>
                            <?php foreach ($all_categories as $cat): ?>
                                <a href="category.php?name=<?php echo urlencode($cat['category']); ?>" 
                                   class="category-link <?php echo $cat['category'] == $category_name ? 'active' : ''; ?>">
                                    <span><?php echo htmlspecialchars($cat['category']); ?></span>
                                    <span class="category-count"><?php echo $cat['quiz_count']; ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No categories available</p>
                        <?php endif; ?>
                    </div>

                    <!-- Category Stats -->
                    <div class="category-sidebar">
                        <h5 class="mb-3">
                            <i class="bi bi-bar-chart me-2"></i>Category Statistics
                        </h5>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Total Quizzes</small>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" 
                                     style="width: <?php echo min(100, $total_quizzes * 10); ?>%;"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Total Questions</small>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo min(100, $total_questions / 5); ?>%;"></div>
                            </div>
                        </div>
                        
                        <div>
                            <small class="text-muted d-block mb-1">Avg Duration</small>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold">
                                    <?php echo $total_quizzes > 0 ? round($total_duration / $total_quizzes, 1) : 0; ?> min
                                </span>
                                <small class="text-muted">per quiz</small>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="category-sidebar">
                        <h5 class="mb-3">
                            <i class="bi bi-lightning me-2"></i>Quick Actions
                        </h5>
                        
                        <a href="quizzes.php" class="btn btn-outline-primary w-100 mb-2">
                            <i class="bi bi-list-check me-2"></i> View All Quizzes
                        </a>
                        
                        <a href="categories.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-grid-3x3-gap me-2"></i> All Categories
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h3 class="mb-4">
                        <i class="bi bi-journal-bookmark-fill me-2"></i>StudyHub
                    </h3>
                    <p class="mb-0">Master your knowledge with interactive quizzes and learning resources.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-2">&copy; <?php echo date('Y'); ?> StudyHub. All rights reserved.</p>
                    <p class="mb-0">
                        <a href="index.php" class="text-white-50 text-decoration-none me-3">Home</a>
                        <a href="categories.php" class="text-white-50 text-decoration-none">Categories</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate quiz cards on scroll
            const quizCards = document.querySelectorAll('.quiz-card');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, index * 100);
                    }
                });
            }, { threshold: 0.1 });
            
            quizCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(card);
            });
            
            // Highlight active category
            const activeLinks = document.querySelectorAll('.category-link.active');
            activeLinks.forEach(link => {
                link.style.boxShadow = '0 3px 10px rgba(67, 97, 238, 0.2)';
            });
        });
    </script>
</body>
</html>
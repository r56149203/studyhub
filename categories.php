<?php
session_start();
require_once 'config/database.php';

// Get categories with quiz counts
$categories = $pdo->query("
    SELECT category, COUNT(*) as quiz_count
    FROM quizzes 
    WHERE is_active = TRUE 
    GROUP BY category 
    ORDER BY category
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quiz Categories - StudyHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-5">
        <h1 class="mb-4">Quiz Categories</h1>
        
        <div class="row">
            <?php foreach ($categories as $category): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card text-center h-100">
                        <div class="card-body">
                            <h4><?php echo htmlspecialchars($category['category']); ?></h4>
                            <p class="text-muted"><?php echo $category['quiz_count']; ?> quizzes</p>
                            <a href="category.php?name=<?php echo urlencode($category['category']); ?>" 
                               class="btn btn-outline-primary">
                                Browse Quizzes
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
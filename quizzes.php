<?php
session_start();
require_once 'config/database.php';

// Get all active quizzes with pagination
$quizzes = $pdo->query("
    SELECT id, title, category, duration, 
           (SELECT COUNT(*) FROM questions WHERE quiz_id = quizzes.id) as question_count
    FROM quizzes 
    WHERE is_active = TRUE 
    ORDER BY title
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Quizzes - StudyHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-5">
        <h1 class="mb-4">All Quizzes</h1>
        
        <div class="row">
            <?php foreach ($quizzes as $quiz): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <span class="badge bg-primary mb-2"><?php echo $quiz['category']; ?></span>
                            <h5 class="card-title"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                            <p class="card-text text-muted">
                                <i class="bi bi-clock me-1"></i> <?php echo $quiz['duration']; ?> min<br>
                                <i class="bi bi-question-circle me-1"></i> <?php echo $quiz['question_count']; ?> questions
                            </p>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href="take_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-primary w-100">
                                Start Quiz
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
<?php
// Simple news_single.php
session_start();
require_once 'config/database.php';

$newsId = $_GET['id'] ?? 0;

if (!$newsId) {
    header('Location: news.php');
    exit;
}

// Get news
$stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
$stmt->execute([$newsId]);
$article = $stmt->fetch();

if (!$article) {
    header('Location: news.php');
    exit;
}

// Update views
$pdo->prepare("UPDATE news SET views = views + 1 WHERE id = ?")->execute([$newsId]);
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($article['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">StudyHub</a>
            <a href="news.php" class="btn btn-outline-primary">Back to News</a>
        </div>
    </nav>
    
    <div class="container mt-5">
        <h1><?php echo htmlspecialchars($article['title']); ?></h1>
        <p class="text-muted">
            <?php echo date('F j, Y', strtotime($article['created_at'])); ?> | 
            Views: <?php echo $article['views'] + 1; ?>
        </p>
        
        <?php if ($article['image']): ?>
            <img src="uploads/news/<?php echo $article['image']; ?>" 
                 class="img-fluid mb-4" alt="News Image">
        <?php endif; ?>
        
        <div class="mb-4">
            <?php echo nl2br(htmlspecialchars($article['content'])); ?>
        </div>
        
        <a href="news.php" class="btn btn-primary">Back to News</a>
    </div>
</body>
</html>
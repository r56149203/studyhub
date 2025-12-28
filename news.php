<?php
session_start();
require_once 'config/database.php';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 9;
$offset = ($page - 1) * $limit;

// Get total news count
$totalNews = $pdo->query("SELECT COUNT(*) FROM news WHERE is_published = TRUE")->fetchColumn();
$totalPages = ceil($totalNews / $limit);

// Get news with pagination
$stmt = $pdo->prepare("
    SELECT id, title, excerpt, image, category, author, views, created_at 
    FROM news 
    WHERE is_published = TRUE 
    ORDER BY created_at DESC 
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$newsArticles = $stmt->fetchAll();

// Get popular news
$popularNews = $pdo->query("
    SELECT id, title, image, category, created_at 
    FROM news 
    WHERE is_published = TRUE 
    ORDER BY views DESC 
    LIMIT 5
")->fetchAll();

// Get news categories
$newsCategories = $pdo->query("
    SELECT category, COUNT(*) as count 
    FROM news 
    WHERE is_published = TRUE 
    GROUP BY category 
    ORDER BY count DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Education News - StudyHub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }
        .news-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
            margin-bottom: 50px;
            border-radius: 0 0 30px 30px;
        }
        .news-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: transform 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            height: 100%;
        }
        .news-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        .news-img {
            height: 220px;
            object-fit: cover;
            width: 100%;
        }
        .news-category-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(255,255,255,0.9);
            color: #333;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .news-views {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .pagination .page-link {
            border-radius: 10px;
            margin: 0 5px;
            border: none;
            color: #495057;
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: transparent;
        }
        .sidebar-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .category-tag {
            display: inline-block;
            background: #e9ecef;
            padding: 5px 15px;
            border-radius: 20px;
            margin: 5px;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
        }
        .category-tag:hover {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation (same as index.php) -->
    <?php include 'includes/navbar.php'; ?>

    <!-- News Header -->
    <div class="news-header">
        <div class="container">
            <h1 class="display-4 fw-bold mb-3">Education News & Updates</h1>
            <p class="lead mb-4">Stay informed with the latest educational trends, research, and insights.</p>
            <div class="d-flex gap-3">
                <form class="w-100" method="GET" action="news.php">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control rounded-pill" 
                               placeholder="Search news articles...">
                        <button class="btn btn-light rounded-pill px-4" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-9">
                <?php if (empty($newsArticles)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>No news articles found.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($newsArticles as $article): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="news-card">
                                    <div class="position-relative">
                                        <?php if ($article['image']): ?>
                                            <img src="uploads/news/<?php echo htmlspecialchars($article['image']); ?>" 
                                                 class="news-img" alt="<?php echo htmlspecialchars($article['title']); ?>">
                                        <?php else: ?>
                                            <img src="https://images.unsplash.com/photo-1586769852044-692eb51d3d6e?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80" 
                                                 class="news-img" alt="News Image">
                                        <?php endif; ?>
                                        <span class="news-category-badge"><?php echo htmlspecialchars($article['category']); ?></span>
                                    </div>
                                    <div class="card-body p-4">
                                        <h5 class="card-title">
                                            <a href="news_single.php?id=<?php echo $article['id']; ?>" 
                                               class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($article['title']); ?>
                                            </a>
                                        </h5>
                                        <p class="card-text text-muted mb-3">
                                            <?php echo htmlspecialchars($article['excerpt']); ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar3 me-1"></i>
                                                    <?php echo date('M d, Y', strtotime($article['created_at'])); ?>
                                                </small>
                                                <span class="news-views ms-3">
                                                    <i class="bi bi-eye me-1"></i><?php echo $article['views']; ?>
                                                </span>
                                            </div>
                                            <a href="news_single.php?id=<?php echo $article['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                Read More
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="News pagination">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                            <i class="bi bi-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                            Next <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-3">
                <!-- Popular News -->
                <div class="sidebar-card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="bi bi-fire text-danger me-2"></i>Popular News
                        </h5>
                        <div class="list-group list-group-flush">
                            <?php foreach ($popularNews as $popular): ?>
                                <a href="news_single.php?id=<?php echo $popular['id']; ?>" 
                                   class="list-group-item list-group-item-action border-0 px-0 py-3">
                                    <div class="d-flex align-items-start">
                                        <?php if ($popular['image']): ?>
                                            <img src="uploads/news/<?php echo htmlspecialchars($popular['image']); ?>" 
                                                 class="rounded me-3" width="60" height="60" style="object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center" 
                                                 style="width: 60px; height: 60px;">
                                                <i class="bi bi-newspaper text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($popular['title']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo date('M d', strtotime($popular['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Categories -->
                <div class="sidebar-card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">
                            <i class="bi bi-tags me-2"></i>Categories
                        </h5>
                        <div>
                            <?php foreach ($newsCategories as $cat): ?>
                                <a href="news.php?category=<?php echo urlencode($cat['category']); ?>" 
                                   class="category-tag">
                                    <?php echo htmlspecialchars($cat['category']); ?> 
                                    <span class="badge bg-light text-dark ms-1"><?php echo $cat['count']; ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Newsletter -->
                <div class="sidebar-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Stay Updated</h5>
                        <p class="card-text mb-4">Subscribe to our newsletter for weekly educational insights.</p>
                        <form>
                            <div class="mb-3">
                                <input type="email" class="form-control rounded-pill" placeholder="Your email">
                            </div>
                            <button type="submit" class="btn btn-light rounded-pill w-100">
                                Subscribe Now
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer (same as index.php) -->
    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
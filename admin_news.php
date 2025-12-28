<?php
// admin_news.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php');
    exit;
}

$success = '';
$error = '';

// Handle news actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_news':
                // Handle image upload
                $imageName = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = 'uploads/news/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $fileExt = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (in_array($fileExt, $allowedExt)) {
                        $imageName = uniqid() . '.' . $fileExt;
                        move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName);
                    }
                }
                
                // Create excerpt from content if not provided
                $excerpt = $_POST['excerpt'] ?? '';
                if (empty($excerpt) && !empty($_POST['content'])) {
                    $excerpt = substr(strip_tags($_POST['content']), 0, 200) . '...';
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO news (title, content, excerpt, category, author, image) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    trim($_POST['title']),
                    trim($_POST['content']),
                    trim($excerpt),
                    trim($_POST['category']),
                    trim($_POST['author']),
                    $imageName
                ]);
                
                $success = "News article published successfully!";
                break;
                
            case 'edit_news':
                $newsId = intval($_POST['news_id']);
                
                // Get current image
                $currentImage = null;
                $stmt = $pdo->prepare("SELECT image FROM news WHERE id = ?");
                $stmt->execute([$newsId]);
                $current = $stmt->fetch();
                if ($current) {
                    $currentImage = $current['image'];
                }
                
                // Handle new image upload
                $imageName = $currentImage;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = 'uploads/news/';
                    $fileExt = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (in_array($fileExt, $allowedExt)) {
                        // Delete old image if exists
                        if ($currentImage && file_exists($uploadDir . $currentImage)) {
                            unlink($uploadDir . $currentImage);
                        }
                        
                        $imageName = uniqid() . '.' . $fileExt;
                        move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName);
                    }
                }
                
                // Create excerpt from content if not provided
                $excerpt = $_POST['excerpt'] ?? '';
                if (empty($excerpt) && !empty($_POST['content'])) {
                    $excerpt = substr(strip_tags($_POST['content']), 0, 200) . '...';
                }
                
                $stmt = $pdo->prepare("
                    UPDATE news 
                    SET title = ?, content = ?, excerpt = ?, category = ?, author = ?, image = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([
                    trim($_POST['title']),
                    trim($_POST['content']),
                    trim($excerpt),
                    trim($_POST['category']),
                    trim($_POST['author']),
                    $imageName,
                    $newsId
                ]);
                
                $success = "News article updated successfully!";
                break;
                
            case 'toggle_news':
                $stmt = $pdo->prepare("UPDATE news SET is_published = NOT is_published WHERE id = ?");
                $stmt->execute([intval($_POST['news_id'])]);
                $success = "News status updated!";
                break;
                
            case 'delete_news':
                $newsId = intval($_POST['news_id']);
                
                // Delete associated image
                $stmt = $pdo->prepare("SELECT image FROM news WHERE id = ?");
                $stmt->execute([$newsId]);
                $news = $stmt->fetch();
                
                if ($news && $news['image']) {
                    $imagePath = 'uploads/news/' . $news['image'];
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
                }
                
                // Delete news
                $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
                $stmt->execute([$newsId]);
                
                $success = "News article deleted!";
                break;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get all news articles
$newsArticles = $pdo->query("
    SELECT * FROM news 
    ORDER BY created_at DESC
")->fetchAll();

// News statistics
$totalNews = count($newsArticles);
$publishedNews = $pdo->query("SELECT COUNT(*) FROM news WHERE is_published = TRUE")->fetchColumn();
$totalViews = $pdo->query("SELECT SUM(views) FROM news")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage News - StudyHub Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .news-image-preview {
            width: 100px;
            height: 70px;
            object-fit: cover;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include Admin Sidebar -->
            <?php include 'admin_sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom">
                    <h2>News Management</h2>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#newsModal">
                        <i class="bi bi-plus-circle"></i> Add News
                    </button>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5>Total News</h5>
                                <h2><?php echo $totalNews; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5>Published</h5>
                                <h2><?php echo $publishedNews; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5>Total Views</h5>
                                <h2><?php echo $totalViews; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- News Table -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Author</th>
                                <th>Views</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($newsArticles as $news): ?>
                                <tr>
                                    <td>
                                        <?php if ($news['image']): ?>
                                            <img src="uploads/news/<?php echo htmlspecialchars($news['image']); ?>" 
                                                 class="news-image-preview" alt="News Image">
                                        <?php else: ?>
                                            <div class="news-image-preview bg-light d-flex align-items-center justify-content-center">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($news['title']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($news['category']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($news['author']); ?></td>
                                    <td><?php echo $news['views']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $news['is_published'] ? 'success' : 'warning'; ?>">
                                            <?php echo $news['is_published'] ? 'Published' : 'Draft'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($news['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" 
                                                onclick="editNews(<?php echo $news['id']; ?>)">
                                            Edit
                                        </button>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_news">
                                            <input type="hidden" name="news_id" value="<?php echo $news['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-warning">
                                                <?php echo $news['is_published'] ? 'Unpublish' : 'Publish'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline" 
                                              onsubmit="return confirm('Delete this news article?')">
                                            <input type="hidden" name="action" value="delete_news">
                                            <input type="hidden" name="news_id" value="<?php echo $news['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- News Modal -->
    <div class="modal fade" id="newsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add News Article</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="newsForm">
                    <input type="hidden" name="action" value="add_news" id="newsAction">
                    <input type="hidden" name="news_id" id="newsId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" id="newsTitle" class="form-control" required>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Category *</label>
                                <select name="category" id="newsCategory" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <option value="Education">Education</option>
                                    <option value="Technology">Technology</option>
                                    <option value="Science">Science</option>
                                    <option value="Mathematics">Mathematics</option>
                                    <option value="Career">Career</option>
                                    <option value="General">General</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Author *</label>
                                <input type="text" name="author" id="newsAuthor" class="form-control" 
                                       value="<?php echo $_SESSION['admin_username'] ?? 'Admin'; ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Content *</label>
                            <textarea name="content" id="newsContent" class="form-control" rows="8" required></textarea>
                            <small class="text-muted">Minimum 300 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Excerpt (Optional)</label>
                            <textarea name="excerpt" id="newsExcerpt" class="form-control" rows="3"></textarea>
                            <small class="text-muted">Short summary (if empty, will be generated from content)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Featured Image</label>
                            <input type="file" name="image" id="newsImage" class="form-control" 
                                   accept="image/*">
                            <small class="text-muted">Recommended size: 1200x630px (will be auto-generated if empty)</small>
                            <div id="imagePreview" class="mt-2"></div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save News</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image preview
        document.getElementById('newsImage').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'img-thumbnail';
                    img.style.maxWidth = '200px';
                    preview.appendChild(img);
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        // Edit news function
        function editNews(newsId) {
            fetch('get_news.php?id=' + newsId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('newsAction').value = 'edit_news';
                        document.getElementById('newsId').value = data.news.id;
                        document.getElementById('newsTitle').value = data.news.title;
                        document.getElementById('newsCategory').value = data.news.category;
                        document.getElementById('newsAuthor').value = data.news.author;
                        document.getElementById('newsContent').value = data.news.content;
                        document.getElementById('newsExcerpt').value = data.news.excerpt;
                        
                        // Update modal title
                        document.querySelector('.modal-title').textContent = 'Edit News Article';
                        
                        // Show image preview if exists
                        if (data.news.image) {
                            const preview = document.getElementById('imagePreview');
                            preview.innerHTML = `
                                <p>Current Image:</p>
                                <img src="uploads/news/${data.news.image}" 
                                     class="img-thumbnail" style="max-width: 200px;">
                                <p class="text-muted mt-1">Upload new image to replace</p>
                            `;
                        }
                        
                        // Show modal
                        new bootstrap.Modal(document.getElementById('newsModal')).show();
                    } else {
                        alert('Error loading news: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading news data');
                });
        }
        
        // Reset form when modal is opened for adding new news
        document.getElementById('newsModal').addEventListener('show.bs.modal', function(event) {
            if (!event.relatedTarget) return;
            
            // Reset form
            document.getElementById('newsForm').reset();
            document.getElementById('newsAction').value = 'add_news';
            document.getElementById('newsId').value = '';
            document.getElementById('imagePreview').innerHTML = '';
            document.querySelector('.modal-title').textContent = 'Add News Article';
        });
    </script>
</body>
</html>
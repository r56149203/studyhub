<?php
// includes/navbar.php
?>
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
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" 
                       href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'news.php' ? 'active' : ''; ?>" 
                       href="news.php">News</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'quizzes.php' ? 'active' : ''; ?>" 
                       href="quizzes.php">Quizzes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>" 
                       href="categories.php">Categories</a>
                </li>
            </ul>
            
            <div class="ms-lg-3 mt-3 mt-lg-0">
                <?php if (isset($_SESSION['admin_id'])): ?>
                    <a href="admin_dashboard.php" class="btn btn-primary">
                        <i class="bi bi-speedometer2 me-2"></i>Dashboard
                    </a>
                 <?php else: ?>
                  <!--  <a href="admin.php" class="btn btn-outline-primary">
                        <i class="bi bi-lock me-2"></i>Admin Login
                    </a>  -->
                <?php endif; ?> 
            </div>
        </div>
    </div>
</nav>
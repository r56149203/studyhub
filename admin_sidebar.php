<?php
// admin_sidebar.php
?>
<nav class="col-md-3 col-lg-2 bg-dark sidebar">
    <div class="position-sticky pt-3">
        <h4 class="text-white px-3">Quiz Admin</h4>
        <hr class="text-white">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-white" href="admin_dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <!-- <li class="nav-item">
                <a class="nav-link text-white" href="admin_quizzes.php">
                    <i class="bi bi-question-circle"></i> Manage Quizzes
                </a>
            </li> -->
            <li class="nav-item">
                <a class="nav-link text-white" href="admin_news.php">
                    <i class="bi bi-newspaper"></i> Manage News
                </a>
            </li>
            <!-- <li class="nav-item">
                <a class="nav-link text-white" href="admin_users.php">
                    <i class="bi bi-people"></i> Manage Users
                </a>
            </li> 
            <li class="nav-item">
                <a class="nav-link text-white" href="admin_results.php">
                    <i class="bi bi-graph-up"></i> View Results
                </a>
            </li> -->
            <li class="nav-item mt-4">
                <a class="nav-link text-white" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</nav>
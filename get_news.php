<?php
// get_news.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$newsId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($newsId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid news ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->execute([$newsId]);
    $news = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$news) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'News not found']);
        exit;
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'news' => $news
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
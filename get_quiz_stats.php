<?php
// get_quiz_stats.php
session_start();
require_once 'config/database.php';

// Check admin session
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check quiz_id parameter
if (!isset($_GET['quiz_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Quiz ID required']);
    exit;
}

$quizId = intval($_GET['quiz_id']);

try {
    // Get question count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = ?");
    $stmt->execute([$quizId]);
    $questionCount = $stmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'question_count' => $questionCount
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
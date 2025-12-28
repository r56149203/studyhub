<?php
// get_quiz.php
session_start();
require_once 'config/database.php';

// Check if user is admin
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Get quiz ID
$quizId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($quizId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid quiz ID']);
    exit;
}

try {
    // Get quiz details
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quiz) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Quiz not found']);
        exit;
    }
    
    // Get questions
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id");
    $stmt->execute([$quizId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format questions for JSON
    $formattedQuestions = [];
    foreach ($questions as $question) {
        $options = json_decode($question['options'], true);
        if (!is_array($options)) {
            $options = [];
        }
        
        $formattedQuestions[] = [
            'text' => $question['question_text'],
            'options' => $options,
            'correct_answer' => (int)$question['correct_answer'],
            'explanation' => $question['explanation'] ?: ''
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'quiz' => $quiz,
        'questions' => $formattedQuestions
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
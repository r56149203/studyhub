<?php
session_start();

if (!isset($_SESSION['quiz_result'])) {
    header('Location: index.php');
    exit;
}

$result = $_SESSION['quiz_result'];
unset($_SESSION['quiz_result']); // Clear after showing

require_once 'config/database.php';

// Get quiz info
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
$stmt->execute([$result['quiz_id']]);
$quiz = $stmt->fetch();

// Get questions with answers
$stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ?");
$stmt->execute([$result['quiz_id']]);
$questions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quiz Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h3>Quiz Completed!</h3>
            </div>
            <div class="card-body">
                <h4><?php echo htmlspecialchars($quiz['title']); ?></h4>
                
                <div class="alert alert-info text-center py-3 my-3">
                    <h2>Score: <?php echo $result['score']; ?>/<?php echo $result['total']; ?></h2>
                    <h3><?php echo round($result['percentage'], 1); ?>%</h3>
                </div>
                
                <h5>Review Your Answers:</h5>
                <?php foreach ($questions as $index => $question): 
                    $options = json_decode($question['options'], true);
                    $userAnswer = $result['answers'][$question['id']] ?? null;
                    $isCorrect = ($userAnswer == $question['correct_answer']);
                ?>
                    <div class="card mb-3 <?php echo $isCorrect ? 'border-success' : 'border-danger'; ?>">
                        <div class="card-body">
                            <h6>Question <?php echo $index + 1; ?>:</h6>
                            <p><?php echo htmlspecialchars($question['question_text']); ?></p>
                            
                            <?php foreach ($options as $optionIndex => $option): 
                                $class = '';
                                if ($optionIndex == $question['correct_answer']) {
                                    $class = 'bg-success text-white';
                                } elseif ($optionIndex == $userAnswer && !$isCorrect) {
                                    $class = 'bg-danger text-white';
                                }
                            ?>
                                <div class="p-2 mb-1 <?php echo $class; ?>">
                                    <?php echo htmlspecialchars($option); ?>
                                    <?php if ($optionIndex == $question['correct_answer']): ?>
                                        <span class="float-end">✓ Correct Answer</span>
                                    <?php elseif ($optionIndex == $userAnswer && !$isCorrect): ?>
                                        <span class="float-end">✗ Your Answer</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if ($question['explanation']): ?>
                                <div class="mt-2 text-muted">
                                    <small><strong>Explanation:</strong> <?php echo htmlspecialchars($question['explanation']); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-4">
                    <a href="index.php" class="btn btn-primary">Take Another Quiz</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
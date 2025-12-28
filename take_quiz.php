<?php
session_start();
require_once 'config/database.php';

$quizId = $_GET['id'] ?? 0;

// Get quiz
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND is_active = TRUE");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();

if (!$quiz) {
    die('Quiz not found or inactive.');
}

// Get questions
$stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll();

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $score = 0;
    $total = count($questions);
    $answers = [];
    
    foreach ($questions as $question) {
        $userAnswer = $_POST['q_' . $question['id']] ?? null;
        $answers[$question['id']] = $userAnswer;
        
        if ($userAnswer == $question['correct_answer']) {
            $score++;
        }
    }
    
    // Store in session to show results
    $_SESSION['quiz_result'] = [
        'quiz_id' => $quizId,
        'score' => $score,
        'total' => $total,
        'percentage' => ($score / $total) * 100,
        'answers' => $answers
    ];
    
    header('Location: result.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($quiz['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                <div id="timer" class="badge bg-warning text-dark">Time: <?php echo $quiz['duration']; ?>:00</div>
            </div>
            
            <form method="POST" id="quizForm">
                <div class="card-body">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="mb-4">
                            <h5>Question <?php echo $index + 1; ?>:</h5>
                            <p class="lead"><?php echo htmlspecialchars($question['question_text']); ?></p>
                            
                            <?php 
                            $options = json_decode($question['options'], true);
                            foreach ($options as $optionIndex => $option): 
                            ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" 
                                           name="q_<?php echo $question['id']; ?>" 
                                           value="<?php echo $optionIndex; ?>" 
                                           id="q<?php echo $question['id']; ?>_<?php echo $optionIndex; ?>" 
                                           required>
                                    <label class="form-check-label" for="q<?php echo $question['id']; ?>_<?php echo $optionIndex; ?>">
                                        <?php echo htmlspecialchars($option); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <hr>
                    <?php endforeach; ?>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg">Submit Quiz</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Simple timer
        let minutes = <?php echo $quiz['duration']; ?>;
        let seconds = 0;
        const timerElement = document.getElementById('timer');
        
        function updateTimer() {
            if (seconds === 0) {
                if (minutes === 0) {
                    document.getElementById('quizForm').submit();
                    return;
                }
                minutes--;
                seconds = 59;
            } else {
                seconds--;
            }
            
            timerElement.textContent = `Time: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
        
        setInterval(updateTimer, 1000);
    </script>
</body>
</html>
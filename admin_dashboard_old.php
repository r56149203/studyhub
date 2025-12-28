<?php
// admin_dashboard.php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin.php');
    exit;
}

$success = '';
$error = '';

// Handle quiz creation/editing/deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_quiz':
                // Validate required fields
                if (empty($_POST['title']) || empty($_POST['category']) || empty($_POST['duration'])) {
                    $error = "All quiz fields are required (title, category, duration)";
                    break;
                }
                
                // Validate questions exist
                if (!isset($_POST['questions']) || !is_array($_POST['questions']) || count($_POST['questions']) === 0) {
                    $error = "At least one question is required";
                    break;
                }
                
                // Create new quiz
                $stmt = $pdo->prepare("INSERT INTO quizzes (title, category, duration) VALUES (?, ?, ?)");
                $stmt->execute([
                    trim($_POST['title']),
                    trim($_POST['category']),
                    intval($_POST['duration'])
                ]);
                $quizId = $pdo->lastInsertId();
                
                // Insert questions
                $stmt = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, options, correct_answer, explanation) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($_POST['questions'] as $question) {
                    // Validate question data
                    if (empty($question['text']) || !isset($question['options']) || !is_array($question['options']) || count($question['options']) < 2) {
                        $error = "Each question must have text and at least 2 options";
                        break 2; // Break out of both loops
                    }
                    
                    $stmt->execute([
                        $quizId,
                        trim($question['text']),
                        json_encode($question['options']),
                        intval($question['correct_answer']),
                        isset($question['explanation']) ? trim($question['explanation']) : null
                    ]);
                }
                $success = "Quiz created successfully!";
                break;
                
            case 'edit_quiz':
                // Validate required fields
                if (empty($_POST['title']) || empty($_POST['category']) || empty($_POST['duration']) || empty($_POST['quiz_id'])) {
                    $error = "All quiz fields are required";
                    break;
                }
                
                // Validate questions exist
                if (!isset($_POST['questions']) || !is_array($_POST['questions']) || count($_POST['questions']) === 0) {
                    $error = "At least one question is required";
                    break;
                }
                
                // Update existing quiz
                $quizId = intval($_POST['quiz_id']);
                
                // Update quiz info
                $stmt = $pdo->prepare("UPDATE quizzes SET title = ?, category = ?, duration = ? WHERE id = ?");
                $stmt->execute([
                    trim($_POST['title']),
                    trim($_POST['category']),
                    intval($_POST['duration']),
                    $quizId
                ]);
                
                // Delete existing questions
                $stmt = $pdo->prepare("DELETE FROM questions WHERE quiz_id = ?");
                $stmt->execute([$quizId]);
                
                // Insert updated questions
                $stmt = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, options, correct_answer, explanation) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($_POST['questions'] as $question) {
                    // Validate question data
                    if (empty($question['text']) || !isset($question['options']) || !is_array($question['options']) || count($question['options']) < 2) {
                        $error = "Each question must have text and at least 2 options";
                        break 2; // Break out of both loops
                    }
                    
                    $stmt->execute([
                        $quizId,
                        trim($question['text']),
                        json_encode($question['options']),
                        intval($question['correct_answer']),
                        isset($question['explanation']) ? trim($question['explanation']) : null
                    ]);
                }
                $success = "Quiz updated successfully!";
                break;
                
            case 'toggle_quiz':
                if (empty($_POST['quiz_id'])) {
                    $error = "Quiz ID is required";
                    break;
                }
                
                $stmt = $pdo->prepare("UPDATE quizzes SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([intval($_POST['quiz_id'])]);
                $success = "Quiz status updated!";
                break;
                
            case 'delete_quiz':
                if (empty($_POST['quiz_id'])) {
                    $error = "Quiz ID is required";
                    break;
                }
                
                $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
                $stmt->execute([intval($_POST['quiz_id'])]);
                $success = "Quiz deleted!";
                break;
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get all quizzes
$quizzes = $pdo->query("SELECT * FROM quizzes ORDER BY created_at DESC")->fetchAll();

// Count statistics
$totalQuizzes = count($quizzes);
$activeQuizzes = $pdo->query("SELECT COUNT(*) FROM quizzes WHERE is_active = TRUE")->fetchColumn();
$totalQuestions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
        }
        .card-stat {
            transition: transform 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-5px);
        }
        .option-group .input-group-text input[type="radio"] {
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 bg-dark sidebar">
                <div class="position-sticky pt-3">
                    <h4 class="text-white px-3">Quiz Admin</h4>
                    <hr class="text-white">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="admin_dashboard.php">
                                📊 Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link text-white btn btn-link text-start p-0" 
                                    data-bs-toggle="modal" data-bs-target="#quizModal">
                                ➕ Create Quiz
                            </button>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="admin_news.php">
                                📊 News
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-white" href="logout.php">
                                👋 Logout (<?php echo htmlspecialchars($_SESSION['admin_username']); ?>)
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom">
                    <h2>Admin Dashboard</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quizModal" onclick="resetQuizForm()">
                        ➕ Create Quiz
                    </button>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Stats -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card card-stat bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Total Quizzes</h5>
                                        <h2 class="display-6"><?php echo $totalQuizzes; ?></h2>
                                    </div>
                                    <div class="display-4">📚</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card card-stat bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Active Quizzes</h5>
                                        <h2 class="display-6"><?php echo $activeQuizzes; ?></h2>
                                    </div>
                                    <div class="display-4">✅</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card card-stat bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Total Questions</h5>
                                        <h2 class="display-6"><?php echo $totalQuestions; ?></h2>
                                    </div>
                                    <div class="display-4">❓</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quiz List -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0">Manage Quizzes</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($quizzes)): ?>
                            <div class="alert alert-info">
                                No quizzes found. Create your first quiz!
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                            <th>Questions</th>
                                            <th>Duration</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($quizzes as $quiz): 
                                            $questionCount = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = ?");
                                            $questionCount->execute([$quiz['id']]);
                                            $count = $questionCount->fetchColumn();
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($quiz['title']); ?></strong>
                                                </td>
                                                <td><?php echo htmlspecialchars($quiz['category']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $quiz['is_active'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $quiz['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $count; ?> questions</span>
                                                </td>
                                                <td><?php echo $quiz['duration']; ?> min</td>
                                                <td>
                                                    <small><?php echo date('M d, Y', strtotime($quiz['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="toggle_quiz">
                                                            <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                                            <button type="submit" class="btn btn-warning">
                                                                <?php echo $quiz['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                            </button>
                                                        </form>
                                                        
                                                        <button type="button" class="btn btn-primary" 
                                                                onclick="loadQuizForEdit(<?php echo $quiz['id']; ?>)">
                                                            Edit
                                                        </button>
                                                        
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('Are you sure you want to delete this quiz? This action cannot be undone.')">
                                                            <input type="hidden" name="action" value="delete_quiz">
                                                            <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                                            <button type="submit" class="btn btn-danger">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Quiz Modal -->
    <div class="modal fade" id="quizModal" tabindex="-1" aria-labelledby="quizModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quizModalLabel">Create New Quiz</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="quizForm" onsubmit="return validateForm()">
                    <!-- Action type (create or edit) -->
                    <input type="hidden" name="action" value="create_quiz" id="formAction">
                    <!-- Quiz ID for editing -->
                    <input type="hidden" name="quiz_id" id="quizId">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Quiz Title *</label>
                            <input type="text" name="title" id="title" class="form-control" required 
                                   placeholder="Enter quiz title">
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Category *</label>
                                <input type="text" name="category" id="category" class="form-control" required 
                                       placeholder="e.g., Mathematics, Science, Geography">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Duration (minutes) *</label>
                                <input type="number" name="duration" id="duration" class="form-control" 
                                       value="15" min="1" max="180" required>
                                <small class="text-muted">Time limit for completing the quiz</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6>Questions</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addQuestion()">
                                    ➕ Add Question
                                </button>
                            </div>
                            
                            <div id="questionsContainer">
                                <!-- Questions will be added here by JavaScript -->
                            </div>
                            
                            <div class="text-center mt-3" id="noQuestionsMessage">
                                <p class="text-muted">No questions added yet. Click "Add Question" to start.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">Save Quiz</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let questionCount = 0;
        let isEditing = false;
        
        // Initialize with one question for new quiz
        document.addEventListener('DOMContentLoaded', function() {
            resetQuizForm();
        });
        
        // Reset form for new quiz
        function resetQuizForm() {
            document.getElementById('questionsContainer').innerHTML = '';
            document.getElementById('quizId').value = '';
            document.getElementById('formAction').value = 'create_quiz';
            document.getElementById('quizModalLabel').textContent = 'Create New Quiz';
            document.getElementById('submitBtn').textContent = 'Save Quiz';
            
            // Reset form fields
            document.getElementById('quizForm').reset();
            document.getElementById('duration').value = '15';
            
            questionCount = 0;
            isEditing = false;
            updateNoQuestionsMessage();
            
            // Add first question
            addQuestion();
        }
        
        // Update the "no questions" message visibility
        function updateNoQuestionsMessage() {
            const message = document.getElementById('noQuestionsMessage');
            const hasQuestions = document.querySelectorAll('#questionsContainer .card').length > 0;
            message.style.display = hasQuestions ? 'none' : 'block';
        }
        
        // Load quiz data for editing
        function loadQuizForEdit(quizId) {
            isEditing = true;
            
            // Show loading state
            document.getElementById('quizModalLabel').textContent = 'Loading...';
            document.getElementById('submitBtn').disabled = true;
            
            // Fetch quiz data
            fetch(`get_quiz.php?id=${quizId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Fill quiz info
                        document.getElementById('title').value = data.quiz.title || '';
                        document.getElementById('category').value = data.quiz.category || '';
                        document.getElementById('duration').value = data.quiz.duration || '15';
                        document.getElementById('quizId').value = data.quiz.id;
                        
                        // Change form action to edit
                        document.getElementById('formAction').value = 'edit_quiz';
                        document.getElementById('quizModalLabel').textContent = 'Edit Quiz: ' + data.quiz.title;
                        document.getElementById('submitBtn').textContent = 'Update Quiz';
                        
                        // Clear existing questions
                        document.getElementById('questionsContainer').innerHTML = '';
                        questionCount = 0;
                        
                        // Add questions from data
                        if (data.questions && data.questions.length > 0) {
                            data.questions.forEach((question, index) => {
                                addQuestionFromData(question, index);
                            });
                        } else {
                            // Add one empty question if no questions exist
                            addQuestion();
                        }
                        
                        // Show modal
                        const modal = new bootstrap.Modal(document.getElementById('quizModal'));
                        modal.show();
                        
                    } else {
                        alert('Error loading quiz: ' + (data.error || 'Unknown error'));
                        resetQuizForm();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading quiz data. Please check if the quiz exists and try again.');
                    resetQuizForm();
                })
                .finally(() => {
                    document.getElementById('submitBtn').disabled = false;
                });
        }
        
        // Add question from loaded data (for editing)
        function addQuestionFromData(question, index) {
            const container = document.getElementById('questionsContainer');
            const questionDiv = document.createElement('div');
            questionDiv.className = 'card mb-3 border-primary';
            questionDiv.innerHTML = `
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 text-primary">Question ${index + 1}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                onclick="this.closest('.card').remove(); updateQuestionNumbers()">
                            🗑️ Remove
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Question Text *</label>
                        <input type="text" class="form-control question-text" 
                               value="${escapeHtml(question.text || '')}" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Options (mark correct with radio button) *</label>
                        <div class="option-group">
                            <!-- Options will be added here -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" 
                                onclick="addOptionToQuestion(this)">
                            ➕ Add Option
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Explanation (optional)</label>
                        <textarea class="form-control question-explanation">${escapeHtml(question.explanation || '')}</textarea>
                    </div>
                </div>
            `;
            container.appendChild(questionDiv);
            
            // Add options from data
            const optionGroup = questionDiv.querySelector('.option-group');
            const options = question.options || [];
            
            options.forEach((option, optIndex) => {
                const optionDiv = document.createElement('div');
                optionDiv.className = 'input-group mb-2';
                optionDiv.innerHTML = `
                    <div class="input-group-text">
                        <input type="radio" name="correct_answer_${index}" 
                               value="${optIndex}" ${optIndex == question.correct_answer ? 'checked' : ''}>
                    </div>
                    <input type="text" class="form-control option-input" 
                           value="${escapeHtml(option)}" required>
                    <button type="button" class="btn btn-outline-danger" 
                            onclick="this.closest('.input-group').remove()">×</button>
                `;
                optionGroup.appendChild(optionDiv);
            });
            
            // If no options, add 2 default ones
            if (options.length === 0) {
                addOptionToQuestion(questionDiv.querySelector('.btn-outline-secondary'));
                addOptionToQuestion(questionDiv.querySelector('.btn-outline-secondary'));
            }
            
            questionCount++;
            updateNoQuestionsMessage();
        }
        
        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Add new question (for creating)
        function addQuestion() {
            const container = document.getElementById('questionsContainer');
            const questionDiv = document.createElement('div');
            questionDiv.className = 'card mb-3';
            questionDiv.innerHTML = `
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Question ${questionCount + 1}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                onclick="this.closest('.card').remove(); updateQuestionNumbers()">
                            🗑️ Remove
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Question Text *</label>
                        <input type="text" class="form-control question-text" 
                               placeholder="Enter your question here" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Options (mark correct with radio button) *</label>
                        <div class="option-group">
                            <!-- Options will be added here -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" 
                                onclick="addOptionToQuestion(this)">
                            ➕ Add Option
                        </button>
                        <small class="text-muted d-block mt-1">At least 2 options required</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Explanation (optional)</label>
                        <textarea class="form-control question-explanation" 
                                  placeholder="Explain why this answer is correct"></textarea>
                    </div>
                </div>
            `;
            container.appendChild(questionDiv);
            
            // Add 2 default options
            const addButton = questionDiv.querySelector('.btn-outline-secondary');
            addOptionToQuestion(addButton);
            addOptionToQuestion(addButton);
            
            questionCount++;
            updateNoQuestionsMessage();
        }
        
        // Add option to a question
        function addOptionToQuestion(button) {
            const questionDiv = button.closest('.card-body');
            const optionGroup = questionDiv.querySelector('.option-group');
            const optionCount = optionGroup.children.length;
            const questionIndex = Array.from(document.querySelectorAll('#questionsContainer .card')).indexOf(button.closest('.card'));
            
            const optionDiv = document.createElement('div');
            optionDiv.className = 'input-group mb-2';
            optionDiv.innerHTML = `
                <div class="input-group-text">
                    <input type="radio" name="correct_answer_${questionIndex}" 
                           value="${optionCount}" ${optionCount === 0 ? 'checked' : ''}>
                </div>
                <input type="text" class="form-control option-input" 
                       placeholder="Option ${optionCount + 1}" required>
                <button type="button" class="btn btn-outline-danger" 
                        onclick="this.closest('.input-group').remove()">×</button>
            `;
            optionGroup.appendChild(optionDiv);
        }
        
        // Update question numbers after removal
        function updateQuestionNumbers() {
            const questionCards = document.querySelectorAll('#questionsContainer .card');
            questionCards.forEach((card, index) => {
                card.querySelector('h6').textContent = `Question ${index + 1}`;
            });
            questionCount = questionCards.length;
            updateNoQuestionsMessage();
        }
        
        // Form validation before submit
        // Form validation and submission
function validateForm() {
    event.preventDefault();
    
    // Validate basic fields
    const title = document.getElementById('title').value.trim();
    const category = document.getElementById('category').value.trim();
    const duration = document.getElementById('duration').value;
    const quizId = document.getElementById('quizId').value;
    const formAction = document.getElementById('formAction').value;
    
    if (!title || !category || !duration) {
        alert('Please fill in all required quiz fields (title, category, duration)');
        return false;
    }
    
    // Get all questions
    const questionCards = document.querySelectorAll('#questionsContainer .card');
    
    if (questionCards.length === 0) {
        alert('Please add at least one question to the quiz.');
        return false;
    }
    
    // Prepare form data
    const form = document.getElementById('quizForm');
    const formData = new FormData(form);
    
    // Clear existing question data (if any from previous submission)
    for (let key of formData.keys()) {
        if (key.startsWith('questions[')) {
            formData.delete(key);
        }
    }
    
    // Add questions to form data
    let isValid = true;
    questionCards.forEach((card, index) => {
        const questionText = card.querySelector('.question-text').value.trim();
        const explanation = card.querySelector('.question-explanation').value.trim();
        const options = Array.from(card.querySelectorAll('.option-input')).map(input => input.value.trim());
        const correctAnswerInput = card.querySelector(`input[name="correct_answer_${index}"]:checked`);
        
        // Validate question
        if (!questionText) {
            alert(`Question ${index + 1} text is required`);
            isValid = false;
            return;
        }
        
        if (options.length < 2) {
            alert(`Question ${index + 1} needs at least 2 options`);
            isValid = false;
            return;
        }
        
        if (!correctAnswerInput) {
            alert(`Please select a correct answer for Question ${index + 1}`);
            isValid = false;
            return;
        }
        
        const correctAnswer = parseInt(correctAnswerInput.value);
        
        // Add to form data
        formData.append(`questions[${index}][text]`, questionText);
        formData.append(`questions[${index}][explanation]`, explanation);
        formData.append(`questions[${index}][correct_answer]`, correctAnswer);
        
        options.forEach((option, optIndex) => {
            formData.append(`questions[${index}][options][]`, option);
        });
    });
    
    if (!isValid) return false;
    
    // Show loading state
    const submitBtn = document.getElementById('submitBtn');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
    
    // Submit via AJAX
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(html => {
        // Parse the HTML response to check for success/error
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Check for success message
        const successAlert = doc.querySelector('.alert-success');
        const errorAlert = doc.querySelector('.alert-danger');
        
        if (successAlert) {
            // Success - close modal and reload page
            const modal = bootstrap.Modal.getInstance(document.getElementById('quizModal'));
            modal.hide();
            
            // Show success message
            alert(successAlert.textContent.trim());
            
            // Reload page after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 500);
            
        } else if (errorAlert) {
            // Error - show message
            alert('Error: ' + errorAlert.textContent.trim());
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        } else {
            // Unknown response - reload
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving quiz. Please try again.');
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
    
    return false;
}
        
        // Modal show event - reset for new quiz
        document.getElementById('quizModal').addEventListener('show.bs.modal', function(event) {
            // If clicking the "Create Quiz" button, reset form
            if (event.relatedTarget && event.relatedTarget.textContent.includes('Create')) {
                resetQuizForm();
            }
        });
    </script>
</body>
</html>
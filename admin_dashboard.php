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
                
            case 'bulk_upload':
                // Validate required fields
                if (empty($_POST['quiz_id']) || empty($_FILES['csv_file'])) {
                    $error = "Quiz selection and CSV file are required";
                    break;
                }
                
                $quizId = intval($_POST['quiz_id']);
                
                // Validate quiz exists
                $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
                $stmt->execute([$quizId]);
                $quiz = $stmt->fetch();
                
                if (!$quiz) {
                    $error = "Selected quiz does not exist";
                    break;
                }
                
                // Process CSV file
                $csvFile = $_FILES['csv_file']['tmp_name'];
                $delimiter = $_POST['delimiter'] ?? ',';
                $hasHeaders = isset($_POST['has_headers']) && $_POST['has_headers'] === 'on';
                
                // Validate file
                if (!file_exists($csvFile)) {
                    $error = "CSV file upload failed";
                    break;
                }
                
                // Check file size (max 2MB)
                if ($_FILES['csv_file']['size'] > 2097152) {
                    $error = "File size too large. Maximum 2MB allowed.";
                    break;
                }
                
                // Process CSV
                $questionsImported = 0;
                $questionsSkipped = 0;
                $errors = [];
                
                $handle = fopen($csvFile, 'r');
                if ($handle === false) {
                    $error = "Unable to open CSV file";
                    break;
                }
                
                // Skip headers if needed
                if ($hasHeaders) {
                    fgetcsv($handle, 1000, $delimiter);
                }
                
                // Prepare insert statement
                $insertStmt = $pdo->prepare("INSERT INTO questions (quiz_id, question_text, options, correct_answer, explanation) VALUES (?, ?, ?, ?, ?)");
                
                // Process each row
                $rowNumber = $hasHeaders ? 2 : 1;
                while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                    // Skip empty rows
                    if (empty($row) || empty(array_filter($row, function($value) { 
                        return $value !== null && $value !== ''; 
                    }))) {
                        $rowNumber++;
                        continue;
                    }
                    
                    // Clean row data
                    $row = array_map(function($value) {
                        $value = trim($value ?? '');
                        // Remove quotes if present
                        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                            $value = substr($value, 1, -1);
                        }
                        return $value;
                    }, $row);
                    
                    // FIXED: Map columns correctly - 4 options only
                    // Format: question, option1, option2, option3, option4, correct_answer, explanation
                    $questionData = [
                        'text' => $row[0] ?? '', // Column 1: Question
                        'options' => [],
                        'correct_answer' => 0,
                        'explanation' => ''
                    ];
                    
                    // Extract 4 options only (columns 2-5)
                    for ($i = 1; $i <= 4; $i++) {
                        if (isset($row[$i]) && trim($row[$i]) !== '') {
                            $questionData['options'][] = $row[$i];
                        }
                    }
                    
                    // Extract correct answer (column 6)
                    if (isset($row[5]) && $row[5] !== '') {
                        $correctAnswer = intval($row[5]) - 1; // Convert to 0-based index
                        if ($correctAnswer >= 0 && $correctAnswer < count($questionData['options'])) {
                            $questionData['correct_answer'] = $correctAnswer;
                        }
                    }
                    
                    // Extract explanation (column 7)
                    if (isset($row[6])) {
                        $questionData['explanation'] = $row[6];
                    }
                    
                    // Validate question data
                    if (empty($questionData['text'])) {
                        $errors[] = "Row $rowNumber: Missing question text";
                        $questionsSkipped++;
                        $rowNumber++;
                        continue;
                    }
                    
                    if (count($questionData['options']) < 2) {
                        $errors[] = "Row $rowNumber: Need at least 2 options (found " . count($questionData['options']) . ")";
                        $questionsSkipped++;
                        $rowNumber++;
                        continue;
                    }
                    
                    // If correct answer not specified or invalid, use first option
                    if ($questionData['correct_answer'] < 0 || $questionData['correct_answer'] >= count($questionData['options'])) {
                        $questionData['correct_answer'] = 0;
                    }
                    
                    try {
                        // Insert question
                        $insertStmt->execute([
                            $quizId,
                            $questionData['text'],
                            json_encode($questionData['options'], JSON_UNESCAPED_UNICODE),
                            $questionData['correct_answer'],
                            $questionData['explanation']
                        ]);
                        $questionsImported++;
                    } catch (PDOException $e) {
                        $errors[] = "Row $rowNumber: Database error - " . $e->getMessage();
                        $questionsSkipped++;
                    }
                    
                    $rowNumber++;
                }
                
                fclose($handle);
                
                // Prepare success message
                $success = "✅ Bulk import completed!<br>";
                $success .= "✅ Successfully imported: <strong>$questionsImported</strong> questions<br>";
                
                if ($questionsSkipped > 0) {
                    $success .= "⚠️ Skipped: <strong>$questionsSkipped</strong> questions";
                }
                
                if (!empty($errors)) {
                    $errorDetails = "<br><br><strong>Errors:</strong><br>" . implode("<br>", array_slice($errors, 0, 5));
                    if (count($errors) > 5) {
                        $errorDetails .= "<br>... and " . (count($errors) - 5) . " more errors";
                    }
                    $success .= $errorDetails;
                }
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
        /* Bulk Upload Styles */
        #csvPreviewTable th, #csvPreviewTable td {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #reviewTable tr.table-warning td {
            background-color: #fff3cd;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .step {
            display: flex;
            align-items: center;
            margin: 0 10px;
            color: #6c757d;
        }
        .step.active {
            color: #0d6efd;
            font-weight: bold;
        }
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #6c757d;
            color: white;
            margin-right: 8px;
        }
        .step.active .step-number {
            background-color: #0d6efd;
        }
        .file-upload-area {
            border: 2px dashed #0d6efd;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .file-upload-area:hover {
            background-color: #e9ecef;
            border-color: #0a58ca;
        }
        .file-upload-area.dragover {
            background-color: #cfe2ff;
            border-color: #0a58ca;
        }
        .question-card {
            border-left: 4px solid #0d6efd;
        }
        .question-card .remove-question {
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        .question-card .remove-question:hover {
            opacity: 1;
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
                                <i class="bi bi-bar-chart"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link text-white btn btn-link text-start p-0" 
                                    data-bs-toggle="modal" data-bs-target="#quizModal">
                                <i class="bi bi-plus-circle"></i> Create Quiz
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link text-white btn btn-link text-start p-0" 
                                    data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                                <i class="bi bi-upload"></i> Bulk Upload Questions
                            </button>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="admin_news.php">
                                <i class="bi bi-newspaper"></i> News
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-white" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout (<?php echo htmlspecialchars($_SESSION['admin_username']); ?>)
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom">
                    <h2>Admin Dashboard</h2>
                    <div>
                        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#quizModal" onclick="resetQuizForm()">
                            <i class="bi bi-plus-circle"></i> Create Quiz
                        </button>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                            <i class="bi bi-upload"></i> Bulk Upload
                        </button>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
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
                                    <div class="display-4"><i class="bi bi-journal-bookmark"></i></div>
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
                                    <div class="display-4"><i class="bi bi-check-circle"></i></div>
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
                                    <div class="display-4"><i class="bi bi-question-circle"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card card-stat bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Bulk Upload</h5>
                                        <h2 class="display-6">Ready</h2>
                                    </div>
                                    <div class="display-4"><i class="bi bi-cloud-arrow-up"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quiz List -->
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h4 class="mb-0"><i class="bi bi-list-task"></i> Manage Quizzes</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($quizzes)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No quizzes found. Create your first quiz!
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
                                                                <i class="bi bi-power"></i> <?php echo $quiz['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                            </button>
                                                        </form>
                                                        
                                                        <button type="button" class="btn btn-primary" 
                                                                onclick="loadQuizForEdit(<?php echo $quiz['id']; ?>)">
                                                            <i class="bi bi-pencil"></i> Edit
                                                        </button>
                                                        
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('Are you sure you want to delete this quiz? This action cannot be undone.')">
                                                            <input type="hidden" name="action" value="delete_quiz">
                                                            <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="bi bi-trash"></i> Delete
                                                            </button>
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
        <div class="modal-dialog modal-xl">
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
                    
                    <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
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
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5>Questions</h5>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addQuestion()">
                                    <i class="bi bi-plus-circle"></i> Add Question
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
                        <div class="d-flex justify-content-between w-100">
                            <button type="button" class="btn btn-outline-primary" onclick="addQuestion()">
                                <i class="bi bi-plus-circle"></i> Add Another Question
                            </button>
                            <div>
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" id="submitBtn">Save Quiz</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Upload Modal -->
    <div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-labelledby="bulkUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkUploadModalLabel"><i class="bi bi-upload"></i> Bulk Upload Questions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="bulkUploadForm" enctype="multipart/form-data" onsubmit="return validateBulkUpload()">
                    <input type="hidden" name="action" value="bulk_upload">
                    
                    <div class="modal-body">
                        <!-- Step 1: Select Quiz -->
                        <div class="mb-4" id="step1">
                            <h6>Step 1: Select Target Quiz</h6>
                            <div class="mb-3">
                                <label class="form-label">Choose Quiz *</label>
                                <select class="form-select" id="targetQuiz" name="quiz_id" required onchange="updateQuizInfo()">
                                    <option value="">-- Select a Quiz --</option>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <?php if ($quiz['is_active']): ?>
                                            <option value="<?php echo $quiz['id']; ?>" 
                                                    data-title="<?php echo htmlspecialchars($quiz['title']); ?>"
                                                    data-category="<?php echo htmlspecialchars($quiz['category']); ?>">
                                                <?php echo htmlspecialchars($quiz['title']); ?> (<?php echo htmlspecialchars($quiz['category']); ?>)
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="card mb-3" id="quizInfoCard" style="display: none;">
                                <div class="card-body">
                                    <h6><i class="bi bi-info-circle"></i> Quiz Information</h6>
                                    <p><strong>Title:</strong> <span id="selectedQuizTitle"></span></p>
                                    <p><strong>Category:</strong> <span id="selectedQuizCategory"></span></p>
                                    <div id="existingQuestionsInfo"></div>
                                </div>
                            </div>
                            
                            <button type="button" class="btn btn-primary" onclick="showStep(2)">
                                Next <i class="bi bi-arrow-right"></i> Upload CSV
                            </button>
                        </div>
                        
                        <!-- Step 2: Upload CSV -->
                        <div class="mb-4" id="step2" style="display: none;">
                            <h6>Step 2: Upload CSV File</h6>
                            
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle"></i> CSV Format Requirements:</h6>
                                <ul class="mb-0">
                                    <li><strong>Required Columns:</strong> question, option1, option2, option3, option4, correct_answer, explanation</li>
                                    <li><strong>correct_answer:</strong> Number (1-4) indicating which option is correct</li>
                                    <li><strong>explanation:</strong> Optional explanation for the correct answer</li>
                                    <li>Minimum 2 options required per question</li>
                                    <li>Download <a href="sample_questions.csv" download>sample CSV file</a></li>
                                </ul>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Upload CSV File *</label>
                                <input type="file" class="form-control" id="csvFile" name="csv_file" 
                                       accept=".csv,.txt" required onchange="previewCSV(this)">
                                <small class="text-muted">Maximum file size: 2MB</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">CSV Delimiter</label>
                                <select class="form-select" id="delimiter" name="delimiter">
                                    <option value="," selected>Comma ( , )</option>
                                    <option value=";">Semicolon ( ; )</option>
                                    <option value="\t">Tab</option>
                                    <option value="|">Pipe ( | )</option>
                                </select>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="hasHeaders" name="has_headers" checked>
                                <label class="form-check-label" for="hasHeaders">
                                    First row contains column headers
                                </label>
                            </div>
                            
                            <!-- CSV Preview -->
                            <div class="card mb-3" id="csvPreviewCard" style="display: none;">
                                <div class="card-header">
                                    <h6 class="mb-0">CSV Preview (First 5 rows)</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-bordered" id="csvPreviewTable">
                                            <thead class="table-light">
                                                <tr id="previewHeaders"></tr>
                                            </thead>
                                            <tbody id="previewBody"></tbody>
                                        </table>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <span id="previewRowCount">0</span> rows detected
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" onclick="showStep(1)">
                                    <i class="bi bi-arrow-left"></i> Back
                                </button>
                                <button type="button" class="btn btn-primary" onclick="processCSV()">
                                    Next <i class="bi bi-arrow-right"></i> Review Questions
                                </button>
                            </div>
                        </div>
                        
                        <!-- Step 3: Review & Confirm -->
                        <div id="step3" style="display: none;">
                            <h6>Step 3: Review Questions</h6>
                            
                            <div class="alert alert-warning">
                                <h6><i class="bi bi-exclamation-triangle"></i> Review Before Importing</h6>
                                <p class="mb-0" id="importSummary"></p>
                            </div>
                            
                            <div class="table-responsive mb-3" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th>#</th>
                                            <th>Question</th>
                                            <th>Options</th>
                                            <th>Correct</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="reviewTable">
                                        <!-- Filled by JavaScript -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" onclick="showStep(2)">
                                    <i class="bi bi-arrow-left"></i> Back
                                </button>
                                <button type="submit" class="btn btn-success" id="importBtn">
                                    <i class="bi bi-check-circle"></i> Import Questions
                                </button>
                            </div>
                        </div>
                        
                        <!-- Loading Indicator -->
                        <div id="loadingIndicator" class="text-center" style="display: none;">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Processing CSV file...</p>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Quiz Functions
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
        
        // Load quiz data for editing - FIXED: No duplication
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
            questionDiv.className = 'card mb-3 question-card';
            questionDiv.innerHTML = `
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 text-primary">Question ${index + 1}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-question" 
                                onclick="removeQuestion(this)">
                            <i class="bi bi-trash"></i> Remove
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Question Text *</label>
                        <input type="text" class="form-control question-text" 
                               value="${escapeHtml(question.text || '')}" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Options (mark correct with radio button) *</label>
                        <div class="option-group" data-question-index="${index}">
                            <!-- Options will be added here -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" 
                                onclick="addOptionToQuestion(this, true)">
                            <i class="bi bi-plus-circle"></i> Add Option
                        </button>
                        <small class="text-muted d-block mt-1">Maximum 4 options allowed</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Explanation (optional)</label>
                        <textarea class="form-control question-explanation" rows="2">${escapeHtml(question.explanation || '')}</textarea>
                    </div>
                </div>
            `;
            container.appendChild(questionDiv);
            
            // Add options from data
            const optionGroup = questionDiv.querySelector('.option-group');
            const options = question.options || [];
            
            options.forEach((option, optIndex) => {
                if (optIndex < 4) { // Only show 4 options
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
                                onclick="removeOption(this)"><i class="bi bi-x"></i></button>
                    `;
                    optionGroup.appendChild(optionDiv);
                }
            });
            
            // If less than 2 options, add default ones
            while (optionGroup.children.length < 2) {
                addOptionToQuestion(questionDiv.querySelector('.btn-outline-secondary'), false);
            }
            
            questionCount++;
            updateNoQuestionsMessage();
        }
        
        // Remove a question
        function removeQuestion(button) {
            if (confirm('Are you sure you want to remove this question?')) {
                button.closest('.card').remove();
                updateQuestionNumbers();
            }
        }
        
        // Remove an option
        function removeOption(button) {
            const optionGroup = button.closest('.option-group');
            if (optionGroup.children.length > 2) {
                button.closest('.input-group').remove();
                updateOptionRadios(optionGroup);
            } else {
                alert('Each question must have at least 2 options');
            }
        }
        
        // Update radio button values after option removal
        function updateOptionRadios(optionGroup) {
            const radioButtons = optionGroup.querySelectorAll('input[type="radio"]');
            const inputs = optionGroup.querySelectorAll('.option-input');
            
            radioButtons.forEach((radio, index) => {
                radio.value = index;
                radio.name = radio.name.split('_').slice(0, -1).join('_') + '_' + index;
            });
            
            inputs.forEach((input, index) => {
                input.placeholder = `Option ${index + 1}`;
            });
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
            questionDiv.className = 'card mb-3 question-card';
            questionDiv.innerHTML = `
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Question ${questionCount + 1}</h6>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-question" 
                                onclick="removeQuestion(this)">
                            <i class="bi bi-trash"></i> Remove
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Question Text *</label>
                        <input type="text" class="form-control question-text" 
                               placeholder="Enter your question here" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Options (mark correct with radio button) *</label>
                        <div class="option-group" data-question-index="${questionCount}">
                            <!-- Options will be added here -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" 
                                onclick="addOptionToQuestion(this, false)">
                            <i class="bi bi-plus-circle"></i> Add Option
                        </button>
                        <small class="text-muted d-block mt-1">Maximum 4 options allowed</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Explanation (optional)</label>
                        <textarea class="form-control question-explanation" rows="2"
                                  placeholder="Explain why this answer is correct"></textarea>
                    </div>
                </div>
            `;
            container.appendChild(questionDiv);
            
            // Add 2 default options
            const addButton = questionDiv.querySelector('.btn-outline-secondary');
            addOptionToQuestion(addButton, false);
            addOptionToQuestion(addButton, false);
            
            questionCount++;
            updateNoQuestionsMessage();
        }
        
        // Add option to a question
        function addOptionToQuestion(button, isEditing) {
            const questionDiv = button.closest('.card-body');
            const optionGroup = questionDiv.querySelector('.option-group');
            const optionCount = optionGroup.children.length;
            
            // Limit to 4 options
            if (optionCount >= 4) {
                alert('Maximum 4 options allowed per question');
                return;
            }
            
            // Get question index from data attribute or calculate
            let questionIndex;
            if (isEditing) {
                questionIndex = parseInt(optionGroup.getAttribute('data-question-index'));
            } else {
                questionIndex = Array.from(document.querySelectorAll('#questionsContainer .card')).indexOf(button.closest('.card'));
            }
            
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
                        onclick="removeOption(this)"><i class="bi bi-x"></i></button>
            `;
            optionGroup.appendChild(optionDiv);
        }
        
        // Update question numbers after removal
        function updateQuestionNumbers() {
            const questionCards = document.querySelectorAll('#questionsContainer .card');
            questionCards.forEach((card, index) => {
                card.querySelector('h6').textContent = `Question ${index + 1}`;
                
                // Update option group data attribute
                const optionGroup = card.querySelector('.option-group');
                optionGroup.setAttribute('data-question-index', index);
                
                // Update radio button names
                const radioButtons = optionGroup.querySelectorAll('input[type="radio"]');
                radioButtons.forEach(radio => {
                    radio.name = `correct_answer_${index}`;
                });
            });
            questionCount = questionCards.length;
            updateNoQuestionsMessage();
        }
        
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
            let errorMessage = '';
            
            questionCards.forEach((card, index) => {
                const questionText = card.querySelector('.question-text').value.trim();
                const explanation = card.querySelector('.question-explanation').value.trim();
                const options = Array.from(card.querySelectorAll('.option-input')).map(input => input.value.trim());
                const correctAnswerInput = card.querySelector(`input[name="correct_answer_${index}"]:checked`);
                
                // Validate question
                if (!questionText) {
                    errorMessage = `Question ${index + 1} text is required`;
                    isValid = false;
                    return;
                }
                
                if (options.length < 2) {
                    errorMessage = `Question ${index + 1} needs at least 2 options`;
                    isValid = false;
                    return;
                }
                
                // Check for empty options
                const emptyOptions = options.filter(opt => !opt);
                if (emptyOptions.length > 0) {
                    errorMessage = `Question ${index + 1} has empty options`;
                    isValid = false;
                    return;
                }
                
                if (!correctAnswerInput) {
                    errorMessage = `Please select a correct answer for Question ${index + 1}`;
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
            
            if (!isValid) {
                alert(errorMessage);
                return false;
            }
            
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
        
        // Bulk Upload Functions
        let currentStep = 1;
        let csvData = [];
        let importData = [];
        
        // Show specific step
        function showStep(step) {
            document.getElementById('step1').style.display = step === 1 ? 'block' : 'none';
            document.getElementById('step2').style.display = step === 2 ? 'block' : 'none';
            document.getElementById('step3').style.display = step === 3 ? 'block' : 'none';
            currentStep = step;
        }
        
        // Update quiz info when selected
        function updateQuizInfo() {
            const select = document.getElementById('targetQuiz');
            const selectedOption = select.options[select.selectedIndex];
            const quizInfoCard = document.getElementById('quizInfoCard');
            
            if (selectedOption.value) {
                document.getElementById('selectedQuizTitle').textContent = selectedOption.getAttribute('data-title');
                document.getElementById('selectedQuizCategory').textContent = selectedOption.getAttribute('data-category');
                quizInfoCard.style.display = 'block';
                
                // Fetch existing question count
                fetch(`get_quiz_stats.php?quiz_id=${selectedOption.value}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('existingQuestionsInfo').innerHTML = 
                                `<strong>Existing Questions:</strong> ${data.question_count}`;
                        }
                    });
            } else {
                quizInfoCard.style.display = 'none';
            }
        }
        
        // Preview CSV file
        function previewCSV(input) {
            const file = input.files[0];
            const previewCard = document.getElementById('csvPreviewCard');
            
            if (!file) {
                previewCard.style.display = 'none';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const text = e.target.result;
                const delimiter = document.getElementById('delimiter').value;
                const rows = text.split('\n');
                
                // Clear previous preview
                document.getElementById('previewHeaders').innerHTML = '';
                document.getElementById('previewBody').innerHTML = '';
                
                // Parse first 6 rows for preview
                const previewRows = Math.min(6, rows.length);
                csvData = [];
                
                for (let i = 0; i < previewRows; i++) {
                    if (rows[i].trim() === '') continue;
                    
                    // Handle quoted values properly
                    const row = [];
                    let currentCell = '';
                    let inQuotes = false;
                    
                    for (let char of rows[i]) {
                        if (char === '"') {
                            inQuotes = !inQuotes;
                        } else if (char === delimiter && !inQuotes) {
                            row.push(currentCell.trim());
                            currentCell = '';
                        } else {
                            currentCell += char;
                        }
                    }
                    row.push(currentCell.trim());
                    
                    csvData.push(row);
                    
                    if (i === 0) {
                        // Add headers
                        const headersRow = document.getElementById('previewHeaders');
                        const headerNames = ['Question', 'Option 1', 'Option 2', 'Option 3', 'Option 4', 'Correct Answer', 'Explanation'];
                        
                        row.forEach((cell, index) => {
                            const th = document.createElement('th');
                            th.textContent = headerNames[index] || `Col ${index + 1}`;
                            th.title = cell;
                            headersRow.appendChild(th);
                        });
                    } else {
                        // Add data row
                        const tr = document.createElement('tr');
                        row.forEach((cell, index) => {
                            const td = document.createElement('td');
                            const displayText = cell.length > 30 ? cell.substring(0, 30) + '...' : cell;
                            td.textContent = displayText;
                            td.title = cell;
                            tr.appendChild(td);
                        });
                        document.getElementById('previewBody').appendChild(tr);
                    }
                }
                
                // Update row count
                const totalRows = rows.filter(row => row.trim() !== '').length;
                const hasHeaders = document.getElementById('hasHeaders').checked;
                const dataRows = hasHeaders ? totalRows - 1 : totalRows;
                
                document.getElementById('previewRowCount').textContent = dataRows;
                previewCard.style.display = 'block';
            };
            
            reader.readAsText(file);
        }
        
        // Process CSV for import
        function processCSV() {
            const fileInput = document.getElementById('csvFile');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select a CSV file first');
                return;
            }
            
            const reader = new FileReader();
            const delimiter = document.getElementById('delimiter').value;
            const hasHeaders = document.getElementById('hasHeaders').checked;
            
            // Show loading
            document.getElementById('step2').style.display = 'none';
            document.getElementById('loadingIndicator').style.display = 'block';
            
            reader.onload = function(e) {
                const text = e.target.result;
                const rows = text.split('\n');
                importData = [];
                let validQuestions = 0;
                let invalidQuestions = 0;
                
                // Process rows
                let startRow = hasHeaders ? 1 : 0;
                for (let i = startRow; i < rows.length; i++) {
                    if (rows[i].trim() === '') continue;
                    
                    // Parse CSV row with quoted values
                    const row = [];
                    let currentCell = '';
                    let inQuotes = false;
                    
                    for (let char of rows[i]) {
                        if (char === '"') {
                            inQuotes = !inQuotes;
                        } else if (char === delimiter && !inQuotes) {
                            row.push(currentCell.trim());
                            currentCell = '';
                        } else {
                            currentCell += char;
                        }
                    }
                    row.push(currentCell.trim());
                    
                    // Remove quotes from cells
                    const cleanRow = row.map(cell => {
                        if (cell.startsWith('"') && cell.endsWith('"')) {
                            return cell.substring(1, cell.length - 1);
                        }
                        return cell;
                    });
                    
                    // Extract question data - FIXED: Only 4 options
                    const question = {
                        text: cleanRow[0] || '',
                        options: [],
                        correctAnswer: 0,
                        explanation: cleanRow[6] || '',
                        isValid: true,
                        errors: []
                    };
                    
                    // Extract 4 options only (columns 1-4)
                    for (let j = 1; j <= 4; j++) {
                        if (cleanRow[j] && cleanRow[j].trim() !== '') {
                            question.options.push(cleanRow[j].trim());
                        }
                    }
                    
                    // Extract correct answer (column 5)
                    if (cleanRow[5] && cleanRow[5].trim() !== '') {
                        const correct = parseInt(cleanRow[5]) - 1;
                        if (correct >= 0 && correct < question.options.length) {
                            question.correctAnswer = correct;
                        }
                    }
                    
                    // Validate
                    if (!question.text) {
                        question.isValid = false;
                        question.errors.push('Missing question text');
                    }
                    
                    if (question.options.length < 2) {
                        question.isValid = false;
                        question.errors.push('Need at least 2 options');
                    }
                    
                    // Check for empty options
                    const emptyOptions = question.options.filter(opt => !opt);
                    if (emptyOptions.length > 0) {
                        question.isValid = false;
                        question.errors.push('Some options are empty');
                    }
                    
                    if (question.correctAnswer < 0 || question.correctAnswer >= question.options.length) {
                        question.correctAnswer = 0; // Default to first option
                    }
                    
                    importData.push(question);
                    
                    if (question.isValid) {
                        validQuestions++;
                    } else {
                        invalidQuestions++;
                    }
                }
                
                // Hide loading, show review
                document.getElementById('loadingIndicator').style.display = 'none';
                showStep(3);
                
                // Update summary
                document.getElementById('importSummary').innerHTML = `
                    <strong>${validQuestions}</strong> valid questions ready to import<br>
                    ${invalidQuestions > 0 ? `<strong class="text-danger">${invalidQuestions}</strong> questions will be skipped due to errors` : ''}
                `;
                
                // Populate review table (first 10 rows)
                const reviewTable = document.getElementById('reviewTable');
                reviewTable.innerHTML = '';
                
                const displayCount = Math.min(10, importData.length);
                for (let i = 0; i < displayCount; i++) {
                    const q = importData[i];
                    const tr = document.createElement('tr');
                    tr.className = q.isValid ? '' : 'table-warning';
                    
                    tr.innerHTML = `
                        <td>${i + 1}</td>
                        <td>
                            <div>${escapeHtml(q.text.substring(0, 50))}${q.text.length > 50 ? '...' : ''}</div>
                            ${!q.isValid ? `<small class="text-danger">${q.errors.join(', ')}</small>` : ''}
                        </td>
                        <td>
                            <small>
                                ${q.options.map((opt, idx) => 
                                    `${idx + 1}. ${escapeHtml(opt.substring(0, 30))}${opt.length > 30 ? '...' : ''} ${idx === q.correctAnswer ? '<span class="badge bg-success ms-1">✓</span>' : ''}`
                                ).join('<br>')}
                            </small>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-success">${q.correctAnswer + 1}</span>
                        </td>
                        <td>
                            ${q.isValid ? 
                                '<span class="badge bg-success">Ready</span>' : 
                                '<span class="badge bg-danger">Error</span>'
                            }
                        </td>
                    `;
                    reviewTable.appendChild(tr);
                }
                
                if (importData.length > 10) {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `<td colspan="5" class="text-center text-muted">... and ${importData.length - 10} more questions</td>`;
                    reviewTable.appendChild(tr);
                }
            };
            
            reader.readAsText(file);
        }
        
        // Validate bulk upload form
        function validateBulkUpload() {
            const quizId = document.getElementById('targetQuiz').value;
            const fileInput = document.getElementById('csvFile');
            
            if (!quizId) {
                alert('Please select a quiz');
                showStep(1);
                return false;
            }
            
            if (!fileInput.files[0]) {
                alert('Please select a CSV file');
                showStep(2);
                return false;
            }
            
            // Count valid questions
            const validQuestions = importData.filter(q => q.isValid).length;
            if (validQuestions === 0) {
                alert('No valid questions found to import. Please check your CSV format.');
                return false;
            }
            
            // Show confirmation
            if (!confirm(`Are you sure you want to import ${validQuestions} questions?`)) {
                return false;
            }
            
            // Show loading on button
            const importBtn = document.getElementById('importBtn');
            importBtn.disabled = true;
            importBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importing...';
            
            return true;
        }
        
        // Reset bulk upload form when modal closes
        document.getElementById('bulkUploadModal').addEventListener('hidden.bs.modal', function() {
            // Reset form
            document.getElementById('bulkUploadForm').reset();
            document.getElementById('csvPreviewCard').style.display = 'none';
            document.getElementById('quizInfoCard').style.display = 'none';
            
            // Reset steps
            showStep(1);
            
            // Clear data
            csvData = [];
            importData = [];
            
            // Reset import button
            const importBtn = document.getElementById('importBtn');
            importBtn.disabled = false;
            importBtn.innerHTML = '<i class="bi bi-check-circle"></i> Import Questions';
        });
    </script>
</body>
</html>
<?php
// debug_form.php
session_start();
if (!isset($_SESSION['admin_id'])) {
    die('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<pre>';
    echo "POST Data:\n";
    print_r($_POST);
    echo "\n\nFiles Data:\n";
    print_r($_FILES);
    echo '</pre>';
    exit;
}
?>
<form method="POST">
    <input type="text" name="title" value="Test Quiz">
    <input type="text" name="category" value="Test">
    <input type="number" name="duration" value="10">
    <button type="submit">Test Submit</button>
</form>
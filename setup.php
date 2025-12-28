<?php
// Run this once to set up the database
require_once 'config/database.php';

try {
    // Create tables
    $sql = file_get_contents('schema_simple.sql');
    $pdo->exec($sql);
    
    // Create default admin (password: admin123)
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO admins (username, password) VALUES ('admin', '$hashedPassword')");
    
    echo "Database setup complete!<br>";
    echo "Admin username: admin<br>";
    echo "Admin password: admin123<br>";
    echo "<a href='index.php'>Go to Quiz</a>";
    
} catch (PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
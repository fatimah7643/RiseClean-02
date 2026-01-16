<?php
require_once __DIR__ . '/includes/db_connect.php';

// Add module_id column to quiz_questions table to connect quizzes to education modules
try {
    // Check if module_id column already exists
    $checkColumn = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = 'quiz_questions' 
        AND COLUMN_NAME = 'module_id'
    ");
    $checkColumn->execute([DB_NAME]);
    $columnExists = $checkColumn->fetch();

    if (!$columnExists) {
        // Add module_id column to quiz_questions
        $alterTable = $pdo->prepare("ALTER TABLE quiz_questions ADD COLUMN module_id INT NULL AFTER question_text");
        $alterTable->execute();
        
        // Add foreign key constraint
        $addForeignKey = $pdo->prepare("
            ALTER TABLE quiz_questions 
            ADD CONSTRAINT fk_quiz_questions_module_id 
            FOREIGN KEY (module_id) REFERENCES education_modules(module_id) 
            ON DELETE CASCADE
        ");
        $addForeignKey->execute();
        
        echo "Added module_id column to quiz_questions table successfully!\n";
    } else {
        echo "module_id column already exists in quiz_questions table.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
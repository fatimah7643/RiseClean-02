<?php
require_once 'includes/db_connect.php';

try {
    $stmt = $pdo->prepare('SHOW COLUMNS FROM quiz_choices');
    $stmt->execute();
    $columns = $stmt->fetchAll();

    echo "Columns in quiz_choices table:\n";
    foreach($columns as $col) {
        echo $col['Field'] . ': ' . $col['Type'] . ' ' . $col['Null'] . ' ' . $col['Key'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
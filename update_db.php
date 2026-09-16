<?php
require_once __DIR__ . '/includes/db.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN nickname VARCHAR(100) AFTER last_name");
    echo "Column added successfully.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

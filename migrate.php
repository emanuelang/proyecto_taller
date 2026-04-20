<?php
require 'config/database.php';
try {
    $pdo->exec("ALTER TABLE Usuarios ADD COLUMN TokenRecuperacion VARCHAR(255) NULL, ADD COLUMN ExpiracionToken DATETIME NULL;");
    echo 'MIGRATION OK';
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') { // Warning: Column already exists
        echo 'ALREADY EXISTS';
    } else {
        echo 'ERROR: ' . $e->getMessage();
    }
}

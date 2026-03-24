<?php
require 'config/database.php';
$tables = ['usuarios', 'conductores', 'vehiculos', 'viajes', 'reservas', 'reportes'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE $table");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo $row['Create Table'] . ";\n\n";
        }
    } catch (Exception $e) {
        echo "-- Table $table error: " . $e->getMessage() . "\n\n";
    }
}

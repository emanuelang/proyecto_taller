<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';

// Exportación mediante PHP nativo (sin dependencias a mysqldump de servidor)
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="backup_carpooling_' . date('Y-m-d_H-i-s') . '.sql"');

echo "-- Backup Generado Automáticamente por la Plataforma Carpooling\n";
echo "-- Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$tables = [];
$result = $pdo->query("SHOW TABLES");
while ($row = $result->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

echo "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    echo "-- Estructura de la tabla `{$table}`\n";
    $result = $pdo->query("SHOW CREATE TABLE {$table}");
    $row = $result->fetch(PDO::FETCH_NUM);
    echo "DROP TABLE IF EXISTS `{$table}`;\n";
    echo $row[1] . ";\n\n";
    
    echo "-- Volcado de datos de la tabla `{$table}`\n";
    $result = $pdo->query("SELECT * FROM {$table}");
    $rows = $result->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($rows) > 0) {
        // Optimización con INSERT multiple
        $insert_start = "INSERT INTO `{$table}` VALUES ";
        echo $insert_start;
        
        $insert_values = [];
        foreach ($rows as $row) {
            $vals = [];
            foreach ($row as $val) {
                if ($val === null) {
                    $vals[] = "NULL";
                } else {
                    // Escapar strings (usamos PDO quote que añade comillas o manual)
                    $vals[] = $pdo->quote($val);
                }
            }
            $insert_values[] = "(" . implode(", ", $vals) . ")";
        }
        echo implode(",\n", $insert_values) . ";\n\n";
    }
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
exit;

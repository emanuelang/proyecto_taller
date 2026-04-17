<?php
require_once dirname(__DIR__) . '/config/database.php';
$stmt = $pdo->query('SELECT id, nombre FROM ciudades');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>

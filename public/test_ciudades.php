<?php
require 'c:/Users/Facun/Desktop/xamp/htdocs/Proyecto_taller/config/database.php';
$stmt = $pdo->query('SELECT id, nombre FROM ciudades');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>

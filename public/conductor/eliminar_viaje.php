<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor'])) {
    die('Acceso denegado');
}

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    DELETE p 
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    WHERE p.ID_publicacion = ? AND cp.ID_conductor = ?
");
$stmt->execute([$id, $_SESSION['conductor_id']]);

header('Location: viajes.php');
exit;

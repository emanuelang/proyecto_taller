<?php
require_once __DIR__ . '/../../core/storage.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['is_conductor'])) {
    die('Acceso denegado');
}

$id = (int)$_GET['id'];

$stmt_info = $pdo->prepare("
    SELECT pr.ID_pasajero, pas.ID_usuario, p.CiudadOrigen, p.CiudadDestino 
    FROM Reservas r 
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva 
    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero 
    JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion 
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    WHERE p.ID_publicacion = ? AND cp.ID_conductor = ?
");
$stmt_info->execute([$id, $_SESSION['conductor_id']]);
$infoDocs = $stmt_info->fetchAll(PDO::FETCH_ASSOC);

foreach ($infoDocs as $info) {
    $msg = "El viaje de {$info['CiudadOrigen']} a {$info['CiudadDestino']} ha sido cancelado por el conductor.";
    $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")->execute([$info['ID_usuario'], $msg]);
}

$stmt = $pdo->prepare("
    DELETE p 
    FROM Publicaciones p
    JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
    WHERE p.ID_publicacion = ? AND cp.ID_conductor = ?
");
$stmt->execute([$id, $_SESSION['conductor_id']]);

header('Location: viajes.php');
exit;

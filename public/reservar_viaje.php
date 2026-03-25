<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if (!isset($_GET['id'])) {
    die("Viaje no especificado.");
}

$viaje_id = (int) $_GET['id'];

/* Verificar viaje y cupo */
$sql = "
    SELECT 
    p.ID_publicacion,
    c.ID_usuario,
    v.CantidadAsientos AS asientos,
    (
        SELECT COUNT(*) 
        FROM Reservas r
        WHERE r.ID_publicacion = p.ID_publicacion
    ) AS ocupados
FROM Publicaciones p
JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
JOIN Vehiculos v ON p.ID_vehiculo = v.ID_vehiculo
WHERE p.ID_publicacion = :viaje_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':viaje_id' => $viaje_id]);
$viaje = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$viaje) {
    die("El viaje no existe.");
}

if ($viaje['ocupados'] >= $viaje['asientos']) {
    die("No hay asientos disponibles en este viaje.");
}

if (!$viaje) {
    die("El viaje no existe.");
}

/* Evitar reservar propio viaje */
if ($viaje['ID_usuario'] == $_SESSION['user_id']) {
    die("No podés reservar tu propio viaje.");
}

// Obtener o Crear ID Pasajero
$stmt_pasajero = $pdo->prepare("SELECT ID_pasajero FROM Pasajeros WHERE ID_usuario = ?");
$stmt_pasajero->execute([$_SESSION['user_id']]);
$pasajero = $stmt_pasajero->fetch();

if (!$pasajero) {
    $stmt_insert = $pdo->prepare("INSERT INTO Pasajeros (ID_usuario) VALUES (?)");
    $stmt_insert->execute([$_SESSION['user_id']]);
    $pasajero_id = $pdo->lastInsertId();
} else {
    $pasajero_id = $pasajero['ID_pasajero'];
}

/* Evitar duplicado */
$sql = "
    SELECT COUNT(*)
    FROM Reservas r
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    WHERE r.ID_publicacion = :viaje_id
    AND pr.ID_pasajero = :pasajero_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':viaje_id' => $viaje_id,
    ':pasajero_id' => $pasajero_id
]);

if ($stmt->fetchColumn() > 0) {
    die("Ya reservaste este viaje.");
}

$_SESSION['reserva_pendiente'] = $viaje_id;
header("Location: " . BASE_URL . "reservas/pago_simulado.php");
exit;

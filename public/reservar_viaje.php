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

/* Verificar viaje, cupo y datos para el pago */
$sql = "
    SELECT p.ID_publicacion,
           p.CiudadOrigen,
           p.CiudadDestino,
           p.Precio,
           c.ID_usuario,
           v.CantidadAsientos AS asientos,
           (
               SELECT COUNT(*)
               FROM Reservas r
               WHERE r.ID_publicacion = p.ID_publicacion
                 AND r.Estado NOT IN ('Cancelada', 'Rechazada')
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

/* Evitar reservar propio viaje */
if ($viaje['ID_usuario'] == $_SESSION['user_id']) {
    die("No podés reservar tu propio viaje.");
}

/* Obtener o crear ID Pasajero */
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

/* Evitar duplicado: si ya tiene una reserva no cancelada para este viaje */
$sql_dup = "
    SELECT COUNT(*)
    FROM Reservas r
    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
    WHERE r.ID_publicacion = :viaje_id
      AND pr.ID_pasajero   = :pasajero_id
      AND r.Estado NOT IN ('Cancelada', 'Rechazada')
";
$stmt_dup = $pdo->prepare($sql_dup);
$stmt_dup->execute([':viaje_id' => $viaje_id, ':pasajero_id' => $pasajero_id]);

if ($stmt_dup->fetchColumn() > 0) {
    die("Ya tenés una reserva activa para este viaje.");
}

/* Crear la reserva en estado Pendiente */
try {
    $pdo->beginTransaction();

    $stmt_res = $pdo->prepare("
        INSERT INTO Reservas (ID_publicacion, Estado, FechaReserva)
        VALUES (:viaje_id, 'Pendiente', NOW())
    ");
    $stmt_res->execute([':viaje_id' => $viaje_id]);
    $reserva_id = $pdo->lastInsertId();

    $stmt_pr = $pdo->prepare("INSERT INTO PasajerosReservas (ID_pasajero, ID_reserva) VALUES (?, ?)");
    $stmt_pr->execute([$pasajero_id, $reserva_id]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die("Error al crear la reserva: " . $e->getMessage());
}

/* Guardar en sesión los datos necesarios para el pago */
$_SESSION['pago_pendiente'] = [
    'reserva_id'  => $reserva_id,
    'viaje_id'    => $viaje_id,
    'origen'      => $viaje['CiudadOrigen'],
    'destino'     => $viaje['CiudadDestino'],
    'precio'      => $viaje['Precio'],
];

/* Redirigir al pago simulado */
header("Location: " . BASE_URL . "reservas/pago_simulado.php");
exit;

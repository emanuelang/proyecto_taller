<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';

$reserva_id = $_GET['external_reference'] ?? null;

if ($reserva_id) {
    $pdo->prepare("UPDATE Reservas SET Estado = 'Rechazada' WHERE ID_reserva = ?")->execute([$reserva_id]);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pago Fallido - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>
    <div style="max-width: 500px; margin: 50px auto; padding: 30px; border: 1px solid #f5c6cb; border-radius: 8px; background-color: #f8d7da; text-align: center;">
        <h2 style="color: #721c24;">No se pudo procesar el pago</h2>
        <p style="color: #721c24;">Tu reserva ha sido cancelada o Mercado Pago rechazó la transacción. Por favor, intenta de nuevo con otro método de pago.</p>
        
        <br>
        <a href="<?= BASE_URL ?>index.php" class="btn">Volver al inicio</a>
    </div>
</body>
</html>

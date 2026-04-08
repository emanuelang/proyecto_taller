<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pago Pendiente - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body>
    <div style="max-width: 500px; margin: 50px auto; padding: 30px; border: 1px solid #ffeeba; border-radius: 8px; background-color: #fff3cd; text-align: center;">
        <h2 style="color: #856404;">Pago en proceso</h2>
        <p style="color: #856404;">Tu pago a través de Mercado Pago está pendiente de acreditación (suele pasar con pagos en Rapipago/PagoFácil u oXxo). Una vez acreditado, tu reserva quedará confirmada automáticamente.</p>
        
        <br>
        <a href="<?= BASE_URL ?>reservas/mis_reservas.php" class="btn">Ir a Mis Reservas</a>
    </div>
</body>
</html>

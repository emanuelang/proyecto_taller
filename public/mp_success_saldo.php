<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

$external_reference = $_GET['external_reference'] ?? null;
$status = $_GET['collection_status'] ?? null;

if (!$external_reference || $status !== 'approved') {
    die("Error o pago no aprobado.");
}

// Extraer ID de usuario y el monto de la external_reference (Formato: usuarioID_monto)
$partes = explode('_', $external_reference);
if (count($partes) !== 2) {
    die("Referencia de pago inválida.");
}
$usuario_id = (int)$partes[0];
$monto = (float)$partes[1];

if ($monto <= 0) {
    die("Monto inválido.");
}

try {
    $pdo->beginTransaction();

    // Actualizar el saldo del usuario
    $stmt = $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?");
    $stmt->execute([$monto, $usuario_id]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die("Error procesando saldo en la base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Saldo Acreditado - Carpooling</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>main.css">
</head>
<body style="background-color: #f0fdf4; font-family: -apple-system, sans-serif;">
    <div style="max-width: 500px; margin: 80px auto; padding: 40px; background: white; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); text-align: center; border-top: 5px solid #22c55e;">
        <h2 style="color: #166534; font-size: 2em; margin-bottom: 10px;">¡Carga Exitosa!</h2>
        <p style="color: #15803d; font-size: 1.2em; margin-bottom: 30px;">
            Tu dinero ya está disponible en tu billetera virtual.
        </p>
        
        <div style="font-size: 3em; font-weight: bold; color: #15803d; margin-bottom: 30px;">
            +$<?= number_format($monto, 2, ',', '.') ?>
        </div>
        
        <a href="<?= BASE_URL ?>perfil.php" class="btn" style="background-color: #22c55e; color: white; text-decoration: none; padding: 15px 30px; font-size: 1.1em; border-radius: 5px; display: inline-block;">
            Volver a mi Perfil
        </a>
    </div>
</body>
</html>

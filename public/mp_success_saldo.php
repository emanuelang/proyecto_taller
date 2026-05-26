<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../core/security.php';
require_once __DIR__ . '/../core/session_guard.php';
require_once __DIR__ . '/../core/mercadopago.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

require_active_session($pdo);

$external_reference = $_GET['external_reference'] ?? null;
$payment_id = mp_extract_payment_id();

if (!$external_reference) {
    safe_error('Pago no aprobado.');
}

$pending = $_SESSION['pending_saldo'][$external_reference] ?? null;
if (!$pending || (time() - (int)$pending['created_at']) > 1800 || (int)$pending['user_id'] !== (int)$_SESSION['user_id']) {
    safe_error('La operacion de carga no es valida o ya expiro.');
}

$usuario_id = (int)$pending['user_id'];
$monto = (float)$pending['monto'];

if ($monto <= 0 || $monto > 500000) {
    safe_error('Monto invalido.');
}

$is_local_test = mp_local_test_mode() && ($_GET['local_test'] ?? '') === '1';
if (!$is_local_test && !mp_validate_approved_payment($payment_id, $external_reference, $monto)) {
    safe_error('No se pudo validar el pago con Mercado Pago.');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ? AND estado = 'activo'");
    $stmt->execute([$monto, $usuario_id]);
    if ($stmt->rowCount() !== 1) {
        throw new Exception('Usuario inactivo o inexistente.');
    }

    $pdo->commit();
    unset($_SESSION['pending_saldo'][$external_reference]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error procesando saldo: ' . $e->getMessage());
    safe_error('No se pudo acreditar el saldo.');
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
        <h2 style="color: #166534; font-size: 2em; margin-bottom: 10px;">Carga exitosa</h2>
        <p style="color: #15803d; font-size: 1.2em; margin-bottom: 30px;">
            Tu dinero ya esta disponible en tu billetera virtual.
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

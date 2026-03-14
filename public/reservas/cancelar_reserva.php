<?php
session_start();
require_once __DIR__ . "/../../config/database.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserva_id'])) {

    $reserva_id = (int) $_POST['reserva_id'];
    $usuario_id = $_SESSION['user_id'];

    // Obtener info del viaje para calcular la diferencia de horas
    $sql_viaje = "
        SELECT v.fecha 
        FROM reservas r
        JOIN viajes v ON r.viaje_id = v.id
        WHERE r.id = ? AND r.usuario_id = ?
    ";
    $stmt_v = $pdo->prepare($sql_viaje);
    $stmt_v->execute([$reserva_id, $usuario_id]);
    $viaje = $stmt_v->fetch(PDO::FETCH_ASSOC);

    if ($viaje) {
        $fecha_viaje = strtotime($viaje['fecha']);
        $ahora = time();
        $horas_diferencia = ($fecha_viaje - $ahora) / 3600;

        $estado_reembolso = ($horas_diferencia >= 12) ? 'Reembolsado' : 'Sin reembolso';
        $_SESSION['mensaje_cancelacion'] = "Reserva cancelada. Estado: " . $estado_reembolso;

        $sql = "UPDATE reservas 
                SET estado = 'cancelada',
                    fecha_cancelacion = NOW()
                WHERE id = ? AND usuario_id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$reserva_id, $usuario_id]);
    }
}

header("Location: mis_reservas.php");
exit;

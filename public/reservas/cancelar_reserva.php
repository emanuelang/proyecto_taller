<?php
session_start();
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/app.php";
require_once __DIR__ . "/../../core/security.php";
require_once __DIR__ . "/../../core/session_guard.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

require_active_session($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserva_id'])) {
    require_csrf();

    $reserva_id = (int) $_POST['reserva_id'];
    $usuario_id = $_SESSION['user_id'];
    /* El usuario quiere eliminar la reserva completamente de la base de datos */
    $stmt_p = $pdo->prepare("SELECT ID_pasajero FROM Pasajeros WHERE ID_usuario = ?");
    $stmt_p->execute([$usuario_id]);
    $pas = $stmt_p->fetch();

    if ($pas) {
        $check = $pdo->prepare("
            SELECT r.Estado, p.Precio 
            FROM Reservas r
            JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
            JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
            WHERE r.ID_reserva = ? AND pr.ID_pasajero = ?
        ");
        $check->execute([$reserva_id, $pas['ID_pasajero']]);
        $reservaInfo = $check->fetch();
        
        if ($reservaInfo && $reservaInfo['Estado'] !== 'Cancelada') {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE Reservas SET Estado = 'Cancelada' WHERE ID_reserva = ?");
                $stmt->execute([$reserva_id]);

                if ($reservaInfo['Estado'] === 'Completada' && PAYMENTS_ENABLED) {
                    // Reembolsar saldo
                    $reembolso = (float)$reservaInfo['Precio'];
                    $stmt_reembolso = $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?");
                    $stmt_reembolso->execute([$reembolso, $usuario_id]);
                    $_SESSION['mensaje_cancelacion'] = "Has cancelado la reserva exitosamente. Se han reembolsado $" . number_format($reembolso, 2) . " a tu saldo.";
                } else {
                    $_SESSION['mensaje_cancelacion'] = "Has cancelado la reserva exitosamente.";
                }

                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Error al cancelar reserva: " . $e->getMessage());
                $_SESSION['mensaje_cancelacion'] = "Error al cancelar la reserva.";
            }
        } else {
            $_SESSION['mensaje_cancelacion'] = "No se pudo cancelar la reserva (ya fue cancelada o no te pertenece).";
        }
    } else {
        $_SESSION['mensaje_cancelacion'] = "No estás registrado como pasajero.";
    }
}

header("Location: mis_reservas.php");
exit;

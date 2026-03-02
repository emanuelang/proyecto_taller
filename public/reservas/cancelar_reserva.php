<?php
session_start();
require_once __DIR__ . "/../../config/database.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserva_id'])) {

    $reserva_id = (int) $_POST['reserva_id'];
    $usuario_id = $_SESSION['usuario_id'];

    $sql = "UPDATE reservas 
            SET estado = 'cancelada',
                fecha_cancelacion = NOW()
            WHERE id = ? AND usuario_id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$reserva_id, $usuario_id]);
}

header("Location: mis_reservas.php");
exit;

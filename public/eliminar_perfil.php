<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    try {
        $pdo->beginTransaction();

        // 1. Obtener datos del usuario (Saldo y si es conductor)
        $stmt_u = $pdo->prepare("SELECT Saldo FROM Usuarios WHERE ID_usuario = ?");
        $stmt_u->execute([$user_id]);
        $saldo_actual = (float)$stmt_u->fetchColumn();

        $stmt_cond = $pdo->prepare("SELECT ID_conductor FROM Conductores WHERE ID_usuario = ?");
        $stmt_cond->execute([$user_id]);
        $conductor = $stmt_cond->fetch();

        // 2. Si es conductor: Cancelar sus viajes activos y reembolsar a los pasajeros
        if ($conductor) {
            $cond_id = $conductor['ID_conductor'];
            
            // Buscar publicaciones activas
            $stmt_pub = $pdo->prepare("SELECT p.ID_publicacion, p.Precio, p.CiudadOrigen, p.CiudadDestino FROM Publicaciones p JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion WHERE cp.ID_conductor = ? AND p.Estado = 'Activa'");
            $stmt_pub->execute([$cond_id]);
            $publicaciones = $stmt_pub->fetchAll(PDO::FETCH_ASSOC);

            foreach ($publicaciones as $p) {
                // Reembolsar a pasajeros
                $stmt_res = $pdo->prepare("
                    SELECT u.ID_usuario
                    FROM Reservas r
                    JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
                    JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
                    JOIN Usuarios u ON pas.ID_usuario = u.ID_usuario
                    WHERE r.ID_publicacion = ? AND r.Estado = 'Completada'
                ");
                $stmt_res->execute([$p['ID_publicacion']]);
                $reservas = $stmt_res->fetchAll(PDO::FETCH_ASSOC);

                foreach ($reservas as $res) {
                    $pdo->prepare("UPDATE Usuarios SET Saldo = Saldo + ? WHERE ID_usuario = ?")->execute([$p['Precio'], $res['ID_usuario']]);
                    $mensaje = "El conductor del viaje " . $p['CiudadOrigen'] . " a " . $p['CiudadDestino'] . " ha desactivado su cuenta. Se han reembolsado $" . number_format($p['Precio'], 2) . ".";
                    $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")->execute([$res['ID_usuario'], $mensaje]);
                }
                
                // Marcar publicación como cancelada
                $pdo->prepare("UPDATE Publicaciones SET Estado = 'Cancelada' WHERE ID_publicacion = ?")->execute([$p['ID_publicacion']]);
            }
        }

        // 3. Si tiene reservas como pasajero: Cancelarlas
        $stmt_res_pas = $pdo->prepare("
            SELECT r.ID_reserva, p.ID_publicacion, u_cond.ID_usuario AS conductor_u_id
            FROM Reservas r
            JOIN PasajerosReservas pr ON r.ID_reserva = pr.ID_reserva
            JOIN Pasajeros pas ON pr.ID_pasajero = pas.ID_pasajero
            JOIN Publicaciones p ON r.ID_publicacion = p.ID_publicacion
            JOIN ConductorPublicacion cp ON p.ID_publicacion = cp.ID_publicacion
            JOIN Conductores c ON cp.ID_conductor = c.ID_conductor
            JOIN Usuarios u_cond ON c.ID_usuario = u_cond.ID_usuario
            WHERE pas.ID_usuario = ? AND r.Estado = 'Completada'
        ");
        $stmt_res_pas->execute([$user_id]);
        $reservas_pas = $stmt_res_pas->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reservas_pas as $rp) {
            $mensaje_cond = "Un pasajero ha cancelado su reserva (eliminó su cuenta) para tu viaje ID #{$rp['ID_publicacion']}.";
            $pdo->prepare("INSERT INTO Notificaciones (ID_usuario, Mensaje) VALUES (?, ?)")->execute([$rp['conductor_u_id'], $mensaje_cond]);
            $pdo->prepare("UPDATE Reservas SET Estado = 'Cancelada' WHERE ID_reserva = ?")->execute([$rp['ID_reserva']]);
        }

        // 4. Soft Delete: Marcar usuario como Inactivo y vaciar saldo
        $stmt_user = $pdo->prepare("UPDATE Usuarios SET Estado = 'Inactivo', Saldo = 0 WHERE ID_usuario = ?");
        $stmt_user->execute([$user_id]);

        $pdo->commit();

        session_unset();
        session_destroy();
        header("Location: " . BASE_URL . "index.php?msg=" . urlencode("Tu perfil ha sido desactivado correctamente."));
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error al desactivar el perfil: " . $e->getMessage());
    }

} else {
    header("Location: " . BASE_URL . "perfil.php");
    exit;
}

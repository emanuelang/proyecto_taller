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

        // Verificar si es conductor para limpiar publicaciones y vehículos
        $stmt_cond = $pdo->prepare("SELECT ID_conductor FROM Conductores WHERE ID_usuario = ?");
        $stmt_cond->execute([$user_id]);
        $conductor = $stmt_cond->fetch();

        if ($conductor) {
            $cond_id = $conductor['ID_conductor'];
            
            // Eliminar publicaciones
            $stmt_pub = $pdo->prepare("SELECT ID_publicacion FROM ConductorPublicacion WHERE ID_conductor = ?");
            $stmt_pub->execute([$cond_id]);
            $publicaciones = $stmt_pub->fetchAll(PDO::FETCH_ASSOC);
            foreach ($publicaciones as $p) {
                $pdo->prepare("DELETE FROM Publicaciones WHERE ID_publicacion = ?")->execute([$p['ID_publicacion']]);
            }

            // Eliminar vehículos
            $stmt_veh = $pdo->prepare("SELECT ID_vehiculo FROM ConductorVehiculo WHERE ID_conductor = ?");
            $stmt_veh->execute([$cond_id]);
            $vehiculos = $stmt_veh->fetchAll(PDO::FETCH_ASSOC);
            foreach ($vehiculos as $v) {
                $pdo->prepare("DELETE FROM Vehiculos WHERE ID_vehiculo = ?")->execute([$v['ID_vehiculo']]);
            }
        }

        // Eliminar usuario principal (por ON DELETE CASCADE se elimina Conductores, Pasajeros, Reservas asoc., Calificaciones, Notificaciones)
        $stmt_user = $pdo->prepare("DELETE FROM Usuarios WHERE ID_usuario = ?");
        $stmt_user->execute([$user_id]);

        $pdo->commit();

        // Destruir sesión
        session_unset();
        session_destroy();
        
        // Redirigir
        header("Location: " . BASE_URL . "index.php?msg=" . urlencode("Tu perfil ha sido eliminado correctamente."));
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Error al eliminar el perfil: " . $e->getMessage());
    }
} else {
    header("Location: " . BASE_URL . "perfil.php");
    exit;
}

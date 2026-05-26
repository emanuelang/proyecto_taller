<?php

function sync_finished_trips(PDO $pdo): void {
    static $already_synced = false;
    if ($already_synced) {
        return;
    }

    $already_synced = true;
    $stmt = $pdo->prepare("UPDATE Publicaciones SET Estado = 'Finalizada' WHERE Estado = 'Activa' AND HoraSalida < NOW()");
    $stmt->execute();
}


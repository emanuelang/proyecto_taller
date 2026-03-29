<?php
require_once __DIR__ . '/config/database.php';
try {
    // Tabla Conductores
    $pdo->exec("ALTER TABLE Conductores ADD FotoCarnet MEDIUMTEXT NULL");
    $pdo->exec("ALTER TABLE Conductores ADD FotoCara MEDIUMTEXT NULL");
    $pdo->exec("ALTER TABLE Conductores ADD TelefonoContacto VARCHAR(50) NULL");
    $pdo->exec("ALTER TABLE Conductores ADD AliasMP VARCHAR(100) NULL");
    
    // Tabla Vehiculos
    // "Foto" is currently there, we'll keep it or rename it. I'll add the specific new ones.
    $pdo->exec("ALTER TABLE Vehiculos ADD PapelesAuto MEDIUMTEXT NULL");
    $pdo->exec("ALTER TABLE Vehiculos ADD FotoFrente MEDIUMTEXT NULL");
    $pdo->exec("ALTER TABLE Vehiculos ADD FotoCostado MEDIUMTEXT NULL");
    $pdo->exec("ALTER TABLE Vehiculos ADD FotoAtras MEDIUMTEXT NULL");
    
    echo "Database updated successfully.\n";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>

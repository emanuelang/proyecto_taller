<?php
require_once __DIR__ . '/config/database.php';
try {
    // Tabla Conductores (ya aplicado antes, se omiten con try/catch)
    $pdo->exec("ALTER TABLE Conductores ADD FotoCarnet MEDIUMTEXT NULL");
    echo "OK: Conductores.FotoCarnet\n";
} catch (PDOException $e) { echo "SKIP: " . $e->getMessage() . "\n"; }
try { $pdo->exec("ALTER TABLE Conductores ADD FotoCara MEDIUMTEXT NULL"); echo "OK: Conductores.FotoCara\n"; } catch (PDOException $e) { echo "SKIP: " . $e->getMessage() . "\n"; }
try { $pdo->exec("ALTER TABLE Conductores ADD TelefonoContacto VARCHAR(50) NULL"); echo "OK: Conductores.TelefonoContacto\n"; } catch (PDOException $e) { echo "SKIP: " . $e->getMessage() . "\n"; }
try { $pdo->exec("ALTER TABLE Conductores ADD AliasMP VARCHAR(100) NULL"); echo "OK: Conductores.AliasMP\n"; } catch (PDOException $e) { echo "SKIP: " . $e->getMessage() . "\n"; }
try { $pdo->exec("ALTER TABLE Vehiculos ADD PapelesAuto MEDIUMTEXT NULL"); echo "OK: Vehiculos.PapelesAuto\n"; } catch (PDOException $e) { echo "SKIP: " . $e->getMessage() . "\n"; }
try { $pdo->exec("ALTER TABLE Vehiculos ADD FotoFrente MEDIUMTEXT NULL"); echo "OK: Vehiculos.FotoFrente\n"; } catch (PDOException $e) { echo "SKIP: " . $e->getMessage() . "\n"; }
try { $pdo->exec("ALTER TABLE Vehiculos ADD FotoCostado MEDIUMTEXT NULL"); echo "OK: Vehiculos.FotoCostado\n"; } catch (PDOException $e) { echo "SKIP: " . $e->getMessage() . "\n"; }
try { $pdo->exec("ALTER TABLE Vehiculos ADD FotoAtras MEDIUMTEXT NULL"); echo "OK: Vehiculos.FotoAtras\n"; } catch (PDOException $e) { echo "SKIP: " . $e->getMessage() . "\n"; }

// NUEVO: Calle de salida en Publicaciones
try { $pdo->exec("ALTER TABLE Publicaciones ADD CalleSalida VARCHAR(200) NULL AFTER CiudadDestino"); echo "OK: Publicaciones.CalleSalida\n"; } catch (PDOException $e) { echo "SKIP: " . $e->getMessage() . "\n"; }

// NUEVO: Perfil Funcional en Usuarios
try { $pdo->exec("ALTER TABLE Usuarios ADD FotoPerfil MEDIUMTEXT NULL"); echo "OK: Usuarios.FotoPerfil\n"; } catch (PDOException $e) { echo "SKIP: " . $e->getMessage() . "\n"; }
try { $pdo->exec("ALTER TABLE Usuarios ADD Descripcion TEXT NULL"); echo "OK: Usuarios.Descripcion\n"; } catch (PDOException $e) { echo "SKIP: " . $e->getMessage() . "\n"; }
try { $pdo->exec("ALTER TABLE Usuarios ADD Preferencias TEXT NULL"); echo "OK: Usuarios.Preferencias\n"; } catch (PDOException $e) { echo "SKIP: " . $e->getMessage() . "\n"; }

echo "\nBase de datos actualizada.\n";
?>

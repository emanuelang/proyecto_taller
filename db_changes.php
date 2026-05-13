<?php
require_once __DIR__ . '/config/database.php';

$ok = [];
$err = [];

// 1. Añadir columna Estado a Vehiculos
try {
    $pdo->exec("ALTER TABLE Vehiculos ADD COLUMN Estado VARCHAR(50) NOT NULL DEFAULT 'Pendiente'");
    // Todos los vehículos existentes ya estaban aprobados implícitamente → los marcamos como Aceptado
    $pdo->exec("UPDATE Vehiculos SET Estado = 'Aceptado'");
    $ok[] = "✅ Columna 'Estado' añadida a Vehiculos. Vehículos existentes marcados como 'Aceptado'.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        $ok[] = "ℹ️ Columna 'Estado' ya existía en Vehiculos — OK.";
    } else {
        $err[] = "❌ Error en Vehiculos ALTER: " . $e->getMessage();
    }
}

// 2. Crear tabla Soporte (con mayúscula inicial para coincidir con el motor)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS Soporte (
        ID_soporte INT AUTO_INCREMENT PRIMARY KEY,
        ID_usuario INT NOT NULL,
        Asunto     VARCHAR(150) NOT NULL,
        Mensaje    TEXT NOT NULL,
        Fecha      DATETIME DEFAULT CURRENT_TIMESTAMP,
        Estado     VARCHAR(50)  NOT NULL DEFAULT 'Pendiente',
        FOREIGN KEY (ID_usuario) REFERENCES Usuarios(ID_usuario) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ok[] = "✅ Tabla 'Soporte' creada correctamente.";
} catch (PDOException $e) {
    $err[] = "❌ Error creando tabla Soporte: " . $e->getMessage();
}

// Mostrar resultado
echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>DB Changes</title></head><body style='font-family:monospace;padding:30px'>";
echo "<h2>📋 Resultado de la migración de BD</h2>";
foreach ($ok  as $msg) echo "<p style='color:green;'>{$msg}</p>";
foreach ($err as $msg) echo "<p style='color:red;'>{$msg}</p>";

if (empty($err)) {
    echo "<p style='margin-top:20px;font-size:1.1em;font-weight:bold;color:green;'>✅ ¡Todo OK! Ya podés eliminar este archivo.</p>";
} else {
    echo "<p style='margin-top:20px;color:red;'>⚠️ Revisá los errores arriba.</p>";
}
echo "<p><a href='public/index.php'>← Ir al inicio</a></p>";
echo "</body></html>";

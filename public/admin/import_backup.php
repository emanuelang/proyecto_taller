<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../core/security.php';

// Aumentar temporalmente el tiempo máximo de ejecución y la memoria para backups pesados
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        header("Location: dashboard.php?error=upload_failed");
        exit;
    }

    $file = $_FILES['backup_file'];
    if (($file['size'] ?? 0) <= 0 || ($file['size'] ?? 0) > 10 * 1024 * 1024) {
        header("Location: dashboard.php?error=invalid_size");
        exit;
    }
    
    // Validar extensión
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'sql') {
        header("Location: dashboard.php?error=invalid_extension");
        exit;
    }

    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        header("Location: dashboard.php?error=upload_failed");
        exit;
    }
    
    // Validar firma de seguridad (para evitar importar un script de otra base de datos y romper todo)
    if (strpos($content, '-- Backup Generado Automáticamente por la Plataforma Carpooling') === false) {
        header("Location: dashboard.php?error=invalid_signature");
        exit;
    }

    try {
        // Ejecutar el script SQL dividiéndolo
        $queries = explode(";\n", $content);
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                try {
                    $pdo->exec($query);
                } catch (PDOException $subE) {
                    // Ignoramos excepciones de "Query was empty" (códigos 42000) 
                    // que ocurren cuando se manda solo un comentario (-- Algo)
                    if (strpos($subE->getMessage(), 'Query was empty') !== false || $subE->getCode() == '42000') {
                        continue;
                    }
                    // Si es un error real, lo lanzamos
                    throw $subE;
                }
            }
        }
        
        header("Location: dashboard.php?msg=import_success");
        exit;

    } catch (PDOException $e) {
        error_log("Error importando DB: " . $e->getMessage());
        header("Location: dashboard.php?error=import_exception");
        exit;
    }
} else {
    header("Location: dashboard.php");
    exit;
}

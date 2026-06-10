<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/backup_export.php';

ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');

$timestamp = date('Y-m-d_H-i-s');
$sqlFile = "backup_carpooling_{$timestamp}.sql";
$baseName = "paquete_recuperacion_moveon_{$timestamp}";
$tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
$sqlContent = generate_database_backup_sql($pdo);

function cleanup_recovery_files(array $files): void
{
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

try {
    if (class_exists('ZipArchive')) {
        $archivePath = $tmpDir . DIRECTORY_SEPARATOR . $baseName . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo ZIP.');
        }

        $zip->addFromString($sqlFile, $sqlContent);
        $zip->addFromString('README_RESTAURACION.txt', recovery_restore_readme($sqlFile));
        $zip->addFromString('MANIFEST.txt', recovery_manifest($pdo, $sqlFile));
        $zip->addFromString('config/database.example.php', recovery_database_example());
        $zip->addFromString('config/app.example.php', recovery_app_example());
        $zip->addFromString('.env.example', recovery_env_example());
        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($archivePath) . '"');
        header('Content-Length: ' . filesize($archivePath));
        readfile($archivePath);
        cleanup_recovery_files([$archivePath]);
        exit;
    }

    if (!class_exists('PharData')) {
        header('Location: dashboard.php?error=recovery_package_unavailable');
        exit;
    }

    $tarPath = $tmpDir . DIRECTORY_SEPARATOR . $baseName . '.tar';
    $gzPath = $tarPath . '.gz';
    cleanup_recovery_files([$tarPath, $gzPath]);

    $tar = new PharData($tarPath);
    $tar->addFromString($sqlFile, $sqlContent);
    $tar->addFromString('README_RESTAURACION.txt', recovery_restore_readme($sqlFile));
    $tar->addFromString('MANIFEST.txt', recovery_manifest($pdo, $sqlFile));
    $tar->addFromString('config/database.example.php', recovery_database_example());
    $tar->addFromString('config/app.example.php', recovery_app_example());
    $tar->addFromString('.env.example', recovery_env_example());
    $tar->compress(Phar::GZ);
    unset($tar);
    @unlink($tarPath);

    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . basename($gzPath) . '"');
    header('Content-Length: ' . filesize($gzPath));
    readfile($gzPath);
    cleanup_recovery_files([$gzPath]);
    exit;
} catch (Throwable $e) {
    error_log('Error generando paquete de recuperacion: ' . $e->getMessage());
    header('Location: dashboard.php?error=recovery_package_exception');
    exit;
}

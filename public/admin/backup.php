<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/backup_export.php';

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="backup_carpooling_' . date('Y-m-d_H-i-s') . '.sql"');

echo generate_database_backup_sql($pdo);
exit;

<?php

function generate_database_backup_sql(PDO $pdo): string
{
    $sql = "-- Backup Generado Automaticamente por la Plataforma Carpooling\n";
    $sql .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n\n";

    $tables = [];
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $quotedTable = '`' . str_replace('`', '``', $table) . '`';

        $sql .= "-- Estructura de la tabla {$quotedTable}\n";
        $result = $pdo->query("SHOW CREATE TABLE {$quotedTable}");
        $row = $result->fetch(PDO::FETCH_NUM);
        $sql .= "DROP TABLE IF EXISTS {$quotedTable};\n";
        $sql .= $row[1] . ";\n\n";

        $sql .= "-- Volcado de datos de la tabla {$quotedTable}\n";
        $result = $pdo->query("SELECT * FROM {$quotedTable}");
        $rows = $result->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            $insertValues = [];
            foreach ($rows as $dataRow) {
                $vals = [];
                foreach ($dataRow as $val) {
                    $vals[] = $val === null ? "NULL" : $pdo->quote($val);
                }
                $insertValues[] = "(" . implode(", ", $vals) . ")";
            }

            $sql .= "INSERT INTO {$quotedTable} VALUES ";
            $sql .= implode(",\n", $insertValues) . ";\n\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

function recovery_database_example(): string
{
    return <<<'PHP'
<?php
// Copiar este archivo como config/database.php y ajustar credenciales.
$host = '127.0.0.1';
$db   = 'carpooling';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('Error DB: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo conectar con la base de datos.');
}
PHP;
}

function recovery_app_example(): string
{
    return <<<'PHP'
<?php
// Plantilla de configuracion general.
define('BASE_URL', 'http://localhost/proyecto_taller/public/');
define('SESSION_TIMEOUT_SECONDS', 30 * 60);
define('PAYMENTS_ENABLED', false);
PHP;
}

function recovery_env_example(): string
{
    return <<<'ENV'
APP_NAME=MOVEON
APP_URL=http://localhost/proyecto_taller/public/

DB_HOST=127.0.0.1
DB_DATABASE=carpooling
DB_USERNAME=root
DB_PASSWORD=

PHP_VERSION=8.x
MYSQL_VERSION=8.x_or_MariaDB_XAMPP
APACHE=XAMPP_Apache

PAYMENTS_ENABLED=false
SESSION_TIMEOUT_SECONDS=1800
ENV;
}

function recovery_restore_readme(string $sqlFile): string
{
    return "RECUPERACION ANTE DESASTRES - MOVEON\n"
        . "====================================\n\n"
        . "Contenido del paquete:\n"
        . "- {$sqlFile}: backup logico de la base de datos.\n"
        . "- config/database.example.php: plantilla de conexion MySQL.\n"
        . "- config/app.example.php: plantilla de configuracion general.\n"
        . "- .env.example: variables necesarias para documentar el entorno.\n"
        . "- MANIFEST.txt: resumen tecnico del paquete.\n\n"
        . "Pasos para restaurar desde cero:\n"
        . "1. Instalar XAMPP o un entorno compatible con Apache, PHP y MySQL/MariaDB.\n"
        . "2. Copiar o clonar el proyecto en C:\\xampp\\htdocs\\proyecto_taller.\n"
        . "3. Crear una base de datos llamada carpooling.\n"
        . "4. Importar el archivo {$sqlFile} en esa base.\n"
        . "5. Copiar config/database.example.php como config/database.php y ajustar host, base, usuario y clave.\n"
        . "6. Revisar config/app.php o usar config/app.example.php como referencia.\n"
        . "7. Iniciar Apache y MySQL.\n"
        . "8. Abrir http://localhost/proyecto_taller/public/.\n"
        . "9. Probar login, busqueda de viajes y panel de administracion.\n\n"
        . "Importante:\n"
        . "- Este paquete incluye base de datos y plantillas de configuracion.\n"
        . "- El codigo fuente debe recuperarse desde el repositorio o una copia completa del proyecto.\n"
        . "- Si en una version futura se guardan archivos subidos en disco, tambien deben respaldarse esas carpetas.\n";
}

function recovery_manifest(PDO $pdo, string $sqlFile): string
{
    $tables = [];
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    return "MOVEON - Paquete de recuperacion\n"
        . "Generado: " . date('Y-m-d H:i:s') . "\n"
        . "Archivo SQL: {$sqlFile}\n"
        . "Tablas incluidas: " . implode(', ', $tables) . "\n"
        . "PHP: " . PHP_VERSION . "\n"
        . "Formato: SQL + plantillas de configuracion + instrucciones\n";
}

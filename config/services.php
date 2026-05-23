<?php
// Configuracion centralizada para servicios externos.
// Los valores sensibles se leen de config/local.php o variables de entorno.

$local_config = [];
$local_config_path = __DIR__ . '/local.php';
if (is_file($local_config_path)) {
    $loaded_config = require $local_config_path;
    if (is_array($loaded_config)) {
        $local_config = $loaded_config;
    }
}

function config_get(string $section, string $key, $default = null) {
    global $local_config;

    $env_key = strtoupper($section . '_' . $key);
    $env_value = getenv($env_key);
    if ($env_value !== false) {
        return $env_value;
    }

    return $local_config[$section][$key] ?? $default;
}

function config_bool(string $section, string $key, bool $default = false): bool {
    $value = config_get($section, $key, $default);
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
}

function app_public_url(string $path = ''): string {
    $base = trim((string)config_get('app', 'public_base_url', ''));
    if ($base === '') {
        $base = defined('BASE_URL') ? BASE_URL : '';
    }

    return rtrim($base, '/') . '/' . ltrim($path, '/');
}


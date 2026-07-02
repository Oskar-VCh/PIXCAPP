<?php
// Configuración global
define('APP_NAME', 'PIXCAPP');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/pixcapp/');
define('UPLOAD_DIR', __DIR__ . '/assets/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'u83030_pixcapp_final');
define('DB_USER', 'u83030_pixcapp_final');
define('DB_PASS', 'GPaZYJWVPXBKCRDftRrg');

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

// Modo desarrollo
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

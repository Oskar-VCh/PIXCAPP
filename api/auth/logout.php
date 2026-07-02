<?php
/**
 * API Logout - PIXCAPP
 * Endpoint: POST /api/auth/logout.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Database.php';

// Obtener token
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = null;

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

if ($token) {
    try {
        $db = Database::getInstance();
        
        // Invalidar token
        $db->query(
            "DELETE FROM sesiones WHERE token = ?",
            [$token]
        );
        
        // Registrar logout
        $db->query(
            "INSERT INTO logs_auditoria (accion, detalle, ip) VALUES (?, ?, ?)",
            ['logout', 'Cierre de sesión', $_SERVER['REMOTE_ADDR']]
        );
        
    } catch (Exception $e) {
        // Ignorar errores en logout
    }
}

echo json_encode(['success' => true, 'message' => 'Sesión cerrada']);
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Database.php';

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = null;
if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) $token = $matches[1];

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $db = Database::getInstance();
    
    $stmt = $db->query(
        "SELECT u.id FROM sesiones s JOIN usuarios u ON s.usuario_id = u.id 
         WHERE s.token = ? AND (s.expira IS NULL OR s.expira > NOW()) AND u.rol = 'agricultor'",
        [$token]
    );
    $user = $stmt->fetch();
    if (!$user) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Sesión inválida']); exit; }
    
    $db->query(
        "UPDATE asignaciones SET estado = 'rechazado', fecha_respuesta = NOW() 
         WHERE agricultor_id = ? AND estado = 'activo'",
        [$user['id']]
    );
    
    echo json_encode(['success' => true, 'message' => 'Ingeniero eliminado']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
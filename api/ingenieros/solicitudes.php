<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
         WHERE s.token = ? AND (s.expira IS NULL OR s.expira > NOW()) AND u.rol = 'ingeniero'",
        [$token]
    );
    $user = $stmt->fetch();
    if (!$user) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'No autorizado']); exit; }
    
    $stmt = $db->query(
        "SELECT a.id, a.fecha_solicitud, u.id as agricultor_id, u.nombre, u.telefono
         FROM asignaciones a
         JOIN usuarios u ON a.agricultor_id = u.id
         WHERE a.ingeniero_id = ? AND a.estado = 'pendiente'
         ORDER BY a.fecha_solicitud DESC",
        [$user['id']]
    );
    $solicitudes = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'solicitudes' => $solicitudes]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
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
         WHERE s.token = ? AND (s.expira IS NULL OR s.expira > NOW())",
        [$token]
    );
    $user = $stmt->fetch();
    if (!$user) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Sesión inválida']); exit; }
    
    // Buscar ingeniero asignado activo
    $stmt = $db->query(
        "SELECT u.id, u.nombre, u.telefono, u.correo, i.cedula_profesional as cedula, i.especialidad
         FROM asignaciones a
         JOIN usuarios u ON a.ingeniero_id = u.id
         LEFT JOIN ingenieros i ON u.id = i.usuario_id
         WHERE a.agricultor_id = ? AND a.estado = 'activo'
         LIMIT 1",
        [$user['id']]
    );
    $ingeniero = $stmt->fetch();
    
    if (!$ingeniero) {
        echo json_encode(['success' => false, 'message' => 'No tienes ingeniero asignado']);
        exit;
    }
    
    // Obtener recomendaciones recientes
    $stmt = $db->query(
        "SELECT mensaje, fecha_envio FROM recomendaciones 
         WHERE agricultor_id = ? AND ingeniero_id = ?
         ORDER BY fecha_envio DESC LIMIT 5",
        [$user['id'], $ingeniero['id']]
    );
    $recomendaciones = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'ingeniero' => $ingeniero,
        'recomendaciones' => $recomendaciones
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
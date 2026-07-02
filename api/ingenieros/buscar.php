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

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['success' => true, 'ingenieros' => []]);
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
    
    // Buscar ingenieros activos que coincidan
    $stmt = $db->query(
        "SELECT u.id, u.nombre, u.telefono, i.especialidad
         FROM usuarios u
         JOIN ingenieros i ON u.id = i.usuario_id
         WHERE u.rol = 'ingeniero' AND u.estado = 'activo' 
         AND (u.nombre LIKE ? OR u.telefono LIKE ?)
         AND u.id != ?
         LIMIT 10",
        ["%$q%", "%$q%", $user['id']]
    );
    $ingenieros = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'ingenieros' => $ingenieros]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
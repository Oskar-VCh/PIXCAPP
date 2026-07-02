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

$input = json_decode(file_get_contents('php://input'), true);
$agricultorId = $input['agricultor_id'] ?? null;
$parcelaId = $input['parcela_id'] ?? null;
$mensaje = trim($input['mensaje'] ?? '');

if (!$agricultorId || empty($mensaje)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
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
    
    // Verificar asignación activa
    $stmt = $db->query(
        "SELECT id FROM asignaciones WHERE ingeniero_id = ? AND agricultor_id = ? AND estado = 'activo'",
        [$user['id'], $agricultorId]
    );
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'No tienes asignado este agricultor']);
        exit;
    }
    
    $db->query(
        "INSERT INTO recomendaciones (ingeniero_id, agricultor_id, parcela_id, mensaje) VALUES (?, ?, ?, ?)",
        [$user['id'], $agricultorId, $parcelaId, $mensaje]
    );
    
    // Notificar
    $db->query(
        "INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, 'Nueva recomendación', ?, 'recomendacion')",
        [$agricultorId, substr($mensaje, 0, 100)]
    );
    
    echo json_encode(['success' => true, 'message' => 'Recomendación enviada']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
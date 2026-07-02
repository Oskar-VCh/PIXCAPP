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
$solicitudId = $input['solicitud_id'] ?? null;
$accion = $input['accion'] ?? null; // 'aceptar' o 'rechazar'

if (!$solicitudId || !in_array($accion, ['aceptar', 'rechazar'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
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
    
    // Verificar que la solicitud pertenezca al ingeniero
    $stmt = $db->query(
        "SELECT a.*, u.nombre as agricultor_nombre 
         FROM asignaciones a JOIN usuarios u ON a.agricultor_id = u.id 
         WHERE a.id = ? AND a.ingeniero_id = ? AND a.estado = 'pendiente'",
        [$solicitudId, $user['id']]
    );
    $solicitud = $stmt->fetch();
    
    if (!$solicitud) {
        echo json_encode(['success' => false, 'message' => 'Solicitud no encontrada']);
        exit;
    }
    
    $nuevoEstado = $accion === 'aceptar' ? 'activo' : 'rechazado';
    
    $db->query(
        "UPDATE asignaciones SET estado = ?, fecha_respuesta = NOW() WHERE id = ?",
        [$nuevoEstado, $solicitudId]
    );
    
    // Notificar al agricultor
    $mensaje = $accion === 'aceptar' 
        ? "Tu solicitud de asesoría fue aceptada" 
        : "Tu solicitud de asesoría fue rechazada";
    
    $db->query(
        "INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo) VALUES (?, ?, ?, 'respuesta')",
        [$solicitud['agricultor_id'], 'Respuesta a solicitud', $mensaje]
    );
    
    echo json_encode(['success' => true, 'message' => 'Solicitud ' . ($accion === 'aceptar' ? 'aceptada' : 'rechazada')]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
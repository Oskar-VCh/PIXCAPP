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
$ingenieroId = $input['ingeniero_id'] ?? null;

if (!$ingenieroId) {
    echo json_encode(['success' => false, 'message' => 'ID de ingeniero requerido']);
    exit;
}

try {
    $db = Database::getInstance();
    
    $stmt = $db->query(
        "SELECT u.id, u.nombre FROM sesiones s JOIN usuarios u ON s.usuario_id = u.id 
         WHERE s.token = ? AND (s.expira IS NULL OR s.expira > NOW()) AND u.rol = 'agricultor'",
        [$token]
    );
    $user = $stmt->fetch();
    if (!$user) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Sesión inválida']); exit; }
    
    // Verificar que el ingeniero exista y esté activo
    $stmt = $db->query(
        "SELECT id FROM usuarios WHERE id = ? AND rol = 'ingeniero' AND estado = 'activo'",
        [$ingenieroId]
    );
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Ingeniero no encontrado']);
        exit;
    }
    
    // Verificar si ya existe una asignación
    $stmt = $db->query(
        "SELECT id, estado FROM asignaciones WHERE ingeniero_id = ? AND agricultor_id = ?",
        [$ingenieroId, $user['id']]
    );
    $existe = $stmt->fetch();
    
    if ($existe) {
        if ($existe['estado'] === 'activo') {
            echo json_encode(['success' => false, 'message' => 'Ya tienes este ingeniero asignado']);
            exit;
        }
        // Actualizar solicitud existente
        $db->query(
            "UPDATE asignaciones SET estado = 'pendiente', fecha_solicitud = NOW() WHERE id = ?",
            [$existe['id']]
        );
    } else {
        // Crear nueva solicitud
        $db->query(
            "INSERT INTO asignaciones (ingeniero_id, agricultor_id, estado) VALUES (?, ?, 'pendiente')",
            [$ingenieroId, $user['id']]
        );
    }
    
    // Notificar al ingeniero
    $db->query(
        "INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, datos) 
         VALUES (?, 'Nueva solicitud', ?, 'solicitud', ?)",
        [$ingenieroId, "{$user['nombre']} quiere que seas su asesor", json_encode(['agricultor_id' => $user['id']])]
    );
    
    echo json_encode(['success' => true, 'message' => 'Solicitud enviada']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor']);
}
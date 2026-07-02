<?php
/**
 * API Verify Token - PIXCAPP
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Database.php';

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = null;

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

if (!$token) {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? null;
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['valid' => false, 'message' => 'Token no proporcionado']);
    exit;
}

try {
    $db = Database::getInstance();
    
    $stmt = $db->query(
        "SELECT s.*, u.id, u.nombre, u.telefono, u.correo, u.rol, u.estado, u.foto_perfil
         FROM sesiones s 
         JOIN usuarios u ON s.usuario_id = u.id 
         WHERE s.token = ? AND (s.expira IS NULL OR s.expira > NOW())",
        [$token]
    );
    
    $session = $stmt->fetch();
    
    if (!$session) {
        http_response_code(401);
        echo json_encode(['valid' => false, 'message' => 'Token inválido o expirado']);
        exit;
    }
    
    if ($session['estado'] !== 'activo') {
        http_response_code(403);
        echo json_encode(['valid' => false, 'message' => 'Cuenta no activa']);
        exit;
    }
    
    echo json_encode([
        'valid' => true,
        'user' => [
            'id' => $session['id'],
            'nombre' => $session['nombre'],
            'telefono' => $session['telefono'],
            'correo' => $session['correo'],
            'rol' => $session['rol'],
            'foto_perfil' => $session['foto_perfil']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['valid' => false, 'message' => 'Error del servidor']);
}
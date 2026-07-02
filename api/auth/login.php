<?php
/**
 * API Login - PIXCAPP
 * Endpoint: POST /api/auth/login.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Database.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$telefono = preg_replace('/[^0-9]/', '', $input['telefono'] ?? '');
$password = $input['password'] ?? '';
$remember = $input['remember'] ?? false;

if (empty($telefono) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Teléfono y contraseña requeridos']);
    exit;
}

if (strlen($telefono) !== 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Teléfono debe tener 10 dígitos']);
    exit;
}

try {
    $db = Database::getInstance();
    
    $stmt = $db->query(
        "SELECT id, nombre, telefono, correo, password_hash, rol, estado, foto_perfil 
         FROM usuarios WHERE telefono = ?",
        [$telefono]
    );
    
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas']);
        exit;
    }
    
    if ($user['estado'] !== 'activo') {
        $mensajes = [
            'pendiente' => 'Tu cuenta está pendiente de validación',
            'suspendido' => 'Tu cuenta ha sido suspendida',
            'eliminado' => 'Esta cuenta ha sido eliminada'
        ];
        $mensaje = $mensajes[$user['estado']] ?? 'Cuenta no activa';
        
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => $mensaje]);
        exit;
    }
    
    // Generar token
    $token = bin2hex(random_bytes(32));
    $expira = $remember ? date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)) : date('Y-m-d H:i:s', time() + (24 * 60 * 60));
    
    // Guardar sesión
    $db->query(
        "INSERT INTO sesiones (usuario_id, token, expira, ip, user_agent) 
         VALUES (?, ?, ?, ?, ?)",
        [$user['id'], $token, $expira, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '']
    );
    
    // Actualizar último acceso
    $db->query("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?", [$user['id']]);
    
    $userData = [
        'id' => $user['id'],
        'nombre' => $user['nombre'],
        'telefono' => $user['telefono'],
        'correo' => $user['correo'],
        'rol' => $user['rol'],
        'foto_perfil' => $user['foto_perfil']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Inicio de sesión exitoso',
        'token' => $token,
        'user' => $userData
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . (DEBUG_MODE ? $e->getMessage() : 'Intente más tarde')
    ]);
}
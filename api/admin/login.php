<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }
    
    $usuario = trim($input['usuario'] ?? '');
    $password = $input['password'] ?? '';
    
    // Validar campos vacíos
    if (empty($usuario) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son requeridos']);
        exit;
    }
    
    // Buscar usuario por nombre o teléfono
    $stmt = $db->prepare("
        SELECT id, nombre, telefono, correo, password_hash, rol, estado, foto_perfil, fecha_registro
        FROM usuarios 
        WHERE (nombre = ? OR telefono = ? OR correo = ?) AND rol = 'admin'
        LIMIT 1
    ");
    $stmt->execute([$usuario, $usuario, $usuario]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Registrar intento fallido
        registrarLog(null, 'login_fallido_admin', "Intento de login con usuario: $usuario");
        echo json_encode(['success' => false, 'message' => 'Credenciales inválidas']);
        exit;
    }
    
    // Verificar estado del usuario
    if ($user['estado'] !== 'activo') {
        echo json_encode(['success' => false, 'message' => 'Cuenta inactiva o suspendida']);
        exit;
    }
    
    // Verificar contraseña
    if (!password_verify($password, $user['password_hash'])) {
        registrarLog($user['id'], 'login_fallido', 'Contraseña incorrecta');
        echo json_encode(['success' => false, 'message' => 'Credenciales inválidas']);
        exit;
    }
    
    // Generar token de sesión
    $token = bin2hex(random_bytes(32));
    $expira = date('Y-m-d H:i:s', strtotime('+7 days'));
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Guardar sesión
    $stmt = $db->prepare("
        INSERT INTO sesiones (usuario_id, token, expira, ip, user_agent, ultima_actividad) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user['id'], $token, $expira, $ip, $userAgent]);
    
    // Actualizar último acceso del usuario
    $stmt = $db->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Registrar login exitoso
    registrarLog($user['id'], 'login_exitoso', 'Inicio de sesión exitoso');
    
    // Preparar respuesta (sin datos sensibles)
    $userData = [
        'id' => $user['id'],
        'nombre' => $user['nombre'],
        'telefono' => $user['telefono'],
        'correo' => $user['correo'],
        'rol' => $user['rol'],
        'foto_perfil' => $user['foto_perfil'],
        'fecha_registro' => $user['fecha_registro']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Login exitoso',
        'token' => $token,
        'user' => $userData
    ]);
    
} catch (PDOException $e) {
    registrarLog(null, 'error_db', 'Error en login: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
} catch (Exception $e) {
    registrarLog(null, 'error_general', 'Error en login: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}

/**
 * Función para registrar logs de auditoría
 */
function registrarLog($usuarioId, $accion, $detalle) {
    try {
        $db = Database::getInstance()->getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $db->prepare("
            INSERT INTO logs_auditoria (usuario_id, accion, detalle, ip, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$usuarioId, $accion, $detalle, $ip, $userAgent]);
    } catch (Exception $e) {
        // No hacer nada si falla el log
    }
}
?>
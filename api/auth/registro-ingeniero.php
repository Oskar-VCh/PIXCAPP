<?php
/**
 * API Registro Ingeniero - PIXCAPP
 * Endpoint: POST /api/auth/registro-ingeniero.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
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
require_once __DIR__ . '/../../core/funciones.php';

$input = json_decode(file_get_contents('php://input'), true);

// Validar datos requeridos
$nombre = trim($input['nombre'] ?? '');
$telefono = trim($input['telefono'] ?? '');
$correo = trim($input['correo'] ?? '');
$cedula = trim($input['cedula'] ?? '');
$password = $input['password'] ?? '';
$fotoPerfil = $input['foto_perfil'] ?? null;

// Validaciones
if (empty($nombre)) {
    echo json_encode(['success' => false, 'message' => 'El nombre es requerido']);
    exit;
}

if (!validarTelefono($telefono)) {
    echo json_encode(['success' => false, 'message' => 'Teléfono inválido (10 dígitos)']);
    exit;
}

if (empty($cedula)) {
    echo json_encode(['success' => false, 'message' => 'La cédula profesional es requerida']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Verificar si el teléfono ya existe
    $stmt = $db->query("SELECT id FROM usuarios WHERE telefono = ?", [$telefono]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este número de teléfono ya está registrado']);
        exit;
    }
    
    // Verificar si el correo ya existe (si se proporcionó)
    if (!empty($correo)) {
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Correo electrónico inválido']);
            exit;
        }
        $stmt = $db->query("SELECT id FROM usuarios WHERE correo = ?", [$correo]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Este correo electrónico ya está registrado']);
            exit;
        }
    }
    
    // Verificar si la cédula ya existe
    $stmt = $db->query("SELECT id FROM ingenieros WHERE cedula_profesional = ?", [$cedula]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Esta cédula profesional ya está registrada']);
        exit;
    }
    
    // Procesar foto de perfil (opcional)
    $fotoPath = null;
    if ($fotoPerfil && strpos($fotoPerfil, 'data:image') === 0) {
        $fotoPath = guardarImagenBase64($fotoPerfil, 'perfiles/');
    }
    
    // Hash de contraseña
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    
    // Iniciar transacción
    $db->getConnection()->beginTransaction();
    
    // Insertar usuario con estado pendiente
    $db->query(
        "INSERT INTO usuarios (nombre, telefono, correo, password_hash, rol, estado, foto_perfil) 
         VALUES (?, ?, ?, ?, 'ingeniero', 'pendiente', ?)",
        [$nombre, $telefono, $correo ?: null, $passwordHash, $fotoPath]
    );
    
    $usuarioId = $db->lastInsertId();
    
    // Insertar en tabla ingenieros
    $db->query(
        "INSERT INTO ingenieros (usuario_id, cedula_profesional) 
         VALUES (?, ?)",
        [$usuarioId, $cedula]
    );
    
    // Registrar log
    $db->query(
        "INSERT INTO logs_auditoria (usuario_id, accion, detalle, ip) VALUES (?, ?, ?, ?)",
        [$usuarioId, 'registro_pendiente', 'Solicitud de registro de ingeniero', $_SERVER['REMOTE_ADDR']]
    );
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Solicitud enviada correctamente. Pendiente de validación.'
    ]);
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->getConnection()->rollBack();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . (DEBUG_MODE ? $e->getMessage() : 'Intente más tarde')
    ]);
}
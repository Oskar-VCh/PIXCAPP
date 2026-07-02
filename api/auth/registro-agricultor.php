<?php
/**
 * API Registro Agricultor - PIXCAPP
 * Endpoint: POST /api/auth/registro-agricultor.php
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
$password = $input['password'] ?? '';
$fotoPerfil = $input['foto_perfil'] ?? null;
$parcela = $input['parcela'] ?? [];

// Validaciones
if (empty($nombre)) {
    echo json_encode(['success' => false, 'message' => 'El nombre es requerido']);
    exit;
}

if (!validarTelefono($telefono)) {
    echo json_encode(['success' => false, 'message' => 'Teléfono inválido (10 dígitos)']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
    exit;
}

if (empty($parcela['nombre']) || empty($parcela['latitud']) || empty($parcela['longitud'])) {
    echo json_encode(['success' => false, 'message' => 'Datos de parcela incompletos']);
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
    
    // Verificar correo si se proporcionó
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
    
    // Verificar duplicado de parcela (radio 50m)
    $duplicado = verificarDuplicadoParcela($parcela['latitud'], $parcela['longitud']);
    if ($duplicado) {
        echo json_encode([
            'success' => false, 
            'message' => 'Ya existe una parcela registrada a menos de 50 metros de esta ubicación',
            'duplicado' => [
                'nombre' => $duplicado['nombre'],
                'distancia' => round($duplicado['distancia']) . ' metros'
            ]
        ]);
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
    
    // Insertar usuario
    $db->query(
        "INSERT INTO usuarios (nombre, telefono, correo, password_hash, rol, estado, foto_perfil) 
         VALUES (?, ?, ?, ?, 'agricultor', 'activo', ?)",
        [$nombre, $telefono, $correo ?: null, $passwordHash, $fotoPath]
    );
    
    $usuarioId = $db->lastInsertId();
    
    // Insertar parcela
    $db->query(
        "INSERT INTO parcelas (agricultor_id, nombre, cultivo_id, variedad, fecha_siembra, latitud, longitud, estado) 
         VALUES (?, ?, ?, ?, ?, ?, ?, 'activo')",
        [
            $usuarioId,
            $parcela['nombre'],
            $parcela['cultivo_id'] ?: 1,
            $parcela['variedad'] ?? 'Blanco Criollo',
            $parcela['fecha_siembra'] ?? date('Y-m-d'),
            $parcela['latitud'],
            $parcela['longitud']
        ]
    );
    
    // Generar token de sesión
    $token = bin2hex(random_bytes(32));
    $expira = time() + (30 * 24 * 60 * 60);
    
    $db->query(
        "INSERT INTO sesiones (usuario_id, token, expira, ip, user_agent) 
         VALUES (?, ?, FROM_UNIXTIME(?), ?, ?)",
        [$usuarioId, $token, $expira, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]
    );
    
    // Registrar log
    $db->query(
        "INSERT INTO logs_auditoria (usuario_id, accion, detalle, ip) VALUES (?, ?, ?, ?)",
        [$usuarioId, 'registro', 'Registro de agricultor exitoso', $_SERVER['REMOTE_ADDR']]
    );
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cuenta creada exitosamente',
        'token' => $token,
        'user' => [
            'id' => $usuarioId,
            'nombre' => $nombre,
            'telefono' => $telefono,
            'correo' => $correo,
            'rol' => 'agricultor',
            'foto_perfil' => $fotoPath
        ]
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
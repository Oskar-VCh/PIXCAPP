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

// Obtener token
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');
$token = str_replace('Bearer ', '', $authHeader);

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token requerido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar sesión
    $stmt = $db->prepare("SELECT usuario_id FROM sesiones WHERE token = ? AND expira > NOW()");
    $stmt->execute([$token]);
    $sesion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sesion) {
        echo json_encode(['success' => false, 'message' => 'Sesión inválida o expirada']);
        exit;
    }
    
    $usuarioId = $sesion['usuario_id'];
    
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        exit;
    }
    
    $nombre = trim($input['nombre'] ?? '');
    $telefono = trim($input['telefono'] ?? '');
    $correo = trim($input['correo'] ?? '');
    
    if (empty($nombre)) {
        echo json_encode(['success' => false, 'message' => 'El nombre es requerido']);
        exit;
    }
    
    // Construir consulta dinámica
    $campos = [];
    $params = [];
    
    if (!empty($nombre)) {
        $campos[] = "nombre = ?";
        $params[] = $nombre;
    }
    
    if (!empty($telefono)) {
        $campos[] = "telefono = ?";
        $params[] = $telefono;
    }
    
    if (!empty($correo)) {
        $campos[] = "correo = ?";
        $params[] = $correo;
    }
    
    if (empty($campos)) {
        echo json_encode(['success' => false, 'message' => 'No hay datos para actualizar']);
        exit;
    }
    
    $params[] = $usuarioId;
    $sql = "UPDATE usuarios SET " . implode(", ", $campos) . " WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute($params);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Perfil actualizado correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el perfil']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
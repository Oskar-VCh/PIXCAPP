<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../core/Database.php';

// Obtener token del header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');
$token = str_replace('Bearer ', '', $authHeader);

error_log("Token recibido: " . substr($token, 0, 20) . "...");

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token requerido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar sesión
    $stmt = $db->prepare("SELECT usuario_id, expira FROM sesiones WHERE token = ?");
    $stmt->execute([$token]);
    $sesion = $stmt->fetch();
    
    if (!$sesion) {
        echo json_encode(['success' => false, 'message' => 'Sesión no encontrada']);
        exit;
    }
    
    // Verificar si la sesión ha expirado
    $fechaExpiracion = strtotime($sesion['expira']);
    if ($fechaExpiracion < time()) {
        echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
        exit;
    }
    
    // Obtener cultivos activos
    $query = "SELECT id, nombre_comun as nombre, nombre_cientifico, imagen_principal 
              FROM cultivos_taxonomia 
              WHERE estado = 'activo' 
              ORDER BY nombre_comun";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $cultivos = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'cultivos' => $cultivos]);
    
} catch (PDOException $e) {
    error_log("Error en listar.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>
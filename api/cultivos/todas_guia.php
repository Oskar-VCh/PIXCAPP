<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../core/Database.php';

// Verificar token
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token requerido']);
    exit;
}

$db = Database::getInstance()->getConnection();

// Verificar sesión
$stmt = $db->prepare("SELECT usuario_id FROM sesiones WHERE token = ? AND expira > NOW()");
$stmt->execute([$token]);
$sesion = $stmt->fetch();

if (!$sesion) {
    echo json_encode(['success' => false, 'message' => 'Sesión inválida o expirada']);
    exit;
}

// Obtener todos los cultivos activos
$query = "SELECT id, nombre_comun as nombre, nombre_cientifico, imagen_principal 
          FROM cultivos_taxonomia 
          WHERE estado = 'activo' 
          ORDER BY nombre_comun";

$stmt = $db->prepare($query);
$stmt->execute();
$cultivos = $stmt->fetchAll();

echo json_encode(['success' => true, 'cultivos' => $cultivos]);
?>
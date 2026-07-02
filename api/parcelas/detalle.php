<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../core/Database.php';

$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');

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
    echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
    exit;
}

$parcelaId = $_GET['id'] ?? 0;

if (!$parcelaId) {
    echo json_encode(['success' => false, 'message' => 'ID de parcela requerido']);
    exit;
}

// Obtener parcela
$stmt = $db->prepare("
    SELECT p.*, 
           DATEDIFF(CURDATE(), p.fecha_siembra) as dias_desde_siembra,
           c.nombre_comun as cultivo_nombre
    FROM parcelas p
    LEFT JOIN cultivos_taxonomia c ON p.cultivo_id = c.id
    WHERE p.id = ? AND p.agricultor_id = ?
");
$stmt->execute([$parcelaId, $sesion['usuario_id']]);
$parcela = $stmt->fetch();

if (!$parcela) {
    echo json_encode(['success' => false, 'message' => 'Parcela no encontrada']);
    exit;
}

// Obtener eventos
$stmt = $db->prepare("
    SELECT * FROM eventos 
    WHERE parcela_id = ? 
    ORDER BY fecha_evento DESC 
    LIMIT 50
");
$stmt->execute([$parcelaId]);
$eventos = $stmt->fetchAll();

// Obtener fotos
$stmt = $db->prepare("
    SELECT * FROM fotos 
    WHERE parcela_id = ? 
    ORDER BY fecha_subida DESC
");
$stmt->execute([$parcelaId]);
$fotos = $stmt->fetchAll();

// Obtener mediciones
$stmt = $db->prepare("
    SELECT * FROM eventos 
    WHERE parcela_id = ? AND tipo = 'medicion'
    ORDER BY fecha_evento ASC
");
$stmt->execute([$parcelaId]);
$mediciones = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'parcela' => $parcela,
    'eventos' => $eventos,
    'fotos' => $fotos,
    'mediciones' => $mediciones
]);
?>
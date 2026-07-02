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

$stmt = $db->prepare("SELECT usuario_id FROM sesiones WHERE token = ? AND expira > NOW()");
$stmt->execute([$token]);
$sesion = $stmt->fetch();

if (!$sesion) {
    echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
    exit;
}

$parcelaId = $_GET['id'] ?? 0;
$input = json_decode(file_get_contents('php://input'), true);

if (!$parcelaId) {
    echo json_encode(['success' => false, 'message' => 'ID de parcela requerido']);
    exit;
}

$nombre = $input['nombre'] ?? '';
$variedad = $input['variedad'] ?? '';
$fecha_siembra = $input['fecha_siembra'] ?? null;
$notas = $input['notas'] ?? '';

$stmt = $db->prepare("
    UPDATE parcelas 
    SET nombre = ?, variedad = ?, fecha_siembra = ?, notas = ?
    WHERE id = ? AND agricultor_id = ?
");
$result = $stmt->execute([$nombre, $variedad, $fecha_siembra, $notas, $parcelaId, $sesion['usuario_id']]);

echo json_encode(['success' => $result, 'message' => $result ? 'Actualizado' : 'Error al actualizar']);
?>
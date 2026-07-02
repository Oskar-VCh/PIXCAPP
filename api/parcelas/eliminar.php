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

if (!$parcelaId) {
    echo json_encode(['success' => false, 'message' => 'ID de parcela requerido']);
    exit;
}

$stmt = $db->prepare("DELETE FROM parcelas WHERE id = ? AND agricultor_id = ?");
$result = $stmt->execute([$parcelaId, $sesion['usuario_id']]);

echo json_encode(['success' => $result, 'message' => $result ? 'Eliminada' : 'Error al eliminar']);
?>
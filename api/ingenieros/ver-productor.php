<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Database.php';

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = null;
if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) $token = $matches[1];

if (!$token) { http_response_code(401); echo json_encode(['success'=>false]); exit; }

$agricultorId = $_GET['id'] ?? null;
if (!$agricultorId) { echo json_encode(['success'=>false,'message'=>'ID requerido']); exit; }

try {
    $db = Database::getInstance();
    
    $stmt = $db->query("SELECT u.id FROM sesiones s JOIN usuarios u ON s.usuario_id=u.id WHERE s.token=? AND u.rol='ingeniero'",[$token]);
    $user = $stmt->fetch();
    if(!$user){ http_response_code(401); echo json_encode(['success'=>false]); exit; }
    
    // Verificar asignación
    $stmt = $db->query("SELECT id FROM asignaciones WHERE ingeniero_id=? AND agricultor_id=? AND estado='activo'",[$user['id'],$agricultorId]);
    if(!$stmt->fetch()){ http_response_code(403); echo json_encode(['success'=>false,'message'=>'No autorizado']); exit; }
    
    // Datos del productor
    $stmt = $db->query("SELECT id,nombre,telefono,correo FROM usuarios WHERE id=?",[$agricultorId]);
    $productor = $stmt->fetch();
    
    // Parcelas
    $stmt = $db->query("SELECT p.*, c.nombre_comun as cultivo_nombre, DATEDIFF(NOW(),p.fecha_siembra) as dias_desde_siembra, (SELECT e.tipo FROM eventos e WHERE e.parcela_id=p.id ORDER BY e.fecha_evento DESC LIMIT 1) as ultimo_evento_tipo FROM parcelas p LEFT JOIN cultivos_taxonomia c ON p.cultivo_id=c.id WHERE p.agricultor_id=? AND p.estado='activo'",[$agricultorId]);
    $parcelas = $stmt->fetchAll();
    
    foreach($parcelas as &$pa){
        $tipos=['riego'=>'💧 Regado','fertilizacion'=>'🌱 Fertilizado','plaga'=>'⚠️ Plaga','foto'=>'📸 Foto'];
        $pa['ultimo_evento'] = $tipos[$pa['ultimo_evento_tipo']] ?? 'Sin eventos';
    }
    
    echo json_encode(['success'=>true,'productor'=>$productor,'parcelas'=>$parcelas]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false]);
}
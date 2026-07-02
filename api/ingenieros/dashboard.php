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

if (!$token) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'No autorizado']); exit; }

try {
    $db = Database::getInstance();
    
    $stmt = $db->query("SELECT u.id FROM sesiones s JOIN usuarios u ON s.usuario_id=u.id WHERE s.token=? AND (s.expira IS NULL OR s.expira>NOW()) AND u.rol='ingeniero'",[$token]);
    $user = $stmt->fetch();
    if(!$user){ http_response_code(401); echo json_encode(['success'=>false]); exit; }
    
    $ingenieroId = $user['id'];
    
    // Estadísticas
    $stmt = $db->query("SELECT COUNT(*) as total FROM asignaciones WHERE ingeniero_id=? AND estado='activo'",[$ingenieroId]);
    $productores = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM parcelas p JOIN asignaciones a ON p.agricultor_id=a.agricultor_id WHERE a.ingeniero_id=? AND a.estado='activo' AND p.estado='activo'",[$ingenieroId]);
    $parcelas = $stmt->fetch()['total'];
    
    // Alertas de plagas recientes
    $stmt = $db->query("SELECT e.id, e.fecha_evento, e.datos, p.nombre as parcela, u.nombre as agricultor, u.id as agricultor_id FROM eventos e JOIN parcelas p ON e.parcela_id=p.id JOIN asignaciones a ON p.agricultor_id=a.agricultor_id JOIN usuarios u ON p.agricultor_id=u.id WHERE a.ingeniero_id=? AND a.estado='activo' AND e.tipo='plaga' ORDER BY e.fecha_evento DESC LIMIT 10",[$ingenieroId]);
    $alertas = $stmt->fetchAll();
    
    $alertasFormateadas = [];
    foreach($alertas as $a){
        $datos = json_decode($a['datos'], true);
        $alertasFormateadas[] = [
            'agricultor' => $a['agricultor'],
            'agricultor_id' => $a['agricultor_id'],
            'parcela' => $a['parcela'],
            'severidad' => $datos['severidad'] ?? 'No especificada',
            'descripcion' => $datos['descripcion'] ?? '',
            'fecha' => date('d/m/Y', strtotime($a['fecha_evento']))
        ];
    }
    
    // Productores asignados
    $stmt = $db->query("SELECT u.id, u.nombre, (SELECT COUNT(*) FROM parcelas WHERE agricultor_id=u.id AND estado='activo') as parcelas FROM asignaciones a JOIN usuarios u ON a.agricultor_id=u.id WHERE a.ingeniero_id=? AND a.estado='activo'",[$ingenieroId]);
    $productoresLista = $stmt->fetchAll();
    
    // Solicitudes pendientes
    $stmt = $db->query("SELECT COUNT(*) as total FROM asignaciones WHERE ingeniero_id=? AND estado='pendiente'",[$ingenieroId]);
    $solicitudesPendientes = $stmt->fetch()['total'];
    
    echo json_encode([
        'success' => true,
        'stats' => ['productores' => (int)$productores, 'parcelas' => (int)$parcelas, 'alertas' => count($alertasFormateadas)],
        'alertas' => $alertasFormateadas,
        'productores' => $productoresLista,
        'solicitudes_pendientes' => (int)$solicitudesPendientes
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error del servidor']);
}
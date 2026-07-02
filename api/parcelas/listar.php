<?php
/**
 * API Listar Parcelas del Agricultor
 * Endpoint: GET /api/parcelas/listar.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Database.php';

// Obtener token
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = null;

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Verificar token y obtener usuario
    $stmt = $db->query(
        "SELECT u.id, u.rol 
         FROM sesiones s 
         JOIN usuarios u ON s.usuario_id = u.id 
         WHERE s.token = ? AND s.expira > NOW() AND u.estado = 'activo'",
        [$token]
    );
    
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
        exit;
    }
    
    // Verificar que sea agricultor
    if ($user['rol'] !== 'agricultor') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }
    
    // Obtener parcelas del agricultor
    $stmt = $db->query(
        "SELECT p.*, 
                c.nombre_comun as cultivo_nombre,
                DATEDIFF(NOW(), p.fecha_siembra) as dias_desde_siembra,
                (SELECT e.tipo FROM eventos e 
                 WHERE e.parcela_id = p.id 
                 ORDER BY e.fecha_evento DESC LIMIT 1) as ultimo_evento_tipo,
                (SELECT e.fecha_evento FROM eventos e 
                 WHERE e.parcela_id = p.id 
                 ORDER BY e.fecha_evento DESC LIMIT 1) as ultimo_evento_fecha
         FROM parcelas p
         LEFT JOIN cultivos_taxonomia c ON p.cultivo_id = c.id
         WHERE p.agricultor_id = ? AND p.estado = 'activo'
         ORDER BY p.fecha_creacion DESC",
        [$user['id']]
    );
    
    $parcelas = $stmt->fetchAll();
    
    // Formatear datos para el frontend
    foreach ($parcelas as &$parcela) {
        $parcela['estado'] = 'saludable'; // Se puede calcular según eventos recientes
        
        // Formatear último evento
        if ($parcela['ultimo_evento_tipo']) {
            $tipos = [
                'riego' => '💧 Regado',
                'fertilizacion' => '🌱 Fertilizado',
                'plaga' => '⚠️ Plaga reportada',
                'foto' => '📸 Foto tomada'
            ];
            $parcela['ultimo_evento'] = $tipos[$parcela['ultimo_evento_tipo']] ?? 'Evento registrado';
            
            // Calcular tiempo relativo
            $fecha = new DateTime($parcela['ultimo_evento_fecha']);
            $ahora = new DateTime();
            $intervalo = $fecha->diff($ahora);
            
            if ($intervalo->d == 0) {
                $parcela['ultimo_evento'] .= ' hoy';
            } elseif ($intervalo->d == 1) {
                $parcela['ultimo_evento'] .= ' ayer';
            } else {
                $parcela['ultimo_evento'] .= ' hace ' . $intervalo->d . ' días';
            }
        } else {
            $parcela['ultimo_evento'] = 'Sin eventos';
        }
    }
    
    echo json_encode([
        'success' => true,
        'parcelas' => $parcelas,
        'total' => count($parcelas)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor: ' . (DEBUG_MODE ? $e->getMessage() : 'Intente más tarde')
    ]);
}
<?php
/**
 * API Estadísticas del Dashboard
 * Endpoint: GET /api/agricultor/stats.php
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
         WHERE s.token = ? AND s.expira > NOW()",
        [$token]
    );
    
    $user = $stmt->fetch();
    
    if (!$user || $user['rol'] !== 'agricultor') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }
    
    $usuarioId = $user['id'];
    
    // Estadísticas de hoy
    $stmt = $db->query(
        "SELECT 
            SUM(CASE WHEN e.tipo = 'riego' THEN 1 ELSE 0 END) as riegos,
            SUM(CASE WHEN e.tipo = 'fertilizacion' THEN 1 ELSE 0 END) as fertilizaciones,
            SUM(CASE WHEN e.tipo = 'plaga' THEN 1 ELSE 0 END) as plagas,
            SUM(CASE WHEN e.tipo = 'foto' THEN 1 ELSE 0 END) as fotos,
            COUNT(*) as total
         FROM eventos e
         JOIN parcelas p ON e.parcela_id = p.id
         WHERE p.agricultor_id = ? AND DATE(e.fecha_evento) = CURDATE()",
        [$usuarioId]
    );
    
    $statsHoy = $stmt->fetch();
    
    // Total de parcelas activas
    $stmt = $db->query(
        "SELECT COUNT(*) as total FROM parcelas WHERE agricultor_id = ? AND estado = 'activo'",
        [$usuarioId]
    );
    $totalParcelas = $stmt->fetch()['total'];
    
    // Próximas tareas (riego programado)
    $stmt = $db->query(
        "SELECT p.nombre as parcela, 
                DATE_ADD(MAX(e.fecha_evento), INTERVAL 3 DAY) as proximo_riego
         FROM parcelas p
         LEFT JOIN eventos e ON p.id = e.parcela_id AND e.tipo = 'riego'
         WHERE p.agricultor_id = ? AND p.estado = 'activo'
         GROUP BY p.id
         HAVING proximo_riego >= CURDATE()
         ORDER BY proximo_riego
         LIMIT 5",
        [$usuarioId]
    );
    
    $tareas = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'riegos_hoy' => (int)($statsHoy['riegos'] ?? 0),
            'fertilizaciones_hoy' => (int)($statsHoy['fertilizaciones'] ?? 0),
            'plagas_hoy' => (int)($statsHoy['plagas'] ?? 0),
            'fotos_hoy' => (int)($statsHoy['fotos'] ?? 0),
            'total_parcelas' => (int)$totalParcelas
        ],
        'tareas' => $tareas
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor'
    ]);
}
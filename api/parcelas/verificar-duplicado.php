<?php
/**
 * API Verificar Duplicado de Parcela - VERSIÓN MEJORADA
 * Permite parcelas cercanas si son del mismo agricultor o cultivos diferentes
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/funciones.php';

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = null;

if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
    $token = $matches[1];
}

// Obtener usuario actual
$usuarioId = null;
if ($token) {
    try {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT u.id FROM sesiones s JOIN usuarios u ON s.usuario_id = u.id 
             WHERE s.token = ? AND (s.expira IS NULL OR s.expira > NOW())",
            [$token]
        );
        $user = $stmt->fetch();
        $usuarioId = $user['id'] ?? null;
    } catch (Exception $e) {
        // Continuar sin usuario
    }
}

$input = json_decode(file_get_contents('php://input'), true);
$latitud = $input['latitud'] ?? null;
$longitud = $input['longitud'] ?? null;
$cultivo_id = $input['cultivo_id'] ?? null;
$nombre_parcela = $input['nombre'] ?? '';

if (!$latitud || !$longitud) {
    echo json_encode(['duplicado' => false]);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Buscar parcelas en un radio de 30 metros (reducido de 50m)
    $stmt = $db->query(
        "SELECT p.id, p.nombre, p.agricultor_id, p.cultivo_id, u.nombre as agricultor_nombre,
                c.nombre_comun as cultivo_nombre,
                (6371000 * acos(cos(radians(?)) * cos(radians(p.latitud)) * 
                cos(radians(p.longitud) - radians(?)) + sin(radians(?)) * 
                sin(radians(p.latitud)))) AS distancia
         FROM parcelas p
         JOIN usuarios u ON p.agricultor_id = u.id
         LEFT JOIN cultivos_taxonomia c ON p.cultivo_id = c.id
         WHERE p.estado = 'activo'
         HAVING distancia < 30
         ORDER BY distancia
         LIMIT 5",
        [$latitud, $longitud, $latitud]
    );
    
    $parcelasCercanas = $stmt->fetchAll();
    
    if (empty($parcelasCercanas)) {
        echo json_encode(['duplicado' => false]);
        exit;
    }
    
    // Analizar cada parcela cercana
    $parcelaMasCercana = $parcelasCercanas[0];
    $distancia = round($parcelaMasCercana['distancia']);
    
    // CASO 1: Es el mismo agricultor
    if ($usuarioId && $parcelaMasCercana['agricultor_id'] == $usuarioId) {
        echo json_encode([
            'duplicado' => false, // Permitir - mismo agricultor puede tener parcelas juntas
            'advertencia' => true,
            'mensaje' => "Ya tienes una parcela a {$distancia} metros. ¿Son parcelas diferentes?",
            'parcela_existente' => $parcelaMasCercana['nombre']
        ]);
        exit;
    }
    
    // CASO 2: Es otro agricultor
    if ($parcelaMasCercana['agricultor_id'] != $usuarioId) {
        echo json_encode([
            'duplicado' => true,
            'tipo' => 'otro_agricultor',
            'parcela' => $parcelaMasCercana['nombre'],
            'agricultor' => $parcelaMasCercana['agricultor_nombre'],
            'distancia' => $distancia . ' metros',
            'mensaje' => "Ya existe una parcela de otro agricultor a {$distancia} metros. ¿Es un terreno compartido o hay algún error?"
        ]);
        exit;
    }
    
    // CASO 3: Muy cerca (menos de 5 metros) - probable duplicado real
    if ($distancia < 5) {
        echo json_encode([
            'duplicado' => true,
            'tipo' => 'muy_cerca',
            'parcela' => $parcelaMasCercana['nombre'],
            'distancia' => $distancia . ' metros',
            'mensaje' => "Hay una parcela a solo {$distancia} metros. ¿Estás seguro que es una parcela diferente?"
        ]);
        exit;
    }
    
    // Por defecto, permitir con advertencia
    echo json_encode([
        'duplicado' => false,
        'advertencia' => true,
        'parcelas_cercanas' => count($parcelasCercanas),
        'mensaje' => "Hay parcelas cercanas. Verifica que no sea un duplicado."
    ]);
    
} catch (Exception $e) {
    echo json_encode(['duplicado' => false, 'error' => $e->getMessage()]);
}
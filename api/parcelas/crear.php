<?php
/**
 * API Crear Parcela
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

if (!$token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Verificar token
    $stmt = $db->query(
        "SELECT u.id FROM sesiones s JOIN usuarios u ON s.usuario_id = u.id 
         WHERE s.token = ? AND (s.expira IS NULL OR s.expira > NOW()) AND u.rol = 'agricultor'",
        [$token]
    );
    
    $user = $stmt->fetch();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $nombre = trim($input['nombre'] ?? '');
    $cultivo_id = $input['cultivo_id'] ?? 1;
    $variedad = $input['variedad'] ?? 'Blanco Criollo';
    $fecha_siembra = $input['fecha_siembra'] ?? date('Y-m-d');
    $latitud = $input['latitud'] ?? null;
    $longitud = $input['longitud'] ?? null;
    $notas = trim($input['notas'] ?? '');
    $fotoBase64 = $input['foto_principal'] ?? null;
    
    if (!$nombre || !$latitud || !$longitud) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }
    
    // Procesar foto
    $fotoPath = null;
    if ($fotoBase64 && strpos($fotoBase64, 'data:image') === 0) {
        $fotoPath = guardarImagenBase64($fotoBase64, 'parcelas/');
    }
    
    $db->getConnection()->beginTransaction();
    
    $db->query(
        "INSERT INTO parcelas (agricultor_id, nombre, cultivo_id, variedad, fecha_siembra, latitud, longitud, notas, foto_principal) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [$user['id'], $nombre, $cultivo_id, $variedad, $fecha_siembra, $latitud, $longitud, $notas, $fotoPath]
    );
    
    $parcelaId = $db->lastInsertId();
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Parcela creada exitosamente',
        'parcela' => [
            'id' => $parcelaId,
            'nombre' => $nombre,
            'cultivo_id' => $cultivo_id,
            'variedad' => $variedad,
            'fecha_siembra' => $fecha_siembra,
            'latitud' => $latitud,
            'longitud' => $longitud,
            'foto_principal' => $fotoPath
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($db)) $db->getConnection()->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al crear la parcela']);
}
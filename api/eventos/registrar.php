<?php
/**
 * API Registrar Evento (Riego, Plaga, Fertilización, etc.)
 * Endpoint: POST /api/eventos/registrar.php
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

// Autenticación
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
        "SELECT u.id, u.rol FROM sesiones s 
         JOIN usuarios u ON s.usuario_id = u.id 
         WHERE s.token = ? AND (s.expira IS NULL OR s.expira > NOW())",
        [$token]
    );
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
        exit;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $parcelaId = $input['parcela_id'] ?? null;
    $tipo = $input['tipo'] ?? null;
    $datosJson = $input['datos'] ?? '{}';
    $foto1 = $input['foto1'] ?? null;
    $foto2 = $input['foto2'] ?? null;
    $foto = $input['foto'] ?? null; // Para riego (una sola foto)
    
    // Validar
    if (!$parcelaId || !$tipo) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        exit;
    }
    
    $tiposPermitidos = ['riego', 'plaga', 'fertilizacion', 'poda', 'medicion', 'foto'];
    if (!in_array($tipo, $tiposPermitidos)) {
        echo json_encode(['success' => false, 'message' => 'Tipo de evento no válido']);
        exit;
    }
    
    // Verificar que la parcela pertenezca al usuario
    $stmt = $db->query(
        "SELECT id, agricultor_id FROM parcelas WHERE id = ? AND estado = 'activo'",
        [$parcelaId]
    );
    $parcela = $stmt->fetch();
    
    if (!$parcela || $parcela['agricultor_id'] != $user['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tienes permiso']);
        exit;
    }
    
    // Iniciar transacción
    $db->getConnection()->beginTransaction();
    
    // Insertar evento
    $db->query(
        "INSERT INTO eventos (parcela_id, tipo, datos, fecha_evento) VALUES (?, ?, ?, NOW())",
        [$parcelaId, $tipo, $datosJson]
    );
    $eventoId = $db->lastInsertId();
    
    // Procesar fotos
    $fotos = array_filter([$foto, $foto1, $foto2]);
    foreach ($fotos as $fotoBase64) {
        if ($fotoBase64 && strpos($fotoBase64, 'data:image') === 0) {
            $fotoPath = guardarImagenBase64($fotoBase64, 'eventos/');
            if ($fotoPath) {
                $db->query(
                    "INSERT INTO fotos (evento_id, parcela_id, usuario_id, url) VALUES (?, ?, ?, ?)",
                    [$eventoId, $parcelaId, $user['id'], $fotoPath]
                );
            }
        }
    }
    
    // Si es plaga y se solicitó notificar al ingeniero
    if ($tipo === 'plaga') {
        $datos = json_decode($datosJson, true);
        if (!empty($datos['notificar'])) {
            // Buscar ingeniero asignado
            $stmt = $db->query(
                "SELECT a.ingeniero_id, u.nombre as agricultor_nombre, p.nombre as parcela_nombre
                 FROM asignaciones a
                 JOIN usuarios u ON a.agricultor_id = u.id
                 JOIN parcelas p ON p.id = ?
                 WHERE a.agricultor_id = ? AND a.estado = 'activo'
                 LIMIT 1",
                [$parcelaId, $user['id']]
            );
            $asignacion = $stmt->fetch();
            
            if ($asignacion) {
                // Crear notificación para el ingeniero
                $db->query(
                    "INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, datos) 
                     VALUES (?, ?, ?, 'plaga', ?)",
                    [
                        $asignacion['ingeniero_id'],
                        'Alerta de plaga',
                        "{$asignacion['agricultor_nombre']} reportó una plaga en {$asignacion['parcela_nombre']}",
                        json_encode([
                            'evento_id' => $eventoId,
                            'parcela_id' => $parcelaId,
                            'severidad' => $datos['severidad'] ?? 'No especificada'
                        ])
                    ]
                );
            }
        }
    }
    
    $db->getConnection()->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Evento registrado correctamente',
        'evento_id' => $eventoId
    ]);
    
} catch (Exception $e) {
    if (isset($db)) $db->getConnection()->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . (DEBUG_MODE ? $e->getMessage() : 'Intente más tarde')
    ]);
}
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar token
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    
    if (!$token) {
        echo json_encode(['success' => false, 'message' => 'Token requerido']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT usuario_id FROM sesiones WHERE token = ? AND expira > NOW()");
    $stmt->execute([$token]);
    $sesion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sesion) {
        echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
        exit;
    }
    
    // Verificar que sea admin
    $stmt = $db->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $stmt->execute([$sesion['usuario_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario['rol'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    // GET - Listar asignaciones, ingenieros y agricultores
    if ($method === 'GET') {
        // Obtener ingenieros
        $stmt = $db->query("SELECT u.id, u.nombre FROM usuarios u JOIN ingenieros i ON u.id = i.usuario_id WHERE u.estado = 'activo'");
        $ingenieros = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener agricultores
        $stmt = $db->query("SELECT id, nombre FROM usuarios WHERE rol = 'agricultor' AND estado = 'activo'");
        $agricultores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener asignaciones
        $stmt = $db->query("
            SELECT a.*, 
                   ing.nombre as ingeniero, 
                   agr.nombre as agricultor
            FROM asignaciones a
            JOIN usuarios ing ON a.ingeniero_id = ing.id
            JOIN usuarios agr ON a.agricultor_id = agr.id
            ORDER BY a.fecha_solicitud DESC
        ");
        $asignaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'ingenieros' => $ingenieros,
            'agricultores' => $agricultores,
            'asignaciones' => $asignaciones
        ]);
        exit;
    }
    
    // POST - Crear asignación
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $ingenieroId = $input['ingeniero_id'] ?? 0;
        $agricultorId = $input['agricultor_id'] ?? 0;
        
        if (!$ingenieroId || !$agricultorId) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        
        // Verificar si ya existe una asignación activa
        $stmt = $db->prepare("
            SELECT id FROM asignaciones 
            WHERE ingeniero_id = ? AND agricultor_id = ? AND estado IN ('activo', 'pendiente')
        ");
        $stmt->execute([$ingenieroId, $agricultorId]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe una asignación pendiente o activa']);
            exit;
        }
        
        $stmt = $db->prepare("
            INSERT INTO asignaciones (ingeniero_id, agricultor_id, estado, fecha_solicitud)
            VALUES (?, ?, 'pendiente', NOW())
        ");
        $stmt->execute([$ingenieroId, $agricultorId]);
        
        echo json_encode(['success' => true, 'message' => 'Asignación creada', 'id' => $db->lastInsertId()]);
        exit;
    }
    
    // PUT - Actualizar estado de asignación (aceptar/rechazar)
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = $input['id'] ?? 0;
        $estado = $input['estado'] ?? '';
        
        if (!$id || !in_array($estado, ['activo', 'rechazado'])) {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
            exit;
        }
        
        $stmt = $db->prepare("
            UPDATE asignaciones 
            SET estado = ?, fecha_respuesta = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$estado, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
        exit;
    }
    
    // DELETE - Eliminar asignación
    if ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? 0;
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID requerido']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM asignaciones WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Asignación eliminada']);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error DB: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
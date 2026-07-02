<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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
    
    // GET - Obtener ingenieros pendientes
    if ($method === 'GET') {
        $stmt = $db->query("
            SELECT i.id, u.nombre, u.telefono, i.cedula_profesional as cedula, u.fecha_registro as fecha
            FROM ingenieros i
            JOIN usuarios u ON i.usuario_id = u.id
            WHERE i.validado_por IS NULL
            ORDER BY u.fecha_registro ASC
        ");
        $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'pendientes' => $pendientes
        ]);
        exit;
    }
    
    // POST - Aprobar o rechazar
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = $input['id'] ?? 0;
        $accion = $input['accion'] ?? '';
        $motivo = $input['motivo'] ?? '';
        
        if (!$id || !$accion) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        
        if ($accion === 'aprobar') {
            $stmt = $db->prepare("
                UPDATE ingenieros 
                SET validado_por = ?, fecha_validacion = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$sesion['usuario_id'], $id]);
            
            // Registrar log
            $stmt = $db->prepare("
                INSERT INTO logs_auditoria (usuario_id, accion, detalle) 
                VALUES (?, 'validacion_aprobada', ?)
            ");
            $stmt->execute([$sesion['usuario_id'], "Ingeniero ID: $id aprobado"]);
            
            echo json_encode(['success' => true, 'message' => 'Validación aprobada']);
            
        } elseif ($accion === 'rechazar') {
            // Opcional: eliminar o marcar como rechazado
            // Por ahora solo registramos el rechazo
            $stmt = $db->prepare("
                INSERT INTO logs_auditoria (usuario_id, accion, detalle) 
                VALUES (?, 'validacion_rechazada', ?)
            ");
            $detalle = "Ingeniero ID: $id rechazado. Motivo: " . ($motivo ?: 'No especificado');
            $stmt->execute([$sesion['usuario_id'], $detalle]);
            
            echo json_encode(['success' => true, 'message' => 'Validación rechazada']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error DB: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
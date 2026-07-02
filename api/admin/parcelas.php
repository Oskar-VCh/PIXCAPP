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
    
    // GET - Listar parcelas
    if ($method === 'GET') {
        $search = $_GET['search'] ?? '';
        $estado = $_GET['estado'] ?? '';
        
        $where = [];
        $params = [];
        
        if ($search) {
            $where[] = "(p.nombre LIKE ? OR u.nombre LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($estado) {
            $where[] = "p.estado = ?";
            $params[] = $estado;
        }
        
        $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);
        
        $query = "
            SELECT p.*, u.nombre as agricultor_nombre, c.nombre_comun as cultivo
            FROM parcelas p
            JOIN usuarios u ON p.agricultor_id = u.id
            LEFT JOIN cultivos_taxonomia c ON p.cultivo_id = c.id
            $whereClause
            ORDER BY p.id DESC
        ";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $parcelas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Estadísticas
        $total = count($parcelas);
        $activas = count(array_filter($parcelas, fn($p) => $p['estado'] === 'activo'));
        $inactivas = count(array_filter($parcelas, fn($p) => $p['estado'] === 'inactivo' || $p['estado'] === 'cosechado'));
        $atencion = count(array_filter($parcelas, fn($p) => $p['estado'] === 'atencion'));
        
        echo json_encode([
            'success' => true,
            'parcelas' => $parcelas,
            'stats' => [
                'total' => $total,
                'activas' => $activas,
                'inactivas' => $inactivas,
                'atencion' => $atencion
            ]
        ]);
        exit;
    }
    
    // POST - Crear parcela
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $nombre = trim($input['nombre'] ?? '');
        $estado = $input['estado'] ?? 'activo';
        
        if (empty($nombre)) {
            echo json_encode(['success' => false, 'message' => 'El nombre es requerido']);
            exit;
        }
        
        // Obtener un agricultor de ejemplo (el primero)
        $stmt = $db->query("SELECT id FROM usuarios WHERE rol = 'agricultor' LIMIT 1");
        $agricultor = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$agricultor) {
            echo json_encode(['success' => false, 'message' => 'No hay agricultores registrados']);
            exit;
        }
        
        $stmt = $db->prepare("
            INSERT INTO parcelas (agricultor_id, nombre, estado, latitud, longitud)
            VALUES (?, ?, ?, 0, 0)
        ");
        $stmt->execute([$agricultor['id'], $nombre, $estado]);
        
        echo json_encode(['success' => true, 'message' => 'Parcela creada', 'id' => $db->lastInsertId()]);
        exit;
    }
    
    // PUT - Actualizar parcela
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = $input['id'] ?? 0;
        $nombre = trim($input['nombre'] ?? '');
        $estado = $input['estado'] ?? 'activo';
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID requerido']);
            exit;
        }
        
        if (empty($nombre)) {
            echo json_encode(['success' => false, 'message' => 'El nombre es requerido']);
            exit;
        }
        
        $stmt = $db->prepare("UPDATE parcelas SET nombre = ?, estado = ? WHERE id = ?");
        $stmt->execute([$nombre, $estado, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Parcela actualizada']);
        exit;
    }
    
    // DELETE - Eliminar parcela
    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID requerido']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM parcelas WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Parcela eliminada']);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error DB: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
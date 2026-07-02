<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
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
    
    // GET - Listar usuarios
    if ($method === 'GET') {
        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)($_GET['perPage'] ?? 15);
        $search = $_GET['search'] ?? '';
        $rol = $_GET['rol'] ?? '';
        $estado = $_GET['estado'] ?? '';
        
        $offset = ($page - 1) * $perPage;
        
        $where = [];
        $params = [];
        
        if ($search) {
            $where[] = "(nombre LIKE ? OR telefono LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($rol) {
            $where[] = "rol = ?";
            $params[] = $rol;
        }
        if ($estado) {
            $where[] = "estado = ?";
            $params[] = $estado;
        }
        
        $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);
        
        // Obtener total
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM usuarios $whereClause");
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Obtener usuarios
        $stmt = $db->prepare("
            SELECT id, nombre, telefono, correo, rol, estado, fecha_registro
            FROM usuarios
            $whereClause
            ORDER BY id DESC
            LIMIT ? OFFSET ?
        ");
        $params[] = $perPage;
        $params[] = $offset;
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'total' => (int)$total,
            'page' => $page,
            'perPage' => $perPage
        ]);
        exit;
    }
    
    // POST - Crear usuario
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $nombre = trim($input['nombre'] ?? '');
        $telefono = trim($input['telefono'] ?? '');
        $correo = trim($input['correo'] ?? '');
        $rol = $input['rol'] ?? 'agricultor';
        $estado = $input['estado'] ?? 'activo';
        $password = $input['password'] ?? '';
        
        if (empty($nombre) || empty($telefono)) {
            echo json_encode(['success' => false, 'message' => 'Nombre y teléfono son requeridos']);
            exit;
        }
        
        if (empty($password)) {
            $password = substr($telefono, -6);
        }
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("
            INSERT INTO usuarios (nombre, telefono, correo, password_hash, rol, estado)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nombre, $telefono, $correo, $hash, $rol, $estado]);
        
        echo json_encode(['success' => true, 'message' => 'Usuario creado', 'id' => $db->lastInsertId()]);
        exit;
    }
    
    // PUT - Actualizar usuario
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = $input['id'] ?? 0;
        $nombre = trim($input['nombre'] ?? '');
        $telefono = trim($input['telefono'] ?? '');
        $correo = trim($input['correo'] ?? '');
        $rol = $input['rol'] ?? 'agricultor';
        $estado = $input['estado'] ?? 'activo';
        $password = $input['password'] ?? '';
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID requerido']);
            exit;
        }
        
        if (empty($nombre) || empty($telefono)) {
            echo json_encode(['success' => false, 'message' => 'Nombre y teléfono son requeridos']);
            exit;
        }
        
        $updates = ["nombre = ?", "telefono = ?", "correo = ?", "rol = ?", "estado = ?"];
        $params = [$nombre, $telefono, $correo, $rol, $estado];
        
        if (!empty($password)) {
            $updates[] = "password_hash = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        $params[] = $id;
        $stmt = $db->prepare("UPDATE usuarios SET " . implode(", ", $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'message' => 'Usuario actualizado']);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error DB: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
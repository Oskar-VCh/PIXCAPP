<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    
    // Si se pide un log específico
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $db->prepare("
            SELECT l.*, u.nombre as usuario_nombre 
            FROM logs_auditoria l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            WHERE l.id = ?
        ");
        $stmt->execute([$id]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'log' => $log]);
        exit;
    }
    
    // Parámetros de filtrado
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['perPage'] ?? 20);
    $search = $_GET['search'] ?? '';
    $accion = $_GET['accion'] ?? '';
    $fechaInicio = $_GET['fechaInicio'] ?? '';
    $fechaFin = $_GET['fechaFin'] ?? '';
    
    $offset = ($page - 1) * $perPage;
    
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(u.nombre LIKE ? OR l.detalle LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($accion) {
        $where[] = "l.accion = ?";
        $params[] = $accion;
    }
    
    if ($fechaInicio) {
        $where[] = "l.fecha >= ?";
        $params[] = $fechaInicio . " 00:00:00";
    }
    
    if ($fechaFin) {
        $where[] = "l.fecha <= ?";
        $params[] = $fechaFin . " 23:59:59";
    }
    
    $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);
    
    // Obtener total de registros
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM logs_auditoria l
        LEFT JOIN usuarios u ON l.usuario_id = u.id
        $whereClause
    ";
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Obtener logs
    $query = "
        SELECT l.*, u.nombre as usuario_nombre 
        FROM logs_auditoria l
        LEFT JOIN usuarios u ON l.usuario_id = u.id
        $whereClause
        ORDER BY l.fecha DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $perPage;
    $params[] = $offset;
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas
    // Total hoy
    $stmt = $db->query("SELECT COUNT(*) as total FROM logs_auditoria WHERE DATE(fecha) = CURDATE()");
    $hoy = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total esta semana
    $stmt = $db->query("SELECT COUNT(*) as total FROM logs_auditoria WHERE YEARWEEK(fecha) = YEARWEEK(CURDATE())");
    $semana = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Usuarios con actividad
    $stmt = $db->query("SELECT COUNT(DISTINCT usuario_id) as total FROM logs_auditoria WHERE usuario_id IS NOT NULL");
    $usuariosActivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'total' => (int)$total,
        'page' => $page,
        'perPage' => $perPage,
        'stats' => [
            'total' => (int)$total,
            'hoy' => (int)$hoy,
            'semana' => (int)$semana,
            'usuarios_activos' => (int)$usuariosActivos
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error DB: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
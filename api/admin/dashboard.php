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
    
    // Verificar rol admin
    $stmt = $db->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $stmt->execute([$sesion['usuario_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario['rol'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }
    
    // ============================================
    // ESTADÍSTICAS GENERALES
    // ============================================
    $stats = [];
    
    // Total usuarios
    $stmt = $db->query("SELECT COUNT(*) as total FROM usuarios");
    $stats['usuarios'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Total parcelas
    $stmt = $db->query("SELECT COUNT(*) as total FROM parcelas");
    $stats['parcelas'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pendientes de validación
    $stmt = $db->query("SELECT COUNT(*) as total FROM ingenieros WHERE validado_por IS NULL");
    $stats['pendientes'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Eventos este mes
    $stmt = $db->query("SELECT COUNT(*) as total FROM eventos WHERE MONTH(fecha_evento) = MONTH(CURDATE()) AND YEAR(fecha_evento) = YEAR(CURDATE())");
    $stats['eventos_mes'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // ============================================
    // USUARIOS POR MES (CORREGIDO - SIN GROUP BY PROBLEMÁTICO)
    // ============================================
    $usuarios_por_mes = [];
    $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $mesActual = (int)date('m');
    
    // Obtener últimos 6 meses
    for ($i = 5; $i >= 0; $i--) {
        $mesNum = $mesActual - $i;
        $anio = date('Y');
        if ($mesNum <= 0) {
            $mesNum += 12;
            $anio -= 1;
        }
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM usuarios 
            WHERE MONTH(fecha_registro) = ? AND YEAR(fecha_registro) = ?
        ");
        $stmt->execute([$mesNum, $anio]);
        $total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $usuarios_por_mes[] = [
            'mes' => $meses[$mesNum - 1],
            'total' => $total
        ];
    }
    
    // ============================================
    // DISTRIBUCIÓN POR ROL
    // ============================================
    $stmt = $db->query("SELECT rol, COUNT(*) as total FROM usuarios GROUP BY rol");
    $roles_distribucion = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // EVENTOS POR TIPO (ÚLTIMOS 30 DÍAS)
    // ============================================
    $stmt = $db->query("
        SELECT tipo, COUNT(*) as total
        FROM eventos
        WHERE fecha_evento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY tipo
    ");
    $eventos_por_tipo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // ACTIVIDAD SEMANAL
    // ============================================
    $dias = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    $numDias = [2, 3, 4, 5, 6, 7, 1];
    $actividad_semanal = [];
    
    for ($i = 0; $i < 7; $i++) {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM eventos
            WHERE DAYOFWEEK(fecha_evento) = ?
            AND fecha_evento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$numDias[$i]]);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $actividad_semanal[] = ['dia' => $dias[$i], 'total' => (int)$total];
    }
    
    // ============================================
    // INGENIEROS PENDIENTES (DETALLE)
    // ============================================
    $stmt = $db->query("
        SELECT i.id, u.nombre, u.telefono, i.cedula_profesional as cedula, u.fecha_registro as fecha
        FROM ingenieros i
        JOIN usuarios u ON i.usuario_id = u.id
        WHERE i.validado_por IS NULL
        ORDER BY u.fecha_registro DESC
        LIMIT 10
    ");
    $pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // ÚLTIMOS USUARIOS REGISTRADOS
    // ============================================
    $stmt = $db->query("
        SELECT nombre, rol, fecha_registro as fecha
        FROM usuarios
        ORDER BY fecha_registro DESC
        LIMIT 10
    ");
    $ultimos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // RESPUESTA
    // ============================================
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'usuarios_por_mes' => $usuarios_por_mes,
        'roles_distribucion' => $roles_distribucion,
        'eventos_por_tipo' => $eventos_por_tipo,
        'actividad_semanal' => $actividad_semanal,
        'pendientes' => $pendientes,
        'ultimos' => $ultimos
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error DB: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
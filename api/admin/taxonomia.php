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
    
    // Verificar el rol del usuario
    $stmt = $db->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $stmt->execute([$sesion['usuario_id']]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // CAMBIO CRUCIAL: Ahora se permite el acceso tanto a 'admin' como a 'ingeniero'
    if ($usuario['rol'] !== 'admin' && $usuario['rol'] !== 'ingeniero') {
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    // GET - Listar cultivos (Permitido para Admin e Ingeniero)
    if ($method === 'GET') {
        $search = $_GET['search'] ?? '';
        
        $where = "";
        $params = [];
        
        if ($search) {
            $where = "WHERE nombre_comun LIKE ? OR nombre_cientifico LIKE ?";
            $params = ["%$search%", "%$search%"];
        }
        
        $query = "SELECT * FROM cultivos_taxonomia $where ORDER BY nombre_comun ASC";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $cultivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Estadísticas
        $total = count($cultivos);
        $activos = count(array_filter($cultivos, fn($c) => $c['estado'] === 'activo'));
        $familias = count(array_unique(array_filter(array_column($cultivos, 'familia'))));
        
        echo json_encode([
            'success' => true,
            'cultivos' => $cultivos,
            'stats' => [
                'total' => $total,
                'activos' => $activos,
                'familias' => $familias
            ]
        ]);
        exit;
    }
    
    // POST - Crear cultivo (Permitido para Admin e Ingeniero)
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $nombreComun = trim($input['nombre_comun'] ?? '');
        $nombreCientifico = trim($input['nombre_cientifico'] ?? '');
        
        if (empty($nombreComun)) {
            echo json_encode(['success' => false, 'message' => 'El nombre común es requerido']);
            exit;
        }
        
        $stmt = $db->prepare("
            INSERT INTO cultivos_taxonomia (
                nombre_comun, nombre_cientifico, reino, division, clase, orden, 
                familia, genero, especie, estado, ph_min, ph_max, temp_min, temp_max,
                altitud_min, altitud_max, precipitacion_min, precipitacion_max
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $nombreComun,
            $nombreCientifico,
            $input['reino'] ?? null,
            $input['division'] ?? null,
            $input['clase'] ?? null,
            $input['orden'] ?? null,
            $input['familia'] ?? null,
            $input['genero'] ?? null,
            $input['especie'] ?? null,
            $input['estado'] ?? 'activo',
            $input['ph_min'] ?? null,
            $input['ph_max'] ?? null,
            $input['temp_min'] ?? null,
            $input['temp_max'] ?? null,
            $input['altitud_min'] ?? null,
            $input['altitud_max'] ?? null,
            $input['precipitacion_min'] ?? null,
            $input['precipitacion_max'] ?? null
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Cultivo creado', 'id' => $db->lastInsertId()]);
        exit;
    }
    
    // PUT - Actualizar cultivo (Permitido para Admin e Ingeniero)
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = $input['id'] ?? 0;
        $nombreComun = trim($input['nombre_comun'] ?? '');
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID requerido']);
            exit;
        }
        
        if (empty($nombreComun)) {
            echo json_encode(['success' => false, 'message' => 'El nombre común es requerido']);
            exit;
        }
        
        $stmt = $db->prepare("
            UPDATE cultivos_taxonomia SET
                nombre_comun = ?,
                nombre_cientifico = ?,
                reino = ?,
                division = ?,
                clase = ?,
                orden = ?,
                familia = ?,
                genero = ?,
                especie = ?,
                estado = ?,
                ph_min = ?,
                ph_max = ?,
                temp_min = ?,
                temp_max = ?,
                altitud_min = ?,
                altitud_max = ?,
                precipitacion_min = ?,
                precipitacion_max = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $nombreComun,
            $input['nombre_cientifico'] ?? null,
            $input['reino'] ?? null,
            $input['division'] ?? null,
            $input['clase'] ?? null,
            $input['orden'] ?? null,
            $input['familia'] ?? null,
            $input['genero'] ?? null,
            $input['especie'] ?? null,
            $input['estado'] ?? 'activo',
            $input['ph_min'] ?? null,
            $input['ph_max'] ?? null,
            $input['temp_min'] ?? null,
            $input['temp_max'] ?? null,
            $input['altitud_min'] ?? null,
            $input['altitud_max'] ?? null,
            $input['precipitacion_min'] ?? null,
            $input['precipitacion_max'] ?? null,
            $id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Cultivo actualizado']);
        exit;
    }
    
    // DELETE - Eliminar cultivo (¡SOLO PERMITIDO PARA ADMIN POR SEGURIDAD!)
    if ($method === 'DELETE') {
        if ($usuario['rol'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Acción denegada. No tienes permisos para eliminar cultivos.']);
            exit;
        }

        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID requerido']);
            exit;
        }
        
        $stmt = $db->prepare("DELETE FROM cultivos_taxonomia WHERE id = ?");
        $stmt->execute([id]);
        
        echo json_encode(['success' => true, 'message' => 'Cultivo eliminado']);
        exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error DB: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

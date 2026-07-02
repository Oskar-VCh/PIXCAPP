<?php
// Activar reporte de errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar si el archivo Database.php existe
$databasePath = __DIR__ . '/../../core/Database.php';
if (!file_exists($databasePath)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database.php no encontrado en: ' . $databasePath
    ]);
    exit;
}

require_once $databasePath;

// Verificar si la clase Database existe
if (!class_exists('Database')) {
    echo json_encode([
        'success' => false, 
        'message' => 'Clase Database no encontrada'
    ]);
    exit;
}

// Obtener token del header
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');
$token = str_replace('Bearer ', '', $authHeader);

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token requerido']);
    exit;
}

$cultivoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$cultivoId) {
    echo json_encode(['success' => false, 'message' => 'ID de cultivo requerido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar sesión
    $stmt = $db->prepare("SELECT usuario_id, expira FROM sesiones WHERE token = ?");
    $stmt->execute([$token]);
    $sesion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sesion) {
        echo json_encode(['success' => false, 'message' => 'Sesión no encontrada']);
        exit;
    }
    
    // Verificar si la sesión ha expirado
    $fechaExpiracion = strtotime($sesion['expira']);
    if ($fechaExpiracion < time()) {
        echo json_encode(['success' => false, 'message' => 'Sesión expirada']);
        exit;
    }
    
    // Verificar si la tabla cultivos_taxonomia existe
    $stmt = $db->prepare("SHOW TABLES LIKE 'cultivos_taxonomia'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo json_encode([
            'success' => false, 
            'message' => 'La tabla cultivos_taxonomia no existe en la base de datos'
        ]);
        exit;
    }
    
    // Obtener información del cultivo
    $stmt = $db->prepare("SELECT * FROM cultivos_taxonomia WHERE id = ? AND estado = 'activo'");
    $stmt->execute([$cultivoId]);
    $cultivo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cultivo) {
        echo json_encode(['success' => false, 'message' => 'Cultivo no encontrado']);
        exit;
    }
    
    // Verificar qué tablas existen
    $stmt = $db->prepare("SHOW TABLES");
    $stmt->execute();
    $existingTables = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existingTables[] = $row[0];
    }
    
    // Inicializar arrays
    $variedades = [];
    $plagas = [];
    $enfermedades = [];
    $etapas = [];
    $riego = [];
    $fertilizacion = [];
    
    // Variedades
    if (in_array('cultivos_variedades', $existingTables)) {
        $stmt = $db->prepare("SELECT * FROM cultivos_variedades WHERE cultivo_id = ? ORDER BY nombre");
        $stmt->execute([$cultivoId]);
        $variedades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Plagas
    if (in_array('cultivos_plagas', $existingTables)) {
        $stmt = $db->prepare("SELECT * FROM cultivos_plagas WHERE cultivo_id = ? ORDER BY nombre");
        $stmt->execute([$cultivoId]);
        $plagas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Enfermedades
    if (in_array('cultivos_enfermedades', $existingTables)) {
        $stmt = $db->prepare("SELECT * FROM cultivos_enfermedades WHERE cultivo_id = ? ORDER BY nombre");
        $stmt->execute([$cultivoId]);
        $enfermedades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Etapas
    if (in_array('cultivos_etapas', $existingTables)) {
        $stmt = $db->prepare("SELECT * FROM cultivos_etapas WHERE cultivo_id = ? ORDER BY orden ASC, dias_promedio ASC");
        $stmt->execute([$cultivoId]);
        $etapas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Riego
    if (in_array('cultivos_riego', $existingTables)) {
        $stmt = $db->prepare("SELECT * FROM cultivos_riego WHERE cultivo_id = ? ORDER BY id");
        $stmt->execute([$cultivoId]);
        $riego = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Fertilización
    if (in_array('cultivos_fertilizacion', $existingTables)) {
        $stmt = $db->prepare("SELECT * FROM cultivos_fertilizacion WHERE cultivo_id = ? ORDER BY id");
        $stmt->execute([$cultivoId]);
        $fertilizacion = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Construir respuesta
    $guia = [
        'id' => $cultivo['id'],
        'nombre' => $cultivo['nombre_comun'],
        'nombre_cientifico' => $cultivo['nombre_cientifico'],
        'reino' => $cultivo['reino'],
        'division' => $cultivo['division'],
        'clase' => $cultivo['clase'],
        'orden' => $cultivo['orden'],
        'familia' => $cultivo['familia'],
        'genero' => $cultivo['genero'],
        'especie' => $cultivo['especie'],
        'ph_min' => $cultivo['ph_min'],
        'ph_max' => $cultivo['ph_max'],
        'temp_min' => $cultivo['temp_min'],
        'temp_max' => $cultivo['temp_max'],
        'altitud_min' => $cultivo['altitud_min'],
        'altitud_max' => $cultivo['altitud_max'],
        'precipitacion_min' => $cultivo['precipitacion_min'],
        'precipitacion_max' => $cultivo['precipitacion_max'],
        'imagen_principal' => $cultivo['imagen_principal'],
        'variedades' => $variedades,
        'plagas' => $plagas,
        'enfermedades' => $enfermedades,
        'etapas' => $etapas,
        'riego' => $riego,
        'fertilizacion' => $fertilizacion
    ];
    
    echo json_encode(['success' => true, 'guia' => $guia]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error general: ' . $e->getMessage()
    ]);
}
?>
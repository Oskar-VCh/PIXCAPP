<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../core/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Obtener cultivos activos
    $query = "SELECT id, nombre_comun as nombre, nombre_cientifico, imagen_principal 
              FROM cultivos_taxonomia 
              WHERE estado = 'activo' 
              ORDER BY nombre_comun";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $cultivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'cultivos' => $cultivos]);
    
} catch (PDOException $e) {
    error_log("Error en listar-publico.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>

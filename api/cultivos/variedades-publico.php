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
    
    $cultivoId = isset($_GET['cultivo_id']) ? (int)$_GET['cultivo_id'] : 0;
    
    if (!$cultivoId) {
        echo json_encode(['success' => false, 'message' => 'ID de cultivo requerido']);
        exit;
    }
    
    // Buscar variedades en la tabla cultivos_variedades
    $stmt = $db->prepare("
        SELECT id, nombre, ciclo_dias, altura_promedio, rendimiento, uso, descripcion
        FROM cultivos_variedades 
        WHERE cultivo_id = ? 
        ORDER BY nombre
    ");
    $stmt->execute([$cultivoId]);
    $variedades = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($variedades) > 0) {
        // Transformar para el frontend
        $variedadesFormateadas = array_map(function($v) {
            return [
                'nombre' => $v['nombre'],
                'descripcion' => $v['descripcion'] ?? ($v['uso'] ? "Uso: {$v['uso']}" : ''),
                'ciclo_dias' => $v['ciclo_dias'],
                'altura' => $v['altura_promedio'],
                'rendimiento' => $v['rendimiento']
            ];
        }, $variedades);
        
        echo json_encode(['success' => true, 'variedades' => $variedadesFormateadas]);
        exit;
    }
    
    // Si no hay variedades registradas, devolver datos de ejemplo
    $stmt = $db->prepare("SELECT nombre_comun FROM cultivos_taxonomia WHERE id = ?");
    $stmt->execute([$cultivoId]);
    $cultivo = $stmt->fetch(PDO::FETCH_ASSOC);
    $cultivoNombre = $cultivo ? $cultivo['nombre_comun'] : '';
    
    $variedadesGenericas = [];
    
    if ($cultivoNombre === 'Maíz' || $cultivoId == 1) {
        $variedadesGenericas = [
            ['nombre' => 'Blanco Criollo', 'descripcion' => 'Tradicional mexicano, grano blanco, resistente a sequía'],
            ['nombre' => 'Amarillo Híbrido', 'descripcion' => 'Alto rendimiento, ideal para forraje'],
            ['nombre' => 'Azul Criollo', 'descripcion' => 'Grano azul rico en antocianinas, uso artesanal'],
            ['nombre' => 'Dulce', 'descripcion' => 'Maíz para elote, sabor dulce, grano tierno']
        ];
    } elseif ($cultivoNombre === 'Frijol') {
        $variedadesGenericas = [
            ['nombre' => 'Negro Jamapa', 'descripcion' => 'Grano negro brillante, alto rendimiento'],
            ['nombre' => 'Flor de Mayo', 'descripcion' => 'Grano beige con manchas rojas, buena demanda'],
            ['nombre' => 'Peruano', 'descripcion' => 'Grano beige claro, ideal para exportación']
        ];
    } elseif ($cultivoNombre === 'Tomate') {
        $variedadesGenericas = [
            ['nombre' => 'Río Grande', 'descripcion' => 'Industria, excelente rendimiento, fruto firme'],
            ['nombre' => 'Cherry', 'descripcion' => 'Fruto pequeño, consumo fresco, alto valor comercial'],
            ['nombre' => 'Saladette', 'descripcion' => 'Consumo en fresco, fruto firme y carnoso']
        ];
    } elseif ($cultivoNombre === 'Chile') {
        $variedadesGenericas = [
            ['nombre' => 'Jalapeño', 'descripcion' => 'Picante medio, muy demandado en fresco y encurtido'],
            ['nombre' => 'Serrano', 'descripcion' => 'Picante alto, tamaño pequeño, ideal para salsas'],
            ['nombre' => 'Habanero', 'descripcion' => 'Muy picante, aroma frutal, alta demanda']
        ];
    } elseif ($cultivoNombre === 'Calabaza') {
        $variedadesGenericas = [
            ['nombre' => 'Zucchini', 'descripcion' => 'Calabacín verde, consumo tierno, alta productividad'],
            ['nombre' => 'Castilla', 'descripcion' => 'Calabaza para dulce, pulpa anaranjada']
        ];
    } else {
        $variedadesGenericas = [
            ['nombre' => 'Criolla', 'descripcion' => 'Variedad local tradicional, adaptada a la región'],
            ['nombre' => 'Mejorada', 'descripcion' => 'Alto rendimiento y resistencia a plagas'],
            ['nombre' => 'Híbrida', 'descripcion' => 'Híbrido de alta productividad, fruto uniforme']
        ];
    }
    
    echo json_encode(['success' => true, 'variedades' => $variedadesGenericas]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error DB: ' . $e->getMessage()]);
}
?>

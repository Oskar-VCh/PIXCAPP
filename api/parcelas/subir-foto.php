<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../core/Database.php';

// Verificar token
$headers = getallheaders();
$authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');
$token = str_replace('Bearer ', '', $authHeader);

if (!$token) {
    echo json_encode(['success' => false, 'message' => 'Token requerido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Verificar sesión
    $stmt = $db->prepare("SELECT usuario_id FROM sesiones WHERE token = ? AND expira > NOW()");
    $stmt->execute([$token]);
    $sesion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sesion) {
        echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
        exit;
    }
    
    $parcelaId = $_POST['parcela_id'] ?? $_GET['parcela_id'] ?? 0;
    
    if (!$parcelaId) {
        echo json_encode(['success' => false, 'message' => 'ID de parcela requerido']);
        exit;
    }
    
    // Verificar que la parcela pertenece al agricultor
    $stmt = $db->prepare("SELECT id FROM parcelas WHERE id = ? AND agricultor_id = ?");
    $stmt->execute([$parcelaId, $sesion['usuario_id']]);
    $parcela = $stmt->fetch();
    
    if (!$parcela) {
        echo json_encode(['success' => false, 'message' => 'Parcela no encontrada']);
        exit;
    }
    
    // Verificar si hay archivo
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No se recibió la foto o hubo un error en la carga']);
        exit;
    }
    
    // Crear directorio si no existe
    $uploadDir = __DIR__ . '/../../assets/uploads/parcelas/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Validar tipo de archivo
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $_FILES['foto']['tmp_name']);
    finfo_close($fileInfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Formato no permitido. Use JPG, PNG o WEBP']);
        exit;
    }
    
    // Validar tamaño (máximo 5MB)
    if ($_FILES['foto']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'La imagen no debe superar los 5MB']);
        exit;
    }
    
    // Generar nombre único
    $extension = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
    $filename = 'parcela_' . $parcelaId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $uploadPath = $uploadDir . $filename;
    
    // Mover archivo
    if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadPath)) {
        $fotoUrl = 'assets/uploads/parcelas/' . $filename;
        
        // Actualizar en la base de datos
        $stmt = $db->prepare("UPDATE parcelas SET foto_principal = ? WHERE id = ?");
        $result = $stmt->execute([$fotoUrl, $parcelaId]);
        
        if ($result) {
            echo json_encode([
                'success' => true, 
                'message' => 'Foto actualizada correctamente',
                'url' => $fotoUrl
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar en la base de datos']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al mover el archivo subido']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
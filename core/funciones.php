<?php
/**
 * Funciones utilitarias para PIXCAPP
 */

// Sanitizar input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Generar respuesta JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Validar teléfono mexicano (10 dígitos)
function validarTelefono($telefono) {
    $telefono = preg_replace('/[^0-9]/', '', $telefono);
    return strlen($telefono) === 10;
}

// Generar código de verificación
function generarCodigo($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}

/**
 * Guardar imagen desde base64
 * @param string $base64String - String en formato data:image/...
 * @param string $subdir - Subdirectorio dentro de uploads/
 * @return string|false - Ruta relativa de la imagen guardada o false si falla
 */
function guardarImagenBase64($base64String, $subdir = '') {
    // Validar que sea un data URI válido
    if (!preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches)) {
        return false;
    }
    
    $extension = strtolower($matches[1]);
    
    // Extensiones permitidas
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $allowedExtensions)) {
        $extension = 'jpg'; // Por defecto
    }
    
    // Extraer los datos base64
    $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $base64String);
    $base64Data = str_replace(' ', '+', $base64Data);
    $imageData = base64_decode($base64Data);
    
    if ($imageData === false) {
        return false;
    }
    
    // Validar tamaño (máximo 5MB)
    if (strlen($imageData) > 5 * 1024 * 1024) {
        return false;
    }
    
    // Crear directorio si no existe
    $uploadDir = UPLOAD_DIR . $subdir;
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return false;
        }
    }
    
    // Generar nombre único
    $filename = uniqid() . '_' . date('YmdHis') . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Guardar archivo
    if (file_put_contents($filepath, $imageData) === false) {
        return false;
    }
    
    // Retornar ruta relativa para guardar en BD
    return 'assets/uploads/' . $subdir . $filename;
}

/**
 * Guardar archivo subido por formulario
 * @param array $file - Array $_FILES['nombre']
 * @param string $subdir - Subdirectorio dentro de uploads/
 * @return array - ['success' => bool, 'path' => string, 'message' => string]
 */
function guardarArchivoSubido($file, $subdir = '') {
    // Validaciones básicas
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error al subir el archivo'];
    }
    
    // Extensiones permitidas
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowedExtensions)) {
        return ['success' => false, 'message' => 'Formato de archivo no permitido'];
    }
    
    // Validar tamaño (5MB máximo)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'El archivo no debe superar los 5MB'];
    }
    
    // Crear directorio si no existe
    $uploadDir = UPLOAD_DIR . $subdir;
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'message' => 'Error al crear directorio'];
        }
    }
    
    // Generar nombre único
    $filename = uniqid() . '_' . date('YmdHis') . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    // Mover archivo
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'message' => 'Error al guardar el archivo'];
    }
    
    return [
        'success' => true,
        'path' => 'assets/uploads/' . $subdir . $filename
    ];
}

// Formatear fecha
function formatearFecha($fecha, $formato = 'd/m/Y') {
    if (empty($fecha)) return '';
    return date($formato, strtotime($fecha));
}

// Calcular distancia entre coordenadas (metros)
function calcularDistancia($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Radio de la tierra en metros
    
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    $latDelta = $lat2 - $lat1;
    $lonDelta = $lon2 - $lon1;
    
    $a = sin($latDelta / 2) * sin($latDelta / 2) +
         cos($lat1) * cos($lat2) *
         sin($lonDelta / 2) * sin($lonDelta / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c;
}

// Verificar duplicado de parcela (50m radio)
function verificarDuplicadoParcela($lat, $lon, $excluirId = null) {
    $db = Database::getInstance();
    
    // Usamos la fórmula de Haversine para calcular distancia
    $sql = "SELECT id, nombre, agricultor_id,
            (6371000 * acos(cos(radians(?)) * cos(radians(latitud)) * 
            cos(radians(longitud) - radians(?)) + sin(radians(?)) * 
            sin(radians(latitud)))) AS distancia 
            FROM parcelas 
            WHERE estado = 'activo'
            HAVING distancia < 50";
    
    $params = [$lat, $lon, $lat];
    
    if ($excluirId) {
        $sql .= " AND id != ?";
        $params[] = $excluirId;
    }
    
    $sql .= " ORDER BY distancia LIMIT 1";
    
    $stmt = $db->query($sql, $params);
    return $stmt->fetch();
}

// Generar slug desde texto
function generarSlug($texto) {
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    $texto = trim($texto, '-');
    return $texto;
}

// Truncar texto
function truncarTexto($texto, $longitud = 100, $sufijo = '...') {
    if (strlen($texto) <= $longitud) {
        return $texto;
    }
    return substr($texto, 0, $longitud) . $sufijo;
}

// Obtener extensión de archivo
function getExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// Validar si es una imagen
function esImagen($extension) {
    $imagenes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    return in_array(strtolower($extension), $imagenes);
}

// Generar token único
function generarToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Verificar si el token es válido
function verificarToken($token, $hash) {
    return hash_equals($hash, hash('sha256', $token));
}
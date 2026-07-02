<?php
require_once __DIR__ . '/Database.php';

class Auth {
    
    // Verificar si el usuario está autenticado
    public static function check() {
        return isset($_SESSION['user_id']);
    }
    
    // Obtener usuario actual
    public static function user() {
        if (!self::check()) {
            return null;
        }
        
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT id, nombre, telefono, correo, rol, estado, foto_perfil 
             FROM usuarios WHERE id = ?",
            [$_SESSION['user_id']]
        );
        
        return $stmt->fetch();
    }
    
    // Intentar login
    public static function login($telefono, $password) {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT id, password_hash, rol, estado FROM usuarios WHERE telefono = ?",
            [$telefono]
        );
        
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['estado'] !== 'activo') {
                return ['success' => false, 'message' => 'Cuenta no activa'];
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_rol'] = $user['rol'];
            
            // Actualizar último acceso
            $db->query(
                "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?",
                [$user['id']]
            );
            
            // Registrar log
            self::log('login', 'Inicio de sesión exitoso');
            
            return [
                'success' => true,
                'rol' => $user['rol']
            ];
        }
        
        self::log('login_failed', "Intento fallido: $telefono");
        return ['success' => false, 'message' => 'Credenciales inválidas'];
    }
    
    // Cerrar sesión
    public static function logout() {
        if (self::check()) {
            self::log('logout', 'Cierre de sesión');
        }
        session_destroy();
        return true;
    }
    
    // Verificar rol
    public static function hasRole($role) {
        return self::check() && $_SESSION['user_rol'] === $role;
    }
    
    // Middleware para proteger rutas
    public static function requireAuth() {
        if (!self::check()) {
            header('Location: ' . BASE_URL . 'login.html');
            exit;
        }
    }
    
    // Middleware para rol específico
    public static function requireRole($role) {
        self::requireAuth();
        if ($_SESSION['user_rol'] !== $role) {
            header('HTTP/1.0 403 Forbidden');
            die('Acceso denegado');
        }
    }
    
    // Registrar en logs
    private static function log($accion, $detalle) {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $db->query(
            "INSERT INTO logs_auditoria (usuario_id, accion, detalle, ip, user_agent) 
             VALUES (?, ?, ?, ?, ?)",
            [$userId, $accion, $detalle, $ip, $ua]
        );
    }
}
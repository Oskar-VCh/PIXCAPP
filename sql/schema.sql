-- Crear base de datos
CREATE DATABASE IF NOT EXISTS pixcapp_db;
USE pixcapp_db;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) UNIQUE NOT NULL,
    correo VARCHAR(100),
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('agricultor', 'ingeniero', 'admin') DEFAULT 'agricultor',
    estado ENUM('activo', 'pendiente', 'suspendido', 'eliminado') DEFAULT 'activo',
    foto_perfil VARCHAR(255),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso TIMESTAMP NULL,
    INDEX idx_telefono (telefono),
    INDEX idx_rol (rol),
    INDEX idx_estado (estado)
);

-- Tabla de ingenieros (extensión)
CREATE TABLE ingenieros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    cedula_profesional VARCHAR(20) UNIQUE NOT NULL,
    especialidad VARCHAR(100),
    validado_por INT NULL,
    fecha_validacion TIMESTAMP NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (validado_por) REFERENCES usuarios(id),
    INDEX idx_cedula (cedula_profesional)
);

-- Tabla de parcelas
CREATE TABLE parcelas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agricultor_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    cultivo_id INT NULL,
    variedad VARCHAR(50),
    fecha_siembra DATE,
    latitud DECIMAL(10, 8) NOT NULL,
    longitud DECIMAL(11, 8) NOT NULL,
    area_m2 DECIMAL(10, 2),
    notas TEXT,
    foto_principal VARCHAR(255),
    estado ENUM('activo', 'cosechado', 'inactivo') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agricultor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_agricultor (agricultor_id),
    INDEX idx_ubicacion (latitud, longitud)
);

-- Tabla de cultivos (taxonomía)
CREATE TABLE cultivos_taxonomia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_comun VARCHAR(100) NOT NULL,
    nombre_cientifico VARCHAR(100) NOT NULL,
    reino VARCHAR(50),
    division VARCHAR(50),
    clase VARCHAR(50),
    orden VARCHAR(50),
    familia VARCHAR(50),
    genero VARCHAR(50),
    especie VARCHAR(50),
    ph_min DECIMAL(3,1),
    ph_max DECIMAL(3,1),
    temp_min DECIMAL(4,1),
    temp_max DECIMAL(4,1),
    altitud_min INT,
    altitud_max INT,
    precipitacion_min INT,
    precipitacion_max INT,
    imagen_principal VARCHAR(255),
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    INDEX idx_nombre (nombre_comun)
);

-- Tabla de eventos
CREATE TABLE eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parcela_id INT NOT NULL,
    tipo ENUM('riego', 'poda', 'fertilizacion', 'plaga', 'medicion', 'foto') NOT NULL,
    datos JSON NOT NULL,
    sincronizado BOOLEAN DEFAULT TRUE,
    fecha_evento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_sincronizacion TIMESTAMP NULL,
    FOREIGN KEY (parcela_id) REFERENCES parcelas(id) ON DELETE CASCADE,
    INDEX idx_parcela (parcela_id),
    INDEX idx_tipo (tipo),
    INDEX idx_sincronizado (sincronizado)
);

-- Tabla de fotos
CREATE TABLE fotos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NULL,
    parcela_id INT NULL,
    usuario_id INT NOT NULL,
    url VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_subida TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (evento_id) REFERENCES eventos(id) ON DELETE SET NULL,
    FOREIGN KEY (parcela_id) REFERENCES parcelas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_parcela_fotos (parcela_id)
);

-- Tabla de asignaciones (ingeniero-agricultor)
CREATE TABLE asignaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingeniero_id INT NOT NULL,
    agricultor_id INT NOT NULL,
    estado ENUM('pendiente', 'activo', 'rechazado') DEFAULT 'pendiente',
    fecha_solicitud TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_respuesta TIMESTAMP NULL,
    UNIQUE KEY unique_asignacion (ingeniero_id, agricultor_id),
    FOREIGN KEY (ingeniero_id) REFERENCES usuarios(id),
    FOREIGN KEY (agricultor_id) REFERENCES usuarios(id),
    INDEX idx_ingeniero (ingeniero_id),
    INDEX idx_agricultor (agricultor_id)
);

-- Tabla de recomendaciones
CREATE TABLE recomendaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingeniero_id INT NOT NULL,
    agricultor_id INT NOT NULL,
    parcela_id INT NULL,
    mensaje TEXT NOT NULL,
    nota_voz VARCHAR(255),
    imagen VARCHAR(255),
    leido BOOLEAN DEFAULT FALSE,
    fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ingeniero_id) REFERENCES usuarios(id),
    FOREIGN KEY (agricultor_id) REFERENCES usuarios(id),
    FOREIGN KEY (parcela_id) REFERENCES parcelas(id),
    INDEX idx_agricultor_rec (agricultor_id)
);

-- Tabla de logs de auditoría
CREATE TABLE logs_auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    accion VARCHAR(50) NOT NULL,
    detalle TEXT,
    ip VARCHAR(45),
    user_agent TEXT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_fecha (fecha)
);

-- Tabla de notificaciones push
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(100) NOT NULL,
    mensaje TEXT NOT NULL,
    tipo VARCHAR(30),
    datos JSON,
    leido BOOLEAN DEFAULT FALSE,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_not (usuario_id),
    INDEX idx_leido (leido)
);

-- Datos iniciales
INSERT INTO usuarios (nombre, telefono, password_hash, rol, estado) VALUES
('Administrador', 'admin', '$2y$10$YourHashedPasswordHere', 'admin', 'activo');

-- Insertar cultivo Maíz
INSERT INTO cultivos_taxonomia (
    nombre_comun, nombre_cientifico, reino, division, clase, orden, familia, genero, especie,
    ph_min, ph_max, temp_min, temp_max, altitud_min, altitud_max, precipitacion_min, precipitacion_max
) VALUES (
    'Maíz', 'Zea mays', 'Plantae', 'Magnoliophyta', 'Liliopsida', 'Poales', 'Poaceae', 'Zea', 'mays',
    5.5, 7.0, 18, 32, 0, 2500, 500, 800
);
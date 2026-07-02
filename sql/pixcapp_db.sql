-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 04-05-2026 a las 01:01:28
-- Versión del servidor: 8.0.45-0ubuntu0.24.04.1
-- Versión de PHP: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `pixcapp_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaciones`
--

CREATE TABLE `asignaciones` (
  `id` int NOT NULL,
  `ingeniero_id` int NOT NULL,
  `agricultor_id` int NOT NULL,
  `estado` enum('pendiente','activo','rechazado') DEFAULT 'pendiente',
  `fecha_solicitud` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_respuesta` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cultivos_taxonomia`
--

CREATE TABLE `cultivos_taxonomia` (
  `id` int NOT NULL,
  `nombre_comun` varchar(100) NOT NULL,
  `nombre_cientifico` varchar(100) NOT NULL,
  `reino` varchar(50) DEFAULT NULL,
  `division` varchar(50) DEFAULT NULL,
  `clase` varchar(50) DEFAULT NULL,
  `orden` varchar(50) DEFAULT NULL,
  `familia` varchar(50) DEFAULT NULL,
  `genero` varchar(50) DEFAULT NULL,
  `especie` varchar(50) DEFAULT NULL,
  `ph_min` decimal(3,1) DEFAULT NULL,
  `ph_max` decimal(3,1) DEFAULT NULL,
  `temp_min` decimal(4,1) DEFAULT NULL,
  `temp_max` decimal(4,1) DEFAULT NULL,
  `altitud_min` int DEFAULT NULL,
  `altitud_max` int DEFAULT NULL,
  `precipitacion_min` int DEFAULT NULL,
  `precipitacion_max` int DEFAULT NULL,
  `imagen_principal` varchar(255) DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cultivos_taxonomia`
--

INSERT INTO `cultivos_taxonomia` (`id`, `nombre_comun`, `nombre_cientifico`, `reino`, `division`, `clase`, `orden`, `familia`, `genero`, `especie`, `ph_min`, `ph_max`, `temp_min`, `temp_max`, `altitud_min`, `altitud_max`, `precipitacion_min`, `precipitacion_max`, `imagen_principal`, `estado`) VALUES
(1, 'Maíz', 'Zea mays', 'Plantae', 'Magnoliophyta', 'Liliopsida', 'Poales', 'Poaceae', 'Zea', 'mays', 5.5, 7.0, 18.0, 32.0, 0, 2500, 500, 800, NULL, 'activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `eventos`
--

CREATE TABLE `eventos` (
  `id` int NOT NULL,
  `parcela_id` int NOT NULL,
  `tipo` enum('riego','poda','fertilizacion','plaga','medicion','foto') NOT NULL,
  `datos` json NOT NULL,
  `sincronizado` tinyint(1) DEFAULT '1',
  `fecha_evento` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_sincronizacion` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fotos`
--

CREATE TABLE `fotos` (
  `id` int NOT NULL,
  `evento_id` int DEFAULT NULL,
  `parcela_id` int DEFAULT NULL,
  `usuario_id` int NOT NULL,
  `url` varchar(255) NOT NULL,
  `descripcion` text,
  `fecha_subida` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ingenieros`
--

CREATE TABLE `ingenieros` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `cedula_profesional` varchar(20) NOT NULL,
  `especialidad` varchar(100) DEFAULT NULL,
  `validado_por` int DEFAULT NULL,
  `fecha_validacion` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `ingenieros`
--

INSERT INTO `ingenieros` (`id`, `usuario_id`, `cedula_profesional`, `especialidad`, `validado_por`, `fecha_validacion`) VALUES
(1, 4, '3516531', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_auditoria`
--

CREATE TABLE `logs_auditoria` (
  `id` int NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `accion` varchar(50) NOT NULL,
  `detalle` text,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `fecha` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `logs_auditoria`
--

INSERT INTO `logs_auditoria` (`id`, `usuario_id`, `accion`, `detalle`, `ip`, `user_agent`, `fecha`) VALUES
(1, 3, 'registro', 'Registro de agricultor exitoso', '127.0.0.1', NULL, '2026-04-20 04:22:35'),
(2, NULL, 'logout', 'Cierre de sesión', '127.0.0.1', NULL, '2026-04-20 04:22:42'),
(3, NULL, 'logout', 'Cierre de sesión', '127.0.0.1', NULL, '2026-04-20 04:24:43'),
(4, 4, 'registro_pendiente', 'Solicitud de registro de ingeniero', '127.0.0.1', NULL, '2026-04-20 04:26:30'),
(5, NULL, 'logout', 'Cierre de sesión', '172.16.1.162', NULL, '2026-04-20 19:32:12'),
(6, 5, 'registro', 'Registro de agricultor exitoso', '172.16.1.162', NULL, '2026-04-20 19:36:55'),
(7, NULL, 'logout', 'Cierre de sesión', '172.16.1.162', NULL, '2026-04-20 19:37:34'),
(8, NULL, 'logout', 'Cierre de sesión', '127.0.0.1', NULL, '2026-04-27 04:24:19'),
(9, NULL, 'logout', 'Cierre de sesión', '127.0.0.1', NULL, '2026-05-02 23:41:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `titulo` varchar(100) NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` varchar(30) DEFAULT NULL,
  `datos` json DEFAULT NULL,
  `leido` tinyint(1) DEFAULT '0',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `parcelas`
--

CREATE TABLE `parcelas` (
  `id` int NOT NULL,
  `agricultor_id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `cultivo_id` int DEFAULT NULL,
  `variedad` varchar(50) DEFAULT NULL,
  `fecha_siembra` date DEFAULT NULL,
  `latitud` decimal(10,8) NOT NULL,
  `longitud` decimal(11,8) NOT NULL,
  `area_m2` decimal(10,2) DEFAULT NULL,
  `notas` text,
  `foto_principal` varchar(255) DEFAULT NULL,
  `estado` enum('activo','cosechado','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `parcelas`
--

INSERT INTO `parcelas` (`id`, `agricultor_id`, `nombre`, `cultivo_id`, `variedad`, `fecha_siembra`, `latitud`, `longitud`, `area_m2`, `notas`, `foto_principal`, `estado`, `fecha_creacion`) VALUES
(1, 3, 'Parcela ejemplo', 1, 'Blanco Criollo', '2026-04-27', 16.80343040, -99.37879040, NULL, NULL, NULL, 'activo', '2026-04-20 04:22:35'),
(2, 5, 'parcela 1', 1, 'Amarillo Híbrido', '2026-04-20', 16.85734370, -99.39034190, NULL, NULL, NULL, 'activo', '2026-04-20 19:36:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recomendaciones`
--

CREATE TABLE `recomendaciones` (
  `id` int NOT NULL,
  `ingeniero_id` int NOT NULL,
  `agricultor_id` int NOT NULL,
  `parcela_id` int DEFAULT NULL,
  `mensaje` text NOT NULL,
  `nota_voz` varchar(255) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `leido` tinyint(1) DEFAULT '0',
  `fecha_envio` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesiones`
--

CREATE TABLE `sesiones` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `token` varchar(64) NOT NULL,
  `expira` datetime DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `ultima_actividad` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `creado` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `sesiones`
--

INSERT INTO `sesiones` (`id`, `usuario_id`, `token`, `expira`, `ip`, `user_agent`, `ultima_actividad`, `creado`) VALUES
(7, 3, '2b12991f46300f7c09f005c783405998ce914edad06843472ca5b4eed180ce0d', '2026-05-26 22:36:20', '192.168.1.92', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-04-27 04:36:20', '2026-04-27 04:36:20'),
(9, 3, '14208baa577134c7cbeb47734c0d6167df343230ad1276bc0d0e47e1e030b69d', '2026-05-27 08:57:18', '172.16.1.173', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '2026-04-27 14:57:18', '2026-04-27 14:57:18'),
(10, 4, '20ce8aecab604ca79e967173c058fe384acafd8704a9209305e7916cad577198', '2026-06-01 17:46:42', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-05-02 23:46:42', '2026-05-02 23:46:42'),
(11, 4, 'e99c8017caab9238a7fc0823a6d1385f454ac734290b3085271a88eca82ec59c', '2026-06-01 18:16:12', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64; rv:150.0) Gecko/20100101 Firefox/150.0', '2026-05-03 00:16:12', '2026-05-03 00:16:12');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('agricultor','ingeniero','admin') DEFAULT 'agricultor',
  `estado` enum('activo','pendiente','suspendido','eliminado') DEFAULT 'activo',
  `foto_perfil` varchar(255) DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acceso` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `telefono`, `correo`, `password_hash`, `rol`, `estado`, `foto_perfil`, `fecha_registro`, `ultimo_acceso`) VALUES
(2, 'Juan Pérez', '5512345678', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agricultor', 'activo', NULL, '2026-04-20 04:17:17', NULL),
(3, 'Jose García', '7452343434', 'jose@ejemplo.com', '$2y$10$rfaUHjACaQvwFyPYrZKcq.mNS9w0HTLedx4WUrdB4BUBmF7/WQrje', 'agricultor', 'activo', NULL, '2026-04-20 04:22:35', '2026-04-27 14:57:18'),
(4, 'Pedro Hernández', '7452353535', 'pedro@ejemplo.com', '$2y$10$NUV2B5lM/dyQiP1q.0yWj.FsqG6r.ikS5v7S1upFz.IbqvRIYZ6Ti', 'ingeniero', 'activo', NULL, '2026-04-20 04:26:30', '2026-05-03 00:16:12'),
(5, 'Rogelio', '7451100228', NULL, '$2y$10$P9XoVBTjS.COoEPKhm0.9eFPpddHNjrGmu6yKQjTPKd9.tMevVlnu', 'agricultor', 'activo', NULL, '2026-04-20 19:36:55', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_asignacion` (`ingeniero_id`,`agricultor_id`),
  ADD KEY `idx_ingeniero` (`ingeniero_id`),
  ADD KEY `idx_agricultor` (`agricultor_id`);

--
-- Indices de la tabla `cultivos_taxonomia`
--
ALTER TABLE `cultivos_taxonomia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nombre` (`nombre_comun`);

--
-- Indices de la tabla `eventos`
--
ALTER TABLE `eventos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_parcela` (`parcela_id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_sincronizado` (`sincronizado`);

--
-- Indices de la tabla `fotos`
--
ALTER TABLE `fotos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `evento_id` (`evento_id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_parcela_fotos` (`parcela_id`);

--
-- Indices de la tabla `ingenieros`
--
ALTER TABLE `ingenieros`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cedula_profesional` (`cedula_profesional`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `validado_por` (`validado_por`),
  ADD KEY `idx_cedula` (`cedula_profesional`);

--
-- Indices de la tabla `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_accion` (`accion`),
  ADD KEY `idx_fecha` (`fecha`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_usuario_not` (`usuario_id`),
  ADD KEY `idx_leido` (`leido`);

--
-- Indices de la tabla `parcelas`
--
ALTER TABLE `parcelas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_agricultor` (`agricultor_id`),
  ADD KEY `idx_ubicacion` (`latitud`,`longitud`);

--
-- Indices de la tabla `recomendaciones`
--
ALTER TABLE `recomendaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ingeniero_id` (`ingeniero_id`),
  ADD KEY `parcela_id` (`parcela_id`),
  ADD KEY `idx_agricultor_rec` (`agricultor_id`);

--
-- Indices de la tabla `sesiones`
--
ALTER TABLE `sesiones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expira` (`expira`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telefono` (`telefono`),
  ADD KEY `idx_telefono` (`telefono`),
  ADD KEY `idx_rol` (`rol`),
  ADD KEY `idx_estado` (`estado`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cultivos_taxonomia`
--
ALTER TABLE `cultivos_taxonomia`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `eventos`
--
ALTER TABLE `eventos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fotos`
--
ALTER TABLE `fotos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ingenieros`
--
ALTER TABLE `ingenieros`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `parcelas`
--
ALTER TABLE `parcelas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `recomendaciones`
--
ALTER TABLE `recomendaciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sesiones`
--
ALTER TABLE `sesiones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asignaciones`
--
ALTER TABLE `asignaciones`
  ADD CONSTRAINT `asignaciones_ibfk_1` FOREIGN KEY (`ingeniero_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `asignaciones_ibfk_2` FOREIGN KEY (`agricultor_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `eventos`
--
ALTER TABLE `eventos`
  ADD CONSTRAINT `eventos_ibfk_1` FOREIGN KEY (`parcela_id`) REFERENCES `parcelas` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `fotos`
--
ALTER TABLE `fotos`
  ADD CONSTRAINT `fotos_ibfk_1` FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fotos_ibfk_2` FOREIGN KEY (`parcela_id`) REFERENCES `parcelas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fotos_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `ingenieros`
--
ALTER TABLE `ingenieros`
  ADD CONSTRAINT `ingenieros_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ingenieros_ibfk_2` FOREIGN KEY (`validado_por`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `logs_auditoria`
--
ALTER TABLE `logs_auditoria`
  ADD CONSTRAINT `logs_auditoria_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `parcelas`
--
ALTER TABLE `parcelas`
  ADD CONSTRAINT `parcelas_ibfk_1` FOREIGN KEY (`agricultor_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `recomendaciones`
--
ALTER TABLE `recomendaciones`
  ADD CONSTRAINT `recomendaciones_ibfk_1` FOREIGN KEY (`ingeniero_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `recomendaciones_ibfk_2` FOREIGN KEY (`agricultor_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `recomendaciones_ibfk_3` FOREIGN KEY (`parcela_id`) REFERENCES `parcelas` (`id`);

--
-- Filtros para la tabla `sesiones`
--
ALTER TABLE `sesiones`
  ADD CONSTRAINT `sesiones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

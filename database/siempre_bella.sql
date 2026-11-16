-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 11-11-2026 a las 14:34:48
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `siempre_bella`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `banners`
--

CREATE TABLE `banners` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `titulo` varchar(200) DEFAULT NULL,
  `subtitulo` text DEFAULT NULL,
  `imagen_id` int(10) UNSIGNED NOT NULL,
  `imagen_movil_id` int(10) UNSIGNED DEFAULT NULL,
  `texto_boton` varchar(100) DEFAULT NULL,
  `enlace_boton` varchar(500) DEFAULT NULL,
  `posicion` enum('principal','secundario','promocional') NOT NULL DEFAULT 'principal',
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `imagen` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`, `slug`, `descripcion`, `activo`, `creado_en`, `actualizado_en`, `imagen`) VALUES
(1, 'Cosméticos', 'cosmeticos', NULL, 1, '2026-11-09 21:13:41', '2026-11-09 21:13:41', NULL),
(2, 'Uñas', 'unas', NULL, 1, '2026-11-09 21:13:41', '2026-11-09 21:13:41', NULL),
(3, 'Cabello', 'cabello', NULL, 1, '2026-11-09 21:13:41', '2026-11-09 21:13:41', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion_tienda`
--

CREATE TABLE `configuracion_tienda` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `nombre_tienda` varchar(150) NOT NULL DEFAULT 'Siempre Bella',
  `whatsapp` varchar(30) DEFAULT NULL,
  `monto_minimo_mayorista` decimal(12,2) DEFAULT NULL,
  `moneda` char(3) NOT NULL DEFAULT 'COP',
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Volcado de datos para la tabla `configuracion_tienda`
--

INSERT INTO `configuracion_tienda` (`id`, `nombre_tienda`, `whatsapp`, `monto_minimo_mayorista`, `moneda`, `actualizado_en`) VALUES
(1, 'Siempre Bella', NULL, NULL, 'COP', '2026-11-09 21:13:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `marcas`
--

CREATE TABLE `marcas` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `marcas`
--

INSERT INTO `marcas` (`id`, `nombre`, `slug`, `descripcion`, `activo`, `creado_en`, `actualizado_en`) VALUES
(1, 'Milagros', 'milagros', NULL, 1, '2026-11-09 21:13:41', '2026-11-09 21:13:41'),
(2, 'La Poción', 'la-pocion', NULL, 1, '2026-11-09 21:13:41', '2026-11-09 21:13:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(10) UNSIGNED NOT NULL,
  `categoria_id` int(10) UNSIGNED NOT NULL,
  `marca_id` int(10) UNSIGNED DEFAULT NULL,
  `sku` varchar(80) DEFAULT NULL,
  `nombre` varchar(180) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_minorista` decimal(12,2) NOT NULL,
  `precio_mayorista` decimal(12,2) DEFAULT NULL,
  `disponibilidad` enum('disponible','agotado') NOT NULL DEFAULT 'disponible',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_imagenes`
--

CREATE TABLE `producto_imagenes` (
  `id` int(10) UNSIGNED NOT NULL,
  `producto_id` int(10) UNSIGNED NOT NULL,
  `ruta` varchar(500) NOT NULL,
  `texto_alternativo` varchar(180) DEFAULT NULL,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  `orden` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `promociones`
--

CREATE TABLE `promociones` (
  `id` int(10) UNSIGNED NOT NULL,
  `producto_id` int(10) UNSIGNED NOT NULL,
  `variante_id` int(10) UNSIGNED DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `precio_minorista_promocional` decimal(12,2) DEFAULT NULL,
  `precio_mayorista_promocional` decimal(12,2) DEFAULT NULL,
  `inicia_en` datetime DEFAULT NULL,
  `termina_en` datetime DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recursos_multimedia`
--

CREATE TABLE `recursos_multimedia` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(500) NOT NULL,
  `tipo_mime` varchar(100) NOT NULL,
  `extension` varchar(20) NOT NULL,
  `tamanio_bytes` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `texto_alternativo` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `redes_sociales`
--

CREATE TABLE `redes_sociales` (
  `id` int(10) UNSIGNED NOT NULL,
  `plataforma` varchar(60) NOT NULL,
  `url` varchar(500) NOT NULL,
  `orden` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `secciones_tienda`
--

CREATE TABLE `secciones_tienda` (
  `id` int(10) UNSIGNED NOT NULL,
  `identificador` varchar(100) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `tipo_seccion` enum('banner','categorias','productos_destacados','promociones','contenido_imagen','personalizada') NOT NULL,
  `configuracion` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuracion`)),
  `orden` int(11) NOT NULL DEFAULT 0,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `secciones_tienda`
--

INSERT INTO `secciones_tienda` (`id`, `identificador`, `nombre`, `tipo_seccion`, `configuracion`, `orden`, `visible`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'banner_principal', 'Banner principal', 'banner', '{\"descripcion\": \"Banner principal de la tienda\"}', 1, 1, '2026-11-11 13:10:01', '2026-11-11 13:10:01'),
(2, 'categorias_destacadas', 'Categorías destacadas', 'categorias', '{\"titulo\": \"Compra por categorías\", \"cantidad\": 3}', 2, 1, '2026-11-11 13:10:01', '2026-11-11 13:10:01'),
(3, 'productos_destacados', 'Productos destacados', 'productos_destacados', '{\"titulo\": \"Nuestros productos destacados\", \"cantidad\": 8}', 3, 1, '2026-11-11 13:10:01', '2026-11-11 13:10:01'),
(4, 'promociones', 'Promociones', 'promociones', '{\"titulo\": \"Ofertas especiales\"}', 4, 1, '2026-11-11 13:10:01', '2026-11-11 13:10:01'),
(5, 'banner_secundario', 'Banner secundario', 'banner', '{\"descripcion\": \"Banner promocional secundario\"}', 5, 1, '2026-11-11 13:10:01', '2026-11-11 13:10:01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_mayoristas`
--

CREATE TABLE `solicitudes_mayoristas` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `nombre_negocio` varchar(180) DEFAULT NULL,
  `telefono` varchar(40) NOT NULL,
  `ciudad` varchar(120) NOT NULL,
  `tipo_negocio` varchar(120) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `estado` enum('nuevo','contactado','cerrado') NOT NULL DEFAULT 'nuevo',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `acepta_marketing` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_autorizacion_marketing` datetime DEFAULT NULL,
  `estado_marketing` enum('pendiente','autorizado','retirado') NOT NULL DEFAULT 'pendiente',
  `origen_contacto` varchar(80) NOT NULL DEFAULT 'formulario_mayorista'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `variantes`
--

CREATE TABLE `variantes` (
  `id` int(10) UNSIGNED NOT NULL,
  `producto_id` int(10) UNSIGNED NOT NULL,
  `sku` varchar(80) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `atributos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`atributos`)),
  `precio_minorista` decimal(12,2) DEFAULT NULL,
  `precio_mayorista` decimal(12,2) DEFAULT NULL,
  `disponibilidad` enum('disponible','agotado') NOT NULL DEFAULT 'disponible',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_banner_imagen` (`imagen_id`),
  ADD KEY `fk_banner_imagen_movil` (`imagen_movil_id`),
  ADD KEY `idx_banner_posicion_activo` (`posicion`,`activo`),
  ADD KEY `idx_banner_orden` (`orden`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categorias_nombre` (`nombre`),
  ADD UNIQUE KEY `uq_categorias_slug` (`slug`),
  ADD KEY `idx_categorias_activo` (`activo`);

--
-- Indices de la tabla `configuracion_tienda`
--
ALTER TABLE `configuracion_tienda`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `marcas`
--
ALTER TABLE `marcas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_marcas_nombre` (`nombre`),
  ADD UNIQUE KEY `uq_marcas_slug` (`slug`),
  ADD KEY `idx_marcas_activo` (`activo`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_productos_slug` (`slug`),
  ADD UNIQUE KEY `uq_productos_sku` (`sku`),
  ADD KEY `idx_productos_categoria` (`categoria_id`),
  ADD KEY `idx_productos_marca` (`marca_id`),
  ADD KEY `idx_productos_catalogo` (`activo`,`disponibilidad`),
  ADD KEY `idx_productos_nombre` (`nombre`);

--
-- Indices de la tabla `producto_imagenes`
--
ALTER TABLE `producto_imagenes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_imagenes_producto` (`producto_id`,`orden`);

--
-- Indices de la tabla `promociones`
--
ALTER TABLE `promociones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_promociones_producto` (`producto_id`),
  ADD KEY `idx_promociones_variante` (`variante_id`),
  ADD KEY `idx_promociones_vigencia` (`activo`,`inicia_en`,`termina_en`);

--
-- Indices de la tabla `recursos_multimedia`
--
ALTER TABLE `recursos_multimedia`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `redes_sociales`
--
ALTER TABLE `redes_sociales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_redes_estado_orden` (`activo`,`orden`);

--
-- Indices de la tabla `secciones_tienda`
--
ALTER TABLE `secciones_tienda`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_seccion_identificador` (`identificador`),
  ADD KEY `idx_seccion_orden_visible` (`orden`,`visible`);

--
-- Indices de la tabla `solicitudes_mayoristas`
--
ALTER TABLE `solicitudes_mayoristas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_solicitudes_estado_fecha` (`estado`,`creado_en`),
  ADD KEY `idx_solicitudes_telefono` (`telefono`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuarios_email` (`email`);

--
-- Indices de la tabla `variantes`
--
ALTER TABLE `variantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_variantes_sku` (`sku`),
  ADD KEY `idx_variantes_producto` (`producto_id`),
  ADD KEY `idx_variantes_estado` (`producto_id`,`activo`,`disponibilidad`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `marcas`
--
ALTER TABLE `marcas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `producto_imagenes`
--
ALTER TABLE `producto_imagenes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `promociones`
--
ALTER TABLE `promociones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `recursos_multimedia`
--
ALTER TABLE `recursos_multimedia`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `redes_sociales`
--
ALTER TABLE `redes_sociales`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `secciones_tienda`
--
ALTER TABLE `secciones_tienda`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `solicitudes_mayoristas`
--
ALTER TABLE `solicitudes_mayoristas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `variantes`
--
ALTER TABLE `variantes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `banners`
--
ALTER TABLE `banners`
  ADD CONSTRAINT `fk_banner_imagen` FOREIGN KEY (`imagen_id`) REFERENCES `recursos_multimedia` (`id`),
  ADD CONSTRAINT `fk_banner_imagen_movil` FOREIGN KEY (`imagen_movil_id`) REFERENCES `recursos_multimedia` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `fk_productos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_productos_marca` FOREIGN KEY (`marca_id`) REFERENCES `marcas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `producto_imagenes`
--
ALTER TABLE `producto_imagenes`
  ADD CONSTRAINT `fk_imagenes_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `promociones`
--
ALTER TABLE `promociones`
  ADD CONSTRAINT `fk_promociones_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_promociones_variante` FOREIGN KEY (`variante_id`) REFERENCES `variantes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `variantes`
--
ALTER TABLE `variantes`
  ADD CONSTRAINT `fk_variantes_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

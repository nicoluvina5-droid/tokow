-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 11-08-2026 a las 08:07:12
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
-- Base de datos: `users`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `biblioteca_usuario`
--

CREATE TABLE `biblioteca_usuario` (
  `id_biblioteca` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_juego` int(11) NOT NULL,
  `fecha_agregado` datetime DEFAULT current_timestamp(),
  `estado` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id_categoria` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dispositivos`
--

CREATE TABLE `dispositivos` (
  `id_dispositivo` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `sistema_operativo` varchar(100) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `juegos`
--

CREATE TABLE `juegos` (
  `id_juego` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `genero` varchar(100) DEFAULT NULL,
  `desarrolladora` varchar(100) DEFAULT NULL,
  `distribuidora` varchar(100) DEFAULT NULL,
  `fecha_lanzamiento` date DEFAULT NULL,
  `portada_url` varchar(255) DEFAULT NULL,
  `requiere_mando` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `juego_categoria`
--

CREATE TABLE `juego_categoria` (
  `id_juego` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mods`
--

CREATE TABLE `mods` (
  `id_mod` int(11) NOT NULL,
  `id_juego` int(11) NOT NULL,
  `nombre` varchar(150) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `autor` varchar(100) DEFAULT NULL,
  `url_descarga` varchar(255) DEFAULT NULL,
  `fecha_publicacion` datetime DEFAULT NULL,
  `estado` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mods_usuario`
--

CREATE TABLE `mods_usuario` (
  `id_mod_usuario` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_mod` int(11) NOT NULL,
  `fecha_instalacion` datetime DEFAULT current_timestamp(),
  `estado` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL,
  `id_suscripcion` int(11) NOT NULL,
  `monto` decimal(10,2) DEFAULT NULL,
  `moneda` varchar(10) DEFAULT 'MXN',
  `fecha_pago` datetime DEFAULT current_timestamp(),
  `metodo_pago` varchar(50) DEFAULT NULL,
  `estado` varchar(30) DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `partidas_guardadas`
--

CREATE TABLE `partidas_guardadas` (
  `id_partida` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_juego` int(11) NOT NULL,
  `nombre_partida` varchar(150) DEFAULT NULL,
  `datos` longtext DEFAULT NULL,
  `fecha_guardado` datetime DEFAULT current_timestamp(),
  `dispositivo` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planes`
--

CREATE TABLE `planes` (
  `id_plan` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `duracion_meses` int(11) NOT NULL,
  `max_dispositivos` int(11) DEFAULT 1,
  `calidad_stream` varchar(20) DEFAULT '1080p',
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servidores`
--

CREATE TABLE `servidores` (
  `id_servidor` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `ubicacion` varchar(100) DEFAULT NULL,
  `proveedor` varchar(100) DEFAULT NULL,
  `gpu` varchar(100) DEFAULT NULL,
  `cpu` varchar(100) DEFAULT NULL,
  `ram_gb` int(11) DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesiones_juego`
--

CREATE TABLE `sesiones_juego` (
  `id_sesion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_juego` int(11) NOT NULL,
  `id_servidor` int(11) NOT NULL,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `duracion_min` int(11) DEFAULT NULL,
  `estado` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `suscripciones`
--

CREATE TABLE `suscripciones` (
  `id_suscripcion` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_plan` int(11) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` varchar(30) DEFAULT 'Activa',
  `metodo_pago` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(20) NOT NULL,
  `contraseña` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `contraseña`) VALUES
(1, 'leo', 'pan12'),
(2, 'leo2', 'pan12'),
(3, 'pan1', '$2y$10$Vma.fpy/QBsqf'),
(4, 'pan12', '$2y$10$DnEyNx.uxGE05'),
(5, 'panadero3000', '123');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `biblioteca_usuario`
--
ALTER TABLE `biblioteca_usuario`
  ADD PRIMARY KEY (`id_biblioteca`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_juego` (`id_juego`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Indices de la tabla `dispositivos`
--
ALTER TABLE `dispositivos`
  ADD PRIMARY KEY (`id_dispositivo`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `juegos`
--
ALTER TABLE `juegos`
  ADD PRIMARY KEY (`id_juego`);

--
-- Indices de la tabla `juego_categoria`
--
ALTER TABLE `juego_categoria`
  ADD PRIMARY KEY (`id_juego`,`id_categoria`),
  ADD KEY `id_categoria` (`id_categoria`);

--
-- Indices de la tabla `mods`
--
ALTER TABLE `mods`
  ADD PRIMARY KEY (`id_mod`),
  ADD KEY `id_juego` (`id_juego`);

--
-- Indices de la tabla `mods_usuario`
--
ALTER TABLE `mods_usuario`
  ADD PRIMARY KEY (`id_mod_usuario`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_mod` (`id_mod`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_suscripcion` (`id_suscripcion`);

--
-- Indices de la tabla `partidas_guardadas`
--
ALTER TABLE `partidas_guardadas`
  ADD PRIMARY KEY (`id_partida`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_juego` (`id_juego`);

--
-- Indices de la tabla `planes`
--
ALTER TABLE `planes`
  ADD PRIMARY KEY (`id_plan`);

--
-- Indices de la tabla `servidores`
--
ALTER TABLE `servidores`
  ADD PRIMARY KEY (`id_servidor`);

--
-- Indices de la tabla `sesiones_juego`
--
ALTER TABLE `sesiones_juego`
  ADD PRIMARY KEY (`id_sesion`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_juego` (`id_juego`),
  ADD KEY `id_servidor` (`id_servidor`);

--
-- Indices de la tabla `suscripciones`
--
ALTER TABLE `suscripciones`
  ADD PRIMARY KEY (`id_suscripcion`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_plan` (`id_plan`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `biblioteca_usuario`
--
ALTER TABLE `biblioteca_usuario`
  MODIFY `id_biblioteca` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `dispositivos`
--
ALTER TABLE `dispositivos`
  MODIFY `id_dispositivo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `juegos`
--
ALTER TABLE `juegos`
  MODIFY `id_juego` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mods`
--
ALTER TABLE `mods`
  MODIFY `id_mod` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `mods_usuario`
--
ALTER TABLE `mods_usuario`
  MODIFY `id_mod_usuario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `partidas_guardadas`
--
ALTER TABLE `partidas_guardadas`
  MODIFY `id_partida` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `planes`
--
ALTER TABLE `planes`
  MODIFY `id_plan` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `servidores`
--
ALTER TABLE `servidores`
  MODIFY `id_servidor` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `sesiones_juego`
--
ALTER TABLE `sesiones_juego`
  MODIFY `id_sesion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `suscripciones`
--
ALTER TABLE `suscripciones`
  MODIFY `id_suscripcion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `biblioteca_usuario`
--
ALTER TABLE `biblioteca_usuario`
  ADD CONSTRAINT `biblioteca_usuario_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `biblioteca_usuario_ibfk_2` FOREIGN KEY (`id_juego`) REFERENCES `juegos` (`id_juego`);

--
-- Filtros para la tabla `dispositivos`
--
ALTER TABLE `dispositivos`
  ADD CONSTRAINT `dispositivos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `juego_categoria`
--
ALTER TABLE `juego_categoria`
  ADD CONSTRAINT `juego_categoria_ibfk_1` FOREIGN KEY (`id_juego`) REFERENCES `juegos` (`id_juego`),
  ADD CONSTRAINT `juego_categoria_ibfk_2` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`);

--
-- Filtros para la tabla `mods`
--
ALTER TABLE `mods`
  ADD CONSTRAINT `mods_ibfk_1` FOREIGN KEY (`id_juego`) REFERENCES `juegos` (`id_juego`);

--
-- Filtros para la tabla `mods_usuario`
--
ALTER TABLE `mods_usuario`
  ADD CONSTRAINT `mods_usuario_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `mods_usuario_ibfk_2` FOREIGN KEY (`id_mod`) REFERENCES `mods` (`id_mod`);

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_suscripcion`) REFERENCES `suscripciones` (`id_suscripcion`);

--
-- Filtros para la tabla `partidas_guardadas`
--
ALTER TABLE `partidas_guardadas`
  ADD CONSTRAINT `partidas_guardadas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `partidas_guardadas_ibfk_2` FOREIGN KEY (`id_juego`) REFERENCES `juegos` (`id_juego`);

--
-- Filtros para la tabla `sesiones_juego`
--
ALTER TABLE `sesiones_juego`
  ADD CONSTRAINT `sesiones_juego_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `sesiones_juego_ibfk_2` FOREIGN KEY (`id_juego`) REFERENCES `juegos` (`id_juego`),
  ADD CONSTRAINT `sesiones_juego_ibfk_3` FOREIGN KEY (`id_servidor`) REFERENCES `servidores` (`id_servidor`);

--
-- Filtros para la tabla `suscripciones`
--
ALTER TABLE `suscripciones`
  ADD CONSTRAINT `suscripciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `suscripciones_ibfk_2` FOREIGN KEY (`id_plan`) REFERENCES `planes` (`id_plan`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

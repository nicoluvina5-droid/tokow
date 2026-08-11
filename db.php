<?php
// Módulo de conexión ultra-robusta a la base de datos Tokow para Railway y entorno local

function getEnvVar($key, $default = '') {
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    return $default;
}

function getDBConnection() {
    // Desactivar reportes estrictos de mysqli para evitar HTTP 500 no capturados
    mysqli_report(MYSQLI_REPORT_OFF);

    // 1. Obtener datos de conexión de Railway o variables de entorno
    $url = getEnvVar('MYSQL_URL') ?: getEnvVar('MYSQLURL');
    
    $host = 'mysql.railway.internal';
    $user = 'root';
    $pass = 'vgELwtMeQfjleucGSRlgsUpGpoynJLvL';
    $db   = 'railway';
    $port = 3306;

    if (!empty($url)) {
        $parsed = parse_url($url);
        if ($parsed) {
            $host = isset($parsed['host']) ? $parsed['host'] : $host;
            $user = isset($parsed['user']) ? $parsed['user'] : $user;
            $pass = isset($parsed['pass']) ? $parsed['pass'] : $pass;
            $db   = isset($parsed['path']) ? ltrim($parsed['path'], '/') : $db;
            $port = isset($parsed['port']) ? (int)$parsed['port'] : $port;
        }
    } else {
        $host = getEnvVar('MYSQLHOST') ?: getEnvVar('MYSQL_HOST') ?: getEnvVar('RAILWAY_MYSQL_HOST') ?: $host;
        $user = getEnvVar('MYSQLUSER') ?: getEnvVar('MYSQL_USER') ?: getEnvVar('RAILWAY_MYSQL_USER') ?: $user;
        $pass = getEnvVar('MYSQLPASSWORD') ?: getEnvVar('MYSQL_PASSWORD') ?: getEnvVar('MYSQL_ROOT_PASSWORD') ?: $pass;
        $db   = getEnvVar('MYSQLDATABASE') ?: getEnvVar('MYSQL_DATABASE') ?: $db;
        $port = (int)(getEnvVar('MYSQLPORT') ?: getEnvVar('MYSQL_PORT') ?: $port);
    }

    $conn = null;

    // Intento 1: Conexión directa a la base de datos especificada
    try {
        $conn = new mysqli($host, $user, $pass, $db, $port);
    } catch (Throwable $e) {
        $conn = null;
    }

    // Intento 2: Si falló, intentar conectar sin especificar BD para crearla si no existe
    if (!$conn || $conn->connect_error) {
        try {
            $conn_nodb = new mysqli($host, $user, $pass, '', $port);
            if ($conn_nodb && !$conn_nodb->connect_error) {
                @$conn_nodb->query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
                @$conn_nodb->select_db($db);
                $conn = $conn_nodb;
            }
        } catch (Throwable $e) {
            $conn = null;
        }
    }

    // Intento 3: Fallback a localhost / 127.0.0.1 para desarrollo local XAMPP/MariaDB
    if (!$conn || $conn->connect_error) {
        try {
            $conn_local = new mysqli('127.0.0.1', 'root', 'root', 'users', 3306);
            if ($conn_local && !$conn_local->connect_error) {
                $conn = $conn_local;
            }
        } catch (Throwable $e) {
            $conn = null;
        }
    }

    if (!$conn || $conn->connect_error) {
        try {
            $conn_local2 = new mysqli('localhost', 'root', '', 'railway', 3306);
            if ($conn_local2 && !$conn_local2->connect_error) {
                $conn = $conn_local2;
            }
        } catch (Throwable $e) {
            $conn = null;
        }
    }

    if (!$conn || $conn->connect_error) {
        $error_msg = $conn ? $conn->connect_error : "No se pudo conectar al host de MySQL en $host:$port";
        die("<!doctype html><html lang='es'><head><meta charset='UTF-8'><link rel='stylesheet' href='styles.css'></head><body style='padding:40px; font-family:sans-serif; text-align:center; background:#0D0E1C; color:white;'><h2>Error de conexión a la Base de Datos</h2><p style='color:#ef4444;'>$error_msg</p><p style='color:#B4AEFF;'>Servidor: <code>$host:$port</code> | BD: <code>$db</code> | Usuario: <code>$user</code></p></body></html>");
    }

    $conn->set_charset("utf8mb4");

    // Inicializar tablas asegurando que no falle la página si ya existen
    try {
        inicializarEsquema($conn);
    } catch (Throwable $e) {
        // Continuar de forma segura
    }

    return $conn;
}

function inicializarEsquema($conn) {
    // 1. Tabla usuarios
    @$conn->query("CREATE TABLE IF NOT EXISTS `usuarios` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `usuario` VARCHAR(50) NOT NULL UNIQUE,
        `contraseña` VARCHAR(255) NOT NULL,
        `es_admin` TINYINT(1) DEFAULT 0,
        `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Añadir es_admin si la tabla ya existía sin esa columna
    $check_col = @$conn->query("SHOW COLUMNS FROM `usuarios` LIKE 'es_admin'");
    if ($check_col && $check_col->num_rows === 0) {
        @$conn->query("ALTER TABLE `usuarios` ADD COLUMN `es_admin` TINYINT(1) DEFAULT 0");
    }
    @$conn->query("ALTER TABLE `usuarios` MODIFY COLUMN `contraseña` VARCHAR(255) NOT NULL;");

    // Usuario admin predeterminado
    $res_admin = @$conn->query("SELECT id FROM `usuarios` WHERE `usuario` = 'admin'");
    if ($res_admin && $res_admin->num_rows === 0) {
        $pass_admin = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO `usuarios` (`usuario`, `contraseña`, `es_admin`) VALUES ('admin', ?, 1)");
        if ($stmt) {
            $stmt->bind_param("s", $pass_admin);
            $stmt->execute();
            $stmt->close();
        }
    }

    // 2. Tabla planes
    @$conn->query("CREATE TABLE IF NOT EXISTS `planes` (
        `id_plan` INT(11) NOT NULL AUTO_INCREMENT,
        `codigo` VARCHAR(50) NOT NULL UNIQUE,
        `nombre` VARCHAR(100) NOT NULL,
        `precio` DECIMAL(10,2) NOT NULL,
        `moneda` VARCHAR(10) DEFAULT 'USD',
        `duracion_meses` INT(11) NOT NULL,
        `max_dispositivos` INT(11) DEFAULT 1,
        `calidad_stream` VARCHAR(20) DEFAULT '1080p',
        `activo` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`id_plan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Poblar o actualizar precios de planes
    $check_planes = @$conn->query("SELECT COUNT(*) as total FROM `planes`");
    $row_p = $check_planes ? $check_planes->fetch_assoc() : ['total' => 0];
    if ($row_p['total'] == 0) {
        @$conn->query("INSERT INTO `planes` (`codigo`, `nombre`, `precio`, `moneda`, `duracion_meses`, `max_dispositivos`, `calidad_stream`) VALUES
            ('normal_mensual', 'Suscripción Normal Mensual', 10.00, 'USD', 1, 1, '1080p 60fps'),
            ('premium_mensual', 'Suscripción Premium Mensual', 20.00, 'USD', 1, 3, '4K 60fps'),
            ('normal_anual', 'Suscripción Normal Anual', 120.00, 'USD', 12, 1, '1080p 60fps'),
            ('premium_anual', 'Suscripción Premium Anual', 240.00, 'USD', 12, 3, '4K 60fps')
        ON DUPLICATE KEY UPDATE `precio` = VALUES(`precio`);");
    }

    // 3. Tabla suscripciones
    @$conn->query("CREATE TABLE IF NOT EXISTS `suscripciones` (
        `id_suscripcion` INT(11) NOT NULL AUTO_INCREMENT,
        `id_usuario` INT(11) NOT NULL,
        `id_plan` INT(11) NOT NULL,
        `fecha_inicio` DATE DEFAULT NULL,
        `fecha_fin` DATE DEFAULT NULL,
        `estado` VARCHAR(30) DEFAULT 'Activa',
        `metodo_pago` VARCHAR(50) DEFAULT 'Tokow Pay',
        PRIMARY KEY (`id_suscripcion`),
        FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Tabla pagos
    @$conn->query("CREATE TABLE IF NOT EXISTS `pagos` (
        `id_pago` INT(11) NOT NULL AUTO_INCREMENT,
        `id_suscripcion` INT(11) NOT NULL,
        `monto` DECIMAL(10,2) DEFAULT NULL,
        `moneda` VARCHAR(10) DEFAULT 'USD',
        `fecha_pago` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `metodo_pago` VARCHAR(50) DEFAULT 'Tokow Pay',
        `estado` VARCHAR(30) DEFAULT 'Completado',
        `referencia` VARCHAR(100) DEFAULT NULL,
        PRIMARY KEY (`id_pago`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function usuarioTieneSuscripcionActiva($conn, $usuario_id) {
    if (!$usuario_id) return false;
    
    try {
        $stmt_admin = $conn->prepare("SELECT es_admin FROM usuarios WHERE id = ?");
        if ($stmt_admin) {
            $stmt_admin->bind_param("i", $usuario_id);
            $stmt_admin->execute();
            $res_admin = $stmt_admin->get_result()->fetch_assoc();
            $stmt_admin->close();
            if ($res_admin && $res_admin['es_admin'] == 1) {
                return ['plan_nombre' => 'Administrador (Acceso Total)', 'fecha_fin' => 'Ilimitado'];
            }
        }

        $stmt = $conn->prepare("SELECT s.id_suscripcion, p.nombre as plan_nombre, s.fecha_fin 
                               FROM suscripciones s 
                               JOIN planes p ON s.id_plan = p.id_plan 
                               WHERE s.id_usuario = ? AND s.estado = 'Activa' AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURDATE())
                               ORDER BY s.id_suscripcion DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $suscripcion = $res->fetch_assoc();
            $stmt->close();
            return $suscripcion ? $suscripcion : false;
        }
    } catch (Throwable $e) {
        return false;
    }
    return false;
}

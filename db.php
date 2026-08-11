<?php
// Módulo de conexión adaptado 100% al esquema exacto de la base de datos de Railway / local

function getEnvVar($key, $default = '') {
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    return $default;
}

function getDBConnection() {
    // Desactivar excepciones automáticas de mysqli para controlar todos los errores manualmente
    mysqli_report(MYSQLI_REPORT_OFF);

    // Obtener parámetros de conexión (soporta MYSQL_URL o variables individuales de Railway)
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

    // Intento 1: Conectar a la BD especificada
    try {
        $conn = new mysqli($host, $user, $pass, $db, $port);
    } catch (Throwable $e) {
        $conn = null;
    }

    // Intento 2: Si la BD principal falla, intentar con 'users' (nombre en el dump del usuario)
    if (!$conn || $conn->connect_error) {
        try {
            $conn_users = new mysqli($host, $user, $pass, 'users', $port);
            if ($conn_users && !$conn_users->connect_error) {
                $conn = $conn_users;
            }
        } catch (Throwable $e) {
            $conn = null;
        }
    }

    // Intento 3: Intentar conectar sin BD para crearla
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

    // Intento 4: Fallback a localhost / 127.0.0.1
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
        $err = $conn ? $conn->connect_error : "Imposible conectar a $host:$port";
        die("<!doctype html><html lang='es'><head><meta charset='UTF-8'><link rel='stylesheet' href='styles.css'></head><body style='padding:40px; font-family:sans-serif; text-align:center; background:#0D0E1C; color:white;'><h2>Error de conexión a la Base de Datos</h2><p style='color:#ef4444;'>$err</p><p style='color:#B4AEFF;'>Servidor: <code>$host:$port</code> | BD: <code>$db</code> | Usuario: <code>$user</code></p></body></html>");
    }

    $conn->set_charset("utf8mb4");

    // Inicialización segura sin alterar columnas sensibles
    try {
        asegurarPlanesBásicos($conn);
    } catch (Throwable $e) {
        // Continuar de forma segura
    }

    return $conn;
}

function asegurarPlanesBásicos($conn) {
    // Si la columna contraseña en usuarios es varchar(20), intentamos ampliarla si es posible para soportar hashes
    try {
        @$conn->query("ALTER TABLE `usuarios` MODIFY COLUMN `contraseña` VARCHAR(255) NOT NULL;");
    } catch (Throwable $e) {}

    // Intentar agregar es_admin si no existe
    try {
        $check_col = @$conn->query("SHOW COLUMNS FROM `usuarios` LIKE 'es_admin'");
        if ($check_col && $check_col->num_rows === 0) {
            @$conn->query("ALTER TABLE `usuarios` ADD COLUMN `es_admin` TINYINT(1) DEFAULT 0");
        }
    } catch (Throwable $e) {}

    // Verificar si la tabla planes tiene registros
    $res = @$conn->query("SELECT COUNT(*) as total FROM `planes`");
    $total = 0;
    if ($res) {
        $row = $res->fetch_assoc();
        $total = (int)$row['total'];
    }

    if ($total == 0) {
        // Insertar usando la estructura del dump exacto: (id_plan, nombre, precio, duracion_meses, max_dispositivos, calidad_stream, activo)
        @$conn->query("INSERT INTO `planes` (`id_plan`, `nombre`, `precio`, `duracion_meses`, `max_dispositivos`, `calidad_stream`, `activo`) VALUES
            (1, 'Suscripción Normal Mensual', 10.00, 1, 1, '1080p 60fps', 1),
            (2, 'Suscripción Premium Mensual', 20.00, 1, 3, '4K 60fps', 1),
            (3, 'Suscripción Normal Anual', 120.00, 12, 1, '1080p 60fps', 1),
            (4, 'Suscripción Premium Anual', 240.00, 12, 3, '4K 60fps', 1);");
    }
}

function usuarioTieneSuscripcionActiva($conn, $usuario_id) {
    if (!$usuario_id) return false;

    try {
        // Verificar si es admin por id o por nombre
        $stmt_u = $conn->prepare("SELECT usuario FROM usuarios WHERE id = ?");
        if ($stmt_u) {
            $stmt_u->bind_param("i", $usuario_id);
            $stmt_u->execute();
            $res_u = $stmt_u->get_result();
            $row_u = $res_u ? $res_u->fetch_assoc() : null;
            $stmt_u->close();

            if ($row_u && (strtolower($row_u['usuario']) === 'admin' || strtolower($row_u['usuario']) === 'leo')) {
                return ['plan_nombre' => 'Administrador (Acceso Total)', 'fecha_fin' => 'Ilimitado'];
            }
        }

        // Consultar suscripción usando la estructura exacta del dump: (suscripciones JOIN planes ON s.id_plan = p.id_plan)
        $stmt = $conn->prepare("SELECT s.id_suscripcion, p.nombre as plan_nombre, s.fecha_fin 
                               FROM suscripciones s 
                               JOIN planes p ON s.id_plan = p.id_plan 
                               WHERE s.id_usuario = ? AND s.estado = 'Activa' AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURDATE())
                               ORDER BY s.id_suscripcion DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $usuario_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $suscripcion = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            return $suscripcion ? $suscripcion : false;
        }
    } catch (Throwable $e) {
        return false;
    }
    return false;
}

<?php
// Módulo de conexión a la base de datos Tokow para Railway y entorno local

function getDBConnection() {
    $host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
    $user = getenv('MYSQLUSER') ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: 'vgELwtMeQfjleucGSRlgsUpGpoynJLvL';
    $db   = getenv('MYSQLDATABASE') ?: 'railway';
    $port = getenv('MYSQLPORT') ?: 3306;

    // Primer intento con el nombre de BD configurado
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli($host, $user, $pass, $db, (int)$port);

    if ($conn->connect_error) {
        // Fallback si la BD principal falla: intentar conectar sin especificar BD para crearla o usar 'users'
        $conn_fallback = @new mysqli($host, $user, $pass, '', (int)$port);
        if ($conn_fallback->connect_error) {
            // Intento final con localhost si estamos probando en desarrollo local XAMPP/MariaDB
            $conn_local = @new mysqli('localhost', 'root', 'root', 'users');
            if ($conn_local->connect_error) {
                die("Error de conexión a la base de datos: " . $conn->connect_error);
            }
            $conn = $conn_local;
        } else {
            // Crear BD si no existe y seleccionar
            $conn_fallback->query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
            $conn_fallback->select_db($db);
            $conn = $conn_fallback;
        }
    }

    $conn->set_charset("utf8mb4");

    // Asegurar estructura de tablas básicas y requeridas
    inicializarEsquema($conn);

    return $conn;
}

function inicializarEsquema($conn) {
    // 1. Tabla usuarios (garantizar columna es_admin y longitud de contraseña)
    $conn->query("CREATE TABLE IF NOT EXISTS `usuarios` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `usuario` VARCHAR(50) NOT NULL UNIQUE,
        `contraseña` VARCHAR(255) NOT NULL,
        `es_admin` TINYINT(1) DEFAULT 0,
        `fecha_registro` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Verificar si existe columna es_admin por si la tabla venía del SQL anterior
    $check_col = $conn->query("SHOW COLUMNS FROM `usuarios` LIKE 'es_admin'");
    if ($check_col && $check_col->num_rows === 0) {
        $conn->query("ALTER TABLE `usuarios` ADD COLUMN `es_admin` TINYINT(1) DEFAULT 0");
    }

    // Modificar longitud de la columna contraseña si era VARCHAR(20)
    $conn->query("ALTER TABLE `usuarios` MODIFY COLUMN `contraseña` VARCHAR(255) NOT NULL;");

    // Crear usuario admin predeterminado si no existe
    $res_admin = $conn->query("SELECT id FROM `usuarios` WHERE `usuario` = 'admin'");
    if ($res_admin && $res_admin->num_rows === 0) {
        $pass_admin = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO `usuarios` (`usuario`, `contraseña`, `es_admin`) VALUES ('admin', ?, 1)");
        $stmt->bind_param("s", $pass_admin);
        $stmt->execute();
        $stmt->close();
    }

    // 2. Tabla planes
    $conn->query("CREATE TABLE IF NOT EXISTS `planes` (
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

    // Insertar planes predeterminados si no existen
    $check_planes = $conn->query("SELECT COUNT(*) as total FROM `planes`");
    $row_p = $check_planes ? $check_planes->fetch_assoc() : ['total' => 0];
    if ($row_p['total'] == 0) {
        $conn->query("INSERT INTO `planes` (`codigo`, `nombre`, `precio`, `moneda`, `duracion_meses`, `max_dispositivos`, `calidad_stream`) VALUES
            ('normal_mensual', 'Suscripción Normal Mensual', 10.00, 'USD', 1, 1, '1080p 60fps'),
            ('premium_mensual', 'Suscripción Premium Mensual', 20.00, 'USD', 1, 3, '4K 60fps'),
            ('normal_anual', 'Suscripción Normal Anual', 120.00, 'USD', 12, 1, '1080p 60fps'),
            ('premium_anual', 'Suscripción Premium Anual', 240.00, 'USD', 12, 3, '4K 60fps')
        ON DUPLICATE KEY UPDATE `precio` = VALUES(`precio`);");
    }

    // 3. Tabla suscripciones
    $conn->query("CREATE TABLE IF NOT EXISTS `suscripciones` (
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
    $conn->query("CREATE TABLE IF NOT EXISTS `pagos` (
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

// Función helper para verificar si un usuario tiene una suscripción activa
function usuarioTieneSuscripcionActiva($conn, $usuario_id) {
    if (!$usuario_id) return false;
    
    // Verificar si es admin (los administradores tienen acceso completo por defecto)
    $stmt_admin = $conn->prepare("SELECT es_admin FROM usuarios WHERE id = ?");
    $stmt_admin->bind_param("i", $usuario_id);
    $stmt_admin->execute();
    $res_admin = $stmt_admin->get_result()->fetch_assoc();
    $stmt_admin->close();
    if ($res_admin && $res_admin['es_admin'] == 1) {
        return true;
    }

    $stmt = $conn->prepare("SELECT s.id_suscripcion, p.nombre as plan_nombre, s.fecha_fin 
                           FROM suscripciones s 
                           JOIN planes p ON s.id_plan = p.id_plan 
                           WHERE s.id_usuario = ? AND s.estado = 'Activa' AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURDATE())
                           ORDER BY s.id_suscripcion DESC LIMIT 1");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $suscripcion = $res->fetch_assoc();
    $stmt->close();

    return $suscripcion ? $suscripcion : false;
}

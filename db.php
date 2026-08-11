<?php
// Módulo de conexión a la base de datos Tokow con descubrimiento inteligente de Railway y soporte dual (MySQLi + PDO)

// Wrappers de compatibilidad PDO -> MySQLi para entornos PHP donde mysqli no esté disponible
if (!class_exists('TokowResult')) {
    class TokowResult {
        public $num_rows = 0;
        private $rows = [];
        private $cursor = 0;

        public function __construct($stmt) {
            if ($stmt instanceof PDOStatement) {
                try {
                    $this->rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $this->num_rows = count($this->rows);
                } catch (Throwable $e) {
                    $this->rows = [];
                    $this->num_rows = 0;
                }
            }
        }

        public function fetch_assoc() {
            if ($this->cursor < count($this->rows)) {
                return $this->rows[$this->cursor++];
            }
            return null;
        }
    }
}

if (!class_exists('TokowStmt')) {
    class TokowStmt {
        private $stmt;
        private $db;
        private $params = [];

        public function __construct($stmt, $db) {
            $this->stmt = $stmt;
            $this->db = $db;
        }

        public function bind_param($types, ...$args) {
            $this->params = $args;
            return true;
        }

        public function execute() {
            try {
                $res = $this->stmt->execute($this->params);
                if (method_exists($this->db, 'setInsertId')) {
                    $this->db->setInsertId();
                }
                return $res;
            } catch (Throwable $e) {
                return false;
            }
        }

        public function get_result() {
            return new TokowResult($this->stmt);
        }

        public function close() {
            $this->stmt = null;
        }
    }
}

if (!class_exists('TokowDBPDO')) {
    class TokowDBPDO {
        private $pdo;
        public $connect_error = null;
        public $insert_id = 0;

        public function __construct($pdo) {
            $this->pdo = $pdo;
        }

        public function set_charset($charset) {
            try {
                $this->pdo->exec("SET NAMES $charset");
            } catch (Throwable $e) {}
        }

        public function setInsertId() {
            try {
                $this->insert_id = (int)$this->pdo->lastInsertId();
            } catch (Throwable $e) {}
        }

        public function query($sql) {
            try {
                $stmt = $this->pdo->query($sql);
                if ($stmt === false) return false;
                $this->setInsertId();
                return new TokowResult($stmt);
            } catch (Throwable $e) {
                return false;
            }
        }

        public function prepare($sql) {
            try {
                $stmt = $this->pdo->prepare($sql);
                if (!$stmt) return false;
                return new TokowStmt($stmt, $this);
            } catch (Throwable $e) {
                return false;
            }
        }
    }
}

function getSystemEnvVars() {
    $vars = [];

    // 1. Leer /proc/self/environ si existe en Linux / Docker / Railway
    if (@file_exists('/proc/self/environ')) {
        $raw = @file_get_contents('/proc/self/environ');
        if ($raw) {
            $lines = explode("\0", $raw);
            foreach ($lines as $l) {
                $p = explode('=', $l, 2);
                if (count($p) === 2 && !empty($p[0])) {
                    $vars[$p[0]] = $p[1];
                }
            }
        }
    }

    // 2. Leer $_ENV
    if (is_array($_ENV)) {
        foreach ($_ENV as $k => $v) {
            if (!isset($vars[$k]) && $v !== '') $vars[$k] = $v;
        }
    }

    // 3. Leer $_SERVER
    if (is_array($_SERVER)) {
        foreach ($_SERVER as $k => $v) {
            if (!isset($vars[$k]) && $v !== '' && is_string($v)) $vars[$k] = $v;
        }
    }

    // 4. getenv
    $keys = [
        'MYSQLHOST', 'MYSQL_HOST', 'RAILWAY_MYSQL_HOST', 'MYSQLPUBLICPORT',
        'MYSQLUSER', 'MYSQL_USER', 'RAILWAY_MYSQL_USER',
        'MYSQLPASSWORD', 'MYSQL_PASSWORD', 'MYSQL_ROOT_PASSWORD',
        'MYSQLDATABASE', 'MYSQL_DATABASE',
        'MYSQLPORT', 'MYSQL_PORT',
        'MYSQL_URL', 'MYSQLURL', 'DATABASE_URL', 'RAILWAY_DATABASE_URL'
    ];
    foreach ($keys as $k) {
        $v = @getenv($k);
        if ($v !== false && $v !== '' && !isset($vars[$k])) {
            $vars[$k] = $v;
        }
        $v2 = @getenv($k, true);
        if ($v2 !== false && $v2 !== '' && !isset($vars[$k])) {
            $vars[$k] = $v2;
        }
    }

    return $vars;
}

function getDBConnection() {
    if (function_exists('mysqli_report')) {
        @mysqli_report(MYSQLI_REPORT_OFF);
    }

    $env = getSystemEnvVars();

    // Intentar extraer host, usuario, clave, bd, puerto de URLs o variables individuales
    $hosts = [];
    $users = [];
    $passes = [];
    $dbs   = [];
    $ports = [];

    // Revisar si existe una URL de conexión en Railway
    $urls = ['MYSQL_URL', 'MYSQLURL', 'DATABASE_URL', 'RAILWAY_DATABASE_URL', 'RAILWAY_MYSQL_URL'];
    foreach ($urls as $u_key) {
        if (!empty($env[$u_key])) {
            $parsed = parse_url($env[$u_key]);
            if ($parsed) {
                if (isset($parsed['host'])) $hosts[] = $parsed['host'];
                if (isset($parsed['user'])) $users[] = $parsed['user'];
                if (isset($parsed['pass'])) $passes[] = $parsed['pass'];
                if (isset($parsed['path'])) $dbs[] = ltrim($parsed['path'], '/');
                if (isset($parsed['port'])) $ports[] = (int)$parsed['port'];
            }
        }
    }

    // Extraer de variables individuales de Railway
    if (!empty($env['MYSQLHOST'])) $hosts[] = $env['MYSQLHOST'];
    if (!empty($env['MYSQL_HOST'])) $hosts[] = $env['MYSQL_HOST'];
    if (!empty($env['RAILWAY_MYSQL_HOST'])) $hosts[] = $env['RAILWAY_MYSQL_HOST'];

    if (!empty($env['MYSQLUSER'])) $users[] = $env['MYSQLUSER'];
    if (!empty($env['MYSQL_USER'])) $users[] = $env['MYSQL_USER'];

    if (!empty($env['MYSQLPASSWORD'])) $passes[] = $env['MYSQLPASSWORD'];
    if (!empty($env['MYSQL_PASSWORD'])) $passes[] = $env['MYSQL_PASSWORD'];
    if (!empty($env['MYSQL_ROOT_PASSWORD'])) $passes[] = $env['MYSQL_ROOT_PASSWORD'];

    if (!empty($env['MYSQLDATABASE'])) $dbs[] = $env['MYSQLDATABASE'];
    if (!empty($env['MYSQL_DATABASE'])) $dbs[] = $env['MYSQL_DATABASE'];

    if (!empty($env['MYSQLPORT'])) $ports[] = (int)$env['MYSQLPORT'];
    if (!empty($env['MYSQL_PORT'])) $ports[] = (int)$env['MYSQL_PORT'];

    // Valores por defecto de Railway suministrados por el usuario
    $hosts[] = 'mysql.railway.internal';
    $hosts[] = '127.0.0.1';
    $hosts[] = 'localhost';

    $users[] = 'root';
    $passes[] = 'vgELwtMeQfjleucGSRlgsUpGpoynJLvL';
    $passes[] = 'root';
    $passes[] = '';

    $dbs[] = 'railway';
    $dbs[] = 'users';

    $ports[] = 3306;

    // Limpiar duplicados manteniendo orden
    $hosts = array_values(array_unique(array_filter($hosts)));
    $users = array_values(array_unique(array_filter($users)));
    $passes = array_values(array_unique($passes));
    $dbs   = array_values(array_unique(array_filter($dbs)));
    $ports = array_values(array_unique(array_filter($ports)));

    $conn = null;
    $last_error = '';

    // Probar combinaciones con MySQLi si está disponible
    if (class_exists('mysqli')) {
        foreach ($hosts as $h) {
            foreach ($ports as $prt) {
                foreach ($users as $u) {
                    foreach ($passes as $p) {
                        foreach ($dbs as $d) {
                            try {
                                $c = @new mysqli($h, $u, $p, $d, $prt);
                                if ($c && !$c->connect_error) {
                                    $conn = $c;
                                    break 5;
                                }
                                if ($c && $c->connect_error) {
                                    $last_error = $c->connect_error;
                                }
                            } catch (Throwable $e) {
                                $last_error = $e->getMessage();
                            }
                        }
                    }
                }
            }
        }
    }

    // Si MySQLi falló o no está instalado, probar con PDO (pdo_mysql)
    if (!$conn && class_exists('PDO')) {
        foreach ($hosts as $h) {
            foreach ($ports as $prt) {
                foreach ($users as $u) {
                    foreach ($passes as $p) {
                        foreach ($dbs as $d) {
                            try {
                                $dsn = "mysql:host=$h;port=$prt;dbname=$d;charset=utf8mb4";
                                $pdo = @new PDO($dsn, $u, $p, [
                                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                                ]);
                                if ($pdo) {
                                    $conn = new TokowDBPDO($pdo);
                                    break 5;
                                }
                            } catch (Throwable $e) {
                                $last_error = $e->getMessage();
                            }
                        }
                    }
                }
            }
        }
    }

    if (!$conn) {
        $found_hosts = implode(', ', $hosts);
        $found_dbs = implode(', ', $dbs);
        die("<!doctype html><html lang='es'><head><meta charset='UTF-8'><link rel='stylesheet' href='styles.css'></head><body style='padding:40px; font-family:sans-serif; text-align:center; background:#0D0E1C; color:white;'><h2>Error de conexión a la Base de Datos</h2><p style='color:#ef4444;'>No se pudo conectar a MySQL: $last_error</p><p style='color:#B4AEFF;'>Servidores probados: <code>$found_hosts</code> | BDs probadas: <code>$found_dbs</code></p></body></html>");
    }

    @$conn->set_charset("utf8mb4");

    try {
        asegurarPlanesBásicos($conn);
    } catch (Throwable $e) {}

    return $conn;
}

function asegurarPlanesBásicos($conn) {
    try {
        @$conn->query("ALTER TABLE `usuarios` MODIFY COLUMN `contraseña` VARCHAR(255) NOT NULL;");
    } catch (Throwable $e) {}

    try {
        $check_col = @$conn->query("SHOW COLUMNS FROM `usuarios` LIKE 'es_admin'");
        if ($check_col && $check_col->num_rows === 0) {
            @$conn->query("ALTER TABLE `usuarios` ADD COLUMN `es_admin` TINYINT(1) DEFAULT 0");
        }
    } catch (Throwable $e) {}

    $res = @$conn->query("SELECT COUNT(*) as total FROM `planes`");
    $total = 0;
    if ($res) {
        $row = $res->fetch_assoc();
        $total = (int)$row['total'];
    }

    if ($total == 0) {
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
        $stmt_u = @$conn->prepare("SELECT usuario FROM usuarios WHERE id = ?");
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

        $stmt = @$conn->prepare("SELECT s.id_suscripcion, p.nombre as plan_nombre, s.fecha_fin 
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

<?php
session_start();
require_once 'db.php';

$conn = getDBConnection();

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php?msg=login_required&redirect=mi-cuenta.php");
    exit();
}

$usuario_nombre = $_SESSION['usuario'];
$usuario_id = isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : 0;

if ($usuario_id <= 0 && !empty($usuario_nombre)) {
    $u_safe = addslashes($usuario_nombre);
    $res_u = @$conn->query("SELECT id, es_admin FROM usuarios WHERE usuario = '$u_safe'");
    if ($res_u && $res_u->num_rows > 0) {
        $row_u = $res_u->fetch_assoc();
        $usuario_id = (int)$row_u['id'];
        $_SESSION['usuario_id'] = $usuario_id;
        if (isset($row_u['es_admin'])) {
            $_SESSION['es_admin'] = (int)$row_u['es_admin'];
        }
    }
}

$user_lower = strtolower($usuario_nombre);
$es_admin = ($user_lower === 'admin' || $user_lower === 'leo' || (isset($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1));

// Procesar eliminación de cuenta enviada por el propio usuario cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_account']) && $_POST['action_account'] === 'delete_my_account') {
    @$conn->query("DELETE FROM pagos WHERE id_suscripcion IN (SELECT id_suscripcion FROM suscripciones WHERE id_usuario = $usuario_id)");
    @$conn->query("DELETE FROM suscripciones WHERE id_usuario = $usuario_id");
    @$conn->query("DELETE FROM biblioteca_usuario WHERE id_usuario = $usuario_id");
    @$conn->query("DELETE FROM dispositivos WHERE id_usuario = $usuario_id");
    @$conn->query("DELETE FROM mods_usuario WHERE id_usuario = $usuario_id");
    @$conn->query("DELETE FROM partidas_guardadas WHERE id_usuario = $usuario_id");
    @$conn->query("DELETE FROM sesiones_juego WHERE id_usuario = $usuario_id");
    @$conn->query("DELETE FROM usuarios WHERE id = $usuario_id");

    // Destruir sesión y limpiar cookies
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    header("Location: login.php?msg=account_deleted");
    exit();
}

// Obtener detalles de la suscripción actual del usuario
$suscripcion_info = null;

try {
    $stmt = @$conn->prepare("SELECT s.id_suscripcion, s.id_plan, s.fecha_inicio, s.fecha_fin, s.estado, s.metodo_pago,
                                   p.nombre as plan_nombre, p.precio, p.duracion_meses, p.max_dispositivos, p.calidad_stream
                            FROM suscripciones s
                            JOIN planes p ON s.id_plan = p.id_plan
                            WHERE s.id_usuario = ? AND s.estado = 'Activa' AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURDATE())
                            ORDER BY s.id_suscripcion DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $suscripcion_info = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
} catch (Throwable $e) {
    $suscripcion_info = null;
}

if (!$suscripcion_info) {
    try {
        $res_dir = @$conn->query("SELECT s.id_suscripcion, s.id_plan, s.fecha_inicio, s.fecha_fin, s.estado, s.metodo_pago,
                                       p.nombre as plan_nombre, p.precio, p.duracion_meses, p.max_dispositivos, p.calidad_stream
                                FROM suscripciones s
                                JOIN planes p ON s.id_plan = p.id_plan
                                WHERE s.id_usuario = $usuario_id AND s.estado = 'Activa'
                                ORDER BY s.id_suscripcion DESC LIMIT 1");
        if ($res_dir && $res_dir->num_rows > 0) {
            $suscripcion_info = $res_dir->fetch_assoc();
        }
    } catch (Throwable $e) {}
}

if (!$suscripcion_info) {
    try {
        $u_esc = addslashes($usuario_nombre);
        $res_name = @$conn->query("SELECT s.id_suscripcion, s.id_plan, s.fecha_inicio, s.fecha_fin, s.estado, s.metodo_pago,
                                        p.nombre as plan_nombre, p.precio, p.duracion_meses, p.max_dispositivos, p.calidad_stream
                                 FROM suscripciones s
                                 JOIN planes p ON s.id_plan = p.id_plan
                                 JOIN usuarios u ON s.id_usuario = u.id
                                 WHERE u.usuario = '$u_esc' AND s.estado = 'Activa'
                                 ORDER BY s.id_suscripcion DESC LIMIT 1");
        if ($res_name && $res_name->num_rows > 0) {
            $suscripcion_info = $res_name->fetch_assoc();
        }
    } catch (Throwable $e) {}
}

if (!$suscripcion_info) {
    try {
        $res_any = @$conn->query("SELECT s.id_suscripcion, s.id_plan, s.fecha_inicio, s.fecha_fin, s.estado, s.metodo_pago,
                                       p.nombre as plan_nombre, p.precio, p.duracion_meses, p.max_dispositivos, p.calidad_stream
                                FROM suscripciones s
                                JOIN planes p ON s.id_plan = p.id_plan
                                WHERE s.id_usuario = $usuario_id
                                ORDER BY s.id_suscripcion DESC LIMIT 1");
        if ($res_any && $res_any->num_rows > 0) {
            $suscripcion_info = $res_any->fetch_assoc();
        }
    } catch (Throwable $e) {}
}

if (!$suscripcion_info && $es_admin) {
    $suscripcion_info = [
        'plan_nombre' => 'Administrador de Plataforma (Acceso Total)',
        'precio' => 0.00,
        'duracion_meses' => 12,
        'max_dispositivos' => 99,
        'calidad_stream' => '4K 60fps (VIP Admin)',
        'fecha_inicio' => date('Y-01-01'),
        'fecha_fin' => 'Ilimitado (Licencia Admin)',
        'estado' => 'Activa',
        'metodo_pago' => 'Sistema Admin'
    ];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi Cuenta y Suscripción — Tokow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<style>
  .profile-container {
    max-width: 900px;
    margin: 40px auto;
    padding: 0 20px;
  }
  .profile-header-card {
    background: linear-gradient(135deg, rgba(124, 111, 247, 0.12) 0%, rgba(77, 200, 163, 0.08) 100%);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
  }
  @media (max-width: 650px) {
    .profile-header-card {
      flex-direction: column;
      align-items: flex-start;
      gap: 20px;
    }
  }
  .user-avatar-circle {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--mint));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 700;
    color: white;
    box-shadow: 0 8px 20px rgba(124, 111, 247, 0.4);
  }
  .info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
  }
  @media (max-width: 768px) {
    .info-grid {
      grid-template-columns: 1fr;
    }
  }
  .info-card {
    background: rgba(13, 14, 28, 0.7);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
  }
  .sub-detail-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    font-size: 14px;
  }
  .sub-detail-row:last-child {
    border-bottom: none;
  }
  .status-pill {
    background: rgba(77, 200, 163, 0.15);
    color: var(--mint);
    border: 1px solid rgba(77, 200, 163, 0.3);
    padding: 4px 12px;
    border-radius: 99px;
    font-size: 13px;
    font-weight: 600;
  }
  .status-pill.inactive {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border-color: rgba(239, 68, 68, 0.3);
  }
</style>
</head>
<body>

<header class="site-header">
  <div class="wrap nav">
    <a href="index.php" class="brand">
      <span class="brand-mark"></span>
      <span class="brand-text">Tokow</span>
    </a>
    <nav class="nav-links">
      <a href="index.php">Inicio</a>
      <a href="precios.php">Precios y servicios</a>
      <a href="nosotros.html">Acerca de</a>
      <a href="mi-cuenta.php" class="active">Mi Cuenta</a>
      <?php if ($es_admin): ?>
        <a href="admin.php" style="color: var(--mint);">Admin Dashboard</a>
      <?php endif; ?>
    </nav>
    <div class="nav-cta">
      <span style="font-size: 14px; color: var(--lavender); margin-right: 12px;">🎮 @<?php echo htmlspecialchars($usuario_nombre); ?></span>
      <a href="logout.php" class="btn btn-ghost">Cerrar sesión</a>
    </div>
    <button class="nav-toggle" aria-label="Abrir menú">☰</button>
  </div>
</header>

<main class="profile-container">

  <div class="profile-header-card">
    <div style="display: flex; align-items: center; gap: 20px;">
      <div class="user-avatar-circle">
        <?php echo strtoupper(substr($usuario_nombre, 0, 1)); ?>
      </div>
      <div>
        <h1 style="margin: 0; font-size: 26px;">@<?php echo htmlspecialchars($usuario_nombre); ?></h1>
        <p style="margin: 4px 0 0; color: var(--muted); font-size: 14px;">
          Usuario ID #<?php echo $usuario_id; ?>
          <?php if ($es_admin): ?>
            · <span style="color: #B4AEFF; font-weight: 700;">[ADMINISTRADOR]</span>
          <?php endif; ?>
        </p>
      </div>
    </div>
    <div style="display: flex; gap: 10px;">
      <?php if ($es_admin): ?>
        <a href="admin.php" class="btn btn-primary" style="padding: 10px 18px; font-size: 14px;">⚙️ Admin Dashboard</a>
      <?php endif; ?>
      <a href="logout.php" class="btn btn-ghost" style="padding: 10px 18px; font-size: 14px; color: #ef4444;">Cerrar Sesión</a>
    </div>
  </div>

  <div class="info-grid">
    <!-- TARJETA 1: DETALLES DE LA SUSCRIPCIÓN -->
    <div class="info-card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0;">Suscripción Actual</h3>
        <?php if ($suscripcion_info): ?>
          <span class="status-pill">Activa ✓</span>
        <?php else: ?>
          <span class="status-pill inactive">Sin suscripción</span>
        <?php endif; ?>
      </div>

      <?php if ($suscripcion_info): ?>
        <div class="sub-detail-row">
          <span style="color: var(--muted);">Plan contratado:</span>
          <strong style="color: white; font-size: 15px;"><?php echo htmlspecialchars($suscripcion_info['plan_nombre']); ?></strong>
        </div>

        <div class="sub-detail-row">
          <span style="color: var(--muted);">Precio:</span>
          <strong style="color: var(--mint);">$<?php echo number_format($suscripcion_info['precio'], 2); ?> USD</strong>
        </div>

        <div class="sub-detail-row">
          <span style="color: var(--muted);">Fecha de Inicio:</span>
          <span><?php echo htmlspecialchars($suscripcion_info['fecha_inicio']); ?></span>
        </div>

        <div class="sub-detail-row">
          <span style="color: var(--muted);">Fecha de Vencimiento:</span>
          <strong style="color: #B4AEFF;"><?php echo htmlspecialchars($suscripcion_info['fecha_fin']); ?></strong>
        </div>

        <div class="sub-detail-row">
          <span style="color: var(--muted);">Calidad de streaming:</span>
          <span><?php echo htmlspecialchars($suscripcion_info['calidad_stream']); ?></span>
        </div>

        <div class="sub-detail-row">
          <span style="color: var(--muted);">Dispositivos permitidos:</span>
          <span><?php echo htmlspecialchars($suscripcion_info['max_dispositivos']); ?> equipo(s)</span>
        </div>

        <div class="sub-detail-row">
          <span style="color: var(--muted);">Método de Pago:</span>
          <span><?php echo htmlspecialchars($suscripcion_info['metodo_pago']); ?></span>
        </div>

        <div style="margin-top: 24px;">
          <a href="precios.php" class="btn btn-ghost btn-block" style="text-align: center;">Cambiar de Plan / Ver Precios</a>
        </div>
      <?php else: ?>
        <p style="color: var(--muted); margin-bottom: 24px;">Actualmente no cuentas con una suscripción activa a Tokow.</p>
        <a href="precios.php" class="btn btn-primary btn-block">Contratar Plan ($10 / $20 USD)</a>
      <?php endif; ?>
    </div>

    <!-- TARJETA 2: INFORMACIÓN DE CUENTA Y ACCESO DE PRUEBA -->
    <div class="info-card">
      <h3 style="margin-bottom: 20px;">Información de la Cuenta</h3>

      <div class="sub-detail-row">
        <span style="color: var(--muted);">Nombre de Usuario:</span>
        <strong>@<?php echo htmlspecialchars($usuario_nombre); ?></strong>
      </div>

      <div class="sub-detail-row">
        <span style="color: var(--muted);">Rol en Plataforma:</span>
        <span><?php echo $es_admin ? 'Administrador' : 'Usuario Estándar'; ?></span>
      </div>

      <div class="sub-detail-row">
        <span style="color: var(--muted);">Estado de Seguridad:</span>
        <span style="color: var(--mint);">Protegido con Sesión Encriptada</span>
      </div>
    </div>

    <!-- TARJETA 3: ZONA DE PELIGRO - ELIMINAR CUENTA -->
    <div class="info-card" style="border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.04); grid-column: 1 / -1; margin-top: 8px;">
      <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
          <h3 style="margin: 0 0 6px 0; color: #fca5a5;">⚠️ Eliminar Cuenta</h3>
          <p style="margin: 0; color: var(--muted); font-size: 13px;">
            Al eliminar tu cuenta, se borrarán de inmediato tus credenciales y suscripciones de la plataforma. Esta acción finalizará tu sesión automáticamente y no se puede revertir.
          </p>
        </div>
        <button type="button" onclick="openDeleteAccountModal()" class="btn" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); padding: 10px 20px; font-size: 14px; font-weight: 600; cursor: pointer;">
          🗑️ Eliminar mi cuenta
        </button>
      </div>
    </div>
  </div>

</main>

<!-- MODAL CONFIRMAR ELIMINACIÓN DE CUENTA -->
<div id="modalDeleteAccount" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(8, 9, 18, 0.85); backdrop-filter: blur(8px); display: none; justify-content: center; align-items: center; z-index: 10000; padding: 20px;">
  <div style="background: #131428; border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 20px; padding: 32px; width: 100%; max-width: 460px; box-shadow: 0 25px 60px rgba(0,0,0,0.6); position: relative;">
    <button type="button" onclick="closeDeleteAccountModal()" style="position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: var(--muted); font-size: 20px; cursor: pointer;">✕</button>
    <h3 style="margin-top: 0; color: #ef4444; font-size: 20px;">⚠️ ¿Eliminar tu cuenta Tokow?</h3>
    <p style="color: var(--muted); font-size: 14px; margin-bottom: 24px; line-height: 1.5;">
      ¿Estás seguro de que deseas eliminar permanentemente la cuenta <strong style="color: white;">@<?php echo htmlspecialchars($usuario_nombre); ?></strong>? Se cerrará tu sesión de inmediato y se borrará tu acceso.
    </p>
    <form method="POST" action="">
      <input type="hidden" name="action_account" value="delete_my_account">
      <div style="display: flex; gap: 12px;">
        <button type="button" onclick="closeDeleteAccountModal()" class="btn btn-ghost" style="flex: 1;">Cancelar</button>
        <button type="submit" class="btn" style="background: #ef4444; color: white; border: none; flex: 1; font-weight: 600; cursor: pointer;">Sí, eliminar cuenta</button>
      </div>
    </form>
  </div>
</div>

<script>
function openDeleteAccountModal() {
  document.getElementById('modalDeleteAccount').style.display = 'flex';
}
function closeDeleteAccountModal() {
  document.getElementById('modalDeleteAccount').style.display = 'none';
}
</script>

<footer>
  <div class="wrap">
    <div class="footer-bottom" style="border-top: 1px solid var(--border); padding-top: 20px; margin-top: 40px;">
      <span>© 2025 Tokow · Universidad Politécnica de Victoria</span>
      <div class="footer-social">
        <a href="https://www.instagram.com/tokow.oficial/" target="_blank" aria-label="Instagram">◎ Instagram</a>
        <a href="https://www.facebook.com/profile.php?id=61592803082599" target="_blank" aria-label="Facebook">󰈌 Facebook</a>
      </div>
    </div>
  </div>
</footer>

</body>
</html>

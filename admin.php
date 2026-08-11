<?php
session_start();
require_once 'db.php';

$conn = getDBConnection();

// Verificar autenticación
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario_id'])) {
    header("Location: login.php?msg=login_required&redirect=admin.php");
    exit();
}

$usuario_id = (int)$_SESSION['usuario_id'];
$user_name = strtolower($_SESSION['usuario']);
$es_admin = ($user_name === 'admin' || $user_name === 'leo' || (isset($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1));

if (!$es_admin) {
    die("<!doctype html><html lang='es'><head><link rel='stylesheet' href='styles.css'></head><body style='padding:40px; text-align:center;'><h2>Acceso Denegado</h2><p>Solo los administradores tienen permiso para ingresar a este panel.</p><a href='index.php' class='btn btn-primary'>Volver al Inicio</a></body></html>");
}

// Acción de administración: Conceder o Cancelar Suscripción de prueba
$msg_admin = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_admin'])) {
    $target_user_id = (int)$_POST['target_user_id'];
    $action = $_POST['action_admin'];

    if ($action === 'grant_premium' && $target_user_id) {
        $id_plan = 2; // Premium Mensual
        @$conn->query("UPDATE suscripciones SET estado = 'Expirada' WHERE id_usuario = $target_user_id");
        $fin = date('Y-m-d', strtotime('+1 month'));
        @$conn->query("INSERT INTO suscripciones (id_usuario, id_plan, fecha_inicio, fecha_fin, estado, metodo_pago) VALUES ($target_user_id, $id_plan, CURDATE(), '$fin', 'Activa', 'Admin Granted')");
        $msg_admin = "Se otorgó la Suscripción Premium al usuario ID #$target_user_id con éxito.";
    } elseif ($action === 'grant_normal' && $target_user_id) {
        $id_plan = 1; // Normal Mensual
        @$conn->query("UPDATE suscripciones SET estado = 'Expirada' WHERE id_usuario = $target_user_id");
        $fin = date('Y-m-d', strtotime('+1 month'));
        @$conn->query("INSERT INTO suscripciones (id_usuario, id_plan, fecha_inicio, fecha_fin, estado, metodo_pago) VALUES ($target_user_id, $id_plan, CURDATE(), '$fin', 'Activa', 'Admin Granted')");
        $msg_admin = "Se otorgó la Suscripción Normal al usuario ID #$target_user_id con éxito.";
    } elseif ($action === 'revoke' && $target_user_id) {
        @$conn->query("UPDATE suscripciones SET estado = 'Expirada' WHERE id_usuario = $target_user_id");
        $msg_admin = "Se revocó la suscripción del usuario ID #$target_user_id.";
    }
}

// Consultas compatibles con el esquema SQL exacto del dump
$res_users = @$conn->query("SELECT COUNT(*) as total FROM usuarios");
$total_usuarios = $res_users ? (int)$res_users->fetch_assoc()['total'] : 0;

$res_subs = @$conn->query("SELECT COUNT(*) as total FROM suscripciones WHERE estado = 'Activa' AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())");
$total_suscripciones_activas = $res_subs ? (int)$res_subs->fetch_assoc()['total'] : 0;

$res_rev = @$conn->query("SELECT SUM(monto) as total FROM pagos WHERE estado = 'Completado'");
$total_ingresos = $res_rev ? (float)($res_rev->fetch_assoc()['total'] ?: 0) : 0;

$res_pagos_count = @$conn->query("SELECT COUNT(*) as total FROM pagos");
$total_pagos = $res_pagos_count ? (int)$res_pagos_count->fetch_assoc()['total'] : 0;

// Histórico de pagos
$res_ultimos_pagos = @$conn->query("SELECT p.*, s.id_usuario, u.usuario, pl.nombre as plan_nombre 
    FROM pagos p 
    JOIN suscripciones s ON p.id_suscripcion = s.id_suscripcion 
    JOIN usuarios u ON s.id_usuario = u.id 
    JOIN planes pl ON s.id_plan = pl.id_plan 
    ORDER BY p.id_pago DESC LIMIT 10");

// Lista de usuarios y suscripción (usando solo id y usuario)
$res_lista_usuarios = @$conn->query("SELECT u.id, u.usuario, s.estado as sub_estado, pl.nombre as plan_nombre, s.fecha_fin 
    FROM usuarios u 
    LEFT JOIN suscripciones s ON u.id = s.id_usuario AND s.estado = 'Activa' 
    LEFT JOIN planes pl ON s.id_plan = pl.id_plan 
    ORDER BY u.id DESC");
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard de Administración — Tokow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<style>
  .admin-header {
    background: rgba(13, 14, 28, 0.9);
    border-bottom: 1px solid var(--border);
    padding: 16px 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  .admin-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 32px;
  }
  @media (max-width: 900px) {
    .admin-grid { grid-template-columns: repeat(2, 1fr); }
  }
  @media (max-width: 550px) {
    .admin-grid { grid-template-columns: 1fr; }
  }
  .stat-card {
    background: rgba(124, 111, 247, 0.06);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
  }
  .stat-number {
    font-size: 32px;
    font-weight: 700;
    margin-top: 8px;
    color: white;
  }
  .stat-number.green { color: var(--mint); }
  .stat-number.purple { color: #B4AEFF; }
  
  .admin-section {
    background: rgba(13, 14, 28, 0.7);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 32px;
  }
  .data-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
    font-size: 14px;
  }
  .data-table th, .data-table td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid rgba(255,255,255,0.06);
  }
  .data-table th {
    background: rgba(255,255,255,0.03);
    color: var(--muted);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 12px;
  }
  .badge-active {
    background: rgba(77, 200, 163, 0.15);
    color: #4DC8A3;
    padding: 4px 10px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
  }
  .badge-inactive {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    padding: 4px 10px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
  }
  .badge-admin {
    background: rgba(124, 111, 247, 0.2);
    color: #B4AEFF;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
  }
  .action-btn-sm {
    padding: 4px 10px;
    font-size: 12px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    margin-right: 4px;
  }
</style>
</head>
<body>

<header class="admin-header">
  <div style="display:flex; align-items:center; gap:16px;">
    <a href="index.php" class="brand">
      <span class="brand-mark"></span>
      <span class="brand-text">Tokow Admin</span>
    </a>
    <span class="badge-admin">DASHBOARD DE PLATAFORMA</span>
  </div>
  <div style="display:flex; align-items:center; gap:16px;">
    <a href="play.php" class="btn btn-ghost" style="padding: 8px 16px; font-size: 13px;">🎮 Ir a la App (Play)</a>
    <a href="logout.php" style="color: #ef4444; text-decoration: none; font-size: 13px;">Cerrar Sesión</a>
  </div>
</header>

<main class="wrap" style="margin-top: 32px;">
  <div style="margin-bottom: 24px;">
    <h1>Dashboard General de Datos</h1>
    <p style="color: var(--muted);">Métricas en tiempo real de usuarios, suscripciones y volumen transaccional simulado en Railway.</p>
  </div>

  <?php if (!empty($msg_admin)): ?>
    <div style="background: rgba(77, 200, 163, 0.15); border: 1px solid var(--mint); color: var(--mint); padding: 12px 20px; border-radius: 12px; margin-bottom: 24px;">
      ✓ <?php echo htmlspecialchars($msg_admin); ?>
    </div>
  <?php endif; ?>

  <div class="admin-grid">
    <div class="stat-card">
      <span style="font-size: 13px; color: var(--muted); text-transform: uppercase;">Total Usuarios</span>
      <div class="stat-number purple"><?php echo number_format($total_usuarios); ?></div>
      <span style="font-size: 12px; color: var(--muted);">Cuentas registradas</span>
    </div>
    
    <div class="stat-card">
      <span style="font-size: 13px; color: var(--muted); text-transform: uppercase;">Suscripciones Activas</span>
      <div class="stat-number green"><?php echo number_format($total_suscripciones_activas); ?></div>
      <span style="font-size: 12px; color: var(--muted);">Usuarios con acceso play</span>
    </div>

    <div class="stat-card">
      <span style="font-size: 13px; color: var(--muted); text-transform: uppercase;">Ingresos Simulados</span>
      <div class="stat-number green">$<?php echo number_format($total_ingresos, 2); ?> <span style="font-size:16px;">USD</span></div>
      <span style="font-size: 12px; color: var(--muted);">Volumen procesado</span>
    </div>

    <div class="stat-card">
      <span style="font-size: 13px; color: var(--muted); text-transform: uppercase;">Pagos en Tokow Pay</span>
      <div class="stat-number purple"><?php echo number_format($total_pagos); ?></div>
      <span style="font-size: 12px; color: var(--muted);">Transacciones exitosas</span>
    </div>
  </div>

  <div class="admin-section">
    <h3 style="margin-bottom: 8px;">Gestión de Usuarios y Estado de Suscripción</h3>
    <p style="color: var(--muted); font-size: 13px; margin-bottom: 16px;">Permite verificar el plan activo de cada usuario u otorgar/revocar accesos directos para pruebas.</p>

    <div style="overflow-x: auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Usuario</th>
            <th>Rol</th>
            <th>Estado Suscripción</th>
            <th>Plan Actual</th>
            <th>Vencimiento</th>
            <th>Acciones de Administración</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($res_lista_usuarios && $res_lista_usuarios->num_rows > 0): ?>
            <?php while ($usr = $res_lista_usuarios->fetch_assoc()): ?>
              <tr>
                <td>#<?php echo $usr['id']; ?></td>
                <td><strong>@<?php echo htmlspecialchars($usr['usuario']); ?></strong></td>
                <td>
                  <?php if (strtolower($usr['usuario']) === 'admin' || strtolower($usr['usuario']) === 'leo'): ?>
                    <span class="badge-admin">ADMIN</span>
                  <?php else: ?>
                    <span style="color: var(--muted);">Usuario</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($usr['sub_estado'] === 'Activa'): ?>
                    <span class="badge-active">Activa ✓</span>
                  <?php else: ?>
                    <span class="badge-inactive">Sin suscripción</span>
                  <?php endif; ?>
                </td>
                <td><?php echo $usr['plan_nombre'] ? htmlspecialchars($usr['plan_nombre']) : '—'; ?></td>
                <td><?php echo $usr['fecha_fin'] ? htmlspecialchars($usr['fecha_fin']) : '—'; ?></td>
                <td>
                  <form method="POST" action="" style="display:inline-block;">
                    <input type="hidden" name="target_user_id" value="<?php echo $usr['id']; ?>">
                    <button type="submit" name="action_admin" value="grant_normal" class="action-btn-sm" style="background: rgba(124, 111, 247, 0.2); color: #B4AEFF;">+ Normal ($10)</button>
                    <button type="submit" name="action_admin" value="grant_premium" class="action-btn-sm" style="background: rgba(77, 200, 163, 0.2); color: #4DC8A3;">+ Premium ($20)</button>
                    <?php if ($usr['sub_estado'] === 'Activa'): ?>
                      <button type="submit" name="action_admin" value="revoke" class="action-btn-sm" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5;">Revocar</button>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" style="text-align:center;">No hay usuarios registrados aún.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="admin-section">
    <h3 style="margin-bottom: 8px;">Histórico de Pagos (Tokow Pay)</h3>
    <p style="color: var(--muted); font-size: 13px; margin-bottom: 16px;">Últimas 10 transacciones simuladas procesadas por la plataforma.</p>

    <div style="overflow-x: auto;">
      <table class="data-table">
        <thead>
          <tr>
            <th>Referencia</th>
            <th>Usuario</th>
            <th>Plan Adquirido</th>
            <th>Monto</th>
            <th>Método</th>
            <th>Estado</th>
            <th>Fecha de Pago</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($res_ultimos_pagos && $res_ultimos_pagos->num_rows > 0): ?>
            <?php while ($pago = $res_ultimos_pagos->fetch_assoc()): ?>
              <tr>
                <td style="font-family: monospace; color: var(--mint);"><?php echo htmlspecialchars($pago['referencia']); ?></td>
                <td>@<?php echo htmlspecialchars($pago['usuario']); ?></td>
                <td><?php echo htmlspecialchars($pago['plan_nombre']); ?></td>
                <td><strong>$<?php echo number_format($pago['monto'], 2); ?> USD</strong></td>
                <td><?php echo htmlspecialchars($pago['metodo_pago']); ?></td>
                <td><span class="badge-active"><?php echo htmlspecialchars($pago['estado']); ?></span></td>
                <td><?php echo htmlspecialchars($pago['fecha_pago']); ?></td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7" style="text-align:center; color: var(--muted);">No hay transacciones registradas aún. Al realizar una compra en Tokow Pay aparecerá aquí.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<footer>
  <div class="wrap">
    <div class="footer-bottom" style="border-top: 1px solid var(--border); padding-top: 20px; margin-top: 40px;">
      <span>© 2025 Tokow Admin · Universidad Politécnica de Victoria</span>
      <div class="footer-social">
        <a href="https://www.instagram.com/tokow.oficial/" target="_blank" aria-label="Instagram">◎ Instagram</a>
        <a href="https://www.facebook.com/profile.php?id=61592803082599" target="_blank" aria-label="Facebook">󰈌 Facebook</a>
      </div>
    </div>
  </div>
</footer>

</body>
</html>

<?php
session_start();
require_once 'db.php';

$conn = getDBConnection();

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

$msg_admin = '';
$error_admin = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_admin'])) {
    $action = $_POST['action_admin'];
    $target_user_id = (int)($_POST['target_user_id'] ?? 0);

    if ($action === 'create_user') {
        $username = trim($_POST['usuario'] ?? '');
        $password = trim($_POST['contrasena'] ?? '');
        $es_adm = isset($_POST['es_admin']) ? (int)$_POST['es_admin'] : 0;

        if (!empty($username) && !empty($password)) {
            $stmt_check = @$conn->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            if ($stmt_check) {
                $stmt_check->bind_param("s", $username);
                $stmt_check->execute();
                $res_chk = $stmt_check->get_result();
                if ($res_chk && $res_chk->num_rows > 0) {
                    $error_admin = "El nombre de usuario '@" . htmlspecialchars($username) . "' ya está registrado.";
                } else {
                    $hash_pass = password_hash($password, PASSWORD_BCRYPT);
                    $stmt_ins = @$conn->prepare("INSERT INTO usuarios (usuario, contraseña, es_admin) VALUES (?, ?, ?)");
                    if ($stmt_ins) {
                        $stmt_ins->bind_param("ssi", $username, $hash_pass, $es_adm);
                        if ($stmt_ins->execute()) {
                            $msg_admin = "Usuario '@" . htmlspecialchars($username) . "' creado exitosamente como " . ($es_adm ? "Administrador" : "Cliente") . ".";
                        } else {
                            $error_admin = "Error al registrar el usuario en la base de datos.";
                        }
                        $stmt_ins->close();
                    } else {
                        $error_admin = "Error preparando la consulta de inserción.";
                    }
                }
                $stmt_check->close();
            }
        } else {
            $error_admin = "Por favor completa todos los campos requeridos para crear el usuario.";
        }
    } elseif ($action === 'edit_user') {
        $edit_id = (int)($_POST['edit_user_id'] ?? 0);
        $username = trim($_POST['usuario'] ?? '');
        $password = trim($_POST['contrasena'] ?? '');
        $es_adm = isset($_POST['es_admin']) ? (int)$_POST['es_admin'] : 0;

        if ($edit_id > 0 && !empty($username)) {
            $stmt_check = @$conn->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
            $is_dup = false;
            if ($stmt_check) {
                $stmt_check->bind_param("si", $username, $edit_id);
                $stmt_check->execute();
                $res_chk = $stmt_check->get_result();
                if ($res_chk && $res_chk->num_rows > 0) {
                    $is_dup = true;
                    $error_admin = "El nombre de usuario '@" . htmlspecialchars($username) . "' ya pertenece a otra cuenta.";
                }
                $stmt_check->close();
            }

            if (!$is_dup) {
                if (!empty($password)) {
                    $hash_pass = password_hash($password, PASSWORD_BCRYPT);
                    $stmt_upd = @$conn->prepare("UPDATE usuarios SET usuario = ?, contraseña = ?, es_admin = ? WHERE id = ?");
                    if ($stmt_upd) {
                        $stmt_upd->bind_param("ssii", $username, $hash_pass, $es_adm, $edit_id);
                        $stmt_upd->execute();
                        $stmt_upd->close();
                    }
                } else {
                    $stmt_upd = @$conn->prepare("UPDATE usuarios SET usuario = ?, es_admin = ? WHERE id = ?");
                    if ($stmt_upd) {
                        $stmt_upd->bind_param("sii", $username, $es_adm, $edit_id);
                        $stmt_upd->execute();
                        $stmt_upd->close();
                    }
                }

                if ($edit_id === $usuario_id) {
                    $_SESSION['usuario'] = $username;
                    $_SESSION['es_admin'] = $es_adm;
                }

                $msg_admin = "Usuario ID #$edit_id (" . htmlspecialchars($username) . ") actualizado con éxito.";
            }
        } else {
            $error_admin = "El ID de usuario y nombre son obligatorios.";
        }
    } elseif ($action === 'delete_user' && $target_user_id) {
        @$conn->query("DELETE FROM suscripciones WHERE id_usuario = $target_user_id");
        @$conn->query("DELETE FROM usuarios WHERE id = $target_user_id");
        $msg_admin = "Usuario ID #$target_user_id eliminado correctamente.";
    } elseif ($action === 'grant_premium' && $target_user_id) {
        $id_plan = 2;
        @$conn->query("UPDATE suscripciones SET estado = 'Expirada' WHERE id_usuario = $target_user_id");
        $fin = date('Y-m-d', strtotime('+1 month'));
        @$conn->query("INSERT INTO suscripciones (id_usuario, id_plan, fecha_inicio, fecha_fin, estado, metodo_pago) VALUES ($target_user_id, $id_plan, CURDATE(), '$fin', 'Activa', 'Admin Granted')");
        $msg_admin = "Se otorgó la Suscripción Premium al usuario ID #$target_user_id con éxito.";
    } elseif ($action === 'grant_normal' && $target_user_id) {
        $id_plan = 1;
        @$conn->query("UPDATE suscripciones SET estado = 'Expirada' WHERE id_usuario = $target_user_id");
        $fin = date('Y-m-d', strtotime('+1 month'));
        @$conn->query("INSERT INTO suscripciones (id_usuario, id_plan, fecha_inicio, fecha_fin, estado, metodo_pago) VALUES ($target_user_id, $id_plan, CURDATE(), '$fin', 'Activa', 'Admin Granted')");
        $msg_admin = "Se otorgó la Suscripción Normal al usuario ID #$target_user_id con éxito.";
    } elseif ($action === 'revoke' && $target_user_id) {
        @$conn->query("UPDATE suscripciones SET estado = 'Expirada' WHERE id_usuario = $target_user_id");
        $msg_admin = "Se revocó la suscripción del usuario ID #$target_user_id.";
    }
}

$res_users = @$conn->query("SELECT COUNT(*) as total FROM usuarios");
$total_usuarios = $res_users ? (int)$res_users->fetch_assoc()['total'] : 0;

$res_subs = @$conn->query("SELECT COUNT(*) as total FROM suscripciones WHERE estado = 'Activa' AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())");
$total_suscripciones_activas = $res_subs ? (int)$res_subs->fetch_assoc()['total'] : 0;

$res_rev = @$conn->query("SELECT SUM(monto) as total FROM pagos WHERE estado = 'Completado'");
$total_ingresos = $res_rev ? (float)($res_rev->fetch_assoc()['total'] ?: 0) : 0;

$res_pagos_count = @$conn->query("SELECT COUNT(*) as total FROM pagos");
$total_pagos = $res_pagos_count ? (int)$res_pagos_count->fetch_assoc()['total'] : 0;

$res_ultimos_pagos = @$conn->query("SELECT p.*, s.id_usuario, u.usuario, pl.nombre as plan_nombre 
    FROM pagos p 
    JOIN suscripciones s ON p.id_suscripcion = s.id_suscripcion 
    JOIN usuarios u ON s.id_usuario = u.id 
    JOIN planes pl ON s.id_plan = pl.id_plan 
    ORDER BY p.id_pago DESC LIMIT 10");

$res_lista_usuarios = @$conn->query("SELECT u.id, u.usuario, u.es_admin, s.estado as sub_estado, pl.nombre as plan_nombre, s.fecha_fin 
    FROM usuarios u 
    LEFT JOIN (
        SELECT s1.* FROM suscripciones s1
        INNER JOIN (
            SELECT id_usuario, MAX(id_suscripcion) as max_id 
            FROM suscripciones 
            WHERE estado = 'Activa' 
            GROUP BY id_usuario
        ) s2 ON s1.id_suscripcion = s2.max_id
    ) s ON u.id = s.id_usuario 
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
  .modal-overlay {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(8, 9, 18, 0.85);
    backdrop-filter: blur(8px);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 10000;
    padding: 20px;
  }
  .modal-overlay.active {
    display: flex;
  }
  .modal-card {
    background: #131428;
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 32px;
    width: 100%;
    max-width: 480px;
    box-shadow: 0 25px 60px rgba(0,0,0,0.6);
    position: relative;
  }
  .modal-card h3 {
    margin-top: 0;
    margin-bottom: 8px;
  }
  .modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    background: transparent;
    border: none;
    color: var(--muted);
    font-size: 20px;
    cursor: pointer;
  }
  .form-group {
    margin-bottom: 18px;
  }
  .form-group label {
    display: block;
    font-size: 13px;
    color: var(--muted);
    margin-bottom: 6px;
  }
  .form-group input, .form-group select {
    width: 100%;
    padding: 10px 14px;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: white;
    font-size: 14px;
    box-sizing: border-box;
  }
  .form-group input:focus, .form-group select:focus {
    outline: none;
    border-color: var(--accent);
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
    <a href="mi-cuenta.php" class="btn btn-ghost" style="padding: 8px 16px; font-size: 13px;">👤 Mi Cuenta</a>
    <a href="precios.php" class="btn btn-ghost" style="padding: 8px 16px; font-size: 13px;">Ver Precios</a>
    <a href="logout.php" style="color: #ef4444; text-decoration: none; font-size: 13px;">Cerrar Sesión</a>
  </div>
</header>

<main class="wrap" style="margin-top: 32px;">
  <div style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 16px;">
    <div>
      <h1>Dashboard General de Datos</h1>
      <p style="color: var(--muted);">Métricas en tiempo real de usuarios, suscripciones y volumen transaccional.</p>
    </div>
    <div style="background: rgba(255,255,255,0.05); border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; font-size: 13px; color: var(--lavender);">
      ⚙️ Motor de Base de Datos: <strong style="color: var(--mint);"><?php echo htmlspecialchars(getDBConnectionType()); ?></strong>
    </div>
  </div>

  <?php if (!empty($msg_admin)): ?>
    <div style="background: rgba(77, 200, 163, 0.15); border: 1px solid var(--mint); color: var(--mint); padding: 12px 20px; border-radius: 12px; margin-bottom: 24px;">
      ✓ <?php echo $msg_admin; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($error_admin)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; padding: 12px 20px; border-radius: 12px; margin-bottom: 24px;">
      ⚠️ <?php echo $error_admin; ?>
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
      <span style="font-size: 12px; color: var(--muted);">Usuarios con acceso</span>
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
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
      <div>
        <h3 style="margin: 0 0 4px 0;">Gestión de Usuarios y Estado de Suscripción</h3>
        <p style="color: var(--muted); font-size: 13px; margin: 0;">Crea nuevos administradores o clientes, edita cuentas existentes u otorga/revocar accesos.</p>
      </div>
      <button type="button" onclick="openAddModal()" class="btn btn-primary" style="padding: 10px 20px; font-size: 13px;">
        ➕ Añadir Nuevo Usuario
      </button>
    </div>

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
              <?php $usr_is_admin = (int)($usr['es_admin'] ?? 0) === 1 || strtolower($usr['usuario']) === 'admin' || strtolower($usr['usuario']) === 'leo'; ?>
              <tr>
                <td>#<?php echo $usr['id']; ?></td>
                <td><strong>@<?php echo htmlspecialchars($usr['usuario']); ?></strong></td>
                <td>
                  <?php if ($usr_is_admin): ?>
                    <span class="badge-admin">ADMIN</span>
                  <?php else: ?>
                    <span style="background: rgba(255,255,255,0.08); color: var(--muted); padding:2px 8px; border-radius:4px; font-size:11px; font-weight:700;">CLIENTE</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($usr['sub_estado'] === 'Activa' || $usr_is_admin): ?>
                    <span class="badge-active">Activa ✓</span>
                  <?php else: ?>
                    <span class="badge-inactive">Sin suscripción</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php 
                    if ($usr['plan_nombre'] && $usr['plan_nombre'] !== '—') {
                        echo htmlspecialchars($usr['plan_nombre']);
                    } elseif ($usr_is_admin) {
                        echo 'Acceso Admin VIP';
                    } else {
                        echo '—';
                    }
                  ?>
                </td>
                <td>
                  <?php 
                    if ($usr['fecha_fin'] && $usr['fecha_fin'] !== '—') {
                        echo htmlspecialchars($usr['fecha_fin']);
                    } elseif ($usr_is_admin) {
                        echo 'Ilimitado';
                    } else {
                        echo '—';
                    }
                  ?>
                </td>
                <td>
                  <div style="display: flex; gap: 4px; align-items: center; flex-wrap: wrap;">
                    <button type="button" onclick="openEditModal(<?php echo $usr['id']; ?>, '<?php echo htmlspecialchars($usr['usuario'], ENT_QUOTES); ?>', <?php echo $usr_is_admin ? 1 : 0; ?>)" class="action-btn-sm" style="background: rgba(124, 111, 247, 0.25); color: #B4AEFF;">✏️ Editar</button>
                    <form method="POST" action="" style="display:inline-block;" onsubmit="return confirm('¿Seguro que deseas eliminar a @<?php echo htmlspecialchars($usr['usuario'], ENT_QUOTES); ?>?');">
                      <input type="hidden" name="target_user_id" value="<?php echo $usr['id']; ?>">
                      <button type="submit" name="action_admin" value="delete_user" class="action-btn-sm" style="background: rgba(239, 68, 68, 0.15); color: #fca5a5;">🗑️</button>
                    </form>
                    <form method="POST" action="" style="display:inline-block;">
                      <input type="hidden" name="target_user_id" value="<?php echo $usr['id']; ?>">
                      <button type="submit" name="action_admin" value="grant_normal" class="action-btn-sm" style="background: rgba(124, 111, 247, 0.2); color: #B4AEFF;">+ Normal ($10)</button>
                      <button type="submit" name="action_admin" value="grant_premium" class="action-btn-sm" style="background: rgba(77, 200, 163, 0.2); color: #4DC8A3;">+ Premium ($20)</button>
                      <?php if ($usr['sub_estado'] === 'Activa'): ?>
                        <button type="submit" name="action_admin" value="revoke" class="action-btn-sm" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5;">Revocar</button>
                      <?php endif; ?>
                    </form>
                  </div>
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

<!-- MODAL AÑADIR USUARIO -->
<div id="modalAddUser" class="modal-overlay">
  <div class="modal-card">
    <button class="modal-close" onclick="closeAddModal()">✕</button>
    <h3>➕ Crear Nuevo Usuario</h3>
    <p style="color: var(--muted); font-size: 13px; margin-bottom: 20px;">Añade un nuevo usuario cliente o administrador al sistema.</p>
    <form method="POST" action="">
      <input type="hidden" name="action_admin" value="create_user">
      <div class="form-group">
        <label for="add_user">Nombre de usuario</label>
        <input type="text" id="add_user" name="usuario" placeholder="Ej. juan_perez" required>
      </div>
      <div class="form-group">
        <label for="add_pass">Contraseña</label>
        <input type="password" id="add_pass" name="contrasena" placeholder="••••••••" required>
      </div>
      <div class="form-group">
        <label for="add_role">Rol en la plataforma</label>
        <select id="add_role" name="es_admin">
          <option value="0">Cliente (Usuario Estándar)</option>
          <option value="1">Administrador (Acceso Total)</option>
        </select>
      </div>
      <div style="display: flex; gap: 12px; margin-top: 24px;">
        <button type="button" onclick="closeAddModal()" class="btn btn-ghost" style="flex: 1;">Cancelar</button>
        <button type="submit" class="btn btn-primary" style="flex: 1;">Crear Usuario</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL EDITAR USUARIO -->
<div id="modalEditUser" class="modal-overlay">
  <div class="modal-card">
    <button class="modal-close" onclick="closeEditModal()">✕</button>
    <h3>✏️ Editar Usuario</h3>
    <p style="color: var(--muted); font-size: 13px; margin-bottom: 20px;">Modifica los detalles del usuario o actualiza su rol / contraseña.</p>
    <form method="POST" action="">
      <input type="hidden" name="action_admin" value="edit_user">
      <input type="hidden" id="edit_user_id" name="edit_user_id" value="">
      <div class="form-group">
        <label for="edit_user">Nombre de usuario</label>
        <input type="text" id="edit_user" name="usuario" required>
      </div>
      <div class="form-group">
        <label for="edit_pass">Nueva Contraseña <span style="font-size: 11px; color: var(--muted);">(dejar en blanco para mantener la actual)</span></label>
        <input type="password" id="edit_pass" name="contrasena" placeholder="Opcional">
      </div>
      <div class="form-group">
        <label for="edit_role">Rol en la plataforma</label>
        <select id="edit_role" name="es_admin">
          <option value="0">Cliente (Usuario Estándar)</option>
          <option value="1">Administrador (Acceso Total)</option>
        </select>
      </div>
      <div style="display: flex; gap: 12px; margin-top: 24px;">
        <button type="button" onclick="closeEditModal()" class="btn btn-ghost" style="flex: 1;">Cancelar</button>
        <button type="submit" class="btn btn-primary" style="flex: 1;">Guardar Cambios</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
  document.getElementById('modalAddUser').classList.add('active');
}
function closeAddModal() {
  document.getElementById('modalAddUser').classList.remove('active');
}
function openEditModal(id, usuario, esAdmin) {
  document.getElementById('edit_user_id').value = id;
  document.getElementById('edit_user').value = usuario;
  document.getElementById('edit_pass').value = '';
  document.getElementById('edit_role').value = esAdmin ? "1" : "0";
  document.getElementById('modalEditUser').classList.add('active');
}
function closeEditModal() {
  document.getElementById('modalEditUser').classList.remove('active');
}
</script>

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

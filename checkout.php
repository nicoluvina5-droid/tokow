<?php
session_start();
require_once 'db.php';

$conn = getDBConnection();

// Verificar inicio de sesión obligatorio
if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario_id'])) {
    $plan_requested = isset($_GET['plan']) ? $_GET['plan'] : 'normal_mensual';
    header("Location: login.php?msg=login_required&redirect=" . urlencode("checkout.php?plan=" . $plan_requested));
    exit();
}

$user_id = $_SESSION['usuario_id'];
$usuario = $_SESSION['usuario'];

// Obtener plan seleccionado
$plan_code = isset($_GET['plan']) ? trim($_GET['plan']) : 'normal_mensual';

$stmt_plan = $conn->prepare("SELECT * FROM planes WHERE codigo = ? AND activo = 1");
$stmt_plan->bind_param("s", $plan_code);
$stmt_plan->execute();
$res_plan = $stmt_plan->get_result();
$plan = $res_plan->fetch_assoc();
$stmt_plan->close();

if (!$plan) {
    // Si no se encuentra, usar por defecto normal_mensual
    $stmt_default = $conn->prepare("SELECT * FROM planes WHERE codigo = 'normal_mensual'");
    $stmt_default->execute();
    $plan = $stmt_default->get_result()->fetch_assoc();
    $stmt_default->close();
}

$pago_exitoso = false;
$referencia_pago = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['procesar_pago'])) {
    $nombre_titular = trim($_POST['card_name']);
    $numero_tarjeta = trim($_POST['card_number']);
    
    // Calcular fechas
    $fecha_inicio = date('Y-m-d');
    $duracion_meses = (int)$plan['duracion_meses'];
    $fecha_fin = date('Y-m-d', strtotime("+$duracion_meses months"));

    // Cancelar cualquier suscripción previa activa para evitar duplicados
    $stmt_cancel = $conn->prepare("UPDATE suscripciones SET estado = 'Expirada' WHERE id_usuario = ? AND estado = 'Activa'");
    $stmt_cancel->bind_param("i", $user_id);
    $stmt_cancel->execute();
    $stmt_cancel->close();

    // Registrar nueva suscripción
    $stmt_sub = $conn->prepare("INSERT INTO suscripciones (id_usuario, id_plan, fecha_inicio, fecha_fin, estado, metodo_pago) VALUES (?, ?, ?, ?, 'Activa', 'Tokow Pay (Simulado)')");
    $stmt_sub->bind_param("iiss", $user_id, $plan['id_plan'], $fecha_inicio, $fecha_fin);
    
    if ($stmt_sub->execute()) {
        $id_suscripcion = $stmt_sub->insert_id;
        $stmt_sub->close();

        // Registrar el pago
        $referencia_pago = 'TKW-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 10));
        $monto = $plan['precio'];
        $moneda = $plan['moneda'];

        $stmt_pago = $conn->prepare("INSERT INTO pagos (id_suscripcion, monto, moneda, metodo_pago, estado, referencia) VALUES (?, ?, ?, 'Tokow Pay (Simulado)', 'Completado', ?)");
        $stmt_pago->bind_param("idss", $id_suscripcion, $monto, $moneda, $referencia_pago);
        $stmt_pago->execute();
        $stmt_pago->close();

        $pago_exitoso = true;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pasarela de Pago — Tokow Pay</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<style>
  .checkout-shell {
    max-width: 1000px;
    margin: 40px auto;
    padding: 0 20px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
  }
  @media (max-width: 850px) {
    .checkout-shell {
      grid-template-columns: 1fr;
    }
  }
  .card-preview-box {
    background: linear-gradient(135deg, #1f1a3a 0%, #0d0e1c 100%);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 28px;
    box-shadow: 0 16px 40px rgba(0,0,0,0.4);
    position: relative;
    overflow: hidden;
  }
  .virtual-credit-card {
    background: linear-gradient(135deg, #7C6FF7 0%, #4DC8A3 100%);
    border-radius: 16px;
    padding: 24px;
    color: white;
    box-shadow: 0 12px 28px rgba(124, 111, 247, 0.35);
    margin-bottom: 24px;
    position: relative;
    height: 190px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .card-chip {
    width: 44px;
    height: 32px;
    background: linear-gradient(135deg, #fcd34d, #f59e0b);
    border-radius: 6px;
  }
  .card-num-display {
    font-family: 'JetBrains Mono', monospace;
    font-size: 20px;
    letter-spacing: 2.5px;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
  }
  .card-details-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    text-transform: uppercase;
  }
  .plan-summary-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 20px;
  }
  .summary-line {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    font-size: 14px;
  }
  .summary-total {
    display: flex;
    justify-content: space-between;
    padding-top: 14px;
    font-size: 18px;
    font-weight: 700;
    color: var(--mint);
  }
  .payment-form-card {
    background: rgba(13, 14, 28, 0.8);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 32px;
    backdrop-filter: blur(12px);
  }
  .sim-badge {
    background: rgba(77, 200, 163, 0.15);
    color: #4DC8A3;
    border: 1px solid rgba(77, 200, 163, 0.3);
    padding: 4px 10px;
    border-radius: 99px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 16px;
  }
  /* Processing Modal Overlay */
  .proc-modal {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(8, 9, 15, 0.9);
    backdrop-filter: blur(16px);
    z-index: 1000;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
  }
  .proc-modal.active {
    opacity: 1;
    pointer-events: all;
  }
  .spinner-ring {
    width: 64px;
    height: 64px;
    border: 4px solid rgba(124, 111, 247, 0.2);
    border-top: 4px solid var(--mint);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 20px;
  }
  @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

  .success-card {
    background: rgba(13, 14, 28, 0.9);
    border: 1px solid var(--mint);
    border-radius: 24px;
    padding: 40px;
    text-align: center;
    max-width: 550px;
    margin: 40px auto;
    box-shadow: 0 0 40px rgba(77, 200, 163, 0.2);
  }
</style>
</head>
<body>

<header class="site-header">
  <div class="wrap nav">
    <a href="index.html" class="brand">
      <span class="brand-mark"></span>
      <span class="brand-text">Tokow</span>
    </a>
    <nav class="nav-links">
      <a href="index.html">Inicio</a>
      <a href="precios.php">Precios y servicios</a>
      <a href="nosotros.html">Acerca de</a>
      <a href="play.php">¡A Jugar!</a>
    </nav>
    <div class="nav-cta">
      <span style="font-size: 14px; color: var(--lavender);">🎮 @<?php echo htmlspecialchars($usuario); ?></span>
    </div>
  </div>
</header>

<main class="wrap">
  <?php if ($pago_exitoso): ?>
    <div class="success-card">
      <div style="font-size: 50px; margin-bottom: 16px;">🎉</div>
      <span class="sim-badge">¡Pago Verificado y Exitoso!</span>
      <h1 style="margin-top: 8px;">¡Bienvenido a <?php echo htmlspecialchars($plan['nombre']); ?>!</h1>
      <p style="color: var(--muted); margin: 16px 0;">Tu transacción simulada ha sido aprobada correctamente y se te han otorgado todos los accesos.</p>
      
      <div class="plan-summary-card" style="text-align: left; margin-bottom: 24px;">
        <div class="summary-line">
          <span>Referencia de Pago:</span>
          <strong style="font-family: monospace; color: white;"><?php echo htmlspecialchars($referencia_pago); ?></strong>
        </div>
        <div class="summary-line">
          <span>Plan Adquirido:</span>
          <strong><?php echo htmlspecialchars($plan['nombre']); ?></strong>
        </div>
        <div class="summary-line">
          <span>Monto Cobrado:</span>
          <strong>$<?php echo number_format($plan['precio'], 2); ?> <?php echo htmlspecialchars($plan['moneda']); ?></strong>
        </div>
        <div class="summary-line">
          <span>Estado de Acceso:</span>
          <strong style="color: var(--mint);">Suscripción Activa ✓</strong>
        </div>
      </div>

      <a href="play.php" class="btn btn-primary btn-block btn-lg" style="font-size: 16px;">🚀 Ir a la plataforma (¡A Jugar!)</a>
    </div>
  <?php else: ?>
    <div style="text-align: center; margin-top: 24px;">
      <span class="sim-badge">⚡ Pasarela de Pago Simulada Tokow Pay</span>
      <h1>Finaliza tu suscripción</h1>
      <p style="color: var(--muted);">Estás a un paso de desbloquear el máximo rendimiento de Cloud Gaming.</p>
    </div>

    <div class="checkout-shell">
      <!-- Tarjeta visual y resumen -->
      <div class="card-preview-box">
        <div class="virtual-credit-card">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div class="card-chip"></div>
            <strong style="letter-spacing: 2px; font-size: 14px;">TOKOW PAY</strong>
          </div>
          <div class="card-num-display" id="cardNumDisplay">•••• •••• •••• 4242</div>
          <div class="card-details-row">
            <div>
              <div style="font-size: 9px; opacity: 0.8;">TITULAR</div>
              <div id="cardNameDisplay" style="font-weight: 600; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo strtoupper(htmlspecialchars($usuario)); ?></div>
            </div>
            <div>
              <div style="font-size: 9px; opacity: 0.8;">VENCIMIENTO</div>
              <div id="cardExpDisplay" style="font-weight: 600;">12/28</div>
            </div>
          </div>
        </div>

        <div class="plan-summary-card">
          <h4 style="margin-bottom: 12px;">Resumen del pedido</h4>
          <div class="summary-line">
            <span>Plan seleccionado</span>
            <strong><?php echo htmlspecialchars($plan['nombre']); ?></strong>
          </div>
          <div class="summary-line">
            <span>Calidad de streaming</span>
            <strong><?php echo htmlspecialchars($plan['calidad_stream']); ?></strong>
          </div>
          <div class="summary-line">
            <span>Dispositivos simultáneos</span>
            <strong><?php echo htmlspecialchars($plan['max_dispositivos']); ?></strong>
          </div>
          <div class="summary-line">
            <span>Facturación</span>
            <strong><?php echo $plan['duracion_meses'] == 12 ? 'Anual' : 'Mensual'; ?></strong>
          </div>
          <div class="summary-total">
            <span>Total a pagar</span>
            <span>$<?php echo number_format($plan['precio'], 2); ?> <?php echo htmlspecialchars($plan['moneda']); ?></span>
          </div>
        </div>
      </div>

      <!-- Formulario de Tarjeta -->
      <div class="payment-form-card">
        <h3 style="margin-bottom: 8px;">Datos de pago</h3>
        <p style="font-size: 13px; color: var(--muted); margin-bottom: 20px;">
          Esta es una <strong>pasarela de prueba</strong>. Puedes ingresar cualquier dato ficticio para simular la verificación y otorgar tu membresía.
        </p>

        <form id="paymentForm" method="POST" action="">
          <input type="hidden" name="procesar_pago" value="1">
          
          <div class="field">
            <label for="card_name">Nombre en la tarjeta</label>
            <input id="card_name" name="card_name" type="text" placeholder="Ej. Nicolas Luevano" value="<?php echo htmlspecialchars($usuario); ?>" required>
          </div>

          <div class="field">
            <label for="card_number">Número de tarjeta (Simulación)</label>
            <input id="card_number" name="card_number" type="text" placeholder="4242 4242 4242 4242" maxlength="19" value="4242 4242 4242 4242" required>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div class="field">
              <label for="card_exp">Vencimiento</label>
              <input id="card_exp" name="card_exp" type="text" placeholder="MM/AA" value="12/28" maxlength="5" required>
            </div>
            <div class="field">
              <label for="card_cvc">CVC / CVV</label>
              <input id="card_cvc" name="card_cvc" type="password" placeholder="123" value="123" maxlength="4" required>
            </div>
          </div>

          <button type="button" id="btnPagarSimulado" class="btn btn-primary btn-block btn-lg" style="margin-top: 12px;">
            🔒 Simular Pago ($<?php echo number_format($plan['precio'], 2); ?> <?php echo htmlspecialchars($plan['moneda']); ?>)
          </button>
        </form>
      </div>
    </div>
  <?php endif; ?>
</main>

<!-- Modal de simulación de verificación de pago -->
<div class="proc-modal" id="procModal">
  <div class="spinner-ring"></div>
  <h2 id="procTitle">Verificando pago...</h2>
  <p id="procDesc" style="color: var(--muted); margin-top: 8px;">Conectando con la pasarela de prueba Tokow Pay...</p>
</div>

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

<script>
  const nameInput = document.getElementById('card_name');
  const numInput = document.getElementById('card_number');
  const expInput = document.getElementById('card_exp');

  const cardNameDisplay = document.getElementById('cardNameDisplay');
  const cardNumDisplay = document.getElementById('cardNumDisplay');
  const cardExpDisplay = document.getElementById('cardExpDisplay');

  if (nameInput) {
    nameInput.addEventListener('input', (e) => {
      cardNameDisplay.textContent = e.target.value.toUpperCase() || 'TITULAR';
    });
  }
  if (numInput) {
    numInput.addEventListener('input', (e) => {
      let val = e.target.value.replace(/\D/g, '');
      val = val.replace(/(.{4})/g, '$1 ').trim();
      e.target.value = val;
      cardNumDisplay.textContent = val || '•••• •••• •••• 4242';
    });
  }
  if (expInput) {
    expInput.addEventListener('input', (e) => {
      let val = e.target.value.replace(/\D/g, '');
      if (val.length >= 2) {
        val = val.substring(0, 2) + '/' + val.substring(2, 4);
      }
      e.target.value = val;
      cardExpDisplay.textContent = val || '12/28';
    });
  }

  const btnPagar = document.getElementById('btnPagarSimulado');
  const procModal = document.getElementById('procModal');
  const procTitle = document.getElementById('procTitle');
  const procDesc = document.getElementById('procDesc');
  const paymentForm = document.getElementById('paymentForm');

  if (btnPagar) {
    btnPagar.addEventListener('click', () => {
      procModal.classList.add('active');
      
      setTimeout(() => {
        procTitle.textContent = "Validando credenciales...";
        procDesc.textContent = "Verificando token de prueba y simulando aprobación bancaria...";
      }, 1000);

      setTimeout(() => {
        procTitle.textContent = "¡Pago Aprobado!";
        procDesc.textContent = "Otorgando suscripción y accesos al usuario...";
      }, 2200);

      setTimeout(() => {
        paymentForm.submit();
      }, 3000);
    });
  }
</script>
</body>
</html>

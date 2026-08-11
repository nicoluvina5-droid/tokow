<?php
session_start();
require_once 'db.php';

$conn = getDBConnection();
$usuario_logueado = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : null;
$es_admin = isset($_SESSION['es_admin']) && $_SESSION['es_admin'] == 1;

// Verificar si el usuario ya tiene suscripción activa
$suscripcion_activa = null;
if (isset($_SESSION['usuario_id'])) {
    $suscripcion_activa = usuarioTieneSuscripcionActiva($conn, (int)$_SESSION['usuario_id']);
}
?>
<!doctype html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Precios y servicios — Tokow</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link
    href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
  <style>
    .toggle-opt {
      cursor: pointer;
      user-select: none;
    }
    .plan-grid-container {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 28px;
      max-width: 900px;
      margin: 0 auto;
    }
    @media (max-width: 768px) {
      .plan-grid-container {
        grid-template-columns: 1fr;
      }
    }
    .user-status-banner {
      background: rgba(77, 200, 163, 0.1);
      border: 1px solid rgba(77, 200, 163, 0.3);
      border-radius: 12px;
      padding: 14px 20px;
      margin-bottom: 24px;
      display: flex;
      justify-content: space-between;
      align-items: center;
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
        <a href="precios.php" class="active">Precios y servicios</a>
        <a href="nosotros.html">Acerca de</a>
        <a href="play.php">¡A Jugar!</a>
        <?php if ($es_admin): ?>
          <a href="admin.php" style="color: var(--mint);">Admin Dashboard</a>
        <?php endif; ?>
      </nav>
      <div class="nav-cta">
        <?php if ($usuario_logueado): ?>
          <span style="font-size: 14px; color: var(--lavender); margin-right: 12px;">🎮 @<?php echo htmlspecialchars($usuario_logueado); ?></span>
          <a href="logout.php" class="btn btn-ghost">Cerrar sesión</a>
        <?php else: ?>
          <a href="login.php" class="btn btn-ghost">Iniciar sesión</a>
          <a href="registro.php" class="btn btn-primary">Crear cuenta</a>
        <?php endif; ?>
      </div>
      <button class="nav-toggle" aria-label="Abrir menú">☰</button>
    </div>
  </header>

  <main>
    <section class="about-hero">
      <div class="wrap">
        <span class="eyebrow">Precios y servicios</span>
        <h1>Un plan claro para cada forma de jugar.</h1>
        <p class="lede">Sin contratos forzosos, sin letras chiquitas. Cambia o cancela tu plan cuando quieras desde tu cuenta.</p>
      </div>
    </section>

    <section style="padding-top:24px;">
      <div class="wrap">
        <?php if ($suscripcion_activa && is_array($suscripcion_activa)): ?>
          <div class="user-status-banner">
            <div>
              <strong>Suscripción Actual:</strong> <span style="color: var(--mint);"><?php echo htmlspecialchars($suscripcion_activa['plan_nombre']); ?></span> (Activa hasta: <?php echo htmlspecialchars($suscripcion_activa['fecha_fin']); ?>)
            </div>
            <a href="play.php" class="btn btn-primary" style="padding: 8px 16px; font-size: 13px;">Ir a Jugar 🚀</a>
          </div>
        <?php endif; ?>

        <div class="toggle-row" id="billingToggle">
          <span class="toggle-opt active" id="btnMonthly">Mensual</span>
          <span class="toggle-opt" id="btnAnnual">Anual · 2 meses gratis</span>
        </div>

        <p style="text-align:center; margin: 8px 0 28px; color: var(--muted, #9aa0a6); font-size: 14px;">
          ⚡ <strong>Tokow Pay Pasarela Simulada:</strong> Elige tu plan e inicia sesión para verificar el proceso de pago simulado en tiempo real.
        </p>

        <!-- PLANES MENSUALES ($10 / $20) -->
        <div class="plan-grid-container" id="monthlyPlans">
          <!-- PLAN NORMAL MENSUAL -->
          <div class="plan">
            <h3>Suscripción Normal</h3>
            <div class="price">$10<span>USD / mes</span></div>
            <p class="price-note">1080p · 60fps · Streaming Estándar</p>
            <ul class="plan-feat">
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> Acceso al catálogo estándar (80+ juegos)</li>
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> 1 dispositivo a la vez</li>
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> Soporte por correo 24/7</li>
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> Horas de juego ilimitadas</li>
            </ul>
            <a href="checkout.php?plan=normal_mensual" class="btn btn-ghost btn-block">Elegir Suscripción Normal</a>
          </div>

          <!-- PLAN PREMIUM MENSUAL -->
          <div class="plan featured">
            <span class="plan-tag">Más recomendado</span>
            <h3>Suscripción Premium</h3>
            <div class="price">$20<span>USD / mes</span></div>
            <p class="price-note">4K · 60fps · Ultra Latencia Reducida</p>
            <ul class="plan-feat">
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> Catálogo completo (200+ títulos + estrenos)</li>
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> 3 dispositivos simultáneos</li>
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> Servidores Edge VIP de alta prioridad</li>
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> Soporte prioritario 24/7</li>
            </ul>
            <a href="checkout.php?plan=premium_mensual" class="btn btn-primary btn-block">Elegir Suscripción Premium</a>
          </div>
        </div>

        <!-- PLANES ANUALES ($120 / $240) -->
        <div class="plan-grid-container" id="annualPlans" style="display: none;">
          <!-- PLAN NORMAL ANUAL -->
          <div class="plan">
            <h3>Suscripción Normal (Anual)</h3>
            <div class="price">$120<span>USD / año</span></div>
            <p class="price-note">Equivale a $10 USD / mes · Facturación anual</p>
            <ul class="plan-feat">
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> Acceso al catálogo estándar (80+ juegos)</li>
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> 1 dispositivo a la vez</li>
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> Ahorra en tu membresía anual</li>
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> Horas de juego ilimitadas</li>
            </ul>
            <a href="checkout.php?plan=normal_anual" class="btn btn-ghost btn-block">Elegir Normal Anual ($120)</a>
          </div>

          <!-- PLAN PREMIUM ANUAL -->
          <div class="plan featured">
            <span class="plan-tag">Mejor valor</span>
            <h3>Suscripción Premium (Anual)</h3>
            <div class="price">$240<span>USD / año</span></div>
            <p class="price-note">Equivale a $20 USD / mes · Facturación anual</p>
            <ul class="plan-feat">
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> Catálogo completo (200+ títulos + estrenos)</li>
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> 3 dispositivos simultáneos</li>
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> Servidores Edge VIP de alta prioridad</li>
              <li><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4DC8A3" stroke-width="2.5"><path d="M20 6 9 17l-5-5" /></svg> Soporte prioritario 24/7</li>
            </ul>
            <a href="checkout.php?plan=premium_anual" class="btn btn-primary btn-block">Elegir Premium Anual ($240)</a>
          </div>
        </div>

      </div>
    </section>

    <!-- COMPARATIVA -->
    <section class="section-alt">
      <div class="wrap">
        <div class="section-head">
          <span class="eyebrow">Comparativa</span>
          <h2>Qué incluye cada plan.</h2>
        </div>
        <table class="compare-table">
          <thead>
            <tr>
              <th>Servicio</th>
              <th>Normal ($10/mes)</th>
              <th>Premium ($20/mes)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Resolución máxima</td>
              <td>1080p</td>
              <td>4K Ultra HD</td>
            </tr>
            <tr>
              <td>Cuadros por segundo</td>
              <td>60fps</td>
              <td>60fps</td>
            </tr>
            <tr>
              <td>Dispositivos simultáneos</td>
              <td>1</td>
              <td>3</td>
            </tr>
            <tr>
              <td>Catálogo de juegos</td>
              <td>80+</td>
              <td>200+ y estrenos</td>
            </tr>
            <tr>
              <td>Prioridad en Edge Computing</td>
              <td class="check">—</td>
              <td class="check">✓</td>
            </tr>
            <tr>
              <td>Soporte prioritario</td>
              <td class="check">—</td>
              <td class="check">✓</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <footer>
    <div class="wrap">
      <div class="footer-grid">
        <div class="footer-brand">
          <a href="index.php" class="brand"><span class="brand-mark"></span><span class="brand-text">Tokow</span></a>
          <p>Cloud gaming accesible para todos. Juega donde quieras, como quieras.</p>
        </div>
        <div class="footer-col">
          <h5>Producto</h5>
          <ul>
            <li><a href="precios.php">Precios y servicios</a></li>
            <li><a href="index.php#como-funciona">Cómo funciona</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h5>Empresa</h5>
          <ul>
            <li><a href="nosotros.html">Acerca de nosotros</a></li>
          </ul>
        </div>
        <div class="footer-col">
          <h5>Cuenta</h5>
          <ul>
            <li><a href="login.php">Iniciar sesión</a></li>
            <li><a href="registro.php">Crear cuenta</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <span>© 2025 Tokow · Universidad Politécnica de Victoria</span>
        <div class="footer-social">
          <a href="https://www.instagram.com/tokow.oficial/" target="_blank" aria-label="Instagram">◎ Instagram</a>
          <a href="https://www.facebook.com/profile.php?id=61592803082599" target="_blank" aria-label="Facebook">󰈌 Facebook</a>
        </div>
      </div>
    </div>
  </footer>

  <script>
    const btnMonthly = document.getElementById('btnMonthly');
    const btnAnnual = document.getElementById('btnAnnual');
    const monthlyPlans = document.getElementById('monthlyPlans');
    const annualPlans = document.getElementById('annualPlans');

    if (btnMonthly && btnAnnual) {
      btnMonthly.addEventListener('click', () => {
        btnMonthly.classList.add('active');
        btnAnnual.classList.remove('active');
        monthlyPlans.style.display = 'grid';
        annualPlans.style.display = 'none';
      });

      btnAnnual.addEventListener('click', () => {
        btnAnnual.classList.add('active');
        btnMonthly.classList.remove('active');
        monthlyPlans.style.display = 'none';
        annualPlans.style.display = 'grid';
      });
    }
  </script>
</body>

</html>

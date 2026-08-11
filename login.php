<?php
session_start();
require_once 'db.php';

$conn = getDBConnection();

$error = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'precios.php';

if (isset($_SESSION['usuario'])) {
    header("Location: " . $redirect);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $contrasena = trim($_POST['contrasena']);
    $redirect_target = isset($_POST['redirect']) && !empty($_POST['redirect']) ? $_POST['redirect'] : 'precios.php';

    if (!empty($usuario) && !empty($contrasena)) {
        $stmt = @$conn->prepare("SELECT id, usuario, contraseña FROM usuarios WHERE usuario = ?");
        if ($stmt) {
            $stmt->bind_param("s", $usuario);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows === 1) {
                $row = $result->fetch_assoc();
                
                $password_matches = password_verify($contrasena, $row['contraseña']) || ($contrasena === $row['contraseña']);

                if ($password_matches) {
                    $_SESSION['usuario_id'] = (int)$row['id'];
                    $_SESSION['usuario'] = $row['usuario'];

                    $is_admin = (strtolower($row['usuario']) === 'admin' || strtolower($row['usuario']) === 'leo');
                    $_SESSION['es_admin'] = $is_admin ? 1 : 0;

                    header("Location: " . $redirect_target);
                    exit();
                } else {
                    $error = "Contraseña incorrecta.";
                }
            } else {
                $error = "El usuario no existe.";
            }
            $stmt->close();
        } else {
            $error = "Error al consultar la base de datos.";
        }
    } else {
        $error = "Por favor, llena todos los campos.";
    }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión — Tokow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<style>
  .auth-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
    padding: 12px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 16px;
    text-align: center;
  }
  .auth-notice {
    background: rgba(124, 111, 247, 0.15);
    border: 1px solid rgba(124, 111, 247, 0.3);
    color: var(--mint, #4DC8A3);
    padding: 12px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 16px;
    text-align: center;
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
    </nav>
    <div class="nav-cta">
      <a href="registro.php" class="btn btn-primary">Crear cuenta</a>
    </div>
    <button class="nav-toggle" aria-label="Abrir menú">☰</button>
  </div>
</header>

<main class="auth-shell">
  <div class="auth-side">
    <div class="auth-side-content">
      <span class="eyebrow">Bienvenido de vuelta</span>
      <h2>Tu biblioteca y tu progreso te esperan en la nube.</h2>
      <p>Retoma exactamente donde lo dejaste, en cualquier dispositivo conectado a tu cuenta Tokow.</p>
    </div>
    <div class="hud" style="position:relative; width:fit-content;">
      <div class="hud-row"><span class="hud-dot"></span> SESIÓN SEGURA</div>
      <div class="hud-row">CIFRADO <span class="val">TLS 1.3</span></div>
    </div>
  </div>

  <div class="auth-form-wrap">
    <form class="auth-form" method="POST" action="">
      <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
      <span class="eyebrow">Acceso</span>
      <h1 style="margin-top:14px;">Inicia sesión</h1>
      <p>Introduce tus datos para continuar jugando.</p>

      <?php if (isset($_GET['msg']) && $_GET['msg'] === 'login_required'): ?>
        <div class="auth-notice">Debes iniciar sesión para comprar una suscripción o acceder a los servicios.</div>
      <?php endif; ?>

      <?php if (!empty($error)): ?>
        <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <div class="field">
        <label for="usuario">Usuario</label>
        <input id="usuario" name="usuario" type="text" placeholder="Tu nombre de usuario" required autocomplete="username">
      </div>
      <div class="field">
        <label for="pass">Contraseña</label>
        <input id="pass" name="contrasena" type="password" placeholder="••••••••" required autocomplete="current-password">
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">Iniciar sesión</button>

      <p class="auth-switch">¿No tienes cuenta? <a href="registro.php?redirect=<?php echo urlencode($redirect); ?>">Regístrate gratis</a></p>
    </form>
  </div>
</main>

<footer>
  <div class="wrap">
    <div class="footer-bottom" style="border-top: 1px solid var(--border); padding-top: 20px;">
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
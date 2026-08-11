<?php
session_start();

// Configuración de la base de datos
$host = 'localhost';
$db_user = 'root'; // Cambia por tu usuario de MySQL
$db_pass = 'root'; // Cambia por tu contraseña de MySQL
$db_name = 'users';

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $contrasena = trim($_POST['contrasena']);

    if (!empty($usuario) && !empty($contrasena)) {
        // Usamos Prepared Statements para evitar Inyección SQL
        $stmt = $conn->prepare("SELECT usuario, contraseña FROM usuarios WHERE usuario = ?");
        $stmt->bind_param("s", $usuario);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            
            // Si guardas contraseñas cifradas usa password_verify, de lo contrario comparación directa:
            if ($contrasena === $row['contraseña']) {
                $_SESSION['usuario'] = $row['usuario'];
                header("Location: play.php");
                exit();
            } else {
                $error = "Contraseña incorrecta.";
            }
        } else {
            $error = "El usuario no existe.";
        }
        $stmt->close();
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
  /* Estilo rápido para la alerta de error adaptada a tu paleta */
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
      <a href="precios.html">Precios y servicios</a>
      <a href="nosotros.html">Acerca de</a>
      <a href="play.php">¡A Jugar!</a>
    </nav>
    <div class="nav-cta">
      <a href="registro.php" class="btn btn-primary">Crear cuenta</a>
    </div>
    <button class="nav-toggle" aria-label="Abrir menú">☰</button>
  </div>
</header>

<main class="auth-shell">
  <!-- Lado izquierdo (Diseño original del HTML) -->
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

  <!-- Lado derecho (Formulario con lógica PHP integrada) -->
  <div class="auth-form-wrap">
    <form class="auth-form" method="POST" action="">
      <span class="eyebrow">Acceso</span>
      <h1 style="margin-top:14px;">Inicia sesión</h1>
      <p>Introduce tus datos para continuar jugando.</p>

      <!-- Mensaje de error de PHP (si existe) -->
      <?php if (!empty($error)): ?>
        <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <div class="oauth-row">
        <button type="button" class="oauth-btn">🟣 Google</button>
        <button type="button" class="oauth-btn">◈ Discord</button>
      </div>
      <div class="divider">o con tu cuenta</div>

      <div class="field">
        <label for="usuario">Usuario</label>
        <input id="usuario" name="usuario" type="text" placeholder="Tu nombre de usuario" required autocomplete="username">
      </div>
      <div class="field">
        <label for="pass">Contraseña</label>
        <input id="pass" name="contrasena" type="password" placeholder="••••••••" required autocomplete="current-password">
      </div>

      <div class="field-inline">
        <label class="check-row"><input type="checkbox"> Recordarme</label>
        <a href="#" style="color:var(--mint); font-weight:600;">¿Olvidaste tu contraseña?</a>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">Iniciar sesión</button>

      <p class="auth-switch">¿No tienes cuenta? <a href="registro.html">Regístrate gratis</a></p>
    </form>
  </div>
</main>

</body>
</html>
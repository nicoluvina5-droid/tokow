<?php
session_start();

// Configuración de la base de datos
$host = 'localhost';
$db_user = 'root'; 
$db_pass = 'root';     
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
        
        // Verificar si el usuario ya existe
        $stmt_check = $conn->prepare("SELECT usuario FROM usuarios WHERE usuario = ?");
        $stmt_check->bind_param("s", $usuario);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error = "El nombre de usuario ya está registrado.";
            $stmt_check->close();
        } else {
            $stmt_check->close();

            // Insertar usuario y contraseña en la base de datos
            $stmt_insert = $conn->prepare("INSERT INTO usuarios (usuario, contraseña) VALUES (?, ?)");
            $stmt_insert->bind_param("ss", $usuario, $contrasena);

            if ($stmt_insert->execute()) {
                $stmt_insert->close();
                
                // Redirección al login tras registrar con éxito
                header("Location: login.php");
                exit();
            } else {
                $error = "Hubo un error al procesar el registro. Inténtalo de nuevo.";
                $stmt_insert->close();
            }
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
<title>Crear cuenta — Tokow</title>
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
      <a href="login.php" class="btn btn-ghost">Iniciar sesión</a>
    </div>
    <button class="nav-toggle" aria-label="Abrir menú">☰</button>
  </div>
</header>

<main class="auth-shell">
  <!-- Columna Lateral Izquierda (Diseño Visual) -->
  <div class="auth-side">
    <div class="auth-side-content">
      <span class="eyebrow">Únete a Tokow</span>
      <h2>Gaming de alto rendimiento sin invertir en hardware.</h2>
      <p>Crea tu cuenta gratis y empieza a jugar en la nube desde el dispositivo que ya tienes.</p>
    </div>
    <div class="hud" style="position:relative; width:fit-content;">
      <div class="hud-row"><span class="hud-dot"></span> 200+ JUEGOS</div>
      <div class="hud-row">PRUEBA <span class="val">7 días gratis</span></div>
    </div>
  </div>

  <!-- Formulario Derecha (Integración con PHP) -->
  <div class="auth-form-wrap">
    <form class="auth-form" method="POST" action="">
      <span class="eyebrow">Registro</span>
      <h1 style="margin-top:14px;">Crea tu cuenta</h1>
      <p>Menos de un minuto para tu primer juego en la nube.</p>

      <!-- Alerta de error si falla la validación o el registro -->
      <?php if (!empty($error)): ?>
        <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <div class="oauth-row">
        <button type="button" class="oauth-btn">🟣 Google</button>
        <button type="button" class="oauth-btn">◈ Discord</button>
      </div>
      <div class="divider">o con tu usuario</div>

      <div class="field">
        <label for="user">Usuario</label>
        <input id="user" name="usuario" type="text" placeholder="Tu nombre de usuario" required autocomplete="username">
      </div>

      <div class="field">
        <label for="pass2">Contraseña</label>
        <input id="pass2" name="contrasena" type="password" placeholder="Mínimo 8 caracteres" required autocomplete="new-password">
        <div class="password-strength"><span></span><span></span><span></span><span></span></div>
        <p class="field-note">Usa letras, números y al menos un símbolo.</p>
      </div>

      <div class="field-inline">
        <label class="check-row"><input type="checkbox" required> Acepto los <a href="#" style="color:var(--mint); margin-left:4px;">términos y condiciones</a></label>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">Crear cuenta gratis</button>

      <p class="auth-switch">¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
    </form>
  </div>
</main>

</body>
</html>
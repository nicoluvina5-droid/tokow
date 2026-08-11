<?php
session_start();
require_once 'db.php';

$conn = getDBConnection();

$error = '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'login.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $contrasena = trim($_POST['contrasena']);

    if (!empty($usuario) && !empty($contrasena)) {
        // Verificar si el usuario ya existe
        $stmt_check = $conn->prepare("SELECT usuario FROM usuarios WHERE usuario = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("s", $usuario);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check && $result_check->num_rows > 0) {
                $error = "El nombre de usuario ya está registrado.";
                $stmt_check->close();
            } else {
                $stmt_check->close();

                // Intentar con hash bcrypt
                $hash_pass = password_hash($contrasena, PASSWORD_BCRYPT);

                $stmt_insert = $conn->prepare("INSERT INTO usuarios (usuario, contraseña) VALUES (?, ?)");
                if ($stmt_insert) {
                    $stmt_insert->bind_param("ss", $usuario, $hash_pass);
                    $success = @$stmt_insert->execute();

                    if (!$success) {
                        // Fallback si la columna 'contraseña' tiene un límite corto varchar(20) y falló el hash largo
                        $stmt_insert->close();
                        $stmt_insert = $conn->prepare("INSERT INTO usuarios (usuario, contraseña) VALUES (?, ?)");
                        $stmt_insert->bind_param("ss", $usuario, $contrasena);
                        $success = $stmt_insert->execute();
                    }

                    if ($success) {
                        $new_id = $stmt_insert->insert_id;
                        $stmt_insert->close();
                        
                        // Auto-login tras el registro
                        $_SESSION['usuario_id'] = (int)$new_id;
                        $_SESSION['usuario'] = $usuario;
                        $_SESSION['es_admin'] = (strtolower($usuario) === 'admin' || strtolower($usuario) === 'leo') ? 1 : 0;

                        $target = isset($_POST['redirect']) && !empty($_POST['redirect']) ? $_POST['redirect'] : 'precios.php';
                        header("Location: " . $target);
                        exit();
                    } else {
                        $error = "Hubo un error al registrar el usuario en la base de datos.";
                        $stmt_insert->close();
                    }
                } else {
                    $error = "Error en la consulta de inserción.";
                }
            }
        } else {
            $error = "Error al verificar la existencia del usuario.";
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
    <a href="index.php" class="brand">
      <span class="brand-mark"></span>
      <span class="brand-text">Tokow</span>
    </a>
    <nav class="nav-links">
      <a href="index.php">Inicio</a>
      <a href="precios.php">Precios y servicios</a>
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
  <div class="auth-side">
    <div class="auth-side-content">
      <span class="eyebrow">Únete a Tokow</span>
      <h2>Gaming de alto rendimiento sin invertir en hardware.</h2>
      <p>Crea tu cuenta gratis y empieza a jugar en la nube desde el dispositivo que ya tienes.</p>
    </div>
    <div class="hud" style="position:relative; width:fit-content;">
      <div class="hud-row"><span class="hud-dot"></span> 200+ JUEGOS</div>
      <div class="hud-row">ACCESO <span class="val">Inmediato</span></div>
    </div>
  </div>

  <div class="auth-form-wrap">
    <form class="auth-form" method="POST" action="">
      <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
      <span class="eyebrow">Registro</span>
      <h1 style="margin-top:14px;">Crea tu cuenta</h1>
      <p>Menos de un minuto para tu primer juego en la nube.</p>

      <?php if (!empty($error)): ?>
        <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <div class="field">
        <label for="user">Usuario</label>
        <input id="user" name="usuario" type="text" placeholder="Tu nombre de usuario" required autocomplete="username">
      </div>

      <div class="field">
        <label for="pass2">Contraseña</label>
        <input id="pass2" name="contrasena" type="password" placeholder="••••••••" required autocomplete="new-password">
      </div>

      <div class="field-inline">
        <label class="check-row"><input type="checkbox" required> Acepto los <a href="#" style="color:var(--mint); margin-left:4px;">términos y condiciones</a></label>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">Crear cuenta gratis</button>

      <p class="auth-switch">¿Ya tienes cuenta? <a href="login.php?redirect=<?php echo urlencode($redirect); ?>">Inicia sesión</a></p>
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
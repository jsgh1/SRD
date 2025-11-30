<?php

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';

$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $mensaje_error = 'Por favor ingresa correo y contraseña.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            
            $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires_at = (new DateTime('+10 minutes'))->format('Y-m-d H:i:s');

            $stmtCode = $pdo->prepare("INSERT INTO codigos_login (admin_id, codigo, expires_at) VALUES (?, ?, ?)");
            $stmtCode->execute([$admin['id'], $codigo, $expires_at]);

            if (enviarCodigoLogin($admin['email'], $codigo)) {
                
                $_SESSION['login_admin_id'] = $admin['id'];
                $_SESSION['login_admin_nombre'] = $admin['nombre'];
                $_SESSION['login_admin_cargo']  = $admin['cargo'];

                header('Location: codigo.php');
                exit;
            } else {
                $mensaje_error = 'No se pudo enviar el código al correo. Contacta al administrador.';
            }
        } else {
            $mensaje_error = 'Correo o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login - Sistema de Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="login-page">
  <div class="login-wrapper">
    <!-- Panel izquierdo (branding / descripción) -->
    <div class="login-hero">
      <div class="login-hero-inner">
        <h1>Sistema de Registro</h1>
        <p>Administra de forma ordenada la información de las personas, sus documentos y predios en un solo lugar.</p>
        <ul>
          <li>✔ Panel con métricas y gráficos</li>
          <li>✔ Búsqueda rápida por documento</li>
          <li>✔ Exportación de registros a PDF</li>
        </ul>
      </div>
      <div class="login-hero-illustration">
        <div class="bubble big"></div>
        <div class="bubble medium"></div>
        <div class="bubble small"></div>
      </div>
    </div>

    <!-- Panel derecho (formulario) -->
    <div class="login-card">
      <div class="login-card-header">
        <h2>Iniciar sesión</h2>
        <p>Ingresa con tu correo y contraseña de administrador.</p>
      </div>

      <?php if ($mensaje_error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
      <?php endif; ?>

      <form method="post" action="" class="login-form">
        <div class="form-group">
          <label for="email">Correo electrónico</label>
          <input
            type="email"
            id="email"
            name="email"
            required
            placeholder="admin@ejemplo.com"
          >
        </div>
        <div class="form-group">
          <label for="password">Contraseña</label>
          <input
            type="password"
            id="password"
            name="password"
            required
            placeholder="Tu contraseña"
          >
        </div>
        <button type="submit" class="btn-primary btn-full">Ingresar</button>
      </form>

      <div class="login-footer-text">
        <small>&copy; <?php echo date('Y'); ?> · Sistema de Registro</small>
      </div>
    </div>
  </div>
</body>
</html>


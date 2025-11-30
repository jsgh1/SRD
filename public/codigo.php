<?php
// public/codigo.php
session_start();
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['login_admin_id'])) {
    // Si no viene del login, lo mandamos al inicio
    header('Location: index.php');
    exit;
}

$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');

    if ($codigo === '') {
        $mensaje_error = 'Ingresa el código que te llegó al correo.';
    } else {
        $admin_id = $_SESSION['login_admin_id'];

        $stmt = $pdo->prepare("
            SELECT * FROM codigos_login
            WHERE admin_id = ? AND codigo = ? AND usado = 0 AND expires_at > NOW()
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$admin_id, $codigo]);
        $registro = $stmt->fetch();

        if ($registro) {
            // Marcar código como usado
            $update = $pdo->prepare("UPDATE codigos_login SET usado = 1 WHERE id = ?");
            $update->execute([$registro['id']]);

            // Cargar datos del admin (incluye tema)
            $stmtAdmin = $pdo->prepare("SELECT nombre, cargo, tema FROM admins WHERE id = ?");
            $stmtAdmin->execute([$admin_id]);
            $adminData = $stmtAdmin->fetch();

            // Crear sesión final del admin
            $_SESSION['admin_id']     = $admin_id;
            $_SESSION['admin_nombre'] = $adminData['nombre'] ?? ($_SESSION['login_admin_nombre'] ?? 'Administrador');
            $_SESSION['admin_cargo']  = $adminData['cargo']  ?? ($_SESSION['login_admin_cargo']  ?? 'Admin');
            $_SESSION['tema']         = $adminData['tema']   ?? 'claro';

            // Borrar variables temporales
            unset($_SESSION['login_admin_id'], $_SESSION['login_admin_nombre'], $_SESSION['login_admin_cargo']);

            header('Location: dashboard.php');
            exit;
        } else {
            $mensaje_error = 'Código inválido o vencido.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Verificar código - Sistema de Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="login-page">
  <div class="login-wrapper">
    <!-- Panel izquierdo reutilizado (branding / seguridad) -->
    <div class="login-hero">
      <div class="login-hero-inner">
        <h1>Verificación en dos pasos</h1>
        <p>
          Por seguridad, te hemos enviado un código de verificación a tu correo electrónico.
          Solo quienes tengan acceso al correo pueden entrar al sistema.
        </p>
        <ul>
          <li>✔ Protege el acceso a los registros</li>
          <li>✔ Código válido por unos minutos</li>
          <li>✔ No compartas este código con nadie</li>
        </ul>
      </div>
      <div class="login-hero-illustration">
        <div class="bubble big"></div>
        <div class="bubble medium"></div>
        <div class="bubble small"></div>
      </div>
    </div>

    <!-- Panel derecho (formulario de código) -->
    <div class="login-card">
      <div class="login-card-header">
        <h2>Ingresa tu código</h2>
        <p>
          Revisa tu bandeja de entrada (y spam) y escribe el código de 6 dígitos que te enviamos.
        </p>
      </div>

      <?php if ($mensaje_error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
      <?php endif; ?>

      <form method="post" action="" class="login-form">
        <div class="form-group">
          <label for="codigo">Código de verificación</label>
          <input
            type="text"
            id="codigo"
            name="codigo"
            required
            maxlength="6"
            pattern="\d{6}"
            inputmode="numeric"
            placeholder="123456"
          >
        </div>
        <button type="submit" class="btn-primary btn-full">Confirmar código</button>
      </form>

      <div class="login-footer-text">
        <small>
          ¿No recibiste el código? Verifica tu carpeta de spam o contacta al administrador del sistema.<br>
          <a href="index.php" style="color:#2563eb;text-decoration:none;">&laquo; Volver al inicio de sesión</a>
        </small>
      </div>
    </div>
  </div>
</body>
</html>

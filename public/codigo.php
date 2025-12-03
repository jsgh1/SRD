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

            // Cargar datos del admin (incluye tema, email, foto)
            $stmtAdmin = $pdo->prepare("SELECT nombre, cargo, tema, email, foto_perfil FROM admins WHERE id = ?");
            $stmtAdmin->execute([$admin_id]);
            $adminData = $stmtAdmin->fetch();

            // Crear sesión final del admin
            $_SESSION['admin_id']     = $admin_id;
            $_SESSION['admin_nombre'] = $adminData['nombre'] ?? ($_SESSION['login_admin_nombre'] ?? 'Administrador');
            $_SESSION['admin_cargo']  = $adminData['cargo']  ?? ($_SESSION['login_admin_cargo']  ?? 'Admin');
            $_SESSION['tema']         = $adminData['tema']   ?? 'claro';
            $_SESSION['admin_email']  = $adminData['email']  ?? '';
            $_SESSION['foto_perfil']  = $adminData['foto_perfil'] ?? null;

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
    <!-- Panel izquierdo -->
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
        <!-- Tarjeta 3D interactiva -->
        <div class="login-hero-3d-wrapper">
          <div class="login-hero-3d-card">
            <div class="hero-3d-header">
              <span class="hero-3d-icon hero-3d-icon-main">
                <svg viewBox="0 0 24 24" class="icon-svg">
                  <path d="M4 9a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v6a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z" fill="none"></path>
                  <path d="M9 11h6" fill="none"></path>
                  <path d="M9 15h3" fill="none"></path>
                </svg>
              </span>
              <div class="hero-3d-title">
                <p class="hero-3d-label">Código enviado</p>
                <p class="hero-3d-value">••••••</p>
              </div>
            </div>

            <div class="hero-3d-body">
              <div class="hero-3d-pill">
                <span class="hero-3d-pill-icon">
                  <svg viewBox="0 0 24 24" class="icon-svg">
                    <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                    <polyline points="4 6 12 12 20 6" fill="none"></polyline>
                  </svg>
                </span>
                <span>Enviado al correo registrado</span>
              </div>
              <div class="hero-3d-pill">
                <span class="hero-3d-pill-icon">
                  <svg viewBox="0 0 24 24" class="icon-svg">
                    <circle cx="12" cy="12" r="6" fill="none"></circle>
                    <path d="M12 8v4l2 2" fill="none"></path>
                  </svg>
                </span>
                <span>Expira en pocos minutos</span>
              </div>
            </div>

            <div class="hero-3d-footer">
              <span class="hero-3d-tag">
                <svg viewBox="0 0 24 24" class="icon-svg">
                  <path d="M12 2a7 7 0 0 0-7 7v3l-1 3h16l-1-3V9a7 7 0 0 0-7-7z" fill="none"></path>
                  <path d="M9 20a3 3 0 0 0 6 0" fill="none"></path>
                </svg>
                <span>Acceso protegido</span>
              </span>
            </div>
          </div>
        </div>
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

      <form method="post" action="" class="login-form">
        <div class="form-group form-group-icon">
          <label for="codigo">Código de verificación</label>
          <div class="input-with-icon">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
                <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                <path d="M8 10h2M14 10h2M8 14h2M14 14h2" stroke-width="1.6"></path>
              </svg>
            </span>
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
        </div>

        <?php if ($mensaje_error): ?>
          <div class="alert alert-error" style="margin-top:6px;">
            <?php echo htmlspecialchars($mensaje_error); ?>
          </div>
        <?php endif; ?>

        <button type="submit" class="btn-primary btn-full" style="margin-top:8px;">Confirmar código</button>
      </form>

      <div class="login-footer-text">
        <small>
          ¿No recibiste el código? Verifica tu carpeta de spam o contacta al administrador del sistema.<br>
          <a href="index.php" style="color:#2563eb;text-decoration:none;">&laquo; Volver al inicio de sesión</a>
        </small>
      </div>
    </div>
  </div>

  <script src="../assets/js/login.js"></script>
</body>
</html>

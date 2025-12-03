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
    <!-- Panel izquierdo -->
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
        <!-- Tarjeta 3D interactiva -->
        <div class="login-hero-3d-wrapper">
          <div class="login-hero-3d-card">
            <div class="hero-3d-header">
              <span class="hero-3d-icon hero-3d-icon-main">
                <svg viewBox="0 0 24 24" class="icon-svg">
                  <rect x="3" y="4" width="18" height="16" rx="3"></rect>
                  <rect x="6" y="8" width="6" height="2" rx="1"></rect>
                  <rect x="6" y="12" width="12" height="2" rx="1"></rect>
                </svg>
              </span>
              <div class="hero-3d-title">
                <p class="hero-3d-label">Registros activos</p>
                <p class="hero-3d-value">+256</p>
              </div>
            </div>

            <div class="hero-3d-body">
              <div class="hero-3d-pill">
                <span class="hero-3d-pill-icon">
                  <svg viewBox="0 0 24 24" class="icon-svg">
                    <polyline points="4 13 9 18 20 6" fill="none" stroke-width="2"></polyline>
                  </svg>
                </span>
                <span>Validación de datos completada</span>
              </div>
              <div class="hero-3d-pill">
                <span class="hero-3d-pill-icon">
                  <svg viewBox="0 0 24 24" class="icon-svg">
                    <circle cx="12" cy="12" r="6" fill="none"></circle>
                    <path d="M12 9v3l1.5 1.5" fill="none"></path>
                  </svg>
                </span>
                <span>Historial de cambios por registro</span>
              </div>
            </div>

            <div class="hero-3d-footer">
              <span class="hero-3d-tag">
                <svg viewBox="0 0 24 24" class="icon-svg">
                  <path d="M4 12a8 8 0 0 1 16 0" fill="none"></path>
                  <path d="M5 14h14" fill="none"></path>
                  <path d="M7 16h10" fill="none"></path>
                </svg>
                <span>Seguridad administrativa</span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Panel derecho (formulario) -->
    <div class="login-card">
      <div class="login-card-header">
        <h2>Iniciar sesión</h2>
        <p>Ingresa con tu correo y contraseña de administrador.</p>
      </div>

      <form method="post" action="" class="login-form">
        <div class="form-group form-group-icon">
          <label for="email">Correo electrónico</label>
          <div class="input-with-icon">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
                <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                <polyline points="4 6 12 12 20 6" fill="none" stroke-width="1.5"></polyline>
              </svg>
            </span>
            <input
              type="email"
              id="email"
              name="email"
              required
              placeholder="admin@ejemplo.com"
            >
          </div>
        </div>

        <div class="form-group form-group-icon">
          <label for="password">Contraseña</label>
          <div class="input-with-icon">
            <span class="input-icon">
              <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
                <rect x="6" y="9" width="12" height="11" rx="2"></rect>
                <path d="M9 9V7a3 3 0 0 1 6 0v2"></path>
              </svg>
            </span>
            <input
              type="password"
              id="password"
              name="password"
              required
              placeholder="Tu contraseña"
            >
            <button
              type="button"
              class="input-icon-button"
              data-password-toggle
              data-target="password"
              aria-label="Mostrar u ocultar contraseña"
              aria-pressed="false"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg icon-eye">
                <path d="M2 12s3-6 10-6 10 6 10 6-3 6-10 6S2 12 2 12z" fill="none"></path>
                <circle cx="12" cy="12" r="3"></circle>
              </svg>
              <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg icon-eye-off">
                <path d="M2 12s3-6 10-6 10 6 10 6-3 6-10 6S2 12 2 12z" fill="none"></path>
                <circle cx="12" cy="12" r="3"></circle>
                <line x1="4" y1="4" x2="20" y2="20" stroke-width="1.6"></line>
              </svg>
            </button>
          </div>
        </div>

        <?php if ($mensaje_error): ?>
          <div class="alert alert-error" style="margin-top:6px;">
            <?php echo htmlspecialchars($mensaje_error); ?>
          </div>
        <?php endif; ?>

        <button type="submit" class="btn-primary btn-full" style="margin-top:8px;">Ingresar</button>
      </form>

      <div class="login-footer-text">
        <small>&copy; <?php echo date('Y'); ?> · Sistema de Registro</small>
      </div>
    </div>
  </div>

  <script src="../assets/js/login.js"></script>
</body>
</html>

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
  <div class="login-container">
    <h1>Iniciar sesión</h1>

    <?php if ($mensaje_error): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="form-group">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" required placeholder="admin@ejemplo.com">
      </div>
      <div class="form-group">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required placeholder="Contraseña">
      </div>
      <button type="submit" class="btn-primary">Ingresar</button>
    </form>
  </div>
</body>
</html>

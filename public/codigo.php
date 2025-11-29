<?php

session_start();
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['login_admin_id'])) {
    
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

            // Cargar tema del admin desde la BD
            $stmtAdmin = $pdo->prepare("SELECT tema FROM admins WHERE id = ?");
            $stmtAdmin->execute([$admin_id]);
            $adminData = $stmtAdmin->fetch();

            // Crear sesión final del admin
            $_SESSION['admin_id']     = $admin_id;
            $_SESSION['admin_nombre'] = $_SESSION['login_admin_nombre'] ?? 'Administrador';
            $_SESSION['admin_cargo']  = $_SESSION['login_admin_cargo'] ?? 'Admin';
            $_SESSION['tema']         = $adminData['tema'] ?? 'claro';

            // Borrar variables temporales usadas en el login previo
            unset(
                $_SESSION['login_admin_id'],
                $_SESSION['login_admin_nombre'],
                $_SESSION['login_admin_cargo']
            );

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
  <title>Ingresar código - Sistema de Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="login-page">
  <div class="login-container">
    <h1>Verificación de código</h1>

    <p>Hemos enviado un código a tu correo. Ingrésalo a continuación.</p>

    <?php if ($mensaje_error): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($mensaje_error); ?></div>
    <?php endif; ?>

    <form method="post" action="">
      <div class="form-group">
        <label for="codigo">Código</label>
        <input type="text" id="codigo" name="codigo" required maxlength="6" placeholder="123456">
      </div>
      <button type="submit" class="btn-primary">Confirmar</button>
    </form>
  </div>
</body>
</html>

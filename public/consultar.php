<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

$mensaje_error = '';
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_doc = trim($_POST['tipo_documento'] ?? '');
    $numero   = trim($_POST['numero_documento'] ?? '');

    if ($tipo_doc === '' || $numero === '') {
        $mensaje_error = 'Debes seleccionar el tipo de documento e ingresar el número.';
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM personas
            WHERE tipo_documento = ? AND numero_documento = ?
            LIMIT 1
        ");
        $stmt->execute([$tipo_doc, $numero]);
        $resultado = $stmt->fetch();

        if (!$resultado) {
            $mensaje_error = 'No se encontraron registros con esos datos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Consultar persona - Sistema de Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="<?php echo $body_class; ?>">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <div class="layout-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content">
      <h1>Consultar persona</h1>

      <section class="form-card">
        <form method="post" action="">
          <div class="form-row">
            <div class="form-group">
              <label for="tipo_documento">Tipo de documento</label>
              <select name="tipo_documento" id="tipo_documento" required>
                <option value="">Seleccione...</option>
                <option value="Registro Civil">Registro Civil</option>
                <option value="Tarjeta de Identidad">Tarjeta de Identidad</option>
                <option value="Cédula de Ciudadanía">Cédula de Ciudadanía</option>
                <option value="Cédula de Extranjería">Cédula de Extranjería</option>
                <option value="NIT">NIT</option>
              </select>
            </div>
            <div class="form-group">
              <label for="numero_documento">Número de documento</label>
              <input type="text" id="numero_documento" name="numero_documento" required>
            </div>
          </div>
          <button type="submit" class="btn-primary">Consultar</button>
        </form>

        <?php if ($mensaje_error): ?>
          <div class="alert alert-error" style="margin-top:10px;">
            <?php echo htmlspecialchars($mensaje_error); ?>
          </div>
        <?php endif; ?>
      </section>

      <?php if ($resultado): ?>
        <section class="detalle-card">
          <h2>Resultado</h2>
          <p><strong>Nombre:</strong> <?php echo htmlspecialchars($resultado['nombres'] . ' ' . $resultado['apellidos']); ?></p>
          <p><strong>Documento:</strong> <?php echo htmlspecialchars($resultado['tipo_documento'] . ' ' . $resultado['numero_documento']); ?></p>
          <p><strong>Afiliado:</strong> <?php echo htmlspecialchars($resultado['afiliado']); ?></p>
          <p><strong>Zona:</strong> <?php echo htmlspecialchars($resultado['zona']); ?></p>
          <p><strong>Género:</strong> <?php echo htmlspecialchars($resultado['genero']); ?></p>
          <p><strong>Fecha de nacimiento:</strong> <?php echo htmlspecialchars($resultado['fecha_nacimiento']); ?></p>
          <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($resultado['telefono']); ?></p>
          <p><strong>Cargo:</strong> <?php echo htmlspecialchars($resultado['cargo']); ?></p>
          <p><strong>Nombre predio:</strong> <?php echo htmlspecialchars($resultado['nombre_predio']); ?></p>
          <p><strong>Correo electrónico:</strong> <?php echo htmlspecialchars($resultado['correo_electronico']); ?></p>
          <p><strong>Estado registro:</strong> <?php echo htmlspecialchars($resultado['estado_registro']); ?></p>
        </section>
      <?php endif; ?>
    </main>
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

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

  <section class="form-card consulta-card">
    <div class="consulta-header">
      <div class="consulta-icon">
        <svg viewBox="0 0 24 24" class="icon-svg">
          <circle cx="11" cy="11" r="5"></circle>
          <line x1="15" y1="15" x2="20" y2="20" stroke-width="2" stroke-linecap="round"></line>
        </svg>
      </div>
      <div>
        <h2>Buscar por documento</h2>
        <p>Selecciona el tipo de documento e ingresa el número para consultar los datos registrados.</p>
      </div>
    </div>

    <form method="post" action="" class="consulta-form">
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

      <?php if ($mensaje_error): ?>
        <div class="alert alert-error" style="margin-top:10px;">
          <?php echo htmlspecialchars($mensaje_error); ?>
        </div>
      <?php endif; ?>
    </form>
  </section>

  <?php if ($resultado): ?>
    <section class="detalle-card consulta-detalle">
      <div class="consulta-detalle-header">
        <div class="consulta-detalle-avatar">
          <svg viewBox="0 0 24 24" class="icon-svg">
            <circle cx="12" cy="9" r="4"></circle>
            <path d="M5 20c0-3.3 3-6 7-6s7 2.7 7 6" fill="none"></path>
          </svg>
        </div>
        <div>
          <h2><?php echo htmlspecialchars($resultado['nombres'] . ' ' . $resultado['apellidos']); ?></h2>
          <p><?php echo htmlspecialchars($resultado['tipo_documento'] . ' ' . $resultado['numero_documento']); ?></p>
        </div>
      </div>
      <div class="consulta-detalle-grid">
        <div>
          <p><strong>Afiliado:</strong> <?php echo htmlspecialchars($resultado['afiliado']); ?></p>
          <p><strong>Zona:</strong> <?php echo htmlspecialchars($resultado['zona']); ?></p>
          <p><strong>Género:</strong> <?php echo htmlspecialchars($resultado['genero']); ?></p>
          <p><strong>Fecha de nacimiento:</strong> <?php echo htmlspecialchars($resultado['fecha_nacimiento']); ?></p>
        </div>
        <div>
          <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($resultado['telefono']); ?></p>
          <p><strong>Cargo:</strong> <?php echo htmlspecialchars($resultado['cargo']); ?></p>
          <p><strong>Nombre predio:</strong> <?php echo htmlspecialchars($resultado['nombre_predio']); ?></p>
          <p><strong>Correo electrónico:</strong> <?php echo htmlspecialchars($resultado['correo_electronico']); ?></p>
        </div>
      </div>
      <p><strong>Estado registro:</strong> <?php echo htmlspecialchars($resultado['estado_registro']); ?></p>
    </section>
  <?php endif; ?>
</main>

  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: lista.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM personas WHERE id = ?");
$stmt->execute([$id]);
$persona = $stmt->fetch();

if (!$persona) {
    header('Location: lista.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Detalle de persona - Sistema de Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="<?php echo $body_class; ?>">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <div class="layout-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content">
      <h1>Detalle de persona</h1>

      <section class="detalle-card">
        <p><strong>Nombre:</strong> <?php echo htmlspecialchars($persona['nombres'] . ' ' . $persona['apellidos']); ?></p>
        <p><strong>Documento:</strong> <?php echo htmlspecialchars($persona['tipo_documento'] . ' ' . $persona['numero_documento']); ?></p>
        <p><strong>Afiliado:</strong> <?php echo htmlspecialchars($persona['afiliado']); ?></p>
        <p><strong>Zona:</strong> <?php echo htmlspecialchars($persona['zona']); ?></p>
        <p><strong>Género:</strong> <?php echo htmlspecialchars($persona['genero']); ?></p>
        <p><strong>Fecha de nacimiento:</strong> <?php echo htmlspecialchars($persona['fecha_nacimiento']); ?></p>
        <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($persona['telefono']); ?></p>
        <p><strong>Cargo:</strong> <?php echo htmlspecialchars($persona['cargo']); ?></p>
        <p><strong>Nombre predio:</strong> <?php echo htmlspecialchars($persona['nombre_predio']); ?></p>
        <p><strong>Correo electrónico:</strong> <?php echo htmlspecialchars($persona['correo_electronico']); ?></p>
        <p><strong>Estado registro:</strong> <?php echo htmlspecialchars($persona['estado_registro']); ?></p>
        <p><strong>Nota admin:</strong><br><?php echo nl2br(htmlspecialchars($persona['nota_admin'])); ?></p>

        <?php if (!empty($persona['foto_persona'])): ?>
          <p><strong>Foto persona:</strong><br>
            <img src="<?php echo htmlspecialchars($persona['foto_persona']); ?>" alt="Foto persona" style="max-width:200px;border-radius:8px;">
          </p>
        <?php endif; ?>

        <?php if (!empty($persona['foto_documento'])): ?>
          <p><strong>Foto documento:</strong><br>
            <img src="<?php echo htmlspecialchars($persona['foto_documento']); ?>" alt="Foto documento" style="max-width:200px;border-radius:8px;">
          </p>
        <?php endif; ?>

        <?php if (!empty($persona['foto_predio'])): ?>
          <p><strong>Foto predio:</strong><br>
            <img src="<?php echo htmlspecialchars($persona['foto_predio']); ?>" alt="Foto predio" style="max-width:200px;border-radius:8px;">
          </p>
        <?php endif; ?>

        <p>
           <a href="lista.php">&laquo; Volver a la lista</a>
            &nbsp;|&nbsp;
          <a href="editar.php?id=<?php echo $persona['id']; ?>">Editar este registro</a>
        </p>
        <p>
          <a href="exportar_pdf.php?id=<?php echo $persona['id']; ?>" target="_blank">Exportar a PDF</a>
        </p>
      </div>
      </section>
    </main>
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

$stmt = $pdo->query("
    SELECT id, nombres, apellidos, tipo_documento, numero_documento, fecha_registro
    FROM personas
    ORDER BY fecha_registro DESC
    LIMIT 100
");
$registros = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Exportar a PDF - Sistema de Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="<?php echo $body_class; ?>">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <div class="layout-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content">
      <h1>Exportar registros</h1>

      <section class="tabla-recientes">
        <h2>Selecciona un registro para exportar a PDF</h2>

        <?php if (empty($registros)): ?>
          <p>No hay registros.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Documento</th>
                <th>Fecha registro</th>
                <th>PDF</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($registros as $fila): ?>
                <tr>
                  <td><?php echo htmlspecialchars($fila['nombres'] . ' ' . $fila['apellidos']); ?></td>
                  <td><?php echo htmlspecialchars($fila['tipo_documento'] . ' ' . $fila['numero_documento']); ?></td>
                  <td><?php echo htmlspecialchars($fila['fecha_registro']); ?></td>
                  <td><a href="exportar_pdf.php?id=<?php echo $fila['id']; ?>">Descargar PDF</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

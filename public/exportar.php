<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

$mensaje = '';

// Guardar nombre_pdf si viene POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_nombre_pdf') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre_pdf = trim($_POST['nombre_pdf'] ?? '');

    if ($id > 0) {
        $stmt = $pdo->prepare("UPDATE personas SET nombre_pdf = ? WHERE id = ?");
        $stmt->execute([$nombre_pdf !== '' ? $nombre_pdf : null, $id]);
        $mensaje = 'Nombre de PDF actualizado.';
    }
}

// Obtener últimos registros (puedes ajustar el LIMIT)
$stmt = $pdo->query("
    SELECT id, nombres, apellidos, tipo_documento, numero_documento, fecha_registro, nombre_pdf
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
      <h1>Exportar registros a PDF</h1>

      <section class="form-card exportar-intro">
        <h2>Selecciona un registro</h2>
        <p>
          Aquí puedes asignar un nombre personalizado para el archivo PDF de cada registro
          y descargar la ficha en formato PDF.
        </p>
        <?php if ($mensaje): ?>
          <div class="alert" style="margin-top:8px;"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
      </section>

      <section class="tabla-recientes exportar-tabla">
        <?php if (empty($registros)): ?>
          <p>No hay registros para exportar.</p>
        <?php else: ?>
          <div class="tabla-scroll">
            <table>
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Documento</th>
                  <th>Fecha registro</th>
                  <th>Nombre del PDF</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($registros as $fila): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($fila['nombres'] . ' ' . $fila['apellidos']); ?></td>
                    <td><?php echo htmlspecialchars($fila['tipo_documento'] . ' ' . $fila['numero_documento']); ?></td>
                    <td><?php echo htmlspecialchars($fila['fecha_registro']); ?></td>
                    <td>
                      <form method="post" action="" class="inline-form exportar-nombre-form">
                        <input type="hidden" name="accion" value="guardar_nombre_pdf">
                        <input type="hidden" name="id" value="<?php echo $fila['id']; ?>">
                        <input
                          type="text"
                          name="nombre_pdf"
                          value="<?php echo htmlspecialchars($fila['nombre_pdf'] ?? ''); ?>"
                          placeholder="Ej: Ficha_<?php echo htmlspecialchars($fila['numero_documento']); ?>"
                        >
                        <button type="submit" class="icon-button" title="Guardar nombre PDF">
                          <svg viewBox="0 0 24 24" class="icon-svg">
                            <polyline points="4 13 9 18 20 6" fill="none" stroke-width="2"></polyline>
                          </svg>
                        </button>
                      </form>
                    </td>
                    <td class="tabla-acciones">
                      <a
                        href="exportar_pdf.php?id=<?php echo $fila['id']; ?>"
                        class="icon-button"
                        title="Descargar PDF"
                      >
                        <svg viewBox="0 0 24 24" class="icon-svg">
                          <rect x="6" y="3" width="12" height="18" rx="2"></rect>
                          <polyline points="9 10 12 13 15 10" fill="none"></polyline>
                          <line x1="12" y1="13" x2="12" y2="7"></line>
                        </svg>
                      </a>
                      <a
                        href="ver.php?id=<?php echo $fila['id']; ?>"
                        class="icon-button"
                        title="Ver ficha"
                      >
                        <svg viewBox="0 0 24 24" class="icon-svg">
                          <path d="M2 12s3-6 10-6 10 6 10 6-3 6-10 6S2 12 2 12z" fill="none"></path>
                          <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

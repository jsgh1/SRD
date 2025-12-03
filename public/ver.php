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

// Helper pequeño para mostrar valor o "—"
function v($valor) {
    return $valor !== null && $valor !== '' ? htmlspecialchars($valor) : '—';
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
      <section class="detalle-card detalle-persona-card">
        <!-- Encabezado -->
        <div class="detalle-header">
          <div class="detalle-avatar">
            <?php if (!empty($persona['foto_persona'])): ?>
              <img src="<?php echo htmlspecialchars($persona['foto_persona']); ?>" alt="Foto persona">
            <?php else: ?>
              <svg viewBox="0 0 24 24" class="icon-svg">
                <circle cx="12" cy="9" r="4"></circle>
                <path d="M5 20c0-3.3 3-6 7-6s7 2.7 7 6" fill="none"></path>
              </svg>
            <?php endif; ?>
          </div>
          <div class="detalle-header-main">
            <h1><?php echo v($persona['nombres'] . ' ' . $persona['apellidos']); ?></h1>
            <p class="detalle-doc">
              <?php echo v($persona['tipo_documento']); ?>
              <?php if (!empty($persona['numero_documento'])): ?>
                · <?php echo v($persona['numero_documento']); ?>
              <?php endif; ?>
            </p>
            <div class="detalle-header-meta">
              <span class="chip-estado chip-<?php echo strtolower($persona['estado_registro'] ?? 'pendiente'); ?>">
                <svg viewBox="0 0 24 24" class="icon-svg">
                  <?php if (($persona['estado_registro'] ?? '') === 'Completado'): ?>
                    <polyline points="4 13 9 18 20 6" fill="none" stroke-width="2"></polyline>
                  <?php else: ?>
                    <circle cx="12" cy="12" r="7" fill="none"></circle>
                    <path d="M12 8v4l2.5 2.5" fill="none"></path>
                  <?php endif; ?>
                </svg>
                <?php echo v($persona['estado_registro']); ?>
              </span>
              <span class="detalle-fecha">
                Registrado el: <?php echo v($persona['fecha_registro']); ?>
              </span>
            </div>
          </div>
        </div>

        <!-- Contenido principal -->
        <div class="detalle-body">
          <div class="detalle-col">
            <h2>Datos personales</h2>
            <p><strong>Afiliado:</strong> <?php echo v($persona['afiliado']); ?></p>
            <p><strong>Zona:</strong> <?php echo v($persona['zona']); ?></p>
            <p><strong>Género:</strong> <?php echo v($persona['genero']); ?></p>
            <p><strong>Fecha de nacimiento:</strong> <?php echo v($persona['fecha_nacimiento']); ?></p>
          </div>

          <div class="detalle-col">
            <h2>Contacto</h2>
            <p><strong>Teléfono:</strong> <?php echo v($persona['telefono']); ?></p>
            <p><strong>Correo electrónico:</strong> <?php echo v($persona['correo_electronico']); ?></p>
            <p><strong>Cargo:</strong> <?php echo v($persona['cargo']); ?></p>
            <p><strong>Nombre del predio:</strong> <?php echo v($persona['nombre_predio']); ?></p>
          </div>
        </div>

        <!-- Nota admin -->
        <div class="detalle-nota">
          <h2>Nota del administrador</h2>
          <p><?php echo nl2br(v($persona['nota_admin'])); ?></p>
        </div>

        <!-- Galería de fotos -->
        <div class="detalle-fotos">
          <?php if (!empty($persona['foto_persona'])): ?>
            <div class="detalle-foto-item">
              <h3>Foto persona</h3>
              <img src="<?php echo htmlspecialchars($persona['foto_persona']); ?>" alt="Foto persona">
            </div>
          <?php endif; ?>

          <?php if (!empty($persona['foto_documento'])): ?>
            <div class="detalle-foto-item">
              <h3>Foto documento</h3>
              <img src="<?php echo htmlspecialchars($persona['foto_documento']); ?>" alt="Foto documento">
            </div>
          <?php endif; ?>

          <?php if (!empty($persona['foto_predio'])): ?>
            <div class="detalle-foto-item">
              <h3>Foto predio</h3>
              <img src="<?php echo htmlspecialchars($persona['foto_predio']); ?>" alt="Foto predio">
            </div>
          <?php endif; ?>

          <?php if (empty($persona['foto_persona']) && empty($persona['foto_documento']) && empty($persona['foto_predio'])): ?>
            <p>No hay imágenes registradas para esta persona.</p>
          <?php endif; ?>
        </div>

        <!-- Acciones -->
        <div class="detalle-acciones">
          <a href="lista.php" class="btn-outline btn-icon">
            <span class="btn-icon-svg">
              <svg viewBox="0 0 24 24" class="icon-svg">
                <polyline points="15 18 9 12 15 6" fill="none"></polyline>
                <line x1="9" y1="12" x2="20" y2="12"></line>
              </svg>
            </span>
            <span>Volver a la lista</span>
          </a>

          <a href="editar.php?id=<?php echo $persona['id']; ?>" class="btn-primary btn-icon">
            <span class="btn-icon-svg">
              <svg viewBox="0 0 24 24" class="icon-svg">
                <path d="M4 20h4l10.5-10.5a1.5 1.5 0 0 0-2.1-2.1L6 18v4z" fill="none"></path>
                <path d="M14 6l4 4" fill="none"></path>
              </svg>
            </span>
            <span>Editar</span>
          </a>

          <a href="exportar_pdf.php?id=<?php echo $persona['id']; ?>" class="btn-ghost btn-icon">
            <span class="btn-icon-svg">
              <svg viewBox="0 0 24 24" class="icon-svg">
                <rect x="6" y="3" width="12" height="18" rx="2"></rect>
                <polyline points="9 10 12 13 15 10" fill="none"></polyline>
                <line x1="12" y1="13" x2="12" y2="7"></line>
              </svg>
            </span>
            <span>Exportar PDF</span>
          </a>
        </div>
      </section>
    </main>
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

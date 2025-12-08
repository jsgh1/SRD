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

// Helper para que las rutas de imagen apunten bien desde /public
function ruta_publica_upload(?string $ruta): ?string {
    if (!$ruta) return null;

    // Si ya es URL absoluta, la dejamos
    if (preg_match('~^https?://~i', $ruta) || strpos($ruta, '//') === 0) {
        return $ruta;
    }

    // Si ya comienza con ../ (por ejemplo ../uploads/...)
    if (strpos($ruta, '../') === 0) {
        return $ruta;
    }

    // Si viene como /uploads/..., anteponer ..
    if (strpos($ruta, '/uploads/') === 0) {
        return '..' . $ruta;
    }

    // Si viene como uploads/..., anteponer ../
    if (strpos($ruta, 'uploads/') === 0) {
        return '../' . $ruta;
    }

    // Cualquier otra cosa se devuelve tal cual
    return $ruta;
}

// --- Campos extra configurados y valores de esta persona ---
$stmtCampos = $pdo->query("
    SELECT *
    FROM campos_extra_registro
    WHERE activo = 1
    ORDER BY grupo, orden, id
");
$campos_extra = $stmtCampos->fetchAll(PDO::FETCH_ASSOC);

$stmtExtra = $pdo->prepare("
    SELECT campo_id, valor
    FROM personas_campos_extra
    WHERE persona_id = ?
");
$stmtExtra->execute([$id]);
$extras_valores = $stmtExtra->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Detalle de persona - Sistema de Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="<?php echo htmlspecialchars($body_class); ?>">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <div class="layout-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content">
      <section class="detalle-card detalle-persona-card">
        <!-- Encabezado -->
        <div class="detalle-header">
          <div class="detalle-avatar">
            <?php if (!empty($persona['foto_persona'])): ?>
              <img
                src="<?php echo htmlspecialchars(ruta_publica_upload($persona['foto_persona'])); ?>"
                alt="Foto persona"
              >
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
              <span class="chip-estado chip-<?php echo ($persona['estado_registro'] ?? '') === 'Completado' ? 'completado' : 'pendiente'; ?>">
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

            <!-- Campos extra grupo "persona" -->
            <?php foreach ($campos_extra as $campo): ?>
              <?php if ($campo['grupo'] === 'persona'): ?>
                <?php
                  $cid = $campo['id'];
                  $valorExtra = $extras_valores[$cid] ?? null;
                ?>
                <p>
                  <strong><?php echo htmlspecialchars($campo['nombre_label']); ?>:</strong>
                  <?php echo v($valorExtra); ?>
                </p>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>

          <div class="detalle-col">
            <h2>Contacto</h2>
            <p><strong>Teléfono:</strong> <?php echo v($persona['telefono']); ?></p>
            <p><strong>Correo electrónico:</strong> <?php echo v($persona['correo_electronico']); ?></p>
            <p><strong>Cargo:</strong> <?php echo v($persona['cargo']); ?></p>
            <p><strong>Nombre del predio:</strong> <?php echo v($persona['nombre_predio']); ?></p>

            <!-- Campos extra grupo "contacto" -->
            <?php foreach ($campos_extra as $campo): ?>
              <?php if ($campo['grupo'] === 'contacto'): ?>
                <?php
                  $cid = $campo['id'];
                  $valorExtra = $extras_valores[$cid] ?? null;
                ?>
                <p>
                  <strong><?php echo htmlspecialchars($campo['nombre_label']); ?>:</strong>
                  <?php echo v($valorExtra); ?>
                </p>
              <?php endif; ?>
            <?php endforeach; ?>

            <!-- Campos extra grupo "predio" -->
            <?php foreach ($campos_extra as $campo): ?>
              <?php if ($campo['grupo'] === 'predio'): ?>
                <?php
                  $cid = $campo['id'];
                  $valorExtra = $extras_valores[$cid] ?? null;
                ?>
                <p>
                  <strong><?php echo htmlspecialchars($campo['nombre_label']); ?>:</strong>
                  <?php echo v($valorExtra); ?>
                </p>
              <?php endif; ?>
            <?php endforeach; ?>
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
              <img
                src="<?php echo htmlspecialchars(ruta_publica_upload($persona['foto_persona'])); ?>"
                alt="Foto persona"
                class="detalle-img-clickable"
                data-full="<?php echo htmlspecialchars(ruta_publica_upload($persona['foto_persona'])); ?>"
              >
            </div>
          <?php endif; ?>

          <?php if (!empty($persona['foto_documento'])): ?>
            <div class="detalle-foto-item">
              <h3>Foto documento</h3>
              <img
                src="<?php echo htmlspecialchars(ruta_publica_upload($persona['foto_documento'])); ?>"
                alt="Foto documento"
                class="detalle-img-clickable"
                data-full="<?php echo htmlspecialchars(ruta_publica_upload($persona['foto_documento'])); ?>"
              >
            </div>
          <?php endif; ?>

          <?php if (!empty($persona['foto_predio'])): ?>
            <div class="detalle-foto-item">
              <h3>Foto predio</h3>
              <img
                src="<?php echo htmlspecialchars(ruta_publica_upload($persona['foto_predio'])); ?>"
                alt="Foto predio"
                class="detalle-img-clickable"
                data-full="<?php echo htmlspecialchars(ruta_publica_upload($persona['foto_predio'])); ?>"
              >
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

          <!-- Ahora solo redirige a exportar.php -->
          <a
            href="exportar.php"
            class="btn-outline btn-icon btn-export-pdf"
          >
            <span class="btn-icon-svg">
              <svg viewBox="0 0 24 24" class="icon-svg">
                <rect x="6" y="3" width="12" height="18" rx="2"></rect>
                <polyline points="9 10 12 13 15 10" fill="none"></polyline>
                <line x1="12" y1="13" x2="12" y2="7"></line>
              </svg>
            </span>
            <span>Ir a exportar PDF</span>
          </a>
        </div>
      </section>
    </main>
  </div>

  <!-- Modal genérico para imágenes de detalle -->
  <div id="modalImagen" class="modal-overlay">
    <div class="modal-image-box">
      <button type="button" class="modal-close" id="modalImagenCerrar">&times;</button>
      <img src="" alt="Imagen ampliada" id="modalImagenImg">
    </div>
  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    const overlay = document.getElementById('modalImagen');
    const modalImg = document.getElementById('modalImagenImg');
    const btnCerrar = document.getElementById('modalImagenCerrar');

    if (!overlay || !modalImg || !btnCerrar) return;

    // Abrir modal al hacer clic en cualquier imagen clicable
    document.querySelectorAll('.detalle-img-clickable').forEach(function (img) {
      img.addEventListener('click', function () {
        const src = this.getAttribute('data-full') || this.getAttribute('src');
        modalImg.src = src;
        overlay.classList.add('is-open');
      });
    });

    function cerrarModal() {
      overlay.classList.remove('is-open');
      modalImg.src = '';
    }

    btnCerrar.addEventListener('click', cerrarModal);

    // Cerrar si se hace clic en el fondo oscuro
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) {
        cerrarModal();
      }
    });

    // Cerrar con ESC
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
        cerrarModal();
      }
    });
  });
  </script>
</body>
</html>

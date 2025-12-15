<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ===================================================
// Filtros (GET)
// ===================================================
$search = trim($_GET['search'] ?? ($_GET['q'] ?? ''));
$estado = trim($_GET['estado'] ?? '');

$where = [];
$params = [];

if ($search !== '') {
  $like = '%' . $search . '%';
  $where[] = "(nombres LIKE ? OR apellidos LIKE ? OR numero_documento LIKE ?)";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}
if ($estado !== '') {
  $where[] = "estado_registro = ?";
  $params[] = $estado;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ===================================================
// Guardar nombre_pdf por registro (POST)
// ===================================================
$mensaje = '';
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_nombre_pdf') {
  $id = (int)($_POST['id'] ?? 0);
  $nombre_pdf = trim($_POST['nombre_pdf'] ?? '');

  if ($id > 0) {
    $stmtUpd = $pdo->prepare("UPDATE personas SET nombre_pdf = ? WHERE id = ?");
    $stmtUpd->execute([$nombre_pdf !== '' ? $nombre_pdf : null, $id]);
    $mensaje = 'Nombre de PDF actualizado.';
  } else {
    $errores[] = 'ID inválido.';
  }
}

// ===================================================
// Guardar nombre PDF (TABLA) en sesión (AJAX)
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_nombre_pdf_tabla') {
  $nombre = trim($_POST['nombre_archivo'] ?? '');

  if ($nombre === '') {
    unset($_SESSION['nombre_pdf_tabla']);
    $msg = 'Nombre del PDF (tabla) limpiado. Se usará el nombre por defecto al descargar.';
  } else {
    $_SESSION['nombre_pdf_tabla'] = $nombre;
    $msg = 'Nombre del PDF (tabla) actualizado.';
  }

  if (($_POST['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'message' => $msg]);
    exit;
  }

  $mensaje = $msg;
}

$nombre_pdf_tabla = $_SESSION['nombre_pdf_tabla'] ?? '';

// ===================================================
// Columnas configurables para exportar TABLA (asistencia)
// ===================================================
$stmtCols = $pdo->query("
  SELECT id, nombre_campo, etiqueta
  FROM campos_filtros_exportar
  WHERE activo = 1
  ORDER BY orden ASC, id ASC
");
$cols_tabla = $stmtCols->fetchAll(PDO::FETCH_ASSOC);

// ===================================================
// Registros para tabla principal (limit 100)
// ===================================================
$sql = "
  SELECT id, nombres, apellidos, tipo_documento, numero_documento,
         afiliado, zona, genero, cargo, estado_registro, fecha_registro,
         nombre_pdf
  FROM personas
  $where_sql
  ORDER BY fecha_registro DESC
  LIMIT 100
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

function v($val) {
  return ($val !== null && $val !== '') ? h($val) : '—';
}

$incHeader  = __DIR__ . '/../includes/header.php';
$incSidebar = __DIR__ . '/../includes/sidebar.php';
$incFooter  = __DIR__ . '/../includes/footer.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Exportar a PDF - Sistema de Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">

  <!-- Ajustes sutiles SOLO para este recuadro (sin romper tu CSS global) -->
  <style>
    /* Input más corto y check al lado */
    .exportar-tabla-nombre-form input[type="text"]{
      max-width: 260px;
    }
    /* Color del botón-check según tema */
    body.tema-claro #btnGuardarNombreTabla { color: #111827; }
    body.tema-oscuro #btnGuardarNombreTabla { color: #ffffff; }

    /* Footer mejor separado del checklist */
    .exportar-checks-footer-tabla{
      display:flex;
      align-items:center;
      justify-content:flex-start;
      gap:12px;
      margin-top:12px;
      padding-top:10px;
      border-top:1px solid rgba(148,163,184,.35);
    }
    body.tema-oscuro .exportar-checks-footer-tabla{
      border-top-color:#1f2937;
    }
  </style>
</head>

<body class="<?php echo h($body_class); ?>">
  <?php if (file_exists($incHeader)) include $incHeader; ?>

  <div class="layout-container">
    <?php if (file_exists($incSidebar)) include $incSidebar; ?>

    <main class="content">
      <h1>Exportar registros a PDF</h1>

      <?php if (!empty($errores)): ?>
        <div class="alert alert-error" style="margin-bottom:10px;">
          <ul>
            <?php foreach ($errores as $e): ?>
              <li><?php echo h($e); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($mensaje): ?>
        <div class="alert" style="margin-bottom:10px;"><?php echo h($mensaje); ?></div>
      <?php endif; ?>

      <!-- Filtros -->
      <section class="form-card exportar-intro">
        <h2>Selecciona un registro</h2>
        <p>
          Asigna un nombre personalizado al PDF de cada registro y descarga la ficha.
          También puedes exportar una tabla con columnas configurables.
        </p>

        <form method="get" action="" class="consulta-form" style="margin-top:10px;">
          <div class="form-row">
            <div class="form-group">
              <label for="search">Buscar (nombre, apellido o documento)</label>
              <input
                type="text"
                id="search"
                name="search"
                value="<?php echo h($search); ?>"
                placeholder="Ej: Juan, Pérez o número de documento"
              >
            </div>

            <div class="form-group">
              <label for="estado">Estado del registro</label>
              <select name="estado" id="estado">
                <option value="">Todos</option>
                <option value="Pendiente"  <?php echo $estado === 'Pendiente'  ? 'selected' : ''; ?>>Pendiente</option>
                <option value="Completado" <?php echo $estado === 'Completado' ? 'selected' : ''; ?>>Completado</option>
              </select>
            </div>
          </div>

          <button type="submit" class="btn-primary btn-small">Aplicar filtros</button>
        </form>
      </section>

      <!-- Exportar tabla -->
      <section class="form-card" style="margin-top:14px;">
        <h2>Exportar tabla</h2>
        <p class="config-desc" style="margin-top:6px;">
          Selecciona hasta 5 campos para agregarlos como columnas extra. Se exporta con los mismos filtros (buscar/estado).
        </p>

        <!-- ALERTA SOLO PARA EL GUARDADO DEL NOMBRE (TABLA) -->
        <div class="alert" id="alertNombreTabla" style="display:none; margin-top:10px;"></div>

        <form method="get" action="exportar_tabla_pdf.php" id="formTablaExport">
          <input type="hidden" name="q" value="<?php echo h($search); ?>">
          <input type="hidden" name="estado" value="<?php echo h($estado); ?>">

          <div class="form-group" style="margin-top:10px;">
            <label for="nombre_archivo">Nombre del PDF (tabla)</label>

            <div class="exportar-nombre-form exportar-tabla-nombre-form">
              <input
                type="text"
                id="nombre_archivo"
                name="nombre_archivo"
                value="<?php echo h($nombre_pdf_tabla); ?>"
                placeholder="Ej: Tabla_Mes_Año"
              >
              <button type="button" class="icon-button" id="btnGuardarNombreTabla" title="Guardar nombre PDF (tabla)">
                <svg viewBox="0 0 24 24" class="icon-svg">
                  <polyline points="4 13 9 18 20 6" fill="none" stroke-width="2"></polyline>
                </svg>
              </button>
            </div>

            <small class="config-desc" style="margin-top:6px; display:block;">
              Si lo dejas vacío, se descargará como <strong>Tabla_YYYY-mm-dd</strong>.
            </small>
          </div>

          <?php if (empty($cols_tabla)): ?>
            <p>No hay columnas configuradas para exportar la tabla.</p>
          <?php else: ?>

            <!-- ✅ FILTROS BONITOS (como antes) -->
            <div class="exportar-tabla-checklist" id="checksTablaExport" style="margin-top:10px;">
              <?php foreach ($cols_tabla as $c): ?>
                <label class="exportar-check-item">
                  <input
                    type="checkbox"
                    name="campos[]"
                    value="<?php echo h($c['nombre_campo']); ?>"
                    class="chkCampoTabla"
                  >
                  <span><?php echo h($c['etiqueta']); ?></span>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="exportar-checks-footer-tabla">
              <small id="contadorSeleccion">Seleccionadas: 0 / 5</small>
              <button type="submit" class="btn-primary btn-small">
                Descargar PDF de tabla
              </button>
            </div>
          <?php endif; ?>
        </form>
      </section>

      <!-- Tabla de registros -->
      <section class="tabla-recientes exportar-tabla" style="margin-top:14px;">
        <h2>Registros (<?php echo count($registros); ?>)</h2>

        <?php if (empty($registros)): ?>
          <p>No hay registros para exportar con los filtros seleccionados.</p>
        <?php else: ?>
          <div class="tabla-scroll">
            <table>
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Documento</th>
                  <th>Afiliado</th>
                  <th>Zona</th>
                  <th>Género</th>
                  <th>Cargo</th>
                  <th>Estado</th>
                  <th>Fecha registro</th>
                  <th>Nombre del PDF</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($registros as $fila): ?>
                  <?php
                    $nombre = trim(($fila['nombres'] ?? '') . ' ' . ($fila['apellidos'] ?? ''));
                    $doc = trim(($fila['tipo_documento'] ?? '') . ' ' . ($fila['numero_documento'] ?? ''));
                    $estadoRow = $fila['estado_registro'] ?? '';
                    $clase_estado = ($estadoRow === 'Completado') ? 'completado' : 'pendiente';
                  ?>
                  <tr>
                    <td><?php echo v($nombre); ?></td>
                    <td><?php echo v($doc); ?></td>
                    <td><?php echo v($fila['afiliado'] ?? null); ?></td>
                    <td><?php echo v($fila['zona'] ?? null); ?></td>
                    <td><?php echo v($fila['genero'] ?? null); ?></td>
                    <td><?php echo v($fila['cargo'] ?? null); ?></td>
                    <td>
                      <span class="chip-estado chip-<?php echo $clase_estado; ?>">
                        <?php echo v($estadoRow); ?>
                      </span>
                    </td>
                    <td><?php echo v($fila['fecha_registro'] ?? null); ?></td>

                    <td>
                      <form method="post" action="" class="inline-form exportar-nombre-form">
                        <input type="hidden" name="accion" value="guardar_nombre_pdf">
                        <input type="hidden" name="id" value="<?php echo (int)$fila['id']; ?>">
                        <input
                          type="text"
                          name="nombre_pdf"
                          value="<?php echo h($fila['nombre_pdf'] ?? ''); ?>"
                          placeholder="Ej: Ficha_<?php echo h($fila['numero_documento'] ?? ''); ?>"
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
                        href="exportar_pdf.php?id=<?php echo (int)$fila['id']; ?>"
                        class="icon-button"
                        title="Descargar ficha (PDF)"
                      >
                        <svg viewBox="0 0 24 24" class="icon-svg">
                          <rect x="6" y="3" width="12" height="18" rx="2"></rect>
                          <polyline points="9 10 12 13 15 10" fill="none"></polyline>
                          <line x1="12" y1="13" x2="12" y2="7"></line>
                        </svg>
                      </a>

                      <a
                        href="ver.php?id=<?php echo (int)$fila['id']; ?>"
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

  <div class="modal-overlay" id="modalLimiteCampos">
    <div class="modal-box">
      <h2>Límite de selección</h2>
      <p>Solo puedes seleccionar máximo 5 campos.</p>
      <div class="modal-actions">
        <button type="button" class="btn-primary" id="btnCerrarModalLimite">Aceptar</button>
      </div>
    </div>
  </div>

  <script>
    // Limite 5 checks
    (function(){
      var max = 5;
      var modal = document.getElementById('modalLimiteCampos');
      var btnCerrar = document.getElementById('btnCerrarModalLimite');
      var checks = Array.prototype.slice.call(document.querySelectorAll('.chkCampoTabla'));
      var contador = document.getElementById('contadorSeleccion');

      function abrirModal() { modal.classList.add('is-open'); }
      function cerrarModal() { modal.classList.remove('is-open'); }

      function contar() {
        var n = checks.filter(function(c){ return c.checked; }).length;
        if (contador) contador.textContent = 'Seleccionadas: ' + n + ' / ' + max;
        return n;
      }

      checks.forEach(function(chk){
        chk.addEventListener('change', function(){
          var n = contar();
          if (n > max) {
            chk.checked = false;
            contar();
            abrirModal();
          }
        });
      });

      if (btnCerrar) btnCerrar.addEventListener('click', cerrarModal);
      if (modal) modal.addEventListener('click', function(e){
        if (e.target === modal) cerrarModal();
      });

      contar();
    })();

    // Guardar nombre del PDF (tabla) sin recargar + alerta
    (function(){
      var btn = document.getElementById('btnGuardarNombreTabla');
      var input = document.getElementById('nombre_archivo');
      var alertBox = document.getElementById('alertNombreTabla');

      function showAlert(msg) {
        if (!alertBox) return;
        alertBox.textContent = msg;
        alertBox.style.display = 'block';
        window.clearTimeout(showAlert._t);
        showAlert._t = window.setTimeout(function(){
          alertBox.style.display = 'none';
        }, 3500);
      }

      if (!btn || !input) return;

      btn.addEventListener('click', function(){
        var fd = new FormData();
        fd.append('accion', 'guardar_nombre_pdf_tabla');
        fd.append('nombre_archivo', input.value || '');
        fd.append('ajax', '1');

        btn.disabled = true;

        fetch(window.location.href, {
          method: 'POST',
          body: fd
        })
        .then(function(r){ return r.json(); })
        .then(function(data){
          if (data && data.ok) showAlert(data.message || 'Nombre actualizado.');
          else showAlert('No se pudo guardar el nombre.');
        })
        .catch(function(){
          showAlert('No se pudo guardar el nombre.');
        })
        .finally(function(){
          btn.disabled = false;
        });
      });
    })();
  </script>

  <?php if (file_exists($incFooter)) include $incFooter; ?>
</body>
</html>

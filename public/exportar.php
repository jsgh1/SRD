<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

$mensaje = '';

/**
 * Helper para mostrar valores seguros
 */
function v_exportar($valor) {
    return ($valor !== null && $valor !== '') ? htmlspecialchars($valor) : '—';
}

/**
 * Campos configurados para mostrarse en EXPORTAR
 * (tabla campos_registro, columna mostrar_en_exportar)
 */
function obtenerCamposExportar(PDO $pdo) {
    $sql = "
        SELECT nombre_campo, etiqueta
        FROM campos_registro
        WHERE mostrar_en_exportar = 1
        ORDER BY orden ASC, id ASC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$campos_exportar = obtenerCamposExportar($pdo);

/* ============================
   Filtros para la tabla
   ============================ */

// Buscador general
$search = trim($_GET['search'] ?? '');

// Filtro por estado de registro
$filtro_estado = trim($_GET['estado'] ?? '');

// Construcción del WHERE
$where  = [];
$params = [];

if ($search !== '') {
    $where[] = "(nombres LIKE ? OR apellidos LIKE ? OR numero_documento LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if ($filtro_estado !== '') {
    $where[]  = "estado_registro = ?";
    $params[] = $filtro_estado;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

/* ============================
   Guardar nombre_pdf si viene POST
   ============================ */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['accion']) &&
    $_POST['accion'] === 'guardar_nombre_pdf'
) {
    $id = (int)($_POST['id'] ?? 0);
    $nombre_pdf = trim($_POST['nombre_pdf'] ?? '');

    if ($id > 0) {
        $stmtUpd = $pdo->prepare("UPDATE personas SET nombre_pdf = ? WHERE id = ?");
        $stmtUpd->execute([$nombre_pdf !== '' ? $nombre_pdf : null, $id]);
        $mensaje = 'Nombre de PDF actualizado.';
    }
}

/* ============================
   Obtener registros (con filtros)
   ============================ */

$sql = "
    SELECT *
    FROM personas
    $where_sql
    ORDER BY fecha_registro DESC
    LIMIT 100
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
          y descargar la ficha en formato PDF. Puedes usar el buscador para encontrar más rápido
          el registro que necesitas.
        </p>

        <?php if ($mensaje): ?>
          <div class="alert" style="margin-top:8px;"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <!-- Filtros de búsqueda para exportar -->
        <form method="get" action="" class="consulta-form" style="margin-top:10px;">
          <div class="form-row">
            <div class="form-group">
              <label for="search">Buscar (nombre, apellido o documento)</label>
              <input
                type="text"
                id="search"
                name="search"
                value="<?php echo htmlspecialchars($search); ?>"
                placeholder="Ej: Juan, Pérez o número de documento"
              >
            </div>
            <div class="form-group">
              <label for="estado">Estado del registro</label>
              <select name="estado" id="estado">
                <option value="">Todos</option>
                <option value="Pendiente"   <?php echo $filtro_estado === 'Pendiente'   ? 'selected' : ''; ?>>Pendiente</option>
                <option value="Completado"  <?php echo $filtro_estado === 'Completado'  ? 'selected' : ''; ?>>Completado</option>
              </select>
            </div>
          </div>
          <button type="submit" class="btn-primary btn-small">Aplicar filtros</button>
        </form>
      </section>

      <section class="tabla-recientes exportar-tabla">
        <?php if (empty($registros)): ?>
          <p>No hay registros para exportar con los filtros seleccionados.</p>
        <?php else: ?>
          <div class="tabla-scroll">
            <table>
              <thead>
                <tr>
                  <th>Nombre</th>
                  <th>Documento</th>
                  <?php if (!empty($campos_exportar)): ?>
                    <?php foreach ($campos_exportar as $campo): ?>
                      <th><?php echo htmlspecialchars($campo['etiqueta']); ?></th>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  <th>Fecha registro</th>
                  <th>Nombre del PDF</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($registros as $fila): ?>
                  <tr>
                    <td>
                      <?php echo v_exportar(($fila['nombres'] ?? '') . ' ' . ($fila['apellidos'] ?? '')); ?>
                    </td>
                    <td>
                      <?php
                        $doc = trim(($fila['tipo_documento'] ?? '') . ' ' . ($fila['numero_documento'] ?? ''));
                        echo v_exportar($doc);
                      ?>
                    </td>

                    <?php if (!empty($campos_exportar)): ?>
                      <?php foreach ($campos_exportar as $campo): ?>
                        <?php
                          $nombre_campo = $campo['nombre_campo'];
                          $valor = $fila[$nombre_campo] ?? null;
                        ?>
                        <td><?php echo v_exportar($valor); ?></td>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <td><?php echo v_exportar($fila['fecha_registro'] ?? null); ?></td>

                    <td>
                      <form method="post" action="" class="inline-form exportar-nombre-form">
                        <input type="hidden" name="accion" value="guardar_nombre_pdf">
                        <input type="hidden" name="id" value="<?php echo (int)$fila['id']; ?>">
                        <input
                          type="text"
                          name="nombre_pdf"
                          value="<?php echo htmlspecialchars($fila['nombre_pdf'] ?? ''); ?>"
                          placeholder="Ej: Ficha_<?php echo htmlspecialchars($fila['numero_documento'] ?? ''); ?>"
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
                        title="Descargar PDF"
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
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

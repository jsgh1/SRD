<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

// Aceptamos tanto ?search= como ?q= (buscador del header)
$search          = trim($_GET['search'] ?? ($_GET['q'] ?? ''));
$filtro_estado   = trim($_GET['estado'] ?? '');
$filtro_tipo_doc = trim($_GET['tipo_documento'] ?? '');

$por_pagina = 20;
$pagina     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset     = ($pagina - 1) * $por_pagina;

// --- Eliminar registro ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['accion']) &&
    $_POST['accion'] === 'eliminar'
) {
    $id_eliminar = (int)($_POST['id'] ?? 0);

    if ($id_eliminar > 0) {
        $stmt = $pdo->prepare("SELECT foto_persona, foto_documento, foto_predio FROM personas WHERE id = ?");
        $stmt->execute([$id_eliminar]);
        $fila = $stmt->fetch();

        if ($fila) {
            foreach (['foto_persona', 'foto_documento', 'foto_predio'] as $campo) {
                if (!empty($fila[$campo])) {
                    // En BD tienes algo tipo: /uploads/personas/archivo.jpg
                    $ruta = __DIR__ . '/..' . $fila[$campo];
                    if (file_exists($ruta)) {
                        @unlink($ruta);
                    }
                }
            }

            $del = $pdo->prepare("DELETE FROM personas WHERE id = ?");
            $del->execute([$id_eliminar]);
        }
    }

    // Redirigir quitando page pero respetando filtros
    $qs = $_GET;
    unset($qs['page']);
    $query_string = http_build_query($qs);
    header('Location: lista.php' . ($query_string ? '?' . $query_string : ''));
    exit;
}

// --- Construcción de filtros ---
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
    $where[] = "estado_registro = ?";
    $params[] = $filtro_estado;
}

if ($filtro_tipo_doc !== '') {
    $where[] = "tipo_documento = ?";
    $params[] = $filtro_tipo_doc;
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// --- Total para paginación ---
$stmt_total = $pdo->prepare("SELECT COUNT(*) AS total FROM personas $where_sql");
$stmt_total->execute($params);
$total_registros = (int)($stmt_total->fetch()['total'] ?? 0);
$total_paginas   = max(1, (int)ceil($total_registros / $por_pagina));

// --- Consulta de registros ---
$sql = "
    SELECT id, nombres, apellidos, tipo_documento, numero_documento,
           fecha_registro, estado_registro
    FROM personas
    $where_sql
    ORDER BY fecha_registro DESC
    LIMIT $por_pagina OFFSET $offset
";
$stmt_lista = $pdo->prepare($sql);
$stmt_lista->execute($params);
$registros = $stmt_lista->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Lista de registros - Sistema de Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="<?php echo htmlspecialchars($body_class); ?>">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <div class="layout-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content">
      <h1>Lista de registros</h1>

      <!-- Filtros -->
      <section class="form-card">
        <h2>Filtros de búsqueda</h2>
        <form method="get" action="">
          <div class="form-row">
            <div class="form-group">
              <label for="search">Buscar (nombre, apellido o documento)</label>
              <input
                type="text"
                id="search"
                name="search"
                value="<?php echo htmlspecialchars($search); ?>"
              >
            </div>
            <div class="form-group">
              <label for="estado">Estado</label>
              <select name="estado" id="estado">
                <option value="">Todos</option>
                <option value="Pendiente"  <?php echo $filtro_estado === 'Pendiente'  ? 'selected' : ''; ?>>Pendiente</option>
                <option value="Completado" <?php echo $filtro_estado === 'Completado' ? 'selected' : ''; ?>>Completado</option>
              </select>
            </div>
            <div class="form-group">
              <label for="tipo_documento">Tipo de documento</label>
              <select name="tipo_documento" id="tipo_documento">
                <option value="">Todos</option>
                <option value="Registro Civil"        <?php echo $filtro_tipo_doc === 'Registro Civil'        ? 'selected' : ''; ?>>Registro Civil</option>
                <option value="Tarjeta de Identidad"  <?php echo $filtro_tipo_doc === 'Tarjeta de Identidad'  ? 'selected' : ''; ?>>Tarjeta de Identidad</option>
                <option value="Cédula de Ciudadanía"  <?php echo $filtro_tipo_doc === 'Cédula de Ciudadanía'  ? 'selected' : ''; ?>>Cédula de Ciudadanía</option>
                <option value="Cédula de Extranjería" <?php echo $filtro_tipo_doc === 'Cédula de Extranjería' ? 'selected' : ''; ?>>Cédula de Extranjería</option>
                <option value="NIT"                   <?php echo $filtro_tipo_doc === 'NIT'                   ? 'selected' : ''; ?>>NIT</option>
              </select>
            </div>
          </div>
          <button type="submit" class="btn-primary">Aplicar filtros</button>
        </form>
      </section>

      <!-- Tabla de resultados -->
      <section class="tabla-recientes">
        <h2>Registros (<?php echo $total_registros; ?>)</h2>

        <?php if (empty($registros)): ?>
          <p>No se encontraron registros.</p>
        <?php else: ?>
          <table>
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Tipo doc</th>
                <th>N° documento</th>
                <th>Fecha registro</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($registros as $fila): ?>
                <tr>
                  <td><?php echo htmlspecialchars($fila['nombres'] . ' ' . $fila['apellidos']); ?></td>
                  <td><?php echo htmlspecialchars($fila['tipo_documento']); ?></td>
                  <td><?php echo htmlspecialchars($fila['numero_documento']); ?></td>
                  <td><?php echo htmlspecialchars($fila['fecha_registro']); ?></td>
                  <td>
                    <span class="chip-estado chip-<?php echo $fila['estado_registro'] === 'Completado' ? 'completado' : 'pendiente'; ?>">
                      <?php echo htmlspecialchars($fila['estado_registro']); ?>
                    </span>
                  </td>
                  <td class="tabla-acciones">
                    <!-- Ver -->
                    <a href="ver.php?id=<?php echo $fila['id']; ?>" class="icon-button" title="Ver detalle">
                      <svg viewBox="0 0 24 24" class="icon-svg">
                        <path d="M2 12s3-6 10-6 10 6 10 6-3 6-10 6S2 12 2 12z" fill="none"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                      </svg>
                    </a>

                    <!-- Editar -->
                    <a href="editar.php?id=<?php echo $fila['id']; ?>" class="icon-button" title="Editar">
                      <svg viewBox="0 0 24 24" class="icon-svg">
                        <path d="M4 20h4l10-10-4-4L4 16v4z" fill="none"></path>
                      </svg>
                    </a>

                    <!-- Eliminar -->
                    <form method="post" action="" class="inline-form"
                          onsubmit="return confirm('¿Seguro que deseas eliminar este registro?');">
                      <input type="hidden" name="accion" value="eliminar">
                      <input type="hidden" name="id" value="<?php echo $fila['id']; ?>">
                      <button type="submit" class="icon-button icon-button-danger" title="Eliminar">
                        <svg viewBox="0 0 24 24" class="icon-svg">
                          <polyline points="3 6 5 6 21 6" fill="none"></polyline>
                          <path d="M8 6V4h8v2" fill="none"></path>
                          <path d="M19 6l-1 14H6L5 6" fill="none"></path>
                          <line x1="10" y1="10" x2="10" y2="17"></line>
                          <line x1="14" y1="10" x2="14" y2="17"></line>
                        </svg>
                      </button>
                    </form>

                    <!-- PDF -->
                    <a href="exportar_pdf.php?id=<?php echo $fila['id']; ?>" class="icon-button"
                       title="Exportar a PDF" target="_blank">
                      <svg viewBox="0 0 24 24" class="icon-svg">
                        <path d="M12 3v12" fill="none"></path>
                        <polyline points="8 11 12 15 16 11" fill="none"></polyline>
                        <rect x="4" y="15" width="16" height="4" rx="2"></rect>
                      </svg>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="paginacion">
            <?php if ($pagina > 1): ?>
              <a href="?<?php
                $qs = $_GET;
                $qs['page'] = $pagina - 1;
                echo htmlspecialchars(http_build_query($qs));
              ?>">&laquo; Anterior</a>
            <?php endif; ?>

            <span>Página <?php echo $pagina; ?> de <?php echo $total_paginas; ?></span>

            <?php if ($pagina < $total_paginas): ?>
              <a href="?<?php
                $qs = $_GET;
                $qs['page'] = $pagina + 1;
                echo htmlspecialchars(http_build_query($qs));
              ?>">Siguiente &raquo;</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

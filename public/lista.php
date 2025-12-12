<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

/**
 * Resolver texto de búsqueda desde GET/POST y nombres search / q
 * (si uno viene vacío y el otro no, usamos el que tenga texto).
 */
$raw_search = '';
if (isset($_REQUEST['search']) && trim($_REQUEST['search']) !== '') {
    $raw_search = $_REQUEST['search'];
} elseif (isset($_REQUEST['q']) && trim($_REQUEST['q']) !== '') {
    $raw_search = $_REQUEST['q'];
}
$search = trim($raw_search);

$por_pagina = 20;
$pagina     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset     = ($pagina - 1) * $por_pagina;

// --- Cargar filtros configurados ---
$stmtFiltros = $pdo->query("
    SELECT *
    FROM campos_filtros_lista
    WHERE activo = 1
    ORDER BY orden, id
");
$filtros_config = $stmtFiltros->fetchAll(PDO::FETCH_ASSOC);

// Valores actuales de filtros (GET)
$valores_filtros = [];
foreach ($filtros_config as $filtro) {
    $key = 'f' . $filtro['id']; // ej: f1, f2...
    $valores_filtros[$filtro['id']] = trim($_GET[$key] ?? '');
}

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

    $qs = $_GET;
    unset($qs['page']);
    $query_string = http_build_query($qs);
    header('Location: lista.php' . ($query_string ? '?' . $query_string : ''));
    exit;
}

// --- Helper: opciones para filtros tipo select ---
function obtenerOpcionesFiltro(PDO $pdo, string $campo): array {
    // Campos dinámicos: vienen como extra_ID
    if (strpos($campo, 'extra_') === 0) {
        $campoId = (int)substr($campo, 6);
        if ($campoId <= 0) {
            return [];
        }
        $stmt = $pdo->prepare("
            SELECT DISTINCT valor
            FROM personas_campos_extra
            WHERE campo_id = ?
              AND valor IS NOT NULL
              AND valor <> ''
            ORDER BY valor
        ");
        $stmt->execute([$campoId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    switch ($campo) {
        case 'estado_registro':
            return ['Pendiente', 'Completado'];

        case 'tipo_documento':
            return [
                'Registro Civil',
                'Tarjeta de Identidad',
                'Cédula de Ciudadanía',
                'Cédula de Extranjería',
                'NIT'
            ];

        case 'afiliado':
        case 'zona':
        case 'genero':
        case 'cargo':
            $stmt = $pdo->prepare("
                SELECT valor
                FROM opciones_select
                WHERE grupo = ? AND activo = 1
                ORDER BY valor
            ");
            $stmt->execute([$campo]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);

        default:
            $permitidos = [
                'nombre_predio',
                'telefono',
                'correo_electronico',
                'nombres',
                'apellidos',
                'numero_documento'
            ];
            if (!in_array($campo, $permitidos, true)) {
                return [];
            }

            $sql = "SELECT DISTINCT $campo AS valor FROM personas WHERE $campo IS NOT NULL AND $campo <> '' ORDER BY $campo";
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// --- Construcción de filtros para el WHERE ---
$where  = [];
$params = [];

// Buscador general
if ($search !== '') {
    $where[] = "(nombres LIKE ? OR apellidos LIKE ? OR numero_documento LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Campos que permitimos filtrar desde config (para evitar inyección)
$campos_permitidos = [
    'estado_registro',
    'tipo_documento',
    'afiliado',
    'zona',
    'genero',
    'cargo',
    'nombre_predio',
    'telefono',
    'correo_electronico',
    'nombres',
    'apellidos',
    'numero_documento'
];

// Filtros dinámicos (configurables)
foreach ($filtros_config as $filtro) {
    $columna = $filtro['nombre_campo'];
    $valor   = $valores_filtros[$filtro['id']] ?? '';
    if ($valor === '') {
        continue;
    }

    $tipo = $filtro['tipo_control'] ?? 'select';

    // Campo dinámico: nombre_campo = extra_ID
    if (strpos($columna, 'extra_') === 0) {
        $campoId = (int)substr($columna, 6);
        if ($campoId <= 0) {
            continue;
        }

        if ($tipo === 'texto') {
            $where[] = "EXISTS (
                SELECT 1
                FROM personas_campos_extra pce
                WHERE pce.persona_id = personas.id
                  AND pce.campo_id = ?
                  AND pce.valor LIKE ?
            )";
            $params[] = $campoId;
            $params[] = '%' . $valor . '%';
        } else {
            $where[] = "EXISTS (
                SELECT 1
                FROM personas_campos_extra pce
                WHERE pce.persona_id = personas.id
                  AND pce.campo_id = ?
                  AND pce.valor = ?
            )";
            $params[] = $campoId;
            $params[] = $valor;
        }
        continue;
    }

    // Campos normales (base)
    if (!in_array($columna, $campos_permitidos, true)) {
        continue;
    }

    if ($tipo === 'texto') {
        $where[]  = "$columna LIKE ?";
        $params[] = '%' . $valor . '%';
    } else {
        $where[]  = "$columna = ?";
        $params[] = $valor;
    }
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
$registros = $stmt_lista->fetchAll(PDO::FETCH_ASSOC);
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
          <!-- Buscador general -->
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
          </div>

          <!-- Filtros dinámicos -->
          <?php if (!empty($filtros_config)): ?>
            <div class="form-row form-row-filtros-dinamicos">
              <?php foreach ($filtros_config as $filtro): ?>
                <?php
                  $fid     = (int)$filtro['id'];
                  $col     = $filtro['nombre_campo'];
                  $etiqueta= $filtro['etiqueta'];
                  $tipo    = $filtro['tipo_control'] ?? 'select';
                  $name    = 'f' . $fid;
                  $valor   = $valores_filtros[$fid] ?? '';
                ?>

                <div class="form-group">
                  <label for="<?php echo htmlspecialchars($name); ?>">
                    <?php echo htmlspecialchars($etiqueta); ?>
                  </label>

                  <?php if ($tipo === 'texto'): ?>
                    <input
                      type="text"
                      id="<?php echo htmlspecialchars($name); ?>"
                      name="<?php echo htmlspecialchars($name); ?>"
                      value="<?php echo htmlspecialchars($valor); ?>"
                    >
                  <?php else: ?>
                    <?php
                      $opciones = obtenerOpcionesFiltro($pdo, $col);
                    ?>
                    <select
                      id="<?php echo htmlspecialchars($name); ?>"
                      name="<?php echo htmlspecialchars($name); ?>"
                    >
                      <option value="">Todos</option>
                      <?php foreach ($opciones as $op): ?>
                        <option
                          value="<?php echo htmlspecialchars($op); ?>"
                          <?php echo ($valor === $op) ? 'selected' : ''; ?>
                        >
                          <?php echo htmlspecialchars($op); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

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

                    <!-- Eliminar (con modal bonito) -->
                    <form method="post" action="" class="inline-form form-confirm"
                          data-confirm="¿Seguro que deseas eliminar este registro? Esta acción no se puede deshacer.">
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
                    <a href="exportar.php" class="icon-button"
                       title="Ir a exportar PDF">
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

  <!-- Modal de confirmación para eliminar registros -->
  <div class="modal-overlay" id="modalConfirmLista">
    <div class="modal-box">
      <h2>Confirmar eliminación</h2>
      <p id="modalConfirmListaMensaje">¿Seguro que deseas eliminar este registro?</p>
      <div class="modal-actions">
        <button type="button" class="btn-muted" id="modalConfirmListaCancelar">Cancelar</button>
        <button type="button" class="btn-primary" id="modalConfirmListaAceptar">Sí, eliminar</button>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var modal = document.getElementById('modalConfirmLista');
      var msgEl = document.getElementById('modalConfirmListaMensaje');
      var btnCancelar = document.getElementById('modalConfirmListaCancelar');
      var btnAceptar = document.getElementById('modalConfirmListaAceptar');
      var formPendiente = null;

      function abrirModal(form, mensaje) {
        formPendiente = form;
        msgEl.textContent = mensaje || '¿Seguro que deseas eliminar este registro?';
        modal.classList.add('is-open');
      }

      function cerrarModal() {
        modal.classList.remove('is-open');
        formPendiente = null;
      }

      document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
          if (form.getAttribute('data-confirm-ok') === '1') {
            return;
          }
          e.preventDefault();
          var mensaje = form.getAttribute('data-confirm') || '¿Seguro que deseas eliminar este registro?';
          abrirModal(form, mensaje);
        });
      });

      btnCancelar.addEventListener('click', function () {
        cerrarModal();
      });

      btnAceptar.addEventListener('click', function () {
        if (formPendiente) {
          formPendiente.setAttribute('data-confirm-ok', '1');
          formPendiente.submit();
        }
        cerrarModal();
      });

      modal.addEventListener('click', function (e) {
        if (e.target === modal) {
          cerrarModal();
        }
      });
    });
  </script>

  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

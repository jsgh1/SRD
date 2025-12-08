<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

$mensaje_error = '';
$resultado = null;

/**
 * Helper para mostrar valores seguros
 */
function v_consulta($valor) {
    return ($valor !== null && $valor !== '') ? htmlspecialchars($valor) : '—';
}

/**
 * Campos configurados para mostrarse en CONSULTAR
 * (tabla campos_registro, columna mostrar_en_consultar)
 */
function obtenerCamposConsulta(PDO $pdo) {
    $sql = "
        SELECT nombre_campo, etiqueta
        FROM campos_registro
        WHERE mostrar_en_consultar = 1
        ORDER BY orden ASC, id ASC
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$campos_consulta = obtenerCamposConsulta($pdo);

// Estos los usaremos más adelante solo si hay resultado
$campos_extra_consulta = [];
$valores_campos_extra  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_doc = trim($_POST['tipo_documento'] ?? '');
    $numero   = trim($_POST['numero_documento'] ?? '');

    if ($tipo_doc === '' || $numero === '') {
        $mensaje_error = 'Debes seleccionar el tipo de documento e ingresar el número.';
    } else {
        $stmt = $pdo->prepare("
            SELECT *
            FROM personas
            WHERE tipo_documento = ? AND numero_documento = ?
            LIMIT 1
        ");
        $stmt->execute([$tipo_doc, $numero]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resultado) {
            $mensaje_error = 'No se encontraron registros con esos datos.';
        } else {
            // --- Cargar CAMPOS EXTRA activos para consulta ---
            $stmtCE = $pdo->query("
                SELECT id, nombre_label
                FROM campos_extra_registro
                WHERE activo = 1
                ORDER BY grupo, orden, id
            ");
            $campos_extra_consulta = $stmtCE->fetchAll(PDO::FETCH_ASSOC);

            // --- Valores de campos extra para la persona encontrada ---
            $stmtVals = $pdo->prepare("
                SELECT campo_id, valor
                FROM personas_campos_extra
                WHERE persona_id = ?
            ");
            $stmtVals->execute([$resultado['id']]);
            $valores_campos_extra = $stmtVals->fetchAll(PDO::FETCH_KEY_PAIR);
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
                <option value="Registro Civil"       <?php echo (($_POST['tipo_documento'] ?? '') === 'Registro Civil')       ? 'selected' : ''; ?>>Registro Civil</option>
                <option value="Tarjeta de Identidad" <?php echo (($_POST['tipo_documento'] ?? '') === 'Tarjeta de Identidad') ? 'selected' : ''; ?>>Tarjeta de Identidad</option>
                <option value="Cédula de Ciudadanía" <?php echo (($_POST['tipo_documento'] ?? '') === 'Cédula de Ciudadanía') ? 'selected' : ''; ?>>Cédula de Ciudadanía</option>
                <option value="Cédula de Extranjería"<?php echo (($_POST['tipo_documento'] ?? '') === 'Cédula de Extranjería')? 'selected' : ''; ?>>Cédula de Extranjería</option>
                <option value="NIT"                  <?php echo (($_POST['tipo_documento'] ?? '') === 'NIT')                  ? 'selected' : ''; ?>>NIT</option>
              </select>
            </div>
            <div class="form-group">
              <label for="numero_documento">Número de documento</label>
              <input
                type="text"
                id="numero_documento"
                name="numero_documento"
                required
                value="<?php echo htmlspecialchars($_POST['numero_documento'] ?? ''); ?>"
              >
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
              <h2><?php echo v_consulta(($resultado['nombres'] ?? '') . ' ' . ($resultado['apellidos'] ?? '')); ?></h2>
              <p>
                <?php echo v_consulta(($resultado['tipo_documento'] ?? '')); ?>
                <?php if (!empty($resultado['numero_documento'])): ?>
                  &nbsp;<?php echo v_consulta($resultado['numero_documento']); ?>
                <?php endif; ?>
              </p>
            </div>
          </div>

          <?php
            // ---- Armamos la lista de campos a mostrar (config + extra dinámicos) ----
            $items_mostrar = [];

            // 1) Campos definidos en campos_registro (pero SIN repetir estado_registro)
            if (!empty($campos_consulta)) {
                foreach ($campos_consulta as $campo) {
                    $nombre_campo = $campo['nombre_campo'];

                    // Evitar duplicar "Estado registro" (lo mostramos más abajo fijo)
                    if ($nombre_campo === 'estado_registro') {
                        continue;
                    }

                    $etiqueta = $campo['etiqueta'];
                    $valor    = $resultado[$nombre_campo] ?? null;

                    $items_mostrar[] = [
                        'etiqueta' => $etiqueta,
                        'valor'    => $valor,
                    ];
                }
            }

            // 2) Campos EXTRA (campos_extra_registro + personas_campos_extra)
            if (!empty($campos_extra_consulta)) {
                foreach ($campos_extra_consulta as $ce) {
                    $cid   = $ce['id'];
                    $label = $ce['nombre_label'];
                    $valor_extra = $valores_campos_extra[$cid] ?? null;

                    $items_mostrar[] = [
                        'etiqueta' => $label,
                        'valor'    => $valor_extra,
                    ];
                }
            }
          ?>

          <?php if (!empty($items_mostrar)): ?>
            <?php
              $total_campos = count($items_mostrar);
              $mitad = (int)ceil($total_campos / 2);
              $col1 = array_slice($items_mostrar, 0,   $mitad);
              $col2 = array_slice($items_mostrar, $mitad);
            ?>
            <div class="consulta-detalle-grid">
              <div>
                <?php foreach ($col1 as $item): ?>
                  <p>
                    <strong><?php echo htmlspecialchars($item['etiqueta']); ?>:</strong>
                    <?php echo v_consulta($item['valor']); ?>
                  </p>
                <?php endforeach; ?>
              </div>
              <div>
                <?php foreach ($col2 as $item): ?>
                  <p>
                    <strong><?php echo htmlspecialchars($item['etiqueta']); ?>:</strong>
                    <?php echo v_consulta($item['valor']); ?>
                  </p>
                <?php endforeach; ?>
              </div>
            </div>
          <?php else: ?>
            <p style="margin-top:8px;">
              No hay campos configurados para mostrar en esta consulta
              ni campos dinámicos activos. Configúralos en la sección
              de campos de registro en <strong>Configuración</strong>.
            </p>
          <?php endif; ?>

          <p style="margin-top:8px;">
            <strong>Estado registro:</strong>
            <?php echo v_consulta($resultado['estado_registro'] ?? null); ?>
          </p>
        </section>
      <?php endif; ?>
    </main>

  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

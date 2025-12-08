<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

$errores = [];
$exito   = '';

/**
 * Cargar opciones activas de un grupo (afiliado, zona, genero, cargo)
 */
function cargarOpciones($pdo, $grupo) {
    $stmt = $pdo->prepare("SELECT valor FROM opciones_select WHERE grupo = ? AND activo = 1 ORDER BY valor");
    $stmt->execute([$grupo]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Sube una imagen para persona/documento/predio y devuelve la ruta para BD: /uploads/{subcarpeta}/archivo.jpg
 */
function subirImagenPersona($campo, $subcarpeta, &$errores) {
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        // No se subió nada para este campo
        return null;
    }

    $file = $_FILES[$campo];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "Error al subir el archivo en $campo.";
        return null;
    }

    $tmp_name = $file['tmp_name'];
    $nombre   = basename($file['name']);
    $ext      = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    $permitidas = ['jpg','jpeg','png'];

    if (!in_array($ext, $permitidas, true)) {
        $errores[] = "Formato de imagen no permitido en $campo. Solo JPG o PNG.";
        return null;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        $errores[] = "La imagen en $campo supera los 5MB.";
        return null;
    }

    // Carpeta física: {raíz del proyecto}/uploads/{subcarpeta}
    $baseDir = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
    $carpetaFisica = $baseDir . '/uploads/' . $subcarpeta;

    if (!is_dir($carpetaFisica)) {
        @mkdir($carpetaFisica, 0777, true);
    }

    $nuevo_nombre = uniqid($campo . '_') . '.' . $ext;
    $destinoFisico = $carpetaFisica . '/' . $nuevo_nombre;

    if (!move_uploaded_file($tmp_name, $destinoFisico)) {
        $errores[] = "No se pudo guardar la imagen en $campo.";
        return null;
    }

    // Lo que se guarda en BD (relativo a la raíz del proyecto)
    return '/uploads/' . $subcarpeta . '/' . $nuevo_nombre;
}

// Cargar opciones de selects
$op_afiliado = cargarOpciones($pdo, 'afiliado');
$op_zona     = cargarOpciones($pdo, 'zona');
$op_genero   = cargarOpciones($pdo, 'genero');
$op_cargo    = cargarOpciones($pdo, 'cargo');

// Cargar campos extra del registro
$stmtCampos = $pdo->query("SELECT * FROM campos_extra_registro WHERE activo = 1 ORDER BY grupo, orden, id");
$campos_extra = $stmtCampos->fetchAll(PDO::FETCH_ASSOC);
$extras_valores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tipo_documento   = trim($_POST['tipo_documento'] ?? '');
    $numero_documento = trim($_POST['numero_documento'] ?? '');
    $nombres          = trim($_POST['nombres'] ?? '');
    $apellidos        = trim($_POST['apellidos'] ?? '');
    $afiliado         = trim($_POST['afiliado'] ?? '');
    $zona             = trim($_POST['zona'] ?? '');
    $genero           = trim($_POST['genero'] ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $telefono         = trim($_POST['telefono'] ?? '');
    $cargo            = trim($_POST['cargo'] ?? '');
    $nombre_predio    = trim($_POST['nombre_predio'] ?? '');
    $correo_elec      = trim($_POST['correo_electronico'] ?? '');
    $estado_registro  = trim($_POST['estado_registro'] ?? 'Pendiente');
    $nota_admin       = trim($_POST['nota_admin'] ?? '');

    // Valores de campos extra
    $extras_valores = $_POST['extra'] ?? [];

    // Validaciones cuando el registro se marca como "Completado"
    if ($estado_registro === 'Completado') {
        if ($tipo_documento === '')   $errores[] = 'El tipo de documento es obligatorio.';
        if ($numero_documento === '') $errores[] = 'El número de documento es obligatorio.';
        if ($nombres === '')          $errores[] = 'Los nombres son obligatorios.';
        if ($apellidos === '')        $errores[] = 'Los apellidos son obligatorios.';
        if ($afiliado === '')         $errores[] = 'El campo afiliado es obligatorio.';
        if ($zona === '')             $errores[] = 'La zona es obligatoria.';
        if ($genero === '')           $errores[] = 'El género es obligatorio.';
        if ($fecha_nacimiento === '') $errores[] = 'La fecha de nacimiento es obligatoria.';
        if ($telefono === '')         $errores[] = 'El teléfono es obligatorio.';
        if ($cargo === '')            $errores[] = 'El cargo es obligatorio.';
        if ($nombre_predio === '')    $errores[] = 'El nombre del predio es obligatorio.';
        if ($correo_elec === '')      $errores[] = 'El correo electrónico es obligatorio.';

        // Campos extra requeridos
        foreach ($campos_extra as $campo) {
            if (!empty($campo['requerido'])) {
                $valorExtra = trim($extras_valores[$campo['id']] ?? '');
                if ($valorExtra === '') {
                    $errores[] = 'El campo "' . $campo['nombre_label'] . '" es obligatorio.';
                }
            }
        }
    }

    if ($correo_elec !== '' && !filter_var($correo_elec, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no tiene un formato válido.';
    }

    $ruta_foto_persona   = null;
    $ruta_foto_documento = null;
    $ruta_foto_predio    = null;

    // Subir imágenes solo si no hay errores de validación de datos
    if (empty($errores)) {
        $ruta_foto_persona   = subirImagenPersona('foto_persona',   'personas',   $errores);
        $ruta_foto_documento = subirImagenPersona('foto_documento', 'documentos', $errores);
        $ruta_foto_predio    = subirImagenPersona('foto_predio',    'predios',    $errores);
    }

    if (empty($errores)) {
        $stmt = $pdo->prepare("
            INSERT INTO personas (
                tipo_documento, numero_documento, nombres, apellidos,
                afiliado, zona, genero, fecha_nacimiento, telefono,
                cargo, nombre_predio, correo_electronico,
                foto_persona, foto_documento, foto_predio,
                estado_registro, nota_admin
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $tipo_documento,
            $numero_documento,
            $nombres,
            $apellidos,
            $afiliado,
            $zona,
            $genero,
            $fecha_nacimiento ?: null,
            $telefono,
            $cargo,
            $nombre_predio,
            $correo_elec,
            $ruta_foto_persona,
            $ruta_foto_documento,
            $ruta_foto_predio,
            $estado_registro,
            $nota_admin
        ]);

        $persona_id = (int)$pdo->lastInsertId();

        // Guardar campos extra
        if ($persona_id > 0 && !empty($campos_extra)) {
            $insExtra = $pdo->prepare("
                INSERT INTO personas_campos_extra (persona_id, campo_id, valor)
                VALUES (?, ?, ?)
            ");

            foreach ($campos_extra as $campo) {
                $cid = (int)$campo['id'];
                $valorExtra = isset($extras_valores[$cid]) ? trim($extras_valores[$cid]) : '';

                if ($valorExtra === '' && empty($campo['requerido'])) {
                    continue;
                }

                $insExtra->execute([$persona_id, $cid, $valorExtra]);
            }
        }

        $exito = 'Registro guardado correctamente.';

        // Limpiar todos los campos del formulario
        $tipo_documento   = '';
        $numero_documento = '';
        $nombres          = '';
        $apellidos        = '';
        $afiliado         = '';
        $zona             = '';
        $genero           = '';
        $fecha_nacimiento = '';
        $telefono         = '';
        $cargo            = '';
        $nombre_predio    = '';
        $correo_elec      = '';
        $estado_registro  = 'Pendiente';
        $nota_admin       = '';
        $extras_valores   = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registro de persona - Sistema de Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="<?php echo $body_class; ?>">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <div class="layout-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content">
      <h1>Nuevo registro</h1>

      <?php if (!empty($errores)): ?>
        <div class="alert alert-error">
          <ul>
            <?php foreach ($errores as $err): ?>
              <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($exito)): ?>
        <div class="alert">
          <?php echo htmlspecialchars($exito); ?>
        </div>
      <?php endif; ?>

      <section class="form-card">
        <form
          id="form-registro"
          class="show-loader-on-submit"
          method="post"
          action=""
          enctype="multipart/form-data"
        >

          <!-- Sección: Datos de la persona -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon">
                <svg viewBox="0 0 24 24" class="icon-svg">
                  <circle cx="12" cy="8" r="4"></circle>
                  <path d="M5 20c0-3.3 3-6 7-6s7 2.7 7 6" fill="none"></path>
                </svg>
              </div>
              <div>
                <h2>Datos de la persona</h2>
                <p>Información básica de identificación.</p>
              </div>
            </div>

            <div class="form-grid-2">
              <div class="form-group">
                <label for="tipo_documento">Tipo de documento</label>
                <select name="tipo_documento" id="tipo_documento">
                  <option value="">Seleccione...</option>
                  <option value="Registro Civil"        <?php echo ($tipo_documento ?? '') === 'Registro Civil' ? 'selected' : ''; ?>>Registro Civil</option>
                  <option value="Tarjeta de Identidad"  <?php echo ($tipo_documento ?? '') === 'Tarjeta de Identidad' ? 'selected' : ''; ?>>Tarjeta de Identidad</option>
                  <option value="Cédula de Ciudadanía"  <?php echo ($tipo_documento ?? '') === 'Cédula de Ciudadanía' ? 'selected' : ''; ?>>Cédula de Ciudadanía</option>
                  <option value="Cédula de Extranjería" <?php echo ($tipo_documento ?? '') === 'Cédula de Extranjería' ? 'selected' : ''; ?>>Cédula de Extranjería</option>
                  <option value="NIT"                   <?php echo ($tipo_documento ?? '') === 'NIT' ? 'selected' : ''; ?>>NIT</option>
                </select>
              </div>

              <div class="form-group">
                <label for="numero_documento">Número de documento</label>
                <input
                  type="text"
                  id="numero_documento"
                  name="numero_documento"
                  value="<?php echo htmlspecialchars($numero_documento ?? ''); ?>"
                >
              </div>

              <div class="form-group">
                <label for="nombres">Nombres</label>
                <input
                  type="text"
                  id="nombres"
                  name="nombres"
                  value="<?php echo htmlspecialchars($nombres ?? ''); ?>"
                >
              </div>

              <div class="form-group">
                <label for="apellidos">Apellidos</label>
                <input
                  type="text"
                  id="apellidos"
                  name="apellidos"
                  value="<?php echo htmlspecialchars($apellidos ?? ''); ?>"
                >
              </div>

              <!-- Campos extra del grupo "persona" -->
              <?php foreach ($campos_extra as $campo): ?>
                <?php if ($campo['grupo'] === 'persona'): ?>
                  <?php
                    $cid = $campo['id'];
                    $idHtml = 'extra_' . $cid;
                    $valorExtra = htmlspecialchars($extras_valores[$cid] ?? '');
                  ?>
                  <div class="form-group">
                    <label for="<?php echo $idHtml; ?>">
                      <?php echo htmlspecialchars($campo['nombre_label']); ?>
                      <?php if (!empty($campo['requerido'])): ?>
                        <span class="campo-requerido">*</span>
                      <?php endif; ?>
                    </label>

                    <?php if ($campo['tipo'] === 'textarea'): ?>
                      <textarea
                        id="<?php echo $idHtml; ?>"
                        name="extra[<?php echo $cid; ?>]"
                        rows="2"
                      ><?php echo $valorExtra; ?></textarea>
                    <?php elseif ($campo['tipo'] === 'numero'): ?>
                      <input
                        type="number"
                        id="<?php echo $idHtml; ?>"
                        name="extra[<?php echo $cid; ?>]"
                        value="<?php echo $valorExtra; ?>"
                      >
                    <?php elseif ($campo['tipo'] === 'fecha'): ?>
                      <input
                        type="date"
                        id="<?php echo $idHtml; ?>"
                        name="extra[<?php echo $cid; ?>]"
                        value="<?php echo $valorExtra; ?>"
                      >
                    <?php else: ?>
                      <input
                        type="text"
                        id="<?php echo $idHtml; ?>"
                        name="extra[<?php echo $cid; ?>]"
                        value="<?php echo $valorExtra; ?>"
                      >
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Sección: Contacto y ubicación -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon">
                <svg viewBox="0 0 24 24" class="icon-svg">
                  <path d="M12 3a7 7 0 0 1 7 7c0 4.2-3.5 7.5-7 11-3.5-3.5-7-6.8-7-11a7 7 0 0 1 7-7z" fill="none"></path>
                  <circle cx="12" cy="10" r="2.5"></circle>
                </svg>
              </div>
              <div>
                <h2>Contacto y ubicación</h2>
                <p>Datos de afiliación y zona.</p>
              </div>
            </div>

            <div class="form-grid-2">
              <div class="form-group">
                <label for="afiliado">Afiliado</label>
                <select name="afiliado" id="afiliado">
                  <option value="">Seleccione...</option>
                  <?php foreach ($op_afiliado as $op): ?>
                    <option value="<?php echo htmlspecialchars($op); ?>" <?php echo ($afiliado ?? '') === $op ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($op); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="zona">Zona</label>
                <select name="zona" id="zona">
                  <option value="">Seleccione...</option>
                  <?php foreach ($op_zona as $op): ?>
                    <option value="<?php echo htmlspecialchars($op); ?>" <?php echo ($zona ?? '') === $op ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($op); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="genero">Género</label>
                <select name="genero" id="genero">
                  <option value="">Seleccione...</option>
                  <?php foreach ($op_genero as $op): ?>
                    <option value="<?php echo htmlspecialchars($op); ?>" <?php echo ($genero ?? '') === $op ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($op); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="fecha_nacimiento">Fecha de nacimiento</label>
                <input
                  type="date"
                  id="fecha_nacimiento"
                  name="fecha_nacimiento"
                  value="<?php echo htmlspecialchars($fecha_nacimiento ?? ''); ?>"
                >
              </div>

              <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input
                  type="text"
                  id="telefono"
                  name="telefono"
                  value="<?php echo htmlspecialchars($telefono ?? ''); ?>"
                >
              </div>

              <!-- Campos extra del grupo "contacto" -->
              <?php foreach ($campos_extra as $campo): ?>
                <?php if ($campo['grupo'] === 'contacto'): ?>
                  <?php
                    $cid = $campo['id'];
                    $idHtml = 'extra_' . $cid;
                    $valorExtra = htmlspecialchars($extras_valores[$cid] ?? '');
                  ?>
                  <div class="form-group">
                    <label for="<?php echo $idHtml; ?>">
                      <?php echo htmlspecialchars($campo['nombre_label']); ?>
                      <?php if (!empty($campo['requerido'])): ?>
                        <span class="campo-requerido">*</span>
                      <?php endif; ?>
                    </label>

                    <?php if ($campo['tipo'] === 'textarea'): ?>
                      <textarea
                        id="<?php echo $idHtml; ?>"
                        name="extra[<?php echo $cid; ?>]"
                        rows="2"
                      ><?php echo $valorExtra; ?></textarea>
                    <?php elseif ($campo['tipo'] === 'numero'): ?>
                      <input
                        type="number"
                        id="<?php echo $idHtml; ?>"
                        name="extra[<?php echo $cid; ?>]"
                        value="<?php echo $valorExtra; ?>"
                      >
                    <?php elseif ($campo['tipo'] === 'fecha'): ?>
                      <input
                        type="date"
                        id="<?php echo $idHtml; ?>"
                        name="extra[<?php echo $cid; ?>]"
                        value="<?php echo $valorExtra; ?>"
                      >
                    <?php else: ?>
                      <input
                        type="text"
                        id="<?php echo $idHtml; ?>"
                        name="extra[<?php echo $cid; ?>]"
                        value="<?php echo $valorExtra; ?>"
                      >
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Sección: Información del predio -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon">
                <svg viewBox="0 0 24 24" class="icon-svg">
                  <path d="M3 11l9-7 9 7" fill="none"></path>
                  <path d="M5 10v10h14V10" fill="none"></path>
                </svg>
              </div>
              <div>
                <h2>Información del predio</h2>
                <p>Datos del predio asociado a la persona.</p>
              </div>
            </div>

            <div class="form-grid-2">
              <div class="form-group">
                <label for="cargo">Cargo</label>
                <select name="cargo" id="cargo">
                  <option value="">Seleccione...</option>
                  <?php foreach ($op_cargo as $op): ?>
                    <option value="<?php echo htmlspecialchars($op); ?>" <?php echo ($cargo ?? '') === $op ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($op); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group">
                <label for="nombre_predio">Nombre del predio</label>
                <input
                  type="text"
                  id="nombre_predio"
                  name="nombre_predio"
                  value="<?php echo htmlspecialchars($nombre_predio ?? ''); ?>"
                >
              </div>

              <div class="form-group">
                <label for="correo_electronico">Correo electrónico</label>
                <input
                  type="email"
                  id="correo_electronico"
                  name="correo_electronico"
                  value="<?php echo htmlspecialchars($correo_elec ?? ''); ?>"
                >
              </div>

              <!-- Campos extra del grupo "predio" -->
              <?php foreach ($campos_extra as $campo): ?>
                <?php if ($campo['grupo'] === 'predio'): ?>
                  <?php
                    $cid = $campo['id'];
                    $idHtml = 'extra_' . $cid;
                    $valorExtra = htmlspecialchars($extras_valores[$cid] ?? '');
                  ?>
                  <div class="form-group">
                    <label for="<?php echo $idHtml; ?>">
                      <?php echo htmlspecialchars($campo['nombre_label']); ?>
                      <?php if (!empty($campo['requerido'])): ?>
                        <span class="campo-requerido">*</span>
                      <?php endif; ?>
                    </label>

                    <?php if ($campo['tipo'] === 'textarea'): ?>
                      <textarea
                        id="<?php echo $idHtml; ?>"
                        name="extra[<?php echo $cid; ?>]"
                        rows="2"
                      ><?php echo $valorExtra; ?></textarea>
                    <?php elseif ($campo['tipo'] === 'numero'): ?>
                      <input
                        type="number"
                        id="<?php echo $idHtml; ?>"
                        name="extra[<?php echo $cid; ?>]"
                        value="<?php echo $valorExtra; ?>"
                      >
                    <?php elseif ($campo['tipo'] === 'fecha'): ?>
                      <input
                        type="date"
                        id="<?php echo $idHtml; ?>"
                        name="extra[<?php echo $cid; ?>]"
                        value="<?php echo $valorExtra; ?>"
                      >
                    <?php else: ?>
                      <input
                        type="text"
                        id="<?php echo $idHtml; ?>"
                        name="extra[<?php echo $cid; ?>]"
                        value="<?php echo $valorExtra; ?>"
                      >
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Sección: Fotos -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon">
                <svg viewBox="0 0 24 24" class="icon-svg">
                  <rect x="4" y="5" width="16" height="14" rx="2"></rect>
                  <circle cx="9" cy="10" r="2"></circle>
                  <path d="M8 17l2.5-3 2.5 2 3-4 2 5" fill="none"></path>
                </svg>
              </div>
              <div>
                <h2>Fotos</h2>
                <p>Adjunta las fotografías requeridas.</p>
              </div>
            </div>

            <div class="form-grid-3">
              <div class="form-group">
                <label for="foto_persona">Foto de la persona</label>
                <div class="upload-box">
                  <input type="file" id="foto_persona" name="foto_persona" accept="image/*">
                  <span>Seleccionar imagen</span>
                </div>
              </div>

              <div class="form-group">
                <label for="foto_documento">Foto del documento</label>
                <div class="upload-box">
                  <input type="file" id="foto_documento" name="foto_documento" accept="image/*">
                  <span>Seleccionar imagen</span>
                </div>
              </div>

              <div class="form-group">
                <label for="foto_predio">Foto del predio</label>
                <div class="upload-box">
                  <input type="file" id="foto_predio" name="foto_predio" accept="image/*">
                  <span>Seleccionar imagen</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Sección: Datos administrativos -->
          <div class="form-section">
            <div class="form-section-header">
              <div class="form-section-icon">
                <svg viewBox="0 0 24 24" class="icon-svg">
                  <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                  <path d="M8 12h8"></path>
                  <path d="M12 8v8"></path>
                </svg>
              </div>
              <div>
                <h2>Datos administrativos</h2>
                <p>Control interno del estado del registro.</p>
              </div>
            </div>

            <div class="form-grid-2">
              <div class="form-group">
                <label for="estado_registro">Estado del registro</label>
                <select name="estado_registro" id="estado_registro">
                  <option value="Pendiente"  <?php echo ($estado_registro ?? '') === 'Pendiente'  ? 'selected' : ''; ?>>Pendiente</option>
                  <option value="Completado" <?php echo ($estado_registro ?? '') === 'Completado' ? 'selected' : ''; ?>>Completado</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label for="nota_admin">Nota (uso del administrador)</label>
              <textarea
                id="nota_admin"
                name="nota_admin"
                rows="3"
                style="width:100%;"
              ><?php echo htmlspecialchars($nota_admin ?? ''); ?></textarea>
            </div>
          </div>

          <div class="form-actions">
            <a href="registro.php" class="btn-ghost">Vaciar</a>
            <button type="submit" class="btn-primary">Guardar registro</button>
          </div>
        </form>
      </section>
    </main>

  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
  <script src="../assets/js/formularios.js"></script>
</body>
</html>

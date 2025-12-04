<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

$errores = [];
$exito   = '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: lista.php');
    exit;
}

/**
 * Cargar opciones activas de un grupo (afiliado, zona, genero, cargo)
 */
function cargarOpciones($pdo, $grupo) {
    $stmt = $pdo->prepare("SELECT valor FROM opciones_select WHERE grupo = ? AND activo = 1 ORDER BY valor");
    $stmt->execute([$grupo]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Convierte una ruta de BD en ruta pública para <img src="...">
 * - Si empieza por /uploads/... => la convertimos a ../uploads/...
 * - Si ya es relativa de otra forma, se deja tal cual.
 */
function ruta_publica_upload(?string $ruta): ?string {
    if (!$ruta) return null;
    if (strpos($ruta, '/uploads/') === 0) {
        return '..' . $ruta;
    }
    return $ruta;
}

/**
 * Sube una nueva imagen (si se envía) y devuelve la ruta para BD (/uploads/{subcarpeta}/archivo.jpg),
 * borrando la imagen anterior si existía.
 */
function subirImagenEditarGeneral($campo, $subcarpeta, &$errores, $ruta_actual) {
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        // No se subió nada nuevo, mantenemos la ruta actual
        return $ruta_actual;
    }

    $file = $_FILES[$campo];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errores[] = "Error al subir el archivo en $campo.";
        return $ruta_actual;
    }

    $tmp_name = $file['tmp_name'];
    $nombre   = basename($file['name']);
    $ext      = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    $permitidas = ['jpg','jpeg','png'];

    if (!in_array($ext, $permitidas, true)) {
        $errores[] = "Formato de imagen no permitido en $campo. Solo JPG o PNG.";
        return $ruta_actual;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        $errores[] = "La imagen en $campo supera los 5MB.";
        return $ruta_actual;
    }

    // Carpeta física: {raíz del proyecto}/uploads/{subcarpeta}
    $baseDir = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
    $carpetaFisica = $baseDir . '/uploads/' . $subcarpeta;

    if (!is_dir($carpetaFisica)) {
        @mkdir($carpetaFisica, 0777, true);
    }

    $nuevo_nombre  = uniqid($campo . '_') . '.' . $ext;
    $destinoFisico = $carpetaFisica . '/' . $nuevo_nombre;

    if (!move_uploaded_file($tmp_name, $destinoFisico)) {
        $errores[] = "No se pudo guardar la imagen en $campo.";
        return $ruta_actual;
    }

    // Borrar imagen anterior si existía
    if ($ruta_actual) {
        // Puede venir como /uploads/... o ../uploads/...
        if (strpos($ruta_actual, '/uploads/') === 0) {
            $ruta_fisica = realpath($baseDir . $ruta_actual);
        } elseif (strpos($ruta_actual, '../uploads/') === 0) {
            $ruta_fisica = realpath(__DIR__ . '/' . $ruta_actual);
        } else {
            $ruta_fisica = null;
        }

        if ($ruta_fisica && file_exists($ruta_fisica)) {
            @unlink($ruta_fisica);
        }
    }

    // Ruta nueva para BD
    return '/uploads/' . $subcarpeta . '/' . $nuevo_nombre;
}

// Opciones de selects
$op_afiliado = cargarOpciones($pdo, 'afiliado');
$op_zona     = cargarOpciones($pdo, 'zona');
$op_genero   = cargarOpciones($pdo, 'genero');
$op_cargo    = cargarOpciones($pdo, 'cargo');

// Cargar persona
$stmt = $pdo->prepare("SELECT * FROM personas WHERE id = ?");
$stmt->execute([$id]);
$persona = $stmt->fetch();

if (!$persona) {
    header('Location: lista.php');
    exit;
}

$tipo_documento   = $persona['tipo_documento'];
$numero_documento = $persona['numero_documento'];
$nombres          = $persona['nombres'];
$apellidos        = $persona['apellidos'];
$afiliado         = $persona['afiliado'];
$zona             = $persona['zona'];
$genero           = $persona['genero'];
$fecha_nacimiento = $persona['fecha_nacimiento'];
$telefono         = $persona['telefono'];
$cargo            = $persona['cargo'];
$nombre_predio    = $persona['nombre_predio'];
$correo_elec      = $persona['correo_electronico'];
$estado_registro  = $persona['estado_registro'];
$nota_admin       = $persona['nota_admin'];

$ruta_foto_persona_bd   = $persona['foto_persona'];
$ruta_foto_documento_bd = $persona['foto_documento'];
$ruta_foto_predio_bd    = $persona['foto_predio'];

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
    }

    if ($correo_elec !== '' && !filter_var($correo_elec, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no tiene un formato válido.';
    }

    // Subida de imágenes solo si no hay errores de campos
    if (empty($errores)) {
        $ruta_foto_persona_bd   = subirImagenEditarGeneral('foto_persona',   'personas',   $errores, $ruta_foto_persona_bd);
        $ruta_foto_documento_bd = subirImagenEditarGeneral('foto_documento', 'documentos', $errores, $ruta_foto_documento_bd);
        $ruta_foto_predio_bd    = subirImagenEditarGeneral('foto_predio',    'predios',    $errores, $ruta_foto_predio_bd);
    }

    if (empty($errores)) {
        $stmt_upd = $pdo->prepare("
            UPDATE personas
            SET tipo_documento = ?,
                numero_documento = ?,
                nombres = ?,
                apellidos = ?,
                afiliado = ?,
                zona = ?,
                genero = ?,
                fecha_nacimiento = ?,
                telefono = ?,
                cargo = ?,
                nombre_predio = ?,
                correo_electronico = ?,
                foto_persona = ?,
                foto_documento = ?,
                foto_predio = ?,
                estado_registro = ?,
                nota_admin = ?
            WHERE id = ?
        ");

        $stmt_upd->execute([
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
            $ruta_foto_persona_bd,
            $ruta_foto_documento_bd,
            $ruta_foto_predio_bd,
            $estado_registro,
            $nota_admin,
            $id
        ]);

        $exito = 'Registro actualizado correctamente.';

        // Refrescar datos de persona por si hay más campos calculados
        $stmt = $pdo->prepare("SELECT * FROM personas WHERE id = ?");
        $stmt->execute([$id]);
        $persona = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar persona - Sistema de Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="<?php echo $body_class; ?>">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <div class="layout-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content">
      <h1>Editar persona</h1>

      <?php if (!empty($errores)): ?>
        <div class="alert alert-error">
          <ul>
            <?php foreach ($errores as $err): ?>
              <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($exito): ?>
        <div class="alert" style="background:#e0ffe0;color:#145214;">
          <?php echo htmlspecialchars($exito); ?>
        </div>
      <?php endif; ?>

      <section class="form-card">
        <form
          id="form-editar"
          class="show-loader-on-submit"
          method="post"
          action=""
          enctype="multipart/form-data"
        >

          <!-- Datos de la persona -->
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
                  <option value="Registro Civil"        <?php echo $tipo_documento === 'Registro Civil' ? 'selected' : ''; ?>>Registro Civil</option>
                  <option value="Tarjeta de Identidad"  <?php echo $tipo_documento === 'Tarjeta de Identidad' ? 'selected' : ''; ?>>Tarjeta de Identidad</option>
                  <option value="Cédula de Ciudadanía"  <?php echo $tipo_documento === 'Cédula de Ciudadanía' ? 'selected' : ''; ?>>Cédula de Ciudadanía</option>
                  <option value="Cédula de Extranjería" <?php echo $tipo_documento === 'Cédula de Extranjería' ? 'selected' : ''; ?>>Cédula de Extranjería</option>
                  <option value="NIT"                   <?php echo $tipo_documento === 'NIT' ? 'selected' : ''; ?>>NIT</option>
                </select>
              </div>

              <div class="form-group">
                <label for="numero_documento">Número de documento</label>
                <input
                  type="text"
                  id="numero_documento"
                  name="numero_documento"
                  value="<?php echo htmlspecialchars($numero_documento); ?>"
                >
              </div>

              <div class="form-group">
                <label for="nombres">Nombres</label>
                <input
                  type="text"
                  id="nombres"
                  name="nombres"
                  value="<?php echo htmlspecialchars($nombres); ?>"
                >
              </div>

              <div class="form-group">
                <label for="apellidos">Apellidos</label>
                <input
                  type="text"
                  id="apellidos"
                  name="apellidos"
                  value="<?php echo htmlspecialchars($apellidos); ?>"
                >
              </div>
            </div>
          </div>

          <!-- Contacto y ubicación -->
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
                    <option value="<?php echo htmlspecialchars($op); ?>" <?php echo $afiliado === $op ? 'selected' : ''; ?>>
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
                    <option value="<?php echo htmlspecialchars($op); ?>" <?php echo $zona === $op ? 'selected' : ''; ?>>
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
                    <option value="<?php echo htmlspecialchars($op); ?>" <?php echo $genero === $op ? 'selected' : ''; ?>>
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
                  value="<?php echo htmlspecialchars($fecha_nacimiento); ?>"
                >
              </div>

              <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input
                  type="text"
                  id="telefono"
                  name="telefono"
                  value="<?php echo htmlspecialchars($telefono); ?>"
                >
              </div>
            </div>
          </div>

          <!-- Información del predio -->
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
                    <option value="<?php echo htmlspecialchars($op); ?>" <?php echo $cargo === $op ? 'selected' : ''; ?>>
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
                  value="<?php echo htmlspecialchars($nombre_predio); ?>"
                >
              </div>

              <div class="form-group">
                <label for="correo_electronico">Correo electrónico</label>
                <input
                  type="email"
                  id="correo_electronico"
                  name="correo_electronico"
                  value="<?php echo htmlspecialchars($correo_elec); ?>"
                >
              </div>
            </div>
          </div>

          <!-- Fotos -->
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
                <p>Visualiza y reemplaza las fotografías si es necesario.</p>
              </div>
            </div>

            <div class="form-grid-3">
              <div class="form-group">
                <label>Foto actual de la persona</label>
                <div class="foto-actual">
                  <?php $src_persona = ruta_publica_upload($ruta_foto_persona_bd); ?>
                  <?php if ($src_persona): ?>
                    <img src="<?php echo htmlspecialchars($src_persona); ?>" alt="Foto persona">
                  <?php else: ?>
                    <span class="foto-placeholder">Sin imagen</span>
                  <?php endif; ?>
                </div>
                <label for="foto_persona" class="label-secundario">Reemplazar foto</label>
                <div class="upload-box">
                  <input type="file" id="foto_persona" name="foto_persona" accept="image/*">
                  <span>Seleccionar nueva imagen</span>
                </div>
              </div>

              <div class="form-group">
                <label>Foto actual del documento</label>
                <div class="foto-actual">
                  <?php $src_doc = ruta_publica_upload($ruta_foto_documento_bd); ?>
                  <?php if ($src_doc): ?>
                    <img src="<?php echo htmlspecialchars($src_doc); ?>" alt="Foto documento">
                  <?php else: ?>
                    <span class="foto-placeholder">Sin imagen</span>
                  <?php endif; ?>
                </div>
                <label for="foto_documento" class="label-secundario">Reemplazar foto</label>
                <div class="upload-box">
                  <input type="file" id="foto_documento" name="foto_documento" accept="image/*">
                  <span>Seleccionar nueva imagen</span>
                </div>
              </div>

              <div class="form-group">
                <label>Foto actual del predio</label>
                <div class="foto-actual">
                  <?php $src_predio = ruta_publica_upload($ruta_foto_predio_bd); ?>
                  <?php if ($src_predio): ?>
                    <img src="<?php echo htmlspecialchars($src_predio); ?>" alt="Foto predio">
                  <?php else: ?>
                    <span class="foto-placeholder">Sin imagen</span>
                  <?php endif; ?>
                </div>
                <label for="foto_predio" class="label-secundario">Reemplazar foto</label>
                <div class="upload-box">
                  <input type="file" id="foto_predio" name="foto_predio" accept="image/*">
                  <span>Seleccionar nueva imagen</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Datos administrativos -->
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
                <p>Control del estado del registro.</p>
              </div>
            </div>

            <div class="form-grid-2">
              <div class="form-group">
                <label for="estado_registro">Estado del registro</label>
                <select name="estado_registro" id="estado_registro">
                  <option value="Pendiente"  <?php echo $estado_registro === 'Pendiente'  ? 'selected' : ''; ?>>Pendiente</option>
                  <option value="Completado" <?php echo $estado_registro === 'Completado' ? 'selected' : ''; ?>>Completado</option>
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
              ><?php echo htmlspecialchars($nota_admin); ?></textarea>
            </div>
          </div>

          <div class="form-actions">
            <a href="lista.php" class="btn-ghost">Volver</a>
            <button type="submit" class="btn-primary">Guardar cambios</button>
          </div>
        </form>
      </section>
    </main>

  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
  <script src="../assets/js/formularios.js"></script>
</body>
</html>

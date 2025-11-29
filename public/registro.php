<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

$errores = [];
$exito = '';

// Cargar opciones dinámicas
function cargarOpciones($pdo, $grupo) {
    $stmt = $pdo->prepare("SELECT valor FROM opciones_select WHERE grupo = ? AND activo = 1 ORDER BY valor");
    $stmt->execute([$grupo]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$op_afiliado = cargarOpciones($pdo, 'afiliado');
$op_zona     = cargarOpciones($pdo, 'zona');
$op_genero   = cargarOpciones($pdo, 'genero');
$op_cargo    = cargarOpciones($pdo, 'cargo');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Campos básicos
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

    // Validación según estado
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

    // Validación simple de correo si viene
    if ($correo_elec !== '' && !filter_var($correo_elec, FILTER_VALIDATE_EMAIL)) {
        $errores[] = 'El correo electrónico no tiene un formato válido.';
    }

    // Manejo de archivos (pueden ser opcionales si estado es Pendiente)
    $ruta_foto_persona   = null;
    $ruta_foto_documento = null;
    $ruta_foto_predio    = null;

    function subirImagen($campo, $carpeta, &$errores) {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
            return null; // no se subió archivo
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

        if (!in_array($ext, $permitidas)) {
            $errores[] = "Formato de imagen no permitido en $campo. Solo JPG o PNG.";
            return null;
        }

        // tamaño max 5MB
        if ($file['size'] > 5 * 1024 * 1024) {
            $errores[] = "La imagen en $campo supera los 5MB.";
            return null;
        }

        if (!is_dir($carpeta)) {
            @mkdir($carpeta, 0777, true);
        }

        $nuevo_nombre = uniqid($campo . '_') . '.' . $ext;
        $destino = $carpeta . '/' . $nuevo_nombre;

        if (!move_uploaded_file($tmp_name, $destino)) {
            $errores[] = "No se pudo guardar la imagen en $campo.";
            return null;
        }

        return $destino;
    }

    // Subir imágenes (solo si no hay errores graves previos)
    if (empty($errores)) {
        $ruta_foto_persona   = subirImagen('foto_persona',   __DIR__ . '/../uploads/personas',   $errores);
        $ruta_foto_documento = subirImagen('foto_documento', __DIR__ . '/../uploads/documentos', $errores);
        $ruta_foto_predio    = subirImagen('foto_predio',    __DIR__ . '/../uploads/predios',    $errores);
    }

    // Insertar en BD
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
            $ruta_foto_persona ? str_replace(__DIR__ . '/..', '', $ruta_foto_persona) : null,
            $ruta_foto_documento ? str_replace(__DIR__ . '/..', '', $ruta_foto_documento) : null,
            $ruta_foto_predio ? str_replace(__DIR__ . '/..', '', $ruta_foto_predio) : null,
            $estado_registro,
            $nota_admin
        ]);

        $exito = 'Registro guardado correctamente.';
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
      <h1>Registro de persona</h1>

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
        <form method="post" action="" enctype="multipart/form-data">
          <h2>Datos de la persona</h2>

          <div class="form-row">
            <div class="form-group">
              <label for="foto_persona">Foto de la persona</label>
              <input type="file" name="foto_persona" id="foto_persona" accept="image/*">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="tipo_documento">Tipo de documento</label>
              <select name="tipo_documento" id="tipo_documento">
                <option value="">Seleccione...</option>
                <option value="Registro Civil">Registro Civil</option>
                <option value="Tarjeta de Identidad">Tarjeta de Identidad</option>
                <option value="Cédula de Ciudadanía">Cédula de Ciudadanía</option>
                <option value="Cédula de Extranjería">Cédula de Extranjería</option>
                <option value="NIT">NIT</option>
              </select>
            </div>
            <div class="form-group">
              <label for="numero_documento">Número de documento</label>
              <input type="text" name="numero_documento" id="numero_documento">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="nombres">Nombres</label>
              <input type="text" name="nombres" id="nombres">
            </div>
            <div class="form-group">
              <label for="apellidos">Apellidos</label>
              <input type="text" name="apellidos" id="apellidos">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="afiliado">Afiliado</label>
              <select name="afiliado" id="afiliado">
                <option value="">Seleccione...</option>
                <?php foreach ($op_afiliado as $op): ?>
                  <option value="<?php echo htmlspecialchars($op); ?>"><?php echo htmlspecialchars($op); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="zona">Zona</label>
              <select name="zona" id="zona">
                <option value="">Seleccione...</option>
                <?php foreach ($op_zona as $op): ?>
                  <option value="<?php echo htmlspecialchars($op); ?>"><?php echo htmlspecialchars($op); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="genero">Género</label>
              <select name="genero" id="genero">
                <option value="">Seleccione...</option>
                <?php foreach ($op_genero as $op): ?>
                  <option value="<?php echo htmlspecialchars($op); ?>"><?php echo htmlspecialchars($op); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="fecha_nacimiento">Fecha de nacimiento</label>
              <input type="date" name="fecha_nacimiento" id="fecha_nacimiento">
            </div>
            <div class="form-group">
              <label for="telefono">Teléfono</label>
              <input type="text" name="telefono" id="telefono">
            </div>
            <div class="form-group">
              <label for="cargo">Cargo</label>
              <select name="cargo" id="cargo">
                <option value="">Seleccione...</option>
                <?php foreach ($op_cargo as $op): ?>
                  <option value="<?php echo htmlspecialchars($op); ?>"><?php echo htmlspecialchars($op); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="nombre_predio">Nombre del predio</label>
              <input type="text" name="nombre_predio" id="nombre_predio">
            </div>
            <div class="form-group">
              <label for="correo_electronico">Correo electrónico</label>
              <input type="email" name="correo_electronico" id="correo_electronico">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="foto_documento">Foto del documento</label>
              <input type="file" name="foto_documento" id="foto_documento" accept="image/*">
            </div>
            <div class="form-group">
              <label for="foto_predio">Foto del predio</label>
              <input type="file" name="foto_predio" id="foto_predio" accept="image/*">
            </div>
          </div>

          <h2>Datos administrativos</h2>

          <div class="form-row">
            <div class="form-group">
              <label for="estado_registro">Estado del registro</label>
              <select name="estado_registro" id="estado_registro">
                <option value="Pendiente">Pendiente</option>
                <option value="Completado">Completado</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="nota_admin">Nota (uso del administrador)</label>
            <textarea name="nota_admin" id="nota_admin" rows="3" style="width:100%;"></textarea>
          </div>

          <div style="display:flex; gap:10px; margin-top:10px;">
            <button type="reset" class="btn-secondary">Vaciar</button>
            <button type="submit" class="btn-primary">Guardar</button>
          </div>
        </form>
      </section>
    </main>
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

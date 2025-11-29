<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

$errores = [];
$exito = '';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: lista.php');
    exit;
}

function cargarOpciones($pdo, $grupo) {
    $stmt = $pdo->prepare("SELECT valor FROM opciones_select WHERE grupo = ? AND activo = 1 ORDER BY valor");
    $stmt->execute([$grupo]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$op_afiliado = cargarOpciones($pdo, 'afiliado');
$op_zona     = cargarOpciones($pdo, 'zona');
$op_genero   = cargarOpciones($pdo, 'genero');
$op_cargo    = cargarOpciones($pdo, 'cargo');

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

    function subirImagenEditar($campo, $carpeta, &$errores, $ruta_actual) {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {

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

        if (!in_array($ext, $permitidas)) {
            $errores[] = "Formato de imagen no permitido en $campo. Solo JPG o PNG.";
            return $ruta_actual;
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            $errores[] = "La imagen en $campo supera los 5MB.";
            return $ruta_actual;
        }

        if (!is_dir($carpeta)) {
            @mkdir($carpeta, 0777, true);
        }

        $nuevo_nombre = uniqid($campo . '_') . '.' . $ext;
        $destino = $carpeta . '/' . $nuevo_nombre;

        if (!move_uploaded_file($tmp_name, $destino)) {
            $errores[] = "No se pudo guardar la imagen en $campo.";
            return $ruta_actual;
        }

        if ($ruta_actual) {
            $ruta_fisica = __DIR__ . '/..' . $ruta_actual; 
            if (file_exists($ruta_fisica)) {
                @unlink($ruta_fisica);
            }
        }

        return str_replace(__DIR__ . '/..', '', $destino); 
    }

    if (empty($errores)) {
        $ruta_foto_persona_bd   = subirImagenEditar('foto_persona',   __DIR__ . '/../uploads/personas',   $errores, $ruta_foto_persona_bd);
        $ruta_foto_documento_bd = subirImagenEditar('foto_documento', __DIR__ . '/../uploads/documentos', $errores, $ruta_foto_documento_bd);
        $ruta_foto_predio_bd    = subirImagenEditar('foto_predio',    __DIR__ . '/../uploads/predios',    $errores, $ruta_foto_predio_bd);
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
        <form id="form-editar" class="show-loader-on-submit" method="post" action="" enctype="multipart/form-data">
          <h2>Datos de la persona</h2>

          <div class="form-row">
            <div class="form-group">
              <label>Foto actual de la persona</label><br>
              <?php if ($ruta_foto_persona_bd): ?>
                <img src="<?php echo htmlspecialchars($ruta_foto_persona_bd); ?>" alt="Foto persona" style="max-width:150px;border-radius:8px;">
              <?php else: ?>
                <span>No hay foto cargada.</span>
              <?php endif; ?>
            </div>
            <div class="form-group">
              <label for="foto_persona">Reemplazar foto de la persona</label>
              <input type="file" name="foto_persona" id="foto_persona" accept="image/*">
            </div>
          </div>

          <div class="form-row">
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
              <input type="text" name="numero_documento" id="numero_documento" value="<?php echo htmlspecialchars($numero_documento); ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="nombres">Nombres</label>
              <input type="text" name="nombres" id="nombres" value="<?php echo htmlspecialchars($nombres); ?>">
            </div>
            <div class="form-group">
              <label for="apellidos">Apellidos</label>
              <input type="text" name="apellidos" id="apellidos" value="<?php echo htmlspecialchars($apellidos); ?>">
            </div>
          </div>

          <div class="form-row">
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
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="fecha_nacimiento">Fecha de nacimiento</label>
              <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" value="<?php echo htmlspecialchars($fecha_nacimiento); ?>">
            </div>
            <div class="form-group">
              <label for="telefono">Teléfono</label>
              <input type="text" name="telefono" id="telefono" value="<?php echo htmlspecialchars($telefono); ?>">
            </div>
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
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="nombre_predio">Nombre del predio</label>
              <input type="text" name="nombre_predio" id="nombre_predio" value="<?php echo htmlspecialchars($nombre_predio); ?>">
            </div>
            <div class="form-group">
              <label for="correo_electronico">Correo electrónico</label>
              <input type="email" name="correo_electronico" id="correo_electronico" value="<?php echo htmlspecialchars($correo_elec); ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Foto actual del documento</label><br>
              <?php if ($ruta_foto_documento_bd): ?>
                <img src="<?php echo htmlspecialchars($ruta_foto_documento_bd); ?>" alt="Foto documento" style="max-width:150px;border-radius:8px;">
              <?php else: ?>
                <span>No hay foto cargada.</span>
              <?php endif; ?>
            </div>
            <div class="form-group">
              <label for="foto_documento">Reemplazar foto del documento</label>
              <input type="file" name="foto_documento" id="foto_documento" accept="image/*">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Foto actual del predio</label><br>
              <?php if ($ruta_foto_predio_bd): ?>
                <img src="<?php echo htmlspecialchars($ruta_foto_predio_bd); ?>" alt="Foto predio" style="max-width:150px;border-radius:8px;">
              <?php else: ?>
                <span>No hay foto cargada.</span>
              <?php endif; ?>
            </div>
            <div class="form-group">
              <label for="foto_predio">Reemplazar foto del predio</label>
              <input type="file" name="foto_predio" id="foto_predio" accept="image/*">
            </div>
          </div>

          <h2>Datos administrativos</h2>

          <div class="form-row">
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
            <textarea name="nota_admin" id="nota_admin" rows="3" style="width:100%;"><?php echo htmlspecialchars($nota_admin); ?></textarea>
          </div>

          <div style="display:flex; gap:10px; margin-top:10px;">
            <a href="lista.php" class="btn-secondary">Volver</a>
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

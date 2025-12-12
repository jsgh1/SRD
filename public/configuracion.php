<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php'; // <-- USAMOS PHPMailer PARA LOS CÓDIGOS

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

$admin_id = $_SESSION['admin_id'] ?? 0;
if ($admin_id <= 0) {
    header('Location: index.php');
    exit;
}

$errores = [];
$mensaje = '';

// Campos permitidos para filtros dinámicos de la lista (base)
$campos_filtros_permitidos_base = [
    'estado_registro'    => 'Estado del registro',
    'tipo_documento'     => 'Tipo de documento',
    'afiliado'           => 'Afiliado',
    'zona'               => 'Zona',
    'genero'             => 'Género',
    'cargo'              => 'Cargo',
    'nombre_predio'      => 'Nombre del predio',
    'telefono'           => 'Teléfono',
    'correo_electronico' => 'Correo electrónico',
    'nombres'            => 'Nombres',
    'apellidos'          => 'Apellidos',
    'numero_documento'   => 'Número de documento',
];
// Array extendido: luego le añadimos los campos dinámicos
$campos_filtros_permitidos = $campos_filtros_permitidos_base;

// Cargar datos actuales del admin
$stmtAdmin = $pdo->prepare("SELECT id, nombre, cargo, email, tema, foto_perfil FROM admins WHERE id = ?");
$stmtAdmin->execute([$admin_id]);
$admin = $stmtAdmin->fetch();

if (!$admin) {
    header('Location: index.php');
    exit;
}

// Cargar config sistema
try {
    $stmtCfg = $pdo->query("SELECT * FROM config_sistema ORDER BY id ASC LIMIT 1");
    $configSistema = $stmtCfg->fetch();
} catch (Exception $e) {
    $configSistema = null;
}

$config_id       = $configSistema['id'] ?? null;
$nombre_sistema  = $configSistema['nombre_sistema'] ?? 'Sistema de Registro';
$logo_sistema    = $configSistema['logo_sistema'] ?? null;

// Helper subir foto de perfil
function subirFotoPerfil($campo, $ruta_actual, &$errores) {
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return $ruta_actual;
    }

    $file = $_FILES[$campo];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errores[] = 'Error al subir la foto de perfil.';
        return $ruta_actual;
    }

    $tmp_name = $file['tmp_name'];
    $nombre   = basename($file['name']);
    $ext      = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    $permitidas = ['jpg','jpeg','png'];

    if (!in_array($ext, $permitidas, true)) {
        $errores[] = 'Formato de imagen no permitido en foto de perfil (solo JPG o PNG).';
        return $ruta_actual;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        $errores[] = 'La foto de perfil supera los 5MB.';
        return $ruta_actual;
    }

    $carpetaFisica = __DIR__ . '/../uploads/admins';
    if (!is_dir($carpetaFisica)) {
        @mkdir($carpetaFisica, 0777, true);
    }

    $nuevo_nombre = uniqid('admin_') . '.' . $ext;
    $destino = $carpetaFisica . '/' . $nuevo_nombre;

    if (!move_uploaded_file($tmp_name, $destino)) {
        $errores[] = 'No se pudo guardar la foto de perfil.';
        return $ruta_actual;
    }

    // Borrar foto anterior si existía
    if ($ruta_actual) {
        $ruta_fisica = realpath(__DIR__ . '/' . $ruta_actual);
        if ($ruta_fisica && file_exists($ruta_fisica)) {
            @unlink($ruta_fisica);
        }
    }

    // Guardar ruta relativa desde public: ../uploads/admins/...
    return '../uploads/admins/' . $nuevo_nombre;
}

// Helper subir logo del sistema
function subirLogoSistema($campo, $ruta_actual, &$errores) {
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return $ruta_actual;
    }

    $file = $_FILES[$campo];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errores[] = 'Error al subir el logo del sistema.';
        return $ruta_actual;
    }

    $tmp_name = $file['tmp_name'];
    $nombre   = basename($file['name']);
    $ext      = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    $permitidas = ['jpg','jpeg','png','svg'];

    if (!in_array($ext, $permitidas, true)) {
        $errores[] = 'Formato de imagen no permitido para el logo (JPG, PNG o SVG).';
        return $ruta_actual;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        $errores[] = 'El logo supera los 5MB.';
        return $ruta_actual;
    }

    $carpetaFisica = __DIR__ . '/../uploads/sistema';
    if (!is_dir($carpetaFisica)) {
        @mkdir($carpetaFisica, 0777, true);
    }

    $nuevo_nombre = uniqid('logo_') . '.' . $ext;
    $destino = $carpetaFisica . '/' . $nuevo_nombre;

    if (!move_uploaded_file($tmp_name, $destino)) {
        $errores[] = 'No se pudo guardar el logo del sistema.';
        return $ruta_actual;
    }

    // Borrar logo anterior si existía
    if ($ruta_actual) {
        $ruta_fisica = realpath(__DIR__ . '/' . $ruta_actual);
        if ($ruta_fisica && file_exists($ruta_fisica)) {
            @unlink($ruta_fisica);
        }
    }

    // Guardar ruta relativa desde public: ../uploads/sistema/...
    return '../uploads/sistema/' . $nuevo_nombre;
}

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // 1. Guardar tema
    if ($accion === 'guardar_tema') {
        $nuevo_tema = $_POST['tema'] ?? 'claro';
        if (!in_array($nuevo_tema, ['claro', 'oscuro'], true)) {
            $nuevo_tema = 'claro';
        }

        $upd = $pdo->prepare("UPDATE admins SET tema = ? WHERE id = ?");
        $upd->execute([$nuevo_tema, $admin_id]);

        $_SESSION['tema'] = $nuevo_tema;
        $tema = $nuevo_tema;
        $body_class = 'main-layout tema-' . $tema;
        $mensaje = 'Tema actualizado correctamente.';
    }

    // 2. Guardar perfil (nombre, cargo, foto)
    if ($accion === 'guardar_perfil') {
        $nombre = trim($_POST['nombre'] ?? '');
        $cargo  = trim($_POST['cargo'] ?? '');
        $ruta_actual = $admin['foto_perfil'] ?? null;

        if ($nombre === '') $errores[] = 'El nombre es obligatorio.';
        if ($cargo === '')  $errores[] = 'El cargo es obligatorio.';

        $ruta_foto = $ruta_actual;
        if (empty($errores)) {
            $ruta_foto = subirFotoPerfil('foto_perfil', $ruta_actual, $errores);
        }

        if (empty($errores)) {
            $upd = $pdo->prepare("UPDATE admins SET nombre = ?, cargo = ?, foto_perfil = ? WHERE id = ?");
            $upd->execute([$nombre, $cargo, $ruta_foto, $admin_id]);

            $_SESSION['admin_nombre'] = $nombre;
            $_SESSION['admin_cargo']  = $cargo;
            $_SESSION['foto_perfil']  = $ruta_foto;

            $admin['nombre']      = $nombre;
            $admin['cargo']       = $cargo;
            $admin['foto_perfil'] = $ruta_foto;

            $mensaje = 'Perfil actualizado correctamente.';
        }
    }

    // 3. Solicitar cambio de email (enviar código al nuevo correo con PHPMailer)
    if ($accion === 'solicitar_cambio_email') {
        $nuevo_email  = trim($_POST['nuevo_email'] ?? '');
        $email_actual = $admin['email'] ?? '';

        // Limpiamos código de debug anterior
        unset($_SESSION['debug_codigo_cambio_email']);

        if ($nuevo_email === '') {
            $errores[] = 'Ingresa el nuevo correo electrónico.';
        } elseif (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El nuevo correo no tiene un formato válido.';
        } elseif ($nuevo_email === $email_actual) {
            $errores[] = 'El nuevo correo es igual al actual.';
        }

        if (empty($errores)) {
            $codigo = (string) random_int(100000, 999999);

            $updOld = $pdo->prepare("UPDATE codigos_login SET usado = 1 WHERE admin_id = ? AND tipo = 'cambio_email'");
            $updOld->execute([$admin_id]);

            $ins = $pdo->prepare("
                INSERT INTO codigos_login (admin_id, tipo, codigo, usado, expires_at)
                VALUES (?, 'cambio_email', ?, 0, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
            ");
            $ins->execute([$admin_id, $codigo]);

            $_SESSION['cambio_email_nuevo'] = $nuevo_email;

            // Usamos PHPMailer en lugar de mail()
            $enviado = enviarCodigoCambioEmail($nuevo_email, $codigo);

            if ($enviado) {
                $mensaje = 'Hemos generado un código de verificación y lo enviamos al nuevo correo. Ingrésalo abajo para confirmar el cambio.';
            } else {
                // Entorno local / sin SMTP: mostramos el código para pruebas
                $_SESSION['debug_codigo_cambio_email'] = $codigo;
                $mensaje = 'Se generó el código de verificación pero no se pudo enviar el correo. '
                         . 'Como estás en entorno de pruebas, mostraremos el código abajo solo para uso local.';
            }
        }
    }

    // 4. Confirmar cambio de email
    if ($accion === 'confirmar_cambio_email') {
        $codigo_ingresado = trim($_POST['codigo_verificacion'] ?? '');
        $nuevo_email = $_SESSION['cambio_email_nuevo'] ?? null;

        if ($codigo_ingresado === '') {
            $errores[] = 'Ingresa el código de verificación.';
        }
        if (!$nuevo_email) {
            $errores[] = 'No hay un cambio de correo pendiente. Vuelve a solicitar el código.';
        }

        if (empty($errores)) {
            $stmtCod = $pdo->prepare("
                SELECT * FROM codigos_login
                WHERE admin_id = ? AND tipo = 'cambio_email' AND codigo = ? AND usado = 0 AND expires_at > NOW()
                ORDER BY id DESC
                LIMIT 1
            ");
            $stmtCod->execute([$admin_id, $codigo_ingresado]);
            $codRow = $stmtCod->fetch();

            if ($codRow) {
                $updCod = $pdo->prepare("UPDATE codigos_login SET usado = 1 WHERE id = ?");
                $updCod->execute([$codRow['id']]);

                $updEmail = $pdo->prepare("UPDATE admins SET email = ? WHERE id = ?");
                $updEmail->execute([$nuevo_email, $admin_id]);

                $_SESSION['admin_email'] = $nuevo_email;
                $admin['email'] = $nuevo_email;

                unset($_SESSION['cambio_email_nuevo']);
                unset($_SESSION['debug_codigo_cambio_email']);

                $mensaje = 'Correo actualizado correctamente.';
            } else {
                $errores[] = 'Código inválido o vencido.';
            }
        }
    }

    // 5. Agregar opción de select
    if ($accion === 'agregar_opcion') {
        $grupo = trim($_POST['grupo'] ?? '');
        $valor = trim($_POST['valor'] ?? '');

        $grupos_permitidos = ['afiliado','zona','genero','cargo'];

        if (!in_array($grupo, $grupos_permitidos, true)) {
            $errores[] = 'Grupo inválido.';
        }
        if ($valor === '') {
            $errores[] = 'El valor no puede estar vacío.';
        }

        if (empty($errores)) {
            $ins = $pdo->prepare("INSERT INTO opciones_select (grupo, valor, activo) VALUES (?, ?, 1)");
            $ins->execute([$grupo, $valor]);
            $mensaje = 'Opción agregada correctamente.';
        }
    }

    // 6. Cambiar estado (activar/desactivar) opción
    if ($accion === 'cambiar_estado_opcion') {
        $id_opcion    = (int)($_POST['id_opcion'] ?? 0);
        $nuevo_estado = (int)($_POST['nuevo_estado'] ?? 0);

        if ($id_opcion > 0) {
            $upd = $pdo->prepare("UPDATE opciones_select SET activo = ? WHERE id = ?");
            $upd->execute([$nuevo_estado ? 1 : 0, $id_opcion]);
            $mensaje = 'Opción actualizada.';
        }
    }

    // 7. Guardar nombre + logo del sistema
    if ($accion === 'guardar_sistema') {
        $nombre_sis_form = trim($_POST['nombre_sistema'] ?? '');
        if ($nombre_sis_form === '') {
            $errores[] = 'El nombre del sistema no puede estar vacío.';
        }

        $ruta_logo = $logo_sistema;
        if (empty($errores)) {
            $ruta_logo = subirLogoSistema('logo_sistema', $logo_sistema, $errores);
        }

        if (empty($errores)) {
            if ($config_id) {
                $upd = $pdo->prepare("UPDATE config_sistema SET nombre_sistema = ?, logo_sistema = ? WHERE id = ?");
                $upd->execute([$nombre_sis_form, $ruta_logo, $config_id]);
            } else {
                $ins = $pdo->prepare("INSERT INTO config_sistema (nombre_sistema, logo_sistema) VALUES (?, ?)");
                $ins->execute([$nombre_sis_form, $ruta_logo]);
                $config_id = $pdo->lastInsertId();
            }

            $nombre_sistema = $nombre_sis_form;
            $logo_sistema   = $ruta_logo;

            $mensaje = 'Nombre y logo del sistema actualizados correctamente.';
        }
    }

    // 8. Agregar CAMPO EXTRA de registro
    if ($accion === 'agregar_campo_extra') {
        $grupo       = trim($_POST['grupo_campo'] ?? '');
        $nombre_lbl  = trim($_POST['nombre_label'] ?? '');
        $tipo_ctrl   = trim($_POST['tipo_control_campo'] ?? 'texto');
        $requerido   = isset($_POST['requerido']) ? 1 : 0;
        $orden       = isset($_POST['orden_campo']) && $_POST['orden_campo'] !== '' ? (int)$_POST['orden_campo'] : 0;

        $grupos_validos = ['persona','contacto','predio'];
        $tipos_validos  = ['texto','numero','fecha','select'];

        if (!in_array($grupo, $grupos_validos, true)) {
            $errores[] = 'Grupo de campo inválido.';
        }
        if ($nombre_lbl === '') {
            $errores[] = 'La etiqueta del campo es obligatoria.';
        }
        if (!in_array($tipo_ctrl, $tipos_validos, true)) {
            $tipo_ctrl = 'texto';
        }

        if (empty($errores)) {
            $ins = $pdo->prepare("
                INSERT INTO campos_extra_registro (grupo, nombre_label, tipo_control, requerido, activo, orden)
                VALUES (?, ?, ?, ?, 1, ?)
            ");
            $ins->execute([$grupo, $nombre_lbl, $tipo_ctrl, $requerido, $orden]);
            $mensaje = 'Campo extra agregado correctamente.';
        }
    }

    // 9. Cambiar estado (activar/desactivar) CAMPO EXTRA
    if ($accion === 'cambiar_estado_campo_extra') {
        $id_campo     = (int)($_POST['id_campo'] ?? 0);
        $nuevo_estado = (int)($_POST['nuevo_estado'] ?? 0);

        if ($id_campo > 0) {
            $upd = $pdo->prepare("UPDATE campos_extra_registro SET activo = ? WHERE id = ?");
            $upd->execute([$nuevo_estado ? 1 : 0, $id_campo]);
            $mensaje = 'Campo extra actualizado.';
        }
    }

    // 10. Eliminar CAMPO EXTRA
    if ($accion === 'eliminar_campo_extra') {
        $id_campo = (int)($_POST['id_campo'] ?? 0);
        if ($id_campo > 0) {
            $del = $pdo->prepare("DELETE FROM campos_extra_registro WHERE id = ?");
            $del->execute([$id_campo]);
            $mensaje = 'Campo extra eliminado.';
        }
    }

    // 11. Agregar FILTRO de lista
    if ($accion === 'agregar_filtro_lista') {
        $nombre_campo = trim($_POST['nombre_campo_filtro'] ?? '');
        $etiqueta     = trim($_POST['etiqueta_filtro'] ?? '');
        $tipo_ctrl    = trim($_POST['tipo_control_filtro'] ?? 'select');
        $orden        = isset($_POST['orden_filtro']) && $_POST['orden_filtro'] !== '' ? (int)$_POST['orden_filtro'] : 0;

        $is_extra = (strpos($nombre_campo, 'extra_') === 0);

        if ($is_extra) {
            // Validamos que exista ese campo dinámico
            $idExtra = (int)substr($nombre_campo, 6);
            if ($idExtra <= 0) {
                $errores[] = 'Campo de filtro dinámico inválido.';
            } else {
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM campos_extra_registro WHERE id = ?");
                $stmtCheck->execute([$idExtra]);
                if ((int)$stmtCheck->fetchColumn() === 0) {
                    $errores[] = 'El campo dinámico seleccionado no existe.';
                }
            }
        } else {
            // Campos base: validamos contra la lista base
            if (!array_key_exists($nombre_campo, $campos_filtros_permitidos_base)) {
                $errores[] = 'Campo de filtro inválido.';
            }
        }

        if ($etiqueta === '') {
            $errores[] = 'La etiqueta del filtro es obligatoria.';
        }
        if (!in_array($tipo_ctrl, ['texto','select'], true)) {
            $tipo_ctrl = 'select';
        }

        if (empty($errores)) {
            $ins = $pdo->prepare("
                INSERT INTO campos_filtros_lista (nombre_campo, etiqueta, tipo_control, activo, orden)
                VALUES (?, ?, ?, 1, ?)
            ");
            $ins->execute([$nombre_campo, $etiqueta, $tipo_ctrl, $orden]);
            $mensaje = 'Filtro agregado correctamente.';
        }
    }

    // 12. Cambiar estado FILTRO de lista
    if ($accion === 'cambiar_estado_filtro') {
        $id_filtro    = (int)($_POST['id_filtro'] ?? 0);
        $nuevo_estado = (int)($_POST['nuevo_estado'] ?? 0);

        if ($id_filtro > 0) {
            $upd = $pdo->prepare("UPDATE campos_filtros_lista SET activo = ? WHERE id = ?");
            $upd->execute([$nuevo_estado ? 1 : 0, $id_filtro]);
            $mensaje = 'Filtro actualizado.';
        }
    }

    // 13. Eliminar FILTRO de lista
    if ($accion === 'eliminar_filtro_lista') {
        $id_filtro = (int)($_POST['id_filtro'] ?? 0);
        if ($id_filtro > 0) {
            $del = $pdo->prepare("DELETE FROM campos_filtros_lista WHERE id = ?");
            $del->execute([$id_filtro]);
            $mensaje = 'Filtro eliminado.';
        }
    }
}

// Cargar opciones actuales
function cargarOpcionesPorGrupo($pdo, $grupo) {
    $stmt = $pdo->prepare("SELECT id, valor, activo FROM opciones_select WHERE grupo = ? ORDER BY valor");
    $stmt->execute([$grupo]);
    return $stmt->fetchAll();
}

$op_afiliado = cargarOpcionesPorGrupo($pdo, 'afiliado');
$op_zona     = cargarOpcionesPorGrupo($pdo, 'zona');
$op_genero   = cargarOpcionesPorGrupo($pdo, 'genero');
$op_cargo    = cargarOpcionesPorGrupo($pdo, 'cargo');

$cambio_email_pendiente = $_SESSION['cambio_email_nuevo'] ?? null;
$debug_codigo = $_SESSION['debug_codigo_cambio_email'] ?? null;

// Cargar campos extra de registro
function cargarCamposExtraRegistro($pdo) {
    $stmt = $pdo->query("
        SELECT *
        FROM campos_extra_registro
        ORDER BY grupo, orden, id
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Cargar filtros de lista
function cargarFiltrosListaConfig($pdo) {
    $stmt = $pdo->query("
        SELECT *
        FROM campos_filtros_lista
        ORDER BY orden, id
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$campos_extra_registro = cargarCamposExtraRegistro($pdo);
$filtros_lista         = cargarFiltrosListaConfig($pdo);

// Ampliar campos de filtros permitidos con los campos dinámicos creados
if (!empty($campos_extra_registro)) {
    foreach ($campos_extra_registro as $c_extra) {
        $key = 'extra_' . $c_extra['id'];
        $campos_filtros_permitidos[$key] = $c_extra['nombre_label'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Configuración - Sistema de Registro</title>
  <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body class="<?php echo $body_class; ?>">
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <div class="layout-container">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="content">
      <h1>Configuración del sistema</h1>

      <?php if (!empty($errores)): ?>
        <div class="alert alert-error">
          <ul>
            <?php foreach ($errores as $e): ?>
              <li><?php echo htmlspecialchars($e); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if ($mensaje): ?>
        <div class="alert" style="margin-bottom:10px;">
          <?php echo htmlspecialchars($mensaje); ?>
        </div>
      <?php endif; ?>

      <div class="config-grid">
        <!-- Configuración del sistema (nombre + logo) -->
        <section class="form-card config-card">
          <h2>
            <span class="config-icon">
              <svg viewBox="0 0 24 24" class="icon-svg">
                <rect x="4" y="6" width="16" height="12" rx="3" fill="none"></rect>
                <path d="M7 10h10" fill="none"></path>
              </svg>
            </span>
            Identidad del sistema
          </h2>
          <p class="config-desc">
            Cambia el nombre que aparece en la barra superior y el logo redondo que lo acompaña.
          </p>

          <form method="post" action="" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="guardar_sistema">

            <div class="perfil-admin-grid">
              <div class="perfil-admin-foto">
                <div class="perfil-admin-avatar">
                  <?php if (!empty($logo_sistema)): ?>
                    <img src="<?php echo htmlspecialchars($logo_sistema); ?>" alt="Logo del sistema">
                  <?php else: ?>
                    <svg viewBox="0 0 24 24" class="icon-svg">
                      <rect x="4" y="6" width="16" height="12" rx="3" fill="none"></rect>
                      <path d="M7 10h10" fill="none"></path>
                    </svg>
                  <?php endif; ?>
                </div>
                <div class="form-group">
                  <label for="logo_sistema">Cambiar logo</label>
                  <input type="file" name="logo_sistema" id="logo_sistema" accept="image/*">
                </div>
              </div>

              <div class="perfil-admin-datos">
                <div class="form-group">
                  <label for="nombre_sistema">Nombre del sistema</label>
                  <input
                    type="text"
                    name="nombre_sistema"
                    id="nombre_sistema"
                    value="<?php echo htmlspecialchars($nombre_sistema); ?>"
                    required
                  >
                </div>
              </div>
            </div>

            <button type="submit" class="btn-primary btn-small">Guardar cambios</button>
          </form>
        </section>

        <!-- Tema -->
        <section class="form-card config-card">
          <h2>
            <span class="config-icon">
              <svg viewBox="0 0 24 24" class="icon-svg">
                <circle cx="12" cy="12" r="4"></circle>
                <line x1="12" y1="3" x2="12" y2="5"></line>
                <line x1="12" y1="19" x2="12" y2="21"></line>
                <line x1="5" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="19" y2="12"></line>
              </svg>
            </span>
            Tema del sistema
          </h2>
          <p class="config-desc">Elige si quieres ver el sistema en modo claro u oscuro.</p>

          <form method="post" action="">
            <input type="hidden" name="accion" value="guardar_tema">
            <div class="config-tema-options">
              <label class="config-radio">
                <input type="radio" name="tema" value="claro" <?php echo $tema === 'claro' ? 'checked' : ''; ?>>
                <span>Claro</span>
              </label>
              <label class="config-radio">
                <input type="radio" name="tema" value="oscuro" <?php echo $tema === 'oscuro' ? 'checked' : ''; ?>>
                <span>Oscuro</span>
              </label>
            </div>
            <button type="submit" class="btn-primary btn-small">Guardar tema</button>
          </form>
        </section>

        <!-- Perfil admin -->
        <section class="form-card config-card" id="perfil-admin">
          <h2>
            <span class="config-icon">
              <svg viewBox="0 0 24 24" class="icon-svg">
                <circle cx="12" cy="9" r="4"></circle>
                <path d="M5 20c0-3.3 3-6 7-6s7 2.7 7 6" fill="none"></path>
              </svg>
            </span>
            Perfil del administrador
          </h2>
          <p class="config-desc">Actualiza tu nombre, cargo y foto de perfil.</p>

          <form method="post" action="" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="guardar_perfil">

            <div class="perfil-admin-grid">
              <div class="perfil-admin-foto">
                <div class="perfil-admin-avatar">
                  <?php if (!empty($admin['foto_perfil'])): ?>
                    <img src="<?php echo htmlspecialchars($admin['foto_perfil']); ?>" alt="Foto perfil">
                  <?php else: ?>
                    <svg viewBox="0 0 24 24" class="icon-svg">
                      <circle cx="12" cy="9" r="4"></circle>
                      <path d="M5 20c0-3.3 3-6 7-6s7 2.7 7 6" fill="none"></path>
                    </svg>
                  <?php endif; ?>
                </div>
                <div class="form-group">
                  <label for="foto_perfil">Cambiar foto</label>
                  <input type="file" name="foto_perfil" id="foto_perfil" accept="image/*">
                </div>
              </div>

              <div class="perfil-admin-datos">
                <div class="form-group">
                  <label for="nombre">Nombre</label>
                  <input type="text" name="nombre" id="nombre" value="<?php echo htmlspecialchars($admin['nombre']); ?>" required>
                </div>
                <div class="form-group">
                  <label for="cargo">Cargo</label>
                  <input type="text" name="cargo" id="cargo" value="<?php echo htmlspecialchars($admin['cargo']); ?>" required>
                </div>
                <div class="form-group">
                  <label>Correo actual</label>
                  <input type="email" value="<?php echo htmlspecialchars($admin['email']); ?>" disabled>
                </div>
              </div>
            </div>

            <button type="submit" class="btn-primary btn-small">Guardar perfil</button>
          </form>
        </section>

        <!-- Cambio de correo -->
        <section class="form-card config-card">
          <h2>
            <span class="config-icon">
              <svg viewBox="0 0 24 24" class="icon-svg">
                <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                <polyline points="4 6 12 12 20 6" fill="none"></polyline>
              </svg>
            </span>
            Correo de acceso
          </h2>
          <p class="config-desc">
            Cambia el correo que utilizas para iniciar sesión. Enviaremos un código de verificación
            al nuevo correo para confirmar el cambio.
          </p>

          <form method="post" action="" class="config-email-form">
            <input type="hidden" name="accion" value="solicitar_cambio_email">
            <div class="form-group">
              <label for="nuevo_email">Nuevo correo electrónico</label>
              <input type="email" name="nuevo_email" id="nuevo_email" placeholder="nuevo-correo@ejemplo.com" required>
            </div>
            <button type="submit" class="btn-outline btn-small">Enviar código al nuevo correo</button>
          </form>

          <?php if ($cambio_email_pendiente): ?>
            <div class="config-email-pendiente">
              <p>
                Tenemos un cambio de correo pendiente para:
                <strong><?php echo htmlspecialchars($cambio_email_pendiente); ?></strong>
              </p>
              <form method="post" action="">
                <input type="hidden" name="accion" value="confirmar_cambio_email">
                <div class="form-group">
                  <label for="codigo_verificacion">Código de verificación</label>
                  <input type="text" name="codigo_verificacion" id="codigo_verificacion" maxlength="6" required>
                </div>
                <button type="submit" class="btn-primary btn-small">Confirmar cambio de correo</button>
              </form>
            </div>
          <?php endif; ?>

          <?php if (!empty($debug_codigo)): ?>
            <p class="config-desc" style="margin-top:8px;">
              <strong>Solo para pruebas en local:</strong> el código generado es
              <code><?php echo htmlspecialchars($debug_codigo); ?></code>.
              En un servidor real configura el correo para no mostrar este dato.
            </p>
          <?php endif; ?>
        </section>
      </div>

      <!-- Opciones de selects -->
      <section class="form-card config-card config-opciones-card">
        <h2>
          <span class="config-icon">
            <svg viewBox="0 0 24 24" class="icon-svg">
              <rect x="4" y="4" width="16" height="4" rx="1"></rect>
              <rect x="4" y="10" width="16" height="4" rx="1"></rect>
              <rect x="4" y="16" width="16" height="4" rx="1"></rect>
            </svg>
          </span>
          Opciones de listas desplegables
        </h2>
        <p class="config-desc">
          Administra los valores que aparecen en los select de afiliado, zona, género y cargo.
        </p>

        <div class="config-opciones-grid">
          <?php
          $grupos = [
            'afiliado' => $op_afiliado,
            'zona'     => $op_zona,
            'genero'   => $op_genero,
            'cargo'    => $op_cargo,
          ];
          $nombresGrupo = [
            'afiliado' => 'Afiliado',
            'zona'     => 'Zona',
            'genero'   => 'Género',
            'cargo'    => 'Cargo',
          ];
          ?>

          <?php foreach ($grupos as $clave => $lista): ?>
            <div class="config-opciones-col">
              <h3><?php echo $nombresGrupo[$clave]; ?></h3>
              <?php if (empty($lista)): ?>
                <p class="config-opciones-empty">No hay opciones registradas.</p>
              <?php else: ?>
                <table class="config-opciones-tabla">
                  <thead>
                    <tr>
                      <th>Valor</th>
                      <th>Estado</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($lista as $op): ?>
                      <tr>
                        <td><?php echo htmlspecialchars($op['valor']); ?></td>
                        <td>
                          <?php if ($op['activo']): ?>
                            <span class="badge badge-activo">Activo</span>
                          <?php else: ?>
                            <span class="badge badge-inactivo">Inactivo</span>
                          <?php endif; ?>
                        </td>
                        <td class="config-opciones-acciones">
                          <form method="post" action="" class="inline-form">
                            <input type="hidden" name="accion" value="cambiar_estado_opcion">
                            <input type="hidden" name="id_opcion" value="<?php echo $op['id']; ?>">
                            <input type="hidden" name="nuevo_estado" value="<?php echo $op['activo'] ? 0 : 1; ?>">
                            <button type="submit" class="icon-button" title="<?php echo $op['activo'] ? 'Desactivar' : 'Activar'; ?>">
                              <?php if ($op['activo']): ?>
                                <svg viewBox="0 0 24 24" class="icon-svg">
                                  <line x1="6" y1="6" x2="18" y2="18"></line>
                                  <line x1="6" y1="18" x2="18" y2="6"></line>
                                </svg>
                              <?php else: ?>
                                <svg viewBox="0 0 24 24" class="icon-svg">
                                  <polyline points="4 13 9 18 20 6" fill="none" stroke-width="2"></polyline>
                                </svg>
                              <?php endif; ?>
                            </button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="config-opciones-agregar">
          <h3>Agregar nueva opción</h3>
          <form method="post" action="" class="config-opciones-form">
            <input type="hidden" name="accion" value="agregar_opcion">
            <div class="form-group">
              <label for="grupo">Grupo</label>
              <select name="grupo" id="grupo" required>
                <option value="">Seleccione...</option>
                <option value="afiliado">Afiliado</option>
                <option value="zona">Zona</option>
                <option value="genero">Género</option>
                <option value="cargo">Cargo</option>
              </select>
            </div>
            <div class="form-group">
              <label for="valor">Valor</label>
              <input type="text" name="valor" id="valor" required>
            </div>
            <button type="submit" class="btn-primary btn-small">Agregar opción</button>
          </form>
        </div>
      </section>

      <!-- CAMPOS DINÁMICOS DE REGISTRO -->
      <section class="form-card config-card">
        <h2>
          <span class="config-icon">
            <svg viewBox="0 0 24 24" class="icon-svg">
              <rect x="4" y="4" width="16" height="4" rx="1"></rect>
              <rect x="4" y="10" width="10" height="4" rx="1"></rect>
              <rect x="4" y="16" width="14" height="4" rx="1"></rect>
            </svg>
          </span>
          Campos dinámicos del registro
        </h2>
        <p class="config-desc">
          Define campos adicionales para el formulario de registro y el detalle de las personas.
          Puedes ubicarlos en las secciones de "Datos de la persona", "Contacto y ubicación" o "Información del predio".
        </p>

        <?php if (empty($campos_extra_registro)): ?>
          <p class="config-opciones-empty">No hay campos extra configurados.</p>
        <?php else: ?>
          <div class="config-opciones-grid">
            <div class="config-opciones-col" style="grid-column: 1 / -1;">
              <table class="config-opciones-tabla">
                <thead>
                  <tr>
                    <th>Etiqueta</th>
                    <th>Grupo</th>
                    <th>Tipo</th>
                    <th>Requerido</th>
                    <th>Estado</th>
                    <th>Orden</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($campos_extra_registro as $c): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($c['nombre_label']); ?></td>
                      <td><?php echo htmlspecialchars(ucfirst($c['grupo'])); ?></td>
                      <td><?php echo htmlspecialchars($c['tipo_control']); ?></td>
                      <td><?php echo $c['requerido'] ? 'Sí' : 'No'; ?></td>
                      <td>
                        <?php if ($c['activo']): ?>
                          <span class="badge badge-activo">Activo</span>
                        <?php else: ?>
                          <span class="badge badge-inactivo">Inactivo</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo (int)$c['orden']; ?></td>
                      <td class="config-opciones-acciones">
                        <form method="post" action="" class="inline-form" style="display:inline-block;">
                          <input type="hidden" name="accion" value="cambiar_estado_campo_extra">
                          <input type="hidden" name="id_campo" value="<?php echo $c['id']; ?>">
                          <input type="hidden" name="nuevo_estado" value="<?php echo $c['activo'] ? 0 : 1; ?>">
                          <button type="submit" class="icon-button" title="<?php echo $c['activo'] ? 'Desactivar' : 'Activar'; ?>">
                            <?php if ($c['activo']): ?>
                              <svg viewBox="0 0 24 24" class="icon-svg">
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                <line x1="6" y1="18" x2="18" y2="6"></line>
                              </svg>
                            <?php else: ?>
                              <svg viewBox="0 0 24 24" class="icon-svg">
                                <polyline points="4 13 9 18 20 6" fill="none" stroke-width="2"></polyline>
                              </svg>
                            <?php endif; ?>
                          </button>
                        </form>
                        <!-- ELIMINAR CAMPO EXTRA CON MODAL BONITO -->
                        <form
                          method="post"
                          action=""
                          class="inline-form form-confirm"
                          style="display:inline-block;"
                          data-confirm="¿Eliminar este campo extra? Esta acción no borrará las personas, solo el campo y sus valores asociados. Esta acción NO se puede deshacer."
                        >
                          <input type="hidden" name="accion" value="eliminar_campo_extra">
                          <input type="hidden" name="id_campo" value="<?php echo $c['id']; ?>">
                          <button type="submit" class="icon-button icon-button-danger" title="Eliminar campo">
                            <svg viewBox="0 0 24 24" class="icon-svg">
                              <polyline points="3 6 5 6 21 6" fill="none"></polyline>
                              <path d="M8 6V4h8v2" fill="none"></path>
                              <path d="M19 6l-1 14H6L5 6" fill="none"></path>
                              <line x1="10" y1="10" x2="10" y2="17"></line>
                              <line x1="14" y1="10" x2="14" y2="17"></line>
                            </svg>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

        <div class="config-opciones-agregar">
          <h3>Agregar nuevo campo</h3>
          <form method="post" action="" class="config-opciones-form">
            <input type="hidden" name="accion" value="agregar_campo_extra">
            <div class="form-group">
              <label for="grupo_campo">Sección (grupo)</label>
              <select name="grupo_campo" id="grupo_campo" required>
                <option value="">Seleccione...</option>
                <option value="persona">Datos de la persona</option>
                <option value="contacto">Contacto y ubicación</option>
                <option value="predio">Información del predio</option>
              </select>
            </div>
            <div class="form-group">
              <label for="nombre_label">Etiqueta visible</label>
              <input type="text" name="nombre_label" id="nombre_label" placeholder="Ej: Junta de Acción Comunal" required>
            </div>
            <div class="form-group">
              <label for="tipo_control_campo">Tipo de campo</label>
              <select name="tipo_control_campo" id="tipo_control_campo" required>
                <option value="texto">Texto</option>
                <option value="numero">Número</option>
                <option value="fecha">Fecha</option>
                <option value="select">Select (lista)</option>
              </select>
            </div>
            <div class="form-group">
              <label for="orden_campo">Orden (opcional)</label>
              <input type="number" name="orden_campo" id="orden_campo" placeholder="0">
            </div>
            <div class="form-group">
              <label>
                <input type="checkbox" name="requerido">
                Campo requerido al marcar "Completado"
              </label>
            </div>
            <button type="submit" class="btn-primary btn-small">Agregar campo</button>
          </form>
        </div>
      </section>

      <!-- FILTROS DE LA LISTA DE REGISTROS -->
      <section class="form-card config-card">
        <h2>
          <span class="config-icon">
            <svg viewBox="0 0 24 24" class="icon-svg">
              <path d="M4 4h16l-6 8v6l-4 2v-8z" fill="none"></path>
            </svg>
          </span>
          Filtros de la lista de registros
        </h2>
        <p class="config-desc">
          Elige qué filtros adicionales aparecerán en la página de lista de registros.
          Puedes combinarlos con el buscador general por nombre, apellido o documento.
        </p>

        <?php if (empty($filtros_lista)): ?>
          <p class="config-opciones-empty">No hay filtros configurados.</p>
        <?php else: ?>
          <div class="config-opciones-grid">
            <div class="config-opciones-col" style="grid-column: 1 / -1;">
              <table class="config-opciones-tabla">
                <thead>
                  <tr>
                    <th>Etiqueta</th>
                    <th>Campo</th>
                    <th>Tipo control</th>
                    <th>Estado</th>
                    <th>Orden</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($filtros_lista as $f): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($f['etiqueta']); ?></td>
                      <td><?php
                        $nc = $f['nombre_campo'];
                        echo htmlspecialchars($campos_filtros_permitidos[$nc] ?? $nc);
                      ?></td>
                      <td><?php echo htmlspecialchars($f['tipo_control']); ?></td>
                      <td>
                        <?php if ($f['activo']): ?>
                          <span class="badge badge-activo">Activo</span>
                        <?php else: ?>
                          <span class="badge badge-inactivo">Inactivo</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo (int)$f['orden']; ?></td>
                      <td class="config-opciones-acciones">
                        <form method="post" action="" class="inline-form" style="display:inline-block;">
                          <input type="hidden" name="accion" value="cambiar_estado_filtro">
                          <input type="hidden" name="id_filtro" value="<?php echo $f['id']; ?>">
                          <input type="hidden" name="nuevo_estado" value="<?php echo $f['activo'] ? 0 : 1; ?>">
                          <button type="submit" class="icon-button" title="<?php echo $f['activo'] ? 'Desactivar' : 'Activar'; ?>">
                            <?php if ($f['activo']): ?>
                              <svg viewBox="0 0 24 24" class="icon-svg">
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                <line x1="6" y1="18" x2="18" y2="6"></line>
                              </svg>
                            <?php else: ?>
                              <svg viewBox="0 0 24 24" class="icon-svg">
                                <polyline points="4 13 9 18 20 6" fill="none" stroke-width="2"></polyline>
                              </svg>
                            <?php endif; ?>
                          </button>
                        </form>
                        <!-- ELIMINAR FILTRO CON MODAL BONITO -->
                        <form
                          method="post"
                          action=""
                          class="inline-form form-confirm"
                          style="display:inline-block;"
                          data-confirm="¿Eliminar este filtro de la lista de registros? Esta acción NO se puede deshacer."
                        >
                          <input type="hidden" name="accion" value="eliminar_filtro_lista">
                          <input type="hidden" name="id_filtro" value="<?php echo $f['id']; ?>">
                          <button type="submit" class="icon-button icon-button-danger" title="Eliminar filtro">
                            <svg viewBox="0 0 24 24" class="icon-svg">
                              <polyline points="3 6 5 6 21 6" fill="none"></polyline>
                              <path d="M8 6V4h8v2" fill="none"></path>
                              <path d="M19 6l-1 14H6L5 6" fill="none"></path>
                              <line x1="10" y1="10" x2="10" y2="17"></line>
                              <line x1="14" y1="10" x2="14" y2="17"></line>
                            </svg>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>

        <div class="config-opciones-agregar">
          <h3>Agregar nuevo filtro</h3>
          <form method="post" action="" class="config-opciones-form">
            <input type="hidden" name="accion" value="agregar_filtro_lista">
            <div class="form-group">
              <label for="nombre_campo_filtro">Campo</label>
              <select name="nombre_campo_filtro" id="nombre_campo_filtro" required>
                <option value="">Seleccione...</option>
                <optgroup label="Campos principales">
                  <?php foreach ($campos_filtros_permitidos_base as $campo => $labelCampo): ?>
                    <option value="<?php echo htmlspecialchars($campo); ?>">
                      <?php echo htmlspecialchars($labelCampo . ' (' . $campo . ')'); ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
                <?php if (!empty($campos_extra_registro)): ?>
                  <optgroup label="Campos dinámicos">
                    <?php foreach ($campos_extra_registro as $c_extra): ?>
                      <?php $valorOpt = 'extra_' . $c_extra['id']; ?>
                      <option value="<?php echo htmlspecialchars($valorOpt); ?>">
                        <?php echo htmlspecialchars($c_extra['nombre_label']); ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endif; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="etiqueta_filtro">Etiqueta visible</label>
              <input type="text" name="etiqueta_filtro" id="etiqueta_filtro" placeholder="Ej: Estado, Zona, Afiliado" required>
            </div>
            <div class="form-group">
              <label for="tipo_control_filtro">Tipo de control</label>
              <select name="tipo_control_filtro" id="tipo_control_filtro" required>
                <option value="select">Select (lista de valores)</option>
                <option value="texto">Texto (búsqueda parcial)</option>
              </select>
            </div>
            <div class="form-group">
              <label for="orden_filtro">Orden (opcional)</label>
              <input type="number" name="orden_filtro" id="orden_filtro" placeholder="0">
            </div>
            <button type="submit" class="btn-primary btn-small">Agregar filtro</button>
          </form>
        </div>
      </section>
    </main>
  </div>

  <!-- MODAL BONITO PARA CONFIRMAR ELIMINACIÓN (CAMPOS Y FILTROS) -->
  <div class="modal-overlay" id="modalConfirmConfig">
    <div class="modal-box">
      <h2>Confirmar eliminación</h2>
      <p id="modalConfirmConfigMensaje">¿Seguro que deseas eliminar este elemento?</p>
      <div class="modal-actions">
        <button type="button" class="btn-muted" id="modalConfirmConfigCancelar">Cancelar</button>
        <button type="button" class="btn-primary" id="modalConfirmConfigAceptar">Sí, eliminar</button>
      </div>
    </div>
  </div>

  <script>
    // Reutilizamos el mismo patrón de lista.php para mostrar un modal bonito
    document.addEventListener('DOMContentLoaded', function () {
      var modal = document.getElementById('modalConfirmConfig');
      var msgEl = document.getElementById('modalConfirmConfigMensaje');
      var btnCancelar = document.getElementById('modalConfirmConfigCancelar');
      var btnAceptar = document.getElementById('modalConfirmConfigAceptar');
      var formPendiente = null;

      function abrirModal(form, mensaje) {
        formPendiente = form;
        msgEl.textContent = mensaje || '¿Seguro que deseas eliminar este elemento?';
        modal.classList.add('is-open');
      }

      function cerrarModal() {
        modal.classList.remove('is-open');
        formPendiente = null;
      }

      document.querySelectorAll('form.form-confirm[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
          if (form.getAttribute('data-confirm-ok') === '1') {
            return;
          }
          e.preventDefault();
          var mensaje = form.getAttribute('data-confirm') || '¿Seguro que deseas eliminar este elemento?';
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

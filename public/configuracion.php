<?php
// public/configuracion.php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

$admin_id = $_SESSION['admin_id'] ?? 0;
if ($admin_id <= 0) {
    header('Location: index.php');
    exit;
}

$errores = [];
$mensaje = '';

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

    // 3. Solicitar cambio de email (enviar código al nuevo correo)
    if ($accion === 'solicitar_cambio_email') {
        $nuevo_email = trim($_POST['nuevo_email'] ?? '');
        $email_actual = $admin['email'] ?? '';

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

            $asunto = 'Código de verificación para cambio de correo';
            $mensajeCorreo = "Hola,\n\nHas solicitado cambiar el correo de acceso al Sistema de Registro.\n\n".
                             "Tu código de verificación es: {$codigo}\n\n".
                             "Este código es válido por algunos minutos.\n\n".
                             "Si no solicitaste este cambio, ignora este mensaje.";
            $cabeceras = "From: no-reply@sistema-registro.local\r\n";
            @mail($nuevo_email, $asunto, $mensajeCorreo, $cabeceras);

            $mensaje = 'Hemos generado un código de verificación y lo enviamos al nuevo correo. Ingrésalo abajo para confirmar el cambio.';
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
        $id_opcion = (int)($_POST['id_opcion'] ?? 0);
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
    </main>
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

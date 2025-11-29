<?php
session_start();
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$tema = $_SESSION['tema'] ?? 'claro';
$body_class = 'main-layout tema-' . $tema;

$admin_id = $_SESSION['admin_id'] ?? 0;

$mensaje_tema = '';
$mensaje_opciones = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_tipo']) && $_POST['form_tipo'] === 'tema') {
    $nuevo_tema = $_POST['tema'] === 'oscuro' ? 'oscuro' : 'claro';

    $stmt = $pdo->prepare("UPDATE admins SET tema = ? WHERE id = ?");
    $stmt->execute([$nuevo_tema, $admin_id]);

    $_SESSION['tema'] = $nuevo_tema;
    $tema = $nuevo_tema;
    $body_class = 'main-layout tema-' . $tema;
    $mensaje_tema = 'Tema actualizado correctamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_tipo']) && $_POST['form_tipo'] === 'add_opcion') {
    $grupo = trim($_POST['grupo'] ?? '');
    $valor = trim($_POST['valor'] ?? '');

    $grupos_validos = ['afiliado', 'zona', 'genero', 'cargo'];

    if ($grupo === '' || !in_array($grupo, $grupos_validos, true)) {
        $mensaje_opciones = 'Grupo inválido.';
    } elseif ($valor === '') {
        $mensaje_opciones = 'El valor no puede estar vacío.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO opciones_select (grupo, valor, activo) VALUES (?, ?, 1)");
        $stmt->execute([$grupo, $valor]);
        $mensaje_opciones = 'Opción agregada correctamente.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_tipo']) && $_POST['form_tipo'] === 'del_opcion') {
    $id_op = (int)($_POST['id'] ?? 0);
    if ($id_op > 0) {
        $stmt = $pdo->prepare("UPDATE opciones_select SET activo = 0 WHERE id = ?");
        $stmt->execute([$id_op]);
        $mensaje_opciones = 'Opción desactivada correctamente.';
    }
}

function cargarOpcionesGrupo($pdo, $grupo) {
    $stmt = $pdo->prepare("SELECT id, valor, activo FROM opciones_select WHERE grupo = ? ORDER BY valor");
    $stmt->execute([$grupo]);
    return $stmt->fetchAll();
}

$op_afiliado = cargarOpcionesGrupo($pdo, 'afiliado');
$op_zona     = cargarOpcionesGrupo($pdo, 'zona');
$op_genero   = cargarOpcionesGrupo($pdo, 'genero');
$op_cargo    = cargarOpcionesGrupo($pdo, 'cargo');
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
      <h1>Configuración</h1>

      <section class="form-card">
        <h2>Tema</h2>
        <?php if ($mensaje_tema): ?>
          <div class="alert"><?php echo htmlspecialchars($mensaje_tema); ?></div>
        <?php endif; ?>

        <form method="post" action="">
          <input type="hidden" name="form_tipo" value="tema">
          <div class="form-group">
            <label for="tema">Selecciona el tema</label>
            <select name="tema" id="tema">
              <option value="claro"  <?php echo $tema === 'claro' ? 'selected' : ''; ?>>Claro</option>
              <option value="oscuro" <?php echo $tema === 'oscuro' ? 'selected' : ''; ?>>Oscuro</option>
            </select>
          </div>
          <button type="submit" class="btn-primary">Guardar tema</button>
        </form>
      </section>

      <section class="form-card">
        <h2>Opciones para selects (Afiliado, Zona, Género, Cargo)</h2>

        <?php if ($mensaje_opciones): ?>
          <div class="alert"><?php echo htmlspecialchars($mensaje_opciones); ?></div>
        <?php endif; ?>

        <h3>Agregar nueva opción</h3>
        <form method="post" action="" style="margin-bottom: 16px;">
          <input type="hidden" name="form_tipo" value="add_opcion">
          <div class="form-row">
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
          </div>
          <button type="submit" class="btn-primary">Agregar opción</button>
        </form>

        <div class="opciones-grid">
          <div>
            <h3>Afiliado</h3>
            <?php foreach ($op_afiliado as $op): ?>
              <form method="post" action="" class="fila-opcion">
                <span class="<?php echo $op['activo'] ? '' : 'opcion-inactiva'; ?>">
                  <?php echo htmlspecialchars($op['valor']); ?>
                  <?php if (!$op['activo']) echo ' (inactiva)'; ?>
                </span>
                <?php if ($op['activo']): ?>
                  <input type="hidden" name="form_tipo" value="del_opcion">
                  <input type="hidden" name="id" value="<?php echo $op['id']; ?>">
                  <button type="submit" class="link-button">Desactivar</button>
                <?php endif; ?>
              </form>
            <?php endforeach; ?>
          </div>

          <div>
            <h3>Zona</h3>
            <?php foreach ($op_zona as $op): ?>
              <form method="post" action="" class="fila-opcion">
                <span class="<?php echo $op['activo'] ? '' : 'opcion-inactiva'; ?>">
                  <?php echo htmlspecialchars($op['valor']); ?>
                  <?php if (!$op['activo']) echo ' (inactiva)'; ?>
                </span>
                <?php if ($op['activo']): ?>
                  <input type="hidden" name="form_tipo" value="del_opcion">
                  <input type="hidden" name="id" value="<?php echo $op['id']; ?>">
                  <button type="submit" class="link-button">Desactivar</button>
                <?php endif; ?>
              </form>
            <?php endforeach; ?>
          </div>

          <div>
            <h3>Género</h3>
            <?php foreach ($op_genero as $op): ?>
              <form method="post" action="" class="fila-opcion">
                <span class="<?php echo $op['activo'] ? '' : 'opcion-inactiva'; ?>">
                  <?php echo htmlspecialchars($op['valor']); ?>
                  <?php if (!$op['activo']) echo ' (inactiva)'; ?>
                </span>
                <?php if ($op['activo']): ?>
                  <input type="hidden" name="form_tipo" value="del_opcion">
                  <input type="hidden" name="id" value="<?php echo $op['id']; ?>">
                  <button type="submit" class="link-button">Desactivar</button>
                <?php endif; ?>
              </form>
            <?php endforeach; ?>
          </div>

          <div>
            <h3>Cargo</h3>
            <?php foreach ($op_cargo as $op): ?>
              <form method="post" action="" class="fila-opcion">
                <span class="<?php echo $op['activo'] ? '' : 'opcion-inactiva'; ?>">
                  <?php echo htmlspecialchars($op['valor']); ?>
                  <?php if (!$op['activo']) echo ' (inactiva)'; ?>
                </span>
                <?php if ($op['activo']): ?>
                  <input type="hidden" name="form_tipo" value="del_opcion">
                  <input type="hidden" name="id" value="<?php echo $op['id']; ?>">
                  <button type="submit" class="link-button">Desactivar</button>
                <?php endif; ?>
              </form>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </main>
  </div>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>

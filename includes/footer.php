<?php
// includes/footer.php

// Si quieres mostrar el nombre del sistema aquí también:
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nombreSistemaFooter = 'Sistema de Registro';

// Si quieres, puedes cargar también config_sistema aquí (opcional)
try {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/db.php';
    }
    $stmtCfgFooter = $pdo->query("SELECT nombre_sistema FROM config_sistema ORDER BY id ASC LIMIT 1");
    $cfgFooter = $stmtCfgFooter->fetch();
    if ($cfgFooter && !empty($cfgFooter['nombre_sistema'])) {
        $nombreSistemaFooter = $cfgFooter['nombre_sistema'];
    }
} catch (Exception $e) {
    // Si algo falla, simplemente usamos el nombre por defecto
}
?>
<footer class="main-footer">
  <small>&copy; <?php echo date('Y'); ?> · <?php echo htmlspecialchars($nombreSistemaFooter); ?></small>
</footer>

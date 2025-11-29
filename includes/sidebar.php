<?php
$current = basename($_SERVER['PHP_SELF']);
function activeClass($file, $current) {
    return $current === $file ? ' class="active"' : '';
}
?>
<aside class="sidebar">
  <nav>
    <ul>
      <li><a href="dashboard.php"<?php echo activeClass('dashboard.php', $current); ?>>🏠 Home</a></li>
      <li><a href="consultar.php"<?php echo activeClass('consultar.php', $current); ?>>🔎 Consultar</a></li>
      <li><a href="registro.php"<?php echo activeClass('registro.php', $current); ?>>📝 Registro</a></li>
      <li><a href="lista.php"<?php echo activeClass('lista.php', $current); ?>>📋 Lista</a></li>
      <li><a href="exportar.php"<?php echo activeClass('exportar.php', $current); ?>>📄 Exportar</a></li>
      <li><a href="configuracion.php"<?php echo activeClass('configuracion.php', $current); ?>>⚙️ Configuración</a></li>
    </ul>
  </nav>
</aside>

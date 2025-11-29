<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$nombre_admin = $_SESSION['admin_nombre'] ?? 'Administrador';
$cargo_admin  = $_SESSION['admin_cargo']  ?? 'Admin';
?>

<div id="global-loader" class="global-loader hidden">
  <div class="global-loader-backdrop"></div>
  <div class="global-loader-content">
    <div class="spinner"></div>
    <p>Guardando, por favor espera...</p>
  </div>
</div>

<header class="main-header">
  <div class="logo">Sistema de Registro</div>
  <div class="search-bar">
    <form method="get" action="lista.php">
      <input type="text" name="search" placeholder="Buscar..." />
      <button type="submit">🔍</button>
    </form>
  </div>
  <div class="user-info">
    <div class="user-details">
      <span class="user-name"><?php echo htmlspecialchars($nombre_admin); ?></span>
      <span class="user-role"><?php echo htmlspecialchars($cargo_admin); ?></span>
    </div>
    <a href="logout.php" class="logout-link">Cerrar sesión</a>
  </div>
</header>

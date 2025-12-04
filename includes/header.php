<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// $pdo debe venir de la página que incluye este header (dashboard, lista, etc.)
// pero por si acaso:
if (!isset($pdo)) {
    require_once __DIR__ . '/../config/db.php';
}

$tema = $_SESSION['tema'] ?? 'claro';

// Cargar configuración del sistema (nombre + logo)
try {
    $stmtCfg = $pdo->query("SELECT * FROM config_sistema ORDER BY id ASC LIMIT 1");
    $configSistema = $stmtCfg->fetch();
} catch (Exception $e) {
    $configSistema = null;
}

$nombreSistema = $configSistema['nombre_sistema'] ?? 'Sistema de Registro';
$logoSistema   = $configSistema['logo_sistema'] ?? null;

// Datos del admin en sesión
$admin_nombre = $_SESSION['admin_nombre'] ?? 'Administrador';
$admin_cargo  = $_SESSION['admin_cargo'] ?? 'Admin';
$admin_email  = $_SESSION['admin_email'] ?? '';
$foto_perfil  = $_SESSION['foto_perfil'] ?? null;

// Ruta actual para volver tras cambiar tema
$ruta_actual = $_SERVER['REQUEST_URI'] ?? 'dashboard.php';

// Iniciales del admin, por si no hay foto
$iniciales = '';
if ($admin_nombre) {
    $partes = preg_split('/\s+/', trim($admin_nombre));
    if (!empty($partes[0])) {
        $iniciales .= mb_substr($partes[0], 0, 1, 'UTF-8');
    }
    if (!empty($partes[1])) {
        $iniciales .= mb_substr($partes[1], 0, 1, 'UTF-8');
    }
    $iniciales = mb_strtoupper($iniciales, 'UTF-8');
}
?>
<header class="main-header">
  <!-- Botón hamburguesa (solo en móvil, controlado por CSS) -->
  <button class="header-toggle" type="button" aria-label="Mostrar u ocultar menú">
    <svg viewBox="0 0 24 24" class="icon-svg">
      <line x1="4" y1="7" x2="20" y2="7"></line>
      <line x1="4" y1="12" x2="20" y2="12"></line>
      <line x1="4" y1="17" x2="20" y2="17"></line>
    </svg>
  </button>

  <!-- Logo + nombre sistema -->
  <div class="logo">
    <div class="logo-icon">
      <?php if (!empty($logoSistema)): ?>
        <img src="<?php echo htmlspecialchars($logoSistema); ?>" alt="Logo del sistema">
      <?php else: ?>
        <svg viewBox="0 0 24 24" class="icon-svg">
          <rect x="4" y="5" width="16" height="14" rx="3" fill="none"></rect>
          <path d="M7 9h10M7 13h6" fill="none"></path>
        </svg>
      <?php endif; ?>
    </div>
    <span class="logo-text"><?php echo htmlspecialchars($nombreSistema); ?></span>
  </div>

  <!-- Buscador centrado -->
  <div class="header-search">
    <form method="get" action="lista.php">
      <div class="search-input-wrapper">
        <span class="search-icon">
          <svg viewBox="0 0 24 24" class="icon-svg">
            <circle cx="11" cy="11" r="6" fill="none"></circle>
            <line x1="16" y1="16" x2="20" y2="20"></line>
          </svg>
        </span>
        <input
          type="text"
          name="search"
          placeholder="Buscar por nombre o documento..."
          value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
        >
      </div>
    </form>
  </div>

  <!-- Usuario -->
  <div class="header-user">
    <button class="user-avatar-btn" type="button" data-user-menu-toggle>
      <span class="user-avatar">
        <?php if (!empty($foto_perfil)): ?>
          <img src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Foto de perfil">
        <?php elseif ($iniciales): ?>
          <span class="user-avatar-iniciales"><?php echo htmlspecialchars($iniciales); ?></span>
        <?php else: ?>
          <svg viewBox="0 0 24 24" class="icon-svg">
            <circle cx="12" cy="9" r="4" fill="none"></circle>
            <path d="M5 20c0-3.3 3-6 7-6s7 2.7 7 6" fill="none"></path>
          </svg>
        <?php endif; ?>
      </span>
      <span class="user-avatar-caret">
        <svg viewBox="0 0 24 24" class="icon-svg">
          <polyline points="6 9 12 15 18 9" fill="none"></polyline>
        </svg>
      </span>
    </button>

    <div class="user-menu" data-user-menu>
      <div class="user-menu-header">
        <div class="user-menu-avatar">
          <?php if (!empty($foto_perfil)): ?>
            <img src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Foto de perfil">
          <?php else: ?>
            <div class="user-avatar">
              <?php echo $iniciales ?: 'AD'; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="user-menu-info">
          <p class="user-menu-name"><?php echo htmlspecialchars($admin_nombre); ?></p>
          <p class="user-menu-role"><?php echo htmlspecialchars($admin_cargo); ?></p>
          <?php if ($admin_email): ?>
            <p class="user-menu-email"><?php echo htmlspecialchars($admin_email); ?></p>
          <?php endif; ?>
        </div>
      </div>

      <div class="user-menu-body">
        <!-- Cambiar tema (usa cambiar_tema.php y se guarda en BD) -->
        <form method="post" action="cambiar_tema.php">
          <input type="hidden" name="origen" value="<?php echo htmlspecialchars($ruta_actual); ?>">
          <button type="submit" class="user-menu-item">
            <span class="user-menu-item-icon">
              <!-- Sol / luna cambian según el tema -->
              <svg viewBox="0 0 24 24" class="icon-svg icon-theme-sun">
                <circle cx="12" cy="12" r="4" fill="none"></circle>
                <line x1="12" y1="3" x2="12" y2="5"></line>
                <line x1="12" y1="19" x2="12" y2="21"></line>
                <line x1="5" y1="12" x2="3" y2="12"></line>
                <line x1="21" y1="12" x2="19" y2="12"></line>
                <line x1="6.5" y1="6.5" x2="5" y2="5"></line>
                <line x1="18.5" y1="18.5" x2="17" y2="17"></line>
                <line x1="6.5" y1="17.5" x2="5" y2="19"></line>
                <line x1="18.5" y1="5.5" x2="17" y2="7"></line>
              </svg>
              <svg viewBox="0 0 24 24" class="icon-svg icon-theme-moon">
                <path d="M20 14.5A8.5 8.5 0 0 1 11.5 6 6.5 6.5 0 0 0 12 19a6.5 6.5 0 0 0 8-4.5z" fill="none"></path>
              </svg>
            </span>
            <span>
              Cambiar a tema <?php echo $tema === 'oscuro' ? 'claro' : 'oscuro'; ?>
            </span>
          </button>
        </form>

        <a href="configuracion.php" class="user-menu-item">
          <span class="user-menu-item-icon">
            <svg viewBox="0 0 24 24" class="icon-svg">
              <circle cx="12" cy="12" r="3" fill="none"></circle>
              <path d="M19.4 15a1.8 1.8 0 0 0 .36 2l.06.06a1.5 1.5 0 0 1 0 2.12l-0.86.86a1.5 1.5 0 0 1-2.12 0l-.06-.06a1.8 1.8 0 0 0-2-.36 1.8 1.8 0 0 0-1.06 1.64V22a1.5 1.5 0 0 1-1.5 1.5H11a1.5 1.5 0 0 1-1.5-1.5v-.12a1.8 1.8 0 0 0-1.06-1.64 1.8 1.8 0 0 0-2 .36l-.06.06a1.5 1.5 0 0 1-2.12 0L3.4 19.2a1.5 1.5 0 0 1 0-2.12l.06-.06a1.8 1.8 0 0 0 .36-2A1.8 1.8 0 0 0 2.2 14H2a1.5 1.5 0 0 1-1.5-1.5V11A1.5 1.5 0 0 1 2 9.5h.12a1.8 1.8 0 0 0 1.64-1.06 1.8 1.8 0 0 0-.36-2l-.06-.06a1.5 1.5 0 0 1 0-2.12L4.2 3.4a1.5 1.5 0 0 1 2.12 0l.06.06a1.8 1.8 0 0 0 2 .36H8.5A1.8 1.8 0 0 0 9.5 2V2a1.5 1.5 0 0 1 1.5-1.5h1A1.5 1.5 0 0 1 13.5 2v.12a1.8 1.8 0 0 0 1.06 1.64 1.8 1.8 0 0 0 2-.36l.06-.06a1.5 1.5 0 0 1 2.12 0l.86.86a1.5 1.5 0 0 1 0 2.12l-.06.06a1.8 1.8 0 0 0-.36 2 1.8 1.8 0 0 0 1.64 1.06H22A1.5 1.5 0 0 1 23.5 11v1.5A1.5 1.5 0 0 1 22 14h-.12a1.8 1.8 0 0 0-2.48 1z" fill="none"></path>
            </svg>
          </span>
          <span>Configuración</span>
        </a>
      </div>

      <div class="user-menu-footer">
        <a href="logout.php" class="user-menu-item user-menu-logout">
          <span class="user-menu-item-icon">
            <svg viewBox="0 0 24 24" class="icon-svg">
              <path d="M10 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h5" fill="none"></path>
              <polyline points="17 16 21 12 17 8" fill="none"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
          </span>
          <span>Cerrar sesión</span>
        </a>
      </div>
    </div>
  </div>
</header>

<!-- JS de layout (hamburguesa + menú usuario) -->
<script src="../assets/js/layout.js"></script>

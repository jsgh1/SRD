<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nombre_admin = $_SESSION['admin_nombre'] ?? 'Administrador';
$cargo_admin  = $_SESSION['admin_cargo']  ?? 'Admin';
$email_admin  = $_SESSION['admin_email']  ?? '';
$foto_perfil  = $_SESSION['foto_perfil']  ?? null;

// Iniciales para el avatar si no hay foto
$iniciales = '';
if ($nombre_admin) {
    $partes = preg_split('/\s+/', trim($nombre_admin));
    $iniciales = mb_substr($partes[0], 0, 1);
    if (isset($partes[1])) {
        $iniciales .= mb_substr($partes[1], 0, 1);
    }
    $iniciales = mb_strtoupper($iniciales);
}
?>

<!-- Loader global -->
<div id="global-loader" class="global-loader hidden">
  <div class="global-loader-backdrop"></div>
  <div class="global-loader-content">
    <div class="spinner"></div>
    <p>Guardando, por favor espera...</p>
  </div>
</div>

<header class="main-header">
  <!-- Botón hamburguesa (móvil) -->
  <button class="header-toggle" type="button" aria-label="Abrir menú">
    <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
      <rect x="3" y="5" width="18" height="2" rx="1"></rect>
      <rect x="3" y="11" width="18" height="2" rx="1"></rect>
      <rect x="3" y="17" width="18" height="2" rx="1"></rect>
    </svg>
  </button>

  <!-- Logo -->
  <div class="logo">
    <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg logo-icon">
      <rect x="3" y="4" width="18" height="16" rx="3" ry="3"></rect>
      <rect x="6" y="8" width="6" height="2" rx="1"></rect>
      <rect x="6" y="12" width="12" height="2" rx="1"></rect>
    </svg>
    <span class="logo-text">Sistema de Registro</span>
  </div>

  <!-- Buscador -->
  <div class="header-search">
    <form method="get" action="lista.php">
      <div class="search-input-wrapper">
        <span class="search-icon">
          <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
            <circle cx="11" cy="11" r="6"></circle>
            <line x1="16" y1="16" x2="20" y2="20" stroke-width="2" stroke-linecap="round"></line>
          </svg>
        </span>
        <input
          type="text"
          name="search"
          placeholder="Buscar por nombre o documento..."
          autocomplete="off"
        >
      </div>
    </form>
  </div>

  <!-- Usuario -->
  <div class="header-user">
    <button
      class="user-avatar-btn"
      type="button"
      aria-haspopup="true"
      aria-expanded="false"
    >
      <?php if ($foto_perfil): ?>
        <span class="user-avatar">
          <img src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Foto de perfil">
        </span>
      <?php else: ?>
        <span class="user-avatar user-avatar-iniciales">
          <?php echo htmlspecialchars($iniciales); ?>
        </span>
      <?php endif; ?>
      <span class="user-avatar-caret">
        <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
          <polyline points="6 9 12 15 18 9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"></polyline>
        </svg>
      </span>
    </button>

    <div class="user-menu" role="menu">
      <div class="user-menu-header">
        <div class="user-menu-avatar">
          <?php if ($foto_perfil): ?>
            <img src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Foto de perfil">
          <?php else: ?>
            <span class="user-avatar user-avatar-iniciales">
              <?php echo htmlspecialchars($iniciales); ?>
            </span>
          <?php endif; ?>
        </div>
        <div class="user-menu-info">
          <p class="user-menu-name"><?php echo htmlspecialchars($nombre_admin); ?></p>
          <p class="user-menu-role"><?php echo htmlspecialchars($cargo_admin); ?></p>
          <?php if ($email_admin): ?>
            <p class="user-menu-email"><?php echo htmlspecialchars($email_admin); ?></p>
          <?php endif; ?>
        </div>
      </div>

      <div class="user-menu-body">
        <button type="button" class="user-menu-item" data-open-config="perfil">
          <span class="user-menu-item-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
              <circle cx="12" cy="9" r="3"></circle>
              <path d="M5 19c0-3 3-5 7-5s7 2 7 5"></path>
            </svg>
          </span>
          <span>Editar perfil</span>
        </button>

        <button type="button" class="user-menu-item" data-toggle-theme>
          <span class="user-menu-item-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
              <circle cx="12" cy="12" r="4"></circle>
              <line x1="12" y1="3" x2="12" y2="5"></line>
              <line x1="12" y1="19" x2="12" y2="21"></line>
              <line x1="5" y1="12" x2="3" y2="12"></line>
              <line x1="21" y1="12" x2="19" y2="12"></line>
              <line x1="17" y1="7" x2="18.5" y2="5.5"></line>
              <line x1="7" y1="17" x2="5.5" y2="18.5"></line>
              <line x1="7" y1="7" x2="5.5" y2="5.5"></line>
              <line x1="17" y1="17" x2="18.5" y2="18.5"></line>
            </svg>
          </span>
          <span>Cambiar tema</span>
        </button>
      </div>

      <div class="user-menu-footer">
        <a href="logout.php" class="user-menu-item user-menu-logout">
          <span class="user-menu-item-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
              <path d="M10 17l-1.5 0c-1.4 0-2.5-1.1-2.5-2.5v-5C6 8.1 7.1 7 8.5 7L10 7"></path>
              <polyline points="14 8 18 12 14 16"></polyline>
              <line x1="18" y1="12" x2="9" y2="12"></line>
            </svg>
          </span>
          <span>Cerrar sesión</span>
        </a>
      </div>
    </div>
  </div>
</header>

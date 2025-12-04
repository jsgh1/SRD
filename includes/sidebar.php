<?php
// includes/sidebar.php
$actual = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <nav class="sidebar-nav">
    <ul>
      <!-- Dashboard / Inicio -->
      <li>
        <a href="dashboard.php"
           class="sidebar-link<?php echo $actual === 'dashboard.php' ? ' active' : ''; ?>">
          <span class="sidebar-icon">
            <svg viewBox="0 0 24 24" class="icon-svg">
              <rect x="3" y="3" width="8" height="8" rx="2"></rect>
              <rect x="13" y="3" width="8" height="5" rx="2"></rect>
              <rect x="13" y="10" width="8" height="11" rx="2"></rect>
              <rect x="3" y="13" width="8" height="8" rx="2"></rect>
            </svg>
          </span>
          <!-- Aquí puedes poner el nombre que tú quieras -->
          <span class="sidebar-link-label">Dashboard</span>
        </a>
      </li>

      <!-- Nuevo registro -->
      <li>
        <a href="registro.php"
           class="sidebar-link<?php echo $actual === 'registro.php' ? ' active' : ''; ?>">
          <span class="sidebar-icon">
            <svg viewBox="0 0 24 24" class="icon-svg">
              <path d="M5 4h10l4 4v12H5z" fill="none"></path>
              <path d="M9 12h6" fill="none"></path>
              <path d="M9 16h3" fill="none"></path>
            </svg>
          </span>
          <span class="sidebar-link-label">Registro</span>
          <!-- Cambia el texto si quieres otro nombre -->
        </a>
      </li>

      <!-- Lista de registros -->
      <li>
        <a href="lista.php"
           class="sidebar-link<?php echo $actual === 'lista.php' ? ' active' : ''; ?>">
          <span class="sidebar-icon">
            <svg viewBox="0 0 24 24" class="icon-svg">
              <rect x="4" y="5" width="16" height="4" rx="1"></rect>
              <rect x="4" y="11" width="16" height="4" rx="1"></rect>
              <rect x="4" y="17" width="10" height="4" rx="1"></rect>
            </svg>
          </span>
          <span class="sidebar-link-label">Lista</span>
        </a>
      </li>

      <!-- Consultar -->
      <li>
        <a href="consultar.php"
           class="sidebar-link<?php echo $actual === 'consultar.php' ? ' active' : ''; ?>">
          <span class="sidebar-icon">
            <svg viewBox="0 0 24 24" class="icon-svg">
              <circle cx="11" cy="11" r="4" fill="none"></circle>
              <line x1="15" y1="15" x2="19" y2="19"></line>
            </svg>
          </span>
          <span class="sidebar-link-label">Consultar</span>
        </a>
      </li>

      <!-- 🔙 Exportar (restaurado) -->
      <li>
        <a href="exportar.php"
           class="sidebar-link<?php echo $actual === 'exportar.php' ? ' active' : ''; ?>">
          <span class="sidebar-icon">
            <svg viewBox="0 0 24 24" class="icon-svg">
              <path d="M12 3v12" fill="none"></path>
              <polyline points="8 11 12 15 16 11" fill="none"></polyline>
              <rect x="4" y="15" width="16" height="4" rx="2"></rect>
            </svg>
          </span>
          <span class="sidebar-link-label">Exportar</span>
        </a>
      </li>

      <!-- Configuración -->
      <li>
        <a href="configuracion.php"
           class="sidebar-link<?php echo $actual === 'configuracion.php' ? ' active' : ''; ?>">
          <span class="sidebar-icon">
            <svg viewBox="0 0 24 24" class="icon-svg">
              <circle cx="12" cy="12" r="3" fill="none"></circle>
              <path d="M19 12a7 7 0 0 0-.2-1.6l2-1.4-2-3.4-2.4.8A7 7 0 0 0 12 5L11 3H9L8 5a7 7 0 0 0-2.4.8L3.6 5.6 1.6 9l2 1.4A7 7 0 0 0 3.5 12a7 7 0 0 0 .1 1.6l-2 1.4 2 3.4 2.4-.8A7 7 0 0 0 12 19l1 2h2l1-2a7 7 0 0 0 2.4-.8l2.4.8 2-3.4-2-1.4A7 7 0 0 0 19 12z" fill="none"></path>
            </svg>
          </span>
          <span class="sidebar-link-label">Configuración</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>

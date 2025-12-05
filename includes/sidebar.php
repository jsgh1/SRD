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
              <path d="M19.4 15a1.8 1.8 0 0 0 .36 2l.06.06a1.5 1.5 0 0 1 0 2.12l-0.86.86a1.5 1.5 0 0 1-2.12 0l-.06-.06a1.8 1.8 0 0 0-2-.36 1.8 1.8 0 0 0-1.06 1.64V22a1.5 1.5 0 0 1-1.5 1.5H11a1.5 1.5 0 0 1-1.5-1.5v-.12a1.8 1.8 0 0 0-1.06-1.64 1.8 1.8 0 0 0-2 .36l-.06.06a1.5 1.5 0 0 1-2.12 0L3.4 19.2a1.5 1.5 0 0 1 0-2.12l.06-.06a1.8 1.8 0 0 0 .36-2A1.8 1.8 0 0 0 2.2 14H2a1.5 1.5 0 0 1-1.5-1.5V11A1.5 1.5 0 0 1 2 9.5h.12a1.8 1.8 0 0 0 1.64-1.06 1.8 1.8 0 0 0-.36-2l-.06-.06a1.5 1.5 0 0 1 0-2.12L4.2 3.4a1.5 1.5 0 0 1 2.12 0l.06.06a1.8 1.8 0 0 0 2 .36H8.5A1.8 1.8 0 0 0 9.5 2V2a1.5 1.5 0 0 1 1.5-1.5h1A1.5 1.5 0 0 1 13.5 2v.12a1.8 1.8 0 0 0 1.06 1.64 1.8 1.8 0 0 0 2-.36l.06-.06a1.5 1.5 0 0 1 2.12 0l.86.86a1.5 1.5 0 0 1 0 2.12l-.06.06a1.8 1.8 0 0 0-.36 2 1.8 1.8 0 0 0 1.64 1.06H22A1.5 1.5 0 0 1 23.5 11v1.5A1.5 1.5 0 0 1 22 14h-.12a1.8 1.8 0 0 0-2.48 1z" fill="none"></path>
            </svg>
          </span>
          <span class="sidebar-link-label">Configuración</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>

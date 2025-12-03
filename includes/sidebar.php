<?php
$current = basename($_SERVER['PHP_SELF']);

function activeClass($file, $current) {
    return $current === $file ? ' active' : '';
}
?>
<aside class="sidebar">
  <nav class="sidebar-nav">
    <ul>
      <li>
        <a href="dashboard.php" class="sidebar-link<?php echo activeClass('dashboard.php', $current); ?>">
          <span class="sidebar-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
              <rect x="3" y="3" width="8" height="8" rx="2"></rect>
              <rect x="13" y="3" width="8" height="5" rx="2"></rect>
              <rect x="3" y="13" width="5" height="8" rx="2"></rect>
              <rect x="10" y="13" width="11" height="8" rx="2"></rect>
            </svg>
          </span>
          <span class="sidebar-text">Home</span>
        </a>
      </li>
      <li>
        <a href="consultar.php" class="sidebar-link<?php echo activeClass('consultar.php', $current); ?>">
          <span class="sidebar-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
              <circle cx="11" cy="11" r="5"></circle>
              <line x1="15" y1="15" x2="20" y2="20" stroke-width="2" stroke-linecap="round"></line>
            </svg>
          </span>
          <span class="sidebar-text">Consultar</span>
        </a>
      </li>
      <li>
        <a href="registro.php" class="sidebar-link<?php echo activeClass('registro.php', $current); ?>">
          <span class="sidebar-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
              <rect x="4" y="4" width="16" height="16" rx="2"></rect>
              <line x1="8" y1="9" x2="16" y2="9"></line>
              <line x1="8" y1="13" x2="13" y2="13"></line>
            </svg>
          </span>
          <span class="sidebar-text">Registro</span>
        </a>
      </li>
      <li>
        <a href="lista.php" class="sidebar-link<?php echo activeClass('lista.php', $current); ?>">
          <span class="sidebar-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
              <rect x="4" y="5" width="16" height="2" rx="1"></rect>
              <rect x="4" y="11" width="16" height="2" rx="1"></rect>
              <rect x="4" y="17" width="16" height="2" rx="1"></rect>
            </svg>
          </span>
          <span class="sidebar-text">Lista</span>
        </a>
      </li>
      <li>
        <a href="exportar.php" class="sidebar-link<?php echo activeClass('exportar.php', $current); ?>">
          <span class="sidebar-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
              <rect x="5" y="3" width="14" height="18" rx="2"></rect>
              <polyline points="9 9 12 12 15 9"></polyline>
              <line x1="12" y1="12" x2="12" y2="6"></line>
            </svg>
          </span>
          <span class="sidebar-text">Exportar</span>
        </a>
      </li>
      <li>
        <a href="configuracion.php" class="sidebar-link<?php echo activeClass('configuracion.php', $current); ?>">
          <span class="sidebar-icon">
            <svg viewBox="0 0 24 24" aria-hidden="true" class="icon-svg">
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M19 13.5l1.2-.7a1 1 0 0 0 .4-1.3l-1-1.8a1 1 0 0 0-1.2-.4l-1.3.5a6 6 0 0 0-1.3-.7l-.2-1.4A1 1 0 0 0 14.5 6h-2a1 1 0 0 0-1 .9L11.3 8a6 6 0 0 0-1.3.7l-1.3-.5a1 1 0 0 0-1.2.4l-1 1.8a1 1 0 0 0 .4 1.3l1.2.7v1.1l-1.2.7a1 1 0 0 0-.4 1.3l1 1.8a1 1 0 0 0 1.2.4l1.3-.5a6 6 0 0 0 1.3.7l.2 1.4a1 1 0 0 0 1 .9h2a1 1 0 0 0 1-.9l.2-1.4a6 6 0 0 0 1.3-.7l1.3.5a1 1 0 0 0 1.2-.4l1-1.8a1 1 0 0 0-.4-1.3l-1.2-.7z"></path>
            </svg>
          </span>
          <span class="sidebar-text">Configuración</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>

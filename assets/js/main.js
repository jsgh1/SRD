// Loader global
function showGlobalLoader() {
  const loader = document.getElementById('global-loader');
  if (loader) {
    loader.classList.remove('hidden');
  }
}

function hideGlobalLoader() {
  const loader = document.getElementById('global-loader');
  if (loader) {
    loader.classList.add('hidden');
  }
}

document.addEventListener('DOMContentLoaded', function () {
  // Formularios que muestran loader al enviar
  const forms = document.querySelectorAll('form.show-loader-on-submit');
  forms.forEach(form => {
    form.addEventListener('submit', function () {
      showGlobalLoader();
    });
  });

  // Toggle de sidebar (móvil)
  const toggleBtn = document.querySelector('.header-toggle');
  const sidebar = document.querySelector('.sidebar');
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', () => {
      document.body.classList.toggle('sidebar-open');
    });
  }

  // Cerrar sidebar al hacer click en contenido en móvil
  const content = document.querySelector('.content');
  if (content) {
    content.addEventListener('click', () => {
      if (window.innerWidth <= 900 && document.body.classList.contains('sidebar-open')) {
        document.body.classList.remove('sidebar-open');
      }
    });
  }

  // Cerrar sidebar si se redimensiona a escritorio
  window.addEventListener('resize', () => {
    if (window.innerWidth > 900) {
      document.body.classList.remove('sidebar-open');
    }
  });

  // Menú de usuario
  const avatarBtn = document.querySelector('.user-avatar-btn');
  const userMenu = document.querySelector('.user-menu');

  if (avatarBtn && userMenu) {
    avatarBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const expanded = avatarBtn.getAttribute('aria-expanded') === 'true';
      avatarBtn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      userMenu.classList.toggle('open', !expanded);
    });

    document.addEventListener('click', () => {
      avatarBtn.setAttribute('aria-expanded', 'false');
      userMenu.classList.remove('open');
    });

    userMenu.addEventListener('click', (e) => {
      e.stopPropagation();
    });
  }

  // Botón "Cambiar tema" en el menú usuario (solamente cambia clase en body; persistencia la maneja PHP)
  const themeBtn = document.querySelector('[data-toggle-theme]');
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      const body = document.body;
      if (body.classList.contains('tema-oscuro')) {
        body.classList.remove('tema-oscuro');
        body.classList.add('tema-claro');
      } else {
        body.classList.remove('tema-claro');
        body.classList.add('tema-oscuro');
      }
      // Aquí solo cambiamos visualmente; el guardado definitivo de tema sigue en configuracion.php
    });
  }

  // Ir a sección de perfil desde menú usuario
  const perfilBtn = document.querySelector('[data-open-config="perfil"]');
  if (perfilBtn) {
    perfilBtn.addEventListener('click', () => {
      window.location.href = 'configuracion.php#perfil-admin';
    });
  }
});

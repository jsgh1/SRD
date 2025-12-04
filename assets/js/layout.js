// assets/js/layout.js
document.addEventListener('DOMContentLoaded', function () {
  // Toggle sidebar en móvil
  const burger = document.querySelector('.header-toggle');
  if (burger) {
    burger.addEventListener('click', () => {
      document.body.classList.toggle('sidebar-open');
    });
  }

  // Menú de usuario
  const userBtn = document.querySelector('[data-user-menu-toggle]');
  const userMenu = document.querySelector('[data-user-menu]');
  if (userBtn && userMenu) {
    userBtn.addEventListener('click', () => {
      userMenu.classList.toggle('open');
    });

    document.addEventListener('click', (e) => {
      if (!userMenu.contains(e.target) && !userBtn.contains(e.target)) {
        userMenu.classList.remove('open');
      }
    });
  }
});

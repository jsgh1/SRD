document.addEventListener('DOMContentLoaded', function () {
  const toggleButtons = document.querySelectorAll('[data-password-toggle]');

  toggleButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (!input) return;

      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      btn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
      btn.classList.toggle('is-visible', isPassword);
    });
  });
});

// Efecto 3D en las tarjetas del panel izquierdo (login y código)
document.addEventListener('DOMContentLoaded', function () {
  const wrappers = document.querySelectorAll('.login-hero-3d-wrapper');

  wrappers.forEach(wrapper => {
    const card = wrapper.querySelector('.login-hero-3d-card');
    if (!card) return;

    const maxTilt = 12; // grados
    const maxTranslateZ = 16; // px

    function resetTilt() {
      card.style.transform = 'rotateX(6deg) rotateY(-10deg) translateZ(0px)';
      card.style.boxShadow = '0 20px 40px rgba(15, 23, 42, 0.55)';
    }

    wrapper.addEventListener('mousemove', (e) => {
      const rect = wrapper.getBoundingClientRect();
      const xRel = (e.clientX - rect.left) / rect.width - 0.5; // -0.5 a 0.5
      const yRel = (e.clientY - rect.top) / rect.height - 0.5;

      const tiltX = (-yRel) * maxTilt; // girar respecto Y
      const tiltY = xRel * maxTilt;    // girar respecto X

      card.style.transform =
        `rotateX(${tiltX}deg) rotateY(${tiltY}deg) translateZ(${maxTranslateZ}px)`;
      card.style.boxShadow =
        '0 26px 60px rgba(15, 23, 42, 0.7)';
    });

    wrapper.addEventListener('mouseleave', () => {
      resetTilt();
    });

    // Estado inicial
    resetTilt();
  });
});

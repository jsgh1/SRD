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
  const forms = document.querySelectorAll('form.show-loader-on-submit');

  forms.forEach(form => {
    form.addEventListener('submit', function () {
      
      showGlobalLoader();
    });
  });
});

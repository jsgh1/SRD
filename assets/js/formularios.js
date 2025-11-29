document.addEventListener('DOMContentLoaded', function () {
  // Aplica validación a formulario de registro / edición si existe
  const formRegistro = document.getElementById('form-registro');
  if (formRegistro) {
    inicializarValidacionFormulario(formRegistro);
  }

  const formEditar = document.getElementById('form-editar');
  if (formEditar) {
    inicializarValidacionFormulario(formEditar);
  }
});

function inicializarValidacionFormulario(form) {
  const estadoSelect = form.querySelector('#estado_registro');
  if (!estadoSelect) return;

  // Campos que serán obligatorios cuando el estado sea "Completado"
  const camposRequeridosIds = [
    'tipo_documento',
    'numero_documento',
    'nombres',
    'apellidos',
    'afiliado',
    'zona',
    'genero',
    'fecha_nacimiento',
    'telefono',
    'cargo',
    'nombre_predio',
    'correo_electronico',
  ];

  const camposRequeridos = camposRequeridosIds
    .map(id => form.querySelector('#' + id))
    .filter(el => el !== null);

  // Quitar marcas de error al escribir/cambiar
  camposRequeridos.forEach(campo => {
    campo.addEventListener('input', () => quitarErrorCampo(campo));
    campo.addEventListener('change', () => quitarErrorCampo(campo));
  });

  // Cada vez que cambie el estado, revisamos si falta algo (modo "pre-chequeo")
  estadoSelect.addEventListener('change', () => {
    if (estadoSelect.value === 'Completado') {
      validarCampos(form, camposRequeridos, true); // solo mostrar, no bloquear
    } else {
      limpiarErrores(form, camposRequeridos);
    }
  });

  // ✅ Validación real al enviar (AQUÍ ESTÁ EL CAMBIO)
  form.addEventListener('submit', function (e) {
    if (estadoSelect.value === 'Completado') {
      const valido = validarCampos(form, camposRequeridos, false); // bloquear si falla
      if (!valido) {
        e.preventDefault();

        // Si existe la función hideGlobalLoader (definida en main.js), la usamos
        if (typeof hideGlobalLoader === 'function') {
          hideGlobalLoader();
        }
      }
    } else {
      // Si es Pendiente, solo limpiamos errores visuales
      limpiarErrores(form, camposRequeridos);
    }
  });
}

function validarCampos(form, campos, soloMarcar) {
  let faltan = [];

  campos.forEach(campo => {
    const valor = (campo.value || '').trim();
    if (valor === '') {
      marcarErrorCampo(campo);
      faltan.push(obtenerNombreCampo(form, campo));
    } else {
      quitarErrorCampo(campo);
    }
  });

  if (faltan.length > 0) {
    mostrarMensajeError(form, faltan, soloMarcar);
    return soloMarcar; // si soloMarcar = true -> no bloquea, solo muestra
  } else {
    quitarMensajeError(form);
    return true;
  }
}

function marcarErrorCampo(campo) {
  campo.classList.add('input-error');
}

function quitarErrorCampo(campo) {
  campo.classList.remove('input-error');
}

function limpiarErrores(form, campos) {
  campos.forEach(campo => quitarErrorCampo(campo));
  quitarMensajeError(form);
}

function obtenerNombreCampo(form, campo) {
  const label = form.querySelector('label[for="' + campo.id + '"]');
  if (label) {
    return label.textContent.replace('*', '').trim();
  }
  return campo.name || campo.id;
}

function mostrarMensajeError(form, faltan, soloMarcar) {
  quitarMensajeError(form);

  const contenedor = document.createElement('div');
  contenedor.className = 'alert alert-error alert-js';
  const texto = soloMarcar
    ? 'Hay campos vacíos que serán obligatorios si el estado es "Completado":'
    : 'No puedes guardar como "Completado" porque faltan campos obligatorios:';

  const lista = faltan.map(f => '<li>' + f + '</li>').join('');

  contenedor.innerHTML = `
    <p>${texto}</p>
    <ul>${lista}</ul>
  `;

  // Insertar al inicio del formulario
  form.insertBefore(contenedor, form.firstChild);
}

function quitarMensajeError(form) {
  const alerta = form.querySelector('.alert-js');
  if (alerta) {
    alerta.remove();
  }
}

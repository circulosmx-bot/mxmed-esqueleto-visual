(function () {
  'use strict';

  var form = document.getElementById('cancelForm');
  var tokenInput = document.getElementById('tokenInput');
  var reasonInput = document.getElementById('reasonInput');
  var cancelBtn = document.getElementById('cancelBtn');
  var alertBox = document.getElementById('alertBox');

  if (!form || !tokenInput || !cancelBtn || !alertBox) {
    return;
  }

  var query = new URLSearchParams(window.location.search);
  var tokenFromQuery = String(query.get('token') || '').trim();
  if (tokenFromQuery !== '') {
    tokenInput.value = tokenFromQuery;
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    submitCancel();
  });

  function resolveApiBase() {
    var loc = window.location;
    var host = loc.hostname;
    var port = loc.port;

    if ((host === '127.0.0.1' || host === 'localhost') && (port === '' || port === '80' || port === '443')) {
      return loc.protocol + '//' + host + ':8090';
    }

    return loc.origin;
  }

  async function submitCancel() {
    var token = String(tokenInput.value || '').trim();
    var reason = String(reasonInput.value || '').trim();

    if (token === '') {
      showAlert('Ingresa un cancel token.', 'danger');
      return;
    }

    cancelBtn.disabled = true;
    cancelBtn.textContent = 'Cancelando...';
    hideAlert();

    try {
      var response = await fetch(resolveApiBase() + '/api/agenda/index.php/public/appointments/cancel', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        body: JSON.stringify({
          cancel_token: token,
          reason: reason
        })
      });

      var payload = null;
      try {
        payload = await response.json();
      } catch (err) {
        payload = null;
      }

      if (!response.ok || !payload) {
        showAlert('Error de red al cancelar.', 'danger');
        return;
      }

      if (payload.ok === true) {
        if (payload.message === 'already_canceled') {
          showAlert('Esta cita ya estaba cancelada.', 'success');
        } else {
          showAlert('Tu cita fue cancelada correctamente.', 'success');
        }
        return;
      }

      if (payload.error === 'invalid_token') {
        showAlert('Token invalido.', 'warning');
      } else if (payload.error === 'not_cancelable') {
        showAlert('La cita no puede cancelarse en su estado actual.', 'warning');
      } else if (payload.error === 'validation_error') {
        showAlert('Datos invalidos.', 'warning');
      } else {
        showAlert(payload.message || 'No se pudo cancelar la cita.', 'danger');
      }
    } catch (err) {
      showAlert('Error de red al cancelar.', 'danger');
    } finally {
      cancelBtn.disabled = false;
      cancelBtn.textContent = 'Cancelar cita';
    }
  }

  function showAlert(message, type) {
    alertBox.className = 'alert alert-' + (type || 'danger');
    alertBox.textContent = message;
    alertBox.classList.remove('d-none');
  }

  function hideAlert() {
    alertBox.classList.add('d-none');
    alertBox.textContent = '';
  }
})();

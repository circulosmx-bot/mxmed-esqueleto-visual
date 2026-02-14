(function () {
  'use strict';

  var query = new URLSearchParams(window.location.search);

  var elements = {
    summary: document.getElementById('summary'),
    errorAlert: document.getElementById('errorAlert'),
    toggleModeBtn: document.getElementById('toggleModeBtn'),
    weekControls: document.getElementById('weekControls'),
    weekLabel: document.getElementById('weekLabel'),
    prevWeekBtn: document.getElementById('prevWeekBtn'),
    nextWeekBtn: document.getElementById('nextWeekBtn'),
    loadingState: document.getElementById('loadingState'),
    emptyState: document.getElementById('emptyState'),
    daysContainer: document.getElementById('daysContainer'),
    slotModal: document.getElementById('slotModal'),
    modalDateTime: document.getElementById('modalDateTime'),
    modalDoctorId: document.getElementById('modalDoctorId'),
    modalConsultorioId: document.getElementById('modalConsultorioId'),
    modalAlert: document.getElementById('modalAlert'),
    appointmentRequestForm: document.getElementById('appointmentRequestForm'),
    patientNameInput: document.getElementById('patientNameInput'),
    patientPhoneInput: document.getElementById('patientPhoneInput'),
    patientEmailInput: document.getElementById('patientEmailInput'),
    sendOtpBtn: document.getElementById('sendOtpBtn'),
    otpStep: document.getElementById('otpStep'),
    otpInput: document.getElementById('otpInput'),
    otpDebugHint: document.getElementById('otpDebugHint'),
    confirmOtpBtn: document.getElementById('confirmOtpBtn'),
    successStep: document.getElementById('successStep'),
    successAppointmentId: document.getElementById('successAppointmentId')
  };

  if (!elements.daysContainer || !elements.toggleModeBtn) {
    return;
  }

  var state = {
    doctorId: readDoctorId(query.get('doctor_id')),
    consultorioId: readOptional(query.get('consultorio_id')),
    limitPerDay: readLimitPerDay(query.get('limit_per_day')),
    mode: 'next',
    days: 3,
    weekOffset: 0,
    lastMeta: null,
    selectedSlot: null,
    otpContext: null
  };

  bindEvents();
  loadAvailability();

  function bindEvents() {
    elements.toggleModeBtn.addEventListener('click', function () {
      if (state.mode === 'next') {
        state.mode = 'week';
        state.weekOffset = 0;
      } else {
        state.mode = 'next';
      }
      loadAvailability();
    });

    elements.prevWeekBtn.addEventListener('click', function () {
      if (state.weekOffset <= 0) {
        return;
      }
      state.weekOffset -= 1;
      loadAvailability();
    });

    elements.nextWeekBtn.addEventListener('click', function () {
      if (state.weekOffset >= 3) {
        return;
      }
      state.weekOffset += 1;
      loadAvailability();
    });

    elements.daysContainer.addEventListener('click', function (event) {
      var btn = event.target.closest('button[data-start-at]');
      if (!btn) {
        return;
      }
      openSlotModal({
        date: btn.getAttribute('data-date') || '',
        startAt: btn.getAttribute('data-start-at') || '',
        endAt: btn.getAttribute('data-end-at') || ''
      });
    });

    elements.appointmentRequestForm.addEventListener('submit', function (event) {
      event.preventDefault();
      requestOtp();
    });

    elements.confirmOtpBtn.addEventListener('click', function () {
      verifyOtp();
    });

    elements.slotModal.addEventListener('hidden.bs.modal', function () {
      state.selectedSlot = null;
      state.otpContext = null;
      resetModalFlow();
    });
  }

  function readDoctorId(value) {
    var v = String(value || '').trim();
    return v === '' ? '1' : v;
  }

  function readOptional(value) {
    var v = String(value || '').trim();
    return v === '' ? null : v;
  }

  function readLimitPerDay(value) {
    if (value === null || value === '') {
      return 12;
    }
    var n = Number(value);
    if (!Number.isInteger(n)) {
      return 12;
    }
    if (n < 0) {
      return 12;
    }
    if (n > 200) {
      return 200;
    }
    return n;
  }

  function resolveApiBase() {
    var loc = window.location;
    var host = loc.hostname;
    var port = loc.port;

    if ((host === '127.0.0.1' || host === 'localhost') && (port === '' || port === '80' || port === '443')) {
      return loc.protocol + '//' + host + ':8090';
    }

    return loc.origin;
  }

  function buildAvailabilityUrl() {
    var params = new URLSearchParams();
    params.set('doctor_id', state.doctorId);

    if (state.consultorioId) {
      params.set('consultorio_id', state.consultorioId);
    }

    params.set('mode', state.mode);
    params.set('limit_per_day', String(state.limitPerDay));

    if (state.mode === 'next') {
      params.set('days', String(state.days));
    } else {
      params.set('week_offset', String(state.weekOffset));
    }

    return resolveApiBase() + '/api/agenda/index.php/public/availability?' + params.toString();
  }

  async function loadAvailability() {
    setLoading(true);
    hideError();
    updateControls();

    try {
      var response = await fetch(buildAvailabilityUrl(), {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
      });

      var payload = await parseJsonSafe(response);
      if (!response.ok || !payload || payload.ok !== true) {
        throw new Error(readErrorMessage(payload, response.status));
      }

      state.lastMeta = payload.meta || {};
      renderAvailability(payload.data || {});
    } catch (err) {
      renderError(err && err.message ? err.message : 'Error de red');
      renderDays([]);
    } finally {
      setLoading(false);
    }
  }

  function renderAvailability(data) {
    var days = Array.isArray(data.days) ? data.days : [];
    renderSummary();
    renderDays(days);
  }

  function renderSummary() {
    var consultorio = state.lastMeta && state.lastMeta.consultorio_id_used ? state.lastMeta.consultorio_id_used : 'N/A';
    var modeLabel = state.mode === 'week' ? 'Semanal' : 'Proximos dias';
    elements.summary.textContent = 'Doctor: ' + state.doctorId + ' | Consultorio: ' + consultorio + ' | Vista: ' + modeLabel;
  }

  function renderDays(days) {
    elements.daysContainer.innerHTML = '';

    if (!Array.isArray(days) || days.length === 0) {
      elements.emptyState.classList.remove('d-none');
      return;
    }

    elements.emptyState.classList.add('d-none');

    for (var i = 0; i < days.length; i += 1) {
      var day = days[i] || {};
      var dateValue = String(day.date || '');
      var slots = Array.isArray(day.slots) ? day.slots : [];

      var card = document.createElement('article');
      card.className = 'card day-card';

      var body = document.createElement('div');
      body.className = 'card-body';

      var title = document.createElement('h2');
      title.className = 'day-title';
      title.textContent = formatDayLabel(dateValue);
      body.appendChild(title);

      var slotsWrap = document.createElement('div');
      slotsWrap.className = 'd-flex flex-wrap gap-2';

      for (var j = 0; j < slots.length; j += 1) {
        var slot = slots[j] || {};
        var startAt = String(slot.start_at || '');
        var endAt = String(slot.end_at || '');

        if (startAt === '' || endAt === '') {
          continue;
        }

        var chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'btn btn-outline-primary btn-sm slot-chip';
        chip.setAttribute('data-date', dateValue);
        chip.setAttribute('data-start-at', startAt);
        chip.setAttribute('data-end-at', endAt);
        chip.textContent = formatTime(startAt);

        slotsWrap.appendChild(chip);
      }

      body.appendChild(slotsWrap);
      card.appendChild(body);
      elements.daysContainer.appendChild(card);
    }
  }

  function openSlotModal(slot) {
    state.selectedSlot = {
      date: String(slot.date || ''),
      startAt: String(slot.startAt || ''),
      endAt: String(slot.endAt || '')
    };

    state.otpContext = null;
    resetModalFlow();

    var dayLabel = formatDayLabel(state.selectedSlot.date);
    var timeLabel = formatTime(state.selectedSlot.startAt);
    var consultorio = state.lastMeta && state.lastMeta.consultorio_id_used ? state.lastMeta.consultorio_id_used : 'N/A';

    elements.modalDateTime.textContent = dayLabel + ' ' + timeLabel;
    elements.modalDoctorId.textContent = state.doctorId;
    elements.modalConsultorioId.textContent = consultorio;

    var modal = window.bootstrap && window.bootstrap.Modal
      ? window.bootstrap.Modal.getOrCreateInstance(elements.slotModal)
      : null;

    if (modal) {
      modal.show();
    }
  }

  async function requestOtp() {
    if (!state.selectedSlot) {
      showModalAlert('Selecciona un horario.', 'danger');
      return;
    }

    var patientName = String(elements.patientNameInput.value || '').trim();
    var patientPhone = String(elements.patientPhoneInput.value || '').trim();
    var patientEmail = String(elements.patientEmailInput.value || '').trim();

    if (patientName === '') {
      showModalAlert('Nombre completo es requerido.', 'danger');
      return;
    }

    if (patientPhone === '' && patientEmail === '') {
      showModalAlert('Ingresa telefono o correo.', 'danger');
      return;
    }

    var payload = {
      doctor_id: state.doctorId,
      start_at: state.selectedSlot.startAt,
      end_at: state.selectedSlot.endAt,
      patient_name: patientName,
      patient_phone: patientPhone,
      patient_email: patientEmail
    };

    var consultorioUsed = state.lastMeta && state.lastMeta.consultorio_id_used ? state.lastMeta.consultorio_id_used : null;
    if (consultorioUsed) {
      payload.consultorio_id = consultorioUsed;
    } else if (state.consultorioId) {
      payload.consultorio_id = state.consultorioId;
    }

    elements.sendOtpBtn.disabled = true;
    elements.sendOtpBtn.textContent = 'Enviando...';
    hideModalAlert();

    try {
      var response = await postJson('/api/agenda/index.php/public/appointments/request', payload);
      if (!response.ok) {
        throw new Error(response.message);
      }

      state.otpContext = {
        requestId: response.payload && response.payload.data ? response.payload.data.request_id : null,
        expiresAt: response.payload && response.payload.data ? response.payload.data.expires_at : null
      };

      elements.otpStep.classList.remove('d-none');
      elements.otpInput.value = '';
      elements.otpInput.focus();

      var otpDebug = response.payload && response.payload.meta ? response.payload.meta.otp_debug : null;
      if (otpDebug) {
        elements.otpDebugHint.textContent = 'QA OTP debug: ' + String(otpDebug);
        elements.otpDebugHint.classList.remove('d-none');
      } else {
        elements.otpDebugHint.textContent = '';
        elements.otpDebugHint.classList.add('d-none');
      }

      showModalAlert('Codigo enviado. Revisa tu telefono o correo.', 'info');
    } catch (err) {
      showModalAlert(err && err.message ? err.message : 'No se pudo enviar codigo.', 'danger');
    } finally {
      elements.sendOtpBtn.disabled = false;
      elements.sendOtpBtn.textContent = 'Enviar codigo';
    }
  }

  async function verifyOtp() {
    if (!state.otpContext || !state.otpContext.requestId) {
      showModalAlert('Primero envia el codigo OTP.', 'danger');
      return;
    }

    var otp = String(elements.otpInput.value || '').trim();
    if (!/^\d{6}$/.test(otp)) {
      showModalAlert('OTP invalido. Deben ser 6 digitos.', 'danger');
      return;
    }

    elements.confirmOtpBtn.disabled = true;
    elements.confirmOtpBtn.textContent = 'Confirmando...';
    hideModalAlert();

    try {
      var response = await postJson('/api/agenda/index.php/public/appointments/verify', {
        request_id: state.otpContext.requestId,
        otp: otp
      });

      if (!response.ok) {
        throw new Error(response.message);
      }

      var appointmentId = response.payload && response.payload.data ? response.payload.data.appointment_id : '';
      elements.successAppointmentId.textContent = appointmentId || '-';
      elements.successStep.classList.remove('d-none');
      elements.otpStep.classList.add('d-none');
      showModalAlert('Cita confirmada correctamente.', 'success');

      loadAvailability();
    } catch (err) {
      showModalAlert(err && err.message ? err.message : 'No se pudo confirmar OTP.', 'danger');
    } finally {
      elements.confirmOtpBtn.disabled = false;
      elements.confirmOtpBtn.textContent = 'Confirmar cita';
    }
  }

  async function postJson(path, payload) {
    try {
      var response = await fetch(resolveApiBase() + path, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      var json = await parseJsonSafe(response);
      if (!response.ok || !json || json.ok !== true) {
        return {
          ok: false,
          payload: json,
          message: readErrorMessage(json, response.status)
        };
      }

      return {
        ok: true,
        payload: json,
        message: ''
      };
    } catch (err) {
      return {
        ok: false,
        payload: null,
        message: err && err.message ? err.message : 'Error de red'
      };
    }
  }

  async function parseJsonSafe(response) {
    try {
      return await response.json();
    } catch (err) {
      return null;
    }
  }

  function readErrorMessage(payload, statusCode) {
    if (payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
      return payload.message;
    }
    if (payload && typeof payload.error === 'string' && payload.error.trim() !== '') {
      return payload.error;
    }
    if (statusCode) {
      return 'Error API (' + statusCode + ')';
    }
    return 'Error al procesar solicitud';
  }

  function formatDayLabel(dateYmd) {
    if (!dateYmd) {
      return 'Fecha no disponible';
    }
    var date = new Date(dateYmd + 'T00:00:00');
    if (Number.isNaN(date.getTime())) {
      return dateYmd;
    }
    var formatter = new Intl.DateTimeFormat('es-MX', {
      weekday: 'long',
      day: '2-digit',
      month: 'short'
    });
    return formatter.format(date);
  }

  function formatTime(dateTimeValue) {
    if (typeof dateTimeValue !== 'string') {
      return '--:--';
    }
    if (dateTimeValue.length >= 16) {
      return dateTimeValue.slice(11, 16);
    }
    return dateTimeValue;
  }

  function resetModalFlow() {
    hideModalAlert();
    elements.otpStep.classList.add('d-none');
    elements.successStep.classList.add('d-none');
    elements.otpDebugHint.classList.add('d-none');
    elements.otpDebugHint.textContent = '';
    elements.otpInput.value = '';
  }

  function showModalAlert(message, type) {
    var level = type || 'danger';
    elements.modalAlert.className = 'alert alert-' + level;
    elements.modalAlert.textContent = message;
    elements.modalAlert.classList.remove('d-none');
  }

  function hideModalAlert() {
    elements.modalAlert.classList.add('d-none');
    elements.modalAlert.textContent = '';
  }

  function updateControls() {
    var isWeek = state.mode === 'week';

    elements.weekControls.classList.toggle('d-none', !isWeek);
    elements.toggleModeBtn.textContent = isWeek ? 'Ver proximos 3 dias' : 'Ver mas fechas';

    if (isWeek) {
      elements.weekLabel.textContent = 'Semana +' + String(state.weekOffset);
      elements.prevWeekBtn.disabled = state.weekOffset <= 0;
      elements.nextWeekBtn.disabled = state.weekOffset >= 3;
    }
  }

  function renderError(message) {
    elements.errorAlert.textContent = message;
    elements.errorAlert.classList.remove('d-none');
  }

  function hideError() {
    elements.errorAlert.textContent = '';
    elements.errorAlert.classList.add('d-none');
  }

  function setLoading(isLoading) {
    elements.loadingState.classList.toggle('d-none', !isLoading);
  }
})();

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
    wizardProgressBar: document.getElementById('wizardProgressBar'),
    wizardStepText: document.getElementById('wizardStepText'),
    wizardBackBtn: document.getElementById('wizardBackBtn'),
    wizardNextBtn: document.getElementById('wizardNextBtn'),
    estimatedPrice: document.getElementById('estimatedPrice'),
    bookerFields: document.getElementById('bookerFields'),
    bookerName: document.getElementById('bookerName'),
    bookerPhone: document.getElementById('bookerPhone'),
    bookerEmail: document.getElementById('bookerEmail'),
    bookerRelationship: document.getElementById('bookerRelationship'),
    patientName: document.getElementById('patientName'),
    patientPhone: document.getElementById('patientPhone'),
    patientEmail: document.getElementById('patientEmail'),
    patientDob: document.getElementById('patientDob'),
    patientGender: document.getElementById('patientGender'),
    patientReason: document.getElementById('patientReason'),
    extraAddressLine1: document.getElementById('extraAddressLine1'),
    extraAddressCp: document.getElementById('extraAddressCp'),
    extraAddressCity: document.getElementById('extraAddressCity'),
    extraAddressState: document.getElementById('extraAddressState'),
    extraAllergies: document.getElementById('extraAllergies'),
    extraHabits: document.getElementById('extraHabits'),
    sendOtpBtn: document.getElementById('sendOtpBtn'),
    otpCode: document.getElementById('otpCode'),
    otpDebugHint: document.getElementById('otpDebugHint'),
    confirmOtpBtn: document.getElementById('confirmOtpBtn'),
    successStep: document.getElementById('successStep'),
    successAppointmentId: document.getElementById('successAppointmentId')
  };

  if (!elements.daysContainer || !elements.toggleModeBtn || !elements.slotModal) {
    return;
  }

  var steps = ['step1', 'step2', 'step3', 'step4', 'step5', 'step6'];

  var state = {
    doctorId: readDoctorId(query.get('doctor_id')),
    consultorioId: readOptional(query.get('consultorio_id')),
    limitPerDay: readLimitPerDay(query.get('limit_per_day')),
    mode: 'next',
    days: 3,
    weekOffset: 0,
    lastMeta: null,
    selectedSlot: null,
    wizardStep: 1,
    appointmentId: null,
    otpId: null
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
      openWizard({
        date: btn.getAttribute('data-date') || '',
        startAt: btn.getAttribute('data-start-at') || '',
        endAt: btn.getAttribute('data-end-at') || ''
      });
    });

    elements.wizardBackBtn.addEventListener('click', function () {
      if (state.wizardStep <= 1) {
        return;
      }
      setWizardStep(state.wizardStep - 1);
    });

    elements.wizardNextBtn.addEventListener('click', function () {
      if (!validateStep(state.wizardStep)) {
        return;
      }
      if (state.wizardStep >= 6) {
        return;
      }
      setWizardStep(state.wizardStep + 1);
    });

    elements.sendOtpBtn.addEventListener('click', function () {
      submitReserveAndOtp();
    });

    elements.confirmOtpBtn.addEventListener('click', function () {
      submitOtpConfirm();
    });

    var bookerRadios = document.querySelectorAll('input[name="bookerIsPatient"]');
    for (var i = 0; i < bookerRadios.length; i += 1) {
      bookerRadios[i].addEventListener('change', function () {
        updateBookerVisibility();
      });
    }

    var pricingRadios = document.querySelectorAll('input[name="visitKind"], input[name="patientType"]');
    for (var j = 0; j < pricingRadios.length; j += 1) {
      pricingRadios[j].addEventListener('change', function () {
        updateEstimatedPrice();
      });
    }

    elements.slotModal.addEventListener('hidden.bs.modal', function () {
      resetWizard();
      state.selectedSlot = null;
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
        headers: { Accept: 'application/json' }
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
        chip.className = 'btn btn-outline-primary slot-chip';
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

  function openWizard(slot) {
    state.selectedSlot = {
      date: String(slot.date || ''),
      startAt: String(slot.startAt || ''),
      endAt: String(slot.endAt || '')
    };

    resetWizard();

    var dayLabel = formatDayLabel(state.selectedSlot.date);
    var timeLabel = formatTime(state.selectedSlot.startAt);
    var consultorio = state.lastMeta && state.lastMeta.consultorio_id_used ? state.lastMeta.consultorio_id_used : (state.consultorioId || 'N/A');

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

  function resetWizard() {
    state.wizardStep = 1;
    state.appointmentId = null;
    state.otpId = null;
    hideModalAlert();
    elements.otpCode.value = '';
    elements.otpDebugHint.classList.add('d-none');
    elements.otpDebugHint.textContent = '';
    elements.successStep.classList.add('d-none');
    elements.successAppointmentId.textContent = '-';
    setWizardStep(1);
    updateBookerVisibility();
    updateEstimatedPrice();
  }

  function setWizardStep(step) {
    state.wizardStep = step;
    for (var i = 0; i < steps.length; i += 1) {
      var el = document.getElementById(steps[i]);
      if (!el) {
        continue;
      }
      el.classList.toggle('active', i === (step - 1));
    }

    elements.wizardStepText.textContent = String(step);
    elements.wizardProgressBar.style.width = String((step / 6) * 100) + '%';
    elements.wizardBackBtn.disabled = step <= 1;

    if (step >= 6) {
      elements.wizardNextBtn.classList.add('d-none');
    } else {
      elements.wizardNextBtn.classList.remove('d-none');
    }

    hideModalAlert();
  }

  function getCheckedValue(name) {
    var el = document.querySelector('input[name="' + name + '"]:checked');
    return el ? String(el.value || '') : '';
  }

  function updateBookerVisibility() {
    var isPatient = getCheckedValue('bookerIsPatient');
    elements.bookerFields.classList.toggle('d-none', isPatient !== 'false');
  }

  function updateEstimatedPrice() {
    var visitKind = getCheckedValue('visitKind');
    var patientType = getCheckedValue('patientType');
    if (!visitKind || !patientType) {
      elements.estimatedPrice.textContent = '-';
      return;
    }

    var base = visitKind === 'video' ? 800 : 900;
    var price = patientType === 'follow_up' ? (base - 150) : base;
    elements.estimatedPrice.textContent = '$' + String(price) + ' MXN';
  }

  function validateStep(step) {
    if (step === 1 && getCheckedValue('visitKind') === '') {
      showModalAlert('Selecciona tipo de cita.', 'danger');
      return false;
    }

    if (step === 2 && getCheckedValue('patientType') === '') {
      showModalAlert('Selecciona primera vez o subsecuente.', 'danger');
      return false;
    }

    if (step === 3) {
      var bookerIsPatient = getCheckedValue('bookerIsPatient');
      if (bookerIsPatient === '') {
        showModalAlert('Indica quien agenda.', 'danger');
        return false;
      }
      if (bookerIsPatient === 'false') {
        if (String(elements.bookerName.value || '').trim() === '' ||
          String(elements.bookerPhone.value || '').trim() === '' ||
          String(elements.bookerEmail.value || '').trim() === '' ||
          String(elements.bookerRelationship.value || '').trim() === '') {
          showModalAlert('Completa datos de la persona que agenda.', 'danger');
          return false;
        }
      }
    }

    if (step === 4) {
      if (String(elements.patientName.value || '').trim() === '' ||
          String(elements.patientPhone.value || '').trim() === '' ||
          String(elements.patientEmail.value || '').trim() === '' ||
          String(elements.patientDob.value || '').trim() === '' ||
          String(elements.patientGender.value || '').trim() === '') {
        showModalAlert('Completa los datos obligatorios del paciente.', 'danger');
        return false;
      }
      if (!isValidEmail(String(elements.patientEmail.value || '').trim())) {
        showModalAlert('Correo del paciente invalido.', 'danger');
        return false;
      }
    }

    return true;
  }

  function validateForReserve() {
    for (var step = 1; step <= 4; step += 1) {
      if (!validateStep(step)) {
        setWizardStep(step);
        return false;
      }
    }
    return true;
  }

  function buildReservePayload() {
    var bookerIsPatient = getCheckedValue('bookerIsPatient') === 'true';
    var patientName = String(elements.patientName.value || '').trim();
    var patientPhone = String(elements.patientPhone.value || '').trim();
    var patientEmail = String(elements.patientEmail.value || '').trim();

    var booker = {
      name: patientName,
      phone: patientPhone,
      email: patientEmail
    };

    if (!bookerIsPatient) {
      booker = {
        name: String(elements.bookerName.value || '').trim(),
        phone: String(elements.bookerPhone.value || '').trim(),
        email: String(elements.bookerEmail.value || '').trim(),
        relationship: String(elements.bookerRelationship.value || '').trim()
      };
    }

    var extras = {
      address: {
        line1: String(elements.extraAddressLine1.value || '').trim(),
        cp: String(elements.extraAddressCp.value || '').trim(),
        city: String(elements.extraAddressCity.value || '').trim(),
        state: String(elements.extraAddressState.value || '').trim()
      },
      allergies: String(elements.extraAllergies.value || '').trim(),
      habits: String(elements.extraHabits.value || '').trim(),
      referred_by_placeholder: true
    };

    var consultorioUsed = state.lastMeta && state.lastMeta.consultorio_id_used
      ? state.lastMeta.consultorio_id_used
      : state.consultorioId;

    return {
      doctor_id: state.doctorId,
      consultorio_id: consultorioUsed || undefined,
      start_at: state.selectedSlot ? state.selectedSlot.startAt : '',
      end_at: state.selectedSlot ? state.selectedSlot.endAt : '',
      visit_kind: getCheckedValue('visitKind'),
      patient_type: getCheckedValue('patientType'),
      booker_is_patient: bookerIsPatient,
      booker: booker,
      patient: {
        name: patientName,
        phone: patientPhone,
        email: patientEmail,
        dob: String(elements.patientDob.value || '').trim(),
        gender: String(elements.patientGender.value || '').trim(),
        reason: String(elements.patientReason.value || '').trim()
      },
      extras: extras,
      otp: {
        channel: getCheckedValue('otpChannel') || undefined,
        otp_id: state.otpId || undefined
      },
      payment_mode: 'none'
    };
  }

  function getOtpContactValue(channel) {
    var bookerIsPatient = getCheckedValue('bookerIsPatient') === 'true';
    if (channel === 'sms') {
      return bookerIsPatient
        ? String(elements.patientPhone.value || '').trim()
        : String(elements.bookerPhone.value || '').trim();
    }

    return bookerIsPatient
      ? String(elements.patientEmail.value || '').trim()
      : String(elements.bookerEmail.value || '').trim();
  }

  async function submitReserveAndOtp() {
    if (!state.selectedSlot) {
      showModalAlert('Selecciona un horario.', 'danger');
      return;
    }

    if (!validateForReserve()) {
      return;
    }

    var channel = getCheckedValue('otpChannel');
    if (channel !== 'sms' && channel !== 'email') {
      showModalAlert('Selecciona canal OTP (sms o email).', 'danger');
      return;
    }

    var contactValue = getOtpContactValue(channel);
    if (!contactValue) {
      showModalAlert('Falta contacto para enviar OTP.', 'danger');
      return;
    }

    elements.sendOtpBtn.disabled = true;
    elements.sendOtpBtn.textContent = 'Procesando...';
    hideModalAlert();

    try {
      if (!state.appointmentId) {
        var reserveResponse = await postJson('/api/agenda/index.php/public/appointments/reserve', buildReservePayload());
        if (!reserveResponse.ok) {
          if (reserveResponse.payload && reserveResponse.payload.error === 'slot_taken') {
            loadAvailability();
          }
          throw new Error(reserveResponse.message);
        }

        state.appointmentId = reserveResponse.payload && reserveResponse.payload.data
          ? reserveResponse.payload.data.appointment_id
          : null;
      }

      if (!state.appointmentId) {
        throw new Error('No se pudo reservar el horario.');
      }

      var otpResponse = await postJson('/api/agenda/index.php/public/otp/request', {
        doctor_id: state.doctorId,
        contact_type: channel,
        contact_value: contactValue
      }, true);

      if (!otpResponse.ok) {
        throw new Error(otpResponse.message);
      }

      state.otpId = otpResponse.payload && otpResponse.payload.data
        ? otpResponse.payload.data.otp_id
        : null;

      if (!state.otpId) {
        throw new Error('No se recibio otp_id.');
      }

      var debugCode = otpResponse.payload && otpResponse.payload.meta
        ? otpResponse.payload.meta.debug_code
        : null;
      if (debugCode) {
        elements.otpDebugHint.textContent = 'Solo QA: debug_code=' + String(debugCode);
        elements.otpDebugHint.classList.remove('d-none');
      } else {
        elements.otpDebugHint.textContent = '';
        elements.otpDebugHint.classList.add('d-none');
      }

      showModalAlert('Reserva creada y OTP enviado. Ingresa el codigo para confirmar.', 'info');
    } catch (err) {
      showModalAlert(err && err.message ? err.message : 'No se pudo procesar la reserva.', 'danger');
    } finally {
      elements.sendOtpBtn.disabled = false;
      elements.sendOtpBtn.textContent = 'Reservar y enviar OTP';
    }
  }

  async function submitOtpConfirm() {
    var code = String(elements.otpCode.value || '').trim();
    if (!state.appointmentId) {
      showModalAlert('Primero reserva y solicita OTP.', 'danger');
      return;
    }
    if (!state.otpId) {
      showModalAlert('Primero solicita OTP.', 'danger');
      return;
    }
    if (!/^\d{6}$/.test(code)) {
      showModalAlert('Codigo OTP invalido.', 'danger');
      return;
    }

    elements.confirmOtpBtn.disabled = true;
    elements.confirmOtpBtn.textContent = 'Confirmando...';
    hideModalAlert();

    try {
      var confirmResponse = await postJson('/api/agenda/index.php/public/appointments/confirm', {
        appointment_id: state.appointmentId,
        otp_id: state.otpId,
        code: code
      });

      if (!confirmResponse.ok) {
        throw new Error(confirmResponse.message);
      }

      elements.successAppointmentId.textContent = state.appointmentId;
      elements.successStep.classList.remove('d-none');
      showModalAlert('Cita confirmada correctamente.', 'success');
      loadAvailability();
    } catch (err) {
      showModalAlert(err && err.message ? err.message : 'No se pudo confirmar OTP.', 'danger');
    } finally {
      elements.confirmOtpBtn.disabled = false;
      elements.confirmOtpBtn.textContent = 'Confirmar cita';
    }
  }

  async function postJson(path, payload, qaMode) {
    try {
      var headers = {
        'Content-Type': 'application/json',
        Accept: 'application/json'
      };
      if (qaMode === true) {
        headers['X-MXMED-QA-Mode'] = '1';
      }

      var response = await fetch(resolveApiBase() + path, {
        method: 'POST',
        headers: headers,
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

  function isValidEmail(value) {
    if (value.indexOf('@') === -1) {
      return false;
    }
    return /.+@.+\..+/.test(value);
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

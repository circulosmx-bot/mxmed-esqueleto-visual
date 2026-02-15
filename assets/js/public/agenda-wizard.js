(function () {
  'use strict';

  var query = new URLSearchParams(window.location.search);
  var doctorId = String(query.get('doctor_id') || '').trim();
  var consultorioId = String(query.get('consultorio_id') || '').trim();

  var elements = {
    fatalError: document.getElementById('fatalError'),
    wizardCard: document.getElementById('wizardCard'),
    globalAlert: document.getElementById('globalAlert'),
    stepNumber: document.getElementById('stepNumber'),
    stepLabel: document.getElementById('stepLabel'),
    progressFill: document.getElementById('progressFill'),
    backBtn: document.getElementById('backBtn'),
    nextBtn: document.getElementById('nextBtn'),
    availabilityLoading: document.getElementById('availabilityLoading'),
    availabilityError: document.getElementById('availabilityError'),
    retryAvailabilityBtn: document.getElementById('retryAvailabilityBtn'),
    slotsContainer: document.getElementById('slotsContainer'),
    selectedSlotNotice: document.getElementById('selectedSlotNotice'),
    selectedSlotText: document.getElementById('selectedSlotText'),
    selectedDoctorText: document.getElementById('selectedDoctorText'),
    visitKind: document.getElementById('visitKind'),
    patientType: document.getElementById('patientType'),
    bookerIsPatient: document.getElementById('bookerIsPatient'),
    bookerIsOther: document.getElementById('bookerIsOther'),
    bookerFields: document.getElementById('bookerFields'),
    bookerName: document.getElementById('bookerName'),
    bookerPhone: document.getElementById('bookerPhone'),
    bookerEmail: document.getElementById('bookerEmail'),
    patientFirstName: document.getElementById('patientFirstName'),
    patientLastName: document.getElementById('patientLastName'),
    patientSecondLastName: document.getElementById('patientSecondLastName'),
    patientPhone: document.getElementById('patientPhone'),
    patientEmail: document.getElementById('patientEmail'),
    patientDobYear: document.getElementById('patientDobYear'),
    patientDobMonth: document.getElementById('patientDobMonth'),
    patientDobDay: document.getElementById('patientDobDay'),
    patientGender: document.getElementById('patientGender'),
    patientReason: document.getElementById('patientReason'),
    otpSummarySlot: document.getElementById('otpSummarySlot'),
    otpSummaryAppointment: document.getElementById('otpSummaryAppointment'),
    sendOtpBtn: document.getElementById('sendOtpBtn'),
    otpCode: document.getElementById('otpCode'),
    confirmBtn: document.getElementById('confirmBtn'),
    finalState: document.getElementById('finalState'),
    finalAppointmentId: document.getElementById('finalAppointmentId'),
    finalSlotText: document.getElementById('finalSlotText'),
    finalCancelToken: document.getElementById('finalCancelToken'),
    cancelNowBtn: document.getElementById('cancelNowBtn'),
    cancelPageLink: document.getElementById('cancelPageLink'),
    restartBtn: document.getElementById('restartBtn')
  };

  var state = {
    step: 1,
    steps: [
      { id: 'step1', label: 'Horario' },
      { id: 'step2', label: 'Datos' },
      { id: 'step3', label: 'OTP' },
      { id: 'step4', label: 'Confirmacion' }
    ],
    consultorioUsed: consultorioId || null,
    slot: null,
    appointmentId: null,
    cancelToken: null,
    otpId: null,
    confirmed: false,
    canceled: false,
    autoStepTimer: null
  };

  if (doctorId === '') {
    showFatal('Falta doctor_id en la URL. Usa: public-book.html?doctor_id=1');
    return;
  }

  bind();
  initDobSelectors();
  syncBookerFieldsVisibility();
  renderStep();
  loadAvailability();

  function bind() {
    elements.backBtn.addEventListener('click', function () {
      if (state.step <= 1) {
        return;
      }
      setStep(state.step - 1);
    });

    elements.nextBtn.addEventListener('click', function () {
      if (state.step === 1 && !state.slot) {
        showAlert('Selecciona un horario para continuar.', 'warning');
        return;
      }
      if (state.step === 2) {
        var valid = validateDataStep();
        if (!valid.ok) {
          showAlert(valid.message, 'warning');
          return;
        }
      }
      if (state.step >= 3) {
        return;
      }
      setStep(state.step + 1);
    });

    elements.retryAvailabilityBtn.addEventListener('click', function () {
      loadAvailability();
    });

    elements.bookerIsPatient.addEventListener('change', syncBookerFieldsVisibility);
    if (elements.bookerIsOther) {
      elements.bookerIsOther.addEventListener('change', syncBookerFieldsVisibility);
    }

    elements.patientDobYear.addEventListener('change', function () {
      refreshDayOptions();
    });

    elements.patientDobMonth.addEventListener('change', function () {
      refreshDayOptions();
    });

    elements.sendOtpBtn.addEventListener('click', function () {
      reserveAndRequestOtp();
    });

    elements.confirmBtn.addEventListener('click', function () {
      confirmAppointment();
    });

    elements.cancelNowBtn.addEventListener('click', function () {
      cancelFromFinal();
    });

    elements.restartBtn.addEventListener('click', function () {
      restartFlow();
    });

    elements.slotsContainer.addEventListener('click', function (event) {
      var btn = event.target.closest('button[data-start-at]');
      if (!btn) {
        return;
      }
      state.slot = {
        startAt: btn.getAttribute('data-start-at') || '',
        endAt: btn.getAttribute('data-end-at') || '',
        date: btn.getAttribute('data-date') || ''
      };
      updateSlotSummary();
      markSelectedSlotButton(btn);
      hideAlert();
      scheduleAutoAdvanceToDataStep();
    });
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

  async function loadAvailability() {
    elements.availabilityLoading.classList.remove('d-none');
    elements.availabilityError.classList.add('d-none');
    elements.retryAvailabilityBtn.classList.add('d-none');
    elements.slotsContainer.innerHTML = '';

    var params = new URLSearchParams();
    params.set('doctor_id', doctorId);
    if (consultorioId !== '') {
      params.set('consultorio_id', consultorioId);
    }
    params.set('mode', 'next');
    params.set('days', '3');
    params.set('limit_per_day', '10');

    try {
      var response = await fetch(resolveApiBase() + '/api/agenda/index.php/public/availability?' + params.toString(), {
        method: 'GET',
        headers: { Accept: 'application/json' }
      });

      var json = await parseJsonSafe(response);
      if (!response.ok || !json || json.ok !== true) {
        throw new Error(readErrorMessage(json, response.status));
      }

      state.consultorioUsed = String((json.meta && json.meta.consultorio_id_used) || state.consultorioUsed || '');
      renderAvailability(json.data || {});
    } catch (err) {
      elements.availabilityError.textContent = err && err.message ? err.message : 'Error de red';
      elements.availabilityError.classList.remove('d-none');
      elements.retryAvailabilityBtn.classList.remove('d-none');
    } finally {
      elements.availabilityLoading.classList.add('d-none');
    }
  }

  function renderAvailability(data) {
    var days = Array.isArray(data.days) ? data.days : [];
    if (days.length === 0) {
      elements.slotsContainer.innerHTML = '<div class="alert alert-light border">No hay horarios disponibles.</div>';
      return;
    }

    var html = '';
    for (var i = 0; i < days.length; i += 1) {
      var day = days[i] || {};
      var date = String(day.date || '');
      var slots = Array.isArray(day.slots) ? day.slots : [];
      html += '<div class="slot-day">';
      html += '<div class="fw-semibold mb-2">' + escapeHtml(formatDayLabel(date)) + '</div>';
      html += '<div class="d-flex flex-wrap gap-2">';
      for (var j = 0; j < slots.length; j += 1) {
        var slot = slots[j] || {};
        var startAt = String(slot.start_at || '');
        var endAt = String(slot.end_at || '');
        if (startAt === '' || endAt === '') {
          continue;
        }
        html += '<button type="button" class="btn btn-outline-primary slot-btn" data-date="' + escapeAttr(date) + '" data-start-at="' + escapeAttr(startAt) + '" data-end-at="' + escapeAttr(endAt) + '">' + escapeHtml(formatTime(startAt)) + '</button>';
      }
      html += '</div></div>';
    }
    elements.slotsContainer.innerHTML = html;
    restoreSelectedSlotButton();
  }

  function markSelectedSlotButton(selected) {
    var buttons = elements.slotsContainer.querySelectorAll('button[data-start-at]');
    for (var i = 0; i < buttons.length; i += 1) {
      buttons[i].classList.remove('btn-primary');
      buttons[i].classList.add('btn-outline-primary');
    }
    selected.classList.remove('btn-outline-primary');
    selected.classList.add('btn-primary');
  }

  function updateSlotSummary() {
    var text = state.slot ? getSlotSummaryText(state.slot) : '-';
    elements.selectedSlotText.textContent = text;
    elements.selectedDoctorText.textContent = doctorId;
    elements.otpSummarySlot.textContent = text;
    elements.finalSlotText.textContent = text;
    if (!elements.selectedSlotNotice) {
      return;
    }
    if (!state.slot) {
      elements.selectedSlotNotice.classList.add('d-none');
      elements.selectedSlotNotice.textContent = '';
      return;
    }
    elements.selectedSlotNotice.textContent = '✅ Has seleccionado: ' + text;
    elements.selectedSlotNotice.classList.remove('d-none');
  }

  function validateDataStep() {
    var patientName = getPatientFullName();
    var patientFirstName = String(elements.patientFirstName.value || '').trim();
    var patientLastName = String(elements.patientLastName.value || '').trim();
    var patientPhone = String(elements.patientPhone.value || '').trim();
    var patientEmail = String(elements.patientEmail.value || '').trim();
    var patientDob = getPatientDob();
    var patientGender = String(elements.patientGender.value || '').trim();

    if (patientFirstName === '' || patientLastName === '') {
      return { ok: false, message: 'Completa nombre(s) y primer apellido del paciente.' };
    }

    if (patientPhone === '' || patientEmail === '') {
      return { ok: false, message: 'Completa teléfono y correo del paciente.' };
    }
    if (!isValidEmail(patientEmail)) {
      return { ok: false, message: 'Correo del paciente invalido.' };
    }
    if (patientDob === null) {
      return { ok: false, message: 'Selecciona una fecha de nacimiento valida.' };
    }
    if (patientGender !== 'M' && patientGender !== 'F' && patientGender !== 'No especifica') {
      return { ok: false, message: 'Selecciona un sexo valido.' };
    }

    if (!isBookerPatient()) {
      var bookerName = String(elements.bookerName.value || '').trim();
      var bookerPhone = String(elements.bookerPhone.value || '').trim();
      var bookerEmail = String(elements.bookerEmail.value || '').trim();
      if (bookerName === '' || bookerPhone === '' || bookerEmail === '') {
        return { ok: false, message: 'Completa los datos de quien agenda.' };
      }
      if (!isValidEmail(bookerEmail)) {
        return { ok: false, message: 'Correo de quien agenda invalido.' };
      }
    }

    return { ok: true };
  }

  function buildReservePayload() {
    var bookerIsPatient = isBookerPatient();
    var patientName = getPatientFullName();
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
        email: String(elements.bookerEmail.value || '').trim()
      };
    }

    var payload = {
      doctor_id: doctorId,
      start_at: state.slot ? state.slot.startAt : '',
      end_at: state.slot ? state.slot.endAt : '',
      visit_kind: String(elements.visitKind.value || 'presencial'),
      patient_type: String(elements.patientType.value || 'first_time'),
      booker_is_patient: bookerIsPatient,
      booker: booker,
      patient: {
        name: patientName,
        phone: patientPhone,
        email: patientEmail,
        dob: getPatientDob() || '',
        gender: String(elements.patientGender.value || '').trim(),
        reason: String(elements.patientReason.value || '').trim()
      },
      payment_mode: 'none'
    };

    if (state.consultorioUsed) {
      payload.consultorio_id = state.consultorioUsed;
    }

    return payload;
  }

  function getOtpContactValue() {
    if (!isBookerPatient()) {
      return String(elements.bookerPhone.value || '').trim();
    }
    return String(elements.patientPhone.value || '').trim();
  }

  async function reserveAndRequestOtp() {
    if (!state.slot) {
      showAlert('Primero elige un horario.', 'warning');
      setStep(1);
      return;
    }

    var valid = validateDataStep();
    if (!valid.ok) {
      showAlert(valid.message, 'warning');
      setStep(2);
      return;
    }

    elements.sendOtpBtn.disabled = true;
    elements.sendOtpBtn.textContent = 'Procesando...';
    hideAlert();

    try {
      if (!state.appointmentId) {
        var reserve = await postJson('/api/agenda/index.php/public/appointments/reserve', buildReservePayload());
        if (!reserve.ok) {
          if (reserve.payload && reserve.payload.error === 'slot_taken') {
            showAlert('Ese horario ya fue tomado. Elige otro.', 'warning');
            setStep(1);
            loadAvailability();
            return;
          }
          throw new Error(reserve.message);
        }

        state.appointmentId = reserve.payload.data && reserve.payload.data.appointment_id ? reserve.payload.data.appointment_id : null;
        state.cancelToken = reserve.payload.data && reserve.payload.data.cancel_token ? reserve.payload.data.cancel_token : null;
      }

      if (!state.appointmentId) {
        throw new Error('No se pudo reservar el horario.');
      }

      var otpRequest = await postJson('/api/agenda/index.php/public/otp/request', {
        doctor_id: doctorId,
        contact_type: 'sms',
        contact_value: getOtpContactValue()
      });

      if (!otpRequest.ok) {
        throw new Error(otpRequest.message);
      }

      state.otpId = otpRequest.payload.data && otpRequest.payload.data.otp_id ? otpRequest.payload.data.otp_id : null;
      if (!state.otpId) {
        throw new Error('No se recibio otp_id.');
      }

      elements.otpSummaryAppointment.textContent = state.appointmentId;
      showAlert('OTP enviado. Ingresa el codigo para confirmar.', 'success');
    } catch (err) {
      showAlert(err && err.message ? err.message : 'Error de red', 'error');
    } finally {
      elements.sendOtpBtn.disabled = false;
      elements.sendOtpBtn.textContent = 'Reservar y enviar OTP';
    }
  }

  async function confirmAppointment() {
    var code = String(elements.otpCode.value || '').trim();
    if (!/^\d{6}$/.test(code)) {
      showAlert('El codigo OTP debe tener 6 digitos.', 'warning');
      return;
    }
    if (!state.appointmentId || !state.otpId) {
      showAlert('Primero solicita OTP.', 'warning');
      return;
    }

    elements.confirmBtn.disabled = true;
    elements.confirmBtn.textContent = 'Confirmando...';
    hideAlert();

    try {
      var confirm = await postJson('/api/agenda/index.php/public/appointments/confirm', {
        appointment_id: state.appointmentId,
        otp_id: state.otpId,
        code: code
      });
      if (!confirm.ok) {
        throw new Error(confirm.message);
      }

      state.confirmed = true;
      state.canceled = false;
      elements.finalState.className = 'alert alert-success';
      elements.finalState.textContent = 'Tu cita fue confirmada.';
      elements.finalAppointmentId.textContent = state.appointmentId;
      elements.finalCancelToken.textContent = state.cancelToken || '-';
      elements.cancelPageLink.href = 'public-cancel.html?token=' + encodeURIComponent(state.cancelToken || '');
      setStep(4);
    } catch (err) {
      showAlert(err && err.message ? err.message : 'Error de red', 'error');
    } finally {
      elements.confirmBtn.disabled = false;
      elements.confirmBtn.textContent = 'Confirmar cita';
    }
  }

  async function cancelFromFinal() {
    if (!state.cancelToken) {
      showAlert('No hay cancel_token disponible.', 'warning');
      return;
    }

    elements.cancelNowBtn.disabled = true;
    elements.cancelNowBtn.textContent = 'Cancelando...';
    hideAlert();

    try {
      var cancel = await postJson('/api/agenda/index.php/public/appointments/cancel', {
        cancel_token: state.cancelToken,
        reason: 'Cancelacion desde wizard'
      });
      if (!cancel.ok) {
        throw new Error(cancel.message);
      }

      state.canceled = true;
      elements.finalState.className = 'alert alert-warning';
      elements.finalState.textContent = 'Cita cancelada.';
      showAlert('La cita fue cancelada y el horario se libero.', 'success');
      loadAvailability();
    } catch (err) {
      showAlert(err && err.message ? err.message : 'Error de red', 'error');
    } finally {
      elements.cancelNowBtn.disabled = false;
      elements.cancelNowBtn.textContent = 'Cancelar cita';
    }
  }

  function restartFlow() {
    window.clearTimeout(state.autoStepTimer);
    state.autoStepTimer = null;
    state.step = 1;
    state.slot = null;
    state.appointmentId = null;
    state.cancelToken = null;
    state.otpId = null;
    state.confirmed = false;
    state.canceled = false;

    elements.otpCode.value = '';
    elements.finalAppointmentId.textContent = '-';
    elements.finalCancelToken.textContent = '-';
    elements.otpSummaryAppointment.textContent = '-';
    updateSlotSummary();
    setStep(1);
    hideAlert();
    loadAvailability();
  }

  function setStep(step) {
    if (step < 1) {
      step = 1;
    }
    if (step > 4) {
      step = 4;
    }
    state.step = step;
    renderStep();
  }

  function renderStep() {
    for (var i = 0; i < state.steps.length; i += 1) {
      var pane = document.getElementById(state.steps[i].id);
      if (!pane) {
        continue;
      }
      pane.classList.toggle('active', i === (state.step - 1));
    }

    elements.stepNumber.textContent = String(state.step);
    elements.stepLabel.textContent = state.steps[state.step - 1].label;
    elements.progressFill.style.width = String(state.step * 25) + '%';
    elements.backBtn.disabled = state.step <= 1;
    elements.nextBtn.classList.toggle('d-none', state.step >= 3);
    if (state.step === 1 && state.slot) {
      restoreSelectedSlotButton();
    }
    updateSlotSummary();
  }

  function scheduleAutoAdvanceToDataStep() {
    if (!state.slot || state.step !== 1) {
      return;
    }
    window.clearTimeout(state.autoStepTimer);
    state.autoStepTimer = window.setTimeout(function () {
      if (state.step !== 1 || !state.slot) {
        return;
      }
      setStep(2);
    }, 550);
  }

  function restoreSelectedSlotButton() {
    if (!state.slot) {
      return;
    }
    var selector = 'button[data-date="' + cssEscape(state.slot.date) + '"][data-start-at="' + cssEscape(state.slot.startAt) + '"][data-end-at="' + cssEscape(state.slot.endAt) + '"]';
    var selected = elements.slotsContainer.querySelector(selector);
    if (selected) {
      markSelectedSlotButton(selected);
    }
  }

  function syncBookerFieldsVisibility() {
    elements.bookerFields.classList.toggle('d-none', isBookerPatient());
  }

  function isBookerPatient() {
    return !!(elements.bookerIsPatient && elements.bookerIsPatient.checked);
  }

  async function postJson(path, payload) {
    try {
      var response = await fetch(resolveApiBase() + path, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json'
        },
        body: JSON.stringify(payload)
      });

      var json = await parseJsonSafe(response);
      if (!response.ok || !json || json.ok !== true) {
        return { ok: false, payload: json, message: readErrorMessage(json, response.status) };
      }

      return { ok: true, payload: json, message: '' };
    } catch (err) {
      return { ok: false, payload: null, message: 'Error de red' };
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
    return statusCode ? 'Error API (' + statusCode + ')' : 'Error de red';
  }

  function showFatal(message) {
    elements.fatalError.textContent = message;
    elements.fatalError.classList.remove('d-none');
    elements.wizardCard.classList.add('d-none');
  }

  function showAlert(message, type) {
    var level = 'danger';
    if (type === 'success') {
      level = 'success';
    } else if (type === 'warning') {
      level = 'warning';
    }
    elements.globalAlert.className = 'alert alert-' + level;
    elements.globalAlert.textContent = message;
    elements.globalAlert.classList.remove('d-none');
  }

  function hideAlert() {
    elements.globalAlert.classList.add('d-none');
    elements.globalAlert.textContent = '';
  }

  function formatDayLabel(dateYmd) {
    if (!dateYmd) {
      return 'Fecha';
    }
    var date = new Date(dateYmd + 'T00:00:00');
    if (Number.isNaN(date.getTime())) {
      return dateYmd;
    }
    return new Intl.DateTimeFormat('es-MX', {
      weekday: 'long',
      day: '2-digit',
      month: 'short'
    }).format(date);
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

  function getSlotSummaryText(slot) {
    return formatDayLabel(slot.date) + ' · ' + formatTime(slot.startAt) + '–' + formatTime(slot.endAt);
  }

  function isValidEmail(value) {
    return /.+@.+\..+/.test(String(value || '').trim());
  }

  function initDobSelectors() {
    var currentYear = new Date().getFullYear();
    var minYear = currentYear - 120;
    var yearOptions = '<option value="">Año</option>';

    for (var year = currentYear; year >= minYear; year -= 1) {
      yearOptions += '<option value="' + String(year) + '">' + String(year) + '</option>';
    }

    elements.patientDobYear.innerHTML = yearOptions;
    refreshDayOptions();
  }

  function refreshDayOptions() {
    var selectedYear = parseInt(String(elements.patientDobYear.value || ''), 10);
    var selectedMonth = parseInt(String(elements.patientDobMonth.value || ''), 10);
    var selectedDay = String(elements.patientDobDay.value || '').trim();

    var maxDay = 31;
    if (!Number.isNaN(selectedYear) && !Number.isNaN(selectedMonth)) {
      maxDay = new Date(selectedYear, selectedMonth, 0).getDate();
    }

    var dayOptions = '<option value="">Día</option>';
    for (var day = 1; day <= maxDay; day += 1) {
      var isSelected = selectedDay === String(day) ? ' selected' : '';
      dayOptions += '<option value="' + String(day) + '"' + isSelected + '>' + String(day) + '</option>';
    }

    elements.patientDobDay.innerHTML = dayOptions;
    if (selectedDay !== '' && parseInt(selectedDay, 10) > maxDay) {
      elements.patientDobDay.value = '';
    }
  }

  function getPatientFullName() {
    var firstName = String(elements.patientFirstName.value || '').trim();
    var lastName = String(elements.patientLastName.value || '').trim();
    var secondLastName = String(elements.patientSecondLastName.value || '').trim();
    return [firstName, lastName, secondLastName].filter(function (part) {
      return part !== '';
    }).join(' ');
  }

  function getPatientDob() {
    var year = parseInt(String(elements.patientDobYear.value || ''), 10);
    var month = parseInt(String(elements.patientDobMonth.value || ''), 10);
    var day = parseInt(String(elements.patientDobDay.value || ''), 10);

    if (Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day)) {
      return null;
    }

    var date = new Date(year, month - 1, day);
    if (
      Number.isNaN(date.getTime()) ||
      date.getFullYear() !== year ||
      date.getMonth() !== (month - 1) ||
      date.getDate() !== day
    ) {
      return null;
    }

    var mm = String(month).padStart(2, '0');
    var dd = String(day).padStart(2, '0');
    return String(year) + '-' + mm + '-' + dd;
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/`/g, '&#96;');
  }

  function cssEscape(value) {
    return String(value || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }
})();

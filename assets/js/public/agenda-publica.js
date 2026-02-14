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
    modalConsultorioId: document.getElementById('modalConsultorioId')
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
    lastMeta: null
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
      openSlotModal(btn.getAttribute('data-date'), btn.getAttribute('data-start-at'));
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

  function buildApiUrl() {
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
      var response = await fetch(buildApiUrl(), {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
      });

      var payload = null;
      try {
        payload = await response.json();
      } catch (jsonErr) {
        payload = null;
      }

      if (!response.ok || !payload || payload.ok !== true) {
        var errMessage = readErrorMessage(payload, response.status);
        throw new Error(errMessage);
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
    return 'Error al cargar disponibilidad';
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

        if (startAt === '') {
          continue;
        }

        var chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'btn btn-outline-primary btn-sm slot-chip';
        chip.setAttribute('data-date', dateValue);
        chip.setAttribute('data-start-at', startAt);
        chip.textContent = formatTime(startAt);

        slotsWrap.appendChild(chip);
      }

      body.appendChild(slotsWrap);
      card.appendChild(body);
      elements.daysContainer.appendChild(card);
    }
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

  function openSlotModal(dateYmd, startAt) {
    var dayLabel = formatDayLabel(String(dateYmd || ''));
    var timeLabel = formatTime(String(startAt || ''));
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

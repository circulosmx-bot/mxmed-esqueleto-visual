(function (root) {
  'use strict';

  var months = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
  ];

  function text(value) {
    return String(value == null ? '' : value).trim();
  }

  function todayParts(now) {
    var date = now instanceof Date ? now : new Date();
    return { year: date.getFullYear(), month: date.getMonth() + 1, day: date.getDate() };
  }

  function compose(parts, now) {
    parts = parts || {};
    var dayText = text(parts.day);
    var monthText = text(parts.month);
    var yearText = text(parts.year);
    if (!/^\d{1,2}$/.test(dayText) || !/^\d{1,2}$/.test(monthText) || !/^\d{4}$/.test(yearText)) {
      return { ok: false, value: '', message: 'Completa día, mes y año de nacimiento.' };
    }
    var day = Number(dayText);
    var month = Number(monthText);
    var year = Number(yearText);
    if (day < 1 || day > 31 || month < 1 || month > 12 || year < 1) {
      return { ok: false, value: '', message: 'Ingresa una fecha de nacimiento válida.' };
    }
    var date = new Date(Date.UTC(year, month - 1, day));
    if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) {
      return { ok: false, value: '', message: 'La fecha de nacimiento no existe.' };
    }
    var today = todayParts(now);
    if (year > today.year || (year === today.year && (month > today.month || (month === today.month && day > today.day)))) {
      return { ok: false, value: '', message: 'La fecha de nacimiento no puede estar en el futuro.' };
    }
    return {
      ok: true,
      value: String(year).padStart(4, '0') + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0'),
      message: ''
    };
  }

  function decompose(value) {
    var match = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(text(value));
    if (!match) return { day: '', month: '', year: '' };
    return { day: String(Number(match[3])), month: String(Number(match[2])), year: match[1] };
  }

  root.MxmedPublicBirthDate = Object.freeze({ months: months, compose: compose, decompose: decompose });
})(window);

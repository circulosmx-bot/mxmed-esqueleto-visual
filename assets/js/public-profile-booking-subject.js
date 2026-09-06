(function (root) {
  'use strict';

  // Local preparation only. PublicAppointmentsController owns the backend contract.
  function text(value, limit) {
    return String(value || '').trim().slice(0, limit);
  }

  function choose(state, isPatient) {
    if (typeof isPatient !== 'boolean') throw new TypeError('Booking subject must be boolean');
    state.booker_is_patient = isPatient;
    state.preparedPayload = null;
  }

  function prepare(state, patientData, bookerData) {
    if (typeof state.booker_is_patient !== 'boolean') {
      return { ok: false, message: 'Selecciona para quién es la cita.' };
    }
    if (!state.selectedSlot) return { ok: false, message: 'Selecciona una cita disponible.' };
    var patient = {
      name: patientData.full_name,
      phone: patientData.mobile_phone,
      email: patientData.email,
      dob: patientData.birth_date,
      gender: patientData.gender,
      reason: patientData.reason
    };
    var booker = { name: patient.name, phone: patient.phone, email: patient.email };
    if (!state.booker_is_patient) {
      bookerData = bookerData || {};
      booker = {
        name: text(bookerData.name, 160),
        phone: text(bookerData.phone, 32),
        email: text(bookerData.email, 191),
        relationship: text(bookerData.relationship, 64)
      };
      if (!booker.name) return { ok: false, message: 'Ingresa el nombre de quien agenda.' };
      if (booker.phone.replace(/\D/g, '').length < 10) return { ok: false, message: 'Ingresa un teléfono válido de quien agenda.' };
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(booker.email)) return { ok: false, message: 'Ingresa un correo válido de quien agenda.' };
      if (!booker.relationship) return { ok: false, message: 'Selecciona la relación con el paciente.' };
    }
    return { ok: true, payload: {
      doctor_id: state.doctorId,
      consultorio_id: state.selectedSlot.consultorio_id,
      start_at: state.selectedSlot.start_at,
      end_at: state.selectedSlot.end_at,
      visit_kind: 'presencial',
      patient_type: 'first_time',
      booker_is_patient: state.booker_is_patient,
      patient: patient,
      booker: booker,
      payment_mode: 'none'
    } };
  }

  root.MxmedPublicBookingSubject = Object.freeze({ choose: choose, prepare: prepare });
})(window);

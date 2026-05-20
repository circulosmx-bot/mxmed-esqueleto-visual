<?php
return [
    'overrides_table' => 'agenda_availability_overrides', // String table name when overrides are ready, otherwise null
    'appointments_table' => 'agenda_appointments',
    'appointment_events_table' => 'agenda_appointment_events',
    'patient_flags_table' => 'agenda_patient_flags',
    'patient_incidents_table' => 'agenda_patient_incidents',
    'waitlist_entries_table' => 'agenda_waitlist_entries',
    'operators_table' => 'agenda_operators',
    'operator_permissions_table' => 'agenda_operator_permissions',
    'operator_audit_events_table' => 'agenda_operator_audit_events',
    'appointment_pk' => 'appointment_id',
    'late_cancel_hours' => null,
];

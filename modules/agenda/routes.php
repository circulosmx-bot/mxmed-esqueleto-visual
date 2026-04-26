<?php
return [
    'GET /agenda/appointments' => 'AppointmentsController@index',
    'GET /agenda/appointments/{id}' => 'AppointmentsController@show',
    'GET /agenda/medical-groups/search' => 'MedicalGroupsController@search',
    'POST /agenda/medical-groups' => 'MedicalGroupsController@create',
    'POST /agenda/medical-groups/{group_id}/join' => 'MedicalGroupsController@join',
    'GET /agenda/medical-groups/pending' => 'MedicalGroupsController@pending',
    'POST /agenda/medical-groups/{group_id}/approve' => 'MedicalGroupsController@approve',
    'POST /agenda/medical-groups/{group_id}/reject' => 'MedicalGroupsController@reject',
    'POST /agenda/medical-groups/{group_id}/merge' => 'MedicalGroupsController@merge',
    'GET /agenda/patients/{patient_id}/behavior' => 'PatientBehaviorController@show',
    'GET /agenda/consultorios' => 'ConsultoriosController@index',
    'PUT /agenda/consultorios' => 'ConsultoriosController@update',
    'GET /agenda/schedule' => 'ScheduleController@index',
    'PUT /agenda/schedule' => 'ScheduleController@update',
    'GET /agenda/settings' => 'AgendaSettingsController@index',
    'PUT /agenda/settings' => 'AgendaSettingsController@update',
];

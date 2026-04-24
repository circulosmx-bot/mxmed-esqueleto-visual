<?php
return [
    'GET /agenda/appointments' => 'AppointmentsController@index',
    'GET /agenda/appointments/{id}' => 'AppointmentsController@show',
    'GET /agenda/consultorios' => 'ConsultoriosController@index',
    'PUT /agenda/consultorios' => 'ConsultoriosController@update',
    'GET /agenda/schedule' => 'ScheduleController@index',
    'PUT /agenda/schedule' => 'ScheduleController@update',
    'GET /agenda/settings' => 'AgendaSettingsController@index',
    'PUT /agenda/settings' => 'AgendaSettingsController@update',
];

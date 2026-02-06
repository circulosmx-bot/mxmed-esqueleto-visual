<?php
return [
    'GET /agenda/appointments' => 'AppointmentsController@index',
    'GET /agenda/appointments/{id}' => 'AppointmentsController@show',
    'GET /agenda/consultorios' => 'ConsultoriosController@index',
];

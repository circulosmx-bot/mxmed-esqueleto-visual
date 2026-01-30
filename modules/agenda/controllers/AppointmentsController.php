<?php
namespace Agenda\Controllers;

class AppointmentsController
{
    public function index(array $params = [])
    {
        return $this->respond('List appointments (not implemented yet)');
    }

    public function show($id)
    {
        return $this->respond("Get appointment {$id} (not implemented yet)");
    }

    private function respond($message)
    {
        return [
            'ok' => false,
            'error' => 'not_implemented',
            'message' => $message,
            'data' => null,
        ];
    }
}

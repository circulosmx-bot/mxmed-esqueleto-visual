<?php
require __DIR__.'/bootstrap.php';
$v=$identity->sessions()->validate($input['token'],true);
if(!$v->allowed())throw new RuntimeException('test_session_required');
echo json_encode(['digest'=>(string)$v->record()->tokenDigest()]);

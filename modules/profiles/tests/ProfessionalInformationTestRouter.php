<?php
// Test-only router. Must never run against the application database.
if(!str_starts_with(getenv('MXMED_DB_NAME')?:'','ip01a_test_')){http_response_code(503);exit;}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/api/')&&$path!=='/api/profiles/professional-information.php'){
    if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(503);header('Content-Type: application/json');echo '{"ok":false}';return true;}
    $readRuntime=getenv('IP01A_READONLY_RUNTIME');
    if(!$readRuntime||!preg_match('~^http://127\.0\.0\.1:[0-9]+$~',$readRuntime)){http_response_code(503);return true;}
    header('Content-Type: application/json');echo file_get_contents($readRuntime.$_SERVER['REQUEST_URI']);return true;
}
session_start();
$doctor=$_COOKIE['qa_doctor']??'synthetic-a';
if(!in_array($doctor,['synthetic-a','synthetic-b','none'],true)){http_response_code(403);exit;}
if($doctor==='none'){$_SESSION=[];}else{
    $_SESSION['doctor_id']=$doctor;$_SESSION['user_id']='ip01a-synthetic-owner';$_SESSION['role']='doctor';
}
session_write_close();return false;

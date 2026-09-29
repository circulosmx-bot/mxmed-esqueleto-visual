<?php
// Disposable-server-only observation. No phone/file content or tokens are recorded.
if (PHP_SAPI !== 'cli-server' || !preg_match('/^flow_r1_qa_[a-f0-9]{12}$/D', (string)getenv('MXMED_DB_NAME'))) return;
$path=(string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!str_ends_with($path, '/upload')) return;
$probeFile=$_FILES['file'] ?? null;
$probe=['files_present'=>is_array($probeFile),'upload_error'=>$probeFile['error'] ?? null,'bytes'=>$probeFile['size'] ?? null,
    'upload_max_filesize'=>ini_get('upload_max_filesize'),'post_max_size'=>ini_get('post_max_size'),'memory_limit'=>ini_get('memory_limit')];
register_shutdown_function(static function() use ($probe, $probeFile):void {
    $probe['raw_temp_exists_at_shutdown']=is_array($probeFile) && is_file((string)($probeFile['tmp_name']??''));
    $probe['peak_php_bytes']=memory_get_peak_usage(true);$probe['http_status']=http_response_code();
    file_put_contents((string)getenv('FLOW_R42_PROBE_LOG'),json_encode($probe)."\n",FILE_APPEND|LOCK_EX);
});

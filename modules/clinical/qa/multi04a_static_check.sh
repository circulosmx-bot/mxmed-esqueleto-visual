#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$repo_root"
php <<'PHP'
<?php
$s=file_get_contents('api/_lib/clinical_private_binary_retrieval.php');
foreach(token_get_all($s) as $t) {
 if(!is_array($t)||in_array($t[0],[T_COMMENT,T_DOC_COMMENT],true))continue;
 if(preg_match('/\$_(?:GET|POST|REQUEST|FILES|SESSION)|https?:\/\/|\/storage\//',$t[1]))throw new RuntimeException('Forbidden input/locator');
 if($t[0]===T_STRING&&in_array(strtolower($t[1]),['header','http_response_code','readfile','fpassthru','session_start'],true))throw new RuntimeException('HTTP output');
 if($t[0]===T_CONSTANT_ENCAPSED_STRING&&preg_match('/\b(?:INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)\b/',$t[1]))throw new RuntimeException('SQL write');
}
$last=0;
foreach(['$this->documentByToken(', 'FROM patients_doctor_links','FROM clinical_encounters','FROM clinical_document_binaries','->stat(', '->openReadStream(', 'hash_update_stream(', 'return ['] as $call){$at=strpos($s,$call,$last);if($at===false)throw new RuntimeException('Wrong order '.$call);$last=$at+strlen($call);}
$descriptor=substr($s,strpos($s,'        return ['),strpos($s,'    /** Bounded')-strpos($s,'        return ['));
if(preg_match('/storage_key|public_url|absolute_path|private_root/',$descriptor))throw new RuntimeException('Descriptor leak');
echo "MULTI04A_STATIC_ORDER_PRIVACY=PASS\n";
PHP
if rg -n 'clinical_private_binary_retrieval' api --glob '*.php' --glob '!clinical_private_binary_retrieval.php' --glob '!index.php'; then
  echo 'FAIL runtime wiring' >&2; exit 1
fi
git diff --exit-code a614f7347ebca01f94a43da48bc987b0d8a6984c -- api/clinical-documents.php api/evolution-note-generate.php api/_lib/clinical_idempotency.php api/_lib/clinical_multipart_document_service.php modules/clinical/db/migrations assets/js/app.js
rg -q 'V1_MULTIPART_STORAGE_NOT_READY' api/clinical/index.php
echo 'MULTI04A_STATIC_QA=PASS'
echo 'ANY_DATABASE_CONNECTED=false'

# MULTI04B permits only the bounded GET insertion and read-only storage construction.
bash "$repo_root/modules/clinical/qa/multi04b_static_check.sh"

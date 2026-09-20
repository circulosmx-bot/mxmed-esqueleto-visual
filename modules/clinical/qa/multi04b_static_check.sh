#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
cd "$repo_root"
python3 - <<'PY'
from pathlib import Path
import subprocess,re
base='99422dfb601282a5c5eceaafa6a02c34dc8b183b'
def prior(path):return subprocess.check_output(['git','show',base+':'+path],text=True)
r=Path('api/clinical/index.php').read_text()
pattern=r'        // MULTI04B BEGIN:[\s\S]*?        // MULTI04B END\.\n'
blocks=re.findall(pattern,r);assert len(blocks)==1
b=blocks[0]
# MULTI05A protects the complete router against MULTI04C, permitting only its
# exact UUID extension and canonical bridge; MULTI04B GET block stays byte-identical.
accepted=subprocess.check_output(['git','show','10c09aa8fa0960fd5d1d57d72c765755026c2c06:api/clinical/index.php'],text=True)
assert b==re.findall(pattern,accepted)[0]
assert re.sub(pattern,'',accepted)==prior('api/clinical/index.php'),'unrelated accepted router change'
subprocess.run(['bash','modules/clinical/qa/multi05a_static_check.sh'],check=True)
assert "$method === 'GET' && count($segments) === 4" in b and "($segments[2] ?? '') === 'binary'" in b
last=0
for item in ['clinical_require_doctor_context(', 'clinical_documents_pdo()', 'clinical_m6_document_route_uses_v1(', 'clinical_private_binary_retrieval.php', "getenv('MXMED_CLINICAL_PRIVATE_STORAGE_ROOT')", 'new ClinicalPrivateBinaryStorage($root, null, null, true)', '->retrieve(', 'clinical_binary_http_send(']:
 last=b.index(item,last)+len(item)
assert all(x not in b for x in ['$_GET','$_POST','$_REQUEST','HTTP_X_DOCTOR','patients_doctor_links','SELECT','INSERT','UPDATE','DELETE','readfile('])
s=Path('api/_lib/clinical_private_binary_storage.php').read_text()
s=s.replace('?callable $opaqueStagingIdentityFactory = null,\n        bool $readOnly = false','?callable $opaqueStagingIdentityFactory = null',1)
s=s.replace('        if (!$readOnly) {\n            $this->makeDirectory($root);\n        }','        $this->makeDirectory($root);',1)
s=s.replace('            if ($readOnly) {\n                $this->assertDirectoryChain($namespace);\n            } else {\n                $this->ensureDirectoryForKey($namespace);\n            }','            $this->ensureDirectoryForKey($namespace);',1)
assert s==prior('api/_lib/clinical_private_binary_storage.php'),'unrelated storage change'
h=Path('api/_lib/clinical_private_binary_http.php').read_text()
for forbidden in ['readfile(', 'fopen(', 'quarantine(', 'deleteUncommitted(', 'Accept-Ranges','ETag','Last-Modified','$_GET','$_POST','SELECT ','INSERT ','UPDATE ','DELETE ']:assert forbidden not in h
assert 'finally' in h and 'fclose($stream)' in h and '65536' in h
assert 'V1_MULTIPART_STORAGE_NOT_READY' in r
print('MULTI04B_STATIC_QA=PASS')
PY
git diff --exit-code 99422dfb601282a5c5eceaafa6a02c34dc8b183b -- api/_lib/clinical_private_binary_retrieval.php api/_lib/clinical_multipart_document_service.php api/_lib/clinical_idempotency.php api/clinical-documents.php api/evolution-note-generate.php modules/clinical/db/migrations assets/js/app.js

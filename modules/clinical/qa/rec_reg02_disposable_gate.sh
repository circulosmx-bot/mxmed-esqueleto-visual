#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="rec_reg02_qa_$(openssl rand -hex 6)"
[[ "$qa_db" =~ ^rec_reg02_qa_[0-9a-f]{12}$ ]]
cleanup(){ mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --single-transaction --no-tablespaces --set-gtid-purged=OFF mxmed | mysql "$qa_db"
MXMED_QA_DB="$qa_db" php "$root_dir/modules/clinical/qa/rec_reg02_disposable.php"
echo "REC_REG02_DISPOSABLE_DATABASE_DELETED_ON_EXIT=PASS"

#!/usr/bin/env bash
set -euo pipefail

# The script owns one uniquely named disposable database and user. It never
# creates tables or rows in the configured local development database.
suffix="${RANDOM}${RANDOM}"
database="mxmed_pdb07b_${suffix}"
user="mxmed_pdb07b_${suffix}"
password="mxmed_pdb07b_disposable_${suffix}"

cleanup() {
  mysql -uroot --protocol=tcp -h127.0.0.1 -e "DROP DATABASE IF EXISTS \`${database}\`; DROP USER IF EXISTS '${user}'@'%'; FLUSH PRIVILEGES;" >/dev/null 2>&1 || true
}
trap cleanup EXIT

mysql -uroot --protocol=tcp -h127.0.0.1 -e "CREATE DATABASE \`${database}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '${user}'@'%' IDENTIFIED BY '${password}'; GRANT ALL PRIVILEGES ON \`${database}\`.* TO '${user}'@'%'; FLUSH PRIVILEGES;"

export MXMED_PDB07B_TEST_DSN="mysql:host=127.0.0.1;port=3306;dbname=${database};charset=utf8mb4"
export MXMED_PDB07B_TEST_USER="$user"
export MXMED_PDB07B_TEST_PASS="$password"

php modules/agenda/tests/PublicHoldExpiryAndConcurrencyIntegrationTest.php

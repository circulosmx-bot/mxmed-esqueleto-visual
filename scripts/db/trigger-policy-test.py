#!/usr/bin/env python3
"""Disposable MySQL only. No published port, host DB, AWS or global toggle."""
import pathlib
import re
import subprocess
import time
import uuid

ROOT = pathlib.Path(__file__).resolve().parents[2]
FILES = [
    'modules/platform/db/migrations/2026_07_20_01_create_platform_audit_events.sql',
    'modules/platform/db/migrations/2026_07_27_01_expand_platform_audit_events_audit_v1.sql',
    'modules/platform/db/migrations/2026_07_27_04_guard_platform_audit_events_audit_v1.sql',
    'modules/patients/db/migrations/2026_07_22_02_create_patient_identity_audit_events.sql',
]
def run(args, **kwargs):
    return subprocess.run(args, text=True, capture_output=True, **kwargs)

def main():
    definitions = []
    for path in FILES:
        definitions.extend(re.findall(r'CREATE TRIGGER\s+\w+\s+BEFORE\s+(?:UPDATE|DELETE)\s+ON\s+\w+\s+FOR EACH ROW\s+BEGIN\s+SIGNAL.*?END(?=//|\$\$)', (ROOT/path).read_text(), re.S))
    assert len(definitions) == 8, 'canonical trigger inventory changed'
    for trust in [0, 1]:
        name = 'mxmed-trigger-qa-' + uuid.uuid4().hex[:12]
        def sql(statement, user='root', error=None):
            p = run(['docker','exec','-i',name,'mysql','--protocol=TCP','-h127.0.0.1','--batch','--skip-column-names','-u'+user], input=statement)
            if error:
                assert p.returncode != 0 and error in p.stderr, (statement, p.stderr)
            else:
                assert p.returncode == 0, p.stderr
            return p.stdout.strip()
        try:
            p = run(['docker','run','--rm','-d','--name',name,'-e','MYSQL_ALLOW_EMPTY_PASSWORD=yes','mysql:8.4', '--log-bin=binlog','--binlog-format=ROW', '--log-bin-trust-function-creators='+str(trust)])
            assert p.returncode == 0, p.stderr
            for _ in range(90):
                if run(['docker','exec',name,'mysqladmin','--protocol=TCP','-h127.0.0.1','ping','--silent']).returncode == 0:
                    break
                time.sleep(1)
            else:
                raise RuntimeError('disposable MySQL startup timeout')
            assert sql('SELECT @@log_bin, @@GLOBAL.log_bin_trust_function_creators;') == '1\t'+str(trust)
            sql("CREATE DATABASE mxmed CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER 'migration'@'127.0.0.1'; CREATE USER 'mxmed_app'@'127.0.0.1'; GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,TRIGGER,CREATE ROUTINE,ALTER ROUTINE ON mxmed.* TO 'migration'@'127.0.0.1'; GRANT SELECT,INSERT,UPDATE,DELETE ON mxmed.* TO 'mxmed_app'@'127.0.0.1';")
            assert 'SUPER' not in sql('SHOW GRANTS;', 'migration')
            for table in ['platform_audit_events','platform_audit_events_audit_v1_shadow','patient_identity_audit_events']:
                sql('USE mxmed; CREATE TABLE '+table+' (id INT PRIMARY KEY);', 'migration')
            if trust == 0:
                sql('USE mxmed;\nDELIMITER $$\n'+definitions[0]+'$$\nDELIMITER ;', 'migration', 'ERROR 1419')
                print('TRUST_OFF_NON_SUPER_ERROR1419=PASS', flush=True)
                continue
            for definition in definitions:
                trigger = re.search(r'CREATE TRIGGER (\w+)', definition)[1]
                sql('USE mxmed; DROP TRIGGER IF EXISTS '+trigger+';', 'migration')
                sql('USE mxmed;\nDELIMITER $$\n'+definition+'$$\nDELIMITER ;', 'migration')
            sql('USE mxmed; CREATE PROCEDURE probe_proc() SELECT 1; CREATE FUNCTION probe_func() RETURNS INT DETERMINISTIC RETURN 1;', 'migration')
            negatives = [
                'CREATE TRIGGER forbidden BEFORE INSERT ON platform_audit_events FOR EACH ROW SET @x=1',
                'DROP TRIGGER platform_audit_events_no_update',
                'CREATE FUNCTION forbidden() RETURNS INT DETERMINISTIC RETURN 1',
                'CREATE PROCEDURE forbidden() SELECT 1',
                'ALTER PROCEDURE probe_proc COMMENT "forbidden"',
                'ALTER FUNCTION probe_func COMMENT "forbidden"',
                'CREATE TABLE forbidden(id INT)', 'ALTER TABLE platform_audit_events ADD COLUMN x INT',
                'DROP TABLE platform_audit_events', "CREATE USER 'forbidden'@'127.0.0.1'",
                "GRANT SELECT ON mxmed.* TO 'mxmed_app'@'127.0.0.1'",
            ]
            for statement in negatives:
                sql('USE mxmed; '+statement+';', 'mxmed_app', 'denied')
            for table in ['platform_audit_events','platform_audit_events_audit_v1_shadow','patient_identity_audit_events']:
                sql('USE mxmed; INSERT INTO '+table+' VALUES (1);','mxmed_app')
                for op in ['UPDATE '+table+' SET id=2', 'DELETE FROM '+table]:
                    sql('USE mxmed; '+op+';', 'mxmed_app', 'forbidden')
            print('TRUST_ON_NON_SUPER_CREATION=PASS; APPLICATION_NEGATIVES=11 PASS; CANONICAL_APPEND_ONLY=6 PASS; MP01B_B4_TRIGGER_BODIES=PASS', flush=True)
        finally:
            run(['docker','stop',name])

if __name__ == '__main__':
    main()

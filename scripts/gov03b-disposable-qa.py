#!/usr/bin/env python3
"""Schema-only clone, fresh synthetic identities, native audit in an owned DB.
Never points a web server at mxmed; never imports normal-local rows."""
import argparse, pathlib, json, os, secrets, subprocess, re, socket, signal, time, base64, tempfile, shutil

ROOT = pathlib.Path(__file__).resolve().parents[1]


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--connection', required=True)
    ap.add_argument('--evidence', required=True)
    ap.add_argument('--suite', choices=['contracts', 'ceremony', 'evidence', 'issuer', 'ui', 'enrollment', 'resolution', 'correction'], default='contracts')
    ap.add_argument('--native-only', action='store_true', help='Run only the new resolution native contract in its owned target')
    args = ap.parse_args()
    if args.native_only and args.suite not in ['resolution','correction']:
        ap.error('--native-only is restricted to the resolution/correction suites')
    os.umask(0o077)
    cfg = json.loads(pathlib.Path(args.connection).read_text())
    assert cfg['MXMED_DB_HOST'] == '127.0.0.1' and str(cfg['MXMED_DB_PORT']) == '3306' and cfg['MXMED_DB_NAME'] == 'mxmed'
    evidence = pathlib.Path(args.evidence); evidence.mkdir(mode=0o700, parents=True, exist_ok=True)
    assert not evidence.is_symlink() and evidence.stat().st_mode & 0o077 == 0
    env = {**os.environ, 'MYSQL_PWD': cfg['MXMED_DB_PASS']}
    mysql_args = ['-h127.0.0.1', '-P3306', '-u' + cfg['MXMED_DB_USER']]

    def sql(text, db=None):
        result = subprocess.run(['mysql', *mysql_args, '-N', '-B', '-r', *([db] if db else [])], input=text, text=True, capture_output=True, env=env)
        if result.returncode:
            (evidence/'private-sql-error.log').write_text(result.stderr)
            raise RuntimeError('owned SQL preparation failed; inspect private evidence')
        return result.stdout

    suffix = secrets.token_hex(5)
    db = ('mxmed_op01_' if args.suite == 'contracts' else ('mxmed_gate4d_preview_gov03b_' if args.suite == 'ceremony' else ('mxmed_gate4d_preview_c4ev_' if args.suite == 'evidence' else ('mxmed_gate4d_preview_c4issuer_' if args.suite == 'issuer' else ('mxmed_gate4d_preview_cor01_' if args.suite == 'correction' else 'mxmed_gate4d_preview_c4ui_'))))) + suffix
    user = 'gov03b_' + suffix
    password = secrets.token_urlsafe(32)
    assert re.fullmatch(r'mxmed_(?:op01_|gate4d_preview_(?:gov03b_|c4ev_|c4issuer_|c4ui_|cor01_))[a-f0-9]{10}', db)
    sql(f"CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")
    created_user = False
    valkey = None
    valkey_log = None
    notification_dir = None
    try:
        sql(f"CREATE USER '{user}'@'127.0.0.1' IDENTIFIED BY '{password}'")
        created_user = True
        sql(f"GRANT SELECT,INSERT,UPDATE,DELETE,EXECUTE,TRIGGER ON `{db}`.* TO '{user}'@'127.0.0.1'")
        sql(f"GRANT SHOW_ROUTINE ON *.* TO '{user}'@'127.0.0.1'")
        dump = subprocess.check_output(['mysqldump', *mysql_args, '--no-data', '--skip-triggers', '--skip-add-drop-table', '--set-gtid-purged=OFF', '--column-statistics=0', '--skip-comments', 'mxmed'], env=env, text=True)
        dump = re.sub(r'AUTO_INCREMENT=\d+', '', dump).replace('`mxmed`.', '`' + db + '`.')
        assert 'INSERT INTO ' not in dump and 'USE `mxmed`' not in dump
        sql(dump, db)
        names = sql("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='mxmed' ORDER BY TRIGGER_NAME").splitlines()
        for name in names:
            assert re.fullmatch(r'[A-Za-z0-9_]+', name)
            row = sql("SELECT JSON_QUOTE(ACTION_STATEMENT),EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_TIMING FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='mxmed' AND TRIGGER_NAME='" + name + "'").strip().split('\t')
            body = json.loads(row[0]).replace('`mxmed`.', '`' + db + '`.')
            sql(f"DELIMITER $$\nCREATE DEFINER='{user}'@'127.0.0.1' TRIGGER `{name}` {row[3]} {row[1]} ON `{row[2]}` FOR EACH ROW {body}$$\nDELIMITER ;\n", db)
        for file in ['05_audit_lock.sql', '06_audit_cas.sql']:
            source = (ROOT/'modules/identity/deployment/op-act01'/file).read_text().replace('{{TARGET_SCHEMA}}', '`'+db+'`').replace('{{RESTRICTED_DEFINER}}', "'"+user+"'@'127.0.0.1'")
            sql('DELIMITER $$\n'+source.rstrip().rstrip(';')+'$$\nDELIMITER ;\n', db)
        sql((ROOT/'modules/identity/db/migrations/2026_10_16_01_governance_security_bindings.sql').read_text(), db)
        if args.suite in ['contracts', 'evidence', 'issuer', 'ui', 'enrollment', 'resolution', 'correction']:
            sql((ROOT/'modules/identity/db/migrations/2026_10_16_02_physician_account_binding.sql').read_text(), db)
        if args.suite in ['evidence', 'issuer', 'ui', 'enrollment', 'resolution', 'correction']:
            sql((ROOT/'modules/identity/db/migrations/2026_10_16_03_physician_evidence_foundation.sql').read_text(), db)
        if args.suite in ['issuer', 'ui', 'enrollment', 'resolution', 'correction']:
            sql((ROOT/'modules/identity/db/migrations/2026_10_16_04_physician_authority_issuer.sql').read_text(), db)
        if args.suite in ['enrollment', 'resolution', 'correction']:
            sql((ROOT/'modules/identity/db/migrations/2026_10_16_05_public_physician_enrollment.sql').read_text(), db)
            # Explicit synthetic commercial data; never infer FREE from a missing plan.
            sql('ALTER TABLE profiles_doctors ADD COLUMN plan_code VARCHAR(32) NULL', db)
        if args.suite in ['resolution','correction']:
            sql((ROOT/'modules/identity/db/migrations/2026_10_16_06_reviewed_physician_enrollment.sql').read_text(), db)
        if args.suite == 'correction':
            sql((ROOT/'modules/profiles/db/2026_10_16_01_public_correction_reports.sql').read_text(), db)
        sql("CREATE TABLE gov03b_qa_faults(enabled INT NOT NULL); INSERT INTO gov03b_qa_faults VALUES(0)", db)
        sql(f"DELIMITER $$\nCREATE DEFINER='{user}'@'127.0.0.1' TRIGGER gov03b_fail_audit BEFORE INSERT ON platform_audit_events FOR EACH ROW BEGIN IF (SELECT enabled FROM gov03b_qa_faults)=1 OR ((SELECT enabled FROM gov03b_qa_faults)=2 AND NEW.action='PHYSICIAN_AUTHORITY_CHANGED' AND JSON_UNQUOTE(JSON_EXTRACT(NEW.metadata_json,'$.producer_metadata.operation'))='BINDING_ACTIVE') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'; END IF; END$$\nDELIMITER ;\n", db)
        if args.suite == 'correction':
            sql(f"DELIMITER $$\nCREATE DEFINER='{user}'@'127.0.0.1' TRIGGER cor01_fail_rate BEFORE INSERT ON profiles_correction_rate_buckets FOR EACH ROW BEGIN IF (SELECT enabled FROM gov03b_qa_faults)=3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic rate storage failure'; END IF; END$$\nDELIMITER ;\n", db)
        connection = evidence/'disposable-connection.json'
        connection.write_text(json.dumps({'db': db, 'user': user, 'password': password}))
        test_env = {**os.environ, 'C4_ISSUER_QA':'0','MXMED_OP02_ISOLATED':'0','MXMED_OPERATOR_DEPLOYMENT_REHEARSAL':'0','MXMED_OPERATORS_RUNTIME_ENABLED':'0','MXMED_IDENTITY_GOVERNANCE_ENABLED':'0', 'GOV03B_QA_CONNECTION': str(connection), 'MXMED_AUDIT_IP_HMAC_SECRET': secrets.token_hex(32), 'MXMED_AUDIT_IP_HMAC_KEY_VERSION': 'audit-ip-local-v001'}
        if args.suite in ['ceremony', 'evidence', 'issuer', 'ui', 'enrollment', 'resolution', 'correction']:
            notification_dir = pathlib.Path(tempfile.mkdtemp(prefix='mxmed-gov03b-notifications-', dir='/tmp')); notification_dir.chmod(0o700)
            with socket.socket() as reservation:
                reservation.bind(('127.0.0.1', 0)); port = reservation.getsockname()[1]
            valkey_log = (evidence/'valkey-private.log').open('wb')
            binary = pathlib.Path.home()/'Library/Application Support/MXMED/local-identity-runtime/valkey-server'
            valkey = subprocess.Popen([str(binary),'--bind','127.0.0.1','--protected-mode','yes','--port',str(port),'--save','','--appendonly','no','--dir',str(evidence)], stdin=subprocess.DEVNULL, stdout=valkey_log, stderr=valkey_log, start_new_session=True)
            for _ in range(30):
                if valkey.poll() is not None: raise RuntimeError('owned Valkey failed; no existing process is replaced')
                try:
                    with socket.create_connection(('127.0.0.1',port),timeout=.1): break
                except OSError: time.sleep(.1)
            else: raise RuntimeError('owned Valkey unavailable')
            test_env.update({'APP_ENV':'local','MXMED_ENVIRONMENT':'local','MXMED_PREVIEW_EXPLICIT':'1','MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT':'0',
                'MXMED_DB_HOST':'127.0.0.1','MXMED_DB_PORT':'3306','MXMED_DB_NAME':db,'MXMED_DB_USER':user,'MXMED_DB_PASS':password,
                'MXMED_PREVIEW_PEPPER':secrets.token_hex(32),'MXMED_PREVIEW_ORIGIN':'https://localhost:8443','MXMED_SESSION_STORE_PORT':str(port),
                'MXMED_PREVIEW_NOTIFICATION_FILE':str(notification_dir/'notifications.json'),'MXMED_GOVERNANCE_CEREMONY_ENABLED':'1',
                'MXMED_STAFF_MFA_ENCRYPTION_KEY':base64.b64encode(secrets.token_bytes(32)).decode(),'MXMED_STAFF_MFA_KEY_ID':'gov03b-test-only',
                'MXMED_GOVERNANCE_ORGANIZATION_ID':'mxmed-synthetic','GOV03B_QA_EVIDENCE':str(evidence)})
        if args.suite in ['evidence', 'issuer', 'ui', 'enrollment', 'resolution', 'correction']:
            test_env['MXMED_GOVERNANCE_CEREMONY_ENABLED'] = '0'
            test_env['C4_EV_QA_STORAGE_ROOT'] = str(notification_dir/'private-c4')
        if args.suite in ['issuer', 'resolution']:
            test_env['C4_ISSUER_QA'] = '1'
        if args.suite in ['ui', 'enrollment', 'resolution', 'correction']:
            test_env['C4_EV_QA_KEY'] = base64.b64encode(secrets.token_bytes(32)).decode()
            test_env['C4_UI_QA_SMS'] = str(notification_dir/'sms.json')
        if args.suite in ['enrollment', 'resolution', 'correction']: test_env.update({'PUB_ENR02_QA':'1','PUB_ENR02_DEPLOYMENT_CONNECTION':str(pathlib.Path(args.connection).resolve())})
        if args.suite == 'resolution': test_env['PUB_ENR03_QA']='1'
        if args.suite == 'correction': test_env.update({'PUB_COR01_QA':'1','PUB_COR01_KEY':base64.b64encode(secrets.token_bytes(32)).decode()})
        test_name = {'contracts':'GovernanceGov03BTest.php', 'ceremony':'GovernanceCeremonyIntegrationTest.php', 'evidence':'C4EvidenceIntegrationTest.php', 'issuer':'PhysicianAuthorityIssuerIntegrationTest.php', 'ui':'PhysicianOnboardingUiFixture.php', 'enrollment':'PublicEnrollmentIntegrationTest.php', 'resolution':'ReviewedEnrollmentIntegrationTest.php', 'correction':'../../profiles/tests/CorrectionReportIntegrationTest.php'}[args.suite]
        result = subprocess.run(['php', str(ROOT/'modules/identity/tests'/test_name)], cwd=ROOT, env=test_env, capture_output=True, text=True)
        (evidence/'disposable-governance.log').write_text(result.stdout+result.stderr)
        print(result.stdout, end='')
        if result.returncode:
            print('DISPOSABLE_GOVERNANCE=FAIL; inspect private log')
            raise SystemExit(result.returncode)
        if args.suite == 'issuer':
            # The application principal deliberately has NO DDL or temporary-table grant.
            # Only this owning runner rehearses migrations, always against its fresh DB.
            before_guard = sql('SHOW CREATE TABLE profiles_physician_admissions; SHOW CREATE TABLE auth_account_professional_bindings', db)
            guard = subprocess.run(['mysql', *mysql_args, db], input=(ROOT/'modules/identity/db/migrations/2026_10_16_04_physician_authority_issuer.sql').read_text(), text=True, capture_output=True, env=env)
            (evidence/'populated-migration-guard.log').write_text(guard.stderr)
            if guard.returncode == 0 or 'ERROR 3819' not in guard.stderr:
                raise RuntimeError('prepared issuer migration did not reject populated authority')
            if sql('SHOW CREATE TABLE profiles_physician_admissions; SHOW CREATE TABLE auth_account_professional_bindings', db) != before_guard:
                raise RuntimeError('populated migration guard changed authority schema')
            print('PASS prepared_04_rejects_populated_authorities_before_DDL')
            print('PASS guard_failure_preserves_existing_authority_schema')
        if args.suite == 'ui':
            result = subprocess.run(['python3', str(ROOT/'scripts/c4-ui01-browser-qa.py')], cwd=ROOT, env=test_env, capture_output=True, text=True)
            (evidence/'browser-qa.log').write_text(result.stdout+result.stderr)
            print(result.stdout, end='')
            if result.returncode: raise RuntimeError('disposable original UI failed; inspect private log')
        if args.suite in ['enrollment', 'resolution'] and not args.native_only:
            result = subprocess.run(['python3', str(ROOT/('scripts/pub-enr03-browser-qa.py' if args.suite == 'resolution' else 'scripts/pub-enr02-browser-qa.py'))], cwd=ROOT, env=test_env, capture_output=True, text=True)
            (evidence/'browser-qa.log').write_text(result.stdout+result.stderr)
            print(result.stdout, end='')
            if result.returncode: raise RuntimeError('disposable public enrollment failed; inspect private log')
        if args.suite == 'correction' and not args.native_only:
            result = subprocess.run(['python3', str(ROOT/'scripts/pub-cor01-browser-qa.py')], cwd=ROOT, env=test_env, capture_output=True, text=True)
            (evidence/'browser-qa.log').write_text(result.stdout+result.stderr)
            print(result.stdout, end='')
            if result.returncode: raise RuntimeError('disposable correction reports failed; inspect private log')
        if args.suite == 'ceremony':
            result = subprocess.run(['python3', str(ROOT/'scripts/gov03b-http-qa.py')], cwd=ROOT, env=test_env, capture_output=True, text=True)
            (evidence/'http-governance.log').write_text(result.stdout+result.stderr)
            print(result.stdout, end='')
            if result.returncode: raise RuntimeError('disposable HTTP failed; inspect private log')
    finally:
        if valkey is not None and valkey.poll() is None:
            os.killpg(valkey.pid, signal.SIGTERM); valkey.wait(timeout=10)
        if valkey_log is not None: valkey_log.close()
        if notification_dir is not None: shutil.rmtree(notification_dir)
        sql(f"DROP DATABASE `{db}`")
        if created_user: sql(f"DROP USER '{user}'@'127.0.0.1'")
        if (evidence/'disposable-connection.json').exists(): (evidence/'disposable-connection.json').unlink()
        (evidence/'disposal.json').write_text(json.dumps({'database_deleted': True, 'temporary_sql_user_deleted': created_user, 'normal_rows_imported': 0, 'normal_sql_writes': 0, 'audit_routines_target': db, 'persistent_qa_touched': False}, indent=2))
        print('OWNED_DATABASE_AND_SQL_USER_DELETED=true')


if __name__ == '__main__':
    main()

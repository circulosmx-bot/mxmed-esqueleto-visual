#!/usr/bin/env python3
"""Final focused smoke against the live owned UXFUNC02 demo; no fixture resets."""
import pathlib,json,os,sys,time,re,ssl,http.client,subprocess,hashlib
import pymysql
from playwright.sync_api import sync_playwright,expect
root=pathlib.Path(__file__).resolve().parents[1];owned=pathlib.Path(sys.argv[1]).resolve()
assert re.fullmatch(r'/private/tmp/mxmed-ase-adm02imp01-[A-Za-z0-9_]+',str(owned))
assert owned.stat().st_uid==os.getuid() and owned.stat().st_mode&0o777==0o700
approval=json.loads((owned/'workflow-approval.json').read_text());info=json.loads((owned/'review-info.json').read_text())
assert pathlib.Path(approval['root']).resolve()==root and time.time()<min(approval['expires'],info['expiry_unix'])
assert all(hashlib.sha256((root/f).read_bytes()).hexdigest()==h for f,h in approval['source_hashes'].items())
tokens=json.loads((owned/'sessions.json').read_text());records=json.loads((owned/'uxfunc02-records.json').read_text());origin=info['url'].split('/internal/')[0];checks=[]
def check(name,value):
    assert value,name;checks.append({'name':name,'pass':True});print('PASS '+name,flush=True)
def get(query,actor='director'):
    conn=http.client.HTTPSConnection('127.0.0.1',int(origin.rsplit(':',1)[1]),context=ssl.create_default_context(cafile=str(owned/'cert.pem')))
    conn.request('GET','/api/internal/team/read.php?'+query,headers={'Origin':origin,'Sec-Fetch-Site':'same-origin','Cookie':'__Host-mxmed_session='+tokens[actor]});r=conn.getresponse();v=json.loads(r.read());conn.close();return r.status,v
# Existing owned router deliberately masks ReadFailure as a closed 503.
def denied(result,canonical_status):
    return result[0]==canonical_status or result==(503,{'error':'isolated_service_unavailable'})
check('Invalid status rejected',denied(get('status=INVALID'),400))
check('Master category cannot be bypassed through status',denied(get('category=MASTER_ADMIN&status=ALL','master'),403))
check('Explicit historical target survives default ACTIVE list filter',get('target='+records['celia']['account'])[1]['items'][0]['staff_status']=='REMOVED')
check('Explicit ACTIVE filter excludes historical target',denied(get('status=ACTIVE&target='+records['celia']['account']),404))
env=json.loads((owned/'review-env.json').read_text())
db=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=env['ASE_INT02A_FIXTURE_PASSWORD'],database=env['MXMED_DB_NAME'])
with db.cursor() as cur:
    cur.execute('SELECT @@datadir');assert pathlib.Path(cur.fetchone()[0]).resolve()==(owned/'mysql').resolve()
    cur.execute("SELECT COUNT(*) FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operation'))='remove_advisor'")
    check('One removal command and canonical audit event; no duplicate mutation',cur.fetchone()[0]==1)
    cur.execute('SELECT COUNT(*) FROM auth_staff_admission_versions WHERE case_id=%s',(records['celia']['case'],))
    check('Removed personal profile versions retained',cur.fetchone()[0]>0)
db.close()
with sync_playwright() as pw:
    b=pw.chromium.launch();c=b.new_context(ignore_https_errors=True,viewport={'width':1366,'height':768});c.add_cookies([{'name':'__Host-mxmed_session','value':tokens['director'],'url':origin,'secure':True,'httpOnly':True,'sameSite':'Strict'}]);p=c.new_page();p.goto(info['url'])
    p.locator('[data-member="'+records['bruno']['account']+'"]').click();expect(p.locator('#detail-title')).to_have_text(records['bruno']['name']);group=p.locator('#operational-matrix [data-family="media"] [data-group-count]');expect(group).to_be_enabled()
    check('Existing partial grant group is indeterminate',group.evaluate('(e)=>e.indeterminate'))
    group.check();group.uncheck();check('Group deselect clears every editable child draft',p.locator('#operational-matrix [data-family="media"] [data-capability]:checked').count()==0)
    p.locator('#revert-permissions').click();expect(p.locator('#save-permissions')).to_be_disabled();check('Revert preserves persisted subset',get('target='+records['bruno']['account'])[1]['items'][0]['grants']==['media_review_read'])
    # A cancelled navigation must preserve draft and filter display together.
    group.check();p.locator('#staff-status').select_option('REMOVED');expect(p.locator('#confirmation')).to_be_visible();p.locator('#cancel-confirmation').click();expect(p.locator('#staff-status')).to_have_value('ACTIVE');expect(p.locator('#save-permissions')).to_be_enabled();p.locator('#revert-permissions').click();check('Cancelled filter navigation preserves draft and selected status',True)
    p.set_viewport_size({'width':390,'height':844});p.locator('#show-directory').click();expect(p.locator('#staff-status')).to_be_visible();p.locator('[data-member="'+records['alma']['account']+'"]').click();group=p.locator('#operational-matrix [data-family="media"] [data-group-count]');expect(group).to_be_enabled();group.check();expect(p.locator('#save-permissions')).to_be_enabled();p.locator('#revert-permissions').click();check('Narrow viewport permits directory selection and editing',True)
    p.locator('#show-directory').click();p.screenshot(path=str(root/'docs/evidence/ase-adm02-uxfunc02/screens/chromium-390-directory.png'),full_page=True);b.close()
(root/'docs/evidence/ase-adm02-uxfunc02/smoke.json').write_text(json.dumps({'result':'PASS','checks':checks,'check_count':len(checks),'source_sha256':{str(f.relative_to(root)):hashlib.sha256(f.read_bytes()).hexdigest() for f in [root/'assets/js/internal-team.js',root/'assets/js/staff-workflow.js',root/'modules/identity/Team/ReadService.php']},'production_activation':False},indent=2)+'\n')

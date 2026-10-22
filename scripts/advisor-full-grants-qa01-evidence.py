#!/usr/bin/env python3
"""Read-only owned SQL reconciliation; emits no credentials/session identifiers."""
import pathlib,sys,json,importlib.util
ROOT=pathlib.Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('qa01',ROOT/'scripts/advisor-full-grants-qa01-browser.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
o=m.O;out=m.OUT;rows=[]
for key in ['test','demo']:
 actor=json.loads((o/('full-'+key+'.json')).read_text());events=m.sql("SELECT event_id,real_actor_reference,metadata_json,occurred_at_utc FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED' AND resource_reference=%s ORDER BY occurred_at_utc",(actor['account'],));grants=[];revokes=[];revoke_seen=False
 for event,who,raw,at in events:
  meta=json.loads(raw)['producer_metadata'];op=meta['operation'];cap=meta['capability'];assert who=='account:qa_director'
  if op not in ['grant','revoke']:continue
  if op=='grant':assert not revoke_seen;grants.append(cap)
  else:revoke_seen=True;revokes.append(cap)
  rows.append(dict(account=actor['account'],event_id=event,actor=who,operation=op,capability=cap,at=str(at)))
 assert sorted(grants)==sorted(m.CAPS) and len(set(grants))==14
 assert len(set(revokes))==len(revokes) and len(revokes)==(14 if key=='test' else 0)
 actual=[r[0] for r in m.sql("SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE' AND revoked_at IS NULL",(actor['account'],))]
 assert sorted(actual)==([] if key=='test' else sorted(m.CAPS))
 assert m.sql('SELECT COUNT(*) FROM auth_staff_admission_completions WHERE case_id=%s',(actor['case'],))[0][0]==1
(out/'canonical-grant-audit.json').write_text(json.dumps(rows,indent=2))
test=json.loads((o/'full-test.json').read_text());events=m.sql("SELECT action,outcome,metadata_json FROM platform_audit_events WHERE real_actor_reference=%s",('account:'+test['account'],));operations={};actions={}
for action,outcome,raw in events:
 if outcome!='SUCCESS':continue
 meta=json.loads(raw).get('producer_metadata',{});op=meta.get('operation') if isinstance(meta,dict) else None;actions[action]=actions.get(action,0)+1
 if op:operations[op]=operations.get(op,0)+1
assert operations.get('REQUEST_APPROVED',0)>=2 and operations.get('EVIDENCE_READ',0)>=4
proofs=m.sql("SELECT COUNT(*) FROM auth_staff_mfa_proofs WHERE actor_account_id=%s AND status='CONSUMED'",(test['account'],))[0][0];assert proofs>=2
(out/'consumer-audit.json').write_text(json.dumps({'actor':test['account'],'actions':actions,'request_operations':operations,'consumed_mfa_proofs':proofs,'session_tokens_exported':False},indent=2))
print('CANONICAL_AUDIT_PASS 28 grants, 14 distinct cumulative revokes; two admitted identities; actual Advisor evidence/decisions/MFA')

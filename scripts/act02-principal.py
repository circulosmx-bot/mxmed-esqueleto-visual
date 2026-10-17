"""Table grants for the disposable ACT02 restricted application principal only."""
import re
def restrict(sql,db,user,synthetic=False,professional_sources=False):
 assert re.fullmatch(r'mxmed_gate4d_preview_(?:act02|cor02|c4ui)_[a-f0-9]{10}',db)
 assert re.fullmatch(r'(?:act02|gov03b)_[a-f0-9]{10}',user) and user.endswith(db.rsplit('_',1)[1])
 who="'"+user+"'@'127.0.0.1'"
 # Existing owning QA may have broader fixture privileges. No global grants.
 sql('REVOKE ALL PRIVILEGES, GRANT OPTION FROM '+who)
 sql('GRANT REFERENCES ON `'+db+'`.* TO '+who)
 # MySQL requires LOCK TABLES (or write privileges) for SELECT FOR UPDATE.
 # Preserve canonical authority locks while staff/grant/membership rows stay read-only.
 sql('GRANT LOCK TABLES ON `'+db+'`.* TO '+who)
 names=sql("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='"+db+"' AND TABLE_TYPE='BASE TABLE'").splitlines()
 readonly=[t for t in names if t.startswith(('auth_','profiles_','subscription_','platform_audit_','consultorio','agenda_consultorio_')) or t in ['internal_staff','internal_operator_grants','doctor_contact_points','profile_subscriptions','media_assets']]
 writable=[t for t in names if t.startswith(('profiles_correction_','auth_physician_')) or t in ['profiles_physician_admissions','auth_account_professional_bindings','profiles_contact_correction_versions','doctor_contact_points','auth_accounts','auth_account_credentials','auth_account_consents','auth_account_one_time_tokens','auth_rate_limit_buckets','auth_identity_notification_deliveries','auth_account_phone_verifications','auth_staff_mfa_challenges','auth_staff_mfa_proofs','auth_governance_cases','platform_audit_events','platform_audit_stream_heads']]
 for t in readonly:sql('GRANT SELECT ON `'+db+'`.`'+t+'` TO '+who)
 for t in writable:sql('GRANT INSERT,UPDATE ON `'+db+'`.`'+t+'` TO '+who)
 if professional_sources:
  for t in ['auth_professional_source_requests','auth_professional_source_operations']:sql('GRANT INSERT,UPDATE ON `'+db+'`.`'+t+'` TO '+who)
  for t in ['auth_professional_source_files','auth_professional_source_reads','auth_professional_source_journal','profiles_verified_identities','profiles_doctor_credentials']:sql('GRANT INSERT ON `'+db+'`.`'+t+'` TO '+who)
 # Runtime verifies existing factors; enrollment/recovery require another principal.
 sql('GRANT UPDATE(last_totp_time_step,enrollment_context) ON `'+db+'`.`auth_staff_mfa_factors` TO '+who)
 sql('GRANT EXECUTE ON PROCEDURE `'+db+'`.`act02_trigger_metadata` TO '+who)
 if synthetic:sql('GRANT SELECT,UPDATE ON `'+db+'`.`gov03b_qa_faults` TO '+who);sql('GRANT DELETE ON `'+db+'`.`auth_rate_limit_buckets` TO '+who)
 for name in ['audit_mp01c_lock_stream_head_v1','audit_mp01c_advance_stream_head_cas_v1']:sql('GRANT EXECUTE ON PROCEDURE `'+db+'`.`'+name+'` TO '+who)

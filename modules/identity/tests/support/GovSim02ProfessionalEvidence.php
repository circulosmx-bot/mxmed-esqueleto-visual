<?php
declare(strict_types=1);

/** Native disposable C4/ADM02 evidence simulation; never a production issuer. */
function govSim02ProfessionalEvidence(PDO $p,array $s,BClock $clock,array $reviewer):void
{
    if(PHP_SAPI!=='cli'||!preg_match('/^mxmed_op01_[a-f0-9]{10}$/D',(string)$p->query('SELECT DATABASE()')->fetchColumn()))
        throw new RuntimeException('sim02_disposable_only');
    $account='sim02_physician_test';$staff=$reviewer['context']->accountId();
    foreach([$account,'sim02_nonmedical_test','sim02_operator_test'] as $id){
        $p->prepare("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(?,?,?,'active',UTC_TIMESTAMP())")->execute([$id,$id.'@example.invalid',$id.'@example.invalid']);
        $p->prepare('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at,credential_version) VALUES(?,?,UTC_TIMESTAMP(),1)')->execute([$id,password_hash(B_PASSWORD,PASSWORD_ARGON2ID)]);
    }
    [$profile,$membership]=profileB($p,'sim02_physician',$account);
    [$nonmedical,$nonmedicalMember]=profileB($p,'sim02_nonmedical','sim02_nonmedical_test');
    [$operator,$operatorMember]=profileB($p,'sim02_operator','sim02_operator_test');
    $p->prepare("UPDATE auth_account_memberships SET assignment_source='owner_operator_invitation' WHERE membership_id=?")->execute([$operatorMember]);
    $p->prepare("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),?,'physician_accreditation_review','ACTIVE')")->execute([$staff]);
    $q=$p->prepare("INSERT INTO profiles_verified_identities(doctor_id,given_names,first_surname,source_type,source_reference,verified_at,verified_by_account_id) VALUES(?,'Synthetic','Physician','internal_provisioning','sim02-disposable-document',UTC_TIMESTAMP(),?)");$q->execute([$profile,$staff]);$identity=(int)$p->lastInsertId();
    $q=$p->prepare("INSERT INTO profiles_doctor_credentials(doctor_id,credential_type,license_number,professional_area_label,institution_name,verification_status,lifecycle_status,verified_at,verified_by_account_id,source_type,source_reference) VALUES(?,'PROFESSIONAL','SIM02-NOT-A-REAL-LICENSE','Synthetic area','Synthetic institution','VERIFIED','ACTIVE',UTC_TIMESTAMP(),?,'internal_provisioning','sim02-disposable-credential')");$q->execute([$profile,$staff]);$credential=(int)$p->lastInsertId();
    $admission=uuidB();$binding=uuidB();$date=$clock->now()->format('Y-m-d H:i:s.u');
    $p->prepare("INSERT INTO profiles_physician_admissions(admission_id,doctor_id,verified_identity_id,professional_credential_id,status,reviewed_by_account_id,case_reference,decision_reference,audit_reference,evidence_reference,approved_at,valid_from) VALUES(?,?,?,?,'APPROVED',?,'sim02-case','sim02-decision','sim02-synthetic-audit','sim02-human-review-fixture',?,?)")->execute([$admission,$profile,$identity,$credential,$staff,$date,$date]);
    $q=$p->prepare('SELECT * FROM profiles_verified_identities WHERE verified_identity_id=?');$q->execute([$identity]);$identityRow=$q->fetch(PDO::FETCH_ASSOC);
    $q=$p->prepare('SELECT * FROM profiles_doctor_credentials WHERE credential_id=?');$q->execute([$credential]);$credentialRow=$q->fetch(PDO::FETCH_ASSOC);
    $p->prepare("INSERT INTO auth_account_professional_bindings(binding_id,account_id,profile_doctor_id,admission_id,admission_version,verified_identity_id,identity_revision_hash,credential_revision_hash,status,relationship_evidence_reference,acceptance_reference,audit_reference,valid_from) VALUES(?,?,?,?,1,?,?,?,'ACTIVE','sim02-attended-account-proof','sim02-independent-acceptance','sim02-synthetic-audit',?)")
        ->execute([$binding,$account,$profile,$admission,$identity,\Identity\Repositories\ProfessionalAccountBindingProjection::identityRevision($identityRow),\Identity\Repositories\ProfessionalAccountBindingProjection::credentialRevision($credentialRow),$date]);
    $projection=new \Identity\Repositories\ProfessionalAccountBindingProjection($p,$clock);
    $gate=new \Identity\Adapters\CanonicalInitialOwnerEligibilityAdapter($projection);
    $deny=function(string $sql,array $params,string $label)use($p,$gate,$account,$profile):void{
        $p->beginTransaction();try{$p->prepare($sql)->execute($params);denyB(fn()=>$gate->assertEligible($p,$account,$profile),$label);}finally{$p->rollBack();}
    };
    foreach([[$nonmedical,'sim02_nonmedical_test','SIM02_nonmedical_candidate_denied'],[$operator,'sim02_operator_test','SIM02_operator_candidate_denied'],[$nonmedical,$account,'SIM02_wrong_profile_denied']] as [$target,$subject,$label]){
        $p->beginTransaction();try{denyB(fn()=>$gate->assertEligible($p,$subject,$target),$label);}finally{$p->rollBack();}
    }
    $deny("UPDATE profiles_doctor_credentials SET verification_status='PENDING_REVIEW' WHERE credential_id=?",[$credential],'SIM02_unverified_physician_denied');
    $deny("UPDATE auth_account_professional_bindings SET status='REVOKED' WHERE binding_id=?",[$binding],'SIM02_revoked_binding_denied');
    // Exact boundary, independent of wall-clock passage.
    $clock->advance('+2 seconds');
    $deny('UPDATE profiles_physician_admissions SET valid_until=? WHERE admission_id=?',[$clock->now()->modify('-1 second')->format('Y-m-d H:i:s.u'),$admission],'SIM02_expired_accreditation_denied');
    $deny('UPDATE profiles_physician_admissions SET authority_version=2 WHERE admission_id=?',[$admission],'SIM02_changed_approval_version_denied');
    $deny("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=UTC_TIMESTAMP() WHERE account_id=? AND capability='physician_accreditation_review'",[$staff],'SIM02_staff_without_review_capability_denied');
    $deny("UPDATE auth_account_memberships SET assignment_source='owner_operator_invitation' WHERE membership_id=?",[$membership],'SIM02_operator_provenance_cannot_promote');
    $deny("UPDATE profiles_verified_identities SET source_reference='changed-evidence' WHERE verified_identity_id=?",[$identity],'SIM02_identity_revision_changed_denied');
    $deny("UPDATE profiles_doctor_credentials SET license_number='CHANGED-SYNTHETIC' WHERE credential_id=?",[$credential],'SIM02_credential_revision_changed_denied');
    $bindings=new \Identity\Services\GovernanceCaseSecurity($p,$s['security'],$gate,null,$s['audit']);
    $cases=new \Identity\Services\R3ProfileGovernanceService($p,$s['mfa'],new \Identity\Adapters\PdoProfileOwnershipApproverAuthorityAdapter($p),$clock,security:$bindings);
    $approvals=new \Identity\Adapters\PdoProfileOwnershipApprovalAdapter($p,new \Identity\Adapters\PdoProfileOwnershipApproverAuthorityAdapter($p),$clock);
    $proofs=new \Identity\Repositories\ProfileOwnershipStepUpRepository($p);
    $roles=new \Identity\Services\ProfileMembershipRoleChangeService($p,new \Identity\Repositories\AccountMembershipRepository($p),$approvals,new \Identity\Adapters\PdoProfileOwnershipStepUpAdapter($p,$proofs,$clock),new \Identity\Adapters\CanonicalProfileMembershipRoleChangeAuditAdapter($p),$clock,new \Identity\Adapters\PdoGovernanceCaseFinalizationAdapter($p,$clock),eligibility:$gate,security:$bindings);
    $runtime=new \Identity\Services\GovernanceRuntime($p,$cases,$s['mfa'],new \Identity\Services\ProfileOwnershipStepUpIssuanceService($p,$s['auth'],$proofs,$clock),$roles,$clock);
    $physician=loginB($s,$account);$case=$cases->openInitialOwnerCase($reviewer['context'],$membership,'Synthetic accredited routine assignment');
    $one=$s;$one['cases']=$cases;endorseB($one,$clock,$reviewer,$case);
    $step=$runtime->issueBeneficiaryStepUp($physician['context'],$case['case_id'],$account.'@example.invalid',B_PASSWORD,['ip'=>'synthetic']);
    $runtime->finalizeInitialOwner($physician['context'],$case['case_id'],$step['step_up_proof']);
    checkB(scalarB($p,'SELECT role_code FROM auth_account_memberships WHERE membership_id=?',[$membership])==='owner','SIM02_single_staff_and_independent_physician_acceptance');
    checkB((int)scalarB($p,'SELECT COUNT(*) FROM auth_governance_case_endorsements WHERE case_id=?',[$case['case_id']])===1,'SIM02_exactly_one_routine_staff_approval');
    checkB((int)scalarB($p,'SELECT COUNT(*) FROM auth_external_trust_anchor_evidence WHERE operation_id=?',[$case['operation_id']])===0,'SIM02_no_external_or_root_ceremony');
    $p->beginTransaction();try{$gate->assertEligible($p,$account,$profile);checkB(true,'SIM02_future_prescription_owner_professional_evidence_eligible_only');}finally{$p->rollBack();}
    require_once dirname(__DIR__,4).'/api/_lib/clinical_professional_authority.php';
    foreach(['PRESCRIPTION_SIGN','PRESCRIPTION_ISSUE'] as $operation){
        denyB(fn()=>clinical_professional_authority_assert(['doctor_id'=>$profile,'user_id'=>'sim02_operator_test','role_code'=>'owner'],$operation),'SIM02_operator_'.$operation.'_denied');
        denyB(fn()=>clinical_professional_authority_assert(['doctor_id'=>$profile,'user_id'=>'sim02_nonmedical_test','role_code'=>'owner'],$operation),'SIM02_owner_without_binding_'.$operation.'_denied');
        denyB(fn()=>clinical_professional_authority_assert(['doctor_id'=>$profile,'user_id'=>$account,'role_code'=>'owner'],$operation),'SIM02_real_signing_not_activated_'.$operation);
    }
}

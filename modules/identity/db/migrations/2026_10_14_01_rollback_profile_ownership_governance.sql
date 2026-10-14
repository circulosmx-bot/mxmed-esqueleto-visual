-- Recovery is destructive only to unused QA02B evidence; never apply after enabling live issuance.
DROP TABLE IF EXISTS `auth_profile_ownership_step_up_proofs`;
DROP TABLE IF EXISTS `auth_profile_ownership_approvals`;

<?php
declare(strict_types=1);
namespace Identity\Http;

use PDO;

/** Metadata-only readiness check: never migrates, seeds or reads credential values. */
final class NormalLocalIdentitySchema
{
    public static function assertReady(PDO $pdo): void
    {
        $required = [
            'auth_accounts'=>['account_id','email_address','email_normalized','status','email_verified_at','created_at','updated_at'],
            'auth_account_credentials'=>['account_id','password_hash','password_changed_at','credential_version','created_at','updated_at'],
            'auth_account_memberships'=>['membership_id','account_id','profile_doctor_id','entity_group_id','role_code','scope_code','status'],
            'auth_rate_limit_buckets'=>['bucket_id','operation_code','dimension_code','dimension_key_hash','window_started_at','attempts_count','blocked_until','created_at','updated_at'],
            'profiles_doctors'=>['doctor_id'], 'medical_groups'=>['group_id'],
        ];
        try {
            $stmt = $pdo->query('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()');
            $columns = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $columns[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
        } catch (\Throwable) { throw new \RuntimeException('normal_local_schema_unavailable'); }
        foreach ($required as $table=>$names) {
            if (array_diff($names, $columns[$table] ?? []) !== []) {
                // Only allowlisted structural names; no database credentials or account information.
                throw new \RuntimeException('normal_local_schema_missing:'.$table);
            }
        }
    }
}

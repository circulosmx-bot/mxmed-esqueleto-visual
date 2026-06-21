<?php
declare(strict_types=1);

namespace Subscriptions\Repositories;

use PDO;

final class SubscriptionContractAcceptanceRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function insert(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO subscription_contract_acceptances (
                uuid,
                entity_type,
                entity_id,
                doctor_id,
                profile_id,
                subscription_id,
                plan_code,
                billing_period,
                duration_days,
                contract_version,
                contract_hash,
                contract_snapshot_url,
                contract_title,
                accepted_at,
                accepted_by_user_id,
                accepted_by_actor_role,
                accepted_by_operator_id,
                acceptance_source,
                ip_address,
                user_agent,
                status,
                source,
                notes,
                deleted_at
            ) VALUES (
                :uuid,
                :entity_type,
                :entity_id,
                :doctor_id,
                :profile_id,
                :subscription_id,
                :plan_code,
                :billing_period,
                :duration_days,
                :contract_version,
                :contract_hash,
                :contract_snapshot_url,
                :contract_title,
                :accepted_at,
                :accepted_by_user_id,
                :accepted_by_actor_role,
                :accepted_by_operator_id,
                :acceptance_source,
                :ip_address,
                :user_agent,
                :status,
                :source,
                :notes,
                NULL
            )'
        );

        $stmt->execute([
            'uuid' => $data['uuid'],
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'doctor_id' => $data['doctor_id'],
            'profile_id' => $data['profile_id'],
            'subscription_id' => $data['subscription_id'],
            'plan_code' => $data['plan_code'],
            'billing_period' => $data['billing_period'],
            'duration_days' => $data['duration_days'],
            'contract_version' => $data['contract_version'],
            'contract_hash' => $data['contract_hash'],
            'contract_snapshot_url' => $data['contract_snapshot_url'],
            'contract_title' => $data['contract_title'],
            'accepted_at' => $data['accepted_at'],
            'accepted_by_user_id' => $data['accepted_by_user_id'],
            'accepted_by_actor_role' => $data['accepted_by_actor_role'],
            'accepted_by_operator_id' => $data['accepted_by_operator_id'],
            'acceptance_source' => $data['acceptance_source'],
            'ip_address' => $data['ip_address'],
            'user_agent' => $data['user_agent'],
            'status' => $data['status'],
            'source' => $data['source'],
            'notes' => $data['notes'],
        ]);
    }
}

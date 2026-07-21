<?php
declare(strict_types=1);

namespace Agenda\Security;

use Platform\Contracts\RiskLevel;

/** One private-resource permission for one concrete HTTP method. */
final readonly class PrivateAgendaMethodRule
{
    /** @param list<string> $allowedRoles */
    public function __construct(
        private string $resource,
        private string $method,
        private array $allowedRoles,
        private bool $ownershipRequired,
        private bool $operatorAllowed,
        private string $risk,
        private string $action,
        private string $scope,
        private bool $failClosed = true
    ) {
        if ($this->resource === '' || $this->method === '' || $this->allowedRoles === [] || $this->scope === '') {
            throw new \InvalidArgumentException('route_method_rule_incomplete');
        }
        if (!in_array($this->method, ['GET', 'POST', 'PUT', 'PATCH'], true)) {
            throw new \InvalidArgumentException('route_method_unsupported');
        }
        if (array_intersect($this->allowedRoles, ['*', 'all', 'admin.everything', 'support.all']) !== []) {
            throw new \InvalidArgumentException('route_method_rule_wildcard_forbidden');
        }
        RiskLevel::assertValid($this->risk);
    }

    public function resource(): string { return $this->resource; }
    public function method(): string { return $this->method; }
    /** @return list<string> */
    public function allowedRoles(): array { return $this->allowedRoles; }
    public function ownershipRequired(): bool { return $this->ownershipRequired; }
    public function operatorAllowed(): bool { return $this->operatorAllowed; }
    public function risk(): string { return $this->risk; }
    public function action(): string { return $this->action; }
    public function scope(): string { return $this->scope; }
    public function failClosed(): bool { return $this->failClosed; }
}

final class PrivateAgendaRoutePolicy
{
    /** @return list<PrivateAgendaMethodRule> */
    public static function rules(): array
    {
        $standard = ['owner', 'administrator', 'collaborator'];
        $ownerAdministrator = ['owner', 'administrator'];
        return [
            new PrivateAgendaMethodRule('appointments', 'GET', $standard, true, true, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('appointments', 'POST', $standard, true, true, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('appointments', 'PATCH', $standard, true, true, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('patients', 'GET', $standard, true, true, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('consultorios', 'GET', $standard, true, true, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('consultorios', 'PUT', $ownerAdministrator, true, false, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('availability', 'GET', $standard, true, true, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('availability', 'POST', $standard, true, true, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('availability', 'PATCH', $standard, true, true, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('schedule', 'GET', $ownerAdministrator, true, false, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('schedule', 'PUT', $ownerAdministrator, true, false, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('settings', 'GET', ['owner'], true, false, RiskLevel::R2, 'configure', 'profile'),
            new PrivateAgendaMethodRule('settings', 'PUT', ['owner'], true, false, RiskLevel::R2, 'configure', 'profile'),
            new PrivateAgendaMethodRule('waitlist', 'GET', $standard, true, true, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('waitlist', 'POST', $standard, true, true, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('waitlist', 'PATCH', $standard, true, true, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('operators', 'GET', ['owner'], true, false, RiskLevel::R2, 'access', 'profile'),
            new PrivateAgendaMethodRule('operators', 'POST', ['owner'], true, false, RiskLevel::R2, 'access', 'profile'),
            new PrivateAgendaMethodRule('operators', 'PATCH', ['owner'], true, false, RiskLevel::R2, 'access', 'profile'),
            new PrivateAgendaMethodRule('medical-groups', 'GET', $ownerAdministrator, true, false, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('medical-groups', 'POST', $ownerAdministrator, true, false, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('geocode', 'GET', $ownerAdministrator, true, false, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaMethodRule('geocode', 'POST', $ownerAdministrator, true, false, RiskLevel::R1, 'access', 'profile'),
        ];
    }

    /** @return list<string> */
    public static function resources(): array
    {
        $resources = [];
        foreach (self::rules() as $rule) {
            if (!in_array($rule->resource(), $resources, true)) $resources[] = $rule->resource();
        }
        return $resources;
    }

    /** @return list<string> */
    public static function publicResources(): array { return []; }

    /** @return list<string> */
    public static function wildcardRoles(): array { return []; }

    public static function find(string $resource, string $method): ?PrivateAgendaMethodRule
    {
        $method = strtoupper(trim($method));
        foreach (self::rules() as $rule) {
            if ($rule->resource() === $resource && $rule->method() === $method) return $rule;
        }
        return null;
    }
}

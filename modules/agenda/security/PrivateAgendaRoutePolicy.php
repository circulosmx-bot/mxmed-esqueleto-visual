<?php
declare(strict_types=1);

namespace Agenda\Security;

use Platform\Contracts\RiskLevel;

final readonly class PrivateAgendaRouteRule
{
    /** @param list<string> $methods @param list<string> $allowedRoles */
    public function __construct(
        private string $resource,
        private array $methods,
        private array $allowedRoles,
        private bool $ownershipRequired,
        private bool $operatorAllowed,
        private bool $ownerOnly,
        private string $risk,
        private string $action,
        private string $scope,
        private bool $failClosed = true
    ) {
        if ($this->resource === '' || $this->methods === [] || $this->allowedRoles === [] || $this->scope === '') throw new \InvalidArgumentException('route_rule_incomplete');
        if (array_intersect($this->allowedRoles, ['*', 'all', 'admin.everything', 'support.all']) !== []) throw new \InvalidArgumentException('route_rule_wildcard_forbidden');
        RiskLevel::assertValid($this->risk);
    }

    public function resource(): string { return $this->resource; }
    /** @return list<string> */
    public function methods(): array { return $this->methods; }
    /** @return list<string> */
    public function allowedRoles(): array { return $this->allowedRoles; }
    public function ownershipRequired(): bool { return $this->ownershipRequired; }
    public function operatorAllowed(): bool { return $this->operatorAllowed; }
    public function ownerOnly(): bool { return $this->ownerOnly; }
    public function risk(): string { return $this->risk; }
    public function action(): string { return $this->action; }
    public function scope(): string { return $this->scope; }
    public function failClosed(): bool { return $this->failClosed; }
    public function allowsMethod(string $method): bool { return in_array(strtoupper($method), $this->methods, true); }
}

final class PrivateAgendaRoutePolicy
{
    /** @return list<PrivateAgendaRouteRule> */
    public static function rules(): array
    {
        $standard = ['owner', 'administrator', 'collaborator'];
        return [
            new PrivateAgendaRouteRule('appointments', ['GET', 'POST', 'PATCH', 'DELETE'], $standard, true, true, false, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaRouteRule('patients', ['GET', 'POST', 'PATCH'], $standard, true, true, false, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaRouteRule('consultorios', ['GET', 'POST', 'PATCH', 'DELETE'], ['owner', 'administrator'], true, false, true, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaRouteRule('availability', ['GET', 'POST', 'PATCH'], $standard, true, true, false, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaRouteRule('schedule', ['GET', 'POST', 'PATCH'], $standard, true, true, false, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaRouteRule('settings', ['GET', 'PATCH'], ['owner'], true, false, true, RiskLevel::R2, 'configure', 'profile'),
            new PrivateAgendaRouteRule('waitlist', ['GET', 'POST', 'PATCH'], $standard, true, true, false, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaRouteRule('operators', ['GET', 'POST', 'PATCH'], ['owner', 'administrator'], true, false, true, RiskLevel::R2, 'access', 'profile'),
            new PrivateAgendaRouteRule('medical-groups', ['GET', 'POST', 'PATCH'], ['owner', 'administrator'], true, false, true, RiskLevel::R1, 'access', 'profile'),
            new PrivateAgendaRouteRule('geocode', ['GET'], $standard, true, true, false, RiskLevel::R1, 'access', 'profile'),
        ];
    }

    /** @return list<string> */
    public static function resources(): array
    {
        return array_map(static fn(PrivateAgendaRouteRule $rule): string => $rule->resource(), self::rules());
    }

    /** @return list<string> */
    public static function publicResources(): array { return []; }

    /** @return list<string> */
    public static function wildcardRoles(): array { return []; }

    public static function find(string $resource): ?PrivateAgendaRouteRule
    {
        foreach (self::rules() as $rule) if ($rule->resource() === $resource) return $rule;
        return null;
    }
}

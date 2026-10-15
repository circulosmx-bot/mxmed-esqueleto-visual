<?php
declare(strict_types=1);
namespace Identity\Services;

/** OP-POL02 closed initial set; all other module actions remain unavailable. */
final class ProfileOperatorCapabilityCatalog
{
    public const KEYS = ['agenda.appointments.read', 'agenda.appointments.create',
        'agenda.appointments.reschedule', 'agenda.appointments.cancel', 'agenda.availability.read'];

    public static function normalize(array $keys): array
    {
        if (!array_is_list($keys) || count($keys) > count(self::KEYS)) throw new \InvalidArgumentException('operator_capabilities_invalid');
        foreach ($keys as $key) if (!is_string($key) || !in_array($key, self::KEYS, true))
            throw new \InvalidArgumentException('operator_capability_not_delegable');
        $keys = array_values(array_unique($keys));
        sort($keys, SORT_STRING);
        return $keys;
    }
}

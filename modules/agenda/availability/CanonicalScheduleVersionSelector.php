<?php
declare(strict_types=1);

namespace Agenda\Availability;

final class CanonicalScheduleVersionSelector
{
    public function select(array $versions, string $profileId, string $consultorioId, string $targetDate): CanonicalScheduleVersion
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $targetDate, new \DateTimeZone('UTC'));
        if (!$parsed || $parsed->format('Y-m-d') !== $targetDate) throw new CanonicalAvailabilityException('invalid_effective_range', 'target date is invalid');
        if ($versions === []) throw new CanonicalAvailabilityException('canonical_schedule_missing', 'no canonical schedule exists');
        $profileMatch = false;
        $consultorioMatch = false;
        $applicable = [];
        foreach ($versions as $version) {
            if (!$version instanceof CanonicalScheduleVersion) throw new CanonicalAvailabilityException('canonical_schedule_missing', 'schedule versions are typed');
            if ($version->profileId() !== $profileId) continue;
            $profileMatch = true;
            if ($version->consultorioId() !== $consultorioId) continue;
            $consultorioMatch = true;
            if ($version->effectiveFrom() <= $targetDate && ($version->effectiveUntil() === null || $targetDate < $version->effectiveUntil())) {
                $applicable[] = $version;
            }
        }
        if (!$profileMatch) throw new CanonicalAvailabilityException('profile_mismatch', 'profile has no canonical schedule');
        if (!$consultorioMatch) throw new CanonicalAvailabilityException('consultorio_mismatch', 'consultorio has no canonical schedule');
        if ($applicable === []) throw new CanonicalAvailabilityException('canonical_schedule_missing', 'no effective canonical schedule exists');
        if (count($applicable) !== 1) throw new CanonicalAvailabilityException('canonical_schedule_ambiguous', 'more than one effective schedule exists');
        return $applicable[0];
    }
}

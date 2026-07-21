<?php
declare(strict_types=1);

namespace Agenda\Availability;

use Agenda\Contracts\ScheduleAvailabilityContract;
use Agenda\Contracts\ScheduleWindow;

final class CanonicalAvailabilityCalculator
{
    public function __construct(private readonly CanonicalScheduleVersionSelector $selector = new CanonicalScheduleVersionSelector()) {}

    public function calculate(AvailabilityCalculationRequest $request): CanonicalAvailabilityResult
    {
        $schedule = $this->selector->select($request->versions(), $request->profileId(), $request->consultorioId(), $request->targetDate());
        $timezone = new \DateTimeZone($schedule->timezone());
        $target = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->targetDate(), $timezone);
        if (!$target) throw new CanonicalAvailabilityException('invalid_effective_range', 'target date is invalid');
        $weekday = (int) $target->format('N');

        $windows = [];
        foreach ($schedule->windows() as $window) {
            if ($window->weekday() === $weekday) $windows[] = [$window->startMinute(), $window->endMinute()];
        }
        $appliedHoliday = null;
        $matchingHolidays = [];
        foreach ($request->holidays() as $holiday) {
            if ($holiday->active() && $holiday->date() === $request->targetDate()) {
                $matchingHolidays[] = $holiday;
            }
        }
        if ($matchingHolidays !== []) {
            $windows = [];
            $appliedHoliday = $matchingHolidays[0]->name();
        }

        $appliedOverrideIds = [];
        $matchingOverrides = [];
        foreach ($request->overrides() as $override) {
            if (!$override->active() || $override->profileId() !== $request->profileId() || $override->consultorioId() !== $request->consultorioId() || $override->date() !== $request->targetDate()) continue;
            $matchingOverrides[] = $override;
            $appliedOverrideIds[] = $override->id();
        }
        foreach ($matchingOverrides as $override) {
            if ($override->type() !== 'close') continue;
            if ($override->fullDay()) {
                $windows = [];
            } elseif ($override->window() !== null) {
                $windows = self::subtract($windows, [[$override->window()->startMinute(), $override->window()->endMinute()]]);
            }
        }
        foreach ($matchingOverrides as $override) {
            if ($override->type() !== 'open') continue;
            $window = $override->window();
            if ($window !== null) $windows[] = [$window->startMinute(), $window->endMinute()];
            elseif ($override->fullDay()) $windows[] = [0, 1440];
        }
        $windows = self::normalize($windows);

        $matchingCollisions = [];
        foreach ($request->collisions() as $collision) {
            if (!$collision->active() || $collision->profileId() !== $request->profileId() || $collision->consultorioId() !== $request->consultorioId() || $collision->date() !== $request->targetDate()) continue;
            $matchingCollisions[] = [$collision->startMinute(), $collision->endMinute()];
        }
        $collisionWindows = self::normalize($matchingCollisions);
        foreach ($collisionWindows as $collision) $windows = self::subtract($windows, [$collision]);
        $windows = self::normalize($windows);

        $finalWindows = [];
        foreach ($windows as [$start, $end]) {
            $finalWindows[] = ['start' => AvailabilityTimeWindow::formatMinute($start), 'end' => AvailabilityTimeWindow::formatMinute($end)];
        }
        $slots = [];
        $step = $schedule->durationMinutes() + $schedule->gapMinutes();
        foreach ($windows as [$start, $end]) {
            for ($cursor = $start; $cursor + $schedule->durationMinutes() <= $end; $cursor += $step) {
                $slotEnd = $cursor + $schedule->durationMinutes();
                $slots[] = [
                    'date' => $request->targetDate(),
                    'start' => AvailabilityTimeWindow::formatMinute($cursor),
                    'end' => AvailabilityTimeWindow::formatMinute($slotEnd),
                    'timezone' => $schedule->timezone(),
                ];
            }
        }
        usort($slots, static fn(array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
        $contractWindows = array_map(static fn(array $window): ScheduleWindow => new ScheduleWindow($window['start'], $window['end']), $finalWindows);
        $contract = new ScheduleAvailabilityContract(
            $request->profileId(),
            $request->consultorioId(),
            $schedule->timezone(),
            $contractWindows,
            $schedule->durationMinutes(),
            $schedule->gapMinutes(),
            array_map(static fn(AvailabilityOverride $value): array => $value->toArray(), $matchingOverrides),
            array_values(array_map(static fn(HolidayClosure $value): array => $value->toArray(), $matchingHolidays)),
            array_values(array_map(static fn(CollisionWindow $value): array => $value->toArray(), array_values(array_filter(
                $request->collisions(), static fn(CollisionWindow $value): bool => $value->active() && $value->profileId() === $request->profileId() && $value->consultorioId() === $request->consultorioId() && $value->date() === $request->targetDate()
            )))),
            $schedule->effectiveFrom()
        );
        $appliedOverrideIds = array_values(array_unique($appliedOverrideIds));
        sort($appliedOverrideIds, SORT_STRING);
        return new CanonicalAvailabilityResult($contract, $schedule, $request->targetDate(), $finalWindows, $slots, $appliedOverrideIds, $appliedHoliday, count($matchingCollisions));
    }

    private static function normalize(array $windows): array
    {
        if ($windows === []) return [];
        usort($windows, static fn(array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $result = [];
        foreach ($windows as $window) {
            if (!is_array($window) || count($window) !== 2 || $window[0] >= $window[1]) continue;
            $last = count($result) - 1;
            if ($last >= 0 && $window[0] <= $result[$last][1]) {
                $result[$last][1] = max($result[$last][1], $window[1]);
            } else {
                $result[] = [(int) $window[0], (int) $window[1]];
            }
        }
        return $result;
    }

    private static function subtract(array $windows, array $cuts): array
    {
        foreach ($cuts as [$cutStart, $cutEnd]) {
            $next = [];
            foreach ($windows as [$start, $end]) {
                if ($cutEnd <= $start || $cutStart >= $end) {
                    $next[] = [$start, $end];
                    continue;
                }
                if ($start < $cutStart) $next[] = [$start, min($cutStart, $end)];
                if ($cutEnd < $end) $next[] = [max($cutEnd, $start), $end];
            }
            $windows = self::normalize($next);
        }
        return $windows;
    }
}

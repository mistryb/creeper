<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum CreepFrequency: string
{
    case Manual = 'manual';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Only when I ask',
            self::Hourly => 'Every hour',
            self::Daily => 'Every day',
            self::Weekly => 'Every week',
        };
    }

    /**
     * When the next run is due, or null for targets that never self-schedule.
     */
    public function nextRunAfter(?Carbon $from = null): ?Carbon
    {
        $from ??= Carbon::now();

        return match ($this) {
            self::Manual => null,
            self::Hourly => $from->addHour(),
            self::Daily => $from->addDay(),
            self::Weekly => $from->addWeek(),
        };
    }

    /**
     * How often this frequency fires, in minutes. Manual never fires.
     */
    public function intervalInMinutes(): ?int
    {
        return match ($this) {
            self::Manual => null,
            self::Hourly => 60,
            self::Daily => 1440,
            self::Weekly => 10080,
        };
    }

    /**
     * Whether this frequency runs at least as often as the given one.
     *
     * Used to enforce plan limits: a plan capped at "daily" must reject
     * "hourly", because hourly is the more demanding schedule.
     */
    public function isAtLeastAsFrequentAs(self $other): bool
    {
        $mine = $this->intervalInMinutes();
        $theirs = $other->intervalInMinutes();

        if ($mine === null) {
            return false;
        }

        if ($theirs === null) {
            return true;
        }

        return $mine <= $theirs;
    }

    /**
     * Frequencies allowed when a plan caps scheduling at the given one.
     *
     * @return array<int, self>
     */
    public static function upTo(self $slowest): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case): bool => ! $case->isAtLeastAsFrequentAs($slowest) || $case === $slowest,
        ));
    }
}

<?php

namespace App\Enums;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * How often a company's digest goes out.
 */
enum DigestFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    /**
     * The hour of the day a digest is sent, in the application's timezone.
     * Early enough to be waiting in the inbox at the start of the day.
     */
    public const SEND_HOUR = 8;

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Every morning',
            self::Weekly => 'Every Monday morning',
        };
    }

    /**
     * When the next digest is due after the given moment: the next morning,
     * or the next Monday morning.
     */
    public function nextRunAfter(?CarbonInterface $from = null): CarbonInterface
    {
        $from ??= Carbon::now();

        $next = match ($this) {
            self::Daily => $from->addDay(),
            self::Weekly => $from->next(Carbon::MONDAY),
        };

        return $next->setTime(self::SEND_HOUR, 0);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $frequency): array => ['value' => $frequency->value, 'label' => $frequency->label()],
            self::cases(),
        );
    }
}

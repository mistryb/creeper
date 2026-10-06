<?php

namespace App\Enums;

/**
 * What kind of news a watched page carries, so the dashboard can slice a
 * competitor's activity: did they move a price, ship something, or change
 * what they say about themselves?
 */
enum PageCategory: string
{
    case Pricing = 'pricing';
    case Releases = 'releases';
    case Messaging = 'messaging';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Pricing => 'Pricing',
            self::Releases => 'Releases',
            self::Messaging => 'Messaging',
            self::Other => 'Other',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category): array => ['value' => $category->value, 'label' => $category->label()],
            self::cases(),
        );
    }
}

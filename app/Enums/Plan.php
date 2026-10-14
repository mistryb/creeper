<?php

namespace App\Enums;

/**
 * What an account can pay for on a billed install. Both plans are the same
 * product; the only difference is how many companies get digests.
 */
enum Plan: string
{
    case Single = 'single';
    case Unlimited = 'unlimited';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'One company',
            self::Unlimited => 'Unlimited',
        };
    }

    /**
     * What the plan charges a month, in dollars. Shown in the app only — the
     * Stripe price is what actually bills, so the two must agree.
     */
    public function monthlyPrice(): int
    {
        return match ($this) {
            self::Single => 5,
            self::Unlimited => 20,
        };
    }

    /**
     * How many companies the plan covers. Null means no limit.
     */
    public function businessLimit(): ?int
    {
        return match ($this) {
            self::Single => 1,
            self::Unlimited => null,
        };
    }

    /**
     * The Stripe price this plan subscribes to.
     */
    public function priceId(): ?string
    {
        return config("billing.prices.{$this->value}");
    }

    /**
     * Every plan, as the billing screen and the landing page offer them.
     *
     * @return list<array{value: string, label: string, price: int, businessLimit: int|null}>
     */
    public static function options(): array
    {
        return array_map(fn (self $plan): array => [
            'value' => $plan->value,
            'label' => $plan->label(),
            'price' => $plan->monthlyPrice(),
            'businessLimit' => $plan->businessLimit(),
        ], self::cases());
    }

    /**
     * The plan a Stripe price belongs to, or null for a price this install
     * does not sell.
     */
    public static function fromPriceId(?string $priceId): ?self
    {
        if ($priceId === null) {
            return null;
        }

        foreach (self::cases() as $plan) {
            if ($plan->priceId() === $priceId) {
                return $plan;
            }
        }

        return null;
    }
}

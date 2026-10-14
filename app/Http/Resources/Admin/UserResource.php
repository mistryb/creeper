<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An account as the admin panel lists it. Expects `subscriptions` loaded and
 * `businesses_count` counted.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status(),
            'comped' => $this->isComped(),
            'is_admin' => $this->isAdmin(),
            'businesses_count' => $this->businesses_count,
            'business_limit' => $this->businessLimit(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * One short word or two for where this account stands with billing.
     */
    private function status(): string
    {
        if (! config('billing.enabled')) {
            return 'Self-hosted';
        }

        if ($this->isComped()) {
            return 'Comped';
        }

        if ($plan = $this->plan()) {
            $subscription = $this->subscription();

            return match (true) {
                $subscription->onGracePeriod() => "{$plan->label()} · cancelling",
                $subscription->onTrial() => "{$plan->label()} · trial",
                default => $plan->label(),
            };
        }

        if ($this->onGenericTrial()) {
            return 'Trial';
        }

        return $this->subscription() === null ? 'Trial ended' : 'Lapsed';
    }
}

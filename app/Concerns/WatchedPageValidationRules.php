<?php

namespace App\Concerns;

use App\Enums\CreepFrequency;
use App\Enums\PageCategory;
use App\Models\ApiKey;
use App\Models\WatchedPage;
use App\Rules\PublicUrl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait WatchedPageValidationRules
{
    /**
     * Get the validation rules used to validate watched pages.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function watchedPageRules(int $userId, int $competitorId, ?int $watchedPageId = null): array
    {
        return [
            'url' => $this->urlRules($competitorId, $watchedPageId),
            // Handed to the reader on every run, so it is the user's whole say
            // in what counts as news. Required to start watching; on update it
            // is `sometimes`, like `status` and the key, so a request that
            // leaves it out leaves it alone. Changing it is fine: readings are
            // compared by label, and the next run reports what it now sees.
            'watch_for' => [$watchedPageId === null ? 'required' : 'sometimes', 'required', 'string', 'max:2000'],
            'category' => [$watchedPageId === null ? 'required' : 'sometimes', Rule::enum(PageCategory::class)],
            'name' => ['nullable', 'string', 'max:255'],
            'api_key_id' => ['sometimes', 'required', ...$this->apiKeyRules($userId)],
            'frequency' => ['required', Rule::enum(CreepFrequency::class)],
            'notify_on_change' => ['boolean'],
        ];
    }

    /**
     * Get the validation rules used to validate the key a page spends.
     *
     * A key can only be one of the user's own, which is what stops a page
     * being pointed at somebody else's credit.
     *
     * Whether one has to be given is the caller's call: creating a page
     * requires a key, while updating one is `sometimes`, for the same reason
     * `status` is — the settings form submits the field, but a request that
     * leaves it out must leave the page on the key it already has.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function apiKeyRules(int $userId): array
    {
        return [
            'integer',
            Rule::exists(ApiKey::class, 'id')->where('user_id', $userId),
        ];
    }

    /**
     * Get the validation rules used to validate a watched page's URL.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function urlRules(int $competitorId, ?int $watchedPageId = null): array
    {
        // One competitor watching the same page twice would only pay twice.
        $unique = Rule::unique(WatchedPage::class, 'url')->where('competitor_id', $competitorId);

        return [
            'required',
            'string',
            'max:2048',
            new PublicUrl,
            $watchedPageId === null ? $unique : $unique->ignore($watchedPageId),
        ];
    }
}

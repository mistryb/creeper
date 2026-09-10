<?php

namespace App\Http\Controllers\Settings;

use App\Enums\CreepProvider;
use App\Enums\TargetStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\CreepTargetController;
use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The keyring: the model API keys Creeper spends on the user's behalf.
 *
 * Keys are only ever added here — there is no key in the environment — and a
 * key is never sent back to the browser. Once saved, the screen shows the name
 * it was given, its provider and its last four characters, and the only edits
 * available are adding another or removing one.
 *
 * Which key a creep spends is the target's business, not this screen's: see
 * {@see CreepTargetController}.
 */
class ApiKeyController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/api-keys', [
            'keys' => $this->keys($request),
            'providers' => $this->providerOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique(ApiKey::class, 'name')->where('user_id', $request->user()->id),
            ],
            'api_key' => ['required', 'string', 'min:20', 'max:400'],
            'provider' => ['required', Rule::enum(CreepProvider::class)],
        ]);

        $request->user()->addApiKey(
            $validated['name'],
            CreepProvider::from($validated['provider']),
            $validated['api_key'],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API key saved.')]);

        return back();
    }

    /**
     * Remove a key, and stop whatever it was paying for.
     *
     * The targets that spent it are left in place but paused: their runs would
     * only fail until somebody picked another key, and a failing run still
     * counts towards the failure limit that parks a target for good. Targets
     * already parked are left alone, so that resuming one does not quietly
     * become an unrelated recovery.
     */
    public function destroy(Request $request, ApiKey $apiKey): RedirectResponse
    {
        $this->authorize('delete', $apiKey);

        $paused = 0;

        foreach ($apiKey->creepTargets()->where('status', TargetStatus::Active)->get() as $target) {
            $target->pause();
            $paused++;
        }

        $apiKey->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $paused === 0
                ? __('API key removed.')
                : trans_choice('API key removed. :count target has been paused until you give it another key.|API key removed. :count targets have been paused until you give them another key.', $paused, ['count' => $paused]),
        ]);

        return back();
    }

    /**
     * The keyring, as the screen shows it: enough to tell two keys apart, and
     * never the key itself.
     *
     * @return array<int, array<string, mixed>>
     */
    private function keys(Request $request): array
    {
        return $request->user()
            ->apiKeys()
            ->withCount('creepTargets')
            ->get()
            ->map(fn (ApiKey $key): array => [
                'id' => $key->id,
                'name' => $key->name,
                'provider' => $key->provider->value,
                'providerLabel' => $key->provider->label(),
                'hint' => $key->hint,
                'targets' => $key->creep_targets_count,
                'created_at' => $key->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * The providers a key can be saved against, with the shape of key each
     * one issues so the form can hint at it.
     *
     * @return array<int, array<string, string>>
     */
    private function providerOptions(): array
    {
        return array_map(
            fn (CreepProvider $provider): array => [
                'value' => $provider->value,
                'label' => $provider->label(),
                'placeholder' => $provider->placeholder(),
            ],
            CreepProvider::cases(),
        );
    }
}

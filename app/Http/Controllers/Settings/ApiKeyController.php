<?php

namespace App\Http\Controllers\Settings;

use App\Enums\CreepProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The model API key Creeper spends on the user's behalf.
 *
 * The key itself is never sent back to the browser — once saved, the screen
 * shows only the last four characters, and the only edits available are
 * replacing it or removing it.
 */
class ApiKeyController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/api-key', [
            'hasKey' => $user->hasCreepApiKey(),
            'hint' => $user->creep_api_key_hint,
            'provider' => $user->creep_api_provider?->value,
            'providerLabel' => $user->creep_api_provider?->label(),
            'providers' => $this->providerOptions(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'api_key' => ['required', 'string', 'min:20', 'max:400'],
            'provider' => ['required', Rule::enum(CreepProvider::class)],
        ]);

        $request->user()->setCreepApiKey(
            $validated['api_key'],
            CreepProvider::from($validated['provider']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API key saved.')]);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->forgetCreepApiKey();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('API key removed. Creeping is paused until you add another.')]);

        return back();
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

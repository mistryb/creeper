<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrentBusinessController extends Controller
{
    /**
     * Switch the business the app is working on, and go to its digests.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_id' => [
                'required',
                'integer',
                Rule::exists(Business::class, 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        $business = Business::query()->whereKey($validated['business_id'])->firstOrFail();

        $request->user()->switchBusiness($business);

        return to_route('businesses.digests.index', $business);
    }
}

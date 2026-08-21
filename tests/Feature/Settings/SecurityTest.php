<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

it('reports no other sessions when sessions are not stored in the database', function () {
    config(['session.driver' => 'array']);

    $this->actingAs(User::factory()->create())
        ->get(route('security.edit'))
        ->assertInertia(fn ($page) => $page->where('otherSessions', 0));
});

it('shows the security page without asking for a password', function () {
    // There is no password to confirm, so nothing should stand in the way.
    $this->actingAs(User::factory()->create())
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/security'));
});

it('counts other live sessions', function () {
    // Only the database driver keeps a sessions table to count.
    config(['session.driver' => 'database']);

    $user = User::factory()->create();

    DB::table('sessions')->insert([
        [
            'id' => 'other-browser',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ],
    ]);

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertInertia(fn ($page) => $page->where('otherSessions', 1));
});

/*
 * Signing in leaves a cookie good for about a year, so revoking it elsewhere
 * is the one security control this application actually has.
 */
it('signs out other browsers by cycling the remember token', function () {
    config(['session.driver' => 'database']);

    $user = User::factory()->create();
    $token = $user->remember_token;

    DB::table('sessions')->insert([
        [
            'id' => 'other-browser',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ],
    ]);

    $response = $this->actingAs($user)
        ->delete(route('security.sessions.destroy'))
        ->assertRedirect(route('security.edit'));

    expect($user->fresh()->remember_token)->not->toBe($token)
        ->and(DB::table('sessions')->where('id', 'other-browser')->exists())->toBeFalse();

    // This browser is handed a cookie for the new token rather than kicked out.
    $response->assertCookie(Auth::guard('web')->getRecallerName());
    $this->assertAuthenticatedAs($user->fresh());
});

it('keeps guests off the security page', function () {
    $this->delete(route('security.sessions.destroy'))->assertRedirect(route('login'));
});

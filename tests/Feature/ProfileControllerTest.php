<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create an authenticated user before each test runs
    $this->user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => Hash::make('password123'),
        'email_verified_at' => now(),
    ]);

    $this->actingAs($this->user);
    $this->withoutMiddleware(PreventRequestForgery::class); // Disable middleware for testing
});

/*
|--------------------------------------------------------------------------
| Edit Profile Tests
|--------------------------------------------------------------------------
*/

it('displays the profile edit page with the authenticated user data', function () {
    $response = $this->get(route('profile.edit'));

    $response->assertOk();
    $response->assertViewIs('profile.edit');
    $response->assertViewHas('user', $this->user);
});

/*
|--------------------------------------------------------------------------
| Update Profile Tests
|--------------------------------------------------------------------------
*/

it('updates the profile information successfully when data is valid', function () {
    $payload = [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ];

    $response = $this->patch(route('profile.update'), $payload);

    $response->assertRedirect(route('profile.edit'));
    $response->assertSessionHas('status', 'Profile updated.');

    // Assert database values updated
    $this->user->refresh();
    expect($this->user->name)->toBe('Jane Doe')
        ->and($this->user->email)->toBe('jane@example.com');
});

it('nullifies email verification timestamp when the email address changes', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'new-email@example.com', // Email is different
    ];

    $response = $this->patch(route('profile.update'), $payload);

    $response->assertRedirect(route('profile.edit'));

    $this->user->refresh();
    expect($this->user->email_verified_at)->toBeNull();
});

it('keeps email verification timestamp if the email address stays the same', function () {
    $originalTimestamp = $this->user->email_verified_at;

    $payload = [
        'name' => 'John Splendid Name',
        'email' => 'john@example.com', // Email is the same
    ];

    $response = $this->patch(route('profile.update'), $payload);

    $this->user->refresh();
    expect($this->user->email_verified_at->toIso8601String())->toBe($originalTimestamp->toIso8601String());
});

/*
|--------------------------------------------------------------------------
| ProfileUpdateRequest (Validation) Tests
|--------------------------------------------------------------------------
*/

it('requires a name and valid email string', function () {
    $payload = [
        'name' => '',
        'email' => 'not-a-valid-email',
    ];

    $response = $this->patch(route('profile.update'), $payload);

    $response->assertSessionHasErrors(['name', 'email']);
});

it('allows the user to keep their current email without triggering a unique error rule', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'john@example.com', // Current authenticated user email
    ];

    $response = $this->patch(route('profile.update'), $payload);

    $response->assertSessionHasNoErrors();
});

it('prevents updating to an email address that is already registered to another user', function () {
    // Create another existing user
    User::factory()->create(['email' => 'taken@example.com']);

    $payload = [
        'name' => 'John Doe',
        'email' => 'TAKEN@example.com', // Testing lowercase & unique validation rule constraint
    ];

    $response = $this->patch(route('profile.update'), $payload);

    $response->assertSessionHasErrors(['email']);
});

/*
|--------------------------------------------------------------------------
| Destroy Profile Account Tests
|--------------------------------------------------------------------------
*/

it('deletes the user account, logs out the user, and invalidates session when correct password given', function () {
    $payload = [
        'password' => 'password123',
    ];

    $response = $this->delete(route('profile.destroy'), $payload);

    $response->assertRedirect('/');

    // Assert user removed from DB
    $this->assertDatabaseMissing('users', ['id' => $this->user->id]);

    // Assert authentication state context is unauthenticated
    $this->assertGuest();
});

it('fails to delete the account and throws error bag validation exception when password mismatch occurs', function () {
    $payload = [
        'password' => 'wrong-password-entry',
    ];

    $response = $this->delete(route('profile.destroy'), $payload);

    // Verify it drops back with errors tied specifically to the 'userDeletion' bag
    $response->assertSessionHasErrorsIn('userDeletion', ['password']);

    // User should still exist in database
    $this->assertDatabaseHas('users', ['id' => $this->user->id]);
});

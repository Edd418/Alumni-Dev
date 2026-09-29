<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up an authenticated user and bypass CSRF checks
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->withoutMiddleware(PreventRequestForgery::class);
});

/*
|--------------------------------------------------------------------------
| Update Profile Engine Tests
|--------------------------------------------------------------------------
*/

it('creates a new profile if the user does not have one and updates their info', function () {
    // Ensure the user starts with no profile relation
    expect($this->user->profile)->toBeNull();

    $payload = [
        'bio' => 'Hello, I am a software engineer.',
        'details' => ['PHP', 'Laravel', 'Pest'],
        'resume_link' => 'https://example.com',
        'picture_url' => 'https://example.com',
    ];

    $response = $this->patch(route('me.profile.update'), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'Profile information updated successfully.');

    // Assert the profile table now has the new row
    $this->assertDatabaseHas('user_profiles', [
        'user_id' => $this->user->id,
        'bio' => 'Hello, I am a software engineer.',
        'resume_link' => 'https://example.com',
        'picture_url' => 'https://example.com',
    ]);

    // Verify the JSON/Array cast details mapping
    $this->user->refresh();
    expect($this->user->profile->details)->toEqual(['PHP', 'Laravel', 'Pest']);
});

it('updates an existing profile if the user already has one', function () {
    // Arrange: Pre-create an old profile record attached to our user
    $profile = $this->user->profile()->create([
        'bio' => 'Old legacy bio string.',
        'details' => ['Vue'],
        'resume_link' => 'https://example.com',
    ]);

    $payload = [
        'bio' => 'Brand new shiny bio.',
        'details' => ['React', 'NextJS'],
        'resume_link' => 'https://example.com',
        'picture_url' => 'https://example.com',
    ];

    $response = $this->patch(route('me.profile.update'), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'Profile information updated successfully.');

    // Assert that the exact same database row was updated instead of generating duplicates
    expect($this->user->profile()->count())->toBe(1);

    $this->user->refresh();
    expect($this->user->profile->bio)->toBe('Brand new shiny bio.')
        ->and($this->user->profile->resume_link)->toBe('https://example.com')
        ->and($this->user->profile->details)->toEqual(['React', 'NextJS']);
});

/*
|--------------------------------------------------------------------------
| Validation Constraint Tests (MeInfoUpdateRequest)
|--------------------------------------------------------------------------
*/

it('rejects invalid urls for resume and picture elements', function () {
    $payload = [
        'resume_link' => 'not-a-valid-url-format',
        'picture_url' => 'not-a-valid-url-format',
    ];

    $response = $this->patch(route('me.profile.update'), $payload);

    $response->assertSessionHasErrors(['resume_link', 'picture_url']);
});

it('rejects nested details array strings that exceed 100 characters', function () {
    $payload = [
        'details' => [
            str_repeat('a', 101), // This string has a length of 101, which fails the 'max:100' constraint
        ],
    ];

    $response = $this->patch(route('me.profile.update'), $payload);

    // Asserts that the wild-card child validator array caught the validation error
    $response->assertSessionHasErrors(['details.0']);
});

it('allows nullable profile information payloads to clear records safely', function () {
    $payload = [
        'bio' => null,
        'details' => null,
        'resume_link' => null,
        'picture_url' => null,
    ];

    $response = $this->patch(route('me.profile.update'), $payload);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();
});

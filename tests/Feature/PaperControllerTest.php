<?php

use App\Models\ResearchPaper;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Set up authenticated environment and bypass CSRF checks
    $this->user = User::factory()->create(['name' => 'Dr. Jane Doe']);
    $this->actingAs($this->user);
    $this->withoutMiddleware(PreventRequestForgery::class);
    $this->withoutExceptionHandling();
});

/*
|--------------------------------------------------------------------------
| Store Method Tests
|--------------------------------------------------------------------------
*/

it('stores a research paper with correct author orders and profiles', function () {
    $payload = [
        'title' => 'Quantum Computing Implementations',
        'abstract' => 'This paper explores modern architectures.',
        'doi' => '10.1000/xyz123',
        'pdf_url' => 'https://example.edu',
        'visibility' => 'public',
        'authors' => [
            ['name' => 'Professor Smith', 'order' => 2],
            ['name' => '', 'order' => 3], // Should be skipped completely
        ],
        'profile_bio' => 'Lab notes and extended summary.',
        'profile_details' => ['Physics', 'Quantum', ''], // Empty details string should be stripped
        'picture_url' => 'https://example.edu',
    ];

    $response = $this->post(route('papers.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHas('status_paper', 'Research paper created.');

    $this->assertDatabaseHas('research_papers', [
        'title' => 'Quantum Computing Implementations',
        'doi' => '10.1000/xyz123',
    ]);

    $paper = ResearchPaper::first();

    // Verify creator is attached to pivot table with author_order 1
    expect($paper->users->contains($this->user))->toBeTrue()
        ->and($paper->users()->first()->pivot->author_order)->toBe(1);

    // Verify both authors exist (Jane Doe automatically added as first author if not typed in payload)
    expect($paper->authors)->toHaveCount(2);
    $this->assertDatabaseHas('research_paper_authors', [
        'research_paper_id' => $paper->id,
        'name' => 'Dr. Jane Doe',
        'author_order' => 1,
    ]);
    $this->assertDatabaseHas('research_paper_authors', [
        'research_paper_id' => $paper->id,
        'name' => 'Professor Smith',
        'author_order' => 2,
    ]);

    // Profile asserts
    expect($paper->profile->details)->toEqual(['Physics', 'Quantum']);
});

/*
|--------------------------------------------------------------------------
| Update Method Tests
|--------------------------------------------------------------------------
*/

it('updates a paper and manages relationship sync changes', function () {
    $paper = ResearchPaper::factory()->create(['title' => 'Initial Title']);
    $paper->users()->attach($this->user->id, ['author_order' => 1]); // Mocking ownership context
    $paper->authors()->create(['name' => 'Old Legacy Co-Author', 'author_order' => 2]);
    $paper->profile()->create(['bio' => 'Old Abstract Bio', 'details' => ['Chemistry']]);

    $updatePayload = [
        'title' => 'Updated Final Title',
        'abstract' => 'Rewritten abstract summary.',
        'doi' => '10.1000/new-doi',
        'pdf_url' => 'https://example.edu',
        'visibility' => 'private',
        'authors' => [
            ['name' => 'Dr. New Expert', 'order' => 5],
        ],
        'profile_bio' => 'Refreshed Profile bio text',
        'profile_details' => ['Biology'],
    ];

    $response = $this->patch(route('papers.update', $paper), $updatePayload);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'Research paper updated.');

    expect($paper->fresh()->title)->toBe('Updated Final Title');

    // Assert stale author relation was wiped out and new context generated
    $this->assertDatabaseMissing('research_paper_authors', ['name' => 'Old Legacy Co-Author']);
    expect($paper->authors()->get())->toHaveCount(2); // Dr. New Expert + Logged-in Prepend Owner

    // Profile replacement validation
    expect($paper->fresh()->profile->bio)->toBe('Refreshed Profile bio text')
        ->and($paper->fresh()->profile->details)->toEqual(['Biology']);
});

/*
|--------------------------------------------------------------------------
| Destroy Method Tests
|--------------------------------------------------------------------------
*/

it('deletes the specified research paper', function () {
    $paper = ResearchPaper::factory()->create();

    $response = $this->delete(route('papers.destroy', $paper));

    $response->assertRedirect();
    $response->assertSessionHas('status', 'Research paper deleted.');

    $this->assertDatabaseMissing('research_papers', ['id' => $paper->id]);
});

<?php

use App\Models\Project;
use App\Models\ProjectCollaborator;
use App\Models\ProjectProfile;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Relationship Tests (Covers ProjectCollaborator & ProjectProfile)
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
});
it('belongs to a project from a collaborator context', function () {
    $project = Project::factory()->create();

    $collaborator = ProjectCollaborator::create([
        'project_id' => $project->id,
        'name' => 'Jane Doe',
        'role' => 'Developer',
    ]);

    // Tests the project() relationship on ProjectCollaborator
    expect($collaborator->project)->toBeInstanceOf(Project::class)
        ->and($collaborator->project->id)->toBe($project->id);
});

it('belongs to a project from a project profile context', function () {
    $project = Project::factory()->create();

    $profile = ProjectProfile::create([
        'project_id' => $project->id,
        'bio' => 'Sample profile bio',
        'details' => ['PHP', 'Vue'],
    ]);

    // Tests the project() relationship on ProjectProfile
    expect($profile->project)->toBeInstanceOf(Project::class)
        ->and($profile->project->id)->toBe($project->id);
});

/*
|--------------------------------------------------------------------------
| Scope Tests (Covers Project lines 39..47)
|--------------------------------------------------------------------------
*/

it('filters public projects for an unauthenticated guest user', function () {
    // Arrange: Create a public and private project
    Project::factory()->create(['visibility' => 'public']);
    Project::factory()->create(['visibility' => 'private']);

    // Act: Apply the scope passing null for the user context
    $visibleProjects = Project::visibleTo(null)->get();

    // Assert: Only the public project is seen
    expect($visibleProjects)->toHaveCount(1)
        ->and($visibleProjects->first()->visibility)->toBe('public');
});

it('filters visible projects for an authenticated user context', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // 1. Create a public project (Visible to everyone)
    $publicProject = Project::factory()->create(['visibility' => 'public']);

    // 2. Create a private project owned by User A
    $privateProjectA = Project::factory()->create(['visibility' => 'private']);
    $privateProjectA->users()->attach($userA->id, ['contribution_role' => 'Owner']);

    // 3. Create a private project owned by User B
    $privateProjectB = Project::factory()->create(['visibility' => 'private']);
    $privateProjectB->users()->attach($userB->id, ['contribution_role' => 'Owner']);

    // Act & Assert for User A: Should see the public project and their own private project
    $visibleToUserA = Project::visibleTo($userA)->get();
    expect($visibleToUserA)->toHaveCount(2)
        ->and($visibleToUserA->pluck('id'))->toContain($publicProject->id, $privateProjectA->id)
        ->and($visibleToUserA->pluck('id'))->not->toContain($privateProjectB->id);
});

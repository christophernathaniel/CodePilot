<?php

use App\Models\Framework;
use App\Models\Project;
use App\Models\User;

test('users can create update and delete their projects', function () {
    $user = User::factory()->create();
    Framework::factory()->for($user)->create([
        'name' => 'Laravel',
        'slug' => 'laravel',
    ]);
    Framework::factory()->for($user)->create([
        'name' => 'React',
        'slug' => 'react',
    ]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('projects.store'), [
            'name' => '  API   Patterns  ',
            'kind' => 'bundle',
            'description' => 'Reusable API examples.',
            'frameworks' => [' Laravel ', 'React'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $project = Project::query()->whereBelongsTo($user)->sole();

    expect($project->name)->toBe('API Patterns')
        ->and($project->kind)->toBe('bundle')
        ->and($project->frameworks()->orderBy('slug')->pluck('slug')->all())
        ->toBe(['laravel', 'react']);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->patch(route('projects.update', $project), [
            'name' => 'Application Patterns',
            'kind' => 'project',
            'description' => null,
            'frameworks' => ['React'],
        ])
        ->assertSessionHasNoErrors();

    expect($project->refresh()->name)->toBe('Application Patterns')
        ->and($project->frameworks()->pluck('slug')->all())->toBe(['react']);

    $this->actingAs($user)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('dashboard'));

    $this->assertModelMissing($project);
});

test('projects are isolated between accounts', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->patch(route('projects.update', $project), [
            'name' => 'Stolen project',
            'kind' => 'project',
            'description' => null,
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('projects.destroy', $project))
        ->assertForbidden();

    expect($project->fresh())->not->toBeNull();
});

test('project names are unique inside an account', function () {
    $user = User::factory()->create();
    Project::factory()->for($user)->create(['name' => 'CodePilot']);

    $this->actingAs($user)
        ->post(route('projects.store'), [
            'name' => 'CodePilot',
            'kind' => 'project',
            'description' => null,
        ])
        ->assertSessionHasErrors('name');
});

test('users can create a guide collection', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.store'), [
            'name' => 'WordPress Gutenberg',
            'kind' => Project::KIND_GUIDE,
            'description' => 'Step-by-step WordPress guides.',
        ])
        ->assertSessionHasNoErrors();

    $project = Project::query()->whereBelongsTo($user)->sole();

    expect($project->kind)->toBe(Project::KIND_GUIDE);
});

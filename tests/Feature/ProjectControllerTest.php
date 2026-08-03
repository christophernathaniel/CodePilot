<?php

use App\Actions\Snippets\ReorderProjects;
use App\Models\Framework;
use App\Models\Project;
use App\Models\User;
use Illuminate\Validation\ValidationException;

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

    $this->assertSoftDeleted($project);
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

test('users can persist a contiguous order across projects bundles and guides', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'name' => 'Application',
        'position' => 8,
    ]);
    $bundle = Project::factory()->for($user)->bundle()->create([
        'name' => 'Reference bundle',
        'position' => 3,
    ]);
    $guide = Project::factory()->for($user)->guide()->create([
        'name' => 'Deployment guide',
        'position' => 12,
    ]);
    $trashedProject = Project::factory()->for($user)->create([
        'name' => 'Trashed project',
        'position' => 40,
    ]);
    $otherProject = Project::factory()->for($otherUser)->create([
        'name' => 'Another account project',
        'position' => 25,
    ]);
    $trashedProject->delete();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->patch(route('projects.reorder'), [
            'project_ids' => [
                (string) $guide->id,
                (string) $project->id,
                (string) $bundle->id,
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(
        Project::query()
            ->whereBelongsTo($user)
            ->orderBy('position')
            ->pluck('id', 'position')
            ->all(),
    )->toBe([
        0 => $guide->id,
        1 => $project->id,
        2 => $bundle->id,
    ])->and($trashedProject->fresh()->position)->toBe(40)
        ->and($otherProject->fresh()->position)->toBe(25);
});

test('project order requires every owned active project exactly once', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $firstProject = Project::factory()->for($user)->create(['position' => 4]);
    $secondProject = Project::factory()->for($user)->bundle()->create(['position' => 9]);
    $foreignProject = Project::factory()->for($otherUser)->create(['position' => 7]);

    $invalidOrders = [
        'missing list' => [[], 'project_ids'],
        'duplicate project' => [
            ['project_ids' => [$firstProject->id, $firstProject->id]],
            'project_ids.1',
        ],
        'incomplete list' => [
            ['project_ids' => [$firstProject->id]],
            'project_ids',
        ],
        'foreign project' => [
            ['project_ids' => [$firstProject->id, $foreignProject->id]],
            'project_ids',
        ],
        'non-integer project' => [
            ['project_ids' => [$firstProject->id, 'not-a-project']],
            'project_ids.1',
        ],
    ];

    foreach ($invalidOrders as [$payload, $errorKey]) {
        $this->actingAs($user)
            ->from(route('dashboard'))
            ->patch(route('projects.reorder'), $payload)
            ->assertSessionHasErrors($errorKey)
            ->assertRedirect(route('dashboard'));
    }

    expect($firstProject->fresh()->position)->toBe(4)
        ->and($secondProject->fresh()->position)->toBe(9)
        ->and($foreignProject->fresh()->position)->toBe(7);
});

test('project reorder action rejects stale missing and foreign ids inside its transaction', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $firstProject = Project::factory()->for($user)->create(['position' => 5]);
    $secondProject = Project::factory()->for($user)->bundle()->create(['position' => 8]);
    $initialProjectIds = [$secondProject->id, $firstProject->id];
    $newProject = Project::factory()->for($user)->guide()->create(['position' => 13]);
    $foreignProject = Project::factory()->for($otherUser)->create(['position' => 21]);
    $reorderProjects = app(ReorderProjects::class);

    $attemptReorder = static function (array $projectIds) use ($reorderProjects, $user): ?ValidationException {
        try {
            $reorderProjects->handle($user, $projectIds);
        } catch (ValidationException $exception) {
            return $exception;
        }

        return null;
    };

    $staleOrderException = $attemptReorder($initialProjectIds);
    $foreignOrderException = $attemptReorder([
        $secondProject->id,
        $firstProject->id,
        $foreignProject->id,
    ]);

    expect($staleOrderException)->toBeInstanceOf(ValidationException::class)
        ->and($staleOrderException?->errors())->toHaveKey('project_ids')
        ->and($foreignOrderException)->toBeInstanceOf(ValidationException::class)
        ->and($foreignOrderException?->errors())->toHaveKey('project_ids')
        ->and($firstProject->fresh()->position)->toBe(5)
        ->and($secondProject->fresh()->position)->toBe(8)
        ->and($newProject->fresh()->position)->toBe(13)
        ->and($foreignProject->fresh()->position)->toBe(21);
});

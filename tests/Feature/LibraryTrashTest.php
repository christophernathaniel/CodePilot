<?php

use App\Models\Folder;
use App\Models\Pin;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('moving a project to trash and restoring it restores only items deleted with it', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'Application']);
    $folder = Folder::factory()->for($project)->create(['name' => 'Source']);
    $snippet = Snippet::factory()->inFolder($folder)->create(['filename' => 'active.php']);
    $alreadyTrashedSnippet = Snippet::factory()->forProject($project)->create([
        'filename' => 'already-trashed.php',
    ]);

    $this->actingAs($user)
        ->delete(route('snippets.destroy', $alreadyTrashedSnippet))
        ->assertSessionHasNoErrors();

    $previousDeletionBatch = Snippet::withTrashed()
        ->findOrFail($alreadyTrashedSnippet->id)
        ->deletion_batch;

    $this->actingAs($user)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('dashboard'));

    $this->assertSoftDeleted($project);
    $this->assertSoftDeleted($folder);
    $this->assertSoftDeleted($snippet);
    $this->assertSoftDeleted($alreadyTrashedSnippet);

    $trashedProject = Project::withTrashed()->findOrFail($project->id);
    $trashedFolder = Folder::withTrashed()->findOrFail($folder->id);
    $trashedSnippet = Snippet::withTrashed()->findOrFail($snippet->id);
    $stillTrashedSnippet = Snippet::withTrashed()->findOrFail($alreadyTrashedSnippet->id);

    expect($trashedProject->deletion_batch)
        ->not->toBe($previousDeletionBatch)
        ->and($trashedFolder->deletion_batch)->toBe($trashedProject->deletion_batch)
        ->and($trashedSnippet->deletion_batch)->toBe($trashedProject->deletion_batch)
        ->and($stillTrashedSnippet->deletion_batch)->toBe($previousDeletionBatch);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects', 0)
            ->has('trash.projects', 1)
            ->where('trash.projects.0.id', $project->id)
            ->has('trash.folders', 0)
            ->has('trash.snippets', 0),
        );

    $this->actingAs($user)
        ->patch(route('projects.restore', $project->id))
        ->assertSessionHasNoErrors();

    $this->assertNotSoftDeleted($project);
    $this->assertNotSoftDeleted($folder);
    $this->assertNotSoftDeleted($snippet);
    $this->assertSoftDeleted($alreadyTrashedSnippet);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects', 1)
            ->has('trash.projects', 0)
            ->has('trash.snippets', 1)
            ->where('trash.snippets.0.id', $alreadyTrashedSnippet->id),
        );
});

test('moving a folder to trash hides its descendants until the folder is restored', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'Website']);
    $folder = Folder::factory()->for($project)->create(['name' => 'Components']);
    $childFolder = Folder::factory()->nestedUnder($folder)->create(['name' => 'Cards']);
    $snippet = Snippet::factory()->inFolder($childFolder)->create(['filename' => 'card.twig']);

    $this->actingAs($user)
        ->delete(route('projects.folders.destroy', [$project, $folder]))
        ->assertSessionHasNoErrors();

    $this->assertSoftDeleted($folder);
    $this->assertSoftDeleted($childFolder);
    $this->assertSoftDeleted($snippet);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('trash.folders', 1)
            ->where('trash.folders.0.id', $folder->id)
            ->has('trash.snippets', 0),
        );

    $this->actingAs($user)
        ->patch(route('folders.restore', $folder->id))
        ->assertSessionHasNoErrors();

    $this->assertNotSoftDeleted($folder);
    $this->assertNotSoftDeleted($childFolder);
    $this->assertNotSoftDeleted($snippet);
});

test('owners can permanently delete trashed items and their stale pins', function () {
    $user = User::factory()->create();
    $snippet = Snippet::factory()->for($user)->create();
    $pin = Pin::factory()->for($user)->create([
        'pinnable_type' => 'snippet',
        'pinnable_key' => (string) $snippet->id,
    ]);

    $this->actingAs($user)
        ->delete(route('snippets.destroy', $snippet))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->delete(route('snippets.force-destroy', $snippet->id))
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($snippet);
    $this->assertModelMissing($pin);
});

test('trash restore and permanent deletion remain isolated between accounts', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $snippet = Snippet::factory()->for($owner)->create();

    $this->actingAs($owner)->delete(route('snippets.destroy', $snippet));

    $this->actingAs($intruder)
        ->patch(route('snippets.restore', $snippet->id))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('snippets.force-destroy', $snippet->id))
        ->assertForbidden();

    $this->assertSoftDeleted($snippet);
});

test('active items cannot be permanently deleted through trash routes', function () {
    $user = User::factory()->create();
    $snippet = Snippet::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('snippets.force-destroy', $snippet))
        ->assertNotFound();

    $this->assertModelExists($snippet);
});

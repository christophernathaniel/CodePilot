<?php

use App\Models\Folder;
use App\Models\Project;
use App\Models\User;

test('users can create and move folders inside their project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $parent = Folder::factory()->for($project)->create(['name' => 'Source']);

    $this->actingAs($user)
        ->post(route('projects.folders.store', $project), [
            'name' => '  Components  ',
            'parent_id' => $parent->id,
        ])
        ->assertSessionHasNoErrors();

    $folder = Folder::query()->where('name', 'Components')->sole();

    expect($folder->project_id)->toBe($project->id)
        ->and($folder->parent_id)->toBe($parent->id);

    $this->actingAs($user)
        ->patch(route('projects.folders.update', [$project, $folder]), [
            'name' => 'UI',
            'parent_id' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($folder->refresh()->name)->toBe('UI')
        ->and($folder->parent_id)->toBeNull();
});

test('folder parents must belong to the same project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($user)->create();
    $otherFolder = Folder::factory()->for($otherProject)->create();

    $this->actingAs($user)
        ->post(route('projects.folders.store', $project), [
            'name' => 'Invalid child',
            'parent_id' => $otherFolder->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('folders cannot be moved inside their descendants', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $parent = Folder::factory()->for($project)->create();
    $child = Folder::factory()->nestedUnder($parent)->create();

    $this->actingAs($user)
        ->patch(route('projects.folders.update', [$project, $parent]), [
            'name' => $parent->name,
            'parent_id' => $child->id,
        ])
        ->assertSessionHasErrors('parent_id');

    expect($parent->fresh()->parent_id)->toBeNull();
});

test('nested folder bindings and policies prevent cross account access', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $folder = Folder::factory()->for($project)->create();
    $intruderProject = Project::factory()->for($intruder)->create();

    $this->actingAs($intruder)
        ->post(route('projects.folders.store', $project), [
            'name' => 'Unauthorized',
            'parent_id' => null,
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('projects.folders.destroy', [$intruderProject, $folder]))
        ->assertNotFound();
});

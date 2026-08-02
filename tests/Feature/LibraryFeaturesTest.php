<?php

use App\Models\Folder;
use App\Models\Pin;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('standalone snippets are created with frameworks and serialized outside projects', function () {
    $this->withoutVite();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('snippets.store'), [
            'title' => 'Standalone helper',
            'filename' => 'standalone-helper.php',
            'language' => 'PHP',
            'description' => 'A snippet that does not belong to a project.',
            'project_id' => null,
            'folder_id' => null,
            'content' => '<?php return true;',
            'tags' => ['Reusable'],
            'frameworks' => ['Laravel', 'React'],
        ])
        ->assertSessionHasNoErrors();

    $snippet = Snippet::query()->whereBelongsTo($user)->sole();

    expect($snippet->project_id)->toBeNull()
        ->and($snippet->folder_id)->toBeNull()
        ->and($snippet->location_key)->toBe('standalone')
        ->and($snippet->language)->toBe('php')
        ->and($snippet->variations()->sole()->content)->toBe('<?php return true;')
        ->and($snippet->frameworks()->orderBy('slug')->pluck('slug')->all())->toBe(['laravel', 'react']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('snippets/workspace')
            ->has('projects', 0)
            ->has('standalone_snippets', 1)
            ->where('standalone_snippets.0.id', $snippet->id)
            ->where('standalone_snippets.0.project_id', null)
            ->where('standalone_snippets.0.folder_id', null)
            ->where('standalone_snippets.0.frameworks.0.slug', 'laravel')
            ->where('standalone_snippets.0.frameworks.1.slug', 'react')
            ->where('standalone_snippets.0.variations.0.content', '<?php return true;'),
        );
});

test('pins can be toggled without reading or mutating another account library', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownerSnippet = Snippet::factory()->for($owner)->create();
    $otherSnippet = Snippet::factory()->for($otherUser)->create();

    $this->actingAs($owner)
        ->put(route('pins.update'), [
            'pinnable_type' => 'snippet',
            'pinnable_key' => (string) $ownerSnippet->id,
            'pinned' => true,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($otherUser)
        ->put(route('pins.update'), [
            'pinnable_type' => 'snippet',
            'pinnable_key' => (string) $otherSnippet->id,
            'pinned' => true,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($otherUser)
        ->put(route('pins.update'), [
            'pinnable_type' => 'snippet',
            'pinnable_key' => (string) $ownerSnippet->id,
            'pinned' => true,
        ])
        ->assertSessionHasErrors('pinnable_key');

    expect(Pin::query()
        ->where('user_id', $otherUser->id)
        ->where('pinnable_type', 'snippet')
        ->where('pinnable_key', (string) $ownerSnippet->id)
        ->doesntExist())->toBeTrue();

    $this->actingAs($owner)
        ->put(route('pins.update'), [
            'pinnable_type' => 'language',
            'pinnable_key' => 'JavaScript',
            'pinned' => true,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('pins', [
        'user_id' => $owner->id,
        'pinnable_type' => 'language',
        'pinnable_key' => 'javascript',
    ]);

    $this->actingAs($owner)
        ->put(route('pins.update'), [
            'pinnable_type' => 'snippet',
            'pinnable_key' => (string) $ownerSnippet->id,
            'pinned' => false,
        ])
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('pins', [
        'user_id' => $owner->id,
        'pinnable_type' => 'snippet',
        'pinnable_key' => (string) $ownerSnippet->id,
    ])->assertDatabaseHas('pins', [
        'user_id' => $otherUser->id,
        'pinnable_type' => 'snippet',
        'pinnable_key' => (string) $otherSnippet->id,
    ]);
});

test('snippets can move between project folders roots and the standalone library', function () {
    $user = User::factory()->create();
    $sourceProject = Project::factory()->for($user)->create();
    $targetProject = Project::factory()->for($user)->create();
    $targetFolder = Folder::factory()->for($targetProject)->create();
    $snippet = Snippet::factory()->forProject($sourceProject)->create([
        'filename' => 'move-me.php',
    ]);

    $this->actingAs($user)
        ->patch(route('snippets.move', $snippet), [
            'project_id' => $targetProject->id,
            'folder_id' => $targetFolder->id,
        ])
        ->assertSessionHasNoErrors();

    expect($snippet->refresh()->project_id)->toBe($targetProject->id)
        ->and($snippet->folder_id)->toBe($targetFolder->id)
        ->and($snippet->location_key)->toBe('folder:'.$targetFolder->id);

    $this->actingAs($user)
        ->patch(route('snippets.move', $snippet), [
            'project_id' => null,
            'folder_id' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($snippet->refresh()->project_id)->toBeNull()
        ->and($snippet->folder_id)->toBeNull()
        ->and($snippet->location_key)->toBe('standalone');

    $this->actingAs($user)
        ->patch(route('snippets.move', $snippet), [
            'project_id' => $sourceProject->id,
            'folder_id' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($snippet->refresh()->project_id)->toBe($sourceProject->id)
        ->and($snippet->folder_id)->toBeNull()
        ->and($snippet->location_key)->toBe('project:'.$sourceProject->id);
});

test('snippet moves reject destination collisions and cross account access', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $sourceProject = Project::factory()->for($owner)->create();
    $targetProject = Project::factory()->for($owner)->create();
    $foreignProject = Project::factory()->for($intruder)->create();
    $snippet = Snippet::factory()->forProject($sourceProject)->create([
        'filename' => 'duplicate.php',
    ]);
    Snippet::factory()->forProject($targetProject)->create([
        'filename' => 'duplicate.php',
    ]);

    $this->actingAs($owner)
        ->patch(route('snippets.move', $snippet), [
            'project_id' => $targetProject->id,
            'folder_id' => null,
        ])
        ->assertSessionHasErrors('filename');

    expect($snippet->refresh()->project_id)->toBe($sourceProject->id);

    $this->actingAs($owner)
        ->patch(route('snippets.move', $snippet), [
            'project_id' => $foreignProject->id,
            'folder_id' => null,
        ])
        ->assertSessionHasErrors('project_id');

    $this->actingAs($intruder)
        ->patch(route('snippets.move', $snippet), [
            'project_id' => $foreignProject->id,
            'folder_id' => null,
        ])
        ->assertForbidden();

    expect($snippet->refresh()->project_id)->toBe($sourceProject->id)
        ->and($snippet->user_id)->toBe($owner->id);
});

test('moving a folder across projects moves its complete subtree and rejects cycles', function () {
    $user = User::factory()->create();
    $sourceProject = Project::factory()->for($user)->create();
    $targetProject = Project::factory()->for($user)->create();
    $targetParent = Folder::factory()->for($targetProject)->create();
    $root = Folder::factory()->for($sourceProject)->create();
    $child = Folder::factory()->nestedUnder($root)->create();
    $snippet = Snippet::factory()->inFolder($child)->create();
    $originalLocationKey = $snippet->location_key;

    $this->actingAs($user)
        ->patch(route('folders.move', $root), [
            'project_id' => $targetProject->id,
            'parent_id' => $targetParent->id,
        ])
        ->assertSessionHasNoErrors();

    expect($root->refresh()->project_id)->toBe($targetProject->id)
        ->and($root->parent_id)->toBe($targetParent->id)
        ->and($child->refresh()->project_id)->toBe($targetProject->id)
        ->and($snippet->refresh()->project_id)->toBe($targetProject->id)
        ->and($snippet->folder_id)->toBe($child->id)
        ->and($snippet->location_key)->toBe($originalLocationKey)
        ->and($snippet->user_id)->toBe($user->id);

    $this->actingAs($user)
        ->patch(route('folders.move', $root), [
            'project_id' => $targetProject->id,
            'parent_id' => $child->id,
        ])
        ->assertSessionHasErrors('parent_id');

    expect($root->refresh()->parent_id)->toBe($targetParent->id)
        ->and($child->refresh()->parent_id)->toBe($root->id);
});

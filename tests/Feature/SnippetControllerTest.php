<?php

use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\Tag;
use App\Models\User;

test('creating a snippet creates a default variation and account local tags', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $otherUser = User::factory()->create();
    $otherUsersTag = Tag::factory()->for($otherUser)->create(['name' => 'API', 'slug' => 'api']);

    $content = "const response = await fetch('{{{base_url:https://example.com}}}');";

    $this->actingAs($user)
        ->post(route('projects.snippets.store', $project), [
            'title' => 'Fetch client',
            'filename' => 'fetch-client.js',
            'language' => 'JavaScript',
            'description' => 'Fetch a JSON endpoint.',
            'folder_id' => null,
            'content' => $content,
            'tags' => [' API ', 'Frontend'],
        ])
        ->assertSessionHasNoErrors();

    $snippet = Snippet::query()->whereBelongsTo($project)->sole();
    $variation = $snippet->variations()->sole();

    expect($snippet->language)->toBe('javascript')
        ->and($variation->name)->toBe('Default')
        ->and($variation->content)->toBe($content)
        ->and($variation->position)->toBe(1)
        ->and($variation->is_default)->toBeTrue()
        ->and($variation->created_by_id)->toBe($user->id)
        ->and($snippet->tags()->pluck('slug')->sort()->values()->all())->toBe(['api', 'frontend'])
        ->and($snippet->tags()->whereKey($otherUsersTag)->exists())->toBeFalse();
});

test('snippet metadata can be updated without mutating or duplicating variations', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('projects.snippets.store', $project), [
            'title' => 'Untagged snippet',
            'filename' => 'untagged.js',
            'language' => 'javascript',
            'description' => null,
            'folder_id' => null,
            'content' => 'const value = 1;',
            'tags' => [],
        ])
        ->assertSessionHasNoErrors();

    $snippet = Snippet::query()->whereBelongsTo($project)->sole();
    $variation = $snippet->variations()->sole();

    $this->actingAs($user)
        ->patch(route('snippets.update', $snippet), [
            'title' => 'Renamed snippet',
            'filename' => 'renamed.js',
            'language' => 'JavaScript',
            'description' => 'Metadata only.',
            'folder_id' => null,
            'tags' => [],
            'content' => 'this field is intentionally ignored by metadata updates',
        ])
        ->assertSessionHasNoErrors();

    expect($snippet->refresh()->title)->toBe('Renamed snippet')
        ->and($snippet->filename)->toBe('renamed.js')
        ->and($snippet->language)->toBe('javascript')
        ->and($snippet->tags()->doesntExist())->toBeTrue()
        ->and($snippet->variations()->count())->toBe(1)
        ->and($variation->refresh()->content)->toBe('const value = 1;');
});

test('snippet folders must belong to the snippet project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $otherProject = Project::factory()->for($user)->create();
    $folder = Folder::factory()->for($otherProject)->create();

    $this->actingAs($user)
        ->post(route('projects.snippets.store', $project), [
            'title' => 'Invalid',
            'filename' => 'invalid.php',
            'language' => 'php',
            'description' => null,
            'folder_id' => $folder->id,
            'content' => '<?php',
            'tags' => [],
        ])
        ->assertSessionHasErrors('folder_id');
});

test('snippet policies isolate account creation updates and deletion', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->for($owner)->create();
    $snippet = Snippet::factory()->for($project)->withVariation()->create();

    $this->actingAs($intruder)
        ->post(route('projects.snippets.store', $project), [
            'title' => 'Unauthorized',
            'filename' => 'unauthorized.js',
            'language' => 'javascript',
            'description' => null,
            'folder_id' => null,
            'content' => 'nope',
            'tags' => [],
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->patch(route('snippets.update', $snippet), [
            'title' => 'Unauthorized',
            'filename' => 'unauthorized.js',
            'language' => 'javascript',
            'description' => null,
            'folder_id' => null,
            'tags' => [],
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('snippets.destroy', $snippet))
        ->assertForbidden();

    expect($snippet->fresh())->not->toBeNull();
});

test('creating a guide stores its file content as a guide variation', function () {
    $user = User::factory()->create();
    $guideCollection = Project::factory()->for($user)->guide()->create();
    $content = "{!# guide-step: install | Install WordPress #!}\nRun the installer.\n\n```bash\nwp core install\n```";

    $this->actingAs($user)
        ->post(route('projects.snippets.store', $guideCollection), [
            'title' => 'Install WordPress',
            'filename' => 'install-wordpress.guide.md',
            'language' => 'markdown',
            'content_type' => Snippet::CONTENT_TYPE_GUIDE,
            'description' => 'A step-by-step WordPress installation guide.',
            'folder_id' => null,
            'content' => $content,
            'tags' => ['WordPress'],
            'frameworks' => [],
        ])
        ->assertSessionHasNoErrors();

    $guide = Snippet::query()->whereBelongsTo($guideCollection, 'project')->sole();

    expect($guide->content_type)->toBe(Snippet::CONTENT_TYPE_GUIDE)
        ->and($guide->variations()->sole()->content)->toBe($content);

    $this->actingAs($user)
        ->patch(route('snippets.update', $guide), [
            'title' => 'Install WordPress locally',
            'filename' => 'install-wordpress.guide.md',
            'language' => 'markdown',
            'description' => 'Updated metadata without changing the file type.',
            'tags' => ['WordPress'],
            'frameworks' => [],
        ])
        ->assertSessionHasNoErrors();

    expect($guide->refresh()->content_type)->toBe(Snippet::CONTENT_TYPE_GUIDE);
});

test('snippet content types are restricted to snippets and guides', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('snippets.store'), [
            'title' => 'Unsupported document',
            'filename' => 'unsupported.md',
            'language' => 'markdown',
            'content_type' => 'tutorial',
            'description' => null,
            'content' => 'Unsupported.',
            'tags' => [],
            'frameworks' => [],
        ])
        ->assertSessionHasErrors('content_type');
});

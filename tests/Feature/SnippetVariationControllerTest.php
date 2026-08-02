<?php

use App\Models\Project;
use App\Models\Snippet;
use App\Models\SnippetVariation;
use App\Models\User;

test('users can create edit select and delete named variations', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $snippet = Snippet::factory()->for($project)->create();
    $defaultVariation = SnippetVariation::factory()->for($snippet)->default()->create([
        'created_by_id' => $user->id,
        'name' => 'Default',
        'content' => 'const value = 1;',
        'position' => 1,
    ]);
    $spoofedCreator = User::factory()->create();

    $this->actingAs($user)
        ->post(route('snippets.variations.store', $snippet), [
            'name' => '  Compact   form  ',
            'content' => 'const value=1;',
            'created_by_id' => $spoofedCreator->id,
        ])
        ->assertSessionHasNoErrors();

    $compactVariation = $snippet->variations()->where('name', 'Compact form')->sole();

    expect($compactVariation->created_by_id)->toBe($user->id)
        ->and($compactVariation->position)->toBe(2)
        ->and($compactVariation->is_default)->toBeFalse();

    $this->actingAs($user)
        ->patch(route('snippets.variations.update', [$snippet, $compactVariation]), [
            'name' => 'Minified',
            'content' => 'const value=2;',
        ])
        ->assertSessionHasNoErrors();

    expect($compactVariation->refresh()->name)->toBe('Minified')
        ->and($compactVariation->content)->toBe('const value=2;')
        ->and($snippet->variations()->count())->toBe(2);

    $this->actingAs($user)
        ->patch(route('snippets.variations.default', [$snippet, $compactVariation]))
        ->assertSessionHasNoErrors();

    expect($compactVariation->refresh()->is_default)->toBeTrue()
        ->and($defaultVariation->refresh()->is_default)->toBeFalse()
        ->and($snippet->variations()->where('is_default', true)->count())->toBe(1);

    $this->actingAs($user)
        ->delete(route('snippets.variations.destroy', [$snippet, $defaultVariation]))
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($defaultVariation);
    expect($snippet->variations()->sole()->is($compactVariation))->toBeTrue();
});

test('variation names are unique inside a snippet after normalization', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $snippet = Snippet::factory()->for($project)->create();
    SnippetVariation::factory()->for($snippet)->default()->create([
        'created_by_id' => $user->id,
        'name' => 'Readable form',
    ]);
    $otherSnippet = Snippet::factory()->for($project)->create();

    $this->actingAs($user)
        ->post(route('snippets.variations.store', $snippet), [
            'name' => '  Readable   form ',
            'content' => 'duplicate',
        ])
        ->assertSessionHasErrors('name');

    $this->actingAs($user)
        ->post(route('snippets.variations.store', $otherSnippet), [
            'name' => 'Readable form',
            'content' => 'allowed on another snippet',
        ])
        ->assertSessionHasNoErrors();

    expect($snippet->variations()->count())->toBe(1)
        ->and($otherSnippet->variations()->where('name', 'Readable form')->exists())->toBeTrue();
});

test('a default or only variation cannot be deleted', function () {
    $user = User::factory()->create();
    $snippet = Snippet::factory()->for(Project::factory()->for($user))->create();
    $defaultVariation = SnippetVariation::factory()->for($snippet)->default()->create([
        'created_by_id' => $user->id,
        'name' => 'Default',
        'position' => 1,
    ]);
    $alternative = SnippetVariation::factory()->for($snippet)->create([
        'created_by_id' => $user->id,
        'name' => 'Alternative',
        'position' => 2,
    ]);

    $this->actingAs($user)
        ->delete(route('snippets.variations.destroy', [$snippet, $defaultVariation]))
        ->assertUnprocessable();

    $this->actingAs($user)
        ->delete(route('snippets.variations.destroy', [$snippet, $alternative]))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->delete(route('snippets.variations.destroy', [$snippet, $defaultVariation]))
        ->assertUnprocessable();

    expect($snippet->variations()->count())->toBe(1)
        ->and($defaultVariation->fresh())->not->toBeNull();
});

test('variation policies and scoped bindings isolate accounts and snippets', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ownerSnippet = Snippet::factory()->for(Project::factory()->for($owner))->create();
    $ownerVariation = SnippetVariation::factory()->for($ownerSnippet)->default()->create([
        'created_by_id' => $owner->id,
        'name' => 'Owner default',
    ]);
    $intruderSnippet = Snippet::factory()->for(Project::factory()->for($intruder))->create();

    $this->actingAs($intruder)
        ->post(route('snippets.variations.store', $ownerSnippet), [
            'name' => 'Unauthorized',
            'content' => 'nope',
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->patch(route('snippets.variations.update', [$ownerSnippet, $ownerVariation]), [
            'name' => 'Stolen',
            'content' => 'nope',
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->patch(route('snippets.variations.update', [$intruderSnippet, $ownerVariation]), [
            'name' => 'Wrong parent',
            'content' => 'nope',
        ])
        ->assertNotFound();

    expect($ownerVariation->refresh()->name)->toBe('Owner default');
});

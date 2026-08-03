<?php

use App\Models\LibraryCategory;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

test('users can create rename and delete their library categories without deleting workspaces', function () {
    $user = User::factory()->create();
    LibraryCategory::factory()->for($user)->create(['position' => 2]);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('library-categories.store'), [
            'name' => '  Programming   Projects  ',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $category = LibraryCategory::query()
        ->whereBelongsTo($user)
        ->where('name', 'Programming Projects')
        ->sole();

    expect($category->position)->toBe(3);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->patch(route('library-categories.update', $category), [
            'name' => 'Books',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $project = Project::factory()
        ->for($user)
        ->for($category->refresh(), 'libraryCategory')
        ->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->delete(route('library-categories.destroy', $category))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertModelMissing($category);
    expect($project->refresh()->library_category_id)->toBeNull();
});

test('library category names are unique per account and isolated between accounts', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $category = LibraryCategory::factory()->for($owner)->create([
        'name' => 'Programming',
    ]);

    $this->actingAs($owner)
        ->post(route('library-categories.store'), ['name' => 'Programming'])
        ->assertSessionHasErrors('name');

    $this->actingAs($otherUser)
        ->post(route('library-categories.store'), ['name' => 'Programming'])
        ->assertSessionHasNoErrors();

    $this->actingAs($otherUser)
        ->patch(route('library-categories.update', $category), [
            'name' => 'Private category',
        ])
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->delete(route('library-categories.destroy', $category))
        ->assertForbidden();

    expect($category->fresh()?->name)->toBe('Programming');
});

test('projects bundles and guide collections can be assigned to an owned library category', function (string $kind) {
    $user = User::factory()->create();
    $category = LibraryCategory::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('projects.store'), [
            'name' => "{$kind} workspace",
            'kind' => $kind,
            'description' => null,
            'library_category_id' => $category->id,
        ])
        ->assertSessionHasNoErrors();

    $project = Project::query()->whereBelongsTo($user)->sole();

    expect($project->library_category_id)->toBe($category->id);

    $this->actingAs($user)
        ->patch(route('projects.update', $project), [
            'name' => $project->name,
            'kind' => $project->kind,
            'description' => null,
            'library_category_id' => null,
        ])
        ->assertSessionHasNoErrors();

    expect($project->refresh()->library_category_id)->toBeNull();
})->with([
    Project::KIND_PROJECT,
    Project::KIND_BUNDLE,
    Project::KIND_GUIDE,
]);

test('projects cannot be assigned to another accounts library category', function () {
    $user = User::factory()->create();
    $foreignCategory = LibraryCategory::factory()
        ->for(User::factory())
        ->create();

    $this->actingAs($user)
        ->post(route('projects.store'), [
            'name' => 'Private workspace',
            'kind' => Project::KIND_PROJECT,
            'description' => null,
            'library_category_id' => $foreignCategory->id,
        ])
        ->assertSessionHasErrors('library_category_id');

    expect(Project::query()->whereBelongsTo($user)->exists())->toBeFalse();
});

test('workspace serializes owned categories including empty ones and project assignments', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $books = LibraryCategory::factory()->for($user)->create([
        'name' => 'Books',
        'position' => 2,
    ]);
    $programming = LibraryCategory::factory()->for($user)->create([
        'name' => 'Programming',
        'position' => 1,
    ]);
    $project = Project::factory()
        ->for($user)
        ->for($programming, 'libraryCategory')
        ->create(['name' => 'Laravel']);

    $foreignUser = User::factory()->create();
    LibraryCategory::factory()->for($foreignUser)->create([
        'name' => 'Private',
        'position' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('library_categories', 2)
            ->where('library_categories.0.id', $programming->id)
            ->where('library_categories.0.name', 'Programming')
            ->where('library_categories.1.id', $books->id)
            ->where('projects', fn (Collection $projects): bool => $projects
                ->contains(fn (array $candidate): bool => $candidate['id'] === $project->id
                    && $candidate['library_category_id'] === $programming->id)),
        );
});

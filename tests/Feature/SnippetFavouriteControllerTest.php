<?php

use App\Models\Framework;
use App\Models\Pin;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\Tag;
use App\Models\User;

test('users can favourite and unfavourite project and standalone files idempotently', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $projectFile = Snippet::factory()->forProject($project)->create([
        'is_favourite' => false,
    ]);
    $standaloneFile = Snippet::factory()->for($user)->create([
        'is_favourite' => false,
    ]);

    foreach ([$projectFile, $standaloneFile] as $file) {
        $this->actingAs($user)
            ->patch(route('snippets.favourite.update', $file), [
                'is_favourite' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->patch(route('snippets.favourite.update', $file), [
                'is_favourite' => true,
            ])
            ->assertSessionHasNoErrors();

        expect($file->refresh()->is_favourite)->toBeTrue();

        $this->actingAs($user)
            ->patch(route('snippets.favourite.update', $file), [
                'is_favourite' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->patch(route('snippets.favourite.update', $file), [
                'is_favourite' => false,
            ])
            ->assertSessionHasNoErrors();

        expect($file->refresh()->is_favourite)->toBeFalse();
    }
});

test('file favourites are protected from guests and other accounts', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $file = Snippet::factory()->for($owner)->create([
        'is_favourite' => false,
    ]);

    $this->patch(route('snippets.favourite.update', $file), [
        'is_favourite' => true,
    ])->assertRedirect(route('login'));

    expect($file->refresh()->is_favourite)->toBeFalse();

    $this->actingAs($intruder)
        ->patch(route('snippets.favourite.update', $file), [
            'is_favourite' => true,
        ])
        ->assertForbidden();

    expect($file->refresh()->is_favourite)->toBeFalse();

    $this->actingAs($owner)
        ->patch(route('snippets.favourite.update', $file), [
            'is_favourite' => true,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($intruder)
        ->patch(route('snippets.favourite.update', $file), [
            'is_favourite' => false,
        ])
        ->assertForbidden();

    expect($file->refresh()->is_favourite)->toBeTrue();
});

test('the favourite value must be boolean', function (array $payload) {
    $user = User::factory()->create();
    $file = Snippet::factory()->for($user)->create([
        'is_favourite' => false,
    ]);

    $this->actingAs($user)
        ->patch(route('snippets.favourite.update', $file), $payload)
        ->assertSessionHasErrors('is_favourite');

    expect($file->refresh()->is_favourite)->toBeFalse();
})->with([
    'missing value' => [[]],
    'non boolean value' => [['is_favourite' => 'yes']],
]);

test('favouriting files does not change any library pins', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $file = Snippet::factory()->forProject($project)->create([
        'is_favourite' => false,
    ]);
    $tag = Tag::factory()->for($user)->create();
    $framework = Framework::factory()->for($user)->create();

    foreach ([
        ['pinnable_type' => 'snippet', 'pinnable_key' => (string) $file->id],
        ['pinnable_type' => 'project', 'pinnable_key' => (string) $project->id],
        ['pinnable_type' => 'tag', 'pinnable_key' => (string) $tag->id],
        ['pinnable_type' => 'language', 'pinnable_key' => 'php'],
        ['pinnable_type' => 'framework', 'pinnable_key' => (string) $framework->id],
    ] as $attributes) {
        Pin::factory()->for($user)->create($attributes);
    }

    $pinsBefore = $user->pins()
        ->orderBy('pinnable_type')
        ->orderBy('pinnable_key')
        ->get(['pinnable_type', 'pinnable_key'])
        ->toArray();

    $this->actingAs($user)
        ->patch(route('snippets.favourite.update', $file), [
            'is_favourite' => true,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->patch(route('snippets.favourite.update', $file), [
            'is_favourite' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($file->refresh()->is_favourite)->toBeFalse()
        ->and($user->pins()
            ->orderBy('pinnable_type')
            ->orderBy('pinnable_key')
            ->get(['pinnable_type', 'pinnable_key'])
            ->toArray())->toBe($pinsBefore);
});

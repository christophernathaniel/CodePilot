<?php

use App\Models\Framework;
use App\Models\User;

test('users can add frameworks to their own library', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->post(route('frameworks.store'), ['name' => '  Ruby on   Rails  '])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $framework = Framework::query()
        ->whereBelongsTo($user)
        ->where('slug', 'ruby-on-rails')
        ->sole();

    expect($framework)
        ->name->toBe('Ruby on Rails')
        ->color->toBe('#64748b');
});

test('framework names can be shared by different accounts but their slugs cannot be duplicated within one account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Framework::factory()->for($user)->create([
        'name' => 'React',
        'slug' => 'react',
    ]);

    $this->actingAs($user)
        ->post(route('frameworks.store'), ['name' => ' React '])
        ->assertSessionHasErrors('slug');

    $this->actingAs($otherUser)
        ->post(route('frameworks.store'), ['name' => 'React'])
        ->assertSessionHasNoErrors();
});

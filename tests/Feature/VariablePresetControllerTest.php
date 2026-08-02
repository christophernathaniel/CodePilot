<?php

use App\Models\Project;
use App\Models\Snippet;
use App\Models\User;
use App\Models\VariablePreset;

test('users can create update and delete presets with known template variables', function () {
    $user = User::factory()->create();
    $snippet = Snippet::factory()
        ->for(Project::factory()->for($user))
        ->withTemplateVariables()
        ->create();

    $this->actingAs($user)
        ->post(route('snippets.presets.store', $snippet), [
            'name' => '  Production  ',
            'values' => [
                'base_url' => 'https://api.example.com',
                'api_token' => '',
            ],
        ])
        ->assertSessionHasNoErrors();

    $preset = VariablePreset::query()->whereBelongsTo($snippet)->sole();

    expect($preset->name)->toBe('Production')
        ->and($preset->values['api_token'])->toBe('');

    $this->actingAs($user)
        ->patch(route('snippets.presets.update', [$snippet, $preset]), [
            'name' => 'Live',
            'values' => ['base_url' => 'https://live.example.com'],
        ])
        ->assertSessionHasNoErrors();

    expect($preset->refresh()->name)->toBe('Live');

    $this->actingAs($user)
        ->delete(route('snippets.presets.destroy', [$snippet, $preset]))
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($preset);
});

test('preset keys must exist in the snippet template', function () {
    $user = User::factory()->create();
    $snippet = Snippet::factory()
        ->for(Project::factory()->for($user))
        ->withTemplateVariables()
        ->create();

    $this->actingAs($user)
        ->post(route('snippets.presets.store', $snippet), [
            'name' => 'Invalid',
            'values' => ['unknown_variable' => 'value'],
        ])
        ->assertSessionHasErrors('values');
});

test('preset keys may come from any named variation on the snippet', function () {
    $user = User::factory()->create();
    $snippet = Snippet::factory()
        ->for(Project::factory()->for($user))
        ->withVariation(
            "const url = '{{{base_url:https://example.com}}}';",
        )
        ->create();
    $snippet->variations()->create([
        'created_by_id' => $user->id,
        'name' => 'Authenticated',
        'content' => "const token = '{{{api_token:demo-token}}}';",
        'position' => 2,
        'is_default' => false,
    ]);

    $this->actingAs($user)
        ->post(route('snippets.presets.store', $snippet), [
            'name' => 'Production',
            'values' => ['api_token' => 'secret'],
        ])
        ->assertSessionHasNoErrors();

    $preset = $snippet->variablePresets()->sole();

    $this->actingAs($user)
        ->patch(route('snippets.presets.update', [$snippet, $preset]), [
            'name' => 'Production',
            'values' => [
                'base_url' => 'https://api.example.com',
                'api_token' => 'new-secret',
            ],
        ])
        ->assertSessionHasNoErrors();

    expect($preset->refresh()->values)->toBe([
        'base_url' => 'https://api.example.com',
        'api_token' => 'new-secret',
    ]);
});

test('preset policies and scoped bindings isolate accounts and snippets', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $snippet = Snippet::factory()
        ->for(Project::factory()->for($owner))
        ->withTemplateVariables()
        ->create();
    $preset = VariablePreset::factory()->for($snippet)->create();
    $intruderSnippet = Snippet::factory()
        ->for(Project::factory()->for($intruder))
        ->withTemplateVariables()
        ->create();

    $this->actingAs($intruder)
        ->post(route('snippets.presets.store', $snippet), [
            'name' => 'Unauthorized',
            'values' => ['base_url' => 'https://example.com'],
        ])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('snippets.presets.destroy', [$intruderSnippet, $preset]))
        ->assertNotFound();
});

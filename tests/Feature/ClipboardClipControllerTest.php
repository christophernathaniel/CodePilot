<?php

use App\Models\ClipboardClip;
use App\Models\ClipboardSession;
use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\SnippetVariation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('first clip capture creates an active clipboard and preserves immutable source provenance', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'CodePilot']);
    $root = Folder::factory()->for($project)->create(['name' => 'app']);
    $folder = Folder::factory()->nestedUnder($root)->create(['name' => 'Services']);
    $snippet = Snippet::factory()->inFolder($folder)->create([
        'title' => 'Build response',
        'filename' => 'BuildResponse.php',
        'language' => 'php',
    ]);
    $variation = SnippetVariation::factory()->for($snippet)->default()->create([
        'created_by_id' => $user->id,
        'name' => 'Readable',
    ]);
    $selectedCode = "    if (\$ready) {\n        run();\n    }\n";

    $this->actingAs($user)
        ->post(route('clipboard-clips.store'), [
            'clipboard_session_id' => null,
            'snippet_id' => (string) $snippet->id,
            'snippet_variation_id' => (string) $variation->id,
            'content' => $selectedCode,
            'representation' => ' SOURCE ',
            'line_start' => 12,
            'line_end' => 14,
        ])
        ->assertSessionHasNoErrors();

    $clipboardSession = $user->clipboardSessions()->sole();
    $clip = $clipboardSession->clips()->sole();

    expect($clipboardSession->name)->toBe('Clipboard 1')
        ->and($clipboardSession->is_active)->toBeTrue()
        ->and($clip->content)->toBe($selectedCode)
        ->and($clip->language)->toBe('php')
        ->and($clip->representation)->toBe('source')
        ->and($clip->source_title)->toBe('Build response')
        ->and($clip->source_filename)->toBe('BuildResponse.php')
        ->and($clip->source_project)->toBe('CodePilot')
        ->and($clip->source_folders)->toBe(['app', 'Services'])
        ->and($clip->source_variation)->toBe('Readable')
        ->and($clip->line_start)->toBe(12)
        ->and($clip->line_end)->toBe(14);

    $project->update(['name' => 'Renamed project']);
    $folder->update(['name' => 'Renamed folder']);
    $snippet->update(['title' => 'Renamed title', 'filename' => 'Renamed.php']);
    $variation->update(['name' => 'Renamed variation']);

    expect($clip->refresh()->source_title)->toBe('Build response')
        ->and($clip->source_filename)->toBe('BuildResponse.php')
        ->and($clip->source_project)->toBe('CodePilot')
        ->and($clip->source_folders)->toBe(['app', 'Services'])
        ->and($clip->source_variation)->toBe('Readable');

    $snippet->forceDelete();

    expect($clip->refresh()->snippet_id)->toBeNull()
        ->and($clip->snippet_variation_id)->toBeNull()
        ->and($clip->content)->toBe($selectedCode)
        ->and($clip->source_filename)->toBe('BuildResponse.php');
});

test('clip capture preserves whitespace-only selections exactly', function () {
    $user = User::factory()->create();
    $snippet = Snippet::factory()->for($user)->create();
    $variation = SnippetVariation::factory()->for($snippet)->create([
        'created_by_id' => $user->id,
    ]);
    $selection = "    \n";

    $this->actingAs($user)
        ->post(route('clipboard-clips.store'), [
            'snippet_id' => $snippet->id,
            'snippet_variation_id' => $variation->id,
            'content' => $selection,
            'representation' => 'source',
            'line_start' => 4,
            'line_end' => 5,
        ])
        ->assertSessionHasNoErrors();

    expect(ClipboardClip::query()->sole()->content)->toBe($selection);
});

test('users can paste system clipboard content into an existing clipboard', function () {
    $user = User::factory()->create();
    $clipboardSession = ClipboardSession::factory()->for($user)->active()->create();
    $pastedContent = "First line\nSecond line\n";

    $this->actingAs($user)
        ->post(route('clipboard-clips.store'), [
            'clipboard_session_id' => $clipboardSession->id,
            'content' => $pastedContent,
            'representation' => 'source',
            'line_start' => 1,
            'line_end' => 2,
        ])
        ->assertSessionHasNoErrors();

    $clip = $clipboardSession->clips()->sole();

    expect($clip->content)->toBe($pastedContent)
        ->and($clip->snippet_id)->toBeNull()
        ->and($clip->snippet_variation_id)->toBeNull()
        ->and($clip->language)->toBe('text')
        ->and($clip->representation)->toBe('source')
        ->and($clip->source_title)->toBe('Pasted content')
        ->and($clip->source_filename)->toBe('clipboard-paste.txt')
        ->and($clip->source_project)->toBeNull()
        ->and($clip->source_folders)->toBe([])
        ->and($clip->source_variation)->toBe('System clipboard')
        ->and($clip->line_start)->toBe(1)
        ->and($clip->line_end)->toBe(2);
});

test('clip capture validates clipboard and source ownership and selection metadata', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $ownerClipboard = ClipboardSession::factory()->for($owner)->active()->create();
    $intruderClipboard = ClipboardSession::factory()->for($intruder)->active()->create();
    $ownerSnippet = Snippet::factory()->for($owner)->create();
    $ownerVariation = SnippetVariation::factory()->for($ownerSnippet)->create([
        'created_by_id' => $owner->id,
    ]);
    $otherOwnerSnippet = Snippet::factory()->for($owner)->create();
    $otherOwnerVariation = SnippetVariation::factory()->for($otherOwnerSnippet)->create([
        'created_by_id' => $owner->id,
    ]);
    $intruderSnippet = Snippet::factory()->for($intruder)->create();
    $intruderVariation = SnippetVariation::factory()->for($intruderSnippet)->create([
        'created_by_id' => $intruder->id,
    ]);
    $validPayload = [
        'clipboard_session_id' => $ownerClipboard->id,
        'snippet_id' => $ownerSnippet->id,
        'snippet_variation_id' => $ownerVariation->id,
        'content' => 'return true;',
        'representation' => 'source',
        'line_start' => 2,
        'line_end' => 2,
    ];

    $this->actingAs($owner)
        ->post(route('clipboard-clips.store'), [
            ...$validPayload,
            'clipboard_session_id' => $intruderClipboard->id,
        ])
        ->assertSessionHasErrors('clipboard_session_id');

    $this->actingAs($owner)
        ->post(route('clipboard-clips.store'), [
            ...$validPayload,
            'snippet_id' => $intruderSnippet->id,
            'snippet_variation_id' => $intruderVariation->id,
        ])
        ->assertSessionHasErrors('snippet_id');

    $this->actingAs($owner)
        ->post(route('clipboard-clips.store'), [
            ...$validPayload,
            'snippet_variation_id' => $otherOwnerVariation->id,
        ])
        ->assertSessionHasErrors('snippet_variation_id');

    $this->actingAs($owner)
        ->post(route('clipboard-clips.store'), [
            ...$validPayload,
            'content' => '',
            'representation' => 'html',
            'line_start' => 5,
            'line_end' => 4,
        ])
        ->assertSessionHasErrors(['content', 'representation', 'line_end']);

    expect(ClipboardClip::query()->doesntExist())->toBeTrue();
});

test('clipboard clips can only be deleted by their owner', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $clipboardSession = ClipboardSession::factory()->for($owner)->active()->create();
    $clipToDelete = ClipboardClip::factory()->for($clipboardSession)->create();
    $remainingClip = ClipboardClip::factory()->for($clipboardSession)->create();

    $this->actingAs($intruder)
        ->delete(route('clipboard-clips.destroy', $clipToDelete))
        ->assertForbidden();

    $this->assertModelExists($clipToDelete);

    $this->actingAs($owner)
        ->delete(route('clipboard-clips.destroy', $clipToDelete))
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($clipToDelete);
    $this->assertModelExists($remainingClip);
    $this->assertModelExists($clipboardSession);
    expect($clipboardSession->clips()->count())->toBe(1);
});

test('workspace includes every clipboard summary and clips only for the active clipboard', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $active = ClipboardSession::factory()->for($user)->active()->create(['name' => 'Active']);
    $previous = ClipboardSession::factory()->for($user)->create(['name' => 'Previous']);
    $activeClips = ClipboardClip::factory()->count(2)->for($active)->create();
    ClipboardClip::factory()->for($previous)->create();
    $otherUser = User::factory()->create();
    ClipboardSession::factory()->for($otherUser)->active()->create(['name' => 'Private']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('snippets/workspace')
            ->has('clipboard_sessions', 2)
            ->where('clipboard_sessions.0.id', $active->id)
            ->where('clipboard_sessions.0.name', 'Active')
            ->where('clipboard_sessions.0.is_active', true)
            ->where('clipboard_sessions.0.clips_count', 2)
            ->has('clipboard_sessions.0.created_at')
            ->has('clipboard_sessions.0.updated_at')
            ->has('clipboard_sessions.0.clips', 2)
            ->where('clipboard_sessions.0.clips.0.id', $activeClips->last()->id)
            ->where('clipboard_sessions.0.clips.0.source.title', 'Hello world')
            ->where('clipboard_sessions.0.clips.0.source.folders', [])
            ->where('clipboard_sessions.1.id', $previous->id)
            ->where('clipboard_sessions.1.clips_count', 1)
            ->has('clipboard_sessions.1.clips', 0),
        );
});

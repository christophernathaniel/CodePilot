<?php

use App\Models\ClipboardClip;
use App\Models\ClipboardSession;
use App\Models\User;

test('users can create rename and activate uniquely named clipboard sessions', function () {
    $user = User::factory()->create();
    $first = ClipboardSession::factory()->for($user)->active()->create(['name' => 'Clipboard 1']);
    ClipboardSession::factory()->for($user)->create(['name' => 'Clipboard 3']);

    $this->actingAs($user)
        ->post(route('clipboards.store'), ['name' => null])
        ->assertSessionHasNoErrors();

    $generated = $user->clipboardSessions()->where('name', 'Clipboard 2')->sole();

    expect($generated->is_active)->toBeTrue()
        ->and($first->refresh()->is_active)->toBeFalse()
        ->and($user->clipboardSessions()->where('is_active', true)->count())->toBe(1);

    $this->actingAs($user)
        ->patch(route('clipboards.update', $generated), ['name' => '  Release   notes  '])
        ->assertSessionHasNoErrors();

    expect($generated->refresh()->name)->toBe('Release notes');

    $this->actingAs($user)
        ->patch(route('clipboards.activate', $first))
        ->assertSessionHasNoErrors();

    expect($first->refresh()->is_active)->toBeTrue()
        ->and($generated->refresh()->is_active)->toBeFalse()
        ->and($user->clipboardSessions()->where('is_active', true)->count())->toBe(1);

    $this->actingAs($user)
        ->post(route('clipboards.store'), ['name' => 'Release notes'])
        ->assertSessionHasErrors('name');

    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)
        ->post(route('clipboards.store'), ['name' => 'Release notes'])
        ->assertSessionHasNoErrors();
});

test('deleting the active clipboard selects the oldest remaining clipboard and cascades its clips', function () {
    $user = User::factory()->create();
    $oldest = ClipboardSession::factory()->for($user)->create(['name' => 'Oldest']);
    $active = ClipboardSession::factory()->for($user)->active()->create(['name' => 'Active']);
    $newest = ClipboardSession::factory()->for($user)->create(['name' => 'Newest']);
    $activeClip = ClipboardClip::factory()->for($active)->create();

    $this->actingAs($user)
        ->delete(route('clipboards.destroy', $active))
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($active);
    $this->assertModelMissing($activeClip);

    expect($oldest->refresh()->is_active)->toBeTrue()
        ->and($newest->refresh()->is_active)->toBeFalse()
        ->and($user->clipboardSessions()->where('is_active', true)->count())->toBe(1);

    $this->actingAs($user)
        ->delete(route('clipboards.destroy', $newest))
        ->assertSessionHasNoErrors();

    expect($oldest->refresh()->is_active)->toBeTrue();
});

test('users can clear a clipboard without deleting it or another clipboard contents', function () {
    $user = User::factory()->create();
    $active = ClipboardSession::factory()->for($user)->active()->create();
    $other = ClipboardSession::factory()->for($user)->create();
    $activeClips = ClipboardClip::factory()->count(2)->for($active)->create();
    $otherClip = ClipboardClip::factory()->for($other)->create();

    $this->actingAs($user)
        ->delete(route('clipboards.clips.clear', $active))
        ->assertSessionHasNoErrors();

    $this->assertModelExists($active);
    $activeClips->each(fn (ClipboardClip $clip) => $this->assertModelMissing($clip));
    $this->assertModelExists($otherClip);
});

test('clipboard session mutations are isolated between accounts', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $clipboardSession = ClipboardSession::factory()->for($owner)->active()->create();
    $clip = ClipboardClip::factory()->for($clipboardSession)->create();

    $this->actingAs($intruder)
        ->patch(route('clipboards.update', $clipboardSession), ['name' => 'Stolen'])
        ->assertForbidden();

    $this->actingAs($intruder)
        ->patch(route('clipboards.activate', $clipboardSession))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('clipboards.clips.clear', $clipboardSession))
        ->assertForbidden();

    $this->actingAs($intruder)
        ->delete(route('clipboards.destroy', $clipboardSession))
        ->assertForbidden();

    expect($clipboardSession->refresh()->name)->not->toBe('Stolen');
    $this->assertModelExists($clip);
});

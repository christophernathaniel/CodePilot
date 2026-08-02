<?php

use App\Actions\Snippets\BuildWorkspace;
use App\Models\Snippet;
use App\Models\SnippetCopyEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

test('copy events are idempotent and account scoped', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $snippet = Snippet::factory()->for($owner)->withVariation()->create();
    $variation = $snippet->variations()->sole();
    $eventUuid = (string) Str::uuid();
    $payload = [
        'event_uuid' => $eventUuid,
        'snippet_variation_id' => $variation->id,
        'variable_preset_id' => null,
        'method' => 'button',
        'representation' => 'source',
        'scope' => 'full',
        'selection_length' => 42,
    ];

    $this->actingAs($owner)
        ->post(route('snippets.copies.store', $snippet), $payload)
        ->assertNoContent();

    $this->actingAs($owner)
        ->post(route('snippets.copies.store', $snippet), [
            ...$payload,
            'method' => 'keyboard',
            'selection_length' => 99,
        ])
        ->assertNoContent();

    expect(SnippetCopyEvent::query()->where('event_uuid', $eventUuid)->count())->toBe(1);

    $this->assertDatabaseHas('snippet_copy_events', [
        'event_uuid' => $eventUuid,
        'user_id' => $owner->id,
        'snippet_id' => $snippet->id,
        'snippet_variation_id' => $variation->id,
        'method' => 'button',
        'selection_length' => 42,
    ]);

    $this->actingAs($intruder)
        ->post(route('snippets.copies.store', $snippet), [
            ...$payload,
            'event_uuid' => (string) Str::uuid(),
        ])
        ->assertForbidden();

    expect(SnippetCopyEvent::query()->where('snippet_id', $snippet->id)->count())->toBe(1)
        ->and(SnippetCopyEvent::query()->where('user_id', $intruder->id)->doesntExist())->toBeTrue();
});

test('workspace usage is relative to the account and marks old activity as stale', function () {
    $now = Carbon::parse('2026-07-28 12:00:00', 'UTC');
    Carbon::setTestNow($now);

    try {
        $user = User::factory()->create();
        $popular = Snippet::factory()->for($user)->withVariation()->create([
            'filename' => 'a-popular.php',
        ]);
        $occasional = Snippet::factory()->for($user)->withVariation()->create([
            'filename' => 'b-occasional.php',
        ]);
        $stale = Snippet::factory()->for($user)->withVariation()->create([
            'filename' => 'c-stale.php',
        ]);
        $unused = Snippet::factory()->for($user)->withVariation()->create([
            'filename' => 'd-unused.php',
        ]);

        SnippetCopyEvent::factory()->count(3)->for($popular)->create([
            'created_at' => $now->copy()->subDays(2),
            'updated_at' => $now->copy()->subDays(2),
        ]);
        SnippetCopyEvent::factory()->for($occasional)->create([
            'created_at' => $now->copy()->subDay(),
            'updated_at' => $now->copy()->subDay(),
        ]);
        SnippetCopyEvent::factory()->for($stale)->create([
            'created_at' => $now->copy()->subDays(31),
            'updated_at' => $now->copy()->subDays(31),
        ]);

        $otherUser = User::factory()->create();
        $otherUsersPopularSnippet = Snippet::factory()->for($otherUser)->create();
        SnippetCopyEvent::factory()->count(10)->for($otherUsersPopularSnippet)->create([
            'created_at' => $now->copy(),
            'updated_at' => $now->copy(),
        ]);

        $workspace = app(BuildWorkspace::class)->handle($user);
        $snippets = collect($workspace['standalone_snippets'])->keyBy('id');

        expect($snippets[$popular->id]['usage']['copies_30d'])->toBe(3)
            ->and($snippets[$popular->id]['usage']['copies_total'])->toBe(3)
            ->and($snippets[$popular->id]['usage']['relative_score'])->toBe(1.0)
            ->and($snippets[$popular->id]['usage']['indicator'])->toBe(2)
            ->and($snippets[$occasional->id]['usage']['copies_30d'])->toBe(1)
            ->and($snippets[$occasional->id]['usage']['relative_score'])->toBe(0.333)
            ->and($snippets[$occasional->id]['usage']['indicator'])->toBe(1)
            ->and($snippets[$stale->id]['usage']['copies_30d'])->toBe(0)
            ->and($snippets[$stale->id]['usage']['copies_total'])->toBe(1)
            ->and($snippets[$stale->id]['usage']['indicator'])->toBe(-1)
            ->and($snippets[$unused->id]['usage']['copies_total'])->toBe(0)
            ->and($snippets[$unused->id]['usage']['indicator'])->toBe(0);
    } finally {
        Carbon::setTestNow();
    }
});

test('workspace usage requires enough recent copies for each positive tier', function () {
    $now = Carbon::parse('2026-07-28 12:00:00', 'UTC');
    Carbon::setTestNow($now);

    try {
        $lowVolumeUser = User::factory()->create();
        $lowVolumeLeader = Snippet::factory()->for($lowVolumeUser)->create();
        SnippetCopyEvent::factory()->count(2)->for($lowVolumeLeader)->create([
            'created_at' => $now->copy(),
            'updated_at' => $now->copy(),
        ]);

        $mediumVolumeUser = User::factory()->create();
        $mediumVolumeLeader = Snippet::factory()->for($mediumVolumeUser)->create();
        SnippetCopyEvent::factory()->count(3)->for($mediumVolumeLeader)->create([
            'created_at' => $now->copy(),
            'updated_at' => $now->copy(),
        ]);

        $highVolumeUser = User::factory()->create();
        $highVolumeLeader = Snippet::factory()->for($highVolumeUser)->create();
        $relativelyLowerUsage = Snippet::factory()->for($highVolumeUser)->create();
        SnippetCopyEvent::factory()->count(30)->for($highVolumeLeader)->create([
            'created_at' => $now->copy(),
            'updated_at' => $now->copy(),
        ]);
        SnippetCopyEvent::factory()->count(10)->for($relativelyLowerUsage)->create([
            'created_at' => $now->copy(),
            'updated_at' => $now->copy(),
        ]);

        $lowVolumeWorkspace = app(BuildWorkspace::class)->handle($lowVolumeUser);
        $mediumVolumeWorkspace = app(BuildWorkspace::class)->handle($mediumVolumeUser);
        $highVolumeSnippets = collect(app(BuildWorkspace::class)->handle($highVolumeUser)['standalone_snippets'])
            ->keyBy('id');

        expect($lowVolumeWorkspace['standalone_snippets'][0]['usage']['relative_score'])->toBe(1.0)
            ->and($lowVolumeWorkspace['standalone_snippets'][0]['usage']['indicator'])->toBe(1)
            ->and($mediumVolumeWorkspace['standalone_snippets'][0]['usage']['relative_score'])->toBe(1.0)
            ->and($mediumVolumeWorkspace['standalone_snippets'][0]['usage']['indicator'])->toBe(2)
            ->and($highVolumeSnippets[$highVolumeLeader->id]['usage']['relative_score'])->toBe(1.0)
            ->and($highVolumeSnippets[$highVolumeLeader->id]['usage']['indicator'])->toBe(3)
            ->and($highVolumeSnippets[$relativelyLowerUsage->id]['usage']['copies_30d'])->toBe(10)
            ->and($highVolumeSnippets[$relativelyLowerUsage->id]['usage']['relative_score'])->toBe(0.333)
            ->and($highVolumeSnippets[$relativelyLowerUsage->id]['usage']['indicator'])->toBe(1);
    } finally {
        Carbon::setTestNow();
    }
});

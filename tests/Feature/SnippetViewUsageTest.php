<?php

use App\Actions\Snippets\BuildWorkspace;
use App\Models\Snippet;
use App\Models\SnippetCopyEvent;
use App\Models\SnippetViewEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

test('view events are idempotent, account scoped, and update the last opened time', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $snippet = Snippet::factory()->for($owner)->withVariation()->create();
    $eventUuid = (string) Str::uuid();

    $this->actingAs($owner)
        ->post(route('snippets.views.store', $snippet), ['event_uuid' => $eventUuid])
        ->assertNoContent();

    $this->actingAs($owner)
        ->post(route('snippets.views.store', $snippet), ['event_uuid' => $eventUuid])
        ->assertNoContent();

    expect(SnippetViewEvent::query()->where('event_uuid', $eventUuid)->count())->toBe(1)
        ->and($snippet->refresh()->last_opened_at)->not->toBeNull();

    $this->actingAs($intruder)
        ->post(route('snippets.views.store', $snippet), ['event_uuid' => (string) Str::uuid()])
        ->assertForbidden();
});

test('workspace usage weights copies above views while including favourites', function () {
    $now = Carbon::parse('2026-08-03 12:00:00', 'UTC');
    Carbon::setTestNow($now);

    try {
        $user = User::factory()->create();
        $copied = Snippet::factory()->for($user)->create(['filename' => 'copied.php']);
        $viewed = Snippet::factory()->for($user)->create(['filename' => 'viewed.php']);
        $favourite = Snippet::factory()->for($user)->create([
            'filename' => 'favourite.php',
            'is_favourite' => true,
        ]);

        SnippetCopyEvent::factory()->for($copied)->create(['created_at' => $now]);
        SnippetViewEvent::factory()->count(3)->for($viewed)->create(['created_at' => $now]);

        $snippets = collect(app(BuildWorkspace::class)->handle($user)['standalone_snippets'])
            ->keyBy('id');

        expect($snippets[$copied->id]['usage']['weighted_score'])->toBe(1.0)
            ->and($snippets[$viewed->id]['usage']['views_30d'])->toBe(3)
            ->and($snippets[$viewed->id]['usage']['weighted_score'])->toBe(0.6)
            ->and($snippets[$favourite->id]['usage']['weighted_score'])->toBe(0.75)
            ->and($snippets[$favourite->id]['usage']['indicator'])->toBe(1);
    } finally {
        Carbon::setTestNow();
    }
});

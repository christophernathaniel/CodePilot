<?php

namespace App\Actions\Snippets;

use App\Models\Snippet;
use App\Models\SnippetViewEvent;
use App\Models\User;

class RecordSnippetView
{
    /** @param array<string, mixed> $attributes */
    public function handle(User $user, Snippet $snippet, array $attributes): SnippetViewEvent
    {
        $snippet->forceFill(['last_opened_at' => now()])->save();

        return SnippetViewEvent::query()->firstOrCreate(
            ['event_uuid' => $attributes['event_uuid']],
            [
                'user_id' => $user->id,
                'snippet_id' => $snippet->id,
            ],
        );
    }
}

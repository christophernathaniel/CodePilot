<?php

namespace App\Actions\Snippets;

use App\Models\Snippet;
use App\Models\SnippetCopyEvent;
use App\Models\User;

class RecordSnippetCopy
{
    /** @param array<string, mixed> $attributes */
    public function handle(User $user, Snippet $snippet, array $attributes): SnippetCopyEvent
    {
        return SnippetCopyEvent::query()->firstOrCreate(
            ['event_uuid' => $attributes['event_uuid']],
            [
                'user_id' => $user->id,
                'snippet_id' => $snippet->id,
                'snippet_variation_id' => $attributes['snippet_variation_id'] ?? null,
                'variable_preset_id' => $attributes['variable_preset_id'] ?? null,
                'method' => $attributes['method'],
                'representation' => $attributes['representation'],
                'scope' => $attributes['scope'],
                'selection_length' => $attributes['selection_length'],
            ],
        );
    }
}

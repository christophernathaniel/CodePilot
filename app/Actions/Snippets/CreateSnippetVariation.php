<?php

namespace App\Actions\Snippets;

use App\Models\Snippet;
use App\Models\SnippetVariation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateSnippetVariation
{
    /** @param array{name: string, content: string} $attributes */
    public function handle(Snippet $snippet, User $user, array $attributes): SnippetVariation
    {
        return DB::transaction(function () use ($snippet, $user, $attributes): SnippetVariation {
            $lockedSnippet = Snippet::query()->lockForUpdate()->findOrFail($snippet->id);

            return $lockedSnippet->variations()->create([
                ...$attributes,
                'created_by_id' => $user->id,
                'position' => ((int) $lockedSnippet->variations()->max('position')) + 1,
                'is_default' => false,
            ]);
        }, attempts: 3);
    }
}

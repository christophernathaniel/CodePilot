<?php

namespace App\Actions\Snippets;

use App\Models\Snippet;
use App\Models\SnippetVariation;
use Illuminate\Support\Facades\DB;

class SetDefaultSnippetVariation
{
    public function handle(SnippetVariation $snippetVariation): void
    {
        DB::transaction(function () use ($snippetVariation): void {
            $snippet = Snippet::query()
                ->lockForUpdate()
                ->findOrFail($snippetVariation->snippet_id);

            $snippet->variations()->update(['is_default' => false]);
            $snippetVariation->update(['is_default' => true]);
        }, attempts: 3);
    }
}

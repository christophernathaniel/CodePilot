<?php

namespace App\Models;

use Database\Factories\ClipboardClipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'clipboard_session_id',
    'snippet_id',
    'snippet_variation_id',
    'content',
    'language',
    'representation',
    'source_title',
    'source_filename',
    'source_project',
    'source_folders',
    'source_variation',
    'line_start',
    'line_end',
])]
class ClipboardClip extends Model
{
    /** @use HasFactory<ClipboardClipFactory> */
    use HasFactory;

    /** @return BelongsTo<ClipboardSession, $this> */
    public function clipboardSession(): BelongsTo
    {
        return $this->belongsTo(ClipboardSession::class);
    }

    /** @return BelongsTo<Snippet, $this> */
    public function snippet(): BelongsTo
    {
        return $this->belongsTo(Snippet::class);
    }

    /** @return BelongsTo<SnippetVariation, $this> */
    public function snippetVariation(): BelongsTo
    {
        return $this->belongsTo(SnippetVariation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source_folders' => 'array',
            'line_start' => 'integer',
            'line_end' => 'integer',
        ];
    }
}

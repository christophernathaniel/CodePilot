<?php

namespace App\Models;

use Database\Factories\SnippetCopyEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_uuid',
    'user_id',
    'snippet_id',
    'snippet_variation_id',
    'variable_preset_id',
    'method',
    'representation',
    'scope',
    'selection_length',
])]
class SnippetCopyEvent extends Model
{
    /** @use HasFactory<SnippetCopyEventFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /** @return BelongsTo<VariablePreset, $this> */
    public function variablePreset(): BelongsTo
    {
        return $this->belongsTo(VariablePreset::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'selection_length' => 'integer',
        ];
    }
}

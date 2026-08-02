<?php

namespace App\Models;

use Database\Factories\VariablePresetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['snippet_id', 'name', 'values'])]
class VariablePreset extends Model
{
    /** @use HasFactory<VariablePresetFactory> */
    use HasFactory;

    /** @return BelongsTo<Snippet, $this> */
    public function snippet(): BelongsTo
    {
        return $this->belongsTo(Snippet::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'values' => 'array',
        ];
    }
}

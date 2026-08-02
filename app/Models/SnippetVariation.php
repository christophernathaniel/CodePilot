<?php

namespace App\Models;

use Database\Factories\SnippetVariationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['snippet_id', 'created_by_id', 'name', 'content', 'position', 'is_default'])]
class SnippetVariation extends Model
{
    /** @use HasFactory<SnippetVariationFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'content' => '',
        'position' => 0,
        'is_default' => false,
    ];

    /** @return BelongsTo<Snippet, $this> */
    public function snippet(): BelongsTo
    {
        return $this->belongsTo(Snippet::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_default' => 'boolean',
        ];
    }
}

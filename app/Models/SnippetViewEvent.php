<?php

namespace App\Models;

use Database\Factories\SnippetViewEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_uuid', 'user_id', 'snippet_id'])]
class SnippetViewEvent extends Model
{
    /** @use HasFactory<SnippetViewEventFactory> */
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
}

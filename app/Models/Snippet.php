<?php

namespace App\Models;

use Database\Factories\SnippetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'project_id',
    'folder_id',
    'location_key',
    'title',
    'filename',
    'language',
    'content_type',
    'description',
    'position',
    'last_opened_at',
    'is_favourite',
])]
class Snippet extends Model
{
    /** @use HasFactory<SnippetFactory> */
    use HasFactory, SoftDeletes;

    public const CONTENT_TYPE_GUIDE = 'guide';

    public const CONTENT_TYPE_SNIPPET = 'snippet';

    public const CONTENT_TYPES = [
        self::CONTENT_TYPE_SNIPPET,
        self::CONTENT_TYPE_GUIDE,
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'language' => 'plaintext',
        'content_type' => self::CONTENT_TYPE_SNIPPET,
        'position' => 0,
        'is_favourite' => false,
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Folder, $this> */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    /** @return HasMany<SnippetVariation, $this> */
    public function variations(): HasMany
    {
        return $this->hasMany(SnippetVariation::class);
    }

    /**
     * Route-scoped alias for the {snippetVariation} parameter.
     *
     * @return HasMany<SnippetVariation, $this>
     */
    public function snippetVariations(): HasMany
    {
        return $this->variations();
    }

    /** @return HasMany<VariablePreset, $this> */
    public function variablePresets(): HasMany
    {
        return $this->hasMany(VariablePreset::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** @return BelongsToMany<Framework, $this> */
    public function frameworks(): BelongsToMany
    {
        return $this->belongsToMany(Framework::class);
    }

    /** @return HasMany<SnippetCopyEvent, $this> */
    public function copyEvents(): HasMany
    {
        return $this->hasMany(SnippetCopyEvent::class);
    }

    /** @return HasMany<SnippetViewEvent, $this> */
    public function viewEvents(): HasMany
    {
        return $this->hasMany(SnippetViewEvent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_favourite' => 'boolean',
            'position' => 'integer',
            'last_opened_at' => 'datetime',
        ];
    }
}

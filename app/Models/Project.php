<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'library_category_id', 'kind', 'name', 'description', 'position'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    public const KIND_BUNDLE = 'bundle';

    public const KIND_GUIDE = 'guide';

    public const KIND_PROJECT = 'project';

    public const KINDS = [
        self::KIND_PROJECT,
        self::KIND_BUNDLE,
        self::KIND_GUIDE,
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'kind' => self::KIND_PROJECT,
        'position' => 0,
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LibraryCategory, $this> */
    public function libraryCategory(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class);
    }

    /** @return HasMany<Folder, $this> */
    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    /** @return HasMany<Snippet, $this> */
    public function snippets(): HasMany
    {
        return $this->hasMany(Snippet::class);
    }

    /** @return BelongsToMany<Framework, $this> */
    public function frameworks(): BelongsToMany
    {
        return $this->belongsToMany(Framework::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }
}

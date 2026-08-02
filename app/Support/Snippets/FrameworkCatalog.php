<?php

namespace App\Support\Snippets;

use App\Models\User;

class FrameworkCatalog
{
    /** @return list<array{name: string, slug: string, color: string}> */
    public static function defaults(): array
    {
        return [
            ['name' => 'WordPress', 'slug' => 'wordpress', 'color' => '#60a5fa'],
            ['name' => 'Laravel', 'slug' => 'laravel', 'color' => '#94a3b8'],
            ['name' => 'React', 'slug' => 'react', 'color' => '#38bdf8'],
        ];
    }

    public static function seedFor(User $user): void
    {
        foreach (self::defaults() as $framework) {
            $user->frameworks()->firstOrCreate(
                ['slug' => $framework['slug']],
                ['name' => $framework['name'], 'color' => $framework['color']],
            );
        }
    }
}

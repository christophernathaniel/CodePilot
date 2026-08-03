<?php

use App\Models\Project;
use App\Models\Snippet;
use App\Models\User;
use App\Support\Snippets\GuideStepParser;
use Database\Seeders\WordPressGutenbergGuidesSeeder;

test('it seeds the complete WordPress Gutenberg guide collection for the development account', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);
    $otherUser = User::factory()->create(['email' => 'someone@example.com']);

    $this->seed(WordPressGutenbergGuidesSeeder::class);

    $project = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'WordPress Gutenberg')
        ->with(['frameworks', 'snippets.tags', 'snippets.frameworks', 'snippets.variations'])
        ->sole();
    $guides = $project->snippets->sortBy('position')->values();

    expect($project->kind)->toBe(Project::KIND_GUIDE)
        ->and($project->description)->toContain('WordPress 7.0.2', 'WordPress 7.1')
        ->and($project->frameworks->pluck('slug')->all())->toBe(['wordpress'])
        ->and($guides)->toHaveCount(7)
        ->and($guides->pluck('filename')->all())->toBe([
            '01-docker-wordpress-gutenberg.guide.md',
            '02-create-theme-pattern.guide.md',
            '03-create-theme-block.guide.md',
            '04-create-wordpress-plugin.guide.md',
            '05-headless-wordpress.guide.md',
            '06-custom-rest-api-endpoint.guide.md',
            '07-custom-field-database-table.guide.md',
        ])
        ->and($otherUser->projects()->count())->toBe(0)
        ->and($otherUser->snippets()->count())->toBe(0)
        ->and($otherUser->tags()->count())->toBe(0)
        ->and($otherUser->frameworks()->count())->toBe(0);

    $guides->each(function (Snippet $guide): void {
        $variation = $guide->variations->sole();
        $steps = (new GuideStepParser)->parse($variation->content);

        expect($guide->content_type)->toBe(Snippet::CONTENT_TYPE_GUIDE)
            ->and($guide->language)->toBe('markdown')
            ->and($guide->project_id)->not->toBeNull()
            ->and($guide->folder_id)->toBeNull()
            ->and($guide->frameworks->pluck('slug')->all())->toBe(['wordpress'])
            ->and($guide->tags->pluck('slug')->all())
            ->toContain('wordpress', 'guide', 'accessibility')
            ->and($variation->name)->toBe('WordPress 7.0 stable / 7.1 aware')
            ->and($variation->is_default)->toBeTrue()
            ->and($steps)->not->toBeEmpty()
            ->and(collect($steps)->pluck('key')->duplicates()->all())->toBe([])
            ->and(collect($steps)->every(
                fn (array $step): bool => $step['title'] !== '' && $step['instructions'] !== '',
            ))->toBeTrue()
            ->and(collect($steps)->flatMap(fn (array $step): array => $step['code_blocks']))
            ->not->toBeEmpty();
    });
});

test('rerunning the WordPress Gutenberg guide seeder creates no duplicates', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);

    $this->seed(WordPressGutenbergGuidesSeeder::class);
    $this->seed(WordPressGutenbergGuidesSeeder::class);

    $project = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'WordPress Gutenberg')
        ->sole();

    expect($user->projects()->where('name', 'WordPress Gutenberg')->count())->toBe(1)
        ->and($project->snippets()->count())->toBe(7)
        ->and($project->snippets()->withCount('variations')->get()->pluck('variations_count')->all())
        ->toBe(array_fill(0, 7, 1));
});

test('the guide examples retain the requested WordPress workflows and safeguards', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);

    $this->seed(WordPressGutenbergGuidesSeeder::class);

    $contents = $user->projects()
        ->where('name', 'WordPress Gutenberg')
        ->sole()
        ->snippets()
        ->with('variations')
        ->get()
        ->mapWithKeys(fn (Snippet $snippet): array => [
            $snippet->filename => $snippet->variations->sole()->content,
        ]);

    expect($contents['01-docker-wordpress-gutenberg.guide.md'])
        ->toContain('wordpress:7.0.2-php8.3-apache')
        ->toContain('https://github.com/olliewp/ollie.git')
        ->toContain('wordpress-seo', 'wp-super-cache')
        ->and($contents['02-create-theme-pattern.guide.md'])
        ->toContain('patterns/feature-callout.php', 'theme.json', 'WCAG 2.2 AA')
        ->and($contents['03-create-theme-block.guide.md'])
        ->toContain('"apiVersion": 3', 'get_block_wrapper_attributes', 'iframed editor')
        ->and($contents['04-create-wordpress-plugin.guide.md'])
        ->toContain('cron_schedules', 'wp_schedule_event', '5 * MINUTE_IN_SECONDS', 'wp_clear_scheduled_hook')
        ->and($contents['05-headless-wordpress.guide.md'])
        ->toContain('headless WordPress', 'Application Password', 'signed revalidation')
        ->and($contents['06-custom-rest-api-endpoint.guide.md'])
        ->toContain('permission_callback', '@wordpress/api-fetch', 'WP_REST_Response')
        ->and($contents['07-custom-field-database-table.guide.md'])
        ->toContain('$wpdb->prefix', 'dbDelta', "current_user_can( 'edit_post'", 'PluginDocumentSettingPanel');
});

<?php

use App\Models\Project;
use App\Models\Snippet;
use App\Models\SnippetVariation;
use App\Models\User;
use App\Support\Snippets\SnippetSectionParser;
use Database\Seeders\RequestsBundleSeeder;

test('it seeds the account-scoped Requests bundle with searchable request recipes', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);
    $otherUser = User::factory()->create(['email' => 'someone@example.com']);

    $this->seed(RequestsBundleSeeder::class);

    $bundle = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'Requests')
        ->with([
            'folders',
            'frameworks',
            'snippets.folder',
            'snippets.tags',
            'snippets.frameworks',
            'snippets.variations',
        ])
        ->sole();
    $snippets = $bundle->snippets->sortBy('filename')->values();
    $snippetsByFilename = $snippets->keyBy('filename');
    $sectionParser = new SnippetSectionParser;
    $sections = $snippets->flatMap(
        fn (Snippet $snippet): array => $sectionParser->parse(
            $snippet->variations->sole()->content,
        ),
    );

    expect($bundle->kind)->toBe(Project::KIND_BUNDLE)
        ->and($bundle->position)->toBe(9)
        ->and($bundle->frameworks->pluck('slug')->all())->toBe(['wordpress'])
        ->and($bundle->folders->sortBy('position')->pluck('name')->values()->all())
        ->toBe([
            'PHP cURL',
            'JavaScript & TypeScript Fetch',
            'WordPress HTTP & AJAX',
            'XML',
        ])
        ->and($bundle->folders->where('name', 'PHP cURL')->sole()->snippets()->count())->toBe(1)
        ->and($bundle->folders->where('name', 'JavaScript & TypeScript Fetch')->sole()->snippets()->count())->toBe(2)
        ->and($bundle->folders->where('name', 'WordPress HTTP & AJAX')->sole()->snippets()->count())->toBe(3)
        ->and($bundle->folders->where('name', 'XML')->sole()->snippets()->count())->toBe(2)
        ->and($snippets)->toHaveCount(8)
        ->and($snippets->pluck('filename')->all())->toBe([
            'admin-ajax.js',
            'admin-ajax.php',
            'curl-json.php',
            'fetch-client.ts',
            'fetch-json.js',
            'fetch-xml.js',
            'wordpress-http.php',
            'xml-requests.php',
        ])
        ->and($snippets->pluck('content_type')->unique()->all())
        ->toBe([Snippet::CONTENT_TYPE_SNIPPET])
        ->and($snippets->countBy('language')->sortKeys()->all())
        ->toBe(['javascript' => 3, 'php' => 4, 'typescript' => 1])
        ->and($snippets->sum(fn (Snippet $snippet): int => $snippet->variations->count()))
        ->toBe(8)
        ->and($snippets->every(
            fn (Snippet $snippet): bool => $snippet->variations->sole()->is_default,
        ))->toBeTrue()
        ->and($sections)->toHaveCount(36)
        ->and($snippets->every(
            fn (Snippet $snippet): bool => count(
                $sectionParser->parse($snippet->variations->sole()->content),
            ) >= 3,
        ))->toBeTrue()
        ->and($snippets->every(
            fn (Snippet $snippet): bool => collect(['requests', 'http', 'security', 'error-handling'])
                ->diff($snippet->tags->pluck('slug'))
                ->isEmpty(),
        ))->toBeTrue()
        ->and($snippets->where('folder.name', 'WordPress HTTP & AJAX')->every(
            fn (Snippet $snippet): bool => $snippet->frameworks->pluck('slug')->all() === ['wordpress'],
        ))->toBeTrue()
        ->and($snippets->reject(
            fn (Snippet $snippet): bool => $snippet->folder?->name === 'WordPress HTTP & AJAX',
        )->every(fn (Snippet $snippet): bool => $snippet->frameworks->isEmpty()))
        ->toBeTrue()
        ->and($snippetsByFilename->get('curl-json.php')?->tags->pluck('slug')->all())
        ->toContain('php', 'curl', 'json', 'retries', 'template-variables')
        ->and($snippetsByFilename->get('fetch-client.ts')?->tags->pluck('slug')->all())
        ->toContain('typescript', 'fetch-api', 'json')
        ->and($snippetsByFilename->get('fetch-xml.js')?->tags->pluck('slug')->all())
        ->toContain('javascript', 'fetch-api', 'xml')
        ->and($otherUser->projects()->count())->toBe(0)
        ->and($otherUser->snippets()->count())->toBe(0)
        ->and($otherUser->tags()->count())->toBe(0)
        ->and($otherUser->frameworks()->count())->toBe(0);
});

test('the Requests recipes retain representative protocol and security safeguards', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);

    $this->seed(RequestsBundleSeeder::class);

    $contents = $user->projects()
        ->where('name', 'Requests')
        ->sole()
        ->snippets()
        ->with('variations')
        ->get()
        ->mapWithKeys(fn (Snippet $snippet): array => [
            $snippet->filename => $snippet->variations->sole()->content,
        ]);
    $allContents = $contents->implode("\n");

    expect($contents['curl-json.php'])
        ->toContain(
            'CURLOPT_CONNECTTIMEOUT',
            'CURLOPT_TIMEOUT',
            'CURLINFO_RESPONSE_CODE',
            'JSON_THROW_ON_ERROR',
            "'Authorization: Bearer '.\$token",
            '{{{api_token:replace-at-runtime}}}',
            '$exception->statusCode === 429',
        )
        ->and($contents['fetch-json.js'])
        ->toContain('response.ok', 'AbortController', 'error.status === 429', 'fetchItemsWithRetry')
        ->and($contents['fetch-client.ts'])
        ->toContain('class ApiError', 'requestJson<T>', 'AbortSignal', "method: 'POST'")
        ->and($contents['wordpress-http.php'])
        ->toContain('wp_remote_get', 'wp_remote_post', 'is_wp_error', 'wp_safe_remote_get')
        ->and($contents['admin-ajax.php'])
        ->toContain(
            "check_ajax_referer('requests_save_setting', 'nonce')",
            "current_user_can('manage_options')",
            'sanitize_text_field',
            'wp_unslash',
        )
        ->and($contents['admin-ajax.js'])
        ->toContain("credentials: 'same-origin'", 'AbortController', "action: 'requests_save_setting'")
        ->and($contents['xml-requests.php'])
        ->toContain('LIBXML_NONET', 'maximumBytes', 'CURLINFO_RESPONSE_CODE', 'Content-Type: application/xml')
        ->and($contents['fetch-xml.js'])
        ->toContain('DOMParser', "querySelector('parsererror')", 'XMLSerializer', 'response.ok', 'AbortController')
        ->and($allContents)
        ->not->toContain(
            'CURLOPT_SSL_VERIFYPEER => false',
            'rejectUnauthorized: false',
            'LIBXML_NOENT',
            'wp_ajax_nopriv_requests_save_setting',
        );
});

test('reseeding updates canonical Requests content without duplicates or deleting custom variations', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);

    $this->seed(RequestsBundleSeeder::class);

    $bundle = Project::query()->whereBelongsTo($user)->where('name', 'Requests')->sole();
    $bundle->update(['position' => 43]);
    $snippet = $bundle->snippets()->where('filename', 'fetch-json.js')->sole();
    $snippet->variations()->where('name', 'Default')->sole()->update([
        'content' => 'stale seeded content',
    ]);
    $snippet->update(['title' => 'User changed seeded title']);
    $customVariation = $snippet->variations()->create([
        'created_by_id' => $user->id,
        'name' => 'Custom authenticated request',
        'content' => 'export const customRequest = true;',
        'position' => 9,
        'is_default' => true,
    ]);

    $this->seed(RequestsBundleSeeder::class);

    $refreshedBundle = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'Requests')
        ->sole();
    $refreshedSnippet = $refreshedBundle->snippets()
        ->where('filename', 'fetch-json.js')
        ->with('variations')
        ->sole();
    $refreshedDefault = $refreshedSnippet->variations->where('name', 'Default')->sole();
    $refreshedCustom = $refreshedSnippet->variations->where('name', $customVariation->name)->sole();

    expect($user->projects()->where('name', 'Requests')->count())->toBe(1)
        ->and($refreshedBundle->position)->toBe(43)
        ->and($refreshedBundle->folders()->count())->toBe(4)
        ->and($refreshedBundle->snippets()->count())->toBe(8)
        ->and($refreshedSnippet->title)->toBe('JavaScript Fetch JSON Requests')
        ->and($refreshedSnippet->variations)->toHaveCount(2)
        ->and($refreshedDefault->content)->toContain('fetchItemsWithRetry', 'AbortController')
        ->and($refreshedDefault->is_default)->toBeTrue()
        ->and($refreshedCustom)->toBeInstanceOf(SnippetVariation::class)
        ->and($refreshedCustom->content)->toBe('export const customRequest = true;')
        ->and($refreshedCustom->is_default)->toBeFalse();
});

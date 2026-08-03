<?php

use App\Models\Project;
use App\Models\Snippet;
use App\Models\SnippetVariation;
use App\Models\User;
use App\Support\Snippets\SnippetSectionParser;
use Database\Seeders\ClassesBundleSeeder;

test('it seeds the account-scoped classes reference bundle with searchable sections', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);
    $otherUser = User::factory()->create(['email' => 'someone@example.com']);

    $this->seed(ClassesBundleSeeder::class);

    $bundle = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'CLASSES')
        ->with(['folders', 'frameworks', 'snippets.tags', 'snippets.frameworks', 'snippets.variations'])
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
        ->and($bundle->position)->toBe(8)
        ->and($bundle->description)->toContain('PHP', 'JavaScript', 'TypeScript', 'WordPress')
        ->and($bundle->frameworks->pluck('slug')->all())->toBe(['wordpress'])
        ->and($bundle->folders->sortBy('position')->pluck('name')->values()->all())
        ->toBe(['PHP', 'JavaScript', 'TypeScript', 'WordPress'])
        ->and($bundle->folders->where('name', 'PHP')->sole()->snippets()->count())->toBe(3)
        ->and($bundle->folders->where('name', 'JavaScript')->sole()->snippets()->count())->toBe(1)
        ->and($bundle->folders->where('name', 'TypeScript')->sole()->snippets()->count())->toBe(2)
        ->and($bundle->folders->where('name', 'WordPress')->sole()->snippets()->count())->toBe(2)
        ->and($snippets)->toHaveCount(8)
        ->and($snippets->pluck('filename')->all())->toBe([
            'ContentHierarchy.php',
            'HookSubscriber.php',
            'NotifierService.php',
            'Plugin.php',
            'ValueObjectAndDto.php',
            'api-client.js',
            'handler-factory.ts',
            'repository.ts',
        ])
        ->and($snippets->pluck('content_type')->unique()->all())
        ->toBe([Snippet::CONTENT_TYPE_SNIPPET])
        ->and($snippets->countBy('language')->all())
        ->toBe(['php' => 5, 'javascript' => 1, 'typescript' => 2])
        ->and($snippets->sum(fn (Snippet $snippet): int => $snippet->variations->count()))
        ->toBe(8)
        ->and($snippets->every(
            fn (Snippet $snippet): bool => $snippet->variations->sole()->is_default,
        ))->toBeTrue()
        ->and($sections)->toHaveCount(25)
        ->and($snippets->every(
            fn (Snippet $snippet): bool => count(
                $sectionParser->parse($snippet->variations->sole()->content),
            ) >= 3,
        ))->toBeTrue()
        ->and($user->tags()->count())->toBe(20)
        ->and($user->frameworks()->count())->toBe(3)
        ->and($otherUser->projects()->count())->toBe(0)
        ->and($otherUser->snippets()->count())->toBe(0)
        ->and($otherUser->tags()->count())->toBe(0)
        ->and($otherUser->frameworks()->count())->toBe(0);

    $valueObject = $snippetsByFilename->get('ValueObjectAndDto.php');
    $javascript = $snippetsByFilename->get('api-client.js');
    $repository = $snippetsByFilename->get('repository.ts');
    $plugin = $snippetsByFilename->get('Plugin.php');
    $hookSubscriber = $snippetsByFilename->get('HookSubscriber.php');

    expect($valueObject)->toBeInstanceOf(Snippet::class)
        ->and($valueObject->tags->sortBy('slug')->pluck('slug')->values()->all())
        ->toBe(['classes', 'dto', 'oop', 'php', 'value-object'])
        ->and($valueObject->frameworks)->toBeEmpty()
        ->and($valueObject->variations->sole()->content)
        ->toContain('final readonly class Money', 'final readonly class CreateOrderData')
        ->and($javascript)->toBeInstanceOf(Snippet::class)
        ->and($javascript->tags->pluck('slug')->all())
        ->toContain('private-fields', 'factory', 'error-handling')
        ->and($javascript->frameworks)->toBeEmpty()
        ->and($javascript->variations->sole()->content)
        ->toContain('export class HttpError', '#baseUrl', 'createApiClient')
        ->and($repository)->toBeInstanceOf(Snippet::class)
        ->and($repository->variations->sole()->content)
        ->toContain('interface Repository<', 'class InMemoryRepository<')
        ->and($plugin)->toBeInstanceOf(Snippet::class)
        ->and($plugin->frameworks->pluck('slug')->all())->toBe(['wordpress'])
        ->and($plugin->variations->sole()->content)
        ->toContain('namespace Acme\\Classes;', "add_action('plugins_loaded'")
        ->and($hookSubscriber)->toBeInstanceOf(Snippet::class)
        ->and($hookSubscriber->frameworks->pluck('slug')->all())->toBe(['wordpress'])
        ->and($hookSubscriber->variations->sole()->content)
        ->toContain('interface HookSubscriber', "add_filter('the_title'");
});

test('reseeding updates canonical classes content without duplicating or deleting user variations', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);

    $this->seed(ClassesBundleSeeder::class);

    $bundle = Project::query()->whereBelongsTo($user)->where('name', 'CLASSES')->sole();
    $bundle->update(['position' => 42]);
    $snippet = $bundle->snippets()->where('filename', 'api-client.js')->sole();
    $defaultVariation = $snippet->variations()->where('name', 'Default')->sole();
    $defaultVariation->update(['content' => 'stale seeded content']);
    $snippet->update(['title' => 'User changed seeded title']);
    $userVariation = $snippet->variations()->create([
        'created_by_id' => $user->id,
        'name' => 'Abort-aware user variation',
        'content' => 'export const userVariation = true;',
        'position' => 9,
        'is_default' => true,
    ]);

    $this->seed(ClassesBundleSeeder::class);

    $refreshedBundle = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'CLASSES')
        ->sole();
    $refreshedSnippet = $refreshedBundle->snippets()
        ->where('filename', 'api-client.js')
        ->with('variations')
        ->sole();
    $refreshedDefault = $refreshedSnippet->variations->where('name', 'Default')->sole();
    $refreshedUserVariation = $refreshedSnippet->variations->where('name', $userVariation->name)->sole();

    expect($user->projects()->where('name', 'CLASSES')->count())->toBe(1)
        ->and($refreshedBundle->position)->toBe(42)
        ->and($refreshedBundle->folders()->count())->toBe(4)
        ->and($refreshedBundle->snippets()->count())->toBe(8)
        ->and($refreshedSnippet->title)->toBe('Private Fields, Factory, and Custom Error')
        ->and($refreshedSnippet->variations)->toHaveCount(2)
        ->and($refreshedDefault->content)->toContain('export class HttpError', '#baseUrl')
        ->and($refreshedDefault->is_default)->toBeTrue()
        ->and($refreshedUserVariation)->toBeInstanceOf(SnippetVariation::class)
        ->and($refreshedUserVariation->content)->toBe('export const userVariation = true;')
        ->and($refreshedUserVariation->is_default)->toBeFalse();
});

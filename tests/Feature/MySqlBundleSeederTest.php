<?php

use App\Models\Project;
use App\Models\Snippet;
use App\Models\SnippetVariation;
use App\Models\User;
use App\Support\Snippets\SnippetSectionParser;
use Database\Seeders\MySqlBundleSeeder;

test('it seeds the account-scoped MySQL bundle with progressive searchable SQL references', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);
    $otherUser = User::factory()->create(['email' => 'someone@example.com']);

    $this->seed(MySqlBundleSeeder::class);

    $bundle = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'MySQL')
        ->with(['folders', 'snippets.tags', 'snippets.variations'])
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
        ->and($bundle->position)->toBe(10)
        ->and($bundle->description)->toContain('MySQL 8', 'schema design', 'query-plan diagnostics')
        ->and($bundle->folders->sortBy('position')->pluck('name')->values()->all())
        ->toBe([
            '01-setup-and-inspection',
            '02-schema-and-data',
            '03-querying-and-analysis',
            '04-json-and-performance',
        ])
        ->and($bundle->folders->every(
            fn ($folder): bool => $folder->snippets()->count() === 2,
        ))->toBeTrue()
        ->and($snippets)->toHaveCount(8)
        ->and($snippets->pluck('filename')->all())->toBe([
            '01-commerce-schema-and-indexes.sql',
            '01-database-user-and-grants.sql',
            '01-joins-and-aggregation.sql',
            '01-json-operations-and-indexable-fields.sql',
            '02-crud-upsert-and-transactions.sql',
            '02-ctes-windows-top-n-and-keyset.sql',
            '02-explain-analyze-and-index-diagnostics.sql',
            '02-inspect-server-and-schema.sql',
        ])
        ->and($snippets->pluck('content_type')->unique()->all())
        ->toBe([Snippet::CONTENT_TYPE_SNIPPET])
        ->and($snippets->pluck('language')->unique()->all())->toBe(['sql'])
        ->and($snippets->sum(fn (Snippet $snippet): int => $snippet->variations->count()))
        ->toBe(8)
        ->and($snippets->every(
            fn (Snippet $snippet): bool => $snippet->variations->sole()->name === 'MySQL 8 default'
                && $snippet->variations->sole()->is_default,
        ))->toBeTrue()
        ->and($snippets->every(
            fn (Snippet $snippet): bool => $snippet->tags->pluck('slug')->contains('mysql')
                && $snippet->tags->pluck('slug')->contains('sql'),
        ))->toBeTrue()
        ->and($sections)->toHaveCount(37)
        ->and($snippets->every(
            fn (Snippet $snippet): bool => count(
                $sectionParser->parse($snippet->variations->sole()->content),
            ) >= 4,
        ))->toBeTrue()
        ->and($otherUser->projects()->count())->toBe(0)
        ->and($otherUser->snippets()->count())->toBe(0)
        ->and($otherUser->tags()->count())->toBe(0);

    $setup = $snippetsByFilename->get('01-database-user-and-grants.sql');
    $inspection = $snippetsByFilename->get('02-inspect-server-and-schema.sql');
    $schema = $snippetsByFilename->get('01-commerce-schema-and-indexes.sql');
    $data = $snippetsByFilename->get('02-crud-upsert-and-transactions.sql');
    $joins = $snippetsByFilename->get('01-joins-and-aggregation.sql');
    $advancedQueries = $snippetsByFilename->get('02-ctes-windows-top-n-and-keyset.sql');
    $json = $snippetsByFilename->get('01-json-operations-and-indexable-fields.sql');
    $diagnostics = $snippetsByFilename->get('02-explain-analyze-and-index-diagnostics.sql');

    expect($setup)->toBeInstanceOf(Snippet::class)
        ->and($setup->tags->pluck('slug')->all())
        ->toContain('security', 'administration')
        ->and($setup->variations->sole()->content)
        ->toContain(
            'CREATE DATABASE IF NOT EXISTS',
            'CREATE USER IF NOT EXISTS',
            'GRANT SELECT, INSERT, UPDATE, DELETE',
            'CHANGE_ME_WITH_A_RANDOM_SECRET',
            'Never commit a real password',
            'DESTRUCTIVE ACCESS CHANGE',
        )
        ->and($inspection)->toBeInstanceOf(Snippet::class)
        ->and($inspection->variations->sole()->content)
        ->toContain('SHOW DATABASES', 'DESCRIBE', 'SHOW CREATE TABLE', 'information_schema.tables')
        ->and($schema)->toBeInstanceOf(Snippet::class)
        ->and($schema->variations->sole()->content)
        ->toContain(
            'CREATE TABLE IF NOT EXISTS customers',
            'CREATE TABLE IF NOT EXISTS products',
            'CREATE TABLE IF NOT EXISTS orders',
            'CREATE TABLE IF NOT EXISTS order_items',
            'FOREIGN KEY',
            'CREATE INDEX idx_orders_customer_created',
        )
        ->and($data)->toBeInstanceOf(Snippet::class)
        ->and($data->variations->sole()->content)
        ->toContain(
            'INSERT INTO customers',
            'UPDATE products',
            'DELETE FROM products',
            'ON DUPLICATE KEY UPDATE',
            'START TRANSACTION',
            'SAVEPOINT before_stock_change',
            'ROLLBACK',
            'COMMIT',
            'DESTRUCTIVE: preview the exact rows',
        )
        ->and($joins)->toBeInstanceOf(Snippet::class)
        ->and($joins->variations->sole()->content)
        ->toContain('INNER JOIN customers', 'LEFT JOIN orders', 'GROUP BY', 'HAVING total_cents')
        ->and($advancedQueries)->toBeInstanceOf(Snippet::class)
        ->and($advancedQueries->variations->sole()->content)
        ->toContain(
            'WITH order_totals AS',
            'OVER (',
            'ROW_NUMBER()',
            'sales_rank <= 3',
            'WHERE (created_at, id) <',
            'do not use OFFSET at scale',
        )
        ->and($json)->toBeInstanceOf(Snippet::class)
        ->and($json->variations->sole()->content)
        ->toContain(
            'JSON_OBJECT',
            'JSON_SET',
            'JSON_TABLE',
            'GENERATED ALWAYS AS',
            'SCHEMA CHANGE',
        )
        ->and($diagnostics)->toBeInstanceOf(Snippet::class)
        ->and($diagnostics->variations->sole()->content)
        ->toContain(
            'EXPLAIN FORMAT=TREE',
            'EXPLAIN ANALYZE',
            'information_schema.statistics',
            'sys.schema_unused_indexes',
            'performance_schema.events_statements_summary_by_digest',
            'never DROP an index from a single snapshot',
        );
});

test('reseeding restores canonical MySQL content without duplicates or deleting a custom variation', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);

    $this->seed(MySqlBundleSeeder::class);

    $bundle = Project::query()->whereBelongsTo($user)->where('name', 'MySQL')->sole();
    $bundle->update(['position' => 44]);
    $snippet = $bundle->snippets()
        ->where('filename', '02-crud-upsert-and-transactions.sql')
        ->sole();
    $defaultVariation = $snippet->variations()->where('name', 'MySQL 8 default')->sole();
    $defaultVariation->update(['content' => 'stale seeded content']);
    $snippet->update(['title' => 'User changed seeded title']);
    $customVariation = $snippet->variations()->create([
        'created_by_id' => $user->id,
        'name' => 'Custom transaction recipe',
        'content' => 'START TRANSACTION; SELECT 1; COMMIT;',
        'position' => 9,
        'is_default' => true,
    ]);

    $this->seed(MySqlBundleSeeder::class);

    $refreshedBundle = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'MySQL')
        ->sole();
    $refreshedSnippet = $refreshedBundle->snippets()
        ->where('filename', '02-crud-upsert-and-transactions.sql')
        ->with('variations')
        ->sole();
    $refreshedDefault = $refreshedSnippet->variations
        ->where('name', 'MySQL 8 default')
        ->sole();
    $refreshedCustomVariation = $refreshedSnippet->variations
        ->where('name', $customVariation->name)
        ->sole();
    $sectionParser = new SnippetSectionParser;
    $sectionCount = $refreshedBundle->snippets()
        ->with('variations')
        ->get()
        ->sum(fn (Snippet $seededSnippet): int => count(
            $sectionParser->parse(
                $seededSnippet->variations->where('name', 'MySQL 8 default')->sole()->content,
            ),
        ));

    expect($user->projects()->where('name', 'MySQL')->count())->toBe(1)
        ->and($refreshedBundle->kind)->toBe(Project::KIND_BUNDLE)
        ->and($refreshedBundle->position)->toBe(44)
        ->and($refreshedBundle->folders()->count())->toBe(4)
        ->and($refreshedBundle->snippets()->count())->toBe(8)
        ->and($sectionCount)->toBe(37)
        ->and($refreshedSnippet->title)->toBe('CRUD, Upserts, Transactions and Savepoints')
        ->and($refreshedSnippet->variations)->toHaveCount(2)
        ->and($refreshedDefault->content)
        ->toContain('ON DUPLICATE KEY UPDATE', 'SAVEPOINT before_stock_change')
        ->and($refreshedDefault->is_default)->toBeTrue()
        ->and($refreshedCustomVariation)->toBeInstanceOf(SnippetVariation::class)
        ->and($refreshedCustomVariation->content)->toBe('START TRANSACTION; SELECT 1; COMMIT;')
        ->and($refreshedCustomVariation->is_default)->toBeFalse();
});

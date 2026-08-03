<?php

use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\User;
use App\Models\VariablePreset;
use App\Support\Snippets\SnippetSectionParser;
use Database\Seeders\SnippetExamplesSeeder;

test('it seeds loop recipes and a cross-file Timber project with named variations', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);
    $otherUser = User::factory()->create(['email' => 'test@example.com']);

    $this->seed(SnippetExamplesSeeder::class);

    $bundle = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'Loop Recipes')
        ->sole();
    $project = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'Timber Blog Theme')
        ->sole();
    $timberLoop = $bundle->snippets()
        ->where('filename', 'timber-foreach.twig')
        ->sole();
    $phpLoop = $bundle->snippets()
        ->where('filename', 'php-foreach.php')
        ->sole();
    $postCard = $bundle->snippets()
        ->where('filename', 'post-card.twig')
        ->sole();
    $teamMemberCard = $bundle->snippets()
        ->where('filename', 'team-member-card.twig')
        ->sole();
    $archiveController = $project->snippets()
        ->where('filename', 'archive.php')
        ->sole();
    $archivePage = $project->snippets()
        ->where('filename', 'archive.twig')
        ->sole();
    $baseLayout = $project->snippets()
        ->where('filename', 'base.twig')
        ->sole();
    $newsPage = $project->snippets()
        ->where('filename', 'news.twig')
        ->sole();
    $twigFolder = $bundle->folders()->where('name', 'Timber Twig')->sole();
    $phpFolder = $bundle->folders()->where('name', 'PHP')->sole();
    $javascriptFolder = $bundle->folders()->where('name', 'JavaScript')->sole();
    $componentsFolder = $bundle->folders()->where('name', 'components')->sole();
    $viewsFolder = $project->folders()->where('name', 'views')->sole();
    $pagesFolder = $project->folders()->where('name', 'pages')->sole();
    $timberDefault = $timberLoop->variations()->where('is_default', true)->sole();
    $phpDefault = $phpLoop->variations()->where('is_default', true)->sole();
    $archiveControllerDefault = $archiveController->variations()->where('is_default', true)->sole();
    $archivePageDefault = $archivePage->variations()->where('is_default', true)->sole();
    $newsPageDefault = $newsPage->variations()->where('is_default', true)->sole();

    expect($bundle->kind)->toBe('bundle')
        ->and($bundle->description)
        ->toBe('Copy-ready PHP, Twig, and JavaScript loop patterns in separate files.')
        ->and($bundle->folders()->whereNull('parent_id')->orderBy('position')->pluck('name')->all())
        ->toBe(['Timber Twig', 'PHP', 'JavaScript'])
        ->and($componentsFolder->parent_id)->toBe($twigFolder->id)
        ->and($timberLoop->title)->toBe('Timber Twig Foreach Loop')
        ->and($timberLoop->variations()->orderBy('position')->pluck('name')->all())
        ->toBe([
            'Basic Timber posts loop',
            'Add loop metadata and empty state',
            'Add configurable collection and partial',
        ])
        ->and($timberDefault->name)->toBe('Add configurable collection and partial')
        ->and($timberDefault->content)->toContain('{{{collection:posts}}}')
        ->and($timberLoop->variablePresets()->orderBy('name')->pluck('name')->all())
        ->toBe(['Team members', 'WordPress posts'])
        ->and($timberLoop->tags()->orderBy('slug')->pluck('slug')->all())
        ->toBe([
            'loop',
            'reusable',
            'template-variables',
            'timber',
            'twig',
            'wordpress',
        ])
        ->and($phpLoop->title)->toBe('PHP Foreach Loop')
        ->and($phpLoop->variations()->orderBy('position')->pluck('name')->all())
        ->toBe([
            'Simple value loop',
            'Key and value loop',
            'Escaped HTML list output',
        ])
        ->and($phpDefault->name)->toBe('Escaped HTML list output')
        ->and($phpLoop->variablePresets()->orderBy('name')->pluck('name')->all())
        ->toBe(['Associative settings', 'Indexed items', 'WordPress fields'])
        ->and($phpFolder->snippets()->orderBy('position')->pluck('filename')->all())
        ->toBe([
            'php-foreach.php',
            'php-for.php',
            'php-while.php',
            'php-do-while.php',
        ])
        ->and($javascriptFolder->snippets()->orderBy('position')->pluck('filename')->all())
        ->toBe([
            'javascript-for.js',
            'javascript-for-of.js',
            'javascript-for-in.js',
            'javascript-for-await-of.js',
            'javascript-while.js',
            'javascript-do-while.js',
            'javascript-foreach.js',
            'javascript-map.js',
            'javascript-filter.js',
            'javascript-reduce.js',
            'javascript-reduce-right.js',
            'javascript-find.js',
            'javascript-find-index.js',
            'javascript-find-last.js',
            'javascript-find-last-index.js',
            'javascript-some.js',
            'javascript-every.js',
            'javascript-flat-map.js',
        ])
        ->and(
            $javascriptFolder->snippets()
                ->where('filename', 'javascript-map.js')
                ->sole()
                ->variations()
                ->where('is_default', true)
                ->sole()
                ->content,
        )->toContain('.map(')
        ->and(
            $javascriptFolder->snippets()
                ->where('filename', 'javascript-for-await-of.js')
                ->sole()
                ->variations()
                ->where('is_default', true)
                ->sole()
                ->content,
        )->toContain('for await (')
        ->and($postCard->folder_id)->toBe($componentsFolder->id)
        ->and($postCard->variations()->count())->toBe(2)
        ->and($teamMemberCard->folder_id)->toBe($componentsFolder->id)
        ->and($teamMemberCard->variations()->count())->toBe(2)
        ->and($project->kind)->toBe('project')
        ->and($pagesFolder->parent_id)->toBe($viewsFolder->id)
        ->and($baseLayout->folder_id)->toBe($viewsFolder->id)
        ->and($archiveController->folder_id)->toBeNull()
        ->and($archiveControllerDefault->name)->toBe('Add a configurable post query')
        ->and($archiveController->variablePresets()->count())->toBe(2)
        ->and($archiveController->variablePresets()->where('name', 'News archive')->sole()->values['template'])
        ->toBe('pages/news.twig')
        ->and($archivePage->folder_id)->toBe($pagesFolder->id)
        ->and($archivePageDefault->content)->toContain("{% extends 'base.twig' %}")
        ->and($archivePage->variations()->count())->toBe(2)
        ->and($newsPage->folder_id)->toBe($pagesFolder->id)
        ->and($newsPageDefault->name)->toBe('Add date, excerpt, and variables')
        ->and($newsPage->variablePresets()->pluck('name')->all())->toBe(['News listing'])
        ->and($otherUser->projects()->count())->toBe(0)
        ->and($otherUser->tags()->count())->toBe(0);

    $user->snippets()
        ->with(['tags', 'variablePresets', 'variations'])
        ->get()
        ->each(function (Snippet $snippet) use ($user): void {
            $defaultVariations = $snippet->variations->where('is_default', true);
            $defaultVariation = $defaultVariations->sole();

            expect($defaultVariations)->toHaveCount(1)
                ->and($snippet->variations)->not->toBeEmpty()
                ->and($snippet->variations->pluck('created_by_id')->unique()->all())->toBe([$user->id])
                ->and($snippet->tags->pluck('user_id')->unique()->all())->toBe([$user->id]);

            preg_match_all('/\{\{\{([A-Za-z_][A-Za-z0-9_]*):/', $defaultVariation->content, $matches);
            $variableNames = collect($matches[1])->unique()->sort()->values()->all();

            $snippet->variablePresets->each(function (VariablePreset $preset) use ($variableNames): void {
                $presetNames = collect(array_keys($preset->values))->sort()->values()->all();

                expect($presetNames)->toBe($variableNames);
            });
        });
});

test('it seeds a WordPress block theme blueprint with searchable embedded snippets', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);

    $this->seed(SnippetExamplesSeeder::class);

    $project = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'WordPress Block Theme Blueprint')
        ->sole();
    $themeFolder = $project->folders()
        ->whereNull('parent_id')
        ->where('name', 'block-theme')
        ->sole();
    $blocksFolder = $project->folders()
        ->where('parent_id', $themeFolder->id)
        ->where('name', 'blocks')
        ->sole();
    $featureCardFolder = $project->folders()
        ->where('parent_id', $blocksFolder->id)
        ->where('name', 'feature-card')
        ->sole();
    $assetsFolder = $project->folders()
        ->where('parent_id', $themeFolder->id)
        ->where('name', 'assets')
        ->sole();
    $stylesFolder = $project->folders()
        ->where('parent_id', $assetsFolder->id)
        ->where('name', 'styles')
        ->sole();
    $pluginFolder = $project->folders()
        ->whereNull('parent_id')
        ->where('name', 'companion-plugin')
        ->sole();
    $pluginIncludesFolder = $project->folders()
        ->where('parent_id', $pluginFolder->id)
        ->where('name', 'includes')
        ->sole();
    $integrationsFolder = $project->folders()
        ->where('parent_id', $pluginFolder->id)
        ->where('name', 'integrations')
        ->sole();
    $directoryIntegrationFolder = $project->folders()
        ->where('parent_id', $integrationsFolder->id)
        ->where('name', 'custom-directory')
        ->sole();
    $facetWpIntegrationFolder = $project->folders()
        ->where('parent_id', $integrationsFolder->id)
        ->where('name', 'facetwp')
        ->sole();
    $setup = $project->snippets()->where('filename', 'setup.php')->sole();
    $blockMetadata = $featureCardFolder->snippets()->where('filename', 'block.json')->sole();
    $blockEditor = $featureCardFolder->snippets()->where('filename', 'index.js')->sole();
    $themeJson = $project->snippets()->where('filename', 'theme.json')->sole();
    $classReference = $project->snippets()
        ->where('filename', '_wordpress-classes.scss')
        ->sole();
    $meilisearch = $project->snippets()
        ->where('filename', 'meilisearch.php')
        ->sole();
    $compose = $project->snippets()->where('filename', 'compose.yaml')->sole();
    $figmaMcp = $project->snippets()->where('filename', 'mcp.json')->sole();
    $figmaTokens = $project->snippets()->where('filename', 'figma-tokens.css')->sole();
    $composer = $project->snippets()->where('filename', 'composer.json')->sole();
    $pluginBootstrap = $project->snippets()
        ->where('filename', 'wordpress-block-theme-tools.php')
        ->sole();

    $setupContent = $setup->variations()->where('is_default', true)->sole()->content;
    $classReferenceContent = $classReference->variations()
        ->where('is_default', true)
        ->sole()
        ->content;
    $meilisearchContent = $meilisearch->variations()
        ->where('is_default', true)
        ->sole()
        ->content;
    $composeContent = $compose->variations()->where('is_default', true)->sole()->content;
    $figmaTokensContent = $figmaTokens->variations()
        ->where('is_default', true)
        ->sole()
        ->content;
    $themeJsonContent = $themeJson->variations()
        ->where('is_default', true)
        ->sole()
        ->content;
    $blockMetadataContent = $blockMetadata->variations()
        ->where('is_default', true)
        ->sole()
        ->content;
    $blockEditorContent = $blockEditor->variations()->where('is_default', true)->sole()->content;
    $figmaMcpContent = $figmaMcp->variations()
        ->where('is_default', true)
        ->sole()
        ->content;
    $composerContent = $composer->variations()->where('is_default', true)->sole()->content;
    $sectionParser = app(SnippetSectionParser::class);
    $setupSections = $sectionParser->parse($setupContent);
    $classReferenceSections = $sectionParser->parse($classReferenceContent);
    $meilisearchSections = $sectionParser->parse($meilisearchContent);
    $composeSections = $sectionParser->parse($composeContent);
    $figmaTokenSections = $sectionParser->parse($figmaTokensContent);

    expect($project->kind)->toBe('project')
        ->and($project->description)
        ->toContain('Gutenberg block theme')
        ->and(
            $project->folders()
                ->whereNull('parent_id')
                ->orderBy('position')
                ->pluck('name')
                ->all(),
        )->toBe([
            'block-theme',
            'companion-plugin',
            'docker',
            '.vscode',
            'tooling',
        ])
        ->and($project->folders()->count())->toBe(18)
        ->and($project->snippets()->count())->toBe(59)
        ->and($directoryIntegrationFolder->parent_id)->toBe($integrationsFolder->id)
        ->and($facetWpIntegrationFolder->parent_id)->toBe($integrationsFolder->id)
        ->and($directoryIntegrationFolder->snippets()->count())->toBe(12)
        ->and($facetWpIntegrationFolder->snippets()->count())->toBe(8)
        ->and($blockMetadata->folder_id)->toBe($featureCardFolder->id)
        ->and($meilisearch->folder_id)->toBe($pluginIncludesFolder->id)
        ->and(json_decode($themeJsonContent, true, flags: JSON_THROW_ON_ERROR)['version'])
        ->toBe(3)
        ->and(json_decode($blockMetadataContent, true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray([
            'apiVersion' => 3,
            'name' => 'blueprint/feature-card',
            'render' => 'file:./render.php',
        ])
        ->and($setupSections)->toHaveCount(3)
        ->and(array_column($setupSections, 'name'))->toBe([
            'register_theme_supports',
            'enqueue_theme_assets',
            'register_editor_style',
        ])
        ->and($classReferenceSections)->toHaveCount(9)
        ->and($meilisearchSections)->toHaveCount(4)
        ->and($composeSections)->toHaveCount(3)
        ->and($figmaTokenSections)->toHaveCount(3)
        ->and($setupContent)->not->toMatch('/^\{!#/m')
        ->and($composeContent)->not->toMatch('/^\{!#/m')
        ->and($blockEditorContent)->toContain('useBlockProps')
        ->and($blockEditorContent)->toContain("el(\n        'article',\n        blockProps,")
        ->and($composeContent)->toContain('condition: service_completed_successfully')
        ->and($composerContent)->toContain('--standard=../tooling/phpcs.xml.dist')
        ->and($meilisearchContent)->toContain("register_rest_route('blueprint/v1', '/search'")
        ->and($meilisearch->tags()->orderBy('slug')->pluck('slug')->all())
        ->toBe([
            'block-theme',
            'gutenberg',
            'meilisearch',
            'rest-api',
            'wordpress',
            'wordpress-standards',
        ])
        ->and($meilisearch->frameworks()->pluck('slug')->all())->toBe(['wordpress'])
        ->and($classReference->description)->toContain('class families')
        ->and($figmaTokens->folder_id)->toBe($stylesFolder->id)
        ->and($setupContent)->toContain('assets/styles/figma-tokens.css')
        ->and($figmaMcpContent)->toContain('https://mcp.figma.com/mcp')
        ->and($figmaMcp->tags()->orderBy('slug')->pluck('slug')->all())
        ->toBe([
            'block-theme',
            'design-tokens',
            'figma-mcp',
            'gutenberg',
            'wordpress',
            'wordpress-standards',
        ])
        ->and($pluginBootstrap->variations()->orderBy('position')->pluck('name')->all())
        ->toBe([
            'Plugin bootstrap',
            'Load directory and FacetWP integrations',
        ])
        ->and($pluginBootstrap->variations()->where('is_default', true)->sole()->name)
        ->toBe('Load directory and FacetWP integrations');

    $project->snippets()
        ->with(['frameworks', 'variations'])
        ->get()
        ->each(function (Snippet $snippet) use ($pluginBootstrap): void {
            expect($snippet->frameworks->pluck('slug')->all())->toBe(['wordpress'])
                ->and($snippet->variations)->toHaveCount(
                    $snippet->is($pluginBootstrap) ? 2 : 1,
                )
                ->and($snippet->variations->where('is_default', true))->toHaveCount(1);
        });
});

test('it seeds a versioned Gutenberg frontend class atlas for every stable Core block', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);

    $this->seed(SnippetExamplesSeeder::class);

    $project = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'WordPress Block Theme Blueprint')
        ->sole();
    $stylesFolder = $project->folders()
        ->where('name', 'styles')
        ->whereHas('parent', fn ($query) => $query->where('name', 'assets'))
        ->sole();
    $atlasFolder = $project->folders()
        ->where('parent_id', $stylesFolder->id)
        ->where('name', 'gutenberg-class-atlas')
        ->sole();
    $atlasFiles = $atlasFolder->snippets()
        ->with(['frameworks', 'tags', 'variations'])
        ->orderBy('position')
        ->get();
    $defaultContent = fn (Snippet $snippet): string => $snippet->variations
        ->where('is_default', true)
        ->sole()
        ->content;
    $manifestSnippet = $atlasFiles->firstWhere('filename', 'core-block-class-manifest.json');
    $manifest = json_decode(
        $defaultContent($manifestSnippet),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $expectedCoreBlocks = preg_split('/\s+/', trim(<<<'BLOCKS'
core/accordion core/accordion-heading core/accordion-item core/accordion-panel
core/archives core/audio core/avatar core/block core/breadcrumbs core/button core/buttons
core/calendar core/categories core/code core/column core/columns
core/comment-author-name core/comment-content core/comment-date core/comment-edit-link core/comment-reply-link core/comment-template
core/comments core/comments-pagination core/comments-pagination-next core/comments-pagination-numbers core/comments-pagination-previous core/comments-title
core/cover core/details core/embed core/file core/footnotes core/freeform core/gallery core/group core/heading core/home-link core/html core/icon core/image
core/latest-comments core/latest-posts core/legacy-widget core/list core/list-item core/loginout core/math core/media-text core/missing core/more
core/navigation core/navigation-link core/navigation-overlay-close core/navigation-submenu core/nextpage
core/page-list core/page-list-item core/paragraph core/pattern
core/post-author core/post-author-biography core/post-author-name core/post-comments-count core/post-comments-form core/post-comments-link
core/post-content core/post-date core/post-excerpt core/post-featured-image core/post-navigation-link core/post-template core/post-terms core/post-time-to-read core/post-title
core/preformatted core/pullquote core/query core/query-no-results core/query-pagination core/query-pagination-next core/query-pagination-numbers core/query-pagination-previous core/query-title core/query-total
core/quote core/read-more core/rss core/search core/separator core/shortcode
core/site-logo core/site-tagline core/site-title core/social-link core/social-links core/spacer core/table core/tag-cloud core/template-part
core/term-count core/term-description core/term-name core/term-template core/terms-query core/text-columns core/verse core/video core/widget-group
BLOCKS));
    $actualCoreBlocks = array_column($manifest['blocks'], 'name');
    sort($expectedCoreBlocks);
    sort($actualCoreBlocks);

    expect($atlasFolder->parent_id)->toBe($stylesFolder->id)
        ->and($atlasFiles->pluck('filename')->all())->toBe([
            'README.md',
            'core-block-class-manifest.json',
            '_shared.scss',
            '_text.scss',
            '_media.scss',
            '_design.scss',
            '_widgets.scss',
            '_navigation.scss',
            '_comments.scss',
            '_post.scss',
            '_query-and-terms.scss',
            '_site.scss',
            '_embeds.scss',
            '_gutenberg-preview.scss',
        ])
        ->and($manifest['schemaVersion'])->toBe(1)
        ->and($manifest['wordpressVersion'])->toBe('7.0.2')
        ->and($manifest['registeredBlockCount'])->toBe(109)
        ->and($manifest['blocks'])->toHaveCount(109)
        ->and($actualCoreBlocks)->toBe($expectedCoreBlocks)
        ->and(collect($actualCoreBlocks)->unique())->toHaveCount(109);

    $manifestByName = collect($manifest['blocks'])->keyBy('name');

    expect($manifestByName['core/block']['status'])->toBe('referenced-inner-blocks')
        ->and($manifestByName['core/pattern']['status'])->toBe('referenced-inner-blocks')
        ->and($manifestByName['core/freeform']['status'])->toBe('delegated-raw-output')
        ->and($manifestByName['core/html']['status'])->toBe('delegated-raw-output')
        ->and($manifestByName['core/legacy-widget']['status'])->toBe('delegated-widget-output')
        ->and($manifestByName['core/list-item']['status'])->toBe('shared-parent-wrapper')
        ->and($manifestByName['core/missing']['status'])->toBe('preserved-unknown-output')
        ->and($manifestByName['core/more']['status'])->toBe('serialization-marker')
        ->and($manifestByName['core/nextpage']['status'])->toBe('serialization-marker')
        ->and($manifestByName['core/page-list-item']['status'])->toBe('internal-child')
        ->and($manifestByName['core/shortcode']['status'])->toBe('delegated-plugin-output')
        ->and($manifestByName['core/widget-group']['status'])->toBe('shared-wrapper')
        ->and($manifestByName['core/paragraph']['status'])->toBe('manual-core-wrapper')
        ->and($manifestByName['core/paragraph']['selectors'])->toContain('.wp-block-paragraph');

    $parser = app(SnippetSectionParser::class);
    $stableReferenceFiles = collect($manifest['blocks'])
        ->pluck('referenceFile')
        ->unique()
        ->values();
    $actualSectionNames = $stableReferenceFiles
        ->flatMap(function (string $filename) use ($atlasFiles, $defaultContent, $parser): array {
            $snippet = $atlasFiles->firstWhere('filename', $filename);

            return array_column($parser->parse($defaultContent($snippet)), 'name');
        })
        ->sort()
        ->values();
    $expectedSectionNames = collect($manifest['blocks'])
        ->pluck('name')
        ->map(fn (string $name): string => str_replace(
            ['core/', '-'],
            ['core_', '_'],
            $name,
        ).'_classes')
        ->sort()
        ->values();

    expect($actualSectionNames)->toHaveCount(109)
        ->and($actualSectionNames->unique())->toHaveCount(109)
        ->and($actualSectionNames->all())->toBe($expectedSectionNames->all());

    $atlasFiles->each(function (Snippet $snippet): void {
        expect($snippet->frameworks->pluck('slug')->all())->toBe(['wordpress'])
            ->and($snippet->tags->pluck('slug')->sort()->values()->all())->toBe([
                'accessibility',
                'block-theme',
                'gutenberg',
                'wordpress',
                'wordpress-classes',
                'wordpress-standards',
            ])
            ->and($snippet->variations)->toHaveCount(1)
            ->and($snippet->variations->where('is_default', true))->toHaveCount(1);
    });

    $contentFor = fn (string $filename): string => $defaultContent(
        $atlasFiles->firstWhere('filename', $filename),
    );

    expect($contentFor('README.md'))->toContain('109 Core block types registered by WordPress 7.0.2')
        ->and($contentFor('_shared.scss'))->toContain('.wp-block-hidden-mobile')
        ->and($contentFor('_shared.scss'))->toContain('.wp-container-{block}-is-layout-{hash}')
        ->and($contentFor('_media.scss'))->toContain('.wp-lightbox-navigation-button-next')
        ->and($contentFor('_widgets.scss'))->toContain('.wp-social-link-bluesky')
        ->and($contentFor('_navigation.scss'))->toContain('.wp-block-navigation__responsive-container')
        ->and($contentFor('_comments.scss'))->toContain('.comment-awaiting-moderation')
        ->and($contentFor('_post.scss'))->toContain('get_post_class() families')
        ->and($contentFor('_query-and-terms.scss'))->toContain('.wp-block-term-template')
        ->and($contentFor('_embeds.scss'))->toContain('.wp-embed-aspect-16-9')
        ->and($contentFor('_gutenberg-preview.scss'))->toContain('.wp-block-form-input__input')
        ->and($contentFor('_gutenberg-preview.scss'))->toContain('.wp-block-playlist-track__length')
        ->and($manifestByName)->not->toHaveKey('core/form');
});

test('it seeds a secure custom-table AJAX integration without collecting email PII', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);

    $this->seed(SnippetExamplesSeeder::class);

    $project = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'WordPress Block Theme Blueprint')
        ->sole();
    $pluginFolder = $project->folders()
        ->whereNull('parent_id')
        ->where('name', 'companion-plugin')
        ->sole();
    $integrationsFolder = $project->folders()
        ->where('parent_id', $pluginFolder->id)
        ->where('name', 'integrations')
        ->sole();
    $directoryFolder = $project->folders()
        ->where('parent_id', $integrationsFolder->id)
        ->where('name', 'custom-directory')
        ->sole();
    $patternsFolder = $project->folders()
        ->where('name', 'patterns')
        ->whereHas('parent', fn ($query) => $query->where('name', 'block-theme'))
        ->sole();
    $defaultContent = fn (Snippet $snippet): string => $snippet->variations()
        ->where('is_default', true)
        ->sole()
        ->content;
    $sectionNames = fn (Snippet $snippet): array => array_column(
        app(SnippetSectionParser::class)->parse($defaultContent($snippet)),
        'name',
    );
    $readme = $directoryFolder->snippets()->where('filename', 'README.md')->sole();
    $schema = $directoryFolder->snippets()
        ->where('filename', 'class-directory-schema.php')
        ->sole();
    $repository = $directoryFolder->snippets()
        ->where('filename', 'class-directory-repository.php')
        ->sole();
    $ajax = $directoryFolder->snippets()
        ->where('filename', 'class-directory-ajax.php')
        ->sole();
    $assets = $directoryFolder->snippets()
        ->where('filename', 'class-directory-assets.php')
        ->sole();
    $blockMetadata = $directoryFolder->snippets()->where('filename', 'block.json')->sole();
    $blockEditor = $directoryFolder->snippets()->where('filename', 'index.js')->sole();
    $renderer = $directoryFolder->snippets()->where('filename', 'render.php')->sole();
    $formScript = $directoryFolder->snippets()
        ->where('filename', 'directory-form.js')
        ->sole();
    $pattern = $patternsFolder->snippets()
        ->where('filename', 'directory-submission.php')
        ->sole();
    $readmeContent = $defaultContent($readme);
    $schemaContent = $defaultContent($schema);
    $repositoryContent = $defaultContent($repository);
    $ajaxContent = $defaultContent($ajax);
    $assetsContent = $defaultContent($assets);
    $blockMetadataContent = $defaultContent($blockMetadata);
    $blockEditorContent = $defaultContent($blockEditor);
    $rendererContent = $defaultContent($renderer);
    $formScriptContent = $defaultContent($formScript);
    $blockDefinition = json_decode($blockMetadataContent, true, flags: JSON_THROW_ON_ERROR);
    $directorySnippets = $directoryFolder->snippets()
        ->with(['frameworks', 'tags', 'variations'])
        ->orderBy('position')
        ->get()
        ->push($pattern->loadMissing(['frameworks', 'tags', 'variations']));
    $allDirectoryContent = $directorySnippets
        ->map(fn (Snippet $snippet): string => $defaultContent($snippet))
        ->implode("\n");

    expect($directoryFolder->snippets()->orderBy('position')->pluck('filename')->all())
        ->toBe([
            'README.md',
            'bootstrap.php',
            'class-directory-schema.php',
            'class-directory-repository.php',
            'class-directory-ajax.php',
            'class-directory-assets.php',
            'block.json',
            'index.asset.php',
            'index.js',
            'render.php',
            'directory-form.js',
            'directory.css',
        ])
        ->and($pattern->folder_id)->toBe($patternsFolder->id)
        ->and($sectionNames($readme))->toBe([
            'custom_table_decision',
            'integration_request_flow',
            'public_submission_variant',
            'schema_lifecycle',
        ])
        ->and($sectionNames($schema))->toBe([
            'prefixed_custom_table_name',
            'register_schema_install_and_upgrade_hooks',
            'install_versioned_table_with_dbdelta',
            'upgrade_table_when_schema_version_changes',
        ])
        ->and($sectionNames($ajax))->toBe([
            'register_authenticated_ajax_action',
            'verify_authorise_and_validate_ajax_request',
            'persist_and_return_public_ajax_data',
        ])
        ->and($sectionNames($renderer))->toBe([
            'query_and_prepare_directory_block',
            'render_public_directory_table',
            'render_editor_submission_form',
        ])
        ->and($sectionNames($formScript))->toBe([
            'build_safe_directory_table_row',
            'submit_directory_entry_with_fetch',
            'bind_directory_forms',
        ])
        ->and($blockDefinition)->toMatchArray([
            'apiVersion' => 3,
            'name' => 'blueprint/directory-table',
            'editorScript' => 'file:./index.js',
            'viewScript' => 'blueprint-directory-form',
            'style' => 'file:./directory.css',
            'render' => 'file:./render.php',
        ])
        ->and($schemaContent)->toContain("\$wpdb->prefix . 'blueprint_directory_entries'")
        ->and($schemaContent)->toContain('dbDelta( $sql );')
        ->and($schemaContent)->toContain("add_action( 'plugins_loaded', array( self::class, 'maybe_upgrade' ) );")
        ->and($repositoryContent)->toContain('WHERE status = %s')
        ->and($repositoryContent)->toContain('$wpdb->prepare(')
        ->and($ajaxContent)->toContain("add_action( 'wp_ajax_' . self::ACTION")
        ->and($ajaxContent)->not->toContain('wp_ajax_nopriv_')
        ->and($ajaxContent)->toContain("check_ajax_referer( self::ACTION, 'nonce' );")
        ->and($ajaxContent)->toContain('current_user_can( self::CAPABILITY )')
        ->and($ajaxContent)->toContain('sanitize_text_field')
        ->and($ajaxContent)->toContain('sanitize_textarea_field')
        ->and($assetsContent)->toContain("wp_create_nonce( 'blueprint_directory_submit' )")
        ->and($assetsContent)->toContain('wp_register_script(')
        ->and($rendererContent)->toContain("current_user_can( 'edit_others_posts' )")
        ->and($rendererContent)->toContain("wp_nonce_field( 'blueprint_directory_submit', 'nonce' );")
        ->and($formScriptContent)->toContain('textContent = entry.name')
        ->and($formScriptContent)->toContain("credentials: 'same-origin'")
        ->and($formScriptContent)->not->toContain('innerHTML')
        ->and(substr_count($blockEditorContent, "blocks.registerBlockType('blueprint/directory-table'"))
        ->toBe(1)
        ->and($allDirectoryContent)->not->toMatch('/\b(?:e-?mail|email_address)\b/i');

    $directorySnippets->each(function (Snippet $snippet) use ($user): void {
        expect($snippet->user_id)->toBe($user->id)
            ->and($snippet->frameworks->pluck('slug')->all())->toBe(['wordpress'])
            ->and($snippet->tags->pluck('slug')->all())->toContain(
                'wordpress',
                'wordpress-standards',
                'plugin-development',
                'custom-database-table',
                'wpdb',
                'ajax',
                'data-integration',
            )
            ->and($snippet->tags->pluck('user_id')->unique()->all())->toBe([$user->id]);
    });
});

test('it seeds a searchable FacetWP and WooCommerce integration with one listing boundary', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);

    $this->seed(SnippetExamplesSeeder::class);

    $project = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'WordPress Block Theme Blueprint')
        ->sole();
    $pluginFolder = $project->folders()
        ->whereNull('parent_id')
        ->where('name', 'companion-plugin')
        ->sole();
    $integrationsFolder = $project->folders()
        ->where('parent_id', $pluginFolder->id)
        ->where('name', 'integrations')
        ->sole();
    $facetWpFolder = $project->folders()
        ->where('parent_id', $integrationsFolder->id)
        ->where('name', 'facetwp')
        ->sole();
    $defaultContent = fn (Snippet $snippet): string => $snippet->variations()
        ->where('is_default', true)
        ->sole()
        ->content;
    $sectionNames = fn (Snippet $snippet): array => array_column(
        app(SnippetSectionParser::class)->parse($defaultContent($snippet)),
        'name',
    );
    $readme = $facetWpFolder->snippets()->where('filename', 'README.md')->sole();
    $integration = $facetWpFolder->snippets()
        ->where('filename', 'class-facetwp-integration.php')
        ->sole();
    $archive = $facetWpFolder->snippets()->where('filename', 'archive-product.php')->sole();
    $pattern = $facetWpFolder->snippets()->where('filename', 'product-filters.php')->sole();
    $script = $facetWpFolder->snippets()->where('filename', 'facetwp-products.js')->sole();
    $readmeContent = $defaultContent($readme);
    $integrationContent = $defaultContent($integration);
    $archiveContent = $defaultContent($archive);
    $patternContent = $defaultContent($pattern);
    $scriptContent = $defaultContent($script);
    $facetWpSnippets = $facetWpFolder->snippets()
        ->with(['frameworks', 'tags', 'variations'])
        ->orderBy('position')
        ->get();

    expect($facetWpSnippets->pluck('filename')->all())->toBe([
        'README.md',
        'bootstrap.php',
        'class-facetwp-integration.php',
        'woocommerce-theme-support.php',
        'archive-product.php',
        'product-filters.php',
        'facetwp-products.js',
        'facetwp-products.css',
    ])
        ->and($sectionNames($readme))->toBe([
            'facetwp_architecture',
            'facetwp_installation_checklist',
            'facetwp_block_editor_options',
            'facetwp_operational_rules',
        ])
        ->and($sectionNames($integration))->toBe([
            'register_woocommerce_facets',
            'index_parent_product_material',
            'conditionally_enqueue_facetwp_assets',
        ])
        ->and($sectionNames($archive))->toBe([
            'facetwp_woocommerce_facets',
            'single_facetwp_woocommerce_listing',
        ])
        ->and($sectionNames($script))->toBe([
            'announce_facetwp_refresh',
            'restore_ui_after_facetwp_loaded',
        ])
        ->and($readmeContent)->toContain('Keep every facet outside the `.facetwp-template`')
        ->and($integrationContent)->toContain("'source' => 'tax/product_cat'")
        ->and($integrationContent)->toContain("'source' => 'woo/price'")
        ->and($integrationContent)->toContain("'source' => 'woo/stock_status'")
        ->and($integrationContent)->toContain("add_filter('facetwp_index_row'")
        ->and($integrationContent)->toContain('get_parent_id()')
        ->and($integrationContent)->toContain("function_exists('FWP')")
        ->and(strpos($archiveContent, "facetwp_display('facet', 'product_categories')"))
        ->toBeLessThan(strpos($archiveContent, 'facetwp-template'))
        ->and(substr_count($archiveContent, 'facetwp-template'))->toBe(1)
        ->and($archiveContent)->toContain('woocommerce_catalog_ordering();')
        ->and($archiveContent)->toContain('woocommerce_pagination();')
        ->and($patternContent)->toContain('[facetwp template="products"]')
        ->and($scriptContent)->toContain("document.addEventListener('facetwp-refresh'")
        ->and($scriptContent)->toContain("document.addEventListener('facetwp-loaded'")
        ->and($scriptContent)->toContain("setAttribute('aria-busy', 'true')")
        ->and($scriptContent)->toContain("removeAttribute('aria-busy')");

    $facetWpSnippets->each(function (Snippet $snippet) use ($user): void {
        expect($snippet->user_id)->toBe($user->id)
            ->and($snippet->frameworks->pluck('slug')->all())->toBe(['wordpress'])
            ->and($snippet->tags->pluck('slug')->all())->toContain(
                'wordpress',
                'wordpress-standards',
                'plugin-development',
                'facetwp',
                'woocommerce',
                'filtering',
            )
            ->and($snippet->tags->pluck('user_id')->unique()->all())->toBe([$user->id]);
    });
});

test('it seeds the CN visual code-stack hero as a complete tagged WordPress project', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);
    $otherUser = User::factory()->create(['email' => 'designer@dev.dev']);

    $this->seed(SnippetExamplesSeeder::class);

    $project = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'CN Visual Hero Code Stack')
        ->sole();
    $themeFolder = $project->folders()
        ->whereNull('parent_id')
        ->where('name', 'theme')
        ->sole();
    $patternsFolder = $project->folders()
        ->where('parent_id', $themeFolder->id)
        ->where('name', 'patterns')
        ->sole();
    $incFolder = $project->folders()
        ->where('parent_id', $themeFolder->id)
        ->where('name', 'inc')
        ->sole();
    $assetsFolder = $project->folders()
        ->where('parent_id', $themeFolder->id)
        ->where('name', 'assets')
        ->sole();
    $stylesFolder = $project->folders()
        ->where('parent_id', $assetsFolder->id)
        ->where('name', 'css')
        ->sole();
    $scriptsFolder = $project->folders()
        ->where('parent_id', $assetsFolder->id)
        ->where('name', 'js')
        ->sole();
    $imagesFolder = $project->folders()
        ->where('parent_id', $assetsFolder->id)
        ->where('name', 'img')
        ->sole();
    $testsFolder = $project->folders()
        ->whereNull('parent_id')
        ->where('name', 'tests')
        ->sole();
    $defaultContent = fn (Snippet $snippet): string => $snippet->variations()
        ->where('is_default', true)
        ->sole()
        ->content;
    $sectionNames = fn (Snippet $snippet): array => array_column(
        app(SnippetSectionParser::class)->parse($defaultContent($snippet)),
        'name',
    );
    $readme = $project->snippets()->whereNull('folder_id')->where('filename', 'README.md')->sole();
    $functions = $incFolder->snippets()->where('filename', 'visual-code-stack.php')->sole();
    $themeJson = $themeFolder->snippets()->where('filename', 'theme-json.fragment.json')->sole();
    $pattern = $patternsFolder->snippets()
        ->where('filename', 'hero-visual-code-stack.php')
        ->sole();
    $tokens = $stylesFolder->snippets()->where('filename', 'tokens.css')->sole();
    $heroStyles = $stylesFolder->snippets()
        ->where('filename', 'visual-code-stack-hero.css')
        ->sole();
    $heroScript = $scriptsFolder->snippets()
        ->where('filename', 'visual-code-stack-hero.js')
        ->sole();
    $cursorScript = $scriptsFolder->snippets()
        ->where('filename', 'cursor-utility.js')
        ->sole();
    $cursor = $imagesFolder->snippets()
        ->where('filename', 'presence-cursor-pointer.svg')
        ->sole();
    $assetNotes = $imagesFolder->snippets()->where('filename', 'README.md')->sole();
    $package = $project->snippets()->whereNull('folder_id')->where('filename', 'package.json')->sole();
    $playwrightConfig = $project->snippets()
        ->whereNull('folder_id')
        ->where('filename', 'playwright.config.js')
        ->sole();
    $browserTest = $testsFolder->snippets()
        ->where('filename', 'visual-code-stack-hero.spec.js')
        ->sole();
    $functionsContent = $defaultContent($functions);
    $patternContent = $defaultContent($pattern);
    $stylesContent = $defaultContent($heroStyles);
    $scriptContent = $defaultContent($heroScript);
    $allProjectContent = $project->snippets()
        ->with('variations')
        ->get()
        ->map(fn (Snippet $snippet): string => $snippet->variations
            ->where('is_default', true)
            ->sole()
            ->content)
        ->implode("\n");

    expect($project->user_id)->toBe($user->id)
        ->and($project->kind)->toBe('project')
        ->and($project->position)->toBe(5)
        ->and($project->description)->toContain('cn-visual-hero__code-stack')
        ->and($project->folders()->whereNull('parent_id')->orderBy('position')->pluck('name')->all())
        ->toBe(['theme', 'tests'])
        ->and($project->folders()->count())->toBe(8)
        ->and($project->snippets()->count())->toBe(14)
        ->and($patternsFolder->snippets()->pluck('filename')->all())
        ->toBe(['hero-visual-code-stack.php'])
        ->and($stylesFolder->snippets()->orderBy('position')->pluck('filename')->all())
        ->toBe(['tokens.css', 'visual-code-stack-hero.css', 'cursor.css'])
        ->and($scriptsFolder->snippets()->orderBy('position')->pluck('filename')->all())
        ->toBe(['visual-code-stack-hero.js', 'cursor-utility.js'])
        ->and($imagesFolder->snippets()->orderBy('position')->pluck('filename')->all())
        ->toBe(['presence-cursor-pointer.svg', 'README.md'])
        ->and($sectionNames($readme))->toBe([
            'source_and_scope',
            'project_file_map',
            'installation_and_usage',
            'accessibility_contract',
        ])
        ->and($sectionNames($functions))->toBe([
            'asset_version_from_file',
            'enqueue_visual_hero_for_block_templates',
            'enqueue_visual_hero_in_editor',
        ])
        ->and($sectionNames($pattern))->toBe([
            'define_safe_code_cards',
            'render_accessible_code_stack',
        ])
        ->and($sectionNames($tokens))->toBe([
            'visual_hero_design_tokens',
            'dark_mode_token_override',
        ])
        ->and($sectionNames($heroStyles))->toBe([
            'visual_hero_layout',
            'animated_code_stack_positions',
            'ide_tabs_and_code_lines',
            'responsive_visual_hero',
            'reduced_motion_fallback',
        ])
        ->and($sectionNames($heroScript))->toBe([
            'activate_accessible_code_tab',
            'add_arrow_key_tab_navigation',
            'rotate_code_cards_accessibly',
            'initialise_visual_code_stacks',
        ])
        ->and($sectionNames($browserTest))->toBe([
            'test_code_stack_controls',
            'test_keyboard_tabs',
            'test_reduced_motion',
            'test_no_javascript_fallback',
        ])
        ->and($sectionNames($playwrightConfig))->toBe([
            'configure_wordpress_preview_target',
            'named_chromium_project',
        ])
        ->and(json_decode($defaultContent($themeJson), true, flags: JSON_THROW_ON_ERROR)['version'])
        ->toBe(3)
        ->and(json_decode($defaultContent($package), true, flags: JSON_THROW_ON_ERROR)['private'])
        ->toBeTrue()
        ->and($patternContent)->toContain('class="cn-visual-hero__code-stack"')
        ->and(substr_count($patternContent, "'status' =>"))->toBe(8)
        ->and($patternContent)->toContain('data-cycle-ms="4000"')
        ->and($patternContent)->toContain('role="tablist"')
        ->and($patternContent)->toContain('data-code-stack-pause')
        ->and($patternContent)->toContain('esc_html($line)')
        ->and($patternContent)->toContain("wp_unique_id('visual-code-stack-')")
        ->and($patternContent)->toContain('cn-token-keyword')
        ->and($functionsContent)->not->toContain('get_queried_object')
        ->and($functionsContent)->toContain("['strategy' => 'defer', 'in_footer' => true]")
        ->and($stylesContent)->toContain('@media (prefers-reduced-motion: reduce)')
        ->and($stylesContent)->toContain('@media (max-width:')
        ->and($scriptContent)->toContain("['ArrowLeft', 'ArrowRight', 'Home', 'End']")
        ->and($scriptContent)->toContain('card.inert = ! isActive')
        ->and($scriptContent)->toContain('window.setInterval')
        ->and($scriptContent)->toContain('pointerPaused')
        ->and($scriptContent)->toContain('focusPaused')
        ->and($scriptContent)->toContain("matchMedia('(prefers-reduced-motion: reduce)')")
        ->and($sectionNames($cursorScript))->toBe([
            'restart_cursor_with_each_card',
            'observe_stack_position_changes',
        ])
        ->and($defaultContent($cursor))->toContain('Decorative blue pointer')
        ->and($sectionNames($assetNotes))->toBe([
            'image_asset_policy',
            'cursor_asset_policy',
        ])
        ->and($allProjectContent)->not->toContain('/Users/christopher')
        ->and($allProjectContent)->not->toContain('redis-cli FLUSHDB')
        ->and($allProjectContent)->not->toContain('sudo rm -rf')
        ->and($otherUser->projects()->count())->toBe(0)
        ->and($otherUser->snippets()->count())->toBe(0);

    $project->snippets()
        ->with(['frameworks', 'tags', 'variations'])
        ->get()
        ->each(function (Snippet $snippet) use ($user): void {
            expect($snippet->user_id)->toBe($user->id)
                ->and($snippet->frameworks->pluck('slug')->all())->toBe(['wordpress'])
                ->and($snippet->tags->pluck('slug')->all())->toContain(
                    'wordpress',
                    'gutenberg',
                    'block-theme',
                    'block-pattern',
                    'visual-hero',
                    'code-stack',
                )
                ->and($snippet->tags->pluck('user_id')->unique()->all())->toBe([$user->id])
                ->and($snippet->variations)->toHaveCount(1)
                ->and($snippet->variations->sole()->created_by_id)->toBe($user->id)
                ->and($snippet->variations->sole()->is_default)->toBeTrue();
        });
});

test('it seeds an enterprise GitHub project with matching merge and deployment contracts', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);
    $otherUser = User::factory()->create(['email' => 'engineering@example.com']);

    $this->seed(SnippetExamplesSeeder::class);

    $project = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'GitHub')
        ->sole();
    $githubFolder = $project->folders()
        ->whereNull('parent_id')
        ->where('name', '.github')
        ->sole();
    $issueTemplatesFolder = $project->folders()
        ->where('parent_id', $githubFolder->id)
        ->where('name', 'ISSUE_TEMPLATE')
        ->sole();
    $workflowsFolder = $project->folders()
        ->where('parent_id', $githubFolder->id)
        ->where('name', 'workflows')
        ->sole();
    $governanceFolder = $project->folders()
        ->whereNull('parent_id')
        ->where('name', 'governance')
        ->sole();
    $scriptsFolder = $project->folders()
        ->whereNull('parent_id')
        ->where('name', 'scripts')
        ->sole();
    $runbooksFolder = $project->folders()
        ->whereNull('parent_id')
        ->where('name', 'runbooks')
        ->sole();
    $defaultContent = fn (Snippet $snippet): string => $snippet->variations()
        ->where('is_default', true)
        ->sole()
        ->content;
    $sectionNames = fn (Snippet $snippet): array => array_column(
        app(SnippetSectionParser::class)->parse($defaultContent($snippet)),
        'name',
    );
    $readme = $project->snippets()->whereNull('folder_id')->where('filename', 'README.md')->sole();
    $codeowners = $githubFolder->snippets()->where('filename', 'CODEOWNERS')->sole();
    $ci = $workflowsFolder->snippets()->where('filename', 'ci.yml')->sole();
    $dependencyReview = $workflowsFolder->snippets()
        ->where('filename', 'dependency-review.yml')
        ->sole();
    $codeQl = $workflowsFolder->snippets()->where('filename', 'codeql.yml')->sole();
    $release = $workflowsFolder->snippets()->where('filename', 'release.yml')->sole();
    $reusableDeploy = $workflowsFolder->snippets()
        ->where('filename', 'reusable-deploy-release.yml')
        ->sole();
    $production = $workflowsFolder->snippets()
        ->where('filename', 'deploy-production.yml')
        ->sole();
    $rollback = $workflowsFolder->snippets()->where('filename', 'rollback.yml')->sole();
    $mainRuleset = $governanceFolder->snippets()
        ->where('filename', 'main-branch-ruleset.json')
        ->sole();
    $releaseRuleset = $governanceFolder->snippets()
        ->where('filename', 'release-tag-ruleset.json')
        ->sole();
    $rulesetGuide = $governanceFolder->snippets()->where('filename', 'rulesets.md')->sole();
    $ciScript = $scriptsFolder->snippets()->where('filename', 'ci.sh')->sole();
    $buildRelease = $scriptsFolder->snippets()->where('filename', 'build-release.sh')->sole();
    $ciContent = $defaultContent($ci);
    $dependencyReviewContent = $defaultContent($dependencyReview);
    $codeQlContent = $defaultContent($codeQl);
    $releaseContent = $defaultContent($release);
    $reusableDeployContent = $defaultContent($reusableDeploy);
    $productionContent = $defaultContent($production);
    $rollbackContent = $defaultContent($rollback);
    $ciScriptContent = $defaultContent($ciScript);
    $buildReleaseContent = $defaultContent($buildRelease);
    $mainRulesetDefinition = json_decode(
        $defaultContent($mainRuleset),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $releaseRulesetDefinition = json_decode(
        $defaultContent($releaseRuleset),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $mainRulesByType = collect($mainRulesetDefinition['rules'])->keyBy('type');
    $githubSnippets = $project->snippets()
        ->with(['frameworks', 'tags', 'variations'])
        ->get();

    expect($project->user_id)->toBe($user->id)
        ->and($project->kind)->toBe('project')
        ->and($project->position)->toBe(6)
        ->and($project->description)->toContain('merge queues')
        ->and($project->folders()->whereNull('parent_id')->orderBy('position')->pluck('name')->all())
        ->toBe(['.github', 'governance', 'scripts', 'runbooks'])
        ->and($project->folders()->count())->toBe(6)
        ->and($project->snippets()->count())->toBe(34)
        ->and($project->snippets()->whereNull('folder_id')->count())->toBe(5)
        ->and($githubFolder->snippets()->count())->toBe(4)
        ->and($issueTemplatesFolder->snippets()->count())->toBe(3)
        ->and($workflowsFolder->snippets()->count())->toBe(8)
        ->and($governanceFolder->snippets()->count())->toBe(6)
        ->and($scriptsFolder->snippets()->count())->toBe(4)
        ->and($runbooksFolder->snippets()->count())->toBe(4)
        ->and($sectionNames($readme))->toBe([
            'enterprise_change_flow',
            'required_repository_controls',
            'responsibility_map',
        ])
        ->and($sectionNames($codeowners))->toBe([
            'default_ownership',
            'protected_automation_ownership',
            'domain_ownership',
        ])
        ->and($sectionNames($ci))->toBe([
            'merge_queue_aware_ci_triggers',
            'least_privilege_ci_permissions',
            'cancellable_pull_request_ci',
            'quality_gate_and_artifact',
        ])
        ->and($sectionNames($rulesetGuide))->toBe([
            'import_ruleset',
            'confirm_required_check_context',
            'evaluate_before_enforcement',
            'break_glass_bypass',
        ])
        ->and($ciContent)->toContain("merge_group:\n    types: [checks_requested]")
        ->and($ciContent)->toContain('name: Quality gates')
        ->and(substr_count($ciContent, 'merge_group:'))->toBe(1)
        ->and($dependencyReviewContent)->toContain("merge_group:\n    types: [checks_requested]")
        ->and($dependencyReviewContent)->toContain('actions/checkout@d23441a48e516b6c34aea4fa41551a30e30af803')
        ->and($dependencyReviewContent)->toContain('actions/dependency-review-action@a1d282b36b6f3519aa1f3fc636f609c47dddb294')
        ->and($codeQlContent)->toContain("merge_group:\n    types: [checks_requested]")
        ->and($mainRulesetDefinition['enforcement'])->toBe('evaluate')
        ->and($mainRulesByType->has('merge_queue'))->toBeTrue()
        ->and($mainRulesByType['pull_request']['parameters'])->toMatchArray([
            'allowed_merge_methods' => ['squash'],
            'dismiss_stale_reviews_on_push' => true,
            'require_code_owner_review' => true,
            'require_last_push_approval' => true,
            'required_approving_review_count' => 2,
            'required_review_thread_resolution' => true,
        ])
        ->and($mainRulesByType['required_status_checks']['parameters']['required_status_checks'])
        ->toBe([
            ['context' => 'Quality gates'],
            ['context' => 'Dependency review'],
            ['context' => 'CodeQL (javascript-typescript)'],
        ])
        ->and($releaseRulesetDefinition)->toMatchArray([
            'target' => 'tag',
            'enforcement' => 'active',
        ])
        ->and($releaseContent)->toContain('actions/attest@f7c74d28b9d84cb8768d0b8ca14a4bac6ef463e6')
        ->and($releaseContent)->toContain('git merge-base --is-ancestor HEAD origin/main')
        ->and($releaseContent)->toContain('gh release create')
        ->and($releaseContent)->toContain('--draft')
        ->and($reusableDeployContent)->toContain('workflow_call:')
        ->and($reusableDeployContent)->toContain('environment:')
        ->and($reusableDeployContent)->toContain('name: ${{ inputs.target_environment }}')
        ->and($reusableDeployContent)->toContain('sha256sum --check')
        ->and($reusableDeployContent)->toContain('gh attestation verify')
        ->and($reusableDeployContent)->toContain('Release must be published and not a prerelease.')
        ->and($reusableDeployContent)->toContain('git merge-base --is-ancestor HEAD origin/main')
        ->and($productionContent)->toContain('types: [published]')
        ->and($productionContent)->toContain('uses: ./.github/workflows/reusable-deploy-release.yml')
        ->and($productionContent)->toContain('target_environment: production')
        ->and($productionContent)->not->toContain('secrets: inherit')
        ->and($rollbackContent)->toContain('workflow_dispatch:')
        ->and($rollbackContent)->toContain('incident:')
        ->and($rollbackContent)->toContain('uses: ./.github/workflows/reusable-deploy-release.yml')
        ->and($rollbackContent)->toContain('target_environment: production')
        ->and($rollbackContent)->not->toContain('secrets: inherit')
        ->and($ciScriptContent)->toContain('cp -R vendor/.')
        ->and($ciScriptContent)->not->toContain("tar --exclude='./dist'")
        ->and($buildReleaseContent)->toContain('cp -R vendor/.')
        ->and($sectionNames($production))->toBe(['deploy_published_release'])
        ->and($sectionNames($rollback))->toBe([
            'manual_rollback_contract',
            'redeploy_known_good_release',
        ])
        ->and($defaultContent($codeowners))->toContain('/.github/CODEOWNERS')
        ->and($otherUser->projects()->count())->toBe(0)
        ->and($otherUser->snippets()->count())->toBe(0)
        ->and($otherUser->tags()->count())->toBe(0)
        ->and($otherUser->frameworks()->count())->toBe(0);

    $githubSnippets->each(function (Snippet $snippet) use ($defaultContent, $user): void {
        expect($snippet->user_id)->toBe($user->id)
            ->and($snippet->frameworks)->toBeEmpty()
            ->and($snippet->tags)->not->toBeEmpty()
            ->and($snippet->tags->pluck('slug')->all())->toContain('github', 'enterprise')
            ->and($snippet->tags->pluck('user_id')->unique()->all())->toBe([$user->id])
            ->and($snippet->variations)->toHaveCount(1)
            ->and($snippet->variations->sole()->created_by_id)->toBe($user->id)
            ->and($snippet->variations->sole()->is_default)->toBeTrue();

        if ($snippet->language === 'json') {
            expect(json_decode($defaultContent($snippet), true, flags: JSON_THROW_ON_ERROR))
                ->toBeArray();
        }

        preg_match_all(
            '/^\s*uses:\s*(?!\.\/)([^@\s]+)@([^\s#]+)/m',
            $defaultContent($snippet),
            $actionUses,
            PREG_SET_ORDER,
        );

        foreach ($actionUses as $actionUse) {
            expect($actionUse[2])->toMatch('/^[a-f0-9]{40}$/');
        }
    });
});

test('rerunning the example seeder preserves user changes and creates no duplicates', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);

    $this->seed(SnippetExamplesSeeder::class);

    $bundle = Project::query()->whereBelongsTo($user)->where('name', 'Loop Recipes')->sole();
    $project = Project::query()->whereBelongsTo($user)->where('name', 'Timber Blog Theme')->sole();
    $bundle->update(['name' => 'My Loop Recipes']);
    $project->update(['name' => 'My Timber Project']);

    $timberLoop = Snippet::query()
        ->where('filename', 'timber-foreach.twig')
        ->sole();
    $timberLoop->update(['title' => 'My Timber Loop']);
    $defaultVariation = $timberLoop->variations()->where('is_default', true)->sole();
    $defaultVariation->update(['content' => 'My edited loop']);
    $customVariation = $timberLoop->variations()->create([
        'created_by_id' => $user->id,
        'name' => 'My variation',
        'content' => 'My alternate loop',
        'position' => 4,
        'is_default' => false,
    ]);

    $this->seed(SnippetExamplesSeeder::class);

    $wordpressProject = $user->projects()
        ->where('name', 'WordPress Block Theme Blueprint')
        ->sole();
    $visualProject = $user->projects()
        ->where('name', 'CN Visual Hero Code Stack')
        ->sole();
    $githubProject = $user->projects()->where('name', 'GitHub')->sole();
    $pluginBootstrap = $wordpressProject->snippets()
        ->where('filename', 'wordpress-block-theme-tools.php')
        ->sole();

    expect($user->projects()->count())->toBe(5)
        ->and($bundle->refresh()->name)->toBe('My Loop Recipes')
        ->and($project->refresh()->name)->toBe('My Timber Project')
        ->and(
            $user->projects()
                ->withCount('folders')
                ->get()
                ->sum('folders_count'),
        )->toBe(38)
        ->and($user->snippets()->count())->toBe(137)
        ->and($wordpressProject->folders()->count())->toBe(18)
        ->and($wordpressProject->snippets()->count())->toBe(59)
        ->and($visualProject->folders()->count())->toBe(8)
        ->and($visualProject->snippets()->count())->toBe(14)
        ->and($githubProject->folders()->count())->toBe(6)
        ->and($githubProject->snippets()->count())->toBe(34)
        ->and($pluginBootstrap->variations()->count())->toBe(2)
        ->and($pluginBootstrap->variations()->where('is_default', true)->count())->toBe(1)
        ->and($timberLoop->refresh()->title)->toBe('My Timber Loop')
        ->and($defaultVariation->refresh()->content)->toBe('My edited loop')
        ->and($customVariation->fresh())->not->toBeNull()
        ->and($timberLoop->variations()->count())->toBe(4)
        ->and($timberLoop->variations()->where('is_default', true)->count())->toBe(1)
        ->and(
            Project::query()
                ->selectRaw('user_id, name, COUNT(*) AS aggregate')
                ->groupBy('user_id', 'name')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
        )->toBe(0)
        ->and(
            Snippet::query()
                ->selectRaw('user_id, location_key, filename, COUNT(*) AS aggregate')
                ->groupBy('user_id', 'location_key', 'filename')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
        )->toBe(0);
});

test('it leaves an unrelated project with a colliding example name untouched', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);
    $existingProject = Project::factory()->for($user)->create([
        'name' => 'Loop Recipes',
        'description' => 'My unrelated project',
    ]);

    $this->seed(SnippetExamplesSeeder::class);

    expect($existingProject->refresh()->description)->toBe('My unrelated project')
        ->and($existingProject->folders()->count())->toBe(0)
        ->and($existingProject->snippets()->count())->toBe(0)
        ->and($user->projects()->count())->toBe(5)
        ->and($user->projects()->where('name', 'Timber Blog Theme')->count())->toBe(1)
        ->and($user->projects()->where('name', 'WordPress Block Theme Blueprint')->count())->toBe(1)
        ->and($user->projects()->where('name', 'CN Visual Hero Code Stack')->count())->toBe(1)
        ->and($user->projects()->where('name', 'GitHub')->count())->toBe(1)
        ->and($user->snippets()->count())->toBe(112);
});

test('it requires every signature filename before reusing an example project', function () {
    $user = User::factory()->create(['email' => 'dev@dev.dev']);
    $decoyProject = Project::factory()->for($user)->create([
        'name' => 'Existing Block Library',
    ]);

    foreach (range(1, 5) as $position) {
        $folder = Folder::factory()->for($decoyProject)->create([
            'name' => "block-{$position}",
            'position' => $position,
        ]);

        Snippet::factory()->inFolder($folder)->create([
            'filename' => 'block.json',
            'language' => 'json',
        ]);
    }

    $this->seed(SnippetExamplesSeeder::class);

    $blueprint = Project::query()
        ->whereBelongsTo($user)
        ->where('name', 'WordPress Block Theme Blueprint')
        ->sole();

    expect($blueprint->id)->not->toBe($decoyProject->id)
        ->and($blueprint->snippets()->count())->toBe(59)
        ->and($decoyProject->snippets()->count())->toBe(5)
        ->and($decoyProject->folders()->count())->toBe(5);
});

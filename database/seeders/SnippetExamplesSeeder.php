<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use App\Support\Snippets\FrameworkCatalog;
use App\Support\Snippets\SnippetLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SnippetExamplesSeeder extends Seeder
{
    /**
     * Seed reusable recipes and representative cross-file projects.
     */
    public function run(): void
    {
        $user = User::query()
            ->where('email', 'dev@dev.dev')
            ->firstOrFail();

        DB::transaction(function () use ($user): void {
            FrameworkCatalog::seedFor($user);
            $tags = $this->tags($user);

            $this->seedStandaloneUtility($user, $tags);
            $this->seedLoopRecipes($user, $tags);
            $this->seedTimberBlogTheme($user, $tags);
            $this->seedWordPressBlockThemeBlueprint($user, $tags);
            $this->seedVisualCodeStackHero($user, $tags);
            $this->seedGitHubReference($user, $tags);
        });
    }

    /** @param array<string, Tag> $tags */
    private function seedStandaloneUtility(User $user, array $tags): void
    {
        $snippet = $user->snippets()->firstOrCreate(
            ['location_key' => 'standalone', 'filename' => 'inspect-value.php'],
            [
                'project_id' => null,
                'folder_id' => null,
                'title' => 'Inspect a PHP Value',
                'language' => 'php',
                'description' => 'A project-free PHP debugging helper that can be found from the flat library.',
                'position' => 0,
            ],
        );

        $snippet->tags()->syncWithoutDetaching([
            $tags['php']->id,
            $tags['reusable']->id,
            $tags['template-variables']->id,
        ]);

        if (! $snippet->wasRecentlyCreated) {
            return;
        }

        $snippet->variations()->createMany([
            [
                'created_by_id' => $user->id,
                'name' => 'Dump to the browser',
                'content' => "<?php\n\nvar_dump({{{value:\$value}}});",
                'position' => 1,
                'is_default' => false,
            ],
            [
                'created_by_id' => $user->id,
                'name' => 'Write to the PHP error log',
                'content' => "<?php\n\nerror_log(print_r({{{value:\$value}}}, true));",
                'position' => 2,
                'is_default' => true,
            ],
        ]);
        $snippet->variablePresets()->createMany([
            ['name' => 'Current item', 'values' => ['value' => '$item']],
            ['name' => 'Request data', 'values' => ['value' => '$_POST']],
        ]);
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedLoopRecipes(User $user, array $tags): void
    {
        $bundle = $this->exampleProject(
            user: $user,
            signatureFilenames: ['timber-foreach.twig', 'php-foreach.php'],
            attributes: [
                'name' => 'Loop Recipes',
                'kind' => 'bundle',
                'description' => 'Copy-ready PHP, Twig, and JavaScript loop patterns in separate files.',
                'position' => 2,
            ],
        );

        if ($bundle === null) {
            return;
        }

        $twigFolder = $bundle->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => 'Timber Twig'],
            ['position' => 0],
        );
        $phpFolder = $bundle->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => 'PHP'],
            ['position' => 1],
        );
        $javascriptFolder = $bundle->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => 'JavaScript'],
            ['position' => 2],
        );
        $componentsFolder = $bundle->folders()->firstOrCreate(
            ['parent_id' => $twigFolder->id, 'name' => 'components'],
            ['position' => 0],
        );

        $timberVariationOne = <<<'TWIG'
{% for post in posts %}
    <article>
        <h2><a href="{{ post.link }}">{{ post.title }}</a></h2>
    </article>
{% endfor %}
TWIG;

        $timberVariationTwo = <<<'TWIG'
<div class="post-grid">
    {% for post in posts %}
        <article class="post-card" data-position="{{ loop.index }}">
            <h2><a href="{{ post.link }}">{{ post.title }}</a></h2>
        </article>
    {% else %}
        <p>No posts found.</p>
    {% endfor %}
</div>
TWIG;

        $timberVariationThree = <<<'TWIG'
{% set items = {{{collection:posts}}} %}

<div class="{{{wrapper_class:post-grid}}}">
    {% for item in items %}
        {% include '{{{partial:components/post-card.twig}}}' with {
            item: item,
            position: loop.index,
        } only %}
    {% else %}
        <p class="empty-state">{{{empty_message:No items found.}}}</p>
    {% endfor %}
</div>
TWIG;

        $this->createSnippet(
            project: $bundle,
            folder: $twigFolder,
            user: $user,
            attributes: [
                'title' => 'Timber Twig Foreach Loop',
                'filename' => 'timber-foreach.twig',
                'language' => 'twig',
                'description' => 'Loop over Timber data, expose loop metadata, render a partial, and handle an empty collection.',
                'content' => $timberVariationThree,
                'position' => 0,
            ],
            variations: [
                [
                    'position' => 1,
                    'name' => 'Basic Timber posts loop',
                    'content' => $timberVariationOne,
                ],
                [
                    'position' => 2,
                    'name' => 'Add loop metadata and empty state',
                    'content' => $timberVariationTwo,
                ],
                [
                    'position' => 3,
                    'name' => 'Add configurable collection and partial',
                    'content' => $timberVariationThree,
                ],
            ],
            presets: [
                [
                    'name' => 'WordPress posts',
                    'values' => [
                        'collection' => 'posts',
                        'wrapper_class' => 'post-grid',
                        'partial' => 'components/post-card.twig',
                        'empty_message' => 'No posts found.',
                    ],
                ],
                [
                    'name' => 'Team members',
                    'values' => [
                        'collection' => 'team_members',
                        'wrapper_class' => 'team-grid',
                        'partial' => 'components/team-member-card.twig',
                        'empty_message' => 'No team members found.',
                    ],
                ],
            ],
            tags: [
                $tags['twig'],
                $tags['timber'],
                $tags['loop'],
                $tags['reusable'],
                $tags['template-variables'],
                $tags['wordpress'],
            ],
        );

        $postCardVariationOne = <<<'TWIG'
<article class="post-card">
    <h2><a href="{{ item.link }}">{{ item.title }}</a></h2>
</article>
TWIG;

        $postCardVariationTwo = <<<'TWIG'
<article class="post-card" data-position="{{ position }}">
    <h2><a href="{{ item.link }}">{{ item.title }}</a></h2>
    <p>{{ item.excerpt }}</p>
</article>
TWIG;

        $this->createSnippet(
            project: $bundle,
            folder: $componentsFolder,
            user: $user,
            attributes: [
                'title' => 'Timber Post Card Partial',
                'filename' => 'post-card.twig',
                'language' => 'twig',
                'description' => 'The companion card rendered by the Timber foreach recipe.',
                'content' => $postCardVariationTwo,
                'position' => 0,
            ],
            variations: [
                [
                    'position' => 1,
                    'name' => 'Linked post title',
                    'content' => $postCardVariationOne,
                ],
                [
                    'position' => 2,
                    'name' => 'Add excerpt and loop position',
                    'content' => $postCardVariationTwo,
                ],
            ],
            presets: [],
            tags: [
                $tags['twig'],
                $tags['timber'],
                $tags['reusable'],
                $tags['wordpress'],
            ],
        );

        $teamCardVariationOne = <<<'TWIG'
<article class="team-member-card">
    <h2><a href="{{ item.link }}">{{ item.title }}</a></h2>
</article>
TWIG;

        $teamCardVariationTwo = <<<'TWIG'
<article class="team-member-card" data-position="{{ position }}">
    <h2><a href="{{ item.link }}">{{ item.title }}</a></h2>

    {% if item.meta('role') %}
        <p class="team-member-card__role">{{ item.meta('role') }}</p>
    {% endif %}
</article>
TWIG;

        $this->createSnippet(
            project: $bundle,
            folder: $componentsFolder,
            user: $user,
            attributes: [
                'title' => 'Timber Team Member Card Partial',
                'filename' => 'team-member-card.twig',
                'language' => 'twig',
                'description' => 'A companion card for the Team members variable preset.',
                'content' => $teamCardVariationTwo,
                'position' => 1,
            ],
            variations: [
                [
                    'position' => 1,
                    'name' => 'Linked team member title',
                    'content' => $teamCardVariationOne,
                ],
                [
                    'position' => 2,
                    'name' => 'Add role and loop position',
                    'content' => $teamCardVariationTwo,
                ],
            ],
            presets: [],
            tags: [
                $tags['twig'],
                $tags['timber'],
                $tags['reusable'],
                $tags['wordpress'],
            ],
        );

        $phpVariationOne = <<<'PHP'
<?php

foreach ($items as $item) {
    echo $item.PHP_EOL;
}
PHP;

        $phpVariationTwo = <<<'PHP'
<?php

foreach ($items as $key => $value) {
    printf("%s: %s\n", $key, $value);
}
PHP;

        $phpVariationThree = <<<'PHP'
<?php

foreach ({{{collection:$items}}} as {{{key:$key}}} => {{{value:$value}}}) {
    printf(
        '<li data-key="%s">%s</li>',
        htmlspecialchars((string) {{{key:$key}}}, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars((string) {{{value:$value}}}, ENT_QUOTES, 'UTF-8'),
    );
}
PHP;

        $this->createSnippet(
            project: $bundle,
            folder: $phpFolder,
            user: $user,
            attributes: [
                'title' => 'PHP Foreach Loop',
                'filename' => 'php-foreach.php',
                'language' => 'php',
                'description' => 'A configurable key/value foreach loop with escaped HTML output.',
                'content' => $phpVariationThree,
                'position' => 0,
            ],
            variations: [
                [
                    'position' => 1,
                    'name' => 'Simple value loop',
                    'content' => $phpVariationOne,
                ],
                [
                    'position' => 2,
                    'name' => 'Key and value loop',
                    'content' => $phpVariationTwo,
                ],
                [
                    'position' => 3,
                    'name' => 'Escaped HTML list output',
                    'content' => $phpVariationThree,
                ],
            ],
            presets: [
                [
                    'name' => 'Indexed items',
                    'values' => [
                        'collection' => '$items',
                        'key' => '$index',
                        'value' => '$item',
                    ],
                ],
                [
                    'name' => 'Associative settings',
                    'values' => [
                        'collection' => '$settings',
                        'key' => '$name',
                        'value' => '$setting',
                    ],
                ],
                [
                    'name' => 'WordPress fields',
                    'values' => [
                        'collection' => '$fields',
                        'key' => '$fieldName',
                        'value' => '$fieldValue',
                    ],
                ],
            ],
            tags: [
                $tags['php'],
                $tags['loop'],
                $tags['reusable'],
                $tags['template-variables'],
            ],
        );

        $this->seedPhpLoopRecipes($bundle, $phpFolder, $user, $tags);
        $this->seedJavaScriptLoopRecipes($bundle, $javascriptFolder, $user, $tags);
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedPhpLoopRecipes(Project $project, Folder $folder, User $user, array $tags): void
    {
        $recipes = [
            [
                'title' => 'PHP For Loop',
                'filename' => 'php-for.php',
                'description' => 'Iterate over an indexed array with a counter and a cached item count.',
                'variation' => 'Indexed for loop',
                'content' => <<<'PHP'
<?php

$count = count($items);

for ($index = 0; $index < $count; $index++) {
    echo $items[$index].PHP_EOL;
}
PHP,
            ],
            [
                'title' => 'PHP While Loop',
                'filename' => 'php-while.php',
                'description' => 'Process indexed items while an explicit condition remains true.',
                'variation' => 'Condition-first while loop',
                'content' => <<<'PHP'
<?php

$index = 0;
$count = count($items);

while ($index < $count) {
    echo $items[$index].PHP_EOL;
    $index++;
}
PHP,
            ],
            [
                'title' => 'PHP Do While Loop',
                'filename' => 'php-do-while.php',
                'description' => 'Run the loop body before checking the condition, while safely handling an empty array.',
                'variation' => 'Guarded do while loop',
                'content' => <<<'PHP'
<?php

$index = 0;
$count = count($items);

if ($count > 0) {
    do {
        echo $items[$index].PHP_EOL;
        $index++;
    } while ($index < $count);
}
PHP,
            ],
        ];

        foreach ($recipes as $position => $recipe) {
            $this->createSingleVariationSnippet(
                project: $project,
                folder: $folder,
                user: $user,
                attributes: [
                    'title' => $recipe['title'],
                    'filename' => $recipe['filename'],
                    'language' => 'php',
                    'description' => $recipe['description'],
                    'content' => $recipe['content'],
                    'position' => $position + 1,
                ],
                variationName: $recipe['variation'],
                tags: [
                    $tags['php'],
                    $tags['loop'],
                    $tags['reusable'],
                ],
            );
        }
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedJavaScriptLoopRecipes(Project $project, Folder $folder, User $user, array $tags): void
    {
        $recipes = [
            [
                'title' => 'JavaScript For Loop',
                'filename' => 'javascript-for.js',
                'description' => 'Iterate over an array with an index-controlled for loop.',
                'variation' => 'Indexed for loop',
                'content' => <<<'JAVASCRIPT'
for (let index = 0; index < items.length; index += 1) {
    console.log(items[index]);
}
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript For Of Loop',
                'filename' => 'javascript-for-of.js',
                'description' => 'Iterate over iterable values while retaining each array index.',
                'variation' => 'For of with entries',
                'content' => <<<'JAVASCRIPT'
for (const [index, item] of items.entries()) {
    console.log(index, item);
}
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript For In Loop',
                'filename' => 'javascript-for-in.js',
                'description' => 'Iterate over an object’s own enumerable properties.',
                'variation' => 'Own object properties',
                'content' => <<<'JAVASCRIPT'
for (const key in settings) {
    if (Object.hasOwn(settings, key)) {
        console.log(key, settings[key]);
    }
}
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript For Await Of Loop',
                'filename' => 'javascript-for-await-of.js',
                'description' => 'Consume values from an asynchronous iterable in sequence.',
                'variation' => 'Sequential async iteration',
                'content' => <<<'JAVASCRIPT'
async function processMessages(messages) {
    for await (const message of messages) {
        await handleMessage(message);
    }
}
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript While Loop',
                'filename' => 'javascript-while.js',
                'description' => 'Repeat work while a condition remains true.',
                'variation' => 'Condition-first while loop',
                'content' => <<<'JAVASCRIPT'
let index = 0;

while (index < items.length) {
    console.log(items[index]);
    index += 1;
}
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript Do While Loop',
                'filename' => 'javascript-do-while.js',
                'description' => 'Run the loop body before checking the next condition.',
                'variation' => 'Guarded do while loop',
                'content' => <<<'JAVASCRIPT'
let index = 0;

if (items.length > 0) {
    do {
        console.log(items[index]);
        index += 1;
    } while (index < items.length);
}
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript Array For Each',
                'filename' => 'javascript-foreach.js',
                'description' => 'Run a side effect once for every array item.',
                'variation' => 'Array forEach callback',
                'content' => <<<'JAVASCRIPT'
items.forEach((item, index) => {
    console.log(index, item);
});
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript Array Map',
                'filename' => 'javascript-map.js',
                'description' => 'Transform every array item into a new array.',
                'variation' => 'Map items to labels',
                'content' => <<<'JAVASCRIPT'
const labels = items.map((item) => item.name);
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript Array Filter',
                'filename' => 'javascript-filter.js',
                'description' => 'Create a new array containing only matching items.',
                'variation' => 'Filter active items',
                'content' => <<<'JAVASCRIPT'
const activeItems = items.filter((item) => item.active);
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript Array Reduce',
                'filename' => 'javascript-reduce.js',
                'description' => 'Combine array items into a single accumulated value.',
                'variation' => 'Reduce items to a total',
                'content' => <<<'JAVASCRIPT'
const total = items.reduce((sum, item) => sum + item.price, 0);
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript Array Reduce Right',
                'filename' => 'javascript-reduce-right.js',
                'description' => 'Accumulate array values from right to left.',
                'variation' => 'Build a path from the right',
                'content' => <<<'JAVASCRIPT'
const path = segments.reduceRight(
    (currentPath, segment) => `${segment}/${currentPath}`,
    '',
);
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript Array Find',
                'filename' => 'javascript-find.js',
                'description' => 'Return the first array item that matches a condition.',
                'variation' => 'Find an item by ID',
                'content' => <<<'JAVASCRIPT'
const selectedItem = items.find((item) => item.id === targetId);
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript Array Find Index',
                'filename' => 'javascript-find-index.js',
                'description' => 'Return the index of the first matching array item.',
                'variation' => 'Find an item index by ID',
                'content' => <<<'JAVASCRIPT'
const selectedIndex = items.findIndex((item) => item.id === targetId);
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript Array Find Last',
                'filename' => 'javascript-find-last.js',
                'description' => 'Return the last array item that matches a condition.',
                'variation' => 'Find the last completed item',
                'content' => <<<'JAVASCRIPT'
const lastCompletedItem = items.findLast((item) => item.completed);
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript Array Find Last Index',
                'filename' => 'javascript-find-last-index.js',
                'description' => 'Return the index of the last matching array item.',
                'variation' => 'Find the last completed index',
                'content' => <<<'JAVASCRIPT'
const lastCompletedIndex = items.findLastIndex((item) => item.completed);
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript Array Some',
                'filename' => 'javascript-some.js',
                'description' => 'Check whether at least one array item matches a condition.',
                'variation' => 'Check for an unavailable item',
                'content' => <<<'JAVASCRIPT'
const hasUnavailableItem = items.some((item) => !item.available);
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript Array Every',
                'filename' => 'javascript-every.js',
                'description' => 'Check whether every array item matches a condition.',
                'variation' => 'Check that every item is valid',
                'content' => <<<'JAVASCRIPT'
const allItemsAreValid = items.every((item) => item.valid);
JAVASCRIPT,
            ],
            [
                'title' => 'JavaScript Array Flat Map',
                'filename' => 'javascript-flat-map.js',
                'description' => 'Map nested values and flatten the result by one level.',
                'variation' => 'Flatten category products',
                'content' => <<<'JAVASCRIPT'
const products = categories.flatMap((category) => category.products);
JAVASCRIPT,
            ],
        ];

        foreach ($recipes as $position => $recipe) {
            $this->createSingleVariationSnippet(
                project: $project,
                folder: $folder,
                user: $user,
                attributes: [
                    'title' => $recipe['title'],
                    'filename' => $recipe['filename'],
                    'language' => 'javascript',
                    'description' => $recipe['description'],
                    'content' => $recipe['content'],
                    'position' => $position,
                ],
                variationName: $recipe['variation'],
                tags: [
                    $tags['javascript'],
                    $tags['loop'],
                    $tags['reusable'],
                ],
            );
        }
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedTimberBlogTheme(User $user, array $tags): void
    {
        $project = $this->exampleProject(
            user: $user,
            signatureFilenames: ['archive.php', 'archive.twig'],
            attributes: [
                'name' => 'Timber Blog Theme',
                'kind' => 'project',
                'description' => 'A small cross-file Timber archive showing PHP context and Twig presentation together.',
                'position' => 3,
            ],
        );

        if ($project === null) {
            return;
        }

        $viewsFolder = $project->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => 'views'],
            ['position' => 0],
        );
        $pagesFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $viewsFolder->id, 'name' => 'pages'],
            ['position' => 0],
        );

        $baseLayout = <<<'TWIG'
<!doctype html>
<html lang="{{ site.language|default('en') }}">
    <head>
        <meta charset="{{ site.charset|default('UTF-8') }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{ function('wp_head') }}
    </head>
    <body class="{{ body_class }}">
        {% block content %}{% endblock %}

        {{ function('wp_footer') }}
    </body>
</html>
TWIG;

        $this->createSnippet(
            project: $project,
            folder: $viewsFolder,
            user: $user,
            attributes: [
                'title' => 'Base Twig Layout',
                'filename' => 'base.twig',
                'language' => 'twig',
                'description' => 'The shared Timber layout extended by archive templates in this project.',
                'content' => $baseLayout,
                'position' => 0,
            ],
            variations: [
                [
                    'position' => 1,
                    'name' => 'WordPress document shell',
                    'content' => $baseLayout,
                ],
            ],
            presets: [],
            tags: [
                $tags['twig'],
                $tags['timber'],
                $tags['wordpress'],
            ],
        );

        $controllerVariationOne = <<<'PHP'
<?php

use Timber\Timber;

$context = Timber::context();

Timber::render('pages/archive.twig', $context);
PHP;

        $controllerVariationTwo = <<<'PHP'
<?php

use Timber\Timber;

$context = Timber::context([
    'posts' => Timber::get_posts([
        'post_type' => '{{{post_type:post}}}',
        'posts_per_page' => {{{posts_per_page:12}}},
        'paged' => max(1, (int) get_query_var('paged')),
    ]),
]);
$context['title'] = '{{{heading:Latest articles}}}';

Timber::render('{{{template:pages/archive.twig}}}', $context);
PHP;

        $this->createSnippet(
            project: $project,
            folder: null,
            user: $user,
            attributes: [
                'title' => 'Archive Controller',
                'filename' => 'archive.php',
                'language' => 'php',
                'description' => 'Build a configurable Timber archive context and render its Twig template.',
                'content' => $controllerVariationTwo,
                'position' => 0,
            ],
            variations: [
                [
                    'position' => 1,
                    'name' => 'Render the default archive query',
                    'content' => $controllerVariationOne,
                ],
                [
                    'position' => 2,
                    'name' => 'Add a configurable post query',
                    'content' => $controllerVariationTwo,
                ],
            ],
            presets: [
                [
                    'name' => 'Blog archive',
                    'values' => [
                        'post_type' => 'post',
                        'posts_per_page' => '12',
                        'heading' => 'Latest articles',
                        'template' => 'pages/archive.twig',
                    ],
                ],
                [
                    'name' => 'News archive',
                    'values' => [
                        'post_type' => 'news',
                        'posts_per_page' => '9',
                        'heading' => 'Latest news',
                        'template' => 'pages/news.twig',
                    ],
                ],
            ],
            tags: [
                $tags['php'],
                $tags['timber'],
                $tags['wordpress'],
                $tags['template-variables'],
            ],
        );

        $archiveVariationOne = <<<'TWIG'
{% extends 'base.twig' %}

{% block content %}
    <main class="archive">
        <h1>{{ title }}</h1>

        {% for post in posts %}
            <article>
                <h2><a href="{{ post.link }}">{{ post.title }}</a></h2>
            </article>
        {% endfor %}
    </main>
{% endblock %}
TWIG;

        $archiveVariationTwo = <<<'TWIG'
{% extends 'base.twig' %}

{% block content %}
    <main class="archive">
        <h1>{{ title|default('{{{fallback_title:Latest posts}}}') }}</h1>

        <div class="{{{grid_class:post-grid}}}">
            {% for post in posts %}
                <article class="post-card">
                    <h2><a href="{{ post.link }}">{{ post.title }}</a></h2>
                    <p>{{ post.excerpt }}</p>
                </article>
            {% else %}
                <p>{{{empty_message:No posts found.}}}</p>
            {% endfor %}
        </div>
    </main>
{% endblock %}
TWIG;

        $this->createSnippet(
            project: $project,
            folder: $pagesFolder,
            user: $user,
            attributes: [
                'title' => 'Archive Page',
                'filename' => 'archive.twig',
                'language' => 'twig',
                'description' => 'Render an archive grid with a heading, post loop, excerpts, and an empty state.',
                'content' => $archiveVariationTwo,
                'position' => 0,
            ],
            variations: [
                [
                    'position' => 1,
                    'name' => 'Basic archive list',
                    'content' => $archiveVariationOne,
                ],
                [
                    'position' => 2,
                    'name' => 'Add grid and empty state',
                    'content' => $archiveVariationTwo,
                ],
            ],
            presets: [
                [
                    'name' => 'Blog archive',
                    'values' => [
                        'fallback_title' => 'Latest posts',
                        'grid_class' => 'post-grid',
                        'empty_message' => 'No posts found.',
                    ],
                ],
                [
                    'name' => 'News archive',
                    'values' => [
                        'fallback_title' => 'Latest news',
                        'grid_class' => 'news-grid',
                        'empty_message' => 'No news articles found.',
                    ],
                ],
            ],
            tags: [
                $tags['twig'],
                $tags['timber'],
                $tags['loop'],
                $tags['wordpress'],
                $tags['template-variables'],
            ],
        );

        $newsVariationOne = <<<'TWIG'
{% extends 'base.twig' %}

{% block content %}
    <main class="news-archive">
        <h1>{{ title }}</h1>

        {% for post in posts %}
            <article class="news-card">
                <h2><a href="{{ post.link }}">{{ post.title }}</a></h2>
            </article>
        {% else %}
            <p>No news articles found.</p>
        {% endfor %}
    </main>
{% endblock %}
TWIG;

        $newsVariationTwo = <<<'TWIG'
{% extends 'base.twig' %}

{% block content %}
    <main class="{{{wrapper_class:news-archive}}}">
        <h1>{{ title }}</h1>

        {% for post in posts %}
            <article class="news-card">
                <p class="news-card__date">{{ post.date }}</p>
                <h2><a href="{{ post.link }}">{{ post.title }}</a></h2>
                <p>{{ post.excerpt }}</p>
            </article>
        {% else %}
            <p>{{{empty_message:No news articles found.}}}</p>
        {% endfor %}
    </main>
{% endblock %}
TWIG;

        $this->createSnippet(
            project: $project,
            folder: $pagesFolder,
            user: $user,
            attributes: [
                'title' => 'News Archive Page',
                'filename' => 'news.twig',
                'language' => 'twig',
                'description' => 'The companion template selected by the Archive Controller news preset.',
                'content' => $newsVariationTwo,
                'position' => 1,
            ],
            variations: [
                [
                    'position' => 1,
                    'name' => 'Basic news archive',
                    'content' => $newsVariationOne,
                ],
                [
                    'position' => 2,
                    'name' => 'Add date, excerpt, and variables',
                    'content' => $newsVariationTwo,
                ],
            ],
            presets: [
                [
                    'name' => 'News listing',
                    'values' => [
                        'wrapper_class' => 'news-grid',
                        'empty_message' => 'No news articles found.',
                    ],
                ],
            ],
            tags: [
                $tags['twig'],
                $tags['timber'],
                $tags['loop'],
                $tags['wordpress'],
                $tags['template-variables'],
            ],
        );
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedWordPressBlockThemeBlueprint(User $user, array $tags): void
    {
        $project = $this->exampleProject(
            user: $user,
            signatureFilenames: [
                'theme.json',
                'block.json',
                'compose.yaml',
                'mcp.json',
                'meilisearch.php',
            ],
            attributes: [
                'name' => 'WordPress Block Theme Blueprint',
                'kind' => 'project',
                'description' => 'A standards-oriented Gutenberg block theme, companion search plugin, local Docker stack, Figma MCP workflow, and reusable WordPress class references.',
                'position' => 4,
            ],
        );

        if ($project === null) {
            return;
        }

        $themeFolder = $project->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => 'block-theme'],
            ['position' => 0],
        );
        $incFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $themeFolder->id, 'name' => 'inc'],
            ['position' => 0],
        );
        $blocksFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $themeFolder->id, 'name' => 'blocks'],
            ['position' => 1],
        );
        $featureCardFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $blocksFolder->id, 'name' => 'feature-card'],
            ['position' => 0],
        );
        $patternsFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $themeFolder->id, 'name' => 'patterns'],
            ['position' => 2],
        );
        $templatesFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $themeFolder->id, 'name' => 'templates'],
            ['position' => 3],
        );
        $partsFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $themeFolder->id, 'name' => 'parts'],
            ['position' => 4],
        );
        $assetsFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $themeFolder->id, 'name' => 'assets'],
            ['position' => 5],
        );
        $stylesFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $assetsFolder->id, 'name' => 'styles'],
            ['position' => 0],
        );
        $pluginFolder = $project->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => 'companion-plugin'],
            ['position' => 1],
        );
        $pluginIncludesFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $pluginFolder->id, 'name' => 'includes'],
            ['position' => 0],
        );
        $dockerFolder = $project->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => 'docker'],
            ['position' => 2],
        );
        $vscodeFolder = $project->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => '.vscode'],
            ['position' => 3],
        );
        $toolingFolder = $project->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => 'tooling'],
            ['position' => 4],
        );
        $integrationsFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $pluginFolder->id, 'name' => 'integrations'],
            ['position' => 1],
        );
        $directoryIntegrationFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $integrationsFolder->id, 'name' => 'custom-directory'],
            ['position' => 0],
        );
        $facetWpIntegrationFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $integrationsFolder->id, 'name' => 'facetwp'],
            ['position' => 1],
        );

        $wordpressTags = [
            $tags['wordpress'],
            $tags['gutenberg'],
            $tags['block-theme'],
            $tags['wordpress-standards'],
        ];

        $styleCss = <<<'CSS'
/**
 * Theme Name: WordPress Block Theme Blueprint
 * Description: A standards-oriented foundation for custom Gutenberg sites.
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Text Domain: blueprint
 */

body {
    text-wrap: pretty;
}

a:focus-visible,
button:focus-visible {
    outline: 2px solid var(--wp--preset--color--accent);
    outline-offset: 3px;
}
CSS;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $themeFolder,
            user: $user,
            attributes: [
                'title' => 'Block Theme Stylesheet Header',
                'filename' => 'style.css',
                'language' => 'css',
                'description' => 'The required WordPress theme header with a small accessible focus baseline.',
                'content' => $styleCss,
                'position' => 0,
            ],
            variationName: 'Theme metadata and focus baseline',
            tags: array_merge($wordpressTags, [$tags['accessibility']]),
        );

        $themeJson = <<<'JSON'
{
    "$schema": "https://schemas.wp.org/trunk/theme.json",
    "version": 3,
    "settings": {
        "appearanceTools": true,
        "layout": {
            "contentSize": "760px",
            "wideSize": "1280px"
        },
        "color": {
            "defaultPalette": false,
            "palette": [
                { "slug": "canvas", "name": "Canvas", "color": "#f5f7fa" },
                { "slug": "ink", "name": "Ink", "color": "#172033" },
                { "slug": "accent", "name": "Accent", "color": "#2563eb" }
            ]
        },
        "spacing": {
            "units": ["px", "rem", "vw", "%"],
            "spacingSizes": [
                { "slug": "20", "name": "Small", "size": "0.75rem" },
                { "slug": "40", "name": "Medium", "size": "1.5rem" },
                { "slug": "60", "name": "Large", "size": "3rem" }
            ]
        },
        "typography": {
            "fluid": true,
            "fontSizes": [
                { "slug": "small", "name": "Small", "size": "0.875rem" },
                { "slug": "body", "name": "Body", "size": "1rem" },
                { "slug": "display", "name": "Display", "size": "clamp(2.5rem, 8vw, 5rem)" }
            ]
        }
    },
    "styles": {
        "color": {
            "background": "var(--wp--preset--color--canvas)",
            "text": "var(--wp--preset--color--ink)"
        },
        "spacing": {
            "blockGap": "var(--wp--preset--spacing--40)"
        },
        "typography": {
            "fontSize": "var(--wp--preset--font-size--body)",
            "lineHeight": "1.6"
        },
        "blocks": {
            "core/button": {
                "border": { "radius": "0.375rem" },
                "typography": { "fontWeight": "600" }
            }
        }
    }
}
JSON;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $themeFolder,
            user: $user,
            attributes: [
                'title' => 'Theme JSON Design System',
                'filename' => 'theme.json',
                'language' => 'json',
                'description' => 'A theme.json version 3 design system with constrained layout, fluid typography, spacing, and editor-visible presets.',
                'content' => $themeJson,
                'position' => 1,
            ],
            variationName: 'Theme JSON version 3',
            tags: array_merge($wordpressTags, [
                $tags['theme-json'],
                $tags['design-tokens'],
            ]),
        );

        $functionsPhp = <<<'PHP'
<?php
/**
 * Block theme bootstrap.
 *
 * @package Blueprint
 */

define('BLUEPRINT_THEME_VERSION', '1.0.0');
define('BLUEPRINT_THEME_PATH', get_template_directory());
define('BLUEPRINT_THEME_URL', get_template_directory_uri());

require_once BLUEPRINT_THEME_PATH . '/inc/setup.php';
require_once BLUEPRINT_THEME_PATH . '/inc/register-blocks.php';
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $themeFolder,
            user: $user,
            attributes: [
                'title' => 'Theme Bootstrap',
                'filename' => 'functions.php',
                'language' => 'php',
                'description' => 'A deliberately small theme entry point that loads namespaced responsibilities from inc.',
                'content' => $functionsPhp,
                'position' => 2,
            ],
            variationName: 'Minimal theme bootstrap',
            tags: $wordpressTags,
        );

        $themeSetup = <<<'PHP'
<?php
/**
 * Theme support and asset registration.
 *
 * @package Blueprint
 */

// {!# snippet: register_theme_supports #!}
function blueprint_setup_theme(): void
{
    add_theme_support('wp-block-styles');
    add_theme_support('editor-styles');
    add_theme_support('responsive-embeds');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');

    add_editor_style([
        'assets/styles/figma-tokens.css',
        'assets/styles/editor.css',
    ]);
}
add_action('after_setup_theme', 'blueprint_setup_theme');

// {!# snippet: enqueue_theme_assets #!}
function blueprint_enqueue_assets(): void
{
    $stylesheetPath = BLUEPRINT_THEME_PATH . '/style.css';
    $version = file_exists($stylesheetPath)
        ? (string) filemtime($stylesheetPath)
        : BLUEPRINT_THEME_VERSION;

    wp_enqueue_style(
        'blueprint-design-tokens',
        BLUEPRINT_THEME_URL . '/assets/styles/figma-tokens.css',
        [],
        BLUEPRINT_THEME_VERSION,
    );
    wp_enqueue_style(
        'blueprint',
        get_stylesheet_uri(),
        ['blueprint-design-tokens'],
        $version,
    );
}
add_action('wp_enqueue_scripts', 'blueprint_enqueue_assets');

// {!# snippet: register_editor_style #!}
function blueprint_enqueue_editor_assets(): void
{
    wp_enqueue_style(
        'blueprint-design-tokens',
        BLUEPRINT_THEME_URL . '/assets/styles/figma-tokens.css',
        [],
        BLUEPRINT_THEME_VERSION,
    );
    wp_enqueue_style(
        'blueprint-editor',
        BLUEPRINT_THEME_URL . '/assets/styles/editor.css',
        ['blueprint-design-tokens'],
        BLUEPRINT_THEME_VERSION,
    );
}
add_action('enqueue_block_editor_assets', 'blueprint_enqueue_editor_assets');
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $incFolder,
            user: $user,
            attributes: [
                'title' => 'Theme Supports and Assets',
                'filename' => 'setup.php',
                'language' => 'php',
                'description' => 'Three searchable snippets for block-theme supports, versioned frontend CSS, and editor parity.',
                'content' => $themeSetup,
                'position' => 0,
            ],
            variationName: 'Standards-oriented theme setup',
            tags: array_merge($wordpressTags, [$tags['accessibility']]),
        );

        $blockRegistration = <<<'PHP'
<?php
/**
 * Block and pattern registration.
 *
 * @package Blueprint
 */

// {!# snippet: register_block_metadata_directories #!}
function blueprint_register_blocks(): void
{
    $metadataFiles = glob(BLUEPRINT_THEME_PATH . '/blocks/*/block.json') ?: [];

    foreach ($metadataFiles as $metadataFile) {
        register_block_type(dirname($metadataFile));
    }
}
add_action('init', 'blueprint_register_blocks');

// {!# snippet: register_pattern_category #!}
function blueprint_register_pattern_categories(): void
{
    register_block_pattern_category(
        'blueprint',
        ['label' => __('Blueprint', 'blueprint')],
    );
}
add_action('init', 'blueprint_register_pattern_categories');
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $incFolder,
            user: $user,
            attributes: [
                'title' => 'Block and Pattern Registration',
                'filename' => 'register-blocks.php',
                'language' => 'php',
                'description' => 'Searchable registration snippets for every block.json directory and the theme pattern category.',
                'content' => $blockRegistration,
                'position' => 1,
            ],
            variationName: 'Metadata-first registration',
            tags: array_merge($wordpressTags, [
                $tags['dynamic-block'],
                $tags['block-pattern'],
            ]),
        );

        $blockJson = <<<'JSON'
{
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "blueprint/feature-card",
    "version": "1.0.0",
    "title": "Feature Card",
    "category": "design",
    "icon": "cover-image",
    "description": "A server-rendered feature card with a heading, summary, and optional link.",
    "textdomain": "blueprint",
    "attributes": {
        "heading": { "type": "string", "default": "Feature heading" },
        "summary": { "type": "string", "default": "" },
        "url": { "type": "string", "default": "" }
    },
    "supports": {
        "align": ["wide", "full"],
        "anchor": true,
        "html": false,
        "spacing": {
            "margin": true,
            "padding": true
        }
    },
    "editorScript": "file:./index.js",
    "style": "file:./style-index.css",
    "render": "file:./render.php"
}
JSON;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $featureCardFolder,
            user: $user,
            attributes: [
                'title' => 'Feature Card Block Metadata',
                'filename' => 'block.json',
                'language' => 'json',
                'description' => 'Block API version 3 metadata for a dynamic custom Gutenberg block.',
                'content' => $blockJson,
                'position' => 0,
            ],
            variationName: 'Block API version 3 metadata',
            tags: array_merge($wordpressTags, [$tags['dynamic-block']]),
        );

        $blockEditorScript = <<<'JAVASCRIPT'
const { registerBlockType } = wp.blocks;
const { RichText, URLInput, useBlockProps } = wp.blockEditor;
const { createElement: el } = wp.element;
const { __ } = wp.i18n;

// {!# snippet: feature_card_edit_component #!}
function FeatureCardEdit({ attributes, setAttributes }) {
    const blockProps = useBlockProps({ className: 'feature-card' });

    return el(
        'article',
        blockProps,
        el(RichText, {
            tagName: 'h2',
            value: attributes.heading,
            placeholder: __('Feature heading', 'blueprint'),
            onChange: (heading) => setAttributes({ heading }),
        }),
        el(RichText, {
            tagName: 'p',
            value: attributes.summary,
            placeholder: __('Feature summary', 'blueprint'),
            onChange: (summary) => setAttributes({ summary }),
        }),
        el(URLInput, {
            value: attributes.url,
            onChange: (url) => setAttributes({ url }),
        }),
    );
}

// {!# snippet: register_dynamic_feature_card #!}
registerBlockType('blueprint/feature-card', {
    edit: FeatureCardEdit,
    save: () => null,
});
JAVASCRIPT;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $featureCardFolder,
            user: $user,
            attributes: [
                'title' => 'Feature Card Editor',
                'filename' => 'index.js',
                'language' => 'javascript',
                'description' => 'Separate searchable edit-component and dynamic-block registration snippets.',
                'content' => $blockEditorScript,
                'position' => 1,
            ],
            variationName: 'Editor registration',
            tags: array_merge($wordpressTags, [
                $tags['dynamic-block'],
                $tags['accessibility'],
            ]),
        );

        $blockAssetMetadata = <<<'PHP'
<?php

return [
    'dependencies' => [
        'wp-block-editor',
        'wp-blocks',
        'wp-element',
        'wp-i18n',
    ],
    'version' => '1.0.0',
];
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $featureCardFolder,
            user: $user,
            attributes: [
                'title' => 'Feature Card Script Dependencies',
                'filename' => 'index.asset.php',
                'language' => 'php',
                'description' => 'Asset metadata that makes the unbundled block editor script load after its WordPress packages.',
                'content' => $blockAssetMetadata,
                'position' => 2,
            ],
            variationName: 'WordPress script dependency metadata',
            tags: array_merge($wordpressTags, [$tags['dynamic-block']]),
        );

        $blockRender = <<<'PHP'
<?php
/**
 * Render the Feature Card block.
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

// {!# snippet: render_feature_card #!}
$heading = isset($attributes['heading']) ? (string) $attributes['heading'] : '';
$summary = isset($attributes['summary']) ? (string) $attributes['summary'] : '';
$url = isset($attributes['url']) ? (string) $attributes['url'] : '';
?>
<article <?php echo get_block_wrapper_attributes(['class' => 'feature-card']); ?>>
    <?php if ($heading !== '') : ?>
        <h2><?php echo esc_html($heading); ?></h2>
    <?php endif; ?>

    <?php if ($summary !== '') : ?>
        <p><?php echo wp_kses_post($summary); ?></p>
    <?php endif; ?>

    <?php if ($url !== '') : ?>
        <a href="<?php echo esc_url($url); ?>">
            <?php esc_html_e('Learn more', 'blueprint'); ?>
        </a>
    <?php endif; ?>
</article>
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $featureCardFolder,
            user: $user,
            attributes: [
                'title' => 'Feature Card Render Callback',
                'filename' => 'render.php',
                'language' => 'php',
                'description' => 'A searchable server-rendered block template with contextual escaping and wrapper attributes.',
                'content' => $blockRender,
                'position' => 3,
            ],
            variationName: 'Escaped dynamic render',
            tags: array_merge($wordpressTags, [
                $tags['dynamic-block'],
                $tags['accessibility'],
            ]),
        );

        $blockStyles = <<<'CSS'
.wp-block-blueprint-feature-card {
    display: grid;
    gap: var(--wp--preset--spacing--20);
    padding: var(--wp--preset--spacing--40);
    color: var(--wp--preset--color--ink);
    background: color-mix(in srgb, var(--wp--preset--color--accent) 8%, transparent);
    border: 1px solid color-mix(in srgb, var(--wp--preset--color--ink) 16%, transparent);
    border-radius: 0.5rem;
}

.wp-block-blueprint-feature-card > :first-child {
    margin-block-start: 0;
}

.wp-block-blueprint-feature-card > :last-child {
    margin-block-end: 0;
}
CSS;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $featureCardFolder,
            user: $user,
            attributes: [
                'title' => 'Feature Card Shared Styles',
                'filename' => 'style-index.css',
                'language' => 'css',
                'description' => 'Frontend and editor styles referenced directly by the feature card block metadata.',
                'content' => $blockStyles,
                'position' => 4,
            ],
            variationName: 'Theme-token block styles',
            tags: array_merge($wordpressTags, [
                $tags['dynamic-block'],
                $tags['design-tokens'],
            ]),
        );

        $heroPattern = <<<'PHP'
<?php
/**
 * Title: Hero with call to action
 * Slug: blueprint/hero-call-to-action
 * Categories: blueprint, featured
 * Keywords: hero, call to action
 * Viewport Width: 1440
 */
?>
<!-- wp:group {"align":"full","backgroundColor":"ink","textColor":"canvas","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-canvas-color has-ink-background-color has-text-color has-background">
    <!-- wp:heading {"level":1,"fontSize":"display"} -->
    <h1 class="wp-block-heading has-display-font-size"><?php esc_html_e('Build something clear and useful.', 'blueprint'); ?></h1>
    <!-- /wp:heading -->

    <!-- wp:buttons -->
    <div class="wp-block-buttons">
        <!-- wp:button {"backgroundColor":"accent"} -->
        <div class="wp-block-button"><a class="wp-block-button__link has-accent-background-color has-background wp-element-button"><?php esc_html_e('Start a project', 'blueprint'); ?></a></div>
        <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
</div>
<!-- /wp:group -->
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $patternsFolder,
            user: $user,
            attributes: [
                'title' => 'Hero Block Pattern',
                'filename' => 'hero.php',
                'language' => 'php',
                'description' => 'A translatable file-based pattern composed from core blocks and theme.json presets.',
                'content' => $heroPattern,
                'position' => 0,
            ],
            variationName: 'Core-block hero pattern',
            tags: array_merge($wordpressTags, [
                $tags['block-pattern'],
                $tags['accessibility'],
            ]),
        );

        $indexTemplate = <<<'HTML'
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
    <!-- wp:query-title {"type":"archive","showPrefix":false} /-->
    <!-- wp:query {"query":{"perPage":10,"postType":"post","order":"desc","orderBy":"date"}} -->
    <div class="wp-block-query">
        <!-- wp:post-template -->
        <!-- wp:post-title {"isLink":true} /-->
        <!-- wp:post-excerpt {"moreText":"Continue reading"} /-->
        <!-- /wp:post-template -->
        <!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
        <!-- wp:query-pagination-previous /-->
        <!-- wp:query-pagination-numbers /-->
        <!-- wp:query-pagination-next /-->
        <!-- /wp:query-pagination -->
    </div>
    <!-- /wp:query -->
</main>
<!-- /wp:group -->
HTML;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $templatesFolder,
            user: $user,
            attributes: [
                'title' => 'Index Block Template',
                'filename' => 'index.html',
                'language' => 'html',
                'description' => 'A valid block-template fallback using Query, Post Template, and accessible pagination blocks.',
                'content' => $indexTemplate,
                'position' => 0,
            ],
            variationName: 'Query-loop index template',
            tags: array_merge($wordpressTags, [$tags['accessibility']]),
        );

        $headerPart = <<<'HTML'
<!-- wp:group {"layout":{"type":"flex","justifyContent":"space-between"},"style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}}} -->
<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40)">
    <!-- wp:site-title {"level":0} /-->
    <!-- wp:navigation {"overlayMenu":"mobile","ariaLabel":"Primary navigation"} /-->
</div>
<!-- /wp:group -->
HTML;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $partsFolder,
            user: $user,
            attributes: [
                'title' => 'Header Template Part',
                'filename' => 'header.html',
                'language' => 'html',
                'description' => 'A responsive header template part built entirely from core blocks.',
                'content' => $headerPart,
                'position' => 0,
            ],
            variationName: 'Core-block header',
            tags: array_merge($wordpressTags, [$tags['accessibility']]),
        );

        $wordpressClassReference = <<<'SCSS'
/*
 * Representative WordPress class families.
 *
 * WordPress and blocks can generate additional classes from block supports,
 * global styles, plugins, and user choices. Treat these as stable families,
 * not a frozen exhaustive list.
 */

/* {!# snippet: document_and_template_class_families #!} */
/*
 * .wp-site-blocks
 * .wp-block-template-part
 * .wp-block-{block-name}
 * .wp-element-button
 * .wp-element-caption
 */
.wp-site-blocks {
    min-height: 100vh;
}

.wp-block-template-part {
    width: 100%;
}

/* {!# snippet: alignment_class_family #!} */
.alignleft {
    float: left;
    margin-inline-end: var(--wp--preset--spacing--40);
}

.alignright {
    float: right;
    margin-inline-start: var(--wp--preset--spacing--40);
}

.aligncenter {
    margin-inline: auto;
}

.alignwide {
    width: min(100% - 2rem, var(--wp--style--global--wide-size));
    max-width: var(--wp--style--global--wide-size);
    margin-inline: auto;
}

.alignfull {
    width: 100vw;
    max-width: none;
    margin-inline: calc(50% - 50vw);
}

/* {!# snippet: screen_reader_text_class #!} */
.screen-reader-text {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
    border: 0;
}

.screen-reader-text:focus {
    z-index: 100000;
    top: 0.5rem;
    left: 0.5rem;
    width: auto;
    height: auto;
    padding: 0.75rem 1rem;
    clip-path: none;
}

/* {!# snippet: block_layout_class_families #!} */
.is-layout-flow > * + *,
.is-layout-constrained > * + * {
    margin-block-start: var(--wp--style--block-gap, 1.5rem);
}

.is-layout-flex {
    display: flex;
    flex-wrap: wrap;
    gap: var(--wp--style--block-gap, 1.5rem);
}

.is-layout-grid {
    display: grid;
    gap: var(--wp--style--block-gap, 1.5rem);
}

.wp-element-button,
.wp-block-button__link {
    cursor: pointer;
    text-decoration: none;
}

/* {!# snippet: block_support_state_class_families #!} */
/*
 * .has-background
 * .has-text-color
 * .has-link-color
 * .has-border-color
 * .has-{slug}-color
 * .has-{slug}-background-color
 * .has-{slug}-border-color
 * .has-{slug}-gradient-background
 */

/* {!# snippet: generated_preset_class_families #!} */
.has-accent-color {
    color: var(--wp--preset--color--accent);
}

.has-accent-background-color {
    background-color: var(--wp--preset--color--accent);
}

.has-display-font-size {
    font-size: var(--wp--preset--font-size--display);
}

/* {!# snippet: body_context_class_families #!} */
/*
 * Views: .home .blog .archive .single .page .search .search-results .error404
 * State: .logged-in .admin-bar .rtl .custom-background
 * Identity: .page-id-{id} .postid-{id} .page-template-{slug}
 */

/* {!# snippet: post_context_class_families #!} */
/*
 * Identity: .post-{id} .type-{post-type} .hentry
 * State: .status-{status} .sticky .password-required
 * Terms: .category-{slug} .tag-{slug}
 * Format: .format-{format}
 */

/* {!# snippet: navigation_media_and_comment_class_families #!} */
/*
 * Navigation: .wp-block-navigation .wp-block-navigation-item .current-menu-item
 * Media: .wp-block-image .wp-block-gallery .wp-element-caption .wp-caption
 * Comments: .comment-list .comment .comment-author .bypostauthor
 * Text alignment: .has-text-align-left .has-text-align-center .has-text-align-right
 */
SCSS;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $stylesFolder,
            user: $user,
            attributes: [
                'title' => 'WordPress Core Class Families',
                'filename' => '_wordpress-classes.scss',
                'language' => 'scss',
                'description' => 'Searchable reference sections for structural, alignment, accessibility, layout, support-state, body, post, navigation, media, comment, and generated preset class families.',
                'content' => $wordpressClassReference,
                'position' => 0,
            ],
            variationName: 'Representative core class families',
            tags: array_merge($wordpressTags, [
                $tags['wordpress-classes'],
                $tags['accessibility'],
            ]),
        );

        $this->seedWordPressGutenbergClassAtlas(
            project: $project,
            stylesFolder: $stylesFolder,
            user: $user,
            tags: $tags,
            wordpressTags: $wordpressTags,
        );

        $editorStyles = <<<'CSS'
.editor-styles-wrapper {
    color: var(--wp--preset--color--ink);
    background: var(--wp--preset--color--canvas);
}

.editor-styles-wrapper :where(a) {
    color: var(--wp--preset--color--accent);
}

.editor-styles-wrapper :where(.wp-block) {
    margin-block-start: 0;
    margin-block-end: var(--wp--style--block-gap);
}
CSS;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $stylesFolder,
            user: $user,
            attributes: [
                'title' => 'Block Editor Styles',
                'filename' => 'editor.css',
                'language' => 'css',
                'description' => 'The editor stylesheet loaded by the theme setup so authored content reflects frontend tokens and spacing.',
                'content' => $editorStyles,
                'position' => 1,
            ],
            variationName: 'Editor and frontend parity',
            tags: array_merge($wordpressTags, [
                $tags['design-tokens'],
                $tags['accessibility'],
            ]),
        );

        $composerJson = <<<'JSON'
{
    "name": "blueprint/wordpress-block-theme-tools",
    "description": "Companion functionality for the WordPress Block Theme Blueprint.",
    "type": "wordpress-plugin",
    "require": {
        "php": ">=8.1",
        "meilisearch/meilisearch-php": "^1.0"
    },
    "require-dev": {
        "dealerdirect/phpcodesniffer-composer-installer": "^1.0",
        "wp-coding-standards/wpcs": "^3.0"
    },
    "config": {
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true
        },
        "sort-packages": true
    },
    "scripts": {
        "lint": "phpcs --standard=../tooling/phpcs.xml.dist"
    }
}
JSON;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $pluginFolder,
            user: $user,
            attributes: [
                'title' => 'Companion Plugin Composer Setup',
                'filename' => 'composer.json',
                'language' => 'json',
                'description' => 'Server-side Meilisearch client and WordPress Coding Standards dependencies for the companion plugin.',
                'content' => $composerJson,
                'position' => 0,
            ],
            variationName: 'Meilisearch and WPCS dependencies',
            tags: array_merge($wordpressTags, [
                $tags['meilisearch'],
                $tags['wordpress-standards'],
            ]),
        );

        $pluginBootstrap = <<<'PHP'
<?php
/**
 * Plugin Name: WordPress Block Theme Tools
 * Description: Search indexing and API helpers for the Blueprint block theme.
 * Version: 1.0.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Text Domain: blueprint-tools
 */

defined('ABSPATH') || exit;

define('BLUEPRINT_TOOLS_PATH', plugin_dir_path(__FILE__));

$autoload = BLUEPRINT_TOOLS_PATH . 'vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
}

require_once BLUEPRINT_TOOLS_PATH . 'includes/meilisearch.php';
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $pluginFolder,
            user: $user,
            attributes: [
                'title' => 'Companion Plugin Bootstrap',
                'filename' => 'wordpress-block-theme-tools.php',
                'language' => 'php',
                'description' => 'A guarded WordPress plugin entry point for functionality that should survive theme changes.',
                'content' => $pluginBootstrap,
                'position' => 1,
            ],
            variationName: 'Plugin bootstrap',
            tags: $wordpressTags,
        );

        $meilisearchPhp = <<<'PHP'
<?php
/**
 * Meilisearch indexing and REST search.
 *
 * @package BlueprintTools
 */

use Meilisearch\Client;

// {!# snippet: create_meilisearch_client #!}
function blueprint_tools_meilisearch_client(): ?Client
{
    if (
        ! class_exists(Client::class)
        || ! defined('MEILISEARCH_HOST')
        || ! defined('MEILISEARCH_API_KEY')
    ) {
        return null;
    }

    return new Client(MEILISEARCH_HOST, MEILISEARCH_API_KEY);
}

// {!# snippet: index_published_wordpress_content #!}
function blueprint_tools_sync_post(
    string $newStatus,
    string $oldStatus,
    WP_Post $post,
): void {
    if (wp_is_post_revision($post) || ! post_type_supports($post->post_type, 'editor')) {
        return;
    }

    $client = blueprint_tools_meilisearch_client();

    if ($client === null) {
        return;
    }

    $index = $client->index('wordpress_content');

    if ($newStatus !== 'publish') {
        $index->deleteDocument((string) $post->ID);

        return;
    }

    $index->addDocuments([[
        'id' => (string) $post->ID,
        'post_type' => $post->post_type,
        'title' => get_the_title($post),
        'excerpt' => wp_strip_all_tags(get_the_excerpt($post)),
        'url' => get_permalink($post),
        'modified_at' => get_post_modified_time(DATE_ATOM, true, $post),
    ]]);
}
add_action('transition_post_status', 'blueprint_tools_sync_post', 10, 3);

// {!# snippet: register_public_search_rest_route #!}
function blueprint_tools_register_search_route(): void
{
    register_rest_route('blueprint/v1', '/search', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'blueprint_tools_search',
        'permission_callback' => '__return_true',
        'args' => [
            'q' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => static fn (string $value): bool => $value !== '',
            ],
        ],
    ]);
}
add_action('rest_api_init', 'blueprint_tools_register_search_route');

// {!# snippet: query_meilisearch_from_rest #!}
function blueprint_tools_search(WP_REST_Request $request): WP_REST_Response|WP_Error
{
    $client = blueprint_tools_meilisearch_client();

    if ($client === null) {
        return new WP_Error(
            'blueprint_search_unavailable',
            __('Search is temporarily unavailable.', 'blueprint-tools'),
            ['status' => 503],
        );
    }

    $result = $client
        ->index('wordpress_content')
        ->search((string) $request->get_param('q'), ['limit' => 12]);

    return new WP_REST_Response([
        'hits' => $result->getHits(),
        'estimated_total_hits' => $result->getEstimatedTotalHits(),
    ]);
}
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $pluginIncludesFolder,
            user: $user,
            attributes: [
                'title' => 'WordPress Meilisearch Integration',
                'filename' => 'meilisearch.php',
                'language' => 'php',
                'description' => 'Four directly searchable snippets for the client, post indexing, REST registration, and search response.',
                'content' => $meilisearchPhp,
                'position' => 0,
            ],
            variationName: 'Server-side indexing and search',
            tags: array_merge($wordpressTags, [
                $tags['meilisearch'],
                $tags['rest-api'],
            ]),
        );

        $composeYaml = <<<'YAML'
name: wordpress-block-theme-blueprint

# {!# snippet: wordpress_docker_services #!}
services:
  database:
    image: mariadb:11
    restart: unless-stopped
    environment:
      MARIADB_DATABASE: ${WORDPRESS_DB_NAME}
      MARIADB_USER: ${WORDPRESS_DB_USER}
      MARIADB_PASSWORD: ${WORDPRESS_DB_PASSWORD}
      MARIADB_ROOT_PASSWORD: ${WORDPRESS_DB_ROOT_PASSWORD}
    volumes:
      - database-data:/var/lib/mysql

# {!# snippet: install_companion_plugin_dependencies #!}
  composer:
    image: composer:2
    working_dir: /app
    command: install --no-interaction --prefer-dist
    volumes:
      - ../companion-plugin:/app

  wordpress:
    build:
      context: .
      dockerfile: Dockerfile
    restart: unless-stopped
    depends_on:
      composer:
        condition: service_completed_successfully
      database:
        condition: service_started
      meilisearch:
        condition: service_started
    ports:
      - "${WORDPRESS_PORT:-8080}:80"
    environment:
      WORDPRESS_DB_HOST: database:3306
      WORDPRESS_DB_NAME: ${WORDPRESS_DB_NAME}
      WORDPRESS_DB_USER: ${WORDPRESS_DB_USER}
      WORDPRESS_DB_PASSWORD: ${WORDPRESS_DB_PASSWORD}
      WORDPRESS_DEBUG: ${WORDPRESS_DEBUG:-1}
      MEILI_MASTER_KEY: ${MEILI_MASTER_KEY}
      WORDPRESS_CONFIG_EXTRA: |
        define('MEILISEARCH_HOST', 'http://meilisearch:7700');
        define('MEILISEARCH_API_KEY', getenv('MEILI_MASTER_KEY'));
    volumes:
      - wordpress-data:/var/www/html
      - ../block-theme:/var/www/html/wp-content/themes/blueprint
      - ../companion-plugin:/var/www/html/wp-content/plugins/blueprint-tools

# {!# snippet: meilisearch_docker_service #!}
  meilisearch:
    image: getmeili/meilisearch:v1
    restart: unless-stopped
    ports:
      - "${MEILISEARCH_PORT:-7700}:7700"
    environment:
      MEILI_ENV: development
      MEILI_MASTER_KEY: ${MEILI_MASTER_KEY}
    volumes:
      - meilisearch-data:/meili_data

volumes:
  database-data:
  wordpress-data:
  meilisearch-data:
YAML;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $dockerFolder,
            user: $user,
            attributes: [
                'title' => 'WordPress and Meilisearch Docker Stack',
                'filename' => 'compose.yaml',
                'language' => 'yaml',
                'description' => 'Searchable Docker Compose sections for WordPress, MariaDB, and persistent Meilisearch services.',
                'content' => $composeYaml,
                'position' => 0,
            ],
            variationName: 'Local development stack',
            tags: array_merge($wordpressTags, [
                $tags['docker'],
                $tags['meilisearch'],
            ]),
        );

        $dockerfile = <<<'DOCKERFILE'
FROM wordpress:php8.3-apache

RUN apt-get update \
    && apt-get install --yes --no-install-recommends git libzip-dev unzip \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
DOCKERFILE;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $dockerFolder,
            user: $user,
            attributes: [
                'title' => 'WordPress Development Image',
                'filename' => 'Dockerfile',
                'language' => 'dockerfile',
                'description' => 'A small official WordPress image extension with Composer and zip support for plugin dependencies.',
                'content' => $dockerfile,
                'position' => 1,
            ],
            variationName: 'Composer-enabled WordPress image',
            tags: array_merge($wordpressTags, [$tags['docker']]),
        );

        $dockerEnvironment = <<<'ENV'
# {!# snippet: wordpress_database_environment #!}
WORDPRESS_PORT=8080
WORDPRESS_DB_NAME=wordpress
WORDPRESS_DB_USER=wordpress
WORDPRESS_DB_PASSWORD=change-me
WORDPRESS_DB_ROOT_PASSWORD=change-root-password
WORDPRESS_DEBUG=1

# {!# snippet: meilisearch_environment #!}
MEILISEARCH_PORT=7700
MEILI_MASTER_KEY=replace-with-a-long-random-development-key
ENV;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $dockerFolder,
            user: $user,
            attributes: [
                'title' => 'Docker Environment Template',
                'filename' => '.env.example',
                'language' => 'dotenv',
                'description' => 'Separate searchable WordPress database and Meilisearch development settings with placeholder secrets.',
                'content' => $dockerEnvironment,
                'position' => 2,
            ],
            variationName: 'Safe development placeholders',
            tags: array_merge($wordpressTags, [
                $tags['docker'],
                $tags['meilisearch'],
            ]),
        );

        $figmaMcpJson = <<<'JSON'
{
    "servers": {
        "figma": {
            "type": "http",
            "url": "https://mcp.figma.com/mcp"
        }
    }
}
JSON;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $vscodeFolder,
            user: $user,
            attributes: [
                'title' => 'Figma MCP Server',
                'filename' => 'mcp.json',
                'language' => 'json',
                'description' => 'A project-scoped VS Code connection to the official remote Figma MCP endpoint.',
                'content' => $figmaMcpJson,
                'position' => 0,
            ],
            variationName: 'Remote Figma MCP server',
            tags: array_merge($wordpressTags, [
                $tags['figma-mcp'],
                $tags['design-tokens'],
            ]),
        );

        $figmaTokens = <<<'CSS'
/* {!# snippet: figma_primitive_tokens #!} */
:root {
    --figma-color-slate-950: #172033;
    --figma-color-slate-100: #e7ecf3;
    --figma-color-blue-600: #2563eb;
    --figma-space-2: 0.5rem;
    --figma-space-4: 1rem;
    --figma-space-8: 2rem;
    --figma-radius-control: 0.375rem;
}

/* {!# snippet: map_figma_tokens_to_wordpress_presets #!} */
:root {
    --wp--preset--color--ink: var(--figma-color-slate-950);
    --wp--preset--color--canvas: var(--figma-color-slate-100);
    --wp--preset--color--accent: var(--figma-color-blue-600);
}

/* {!# snippet: editor_frontend_token_parity #!} */
.editor-styles-wrapper,
body {
    color: var(--wp--preset--color--ink);
    background: var(--wp--preset--color--canvas);
}
CSS;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $stylesFolder,
            user: $user,
            attributes: [
                'title' => 'Figma to WordPress Design Tokens',
                'filename' => 'figma-tokens.css',
                'language' => 'css',
                'description' => 'Searchable primitives, theme.json mappings, and editor/frontend parity sections for an MCP-assisted styling workflow.',
                'content' => $figmaTokens,
                'position' => 2,
            ],
            variationName: 'Figma token bridge',
            tags: array_merge($wordpressTags, [
                $tags['figma-mcp'],
                $tags['design-tokens'],
                $tags['theme-json'],
            ]),
        );

        $phpcsXml = <<<'XML'
<?xml version="1.0"?>
<ruleset name="WordPress Block Theme Blueprint">
    <description>WordPress Coding Standards for the Blueprint theme and companion plugin.</description>

    <file>../block-theme</file>
    <file>../companion-plugin</file>

    <exclude-pattern>*/vendor/*</exclude-pattern>

    <rule ref="WordPress-Extra"/>
    <rule ref="WordPress-Docs"/>

    <config name="text_domain" value="blueprint,blueprint-tools"/>
    <config name="minimum_supported_wp_version" value="6.6"/>

    <rule ref="WordPress.NamingConventions.PrefixAllGlobals">
        <properties>
            <property name="prefixes" type="array" value="blueprint,BLUEPRINT"/>
        </properties>
    </rule>
</ruleset>
XML;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $toolingFolder,
            user: $user,
            attributes: [
                'title' => 'WordPress Coding Standards Ruleset',
                'filename' => 'phpcs.xml.dist',
                'language' => 'xml',
                'description' => 'A PHPCS ruleset covering the theme and plugin with text-domain, WordPress-version, and prefix checks.',
                'content' => $phpcsXml,
                'position' => 1,
            ],
            variationName: 'Theme and plugin WPCS rules',
            tags: array_merge($wordpressTags, [$tags['wordpress-standards']]),
        );

        $this->seedWordPressCustomDirectoryIntegration(
            project: $project,
            folder: $directoryIntegrationFolder,
            patternsFolder: $patternsFolder,
            user: $user,
            tags: $tags,
        );
        $this->seedWordPressFacetWpIntegration(
            project: $project,
            folder: $facetWpIntegrationFolder,
            user: $user,
            tags: $tags,
        );
    }

    /**
     * @param  array<string, Tag>  $tags
     * @param  list<Tag>  $wordpressTags
     */
    private function seedWordPressGutenbergClassAtlas(
        Project $project,
        Folder $stylesFolder,
        User $user,
        array $tags,
        array $wordpressTags,
    ): void {
        $atlasFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $stylesFolder->id, 'name' => 'gutenberg-class-atlas'],
            ['position' => 0],
        );
        $atlasTags = array_merge($wordpressTags, [
            $tags['wordpress-classes'],
            $tags['accessibility'],
        ]);
        $blockFiles = $this->wordpressGutenbergBlockClassFiles();
        $files = [
            [
                'title' => 'Gutenberg Class Atlas Scope and Version',
                'filename' => 'README.md',
                'language' => 'markdown',
                'description' => 'Defines the WordPress 7.0.2 frontend-only scope, coverage rules, exclusions, and update sources for the class atlas.',
                'content' => $this->wordpressGutenbergClassAtlasReadme(),
                'variation' => 'WordPress 7.0.2 atlas scope',
            ],
            [
                'title' => 'Core Block Class Manifest',
                'filename' => 'core-block-class-manifest.json',
                'language' => 'json',
                'description' => 'Machine-readable coverage for all 109 Core block types bundled with WordPress 7.0.2, including deliberate wrapper exceptions.',
                'content' => $this->wordpressGutenbergClassManifest($blockFiles),
                'variation' => 'WordPress 7.0.2 block manifest',
            ],
            [
                'title' => 'Shared Gutenberg Block Supports',
                'filename' => '_shared.scss',
                'language' => 'scss',
                'description' => 'Shared alignment, colour, typography, layout, element, accessibility, style, and generated class families used across Core blocks.',
                'content' => $this->wordpressGutenbergSharedClasses(),
                'variation' => 'Shared frontend class families',
            ],
            ...array_map(
                fn (array $file): array => [
                    ...$file,
                    'content' => $this->renderWordPressGutenbergBlockClassFile(
                        heading: $file['heading'],
                        blocks: $file['blocks'],
                    ),
                ],
                $blockFiles,
            ),
            [
                'title' => 'Gutenberg Preview and Experimental Classes',
                'filename' => '_gutenberg-preview.scss',
                'language' => 'scss',
                'description' => 'Classes for evolving block-library types documented upstream but not registered as stable Core blocks in WordPress 7.0.2.',
                'content' => $this->wordpressGutenbergPreviewClasses(),
                'variation' => 'Preview classes kept outside stable Core',
            ],
        ];

        foreach ($files as $position => $file) {
            $this->createSingleVariationSnippet(
                project: $project,
                folder: $atlasFolder,
                user: $user,
                attributes: [
                    'title' => $file['title'],
                    'filename' => $file['filename'],
                    'language' => $file['language'],
                    'description' => $file['description'],
                    'content' => $file['content'],
                    'position' => $position,
                ],
                variationName: $file['variation'],
                tags: $atlasTags,
            );
        }
    }

    /**
     * @param  list<array{
     *     filename: string,
     *     title: string,
     *     language: string,
     *     description: string,
     *     heading: string,
     *     variation: string,
     *     blocks: array<string, array{selectors: list<string>, status?: string, note?: string}>
     * }>  $files
     */
    private function wordpressGutenbergClassManifest(array $files): string
    {
        $blocks = [];

        foreach ($files as $file) {
            foreach ($file['blocks'] as $name => $definition) {
                $blocks[] = [
                    'name' => $name,
                    'referenceFile' => $file['filename'],
                    'status' => $definition['status'] ?? 'canonical-wrapper',
                    'selectors' => $definition['selectors'],
                    'note' => $definition['note'] ?? null,
                ];
            }
        }

        return json_encode([
            'schemaVersion' => 1,
            'wordpressVersion' => '7.0.2',
            'scope' => 'Frontend classes for stable Core blocks registered by WordPress 7.0.2.',
            'registeredBlockCount' => count($blocks),
            'blocks' => $blocks,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @param  array<string, array{selectors: list<string>, status?: string, note?: string}>  $blocks
     */
    private function renderWordPressGutenbergBlockClassFile(string $heading, array $blocks): string
    {
        $sections = [
            '/*',
            " * {$heading}",
            ' *',
            ' * WordPress 7.0.2 frontend reference. Empty rules are intentional:',
            ' * this file documents selectors and must not be shipped as theme CSS.',
            ' */',
            '',
        ];

        foreach ($blocks as $name => $definition) {
            $sectionName = str_replace(['core/', '-'], ['core_', '_'], $name).'_classes';
            $status = $definition['status'] ?? 'canonical-wrapper';
            $sections[] = "/* {!# snippet: {$sectionName} #!} */";
            $sections[] = "/* {$name} | {$status} */";

            if (isset($definition['note'])) {
                $sections[] = '/* '.$definition['note'].' */';
            }

            if ($definition['selectors'] === []) {
                $sections[] = '/* No block-owned frontend selector. */';
            } else {
                $sections[] = implode(",\n", $definition['selectors']).' {}';
            }

            $sections[] = '';
        }

        return rtrim(implode("\n", $sections))."\n";
    }

    private function wordpressGutenbergClassAtlasReadme(): string
    {
        return <<<'MARKDOWN'
# Gutenberg frontend class atlas

This atlas is a versioned snapshot of the **109 Core block types registered by WordPress 7.0.2**. It covers canonical wrappers, block-owned descendants, shipped compatibility selectors, attribute-driven states, and shared Block Supports class patterns.

## What “complete” means here

- Included: frontend selectors from Core `block.json`, unminified `style.css` and `theme.css`, dynamic PHP render paths, static save markup, and Block Supports.
- Included: explicit records for blocks which deliberately have no wrapper or delegate their output.
- Separated: preview, experimental, deprecated-FSE, and post-7.0 block-library types in `_gutenberg-preview.scss`.
- Excluded: editor/admin implementation classes, arbitrary Additional CSS Classes, theme/plugin classes, runtime hashes, and literal expansion of theme-defined preset slugs.

Class output changes with block attributes, registered styles, `theme.json`, plugins, and user choices. Treat literal selectors as a 7.0.2 reference and brace-form names such as `has-{slug}-color` as patterns, not selectors to paste directly.

## Files

- `core-block-class-manifest.json` proves coverage and records wrapper status for every stable Core block.
- `_shared.scss` contains Block Supports and cross-block class families.
- The remaining stable SCSS files group each block by purpose: text, media, design, widgets, navigation, comments, posts, queries/terms, site structure, and embeds.
- `_gutenberg-preview.scss` is intentionally outside the stable manifest.

## Updating the atlas

Re-check the matching WordPress release under `wp-includes/blocks`, `wp-includes/block-supports`, and the frontend block-library CSS/JavaScript. Cross-check the official Core Blocks Reference: https://developer.wordpress.org/block-editor/reference-guides/core-blocks/

Do not import these empty reference rules into production stylesheets. They are searchable documentation snippets.
MARKDOWN;
    }

    /**
     * @return list<array{
     *     filename: string,
     *     title: string,
     *     language: string,
     *     description: string,
     *     heading: string,
     *     variation: string,
     *     blocks: array<string, array{selectors: list<string>, status?: string, note?: string}>
     * }>
     */
    private function wordpressGutenbergBlockClassFiles(): array
    {
        return [
            [
                'filename' => '_text.scss',
                'title' => 'Core Text Block Classes',
                'language' => 'scss',
                'description' => 'Frontend wrapper, descendant, state, compatibility, and deliberate no-wrapper selectors for every stable Core text block.',
                'heading' => 'Core text block classes',
                'variation' => 'WordPress 7.0.2 text blocks',
                'blocks' => [
                    'core/code' => ['selectors' => ['.wp-block-code']],
                    'core/details' => ['selectors' => ['.wp-block-details']],
                    'core/footnotes' => [
                        'selectors' => ['.wp-block-footnotes', '.fn', '.fnref'],
                        'note' => 'Footnote references and return links use generated IDs as well as these classes.',
                    ],
                    'core/freeform' => [
                        'selectors' => [],
                        'status' => 'delegated-raw-output',
                        'note' => 'Classic content is not wrapped in a Core-owned frontend class.',
                    ],
                    'core/heading' => ['selectors' => ['.wp-block-heading']],
                    'core/list' => ['selectors' => ['.wp-block-list']],
                    'core/list-item' => [
                        'selectors' => ['.wp-block-list > li'],
                        'status' => 'shared-parent-wrapper',
                        'note' => 'List Item has no class of its own; target list children through the parent.',
                    ],
                    'core/math' => ['selectors' => ['.wp-block-math']],
                    'core/missing' => [
                        'selectors' => [],
                        'status' => 'preserved-unknown-output',
                        'note' => 'Unsupported blocks preserve the unknown block markup instead of inventing a wrapper.',
                    ],
                    'core/paragraph' => [
                        'selectors' => [
                            '.wp-block-paragraph',
                            '.wp-block-paragraph.has-drop-cap',
                            '.wp-block-paragraph.is-small-text',
                            '.wp-block-paragraph.is-regular-text',
                            '.wp-block-paragraph.is-large-text',
                            '.wp-block-paragraph.is-larger-text',
                        ],
                        'status' => 'manual-core-wrapper',
                        'note' => 'WordPress 7.0 adds wp-block-paragraph server-side even though className support is disabled.',
                    ],
                    'core/preformatted' => ['selectors' => ['.wp-block-preformatted']],
                    'core/pullquote' => [
                        'selectors' => [
                            '.wp-block-pullquote',
                            '.wp-block-pullquote__citation',
                            '.wp-block-pullquote.is-style-solid-color',
                        ],
                    ],
                    'core/quote' => [
                        'selectors' => [
                            '.wp-block-quote',
                            '.wp-block-quote__citation',
                            '.wp-block-quote.is-large',
                            '.wp-block-quote.is-style-large',
                            '.wp-block-quote.is-style-plain',
                        ],
                    ],
                    'core/table' => [
                        'selectors' => [
                            '.wp-block-table',
                            '.wp-block-table > table',
                            '.wp-block-table.has-fixed-layout',
                            '.wp-block-table.is-style-stripes',
                        ],
                    ],
                    'core/verse' => ['selectors' => ['.wp-block-verse']],
                ],
            ],
            [
                'filename' => '_media.scss',
                'title' => 'Core Media Block Classes',
                'language' => 'scss',
                'description' => 'All stable Core media wrappers plus Cover, Gallery, Image lightbox, File, Icon, and Media & Text descendants and modifiers.',
                'heading' => 'Core media block classes',
                'variation' => 'WordPress 7.0.2 media blocks',
                'blocks' => [
                    'core/audio' => [
                        'selectors' => ['.wp-block-audio', '.wp-block-audio .wp-element-caption'],
                    ],
                    'core/cover' => [
                        'selectors' => [
                            '.wp-block-cover',
                            '.wp-block-cover-image',
                            '.wp-block-cover-text',
                            '.wp-block-cover-image-text',
                            '.wp-block-cover__background',
                            '.wp-block-cover__gradient-background',
                            '.wp-block-cover__image-background',
                            '.wp-block-cover__video-background',
                            '.wp-block-cover__embed-background',
                            '.wp-block-cover__inner-container',
                            '.wp-block-cover.has-background-dim',
                            '.wp-block-cover.has-background-dim-0',
                            '.wp-block-cover.has-background-dim-10',
                            '.wp-block-cover.has-background-dim-20',
                            '.wp-block-cover.has-background-dim-30',
                            '.wp-block-cover.has-background-dim-40',
                            '.wp-block-cover.has-background-dim-50',
                            '.wp-block-cover.has-background-dim-60',
                            '.wp-block-cover.has-background-dim-70',
                            '.wp-block-cover.has-background-dim-80',
                            '.wp-block-cover.has-background-dim-90',
                            '.wp-block-cover.has-background-dim-100',
                            '.wp-block-cover.has-background-gradient',
                            '.wp-block-cover.has-custom-content-position',
                            '.wp-block-cover.has-parallax',
                            '.wp-block-cover.is-repeated',
                            '.wp-block-cover.is-light',
                            '.wp-block-cover.is-position-top-left',
                            '.wp-block-cover.is-position-top-center',
                            '.wp-block-cover.is-position-top-right',
                            '.wp-block-cover.is-position-center-left',
                            '.wp-block-cover.is-position-center-center',
                            '.wp-block-cover.is-position-center-right',
                            '.wp-block-cover.is-position-bottom-left',
                            '.wp-block-cover.is-position-bottom-center',
                            '.wp-block-cover.is-position-bottom-right',
                        ],
                    ],
                    'core/file' => [
                        'selectors' => [
                            '.wp-block-file',
                            '.wp-block-file__button',
                            '.wp-block-file__embed',
                            '.wp-block-file-view',
                            '.wp-block-file .wp-element-button',
                        ],
                    ],
                    'core/gallery' => [
                        'selectors' => [
                            '.wp-block-gallery',
                            '.wp-block-gallery.has-nested-images',
                            '.wp-block-gallery .wp-block-image',
                            '.wp-block-gallery.is-cropped',
                            '.wp-block-gallery.columns-default',
                            '.wp-block-gallery.columns-1',
                            '.wp-block-gallery.columns-2',
                            '.wp-block-gallery.columns-3',
                            '.wp-block-gallery.columns-4',
                            '.wp-block-gallery.columns-5',
                            '.wp-block-gallery.columns-6',
                            '.wp-block-gallery.columns-7',
                            '.wp-block-gallery.columns-8',
                            '.blocks-gallery-grid',
                            '.blocks-gallery-item',
                            '.blocks-gallery-image',
                            '.blocks-gallery-caption',
                        ],
                        'note' => 'A gallery may also receive the runtime instance family wp-block-gallery-{id}.',
                    ],
                    'core/icon' => ['selectors' => ['.wp-block-icon', '.wp-block-icon svg']],
                    'core/image' => [
                        'selectors' => [
                            '.wp-block-image',
                            '.wp-block-image.has-custom-border',
                            '.wp-block-image.is-style-rounded',
                            '.wp-block-image.is-style-circle-mask',
                            '.lightbox-image-container',
                            '.wp-lightbox-container',
                            '.wp-lightbox-overlay',
                            '.wp-lightbox-close-button',
                            '.wp-lightbox-close-icon',
                            '.wp-lightbox-close-text',
                            '.wp-lightbox-navigation-button-next',
                            '.wp-lightbox-navigation-button-prev',
                            '.wp-lightbox-navigation-icon',
                            '.wp-lightbox-navigation-text',
                            '.wp-lightbox-overlay.active',
                            '.wp-lightbox-overlay.hide',
                            '.wp-lightbox-overlay.show',
                            '.wp-lightbox-overlay.show-closing-animation',
                            '.wp-lightbox-overlay.zoom',
                            '.wp-lightbox-overlay.scrim',
                        ],
                    ],
                    'core/media-text' => [
                        'selectors' => [
                            '.wp-block-media-text',
                            '.wp-block-media-text__media',
                            '.wp-block-media-text__content',
                            '.wp-block-media-text.has-media-on-the-right',
                            '.wp-block-media-text.is-image-fill',
                            '.wp-block-media-text.is-image-fill-element',
                            '.wp-block-media-text.is-stacked-on-mobile',
                            '.wp-block-media-text.is-vertically-aligned-top',
                            '.wp-block-media-text.is-vertically-aligned-center',
                            '.wp-block-media-text.is-vertically-aligned-bottom',
                        ],
                    ],
                    'core/video' => [
                        'selectors' => ['.wp-block-video', '.wp-block-video .wp-element-caption'],
                    ],
                ],
            ],
            [
                'filename' => '_design.scss',
                'title' => 'Core Design Block Classes',
                'language' => 'scss',
                'description' => 'Stable Core design classes for accordions, buttons, columns, groups, separators, spacers, and marker-style blocks.',
                'heading' => 'Core design block classes',
                'variation' => 'WordPress 7.0.2 design blocks',
                'blocks' => [
                    'core/accordion' => ['selectors' => ['.wp-block-accordion']],
                    'core/accordion-heading' => [
                        'selectors' => [
                            '.wp-block-accordion-heading',
                            '.wp-block-accordion-heading__toggle',
                            '.wp-block-accordion-heading__toggle-title',
                            '.wp-block-accordion-heading__toggle-icon',
                        ],
                    ],
                    'core/accordion-item' => [
                        'selectors' => ['.wp-block-accordion-item', '.wp-block-accordion-item.is-open'],
                    ],
                    'core/accordion-panel' => ['selectors' => ['.wp-block-accordion-panel']],
                    'core/button' => [
                        'selectors' => [
                            '.wp-block-button',
                            '.wp-block-button__link',
                            '.wp-block-button__width-25',
                            '.wp-block-button__width-50',
                            '.wp-block-button__width-75',
                            '.wp-block-button__width-100',
                            '.wp-block-button.has-custom-width',
                            '.wp-block-button.has-custom-font-size',
                            '.wp-block-button.is-style-outline',
                            '.wp-block-button .wp-element-button',
                        ],
                    ],
                    'core/buttons' => [
                        'selectors' => [
                            '.wp-block-buttons',
                            '.wp-block-buttons.is-content-justification-left',
                            '.wp-block-buttons.is-content-justification-center',
                            '.wp-block-buttons.is-content-justification-right',
                            '.wp-block-buttons.is-content-justification-space-between',
                            '.wp-block-buttons.is-vertical',
                        ],
                    ],
                    'core/column' => [
                        'selectors' => [
                            '.wp-block-column',
                            '.wp-block-column.is-vertically-aligned-top',
                            '.wp-block-column.is-vertically-aligned-center',
                            '.wp-block-column.is-vertically-aligned-bottom',
                            '.wp-block-column.is-vertically-aligned-stretch',
                        ],
                    ],
                    'core/columns' => [
                        'selectors' => [
                            '.wp-block-columns',
                            '.wp-block-columns.are-vertically-aligned-top',
                            '.wp-block-columns.are-vertically-aligned-center',
                            '.wp-block-columns.are-vertically-aligned-bottom',
                            '.wp-block-columns.is-not-stacked-on-mobile',
                        ],
                    ],
                    'core/group' => [
                        'selectors' => [
                            '.wp-block-group',
                            '.wp-block-group__inner-container',
                            '.wp-block-group-is-layout-flow',
                            '.wp-block-group-is-layout-constrained',
                            '.wp-block-group-is-layout-flex',
                            '.wp-block-group-is-layout-grid',
                        ],
                    ],
                    'core/more' => [
                        'selectors' => ['.more-link'],
                        'status' => 'serialization-marker',
                        'note' => 'The block serializes a more marker; the content renderer owns the more-link class.',
                    ],
                    'core/nextpage' => [
                        'selectors' => ['.post-page-numbers', '.page-numbers'],
                        'status' => 'serialization-marker',
                        'note' => 'Page Break serializes a nextpage marker; pagination functions create the frontend links.',
                    ],
                    'core/separator' => [
                        'selectors' => [
                            '.wp-block-separator',
                            '.wp-block-separator.has-alpha-channel-opacity',
                            '.wp-block-separator.has-css-opacity',
                            '.wp-block-separator.is-style-wide',
                            '.wp-block-separator.is-style-dots',
                        ],
                    ],
                    'core/spacer' => ['selectors' => ['.wp-block-spacer']],
                    'core/text-columns' => [
                        'selectors' => [
                            '.wp-block-text-columns',
                            '.wp-block-text-columns .wp-block-column',
                            '.wp-block-text-columns.columns-2',
                            '.wp-block-text-columns.columns-3',
                            '.wp-block-text-columns.columns-4',
                        ],
                        'status' => 'deprecated',
                    ],
                ],
            ],
            [
                'filename' => '_widgets.scss',
                'title' => 'Core Widget Block Classes',
                'language' => 'scss',
                'description' => 'Stable Core widget classes, including list states, search variants, Social Icons service selectors, and delegated-output exceptions.',
                'heading' => 'Core widget block classes',
                'variation' => 'WordPress 7.0.2 widget blocks',
                'blocks' => [
                    'core/archives' => [
                        'selectors' => [
                            '.wp-block-archives',
                            '.wp-block-archives-list',
                            '.wp-block-archives-dropdown',
                            '.wp-block-archives__label',
                        ],
                    ],
                    'core/calendar' => [
                        'selectors' => [
                            '.wp-block-calendar',
                            '.wp-calendar-table',
                            '.wp-calendar-nav',
                            '.wp-calendar-nav-prev',
                            '.wp-calendar-nav-next',
                        ],
                    ],
                    'core/categories' => [
                        'selectors' => [
                            '.wp-block-categories',
                            '.wp-block-categories-list',
                            '.wp-block-categories-dropdown',
                            '.wp-block-categories__label',
                            '.cat-item',
                            '.current-cat',
                            '.current-cat-parent',
                            '.current-cat-ancestor',
                            '.children',
                        ],
                        'note' => 'Dynamic families also include wp-block-categories-taxonomy-{taxonomy} and cat-item-{id}.',
                    ],
                    'core/html' => [
                        'selectors' => [],
                        'status' => 'delegated-raw-output',
                        'note' => 'Custom HTML saves exactly the user-authored markup.',
                    ],
                    'core/latest-comments' => [
                        'selectors' => [
                            '.wp-block-latest-comments',
                            '.wp-block-latest-comments__comment',
                            '.wp-block-latest-comments__comment-avatar',
                            '.wp-block-latest-comments__comment-meta',
                            '.wp-block-latest-comments__comment-author',
                            '.wp-block-latest-comments__comment-link',
                            '.wp-block-latest-comments__comment-date',
                            '.wp-block-latest-comments__comment-excerpt',
                            '.wp-block-latest-comments.has-avatars',
                            '.wp-block-latest-comments.has-dates',
                            '.wp-block-latest-comments.has-excerpts',
                            '.wp-block-latest-comments.no-comments',
                        ],
                    ],
                    'core/latest-posts' => [
                        'selectors' => [
                            '.wp-block-latest-posts',
                            '.wp-block-latest-posts__list',
                            '.wp-block-latest-posts__featured-image',
                            '.wp-block-latest-posts__post-title',
                            '.wp-block-latest-posts__post-author',
                            '.wp-block-latest-posts__post-date',
                            '.wp-block-latest-posts__post-excerpt',
                            '.wp-block-latest-posts__post-full-content',
                            '.wp-block-latest-posts__read-more',
                            '.wp-block-latest-posts.has-author',
                            '.wp-block-latest-posts.has-dates',
                            '.wp-block-latest-posts.is-grid',
                            '.wp-block-latest-posts.columns-2',
                            '.wp-block-latest-posts.columns-3',
                            '.wp-block-latest-posts.columns-4',
                            '.wp-block-latest-posts.columns-5',
                            '.wp-block-latest-posts.columns-6',
                        ],
                    ],
                    'core/legacy-widget' => [
                        'selectors' => [],
                        'status' => 'delegated-widget-output',
                        'note' => 'The selected legacy widget controls its frontend classes.',
                    ],
                    'core/rss' => [
                        'selectors' => [
                            '.wp-block-rss',
                            '.wp-block-rss__item',
                            '.wp-block-rss__item-title',
                            '.wp-block-rss__item-author',
                            '.wp-block-rss__item-publish-date',
                            '.wp-block-rss__item-excerpt',
                            '.wp-block-rss.has-authors',
                            '.wp-block-rss.has-dates',
                            '.wp-block-rss.has-excerpts',
                            '.wp-block-rss.is-grid',
                            '.wp-block-rss.columns-2',
                            '.wp-block-rss.columns-3',
                            '.wp-block-rss.columns-4',
                            '.wp-block-rss.columns-5',
                            '.wp-block-rss.columns-6',
                        ],
                    ],
                    'core/search' => [
                        'selectors' => [
                            '.wp-block-search',
                            '.wp-block-search__label',
                            '.wp-block-search__inside-wrapper',
                            '.wp-block-search__input',
                            '.wp-block-search__button',
                            '.wp-block-search__button-inside',
                            '.wp-block-search__button-outside',
                            '.wp-block-search__button-only',
                            '.wp-block-search__no-button',
                            '.wp-block-search__icon-button',
                            '.wp-block-search__text-button',
                            '.wp-block-search__searchfield-hidden',
                            '.wp-block-search__width-25',
                            '.wp-block-search__width-50',
                            '.wp-block-search__width-75',
                            '.wp-block-search__width-100',
                            '.wp-block-search.has-icon',
                        ],
                    ],
                    'core/shortcode' => [
                        'selectors' => [],
                        'status' => 'delegated-plugin-output',
                        'note' => 'The shortcode callback controls the rendered markup and classes.',
                    ],
                    'core/social-link' => [
                        'selectors' => [
                            '.wp-block-social-link',
                            '.wp-block-social-link-anchor',
                            '.wp-block-social-link-label',
                            '.wp-social-link',
                        ],
                        'note' => 'Each item also receives wp-social-link-{service}; the concrete stable service list is recorded under Social Links.',
                    ],
                    'core/social-links' => [
                        'selectors' => [
                            '.wp-block-social-links',
                            '.wp-social-link-amazon',
                            '.wp-social-link-bandcamp',
                            '.wp-social-link-behance',
                            '.wp-social-link-bluesky',
                            '.wp-social-link-codepen',
                            '.wp-social-link-deviantart',
                            '.wp-social-link-discord',
                            '.wp-social-link-dribbble',
                            '.wp-social-link-dropbox',
                            '.wp-social-link-etsy',
                            '.wp-social-link-facebook',
                            '.wp-social-link-fivehundredpx',
                            '.wp-social-link-flickr',
                            '.wp-social-link-foursquare',
                            '.wp-social-link-github',
                            '.wp-social-link-goodreads',
                            '.wp-social-link-google',
                            '.wp-social-link-gravatar',
                            '.wp-social-link-instagram',
                            '.wp-social-link-lastfm',
                            '.wp-social-link-linkedin',
                            '.wp-social-link-mastodon',
                            '.wp-social-link-medium',
                            '.wp-social-link-meetup',
                            '.wp-social-link-patreon',
                            '.wp-social-link-pinterest',
                            '.wp-social-link-pocket',
                            '.wp-social-link-reddit',
                            '.wp-social-link-skype',
                            '.wp-social-link-snapchat',
                            '.wp-social-link-soundcloud',
                            '.wp-social-link-spotify',
                            '.wp-social-link-telegram',
                            '.wp-social-link-threads',
                            '.wp-social-link-tiktok',
                            '.wp-social-link-tumblr',
                            '.wp-social-link-twitch',
                            '.wp-social-link-twitter',
                            '.wp-social-link-vimeo',
                            '.wp-social-link-vk',
                            '.wp-social-link-whatsapp',
                            '.wp-social-link-wordpress',
                            '.wp-social-link-x',
                            '.wp-social-link-yelp',
                            '.wp-social-link-youtube',
                            '.wp-block-social-links.has-small-icon-size',
                            '.wp-block-social-links.has-normal-icon-size',
                            '.wp-block-social-links.has-large-icon-size',
                            '.wp-block-social-links.has-huge-icon-size',
                            '.wp-block-social-links.has-icon-color',
                            '.wp-block-social-links.has-icon-background-color',
                            '.wp-block-social-links.has-visible-labels',
                            '.wp-block-social-links.is-style-logos-only',
                            '.wp-block-social-links.is-style-pill-shape',
                        ],
                    ],
                    'core/tag-cloud' => [
                        'selectors' => [
                            '.wp-block-tag-cloud',
                            '.wp-block-tag-cloud.is-style-outline',
                            '.tag-cloud-link',
                        ],
                        'note' => 'Dynamic link families include tag-link-{termId} and tag-link-position-{position}.',
                    ],
                    'core/widget-group' => [
                        'selectors' => ['.wp-widget-group__inner-blocks', '.widget-title'],
                        'status' => 'shared-wrapper',
                        'note' => 'Widget Group does not emit the normal wp-block-widget-group root.',
                    ],
                ],
            ],
            [
                'filename' => '_navigation.scss',
                'title' => 'Core Navigation Block Classes',
                'language' => 'scss',
                'description' => 'Navigation, link, submenu, overlay, Home Link, Page List, responsive container, and current-item frontend selectors.',
                'heading' => 'Core navigation block classes',
                'variation' => 'WordPress 7.0.2 navigation blocks',
                'blocks' => [
                    'core/home-link' => [
                        'selectors' => [
                            '.wp-block-home-link',
                            '.wp-block-home-link__content',
                            '.wp-block-home-link.current-menu-item',
                        ],
                    ],
                    'core/navigation-link' => [
                        'selectors' => [
                            '.wp-block-navigation-link',
                            '.wp-block-navigation-item',
                            '.wp-block-navigation-item__content',
                            '.wp-block-navigation-item__label',
                            '.wp-block-navigation-item__description',
                        ],
                    ],
                    'core/navigation-overlay-close' => [
                        'selectors' => [
                            '.wp-block-navigation-overlay-close',
                            '.wp-block-navigation-overlay-close__text',
                        ],
                    ],
                    'core/navigation-submenu' => [
                        'selectors' => [
                            '.wp-block-navigation-submenu',
                            '.wp-block-navigation-submenu__toggle',
                            '.wp-block-navigation__submenu-container',
                            '.wp-block-navigation__submenu-icon',
                        ],
                    ],
                    'core/page-list' => [
                        'selectors' => [
                            '.wp-block-page-list',
                            '.wp-block-pages-list__item',
                            '.wp-block-pages-list__item__link',
                            '.wp-block-page-list__submenu-icon',
                            '.wp-block-page-list .current-menu-item',
                            '.wp-block-page-list .current-menu-ancestor',
                            '.wp-block-page-list .has-child',
                            '.wp-block-page-list .menu-item-home',
                            '.wp-block-page-list .open-always',
                            '.wp-block-page-list .open-on-click',
                            '.wp-block-page-list .open-on-hover-click',
                        ],
                    ],
                    'core/page-list-item' => [
                        'selectors' => [
                            '.wp-block-pages-list__item',
                            '.wp-block-pages-list__item__link',
                        ],
                        'status' => 'internal-child',
                        'note' => 'Page List Item is rendered through Page List and has no wp-block-page-list-item root.',
                    ],
                    'core/navigation' => [
                        'selectors' => [
                            '.wp-block-navigation',
                            '.wp-block-navigation__container',
                            '.wp-block-navigation__responsive-container',
                            '.wp-block-navigation__responsive-container-open',
                            '.wp-block-navigation__responsive-container-close',
                            '.wp-block-navigation__responsive-container-content',
                            '.wp-block-navigation__responsive-close',
                            '.wp-block-navigation__responsive-dialog',
                            '.wp-block-navigation__overlay-container',
                            '.wp-block-navigation__submenu-container',
                            '.wp-block-navigation__submenu-icon',
                            '.wp-block-navigation__toggle_button_label',
                            '.wp-block-navigation.has-child',
                            '.wp-block-navigation.is-responsive',
                            '.wp-block-navigation.is-vertical',
                            '.wp-block-navigation.items-justified-left',
                            '.wp-block-navigation.items-justified-center',
                            '.wp-block-navigation.items-justified-right',
                            '.wp-block-navigation.items-justified-space-between',
                            '.wp-block-navigation.no-wrap',
                            '.wp-block-navigation.is-menu-open',
                            '.wp-block-navigation.has-modal-open',
                            '.wp-block-navigation.hidden-by-default',
                            '.wp-block-navigation.always-shown',
                            '.wp-block-navigation.disable-default-overlay',
                        ],
                    ],
                ],
            ],
            [
                'filename' => '_comments.scss',
                'title' => 'Core Comment Block Classes',
                'language' => 'scss',
                'description' => 'All stable Comment and Post Comments blocks, pagination descendants, classic comment markup, and dynamic comment_class families.',
                'heading' => 'Core comment block classes',
                'variation' => 'WordPress 7.0.2 comment blocks',
                'blocks' => [
                    'core/comment-template' => [
                        'selectors' => [
                            '.wp-block-comment-template',
                            '.wp-block-comment-template .comment',
                            '.wp-block-comment-template .even',
                            '.wp-block-comment-template .odd',
                            '.wp-block-comment-template .alt',
                            '.wp-block-comment-template .thread-even',
                            '.wp-block-comment-template .thread-odd',
                            '.wp-block-comment-template .parent',
                            '.wp-block-comment-template .byuser',
                            '.wp-block-comment-template .bypostauthor',
                            '.wp-block-comment-template .pingback',
                            '.wp-block-comment-template .trackback',
                        ],
                        'note' => 'Dynamic comment_class families also include depth-{n} and comment-author-{nicename}.',
                    ],
                    'core/comment-author-name' => ['selectors' => ['.wp-block-comment-author-name']],
                    'core/comment-content' => ['selectors' => ['.wp-block-comment-content']],
                    'core/comment-date' => ['selectors' => ['.wp-block-comment-date']],
                    'core/comment-edit-link' => ['selectors' => ['.wp-block-comment-edit-link']],
                    'core/comment-reply-link' => ['selectors' => ['.wp-block-comment-reply-link']],
                    'core/comments' => [
                        'selectors' => [
                            '.wp-block-comments',
                            '.wp-block-post-comments',
                            '.wp-block-comments .commentlist',
                            '.wp-block-comments .children',
                            '.wp-block-comments .comment-body',
                            '.wp-block-comments .comment-author',
                            '.wp-block-comments .comment-meta',
                            '.wp-block-comments .commentmetadata',
                            '.wp-block-comments .reply',
                            '.wp-block-comments .navigation',
                            '.wp-block-comments .avatar',
                            '.wp-block-comments .comment-awaiting-moderation',
                        ],
                    ],
                    'core/comments-pagination' => ['selectors' => ['.wp-block-comments-pagination']],
                    'core/comments-pagination-next' => [
                        'selectors' => [
                            '.wp-block-comments-pagination-next',
                            '.wp-block-comments-pagination-next-arrow',
                        ],
                    ],
                    'core/comments-pagination-numbers' => [
                        'selectors' => ['.wp-block-comments-pagination-numbers', '.page-numbers'],
                    ],
                    'core/comments-pagination-previous' => [
                        'selectors' => [
                            '.wp-block-comments-pagination-previous',
                            '.wp-block-comments-pagination-previous-arrow',
                        ],
                    ],
                    'core/comments-title' => ['selectors' => ['.wp-block-comments-title']],
                    'core/post-comments-count' => ['selectors' => ['.wp-block-post-comments-count']],
                    'core/post-comments-form' => [
                        'selectors' => [
                            '.wp-block-post-comments-form',
                            '.wp-block-post-comments-form .comment-form',
                            '.wp-block-post-comments-form .comment-reply-title',
                            '.wp-block-post-comments-form .comment-form-comment',
                            '.wp-block-post-comments-form .comment-form-author',
                            '.wp-block-post-comments-form .comment-form-email',
                            '.wp-block-post-comments-form .comment-form-url',
                            '.wp-block-post-comments-form .comment-form-cookies-consent',
                            '.wp-block-post-comments-form .wp-block-button',
                            '.wp-block-post-comments-form .wp-block-button__link',
                        ],
                    ],
                    'core/post-comments-link' => [
                        'selectors' => ['.wp-block-post-comments-link', '.wp-block-post-comments-link .screen-reader-text'],
                    ],
                ],
            ],
            [
                'filename' => '_post.scss',
                'title' => 'Core Post Block Classes',
                'language' => 'scss',
                'description' => 'Author, content, date, excerpt, featured image, navigation, template, term, reading-time, title, and read-more block classes.',
                'heading' => 'Core post block classes',
                'variation' => 'WordPress 7.0.2 post blocks',
                'blocks' => [
                    'core/post-author' => [
                        'selectors' => [
                            '.wp-block-post-author',
                            '.wp-block-post-author__avatar',
                            '.wp-block-post-author__content',
                            '.wp-block-post-author__byline',
                            '.wp-block-post-author__name',
                            '.wp-block-post-author__bio',
                        ],
                        'status' => 'deprecated',
                    ],
                    'core/post-author-biography' => ['selectors' => ['.wp-block-post-author-biography']],
                    'core/post-author-name' => [
                        'selectors' => ['.wp-block-post-author-name', '.wp-block-post-author-name__link'],
                    ],
                    'core/post-content' => ['selectors' => ['.wp-block-post-content']],
                    'core/post-date' => [
                        'selectors' => ['.wp-block-post-date', '.wp-block-post-date__modified-date'],
                    ],
                    'core/post-excerpt' => [
                        'selectors' => [
                            '.wp-block-post-excerpt',
                            '.wp-block-post-excerpt__excerpt',
                            '.wp-block-post-excerpt__more-text',
                            '.wp-block-post-excerpt__more-link',
                        ],
                    ],
                    'core/post-featured-image' => [
                        'selectors' => [
                            '.wp-block-post-featured-image',
                            '.wp-block-post-featured-image__overlay',
                            '.wp-block-post-featured-image.has-background-dim',
                            '.wp-block-post-featured-image.has-background-dim-0',
                            '.wp-block-post-featured-image.has-background-dim-10',
                            '.wp-block-post-featured-image.has-background-dim-20',
                            '.wp-block-post-featured-image.has-background-dim-30',
                            '.wp-block-post-featured-image.has-background-dim-40',
                            '.wp-block-post-featured-image.has-background-dim-50',
                            '.wp-block-post-featured-image.has-background-dim-60',
                            '.wp-block-post-featured-image.has-background-dim-70',
                            '.wp-block-post-featured-image.has-background-dim-80',
                            '.wp-block-post-featured-image.has-background-dim-90',
                            '.wp-block-post-featured-image.has-background-dim-100',
                            '.wp-block-post-featured-image.has-background-gradient',
                        ],
                    ],
                    'core/post-navigation-link' => [
                        'selectors' => [
                            '.wp-block-post-navigation-link',
                            '.post-navigation-link-previous',
                            '.post-navigation-link-next',
                            '.wp-block-post-navigation-link__label',
                            '.wp-block-post-navigation-link__title',
                            '.wp-block-post-navigation-link__arrow-previous',
                            '.wp-block-post-navigation-link__arrow-next',
                        ],
                    ],
                    'core/post-template' => [
                        'selectors' => [
                            '.wp-block-post-template',
                            '.wp-block-post',
                            '.wp-block-post-template.is-flex-container',
                            '.wp-block-post-template.columns-2',
                            '.wp-block-post-template.columns-3',
                            '.wp-block-post-template.columns-4',
                            '.wp-block-post-template.columns-5',
                            '.wp-block-post-template.columns-6',
                            '.wp-block-post-template-is-layout-flow',
                            '.wp-block-post-template-is-layout-constrained',
                            '.wp-block-post-template-is-layout-grid',
                        ],
                        'note' => 'Each result also receives get_post_class() families such as post-{id}, type-{postType}, status-{status}, format-{format}, category-{slug}, tag-{slug}, hentry, and sticky.',
                    ],
                    'core/post-terms' => [
                        'selectors' => [
                            '.wp-block-post-terms',
                            '.wp-block-post-terms__prefix',
                            '.wp-block-post-terms__separator',
                            '.wp-block-post-terms__suffix',
                        ],
                    ],
                    'core/post-time-to-read' => ['selectors' => ['.wp-block-post-time-to-read']],
                    'core/post-title' => ['selectors' => ['.wp-block-post-title']],
                    'core/read-more' => [
                        'selectors' => [
                            '.wp-block-read-more',
                            '.wp-block-read-more.is-justified-left',
                            '.wp-block-read-more.is-justified-center',
                            '.wp-block-read-more.is-justified-right',
                            '.wp-block-read-more .screen-reader-text',
                        ],
                    ],
                ],
            ],
            [
                'filename' => '_query-and-terms.scss',
                'title' => 'Core Query and Term Block Classes',
                'language' => 'scss',
                'description' => 'Query Loop, pagination, totals, Terms Query, Term Template, and taxonomy result class families.',
                'heading' => 'Core query and term block classes',
                'variation' => 'WordPress 7.0.2 query and term blocks',
                'blocks' => [
                    'core/query' => ['selectors' => ['.wp-block-query']],
                    'core/query-no-results' => ['selectors' => ['.wp-block-query-no-results']],
                    'core/query-pagination' => [
                        'selectors' => [
                            '.wp-block-query-pagination',
                            '.wp-block-query-pagination.is-content-justification-space-between',
                        ],
                    ],
                    'core/query-pagination-next' => [
                        'selectors' => [
                            '.wp-block-query-pagination-next',
                            '.wp-block-query-pagination-next-arrow',
                        ],
                    ],
                    'core/query-pagination-numbers' => [
                        'selectors' => ['.wp-block-query-pagination-numbers', '.page-numbers'],
                    ],
                    'core/query-pagination-previous' => [
                        'selectors' => [
                            '.wp-block-query-pagination-previous',
                            '.wp-block-query-pagination-previous-arrow',
                        ],
                    ],
                    'core/query-title' => ['selectors' => ['.wp-block-query-title']],
                    'core/query-total' => ['selectors' => ['.wp-block-query-total']],
                    'core/term-count' => ['selectors' => ['.wp-block-term-count']],
                    'core/term-description' => ['selectors' => ['.wp-block-term-description']],
                    'core/term-name' => ['selectors' => ['.wp-block-term-name']],
                    'core/term-template' => [
                        'selectors' => [
                            '.wp-block-term-template',
                            '.wp-block-term',
                        ],
                        'note' => 'Each result also receives term-{id}, the taxonomy slug, and taxonomy-{taxonomy}.',
                    ],
                    'core/terms-query' => ['selectors' => ['.wp-block-terms-query']],
                ],
            ],
            [
                'filename' => '_site.scss',
                'title' => 'Core Site Structure Block Classes',
                'language' => 'scss',
                'description' => 'Avatar, Breadcrumbs, Login/out, synced Pattern, site identity, Template Part, and reusable Pattern wrapper behavior.',
                'heading' => 'Core site structure block classes',
                'variation' => 'WordPress 7.0.2 site blocks',
                'blocks' => [
                    'core/avatar' => [
                        'selectors' => [
                            '.wp-block-avatar',
                            '.wp-block-avatar__image',
                            '.wp-block-avatar__link',
                        ],
                    ],
                    'core/breadcrumbs' => ['selectors' => ['.wp-block-breadcrumbs']],
                    'core/loginout' => [
                        'selectors' => ['.wp-block-loginout', '.wp-block-loginout.has-login-form'],
                    ],
                    'core/pattern' => [
                        'selectors' => [],
                        'status' => 'referenced-inner-blocks',
                        'note' => 'Pattern Placeholder resolves referenced inner blocks and contributes no wrapper of its own.',
                    ],
                    'core/site-logo' => [
                        'selectors' => [
                            '.wp-block-site-logo',
                            '.wp-block-site-logo.is-default-size',
                            '.wp-block-site-logo.is-style-rounded',
                        ],
                    ],
                    'core/site-tagline' => ['selectors' => ['.wp-block-site-tagline']],
                    'core/site-title' => ['selectors' => ['.wp-block-site-title']],
                    'core/template-part' => ['selectors' => ['.wp-block-template-part']],
                    'core/block' => [
                        'selectors' => [],
                        'status' => 'referenced-inner-blocks',
                        'note' => 'The synced Pattern block renders the referenced blocks without a wp-block-block wrapper.',
                    ],
                ],
            ],
            [
                'filename' => '_embeds.scss',
                'title' => 'Core Embed Block Classes',
                'language' => 'scss',
                'description' => 'Embed wrapper, provider/type families, responsive aspect ratios, and embedded-content selectors.',
                'heading' => 'Core embed block classes',
                'variation' => 'WordPress 7.0.2 embed block',
                'blocks' => [
                    'core/embed' => [
                        'selectors' => [
                            '.wp-block-embed',
                            '.wp-block-embed__wrapper',
                            '.wp-has-aspect-ratio',
                            '.wp-embed-responsive',
                            '.wp-embed-aspect-1-1',
                            '.wp-embed-aspect-1-2',
                            '.wp-embed-aspect-4-3',
                            '.wp-embed-aspect-9-16',
                            '.wp-embed-aspect-16-9',
                            '.wp-embed-aspect-18-9',
                            '.wp-embed-aspect-21-9',
                            '.wp-embedded-content',
                        ],
                        'note' => 'Dynamic families include wp-block-embed-{provider}, is-provider-{provider}, and is-type-{video|rich|photo|link}.',
                    ],
                ],
            ],
        ];
    }

    private function wordpressGutenbergSharedClasses(): string
    {
        return <<<'SCSS'
/*
 * Shared Gutenberg frontend classes — WordPress 7.0.2.
 * Empty rules are searchable documentation and must not ship as theme CSS.
 */

/* {!# snippet: generated_block_root_classes #!} */
/*
 * Core: .wp-block-{slug}
 * Third party: .wp-block-{namespace}-{slug}
 * Additional CSS Class(es): arbitrary user input, so it cannot be enumerated.
 */
.wp-site-blocks,
.wp-block,
.has-global-padding {}

/* {!# snippet: alignment_classes #!} */
.alignleft,
.aligncenter,
.alignright,
.alignwide,
.alignfull {}

/* {!# snippet: color_and_gradient_classes #!} */
/*
 * Presets: .has-{slug}-color, .has-{slug}-background-color,
 * .has-{slug}-border-color, .has-{slug}-gradient-background
 */
.has-text-color,
.has-background,
.has-border-color,
.has-link-color,
.has-background-gradient,
.has-custom-border {}

/* {!# snippet: cover_overlay_opacity_classes #!} */
.has-background-dim,
.has-background-dim-0,
.has-background-dim-10,
.has-background-dim-20,
.has-background-dim-30,
.has-background-dim-40,
.has-background-dim-50,
.has-background-dim-60,
.has-background-dim-70,
.has-background-dim-80,
.has-background-dim-90,
.has-background-dim-100 {}

/* {!# snippet: typography_classes #!} */
/* Presets: .has-{slug}-font-size and .has-{slug}-font-family */
.has-custom-font-size,
.has-fit-text,
.has-drop-cap,
.has-text-align-left,
.has-text-align-center,
.has-text-align-right,
.has-text-decoration-underline,
.has-text-decoration-line-through {}

/* {!# snippet: layout_classes #!} */
/*
 * Stable types: .wp-block-{block}-is-layout-{flow|constrained|flex|grid}
 * Runtime only: .wp-container-{block}-is-layout-{hash}; never target the hash.
 */
.is-layout-flow,
.is-layout-constrained,
.is-layout-flex,
.is-layout-grid,
.is-horizontal,
.is-vertical,
.is-nowrap,
.is-content-justification-left,
.is-content-justification-center,
.is-content-justification-right,
.is-content-justification-space-between,
.is-content-justification-stretch {}

/* {!# snippet: position_visibility_and_custom_css_classes #!} */
/*
 * Runtime families: .wp-container-{id}, .wp-custom-css-{hash},
 * .wp-elements-{hash}, .wp-settings-{hash}, and .wp-duotone-{preset-or-id}.
 */
.is-position-sticky,
.is-position-fixed,
.has-custom-css,
.wp-block-hidden-mobile,
.wp-block-hidden-tablet,
.wp-block-hidden-desktop {}

/* {!# snippet: flex_alignment_and_direction_classes #!} */
.is-flex-container,
.is-grid,
.is-not-stacked-on-mobile,
.is-stacked-on-mobile,
.is-vertically-aligned-top,
.is-vertically-aligned-center,
.is-vertically-aligned-bottom,
.is-vertically-aligned-stretch,
.are-vertically-aligned-top,
.are-vertically-aligned-center,
.are-vertically-aligned-bottom,
.items-justified-left,
.items-justified-center,
.items-justified-right,
.items-justified-space-between {}

/* {!# snippet: registered_style_variation_classes #!} */
/* Open family: .is-style-{registered-style}; instances may add --{number}. */
.is-style-circle-mask,
.is-style-dots,
.is-style-large,
.is-style-logos-only,
.is-style-outline,
.is-style-pill-shape,
.is-style-plain,
.is-style-rounded,
.is-style-solid-color,
.is-style-squared,
.is-style-stripes,
.is-style-wide {}

/* {!# snippet: shared_attribute_state_classes #!} */
.has-alpha-channel-opacity,
.has-css-opacity,
.has-child,
.has-custom-content-position,
.has-custom-width,
.has-icon,
.has-left-content,
.has-media-on-the-right,
.has-modal-open,
.has-nested-images,
.has-parallax,
.has-right-content,
.is-active,
.is-arrow-none,
.is-arrow-arrow,
.is-arrow-chevron,
.is-cropped,
.is-dark-theme,
.is-default-size,
.is-image-fill,
.is-image-fill-element,
.is-light,
.is-menu-open,
.is-open,
.is-repeated,
.is-responsive {}

/* {!# snippet: content_position_classes #!} */
.is-position-top-left,
.is-position-top-center,
.is-position-top-right,
.is-position-center-left,
.is-position-center-center,
.is-position-center-right,
.is-position-bottom-left,
.is-position-bottom-center,
.is-position-bottom-right {}

/* {!# snippet: shared_element_and_accessibility_classes #!} */
.wp-element-button,
.wp-element-caption,
.screen-reader-text {}

/* {!# snippet: core_compatibility_preset_classes #!} */
.has-atomic-cream-gradient-background,
.has-hazy-dawn-gradient-background,
.has-midnight-gradient-background,
.has-nightshade-gradient-background,
.has-purple-crush-gradient-background,
.has-subdued-olive-gradient-background,
.has-vivid-green-cyan-to-vivid-cyan-blue-gradient-background,
.has-very-dark-gray-color,
.has-very-dark-gray-background-color,
.has-very-light-gray-color,
.has-very-light-gray-background-color,
.has-normal-font-size,
.has-regular-font-size,
.has-larger-font-size,
.has-huge-font-size,
.has-subtle-light-gray-background-color,
.has-subtle-pale-blue-background-color,
.has-subtle-pale-green-background-color,
.has-subtle-pale-pink-background-color {}
SCSS;
    }

    private function wordpressGutenbergPreviewClasses(): string
    {
        return <<<'SCSS'
/*
 * Gutenberg preview / experimental frontend classes.
 *
 * These types appear in the evolving block-library documentation or compiled
 * WordPress assets but are not part of the 109 stable server-registered Core
 * blocks in WordPress 7.0.2. Verify the active Gutenberg/WordPress version.
 */

/* {!# snippet: preview_form_classes #!} */
.wp-block-form {}

/* {!# snippet: preview_form_input_classes #!} */
.wp-block-form-input,
.wp-block-form-input__input,
.wp-block-form-input__label,
.wp-block-form-input__label-content,
.wp-block-form-input.is-label-inline {}

/* {!# snippet: preview_form_submit_button_classes #!} */
.wp-block-form-submit-button,
.wp-block-form-submit-wrapper {}

/* {!# snippet: preview_form_notification_classes #!} */
.wp-block-form-submission-notification,
.wp-block-form-submission-notification.form-notification-type-success,
.wp-block-form-submission-notification.form-notification-type-error {}

/* {!# snippet: preview_playlist_classes #!} */
.wp-block-playlist,
.wp-block-playlist__current-item,
.wp-block-playlist__current-item-artist-album,
.wp-block-playlist__item-image,
.wp-block-playlist__item-title,
.wp-block-playlist__item-artist,
.wp-block-playlist__item-album,
.wp-block-playlist__tracklist,
.wp-block-playlist__tracklist-is-hidden,
.wp-block-playlist__tracklist-artist-is-hidden,
.wp-block-playlist__tracklist-show-numbers {}

/* {!# snippet: preview_playlist_track_classes #!} */
.wp-block-playlist-track,
.wp-block-playlist-track__button,
.wp-block-playlist-track__content,
.wp-block-playlist-track__title,
.wp-block-playlist-track__artist,
.wp-block-playlist-track__length {}

/* {!# snippet: preview_tabs_classes #!} */
.wp-block-tabs,
.wp-block-tabs__title,
.wp-block-tabs-menu,
.wp-block-tabs-menu-item,
.wp-block-tabs-menu-item__template,
.wp-block-tab,
.wp-block-tab-panel {}

/* {!# snippet: preview_table_of_contents_classes #!} */
.wp-block-table-of-contents,
.wp-block-table-of-contents__entry {}

/* {!# snippet: deprecated_fse_comment_classes #!} */
.wp-block-comment-author-avatar,
.wp-block-post-comment {}
SCSS;
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedWordPressCustomDirectoryIntegration(
        Project $project,
        Folder $folder,
        Folder $patternsFolder,
        User $user,
        array $tags,
    ): void {
        $integrationTags = [
            $tags['wordpress'],
            $tags['gutenberg'],
            $tags['wordpress-standards'],
            $tags['plugin-development'],
            $tags['data-integration'],
            $tags['custom-database-table'],
            $tags['wpdb'],
            $tags['ajax'],
        ];

        $readme = <<<'MARKDOWN'
<!-- {!# snippet: custom_table_decision #!} -->
# Custom directory integration

Use post meta or a custom post type when the data naturally behaves like WordPress content. Use a custom table when the records are operational data with their own query shape, indexes, retention policy, or volume. This example uses a custom table deliberately and keeps the functionality in the companion plugin so it survives theme changes.

<!-- {!# snippet: integration_request_flow #!} -->
## Request and rendering flow

1. Plugin activation runs the versioned `dbDelta()` schema installer.
2. An editor-only frontend form submits to `wp-admin/admin-ajax.php` with an action and nonce.
3. The AJAX handler verifies the nonce and capability, unslashes and sanitises input, validates it, and writes through a repository.
4. JSON returns only public fields; JavaScript inserts them with `textContent` rather than HTML.
5. A dynamic Gutenberg block reads published rows and renders an escaped table for every visitor.

<!-- {!# snippet: public_submission_variant #!} -->
## Public submission variant

Do not add `wp_ajax_nopriv_*` blindly. A public form should store entries as `pending`, add abuse controls and rate limiting, define a moderation screen, document personal-data retention, and publish only after approval. A nonce prevents cross-site requests; it is not authentication or authorisation.

<!-- {!# snippet: schema_lifecycle #!} -->
## Schema lifecycle

The schema version is stored in an option and checked on `plugins_loaded`, because plugin activation hooks do not run during ordinary plugin updates. Deactivation keeps data. Permanent table deletion belongs in an explicit uninstall routine and should be opt-in for production systems.
MARKDOWN;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Custom Directory Integration Guide',
                'filename' => 'README.md',
                'language' => 'markdown',
                'description' => 'Architecture, security boundary, AJAX flow, public-submission variant, and custom-table lifecycle.',
                'content' => $readme,
                'position' => 0,
            ],
            variationName: 'Production-minded integration guide',
            tags: array_merge($integrationTags, [$tags['security']]),
        );

        $bootstrapPhp = <<<'PHP'
<?php
/**
 * Custom directory integration bootstrap.
 *
 * @package BlueprintTools
 */

namespace BlueprintTools\Directory;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-directory-schema.php';
require_once __DIR__ . '/class-directory-repository.php';
require_once __DIR__ . '/class-directory-ajax.php';
require_once __DIR__ . '/class-directory-assets.php';

// {!# snippet: register_directory_schema_lifecycle #!}
Directory_Schema::register( BLUEPRINT_TOOLS_FILE );

// {!# snippet: register_directory_ajax_and_assets #!}
Directory_Ajax::register();
Directory_Assets::register( BLUEPRINT_TOOLS_FILE );

// {!# snippet: register_directory_dynamic_block #!}
add_action( 'init', array( Directory_Assets::class, 'register_block' ) );
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Custom Directory Bootstrap',
                'filename' => 'bootstrap.php',
                'language' => 'php',
                'description' => 'Loads integration classes and registers the schema lifecycle, AJAX endpoint, assets, and dynamic block.',
                'content' => $bootstrapPhp,
                'position' => 1,
            ],
            variationName: 'Integration bootstrap',
            tags: $integrationTags,
        );

        $schemaPhp = <<<'PHP'
<?php
/**
 * Versioned directory table schema.
 *
 * @package BlueprintTools
 */

namespace BlueprintTools\Directory;

final class Directory_Schema
{
    private const VERSION = '1.0.0';

    private const VERSION_OPTION = 'blueprint_directory_schema_version';

    // {!# snippet: prefixed_custom_table_name #!}
    public static function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'blueprint_directory_entries';
    }

    // {!# snippet: register_schema_install_and_upgrade_hooks #!}
    public static function register( string $plugin_file ): void
    {
        register_activation_hook( $plugin_file, array( self::class, 'install' ) );
        add_action( 'plugins_loaded', array( self::class, 'maybe_upgrade' ) );
    }

    // {!# snippet: install_versioned_table_with_dbdelta #!}
    public static function install(): void
    {
        global $wpdb;

        $table_name      = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE {$table_name} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
name varchar(190) NOT NULL,
message text NOT NULL,
status varchar(20) NOT NULL DEFAULT 'published',
created_at_gmt datetime NOT NULL,
PRIMARY KEY  (id),
KEY status_created (status,created_at_gmt,id)
) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        $table_exists = $wpdb->get_var(
            $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) )
        );

        if ( $table_name === $table_exists ) {
            update_option( self::VERSION_OPTION, self::VERSION, false );
        }
    }

    // {!# snippet: upgrade_table_when_schema_version_changes #!}
    public static function maybe_upgrade(): void
    {
        if ( self::VERSION !== get_option( self::VERSION_OPTION ) ) {
            self::install();
        }
    }
}
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Versioned WordPress Table Schema',
                'filename' => 'class-directory-schema.php',
                'language' => 'php',
                'description' => 'Prefix-safe table naming, dbDelta-compatible SQL, indexes, activation, and update-time schema checks.',
                'content' => $schemaPhp,
                'position' => 2,
            ],
            variationName: 'dbDelta schema lifecycle',
            tags: array_merge($integrationTags, [$tags['database']]),
        );

        $repositoryPhp = <<<'PHP'
<?php
/**
 * Directory table persistence.
 *
 * @package BlueprintTools
 */

namespace BlueprintTools\Directory;

final class Directory_Repository
{
    // {!# snippet: insert_sanitised_directory_entry #!}
    public function create( array $entry ): int|\WP_Error
    {
        global $wpdb;

        $inserted = $wpdb->insert(
            Directory_Schema::table_name(),
            array(
                'name' => $entry['name'],
                'message' => $entry['message'],
                'status' => $entry['status'],
                'created_at_gmt' => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%s', '%s' ),
        );

        if ( false === $inserted ) {
            return new \WP_Error(
                'blueprint_directory_insert_failed',
                __( 'The directory entry could not be saved.', 'blueprint-tools' ),
            );
        }

        return (int) $wpdb->insert_id;
    }

    // {!# snippet: query_published_directory_rows #!}
    public function published( int $limit = 50, int $offset = 0 ): array|\WP_Error
    {
        global $wpdb;

        $limit  = max( 1, min( 100, $limit ) );
        $offset = max( 0, $offset );
        $query = $wpdb->prepare(
            'SELECT id, name, message, created_at_gmt
             FROM %i
             WHERE status = %s
             ORDER BY created_at_gmt DESC, id DESC
             LIMIT %d OFFSET %d',
            Directory_Schema::table_name(),
            'published',
            $limit,
            $offset,
        );
        $entries = $wpdb->get_results( $query, ARRAY_A );

        if ( '' !== $wpdb->last_error ) {
            return new \WP_Error(
                'blueprint_directory_query_failed',
                __( 'Directory entries are temporarily unavailable.', 'blueprint-tools' )
            );
        }

        return array_map( array( self::class, 'public_entry' ), $entries );
    }

    // {!# snippet: retrieve_directory_entry_for_ajax_response #!}
    public function find_public( int $entry_id ): ?array
    {
        global $wpdb;

        $query = $wpdb->prepare(
            'SELECT id, name, message, created_at_gmt
             FROM %i
             WHERE id = %d AND status = %s',
            Directory_Schema::table_name(),
            $entry_id,
            'published',
        );
        $entry = $wpdb->get_row( $query, ARRAY_A );

        return is_array( $entry ) ? self::public_entry( $entry ) : null;
    }

    /**
     * Convert the GMT database row to the public response contract.
     *
     * @param array<string, string> $entry Database row.
     * @return array{id: int, name: string, message: string, created_at: string, created_label: string}
     */
    private static function public_entry( array $entry ): array
    {
        $timestamp = strtotime( $entry['created_at_gmt'] . ' UTC' );

        return array(
            'id' => (int) $entry['id'],
            'name' => $entry['name'],
            'message' => $entry['message'],
            'created_at' => gmdate( DATE_ATOM, $timestamp ),
            'created_label' => wp_date( (string) get_option( 'date_format' ), $timestamp ),
        );
    }
}
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'WPDB Directory Repository',
                'filename' => 'class-directory-repository.php',
                'language' => 'php',
                'description' => 'Typed insert, prepared reads, bounded pagination, public-field projection, and database errors.',
                'content' => $repositoryPhp,
                'position' => 3,
            ],
            variationName: 'Repository boundary for WPDB',
            tags: array_merge($integrationTags, [
                $tags['database'],
                $tags['security'],
            ]),
        );

        $ajaxPhp = <<<'PHP'
<?php
/**
 * Authenticated directory submission endpoint.
 *
 * @package BlueprintTools
 */

namespace BlueprintTools\Directory;

final class Directory_Ajax
{
    private const ACTION = 'blueprint_directory_submit';

    private const CAPABILITY = 'edit_others_posts';

    // {!# snippet: register_authenticated_ajax_action #!}
    public static function register(): void
    {
        add_action( 'wp_ajax_' . self::ACTION, array( self::class, 'submit' ) );
    }

    // {!# snippet: verify_authorise_and_validate_ajax_request #!}
    public static function submit(): void
    {
        check_ajax_referer( self::ACTION, 'nonce' );

        if ( ! current_user_can( self::CAPABILITY ) ) {
            wp_send_json_error(
                array( 'message' => __( 'You are not allowed to add directory entries.', 'blueprint-tools' ) ),
                403
            );
        }

        $entry = array(
            'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'message' => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
            'status' => 'published',
        );
        $name_length    = function_exists( 'mb_strlen' ) ? mb_strlen( $entry['name'] ) : strlen( $entry['name'] );
        $message_length = function_exists( 'mb_strlen' ) ? mb_strlen( $entry['message'] ) : strlen( $entry['message'] );

        if (
            '' === $entry['name']
            || '' === $entry['message']
            || 190 < $name_length
            || 2000 < $message_length
        ) {
            wp_send_json_error(
                array( 'message' => __( 'Enter a valid name and message.', 'blueprint-tools' ) ),
                422
            );
        }

        // {!# snippet: persist_and_return_public_ajax_data #!}
        $repository = new Directory_Repository();
        $entry_id   = $repository->create( $entry );

        if ( is_wp_error( $entry_id ) ) {
            wp_send_json_error( array( 'message' => $entry_id->get_error_message() ), 500 );
        }

        $public_entry = $repository->find_public( $entry_id );

        if ( null === $public_entry ) {
            wp_send_json_error(
                array( 'message' => __( 'The entry was saved but could not be reloaded.', 'blueprint-tools' ) ),
                500
            );
        }

        wp_send_json_success(
            array(
                'message' => __( 'Directory entry added.', 'blueprint-tools' ),
                'entry' => $public_entry,
            ),
            201
        );
    }
}
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Secure WordPress AJAX Handler',
                'filename' => 'class-directory-ajax.php',
                'language' => 'php',
                'description' => 'Authenticated AJAX registration, nonce verification, capability checks, sanitisation, validation, persistence, and JSON responses.',
                'content' => $ajaxPhp,
                'position' => 4,
            ],
            variationName: 'Authenticated editor submission',
            tags: array_merge($integrationTags, [$tags['security']]),
        );

        $assetsPhp = <<<'PHP'
<?php
/**
 * Directory frontend assets and AJAX configuration.
 *
 * @package BlueprintTools
 */

namespace BlueprintTools\Directory;

final class Directory_Assets
{
    private const SCRIPT_HANDLE = 'blueprint-directory-form';

    private static string $plugin_file;

    // {!# snippet: register_directory_asset_source #!}
    public static function register( string $plugin_file ): void
    {
        self::$plugin_file = $plugin_file;
    }

    // {!# snippet: register_block_and_view_script #!}
    public static function register_block(): void
    {
        $relative_path = 'integrations/custom-directory/directory-form.js';
        $absolute_path = BLUEPRINT_TOOLS_PATH . $relative_path;

        wp_register_script(
            self::SCRIPT_HANDLE,
            plugins_url( $relative_path, self::$plugin_file ),
            array(),
            file_exists( $absolute_path ) ? (string) filemtime( $absolute_path ) : '1.0.0',
            array( 'strategy' => 'defer', 'in_footer' => true )
        );

        register_block_type( __DIR__ . '/block.json' );
    }

    // {!# snippet: configure_ajax_when_form_renders #!}
    public static function configure_submission(): void
    {
        static $configured = false;

        if ( $configured ) {
            return;
        }

        wp_add_inline_script(
            self::SCRIPT_HANDLE,
            'window.BlueprintDirectory = ' . wp_json_encode(
                array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'action' => 'blueprint_directory_submit',
                'nonce' => wp_create_nonce( 'blueprint_directory_submit' ),
                'savingMessage' => __( 'Saving…', 'blueprint-tools' ),
                'errorMessage' => __( 'The entry could not be added.', 'blueprint-tools' ),
                'savedRefreshMessage' => __( 'Saved. Refresh to see the new entry.', 'blueprint-tools' ),
                )
            ) . ';',
            'before'
        );

        $configured = true;
    }
}
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Conditional Directory Assets',
                'filename' => 'class-directory-assets.php',
                'language' => 'php',
                'description' => 'Loads the form script only with the directory block and safely exposes the AJAX URL, nonce, action, and translated error.',
                'content' => $assetsPhp,
                'position' => 5,
            ],
            variationName: 'Block-aware AJAX configuration',
            tags: array_merge($integrationTags, [
                $tags['performance'],
                $tags['security'],
            ]),
        );

        $blockJson = <<<'JSON'
{
    "$schema": "https://schemas.wp.org/trunk/block.json",
    "apiVersion": 3,
    "name": "blueprint/directory-table",
    "version": "1.0.0",
    "title": "Directory Table",
    "category": "widgets",
    "icon": "list-view",
    "description": "Displays custom-table directory entries and an optional editor-and-administrator submission form.",
    "textdomain": "blueprint-tools",
    "attributes": {
        "showForm": {
            "type": "boolean",
            "default": true
        }
    },
    "supports": {
        "align": ["wide", "full"],
        "html": false
    },
    "editorScript": "file:./index.js",
    "viewScript": "blueprint-directory-form",
    "style": "file:./directory.css",
    "render": "file:./render.php"
}
JSON;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Directory Table Block Metadata',
                'filename' => 'block.json',
                'language' => 'json',
                'description' => 'Block API v3 metadata for the dynamic directory table, editor control, styles, and server renderer.',
                'content' => $blockJson,
                'position' => 6,
            ],
            variationName: 'Dynamic directory block metadata',
            tags: array_merge($integrationTags, [$tags['dynamic-block']]),
        );

        $indexAssetPhp = <<<'PHP'
<?php

return [
    'dependencies' => [
        'wp-block-editor',
        'wp-blocks',
        'wp-components',
        'wp-element',
        'wp-i18n',
        'wp-server-side-render',
    ],
    'version' => '1.0.0',
];
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Directory Editor Asset Manifest',
                'filename' => 'index.asset.php',
                'language' => 'php',
                'description' => 'WordPress script dependencies for the unbundled dynamic-block editor example.',
                'content' => $indexAssetPhp,
                'position' => 7,
            ],
            variationName: 'WordPress editor dependencies',
            tags: array_merge($integrationTags, [$tags['dynamic-block']]),
        );

        $indexJavaScript = <<<'JAVASCRIPT'
(function (blocks, blockEditor, components, element, i18n, ServerSideRender) {
    'use strict';

    var createElement = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var useBlockProps = blockEditor.useBlockProps;
    var PanelBody = components.PanelBody;
    var ToggleControl = components.ToggleControl;
    var __ = i18n.__;

    // {!# snippet: register_directory_table_editor_block #!}
    blocks.registerBlockType('blueprint/directory-table', {
        edit: function (props) {
            var blockProps = useBlockProps();

            return createElement(
                'div',
                blockProps,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        { title: __('Directory settings', 'blueprint-tools') },
                        createElement(ToggleControl, {
                            label: __('Show submission form to editors and administrators', 'blueprint-tools'),
                            checked: props.attributes.showForm,
                            onChange: function (showForm) {
                                props.setAttributes({ showForm: showForm });
                            },
                        }),
                    ),
                ),
                createElement(ServerSideRender, {
                    block: 'blueprint/directory-table',
                    attributes: props.attributes,
                }),
            );
        },
        save: function () {
            return null;
        },
    });
}(window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender));
JAVASCRIPT;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Directory Table Block Editor',
                'filename' => 'index.js',
                'language' => 'javascript',
                'description' => 'useBlockProps-based dynamic block registration with an inspector toggle and server-side preview.',
                'content' => $indexJavaScript,
                'position' => 8,
            ],
            variationName: 'Dynamic block editor controls',
            tags: array_merge($integrationTags, [
                $tags['dynamic-block'],
                $tags['accessibility'],
            ]),
        );

        $renderPhp = <<<'PHP'
<?php
/**
 * Directory table block renderer.
 *
 * @var array<string, mixed> $attributes
 *
 * @package BlueprintTools
 */

use BlueprintTools\Directory\Directory_Assets;
use BlueprintTools\Directory\Directory_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// {!# snippet: query_and_prepare_directory_block #!}
$directory_repository = new Directory_Repository();
$directory_result     = $directory_repository->published();
$directory_error      = is_wp_error( $directory_result ) ? $directory_result : null;
$directory_entries    = is_array( $directory_result ) ? $directory_result : array();
$is_editor_preview    = defined( 'REST_REQUEST' ) && REST_REQUEST;
$show_directory_form  = ! empty( $attributes['showForm'] )
	&& current_user_can( 'edit_others_posts' )
	&& ! $is_editor_preview;
$directory_wrapper    = get_block_wrapper_attributes( array( 'class' => 'blueprint-directory' ) );

if ( $show_directory_form ) {
	Directory_Assets::configure_submission();
}
?>

<!-- {!# snippet: render_public_directory_table #!} -->
<section <?php echo $directory_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated by get_block_wrapper_attributes(). ?>>
    <?php if ( $directory_error ) : ?>
        <p role="status"><?php echo esc_html( $directory_error->get_error_message() ); ?></p>
    <?php endif; ?>
    <div class="blueprint-directory__table-wrap">
        <table class="blueprint-directory__table" data-blueprint-directory-table>
            <caption class="screen-reader-text"><?php esc_html_e('Published directory entries', 'blueprint-tools'); ?></caption>
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Name', 'blueprint-tools'); ?></th>
                    <th scope="col"><?php esc_html_e('Message', 'blueprint-tools'); ?></th>
                    <th scope="col"><?php esc_html_e('Added', 'blueprint-tools'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( array() === $directory_entries ) : ?>
                    <tr data-blueprint-directory-empty>
                        <td colspan="3"><?php esc_html_e('No directory entries yet.', 'blueprint-tools'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $directory_entries as $directory_entry ) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html( $directory_entry['name'] ); ?></th>
                            <td><?php echo esc_html( $directory_entry['message'] ); ?></td>
                            <td><time datetime="<?php echo esc_attr( $directory_entry['created_at'] ); ?>"><?php echo esc_html( $directory_entry['created_label'] ); ?></time></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- {!# snippet: render_editor_submission_form #!} -->
    <?php if ( $show_directory_form ) : ?>
        <form class="blueprint-directory__form" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post" data-blueprint-directory-form>
            <input name="action" type="hidden" value="blueprint_directory_submit">
            <?php wp_nonce_field( 'blueprint_directory_submit', 'nonce' ); ?>
            <label>
                <span><?php esc_html_e('Name', 'blueprint-tools'); ?></span>
                <input name="name" type="text" maxlength="190" required>
            </label>
            <label>
                <span><?php esc_html_e('Message', 'blueprint-tools'); ?></span>
                <textarea name="message" maxlength="2000" required></textarea>
            </label>
            <button type="submit"><?php esc_html_e('Add directory entry', 'blueprint-tools'); ?></button>
            <p role="status" aria-live="polite" data-blueprint-directory-status></p>
        </form>
    <?php endif; ?>
</section>
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Directory Table Server Renderer',
                'filename' => 'render.php',
                'language' => 'php',
                'description' => 'Prepared repository query, escaped semantic table, empty state, capability-gated form, and live status region.',
                'content' => $renderPhp,
                'position' => 9,
            ],
            variationName: 'Public table and editor form',
            tags: array_merge($integrationTags, [
                $tags['dynamic-block'],
                $tags['accessibility'],
                $tags['security'],
            ]),
        );

        $directoryJavaScript = <<<'JAVASCRIPT'
(function () {
    'use strict';

    // {!# snippet: build_safe_directory_table_row #!}
    function buildTableRow(entry) {
        var row = document.createElement('tr');
        var name = document.createElement('th');
        var message = document.createElement('td');
        var created = document.createElement('td');
        var time = document.createElement('time');

        name.scope = 'row';
        name.textContent = entry.name;
        message.textContent = entry.message;
        time.dateTime = entry.created_at;
        time.textContent = entry.created_label;
        created.append(time);
        row.append(name, message, created);

        return row;
    }

    // {!# snippet: submit_directory_entry_with_fetch #!}
    async function submitDirectoryEntry(form) {
        var config = window.BlueprintDirectory;
        var status = form.querySelector('[data-blueprint-directory-status]');
        var submitButton = form.querySelector('[type="submit"]');

        if (! config || ! status || ! submitButton) {
            return;
        }

        var body = new FormData(form);

        submitButton.disabled = true;
        form.setAttribute('aria-busy', 'true');
        status.textContent = config.savingMessage;

        try {
            var response = await fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: body,
            });
            var payload = await response.json();

            if (! response.ok || ! payload.success || ! payload.data.entry) {
                throw new Error(payload.data && payload.data.message ? payload.data.message : config.errorMessage);
            }

            var wrapper = form.closest('.blueprint-directory');
            var table = wrapper ? wrapper.querySelector('[data-blueprint-directory-table] tbody') : null;
            form.reset();
            status.textContent = payload.data.message;

            try {
                if (! table) {
                    throw new Error('Directory table is unavailable.');
                }

                table.querySelector('[data-blueprint-directory-empty]')?.remove();
                table.prepend(buildTableRow(payload.data.entry));

                while (table.rows.length > 50) {
                    table.deleteRow(table.rows.length - 1);
                }
            } catch (renderError) {
                status.textContent = config.savedRefreshMessage;
            }
        } catch (error) {
            status.textContent = error instanceof Error ? error.message : config.errorMessage;
        } finally {
            submitButton.disabled = false;
            form.removeAttribute('aria-busy');
        }
    }

    // {!# snippet: bind_directory_forms #!}
    document.querySelectorAll('[data-blueprint-directory-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            void submitDirectoryEntry(form);
        });
    });
}());
JAVASCRIPT;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Directory AJAX Form',
                'filename' => 'directory-form.js',
                'language' => 'javascript',
                'description' => 'Fetch submission with busy/status states and XSS-safe table insertion using DOM textContent.',
                'content' => $directoryJavaScript,
                'position' => 10,
            ],
            variationName: 'Progressive AJAX submission',
            tags: array_merge($integrationTags, [
                $tags['accessibility'],
                $tags['security'],
                $tags['progressive-enhancement'],
            ]),
        );

        $directoryCss = <<<'CSS'
/* {!# snippet: responsive_directory_layout #!} */
.blueprint-directory {
    display: grid;
    gap: clamp(1.5rem, 4vw, 3rem);
}

.blueprint-directory__table-wrap {
    overflow-x: auto;
}

.blueprint-directory__table {
    border-collapse: collapse;
    inline-size: 100%;
    min-inline-size: 42rem;
}

.blueprint-directory__table th,
.blueprint-directory__table td {
    border-block-end: 1px solid var(--wp--preset--color--contrast-3, #cbd5e1);
    padding: 0.85rem 1rem;
    text-align: start;
    vertical-align: top;
}

/* {!# snippet: accessible_directory_form #!} */
.blueprint-directory__form {
    display: grid;
    gap: 1rem;
    max-inline-size: 42rem;
}

.blueprint-directory__form label {
    display: grid;
    font-weight: 600;
    gap: 0.4rem;
}

.blueprint-directory__form input,
.blueprint-directory__form textarea {
    border: 1px solid var(--wp--preset--color--contrast-3, #94a3b8);
    border-radius: 0.35rem;
    font: inherit;
    padding: 0.7rem 0.8rem;
}

.blueprint-directory__form :focus-visible {
    outline: 3px solid var(--wp--preset--color--accent, #2563eb);
    outline-offset: 2px;
}
CSS;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Directory Table and Form Styles',
                'filename' => 'directory.css',
                'language' => 'css',
                'description' => 'Responsive overflow, semantic table styling, form layout, and visible keyboard focus.',
                'content' => $directoryCss,
                'position' => 11,
            ],
            variationName: 'Accessible directory presentation',
            tags: array_merge($integrationTags, [
                $tags['accessibility'],
                $tags['responsive'],
            ]),
        );

        $patternPhp = <<<'PHP'
<?php
/**
 * Title: Directory submission and table
 * Slug: blueprint/directory-submission
 * Categories: featured
 *
 * @package BlueprintTools
 */
?>
<!-- {!# snippet: directory_table_pattern #!} -->
<!-- wp:group {"metadata":{"name":"Directory integration"},"align":"wide","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide">
    <!-- wp:heading -->
    <h2 class="wp-block-heading"><?php esc_html_e('Community directory', 'blueprint-tools'); ?></h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph -->
    <p><?php esc_html_e('Published entries are stored in a dedicated WordPress table and rendered by a dynamic block.', 'blueprint-tools'); ?></p>
    <!-- /wp:paragraph -->

    <!-- wp:blueprint/directory-table {"showForm":true,"align":"wide"} /-->
</div>
<!-- /wp:group -->
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $patternsFolder,
            user: $user,
            attributes: [
                'title' => 'Directory Integration Pattern',
                'filename' => 'directory-submission.php',
                'language' => 'php',
                'description' => 'An auto-discovered theme pattern composing explanatory copy with the custom-table dynamic block.',
                'content' => $patternPhp,
                'position' => 12,
            ],
            variationName: 'Pattern using the directory block',
            tags: array_merge($integrationTags, [
                $tags['block-pattern'],
                $tags['dynamic-block'],
            ]),
        );

        $pluginBootstrap = $project->snippets()
            ->where('filename', 'wordpress-block-theme-tools.php')
            ->first();

        if ($pluginBootstrap !== null) {
            $pluginBootstrap->variations()->update(['is_default' => false]);
            $integratedVariation = $pluginBootstrap->variations()->updateOrCreate(
                ['name' => 'Load directory and FacetWP integrations'],
                [
                    'created_by_id' => $user->id,
                    'content' => <<<'PHP'
<?php
/**
 * Plugin Name: WordPress Block Theme Tools
 * Description: Search, directory, AJAX, and FacetWP helpers for the Blueprint block theme.
 * Version: 1.1.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Text Domain: blueprint-tools
 */

defined('ABSPATH') || exit;

define('BLUEPRINT_TOOLS_FILE', __FILE__);
define('BLUEPRINT_TOOLS_PATH', plugin_dir_path(__FILE__));

// {!# snippet: load_composer_dependencies #!}
$autoload_file = BLUEPRINT_TOOLS_PATH . 'vendor/autoload.php';

if ( file_exists( $autoload_file ) ) {
	require_once $autoload_file;
}

// {!# snippet: load_companion_plugin_integrations #!}
require_once BLUEPRINT_TOOLS_PATH . 'includes/meilisearch.php';
require_once BLUEPRINT_TOOLS_PATH . 'integrations/custom-directory/bootstrap.php';
require_once BLUEPRINT_TOOLS_PATH . 'integrations/facetwp/bootstrap.php';
PHP,
                    'position' => 2,
                    'is_default' => true,
                ],
            );
            $integratedVariation->update(['is_default' => true]);
        }
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedWordPressFacetWpIntegration(
        Project $project,
        Folder $folder,
        User $user,
        array $tags,
    ): void {
        $facetWpTags = [
            $tags['wordpress'],
            $tags['wordpress-standards'],
            $tags['plugin-development'],
            $tags['facetwp'],
            $tags['woocommerce'],
            $tags['filtering'],
        ];

        $readme = <<<'MARKDOWN'
<!-- {!# snippet: facetwp_architecture #!} -->
# FacetWP and WooCommerce integration

FacetWP filters one query-backed listing at a time. Keep every facet outside the `.facetwp-template` element and keep WooCommerce ordering, result count, products, and pagination inside that element so the complete result state refreshes together.

Use the normal WooCommerce archive template when the shop archive is the product listing. Use a FacetWP Listing Builder template when a block pattern or landing page owns the layout. Do not place two independently filtered listings on the same page.

<!-- {!# snippet: facetwp_installation_checklist #!} -->
## Installation checklist

1. Install and license FacetWP and WooCommerce.
2. Add WooCommerce theme support on `after_setup_theme`.
3. Register the code-owned facets, or create them in FacetWP and export the settings from the installed version.
4. Choose exactly one product listing and give its wrapper the `facetwp-template` class.
5. Re-index after changing products, taxonomies, custom fields, facet sources, or indexing hooks.
6. Test variable products, out-of-stock products, zero-result combinations, pagination, browser history, keyboard use, and JavaScript-disabled fallback.

<!-- {!# snippet: facetwp_block_editor_options #!} -->
## Block editor options

The included pattern uses FacetWP shortcodes and a Listing Builder template. If a project uses WooCommerce Product Collection blocks, install FacetWP's Blocks add-on and follow its supported block configuration rather than wrapping arbitrary nested queries.

<!-- {!# snippet: facetwp_operational_rules #!} -->
## Operational rules

- Treat facet names as API identifiers; renaming them breaks templates and URLs.
- Keep labels translatable and never trust query-string values in custom query hooks.
- Use `facetwp_index_row` to normalise indexed values, not to perform slow remote requests.
- Use `facetwp-loaded` for UI work that must run after every AJAX refresh.
- Keep a staging index and deployment runbook for large catalogues.
MARKDOWN;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'FacetWP WooCommerce Integration Guide',
                'filename' => 'README.md',
                'language' => 'markdown',
                'description' => 'Listing boundaries, installation, block-editor options, indexing, testing, and production operating rules.',
                'content' => $readme,
                'position' => 0,
            ],
            variationName: 'WooCommerce and block-theme integration guide',
            tags: array_merge($facetWpTags, [
                $tags['block-pattern'],
                $tags['accessibility'],
                $tags['performance'],
            ]),
        );

        $bootstrapPhp = <<<'PHP'
<?php
/**
 * FacetWP integration bootstrap.
 *
 * @package BlueprintTools
 */

namespace BlueprintTools\FacetWP;

defined('ABSPATH') || exit;

require_once __DIR__ . '/class-facetwp-integration.php';

// {!# snippet: register_facetwp_integration #!}
Integration::register();
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'FacetWP Integration Bootstrap',
                'filename' => 'bootstrap.php',
                'language' => 'php',
                'description' => 'Loads the optional FacetWP integration without coupling the theme to the premium plugin.',
                'content' => $bootstrapPhp,
                'position' => 1,
            ],
            variationName: 'Companion plugin bootstrap',
            tags: array_merge($facetWpTags, [$tags['progressive-enhancement']]),
        );

        $integrationPhp = <<<'PHP'
<?php
/**
 * Code-owned FacetWP facets and product index normalisation.
 *
 * @package BlueprintTools
 */

namespace BlueprintTools\FacetWP;

defined('ABSPATH') || exit;

final class Integration
{
    /** Register integration hooks. */
    public static function register(): void
    {
        add_filter('facetwp_facets', [self::class, 'register_facets']);
        add_filter('facetwp_index_row', [self::class, 'normalise_material_index'], 10, 2);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    /**
     * Register stable facet identifiers in code.
     *
     * @param array<int, array<string, mixed>> $facets Registered facets.
     * @return array<int, array<string, mixed>>
     */
    public static function register_facets(array $facets): array
    {
        // {!# snippet: register_woocommerce_facets #!}
        $facets[] = [
            'label' => __('Product categories', 'blueprint-tools'),
            'name' => 'product_categories',
            'type' => 'checkboxes',
            'source' => 'tax/product_cat',
            'hierarchical' => 'yes',
            'operator' => 'and',
        ];
        $facets[] = [
            'label' => __('Price', 'blueprint-tools'),
            'name' => 'product_price',
            'type' => 'slider',
            'source' => 'woo/price',
            'prefix' => get_woocommerce_currency_symbol(),
        ];
        $facets[] = [
            'label' => __('Stock status', 'blueprint-tools'),
            'name' => 'stock_status',
            'type' => 'checkboxes',
            'source' => 'woo/stock_status',
        ];
        $facets[] = [
            'label' => __('Material', 'blueprint-tools'),
            'name' => 'product_material',
            'type' => 'checkboxes',
            'source' => 'cf/product_material',
        ];

        return $facets;
    }

    /**
     * Normalise a custom product field for consistent facet URLs and labels.
     *
     * @param array<string, mixed> $params Index row parameters.
     * @param object               $class  FacetWP indexer.
     * @return array<string, mixed>|false
     */
    public static function normalise_material_index(array $params, object $class): array|false
    {
        unset($class);

        if ('product_material' !== $params['facet_name']) {
            return $params;
        }

        // {!# snippet: index_parent_product_material #!}
        $product_id = (int) $params['post_id'];

        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);

            if ($product && $product->get_parent_id()) {
                $product_id = $product->get_parent_id();
            }
        }

        $material = sanitize_text_field((string) get_post_meta($product_id, 'product_material', true));

        if ('' === $material) {
            return false;
        }

        $params['facet_value'] = sanitize_title($material);
        $params['facet_display_value'] = $material;

        return $params;
    }

    /** Enqueue refresh feedback only on the product catalogue. */
    public static function enqueue_assets(): void
    {
        // {!# snippet: conditionally_enqueue_facetwp_assets #!}
        $is_product_listing = function_exists('is_shop')
            && (is_shop() || is_product_taxonomy());

        if (! $is_product_listing || ! function_exists('FWP')) {
            return;
        }

        wp_enqueue_style(
            'blueprint-facetwp',
            plugins_url('facetwp-products.css', __FILE__),
            [],
            '1.0.0'
        );
        wp_enqueue_script(
            'blueprint-facetwp',
            plugins_url('facetwp-products.js', __FILE__),
            [],
            '1.0.0',
            true
        );
    }
}
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'FacetWP WooCommerce Integration',
                'filename' => 'class-facetwp-integration.php',
                'language' => 'php',
                'description' => 'Code-owned WooCommerce facets, variable-product material normalisation, and conditional frontend assets.',
                'content' => $integrationPhp,
                'position' => 2,
            ],
            variationName: 'Product catalogue facets',
            tags: array_merge($facetWpTags, [
                $tags['automation'],
                $tags['performance'],
            ]),
        );

        $themeSupportPhp = <<<'PHP'
<?php
/**
 * WooCommerce support for a block theme.
 *
 * @package Blueprint
 */

defined('ABSPATH') || exit;

// {!# snippet: register_woocommerce_theme_support #!}
add_action('after_setup_theme', static function (): void {
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
});
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'WooCommerce Theme Support',
                'filename' => 'woocommerce-theme-support.php',
                'language' => 'php',
                'description' => 'Theme-side WooCommerce support required before integrating its catalogue with FacetWP.',
                'content' => $themeSupportPhp,
                'position' => 3,
            ],
            variationName: 'Block-theme WooCommerce support',
            tags: array_merge($facetWpTags, [$tags['block-theme']]),
        );

        $archivePhp = <<<'PHP'
<?php
/**
 * Faceted WooCommerce product archive excerpt.
 *
 * Copy into the theme's WooCommerce archive template and preserve any
 * project-specific wrapper hooks.
 *
 * @package Blueprint
 */

defined('ABSPATH') || exit;

get_header('shop');
?>
<main id="primary" class="blueprint-product-catalogue">
    <aside class="blueprint-product-catalogue__filters" aria-label="<?php esc_attr_e('Filter products', 'blueprint'); ?>">
        <!-- {!# snippet: facetwp_woocommerce_facets #!} -->
        <?php echo facetwp_display('facet', 'product_categories'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FacetWP returns trusted plugin markup. ?>
        <?php echo facetwp_display('facet', 'product_price'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FacetWP returns trusted plugin markup. ?>
        <?php echo facetwp_display('facet', 'stock_status'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FacetWP returns trusted plugin markup. ?>
        <?php echo facetwp_display('facet', 'product_material'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FacetWP returns trusted plugin markup. ?>
        <?php echo facetwp_display('selections'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FacetWP returns trusted plugin markup. ?>
    </aside>

    <!-- {!# snippet: single_facetwp_woocommerce_listing #!} -->
    <section class="blueprint-product-catalogue__results facetwp-template" aria-live="polite">
        <?php if (woocommerce_product_loop()) : ?>
            <?php woocommerce_result_count(); ?>
            <?php woocommerce_catalog_ordering(); ?>
            <?php woocommerce_product_loop_start(); ?>
            <?php while (have_posts()) : ?>
                <?php the_post(); ?>
                <?php wc_get_template_part('content', 'product'); ?>
            <?php endwhile; ?>
            <?php woocommerce_product_loop_end(); ?>
            <?php woocommerce_pagination(); ?>
        <?php else : ?>
            <?php do_action('woocommerce_no_products_found'); ?>
        <?php endif; ?>
    </section>
</main>
<?php
get_footer('shop');
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'FacetWP WooCommerce Archive',
                'filename' => 'archive-product.php',
                'language' => 'php',
                'description' => 'Facets outside one refreshable WooCommerce result wrapper with sorting, products, and pagination inside it.',
                'content' => $archivePhp,
                'position' => 4,
            ],
            variationName: 'Classic WooCommerce archive listing',
            tags: array_merge($facetWpTags, [
                $tags['accessibility'],
                $tags['block-theme'],
            ]),
        );

        $patternPhp = <<<'PHP'
<?php
/**
 * Title: Faceted product catalogue
 * Slug: blueprint/faceted-product-catalogue
 * Categories: featured, woocommerce
 *
 * @package Blueprint
 */
?>
<!-- {!# snippet: facetwp_listing_builder_pattern #!} -->
<!-- wp:group {"align":"wide","className":"blueprint-product-catalogue","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide blueprint-product-catalogue">
    <!-- wp:group {"className":"blueprint-product-catalogue__filters","layout":{"type":"constrained"}} -->
    <div class="wp-block-group blueprint-product-catalogue__filters">
        <!-- wp:heading {"level":2,"fontSize":"large"} -->
        <h2 class="wp-block-heading has-large-font-size"><?php esc_html_e('Filter products', 'blueprint'); ?></h2>
        <!-- /wp:heading -->
        <!-- wp:shortcode -->[facetwp facet="product_categories"]<!-- /wp:shortcode -->
        <!-- wp:shortcode -->[facetwp facet="product_price"]<!-- /wp:shortcode -->
        <!-- wp:shortcode -->[facetwp facet="stock_status"]<!-- /wp:shortcode -->
        <!-- wp:shortcode -->[facetwp selections="true"]<!-- /wp:shortcode -->
    </div>
    <!-- /wp:group -->

    <!-- wp:group {"className":"blueprint-product-catalogue__results","layout":{"type":"default"}} -->
    <div class="wp-block-group blueprint-product-catalogue__results">
        <!-- wp:shortcode -->[facetwp template="products"]<!-- /wp:shortcode -->
    </div>
    <!-- /wp:group -->
</div>
<!-- /wp:group -->
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'FacetWP Product Catalogue Pattern',
                'filename' => 'product-filters.php',
                'language' => 'php',
                'description' => 'A block pattern using FacetWP facets with one Listing Builder product template.',
                'content' => $patternPhp,
                'position' => 5,
            ],
            variationName: 'Listing Builder block pattern',
            tags: array_merge($facetWpTags, [
                $tags['gutenberg'],
                $tags['block-pattern'],
            ]),
        );

        $javascript = <<<'JAVASCRIPT'
(function () {
    'use strict';

    var status = document.querySelector('[data-facetwp-results-status]');

    // {!# snippet: announce_facetwp_refresh #!}
    document.addEventListener('facetwp-refresh', function () {
        var listing = document.querySelector('.facetwp-template');

        listing?.setAttribute('aria-busy', 'true');
    });

    // {!# snippet: restore_ui_after_facetwp_loaded #!}
    document.addEventListener('facetwp-loaded', function () {
        var listing = document.querySelector('.facetwp-template');
        var total = window.FWP?.settings?.pager?.total_rows;

        listing?.removeAttribute('aria-busy');

        if (status && Number.isFinite(Number(total))) {
            status.textContent = Number(total) === 1
                ? '1 product found.'
                : String(total) + ' products found.';
        }
    });
}());
JAVASCRIPT;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'FacetWP Refresh Feedback',
                'filename' => 'facetwp-products.js',
                'language' => 'javascript',
                'description' => 'Busy-state and result-count feedback that reruns after each FacetWP AJAX refresh.',
                'content' => $javascript,
                'position' => 6,
            ],
            variationName: 'Accessible AJAX refresh feedback',
            tags: array_merge($facetWpTags, [
                $tags['ajax'],
                $tags['accessibility'],
            ]),
        );

        $css = <<<'CSS'
/* {!# snippet: responsive_facetwp_catalogue #!} */
.blueprint-product-catalogue {
    display: grid;
    gap: clamp(1.5rem, 4vw, 3rem);
    grid-template-columns: minmax(14rem, 0.3fr) minmax(0, 1fr);
}

.blueprint-product-catalogue__filters {
    align-self: start;
    background: var(--wp--preset--color--base-2, #f1f5f9);
    border: 1px solid var(--wp--preset--color--contrast-3, #cbd5e1);
    border-radius: 0.5rem;
    padding: 1.25rem;
}

/* {!# snippet: facetwp_loading_state #!} */
.facetwp-template[aria-busy='true'] {
    opacity: 0.55;
    pointer-events: none;
    transition: opacity 160ms ease;
}

.facetwp-facet :focus-visible {
    outline: 3px solid var(--wp--preset--color--accent, #2563eb);
    outline-offset: 2px;
}

@media (max-width: 48rem) {
    .blueprint-product-catalogue {
        grid-template-columns: 1fr;
    }
}

@media (prefers-reduced-motion: reduce) {
    .facetwp-template {
        transition: none;
    }
}
CSS;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'FacetWP Product Catalogue Styles',
                'filename' => 'facetwp-products.css',
                'language' => 'css',
                'description' => 'Responsive filter/result layout, visible focus, and non-blocking AJAX loading feedback.',
                'content' => $css,
                'position' => 7,
            ],
            variationName: 'Responsive faceted catalogue',
            tags: array_merge($facetWpTags, [
                $tags['responsive'],
                $tags['accessibility'],
                $tags['reduced-motion'],
            ]),
        );
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedVisualCodeStackHero(User $user, array $tags): void
    {
        $project = $this->exampleProject(
            user: $user,
            signatureFilenames: [
                'hero-visual-code-stack.php',
                'visual-code-stack-hero.css',
                'visual-code-stack-hero.js',
                'presence-cursor-pointer.svg',
            ],
            attributes: [
                'name' => 'CN Visual Hero Code Stack',
                'kind' => 'project',
                'description' => 'A sanitised, reusable WordPress block-pattern implementation of the animated cn-visual-hero__code-stack from the Ollie theme, including conditional assets, accessible tabs, responsive states, reduced motion, and tests.',
                'position' => 5,
            ],
        );

        if ($project === null) {
            return;
        }

        $themeFolder = $project->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => 'theme'],
            ['position' => 0],
        );
        $incFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $themeFolder->id, 'name' => 'inc'],
            ['position' => 0],
        );
        $patternsFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $themeFolder->id, 'name' => 'patterns'],
            ['position' => 1],
        );
        $assetsFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $themeFolder->id, 'name' => 'assets'],
            ['position' => 2],
        );
        $stylesFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $assetsFolder->id, 'name' => 'css'],
            ['position' => 0],
        );
        $scriptsFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $assetsFolder->id, 'name' => 'js'],
            ['position' => 1],
        );
        $imagesFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $assetsFolder->id, 'name' => 'img'],
            ['position' => 2],
        );
        $testsFolder = $project->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => 'tests'],
            ['position' => 1],
        );

        $visualTags = [
            $tags['wordpress'],
            $tags['gutenberg'],
            $tags['block-theme'],
            $tags['block-pattern'],
            $tags['visual-hero'],
            $tags['code-stack'],
        ];

        $readme = <<<'MARKDOWN'
<!-- {!# snippet: source_and_scope #!} -->
# CN Visual Hero Code Stack

This project is a sanitised reference implementation of `.cn-visual-hero__code-stack` from the local Ollie theme used by `christophernathaniel.co.uk`.

The original implementation contains eight editor cards, a 32-second cycle, optional cursor animation, conditional assets, responsive layouts and a reduced-motion fallback. This example preserves those behaviours while replacing machine paths, repository details, destructive shell commands, personal cursor labels and unlicensed image assets with safe placeholders.

<!-- {!# snippet: project_file_map #!} -->
## File map

- `theme/inc/visual-code-stack.php` is a safe module required by the host theme; it does not replace `functions.php`.
- `theme/patterns/hero-visual-code-stack.php` renders a complete no-JavaScript fallback.
- `theme/assets/js/visual-code-stack-hero.js` adds card rotation, accessible tabs and pause controls.
- `theme/assets/css/visual-code-stack-hero.css` provides the stack, editor chrome and responsive layout.
- `theme/assets/css/tokens.css` keeps the visual system portable.
- `theme/assets/css/cursor.css`, `cursor-utility.js` and the SVG pointer are optional presentation layers.
- `theme/theme-json.fragment.json` is merged into the host `theme.json`; it is not a replacement theme configuration.

<!-- {!# snippet: installation_and_usage #!} -->
## Installation

1. Copy `patterns/hero-visual-code-stack.php`, the three asset folders, and `inc/visual-code-stack.php` into the same relative paths in the host theme.
2. Add `require_once get_theme_file_path('/inc/visual-code-stack.php');` to the host theme's existing `functions.php`.
3. Merge the palette, font-family, and CSS values from `theme-json.fragment.json` into the host theme's existing `theme.json` instead of overwriting it.
4. Insert the “Visual code stack hero” pattern and replace the safe demonstration card content with escaped display strings.
5. Keep the supplied unique IDs, inert states, pause behaviour, and reduced-motion fallback when adapting the markup.

Run the browser checks against a WordPress preview containing the pattern with `VISUAL_HERO_BASE_URL=https://project.test npm test`.

<!-- {!# snippet: accessibility_contract #!} -->
## Accessibility contract

The PHP pattern is meaningful without JavaScript. JavaScript uses roving tab focus and arrow-key navigation, exposes only the front card to assistive technology, and provides a pause button. `prefers-reduced-motion` disables cycling and hides the decorative cursor.
MARKDOWN;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: null,
            user: $user,
            attributes: [
                'title' => 'Visual Code Stack Implementation Guide',
                'filename' => 'README.md',
                'language' => 'markdown',
                'description' => 'Source lineage, portable file map, installation steps, and accessibility requirements for the hero.',
                'content' => $readme,
                'position' => 0,
            ],
            variationName: 'Sanitised Ollie implementation guide',
            tags: array_merge($visualTags, [
                $tags['progressive-enhancement'],
                $tags['accessibility'],
                $tags['reduced-motion'],
            ]),
        );

        $functionsPhp = <<<'PHP'
<?php
/**
 * Visual code stack hero assets.
 *
 * @package VisualCodeStack
 */

namespace VisualCodeStack;

// {!# snippet: asset_version_from_file #!}
function asset_version(string $relative_path): string
{
    $absolute_path = get_theme_file_path($relative_path);

    return file_exists($absolute_path)
        ? (string) filemtime($absolute_path)
        : (string) wp_get_theme()->get('Version');
}

// {!# snippet: enqueue_visual_hero_for_block_templates #!}
function enqueue_visual_hero_assets(): void
{
    // The module loads small scoped assets on every frontend request so the
    // pattern also works inside templates, template parts and synced patterns.
    wp_enqueue_style(
        'visual-code-stack-tokens',
        get_theme_file_uri('assets/css/tokens.css'),
        [],
        asset_version('assets/css/tokens.css'),
    );
    wp_enqueue_style(
        'visual-code-stack-hero',
        get_theme_file_uri('assets/css/visual-code-stack-hero.css'),
        ['visual-code-stack-tokens'],
        asset_version('assets/css/visual-code-stack-hero.css'),
    );
    wp_enqueue_style(
        'visual-code-stack-cursor',
        get_theme_file_uri('assets/css/cursor.css'),
        ['visual-code-stack-hero'],
        asset_version('assets/css/cursor.css'),
    );
    wp_enqueue_script(
        'visual-code-stack-hero',
        get_theme_file_uri('assets/js/visual-code-stack-hero.js'),
        [],
        asset_version('assets/js/visual-code-stack-hero.js'),
        ['strategy' => 'defer', 'in_footer' => true],
    );
    wp_enqueue_script(
        'visual-code-stack-cursor',
        get_theme_file_uri('assets/js/cursor-utility.js'),
        ['visual-code-stack-hero'],
        asset_version('assets/js/cursor-utility.js'),
        ['strategy' => 'defer', 'in_footer' => true],
    );
}
add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\enqueue_visual_hero_assets');

// {!# snippet: enqueue_visual_hero_in_editor #!}
function enqueue_visual_hero_editor_assets(): void
{
    wp_enqueue_style(
        'visual-code-stack-editor-tokens',
        get_theme_file_uri('assets/css/tokens.css'),
        [],
        asset_version('assets/css/tokens.css'),
    );
    wp_enqueue_style(
        'visual-code-stack-editor',
        get_theme_file_uri('assets/css/visual-code-stack-hero.css'),
        ['visual-code-stack-editor-tokens'],
        asset_version('assets/css/visual-code-stack-hero.css'),
    );
}
add_action('enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_visual_hero_editor_assets');
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $incFolder,
            user: $user,
            attributes: [
                'title' => 'Visual Hero Theme Module',
                'filename' => 'visual-code-stack.php',
                'language' => 'php',
                'description' => 'A host-theme include with reliable block-template assets, file-based cache busting, deferred scripts, and editor parity.',
                'content' => $functionsPhp,
                'position' => 0,
            ],
            variationName: 'Block-theme integration module',
            tags: array_merge($visualTags, [
                $tags['progressive-enhancement'],
            ]),
        );

        $themeJson = <<<'JSON'
{
    "$schema": "https://schemas.wp.org/trunk/theme.json",
    "version": 3,
    "settings": {
        "appearanceTools": true,
        "color": {
            "palette": [
                { "slug": "hero-canvas", "name": "Hero canvas", "color": "#e7ecf3" },
                { "slug": "hero-ink", "name": "Hero ink", "color": "#172033" },
                { "slug": "hero-accent", "name": "Hero accent", "color": "#2563eb" }
            ]
        },
        "typography": {
            "fontFamilies": [
                {
                    "slug": "hero-mono",
                    "name": "Hero monospace",
                    "fontFamily": "ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace"
                }
            ]
        }
    },
    "styles": {
        "css": ".cn-visual-hero { --cn-bg: var(--wp--preset--color--hero-canvas); --cn-text: var(--wp--preset--color--hero-ink); --cn-accent: var(--wp--preset--color--hero-accent); }"
    }
}
JSON;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $themeFolder,
            user: $user,
            attributes: [
                'title' => 'Visual Hero Theme JSON Fragment',
                'filename' => 'theme-json.fragment.json',
                'language' => 'json',
                'description' => 'A mergeable theme.json v3 palette and monospace fragment consumed by the visual hero.',
                'content' => $themeJson,
                'position' => 1,
            ],
            variationName: 'Mergeable theme JSON v3 fragment',
            tags: array_merge($visualTags, [
                $tags['theme-json'],
                $tags['design-tokens'],
            ]),
        );

        $patternPhp = <<<'PHP'
<?php
/**
 * Title: Visual code stack hero
 * Slug: visual-code-stack/hero
 * Categories: banner, featured
 * Viewport Width: 1440
 *
 * @package VisualCodeStack
 */

// {!# snippet: define_safe_code_cards #!}
$visual_code_cards = [
    [
        'label' => 'index.js',
        'icon' => 'JS',
        'status' => 'ready',
        'panels' => [
            ['label' => 'index.js', 'lines' => ['const blocks = discoverBlocks();', 'registerBlocks(blocks);', 'bootEditor();']],
            ['label' => 'tests', 'lines' => ['PASS block registration', 'PASS editor hydration', 'PASS keyboard tabs']],
        ],
    ],
    [
        'label' => 'theme.json',
        'icon' => '{}',
        'status' => 'v3',
        'panels' => [
            ['label' => 'theme.json', 'lines' => ['"version": 3,', '"appearanceTools": true,', '"spacing": { "units": ["rem"] }']],
            ['label' => 'tokens', 'lines' => ['hero.canvas → #e7ecf3', 'hero.ink → #172033', 'hero.accent → #2563eb']],
        ],
    ],
    [
        'label' => 'block.php',
        'icon' => 'PHP',
        'status' => 'linted',
        'panels' => [
            ['label' => 'block.php', 'lines' => ['register_block_type($path);', '$wrapper = get_block_wrapper_attributes();', 'echo wp_kses_post($markup);']],
            ['label' => 'review', 'lines' => ['Escaping boundary documented', 'Text domain isolated', 'Theme prefix selected']],
        ],
    ],
    [
        'label' => 'Panel.tsx',
        'icon' => 'TS',
        'status' => 'typed',
        'panels' => [
            ['label' => 'Panel.tsx', 'lines' => ['type PanelProps = { title: string };', 'export function Panel(props: PanelProps) {', '    return <aside>{props.title}</aside>;', '}']],
            ['label' => 'a11y', 'lines' => ['PASS labelled region', 'PASS focus order', 'PASS reduced motion']],
        ],
    ],
    [
        'label' => 'terminal',
        'icon' => '$',
        'status' => 'pushed',
        'panels' => [
            ['label' => 'terminal', 'lines' => ['$ git status --short', '$ git add assets patterns', '$ git commit -m "Add visual hero"', '$ git push origin feature/visual-hero']],
            ['label' => 'review', 'lines' => ['Pull request opened', 'Required checks queued', 'Reviewers requested']],
        ],
    ],
    [
        'label' => 'server',
        'icon' => 'SH',
        'status' => 'healthy',
        'panels' => [
            ['label' => 'server', 'lines' => ['$ ./deploy.sh staging release.tar.gz', 'Uploading immutable artifact', 'Running health checks', 'Deployment healthy']],
            ['label' => 'metrics', 'lines' => ['HTTP 200', 'p95 182ms', 'Error rate 0.00%']],
        ],
    ],
    [
        'label' => 'checks',
        'icon' => 'QA',
        'status' => 'passed',
        'panels' => [
            ['label' => 'checks', 'lines' => ['PASS JavaScript syntax', 'PASS PHP lint', 'PASS accessibility states', 'PASS Lighthouse budget']],
            ['label' => 'coverage', 'lines' => ['Statements 94%', 'Branches 91%', 'Functions 96%']],
        ],
    ],
    [
        'label' => 'deploy',
        'icon' => 'CD',
        'status' => 'live',
        'panels' => [
            ['label' => 'deploy', 'lines' => ['Release approved', 'Artifact signature verified', 'Production rollout complete', 'Monitoring window active']],
            ['label' => 'rollback', 'lines' => ['Known-good release recorded', 'Rollback workflow available', 'Audit trail retained']],
        ],
    ],
];
$visual_code_stack_id = wp_unique_id('visual-code-stack-');
$highlight_visual_code_line = static function (string $line): string {
    $highlighted = esc_html($line);
    $highlighted = preg_replace(
        [
            '/\b(const|type|export|function|return|true|false)\b/',
            '/\b(PASS|healthy|approved|verified|complete)\b/i',
            '/(?<![A-Za-z0-9_-])(#[0-9a-f]{6}|[0-9]+(?:\.[0-9]+)?%?)(?![A-Za-z0-9_-])/i',
            '/^\$/',
        ],
        [
            '<span class="cn-token-keyword">$1</span>',
            '<span class="cn-token-success">$1</span>',
            '<span class="cn-token-literal">$1</span>',
            '<span class="cn-token-prompt">$</span>',
        ],
        $highlighted,
    );

    return wp_kses($highlighted ?? '', ['span' => ['class' => true]]);
};

// {!# snippet: render_accessible_code_stack #!}
?>
<!-- wp:group {"tagName":"section","align":"full","className":"cn-visual-hero","layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull cn-visual-hero">
    <div class="cn-visual-hero__intro alignwide">
        <div class="cn-visual-hero__copy">
            <p class="cn-visual-hero__eyebrow"><?php esc_html_e('01. Introduction', 'visual-code-stack'); ?></p>
            <h1 class="wp-block-heading cn-visual-hero__title"><?php esc_html_e('Build clear systems, then show the work.', 'visual-code-stack'); ?></h1>
            <p class="cn-visual-hero__description"><?php esc_html_e('A progressive code-stack hero with valid fallbacks, accessible controls and motion preferences.', 'visual-code-stack'); ?></p>
        </div>

        <div class="cn-visual-hero__editor-column">
            <div id="<?php echo esc_attr($visual_code_stack_id); ?>" class="cn-visual-hero__code-stack" data-code-stack data-cycle-ms="4000" role="group" aria-label="<?php esc_attr_e('Code workflow previews', 'visual-code-stack'); ?>">
                <?php foreach ($visual_code_cards as $card_index => $card) : ?>
                    <?php $card_id = $visual_code_stack_id . '-card-' . (string) $card_index; ?>
                    <article
                        class="cn-visual-hero__code-editor"
                        data-code-card
                        data-stack-position="<?php echo esc_attr((string) $card_index); ?>"
                        aria-hidden="<?php echo 0 === $card_index ? 'false' : 'true'; ?>"
                        <?php echo 0 === $card_index ? '' : 'inert'; ?>
                    >
                        <div class="cn-visual-code__tabs" role="tablist" aria-label="<?php echo esc_attr($card['label']); ?>">
                            <?php foreach ($card['panels'] as $panel_index => $panel) : ?>
                                <?php $panel_id = $card_id . '-panel-' . (string) $panel_index; ?>
                                <button
                                    class="cn-visual-code__tab<?php echo 0 === $panel_index ? ' is-active' : ''; ?>"
                                    type="button"
                                    role="tab"
                                    id="<?php echo esc_attr($panel_id . '-tab'); ?>"
                                    aria-controls="<?php echo esc_attr($panel_id); ?>"
                                    aria-selected="<?php echo 0 === $panel_index ? 'true' : 'false'; ?>"
                                    tabindex="<?php echo 0 === $panel_index ? '0' : '-1'; ?>"
                                    data-code-tab
                                >
                                    <span class="cn-visual-code__file-icon" aria-hidden="true"><?php echo esc_html($card['icon']); ?></span>
                                    <?php echo esc_html($panel['label']); ?>
                                    <?php if (0 === $panel_index) : ?>
                                        <span class="cn-visual-code__status"><?php echo esc_html($card['status']); ?></span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ($card['panels'] as $panel_index => $panel) : ?>
                            <?php $panel_id = $card_id . '-panel-' . (string) $panel_index; ?>
                            <div
                                class="cn-visual-code__panel"
                                id="<?php echo esc_attr($panel_id); ?>"
                                role="tabpanel"
                                aria-labelledby="<?php echo esc_attr($panel_id . '-tab'); ?>"
                                <?php echo 0 === $panel_index ? '' : 'hidden'; ?>
                            >
                                <ol class="cn-visual-code__lines">
                                    <?php foreach ($panel['lines'] as $line) : ?>
                                        <li><code><?php echo $highlight_visual_code_line($line); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped then restricted to span class markup. ?></code></li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                        <?php endforeach; ?>
                    </article>
                <?php endforeach; ?>

                <span class="cn-code-stack-cursor" aria-hidden="true"></span>
                <button class="cn-code-stack-pause" type="button" aria-pressed="false" data-code-stack-pause data-pause-label="<?php esc_attr_e('Pause animation', 'visual-code-stack'); ?>" data-resume-label="<?php esc_attr_e('Resume animation', 'visual-code-stack'); ?>" hidden>
                    <?php esc_html_e('Pause animation', 'visual-code-stack'); ?>
                </button>
            </div>
        </div>
    </div>
</section>
<!-- /wp:group -->
PHP;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $patternsFolder,
            user: $user,
            attributes: [
                'title' => 'Visual Code Stack Hero Pattern',
                'filename' => 'hero-visual-code-stack.php',
                'language' => 'php',
                'description' => 'Eight safe code cards, server-rendered fallback markup, ARIA tabs, pause control, and escaped output.',
                'content' => $patternPhp,
                'position' => 0,
            ],
            variationName: 'Server-rendered progressive pattern',
            tags: array_merge($visualTags, [
                $tags['accessibility'],
                $tags['progressive-enhancement'],
            ]),
        );

        $tokensCss = <<<'CSS'
/* {!# snippet: visual_hero_design_tokens #!} */
:root {
    --cn-bg: #e7ecf3;
    --cn-text: #172033;
    --cn-text-muted: #536176;
    --cn-accent: #2563eb;
    --cn-bg-card: #161a22;
    --cn-border-strong: rgb(23 32 51 / 24%);
    --cn-code-border: rgb(255 255 255 / 8%);
    --cn-code-text: #dce6f5;
    --cn-code-muted: #77859a;
    --cn-code-blue: #7db7ff;
    --cn-code-cyan: #72d8e8;
    --cn-code-violet: #b6a0ff;
    --cn-code-green: #8ad4ad;
    --cn-font-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

/* {!# snippet: dark_mode_token_override #!} */
@media (prefers-color-scheme: dark) {
    :root {
        --cn-bg: #111722;
        --cn-text: #edf3fb;
        --cn-text-muted: #a4b0c0;
        --cn-border-strong: rgb(231 236 243 / 22%);
    }
}
CSS;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $stylesFolder,
            user: $user,
            attributes: [
                'title' => 'Visual Hero Design Tokens',
                'filename' => 'tokens.css',
                'language' => 'css',
                'description' => 'Portable blue-grey canvas, editor, syntax, typography, and dark-mode tokens.',
                'content' => $tokensCss,
                'position' => 0,
            ],
            variationName: 'Blue-grey visual system',
            tags: array_merge($visualTags, [$tags['design-tokens']]),
        );

        $heroCss = <<<'CSS'
/* {!# snippet: visual_hero_layout #!} */
.cn-visual-hero {
    background: var(--cn-bg);
    color: var(--cn-text);
    isolation: isolate;
    min-height: clamp(42rem, 72vw, 58rem);
    overflow: hidden;
    padding: clamp(6rem, 10vw, 9rem) clamp(1.25rem, 4vw, 4rem);
    position: relative;
}

.cn-visual-hero__intro {
    align-items: center;
    display: grid;
    gap: clamp(2rem, 5vw, 5rem);
    grid-template-columns: minmax(0, 1fr) minmax(22rem, 0.85fr);
    margin-inline: auto;
    max-width: 86rem;
}

.cn-visual-hero__copy {
    display: grid;
    gap: 1.25rem;
}

.cn-visual-hero__eyebrow {
    color: var(--cn-accent);
    font-family: var(--cn-font-mono);
    font-size: 0.75rem;
    font-weight: 700;
    margin: 0;
    text-transform: uppercase;
}

.cn-visual-hero__title {
    font-size: clamp(3rem, 7vw, 7rem);
    letter-spacing: -0.045em;
    line-height: 0.94;
    margin: 0;
    max-width: 11ch;
}

.cn-visual-hero__description {
    color: var(--cn-text-muted);
    font-size: clamp(1rem, 1.4vw, 1.2rem);
    line-height: 1.6;
    margin: 0;
    max-width: 34rem;
}

/* {!# snippet: animated_code_stack_positions #!} */
.cn-visual-hero__code-stack {
    height: clamp(18rem, 28vw, 25rem);
    margin-inline: auto;
    max-width: 38rem;
    position: relative;
    width: 100%;
}

.cn-visual-hero__code-editor {
    background: var(--cn-bg-card);
    border: 1px solid var(--cn-code-border);
    border-radius: clamp(1rem, 2vw, 1.5rem);
    bottom: 0;
    box-shadow: 0 1.5rem 4rem rgb(0 0 0 / 22%);
    color: var(--cn-code-text);
    display: grid;
    font-family: var(--cn-font-mono);
    grid-template-rows: auto 1fr;
    height: calc(100% - 2rem);
    left: 0;
    overflow: hidden;
    position: absolute;
    right: 0;
    transform-origin: top center;
    transition: opacity 500ms ease, transform 700ms cubic-bezier(0.16, 1, 0.3, 1);
}

.cn-visual-hero__code-editor[data-stack-position="0"] {
    opacity: 1;
    transform: translateY(0) scale(1);
    z-index: 8;
}

.cn-visual-hero__code-editor[data-stack-position="1"] {
    opacity: 0.74;
    transform: translateY(-0.75rem) scale(0.96);
    z-index: 7;
}

.cn-visual-hero__code-editor[data-stack-position="2"] {
    opacity: 0.42;
    transform: translateY(-1.4rem) scale(0.91);
    z-index: 6;
}

.cn-visual-hero__code-editor[data-stack-position="3"] {
    opacity: 0.16;
    transform: translateY(-1.9rem) scale(0.86);
    z-index: 5;
}

.cn-visual-hero__code-editor[data-stack-position="4"],
.cn-visual-hero__code-editor[data-stack-position="5"],
.cn-visual-hero__code-editor[data-stack-position="6"],
.cn-visual-hero__code-editor[data-stack-position="7"] {
    opacity: 0;
    transform: translateY(-2.2rem) scale(0.82);
    z-index: 1;
}

/* {!# snippet: ide_tabs_and_code_lines #!} */
.cn-visual-code__tabs {
    align-items: stretch;
    background: #12161e;
    border-bottom: 1px solid var(--cn-code-border);
    display: flex;
    min-height: 3.25rem;
    overflow-x: auto;
}

.cn-visual-code__tab {
    align-items: center;
    background: transparent;
    border: 0;
    border-right: 1px solid var(--cn-code-border);
    color: var(--cn-code-muted);
    cursor: pointer;
    display: flex;
    font: inherit;
    font-size: 0.72rem;
    gap: 0.55rem;
    padding-inline: 1rem;
}

.cn-visual-code__tab.is-active {
    background: #1a202a;
    color: var(--cn-code-text);
}

.cn-visual-code__tab:focus-visible,
.cn-code-stack-pause:focus-visible {
    outline: 2px solid var(--cn-code-blue);
    outline-offset: -3px;
}

.cn-visual-code__file-icon,
.cn-visual-code__status {
    background: rgb(255 255 255 / 12%);
    border-radius: 0.25rem;
    font-size: 0.58rem;
    font-weight: 800;
    padding: 0.18rem 0.3rem;
}

.cn-visual-code__status {
    margin-inline-start: 0.35rem;
    text-transform: uppercase;
}

.cn-visual-code__panel {
    min-height: 0;
    overflow: auto;
    padding: 1rem 1.2rem 1.25rem;
}

.cn-visual-code__lines {
    list-style-position: outside;
    margin: 0;
    padding-inline-start: 2.25rem;
}

.cn-visual-code__lines li {
    border-bottom: 1px solid rgb(255 255 255 / 6%);
    color: var(--cn-code-muted);
    min-height: 1.55rem;
    padding-inline-start: 0.5rem;
}

.cn-visual-code__lines code {
    color: var(--cn-code-text);
    font: inherit;
    white-space: pre-wrap;
}

.cn-token-keyword {
    color: var(--cn-code-violet);
}

.cn-token-success {
    color: var(--cn-code-green);
}

.cn-token-literal {
    color: var(--cn-code-cyan);
}

.cn-token-prompt {
    color: var(--cn-code-blue);
}

.cn-code-stack-pause {
    background: rgb(18 22 30 / 92%);
    border: 1px solid var(--cn-code-border);
    border-radius: 999px;
    bottom: 0.7rem;
    color: var(--cn-code-text);
    cursor: pointer;
    font: 600 0.68rem/1 var(--cn-font-mono);
    padding: 0.55rem 0.75rem;
    position: absolute;
    right: 0.7rem;
    z-index: 20;
}

/* {!# snippet: responsive_visual_hero #!} */
@media (max-width: 800px) {
    .cn-visual-hero {
        min-height: 0;
    }

    .cn-visual-hero__intro {
        grid-template-columns: 1fr;
    }

    .cn-visual-hero__code-stack {
        max-width: 34rem;
    }
}

@media (max-width: 540px) {
    .cn-visual-hero {
        padding-inline: 1rem;
    }

    .cn-visual-hero__code-stack {
        height: 17rem;
    }

    .cn-visual-code__tab {
        padding-inline: 0.75rem;
    }
}

/* {!# snippet: reduced_motion_fallback #!} */
@media (prefers-reduced-motion: reduce) {
    .cn-visual-hero__code-editor {
        transition: none;
    }

    .cn-visual-hero__code-editor:not([data-stack-position="0"]) {
        opacity: 0;
    }

    .cn-code-stack-cursor {
        display: none;
    }
}
CSS;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $stylesFolder,
            user: $user,
            attributes: [
                'title' => 'Visual Code Stack Hero Styles',
                'filename' => 'visual-code-stack-hero.css',
                'language' => 'css',
                'description' => 'Complete hero layout, layered editor positions, IDE chrome, responsive rules, and reduced-motion fallback.',
                'content' => $heroCss,
                'position' => 1,
            ],
            variationName: 'Responsive animated stack',
            tags: array_merge($visualTags, [
                $tags['animation'],
                $tags['responsive'],
                $tags['reduced-motion'],
                $tags['accessibility'],
            ]),
        );

        $cursorCss = <<<'CSS'
/* {!# snippet: decorative_presence_cursor #!} */
.cn-code-stack-cursor {
    background: url('../img/presence-cursor-pointer.svg') center / contain no-repeat;
    height: 2.8rem;
    left: 14%;
    pointer-events: none;
    position: absolute;
    top: 25%;
    transform: translate3d(0, 0, 0);
    width: 2.8rem;
    z-index: 18;
}

.cn-code-stack-cursor.is-running {
    animation: cn-code-stack-cursor-move 4s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes cn-code-stack-cursor-move {
    0%, 12% {
        transform: translate3d(0, 0, 0) scale(0.9);
    }

    45% {
        transform: translate3d(5.4rem, 0.35rem, 0) scale(0.9);
    }

    72%, 100% {
        transform: translate3d(5.4rem, 2.6rem, 0) scale(0.82);
    }
}
CSS;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $stylesFolder,
            user: $user,
            attributes: [
                'title' => 'Optional Presence Cursor Styles',
                'filename' => 'cursor.css',
                'language' => 'css',
                'description' => 'Decorative cursor placement and a non-essential animation that disappears for reduced motion.',
                'content' => $cursorCss,
                'position' => 2,
            ],
            variationName: 'Decorative cursor layer',
            tags: array_merge($visualTags, [
                $tags['animation'],
                $tags['reduced-motion'],
            ]),
        );

        $heroJavaScript = <<<'JAVASCRIPT'
(function () {
    'use strict';

    // {!# snippet: activate_accessible_code_tab #!}
    function activateTab(tab, focusTab) {
        var editor = tab.closest('[data-code-card]');

        if (! editor) {
            return;
        }

        editor.querySelectorAll('[data-code-tab]').forEach(function (candidate) {
            var isActive = candidate === tab;
            var panel = editor.querySelector('#' + candidate.getAttribute('aria-controls'));

            candidate.classList.toggle('is-active', isActive);
            candidate.setAttribute('aria-selected', isActive ? 'true' : 'false');
            candidate.tabIndex = isActive ? 0 : -1;

            if (panel) {
                panel.hidden = ! isActive;
            }
        });

        if (focusTab) {
            tab.focus();
        }
    }

    // {!# snippet: add_arrow_key_tab_navigation #!}
    function handleTabKeydown(event) {
        var tab = event.target.closest('[data-code-tab]');

        if (! tab || ! ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
            return;
        }

        var tabs = Array.from(tab.closest('[role="tablist"]').querySelectorAll('[data-code-tab]'));
        var currentIndex = tabs.indexOf(tab);
        var nextIndex = event.key === 'Home'
            ? 0
            : event.key === 'End'
                ? tabs.length - 1
                : (currentIndex + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;

        event.preventDefault();
        activateTab(tabs[nextIndex], true);
    }

    // {!# snippet: rotate_code_cards_accessibly #!}
    function setupCodeStack(stack) {
        var cards = Array.from(stack.querySelectorAll('[data-code-card]'));
        var pauseButton = stack.querySelector('[data-code-stack-pause]');
        var cursor = stack.querySelector('.cn-code-stack-cursor');
        var cycleMilliseconds = Number.parseInt(stack.dataset.cycleMs || '4000', 10);
        var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        var activeIndex = 0;
        var timer = null;
        var manuallyPaused = false;
        var pointerPaused = false;
        var focusPaused = false;

        function updatePauseControl() {
            if (! pauseButton) {
                return;
            }

            pauseButton.hidden = reducedMotion.matches;
            pauseButton.textContent = manuallyPaused
                ? pauseButton.dataset.resumeLabel
                : pauseButton.dataset.pauseLabel;
        }

        function renderCards() {
            cards.forEach(function (card, cardIndex) {
                var position = (cardIndex - activeIndex + cards.length) % cards.length;
                var isActive = position === 0;

                card.dataset.stackPosition = String(position);
                card.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                card.inert = ! isActive;
            });
        }

        function stop() {
            window.clearInterval(timer);
            timer = null;
            cursor && cursor.classList.remove('is-running');
        }

        function start() {
            stop();

            if (
                reducedMotion.matches
                || manuallyPaused
                || pointerPaused
                || focusPaused
                || cards.length < 2
            ) {
                return;
            }

            cursor && cursor.classList.add('is-running');
            timer = window.setInterval(function () {
                activeIndex = (activeIndex + 1) % cards.length;
                renderCards();
            }, cycleMilliseconds);
        }

        pauseButton && pauseButton.addEventListener('click', function () {
            manuallyPaused = ! manuallyPaused;
            pauseButton.setAttribute('aria-pressed', manuallyPaused ? 'true' : 'false');
            updatePauseControl();
            start();
        });
        stack.addEventListener('pointerenter', function () {
            pointerPaused = true;
            start();
        });
        stack.addEventListener('pointerleave', function () {
            pointerPaused = false;
            start();
        });
        stack.addEventListener('focusin', function (event) {
            focusPaused = event.target !== pauseButton;
            start();
        });
        stack.addEventListener('focusout', function (event) {
            focusPaused = event.relatedTarget instanceof Node
                && stack.contains(event.relatedTarget)
                && event.relatedTarget !== pauseButton;
            start();
        });
        stack.addEventListener('click', function (event) {
            var tab = event.target.closest('[data-code-tab]');

            if (tab) {
                activateTab(tab, false);
            }
        });
        stack.addEventListener('keydown', handleTabKeydown);
        reducedMotion.addEventListener('change', function () {
            updatePauseControl();
            start();
        });

        renderCards();
        updatePauseControl();
        start();
    }

    // {!# snippet: initialise_visual_code_stacks #!}
    function initialise() {
        document.querySelectorAll('[data-code-stack]').forEach(setupCodeStack);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialise, { once: true });
    } else {
        initialise();
    }
}());
JAVASCRIPT;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $scriptsFolder,
            user: $user,
            attributes: [
                'title' => 'Visual Code Stack Behaviour',
                'filename' => 'visual-code-stack-hero.js',
                'language' => 'javascript',
                'description' => 'Accessible tab switching, arrow keys, card rotation, pause/resume, inert hidden cards, and motion preference changes.',
                'content' => $heroJavaScript,
                'position' => 0,
            ],
            variationName: 'Accessible progressive enhancement',
            tags: array_merge($visualTags, [
                $tags['animation'],
                $tags['accessibility'],
                $tags['reduced-motion'],
                $tags['progressive-enhancement'],
            ]),
        );

        $cursorJavaScript = <<<'JAVASCRIPT'
(function () {
    'use strict';

    // {!# snippet: restart_cursor_with_each_card #!}
    function restartCursor(stack) {
        var cursor = stack.querySelector('.cn-code-stack-cursor');

        if (! cursor || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        cursor.classList.remove('is-running');
        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                cursor.classList.add('is-running');
            });
        });
    }

    // {!# snippet: observe_stack_position_changes #!}
    document.querySelectorAll('[data-code-stack]').forEach(function (stack) {
        var observer = new MutationObserver(function (records) {
            if (records.some(function (record) {
                return record.type === 'attributes' && record.attributeName === 'data-stack-position';
            })) {
                restartCursor(stack);
            }
        });

        stack.querySelectorAll('[data-code-card]').forEach(function (card) {
            observer.observe(card, { attributes: true });
        });
        restartCursor(stack);
    });
}());
JAVASCRIPT;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $scriptsFolder,
            user: $user,
            attributes: [
                'title' => 'Optional Cursor Utility',
                'filename' => 'cursor-utility.js',
                'language' => 'javascript',
                'description' => 'A small isolated observer that restarts the decorative cursor when the front card changes.',
                'content' => $cursorJavaScript,
                'position' => 1,
            ],
            variationName: 'Card-synchronised cursor',
            tags: array_merge($visualTags, [
                $tags['animation'],
                $tags['reduced-motion'],
            ]),
        );

        $cursorSvg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" role="img" aria-labelledby="title">
    <title id="title">Decorative blue pointer</title>
    <path d="M8 5v30l8-7 6 13 6-3-6-12h12z" fill="#2563eb" stroke="#f8fafc" stroke-width="2" stroke-linejoin="round"/>
</svg>
SVG;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $imagesFolder,
            user: $user,
            attributes: [
                'title' => 'Generic Presence Cursor',
                'filename' => 'presence-cursor-pointer.svg',
                'language' => 'xml',
                'description' => 'A generic local SVG pointer replacing the personal cursor label and avoiding external asset licensing.',
                'content' => $cursorSvg,
                'position' => 0,
            ],
            variationName: 'Generic blue pointer',
            tags: array_merge($visualTags, [$tags['design-tokens']]),
        );

        $assetNotes = <<<'MARKDOWN'
<!-- {!# snippet: image_asset_policy #!} -->
# Image assets

The live Ollie mosaic uses four photographic assets. Their redistribution licence is not documented, so this reference project intentionally does not copy them.

Use WordPress Media Library selections, licensed project imagery, or remote placeholders during prototyping. Always provide meaningful alternative text when an image conveys content; use an empty `alt` attribute when it is purely decorative.

<!-- {!# snippet: cursor_asset_policy #!} -->
The included pointer SVG is generic and local. It replaces the personal presence label used by the live site and can be removed without affecting the code-stack content.
MARKDOWN;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $imagesFolder,
            user: $user,
            attributes: [
                'title' => 'Visual Hero Asset Policy',
                'filename' => 'README.md',
                'language' => 'markdown',
                'description' => 'Licensing and accessibility guidance for replacing the omitted Ollie photographs and cursor branding.',
                'content' => $assetNotes,
                'position' => 1,
            ],
            variationName: 'Safe asset replacement notes',
            tags: array_merge($visualTags, [$tags['accessibility']]),
        );

        $packageJson = <<<'JSON'
{
    "name": "visual-code-stack-reference",
    "private": true,
    "scripts": {
        "test": "playwright test",
        "test:visual": "playwright test --project=chromium"
    },
    "devDependencies": {
        "@playwright/test": "^1.55.0"
    }
}
JSON;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: null,
            user: $user,
            attributes: [
                'title' => 'Visual Hero Test Package',
                'filename' => 'package.json',
                'language' => 'json',
                'description' => 'Minimal Playwright package metadata for interaction and reduced-motion coverage.',
                'content' => $packageJson,
                'position' => 1,
            ],
            variationName: 'Playwright test tooling',
            tags: array_merge($visualTags, [$tags['testing']]),
        );

        $playwrightConfig = <<<'JAVASCRIPT'
const { defineConfig, devices } = require('@playwright/test');

// {!# snippet: configure_wordpress_preview_target #!}
module.exports = defineConfig({
    testDir: './tests',
    retries: process.env.CI ? 2 : 0,
    use: {
        baseURL: process.env.VISUAL_HERO_BASE_URL || 'http://visual-code-stack.test',
        trace: 'on-first-retry',
    },
    // {!# snippet: named_chromium_project #!}
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
JAVASCRIPT;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: null,
            user: $user,
            attributes: [
                'title' => 'Visual Hero Playwright Configuration',
                'filename' => 'playwright.config.js',
                'language' => 'javascript',
                'description' => 'A named Chromium project and configurable WordPress preview base URL for the supplied browser tests.',
                'content' => $playwrightConfig,
                'position' => 2,
            ],
            variationName: 'WordPress preview test target',
            tags: array_merge($visualTags, [$tags['testing']]),
        );

        $playwrightTest = <<<'JAVASCRIPT'
import { expect, test } from '@playwright/test';

// {!# snippet: test_code_stack_controls #!}
test('cycles cards and exposes a working pause control', async ({ page }) => {
    await page.goto('/');

    const stack = page.locator('[data-code-stack]');
    const pause = stack.locator('[data-code-stack-pause]');

    await expect(stack.locator('[data-code-card]')).toHaveCount(8);
    await expect(pause).toHaveAttribute('aria-pressed', 'false');
    const firstCardId = await stack.locator('[data-code-card][data-stack-position="0"]').getAttribute('id');

    await expect.poll(
        () => stack.locator('[data-code-card][data-stack-position="0"]').getAttribute('id'),
        { timeout: 6000 },
    ).not.toBe(firstCardId);

    await pause.click();
    await expect(pause).toHaveAttribute('aria-pressed', 'true');
    await expect(pause).toHaveText('Resume animation');
});

// {!# snippet: test_keyboard_tabs #!}
test('supports arrow-key tab navigation', async ({ page }) => {
    await page.goto('/');

    const frontCard = page.locator('[data-code-card][data-stack-position="0"]');
    const firstTab = frontCard.getByRole('tab').first();
    const secondTab = frontCard.getByRole('tab').nth(1);

    await firstTab.focus();
    await page.keyboard.press('ArrowRight');
    await expect(secondTab).toBeFocused();
    await expect(secondTab).toHaveAttribute('aria-selected', 'true');
});

// {!# snippet: test_reduced_motion #!}
test('keeps one stable card for reduced motion', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('/');

    await expect(page.locator('.cn-code-stack-cursor')).toBeHidden();
    await expect(page.locator('[data-code-stack-pause]')).toBeHidden();
    await expect(page.locator('[data-code-card][aria-hidden="false"]')).toHaveCount(1);
});

// {!# snippet: test_no_javascript_fallback #!}
test('keeps inactive cards inert without JavaScript', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();

    await page.goto('/');
    await expect(page.locator('[data-code-stack-pause]')).toBeHidden();
    await expect(page.locator('[data-code-card][inert]')).toHaveCount(7);
    await context.close();
});
JAVASCRIPT;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $testsFolder,
            user: $user,
            attributes: [
                'title' => 'Visual Hero Browser Tests',
                'filename' => 'visual-code-stack-hero.spec.js',
                'language' => 'javascript',
                'description' => 'Interaction, keyboard navigation, and reduced-motion checks for the hero stack.',
                'content' => $playwrightTest,
                'position' => 0,
            ],
            variationName: 'Playwright interaction coverage',
            tags: array_merge($visualTags, [
                $tags['testing'],
                $tags['accessibility'],
                $tags['reduced-motion'],
            ]),
        );
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedGitHubReference(User $user, array $tags): void
    {
        $project = $this->exampleProject(
            user: $user,
            signatureFilenames: [
                'CODEOWNERS',
                'ci.yml',
                'deploy-production.yml',
                'main-branch-ruleset.json',
                'dependabot.yml',
            ],
            attributes: [
                'name' => 'GitHub',
                'kind' => 'project',
                'description' => 'An enterprise repository operating model covering ownership, pull requests, merge queues, secure CI, environment-gated deployments, immutable releases, and rollback.',
                'position' => 6,
            ],
        );

        if ($project === null) {
            return;
        }

        $githubFolder = $project->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => '.github'],
            ['position' => 0],
        );
        $issueTemplatesFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $githubFolder->id, 'name' => 'ISSUE_TEMPLATE'],
            ['position' => 0],
        );
        $workflowsFolder = $project->folders()->firstOrCreate(
            ['parent_id' => $githubFolder->id, 'name' => 'workflows'],
            ['position' => 1],
        );
        $governanceFolder = $project->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => 'governance'],
            ['position' => 1],
        );
        $scriptsFolder = $project->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => 'scripts'],
            ['position' => 2],
        );
        $runbooksFolder = $project->folders()->firstOrCreate(
            ['parent_id' => null, 'name' => 'runbooks'],
            ['position' => 3],
        );
        $githubTags = [$tags['github'], $tags['enterprise']];
        /**
         * @param  list<Tag>  $additionalTags
         */
        $seed = function (
            ?Folder $folder,
            string $title,
            string $filename,
            string $language,
            string $description,
            string $content,
            int $position,
            string $variationName,
            array $additionalTags,
        ) use ($project, $user, $githubTags): void {
            $this->createSingleVariationSnippet(
                project: $project,
                folder: $folder,
                user: $user,
                attributes: [
                    'title' => $title,
                    'filename' => $filename,
                    'language' => $language,
                    'description' => $description,
                    'content' => $content,
                    'position' => $position,
                ],
                variationName: $variationName,
                tags: array_values([...$githubTags, ...$additionalTags]),
            );
        };

        $seed(
            null,
            'Enterprise GitHub Operating Model',
            'README.md',
            'markdown',
            'The end-to-end path from a feature branch through review, merge queue, staging, immutable release, production, and rollback.',
            <<<'MARKDOWN'
<!-- {!# snippet: enterprise_change_flow #!} -->
# Enterprise GitHub operating model

```text
feature branch
→ draft pull request
→ CI, dependency review, and CodeQL
→ CODEOWNERS plus two approvals
→ resolved review conversations
→ merge queue revalidation
→ squash merge to main
→ deploy the tested commit to staging
→ tag, build, attest, and draft a release
→ publish an immutable release
→ approve the production environment
→ deploy the exact release artifact
→ observe, or redeploy a known-good release to roll back
```

<!-- {!# snippet: required_repository_controls #!} -->
## Required repository controls

- Import the main-branch and release-tag rulesets, verify required check names, then move from `evaluate` to `active`.
- Require `Quality gates`, `Dependency review`, and `CodeQL (javascript-typescript)` for pull requests and merge groups, plus two approvals, CODEOWNERS, last-push approval, resolved conversations, linear history, squash merge, and merge queue.
- Restrict Actions to approved sources and full commit SHAs; let Dependabot maintain those pins.
- Configure `staging` and `production` as GitHub Environments. Production requires independent reviewers and must not allow administrators to bypass protection.
- Use OIDC or an equivalent short-lived identity. Never store permanent production credentials in repository secrets.
- Create releases as drafts, attach and attest the exact artifact, then publish with immutable releases enabled.

<!-- {!# snippet: responsibility_map #!} -->
## Responsibility map

| Concern | Source of truth |
| --- | --- |
| Review and merge policy | `governance/main-branch-ruleset.json` |
| Ownership | `.github/CODEOWNERS` and `governance/team-ownership.md` |
| Test contract | `.github/workflows/ci.yml` and `scripts/ci.sh` |
| Environment approvals | GitHub repository Environment settings |
| Release artifact | Draft/published GitHub Release plus attestation |
| Deployment mechanics | One reusable workflow and `scripts/deploy.sh` |
| Operational response | `runbooks/` |

This is a reference operating model, not a drop-in platform adapter. Replace team handles, package commands, cloud roles, URLs, and the explicit deployment adapter failure with approved organisation-specific values.
MARKDOWN,
            0,
            'Enterprise change lifecycle',
            [$tags['governance'], $tags['ci-cd'], $tags['deployment'], $tags['release']],
        );

        $seed(
            null,
            'Contribution and Pull Request Policy',
            'CONTRIBUTING.md',
            'markdown',
            'Branch naming, draft pull requests, review expectations, merge queue use, and emergency fixes.',
            <<<'MARKDOWN'
<!-- {!# snippet: branch_naming #!} -->
# Contributing

Branch from the latest `main` and use `feature/TICKET-short-description`, `fix/TICKET-short-description`, or `chore/TICKET-short-description`. Keep each branch to one reviewable outcome; do not bundle opportunistic refactors into a production change.

<!-- {!# snippet: draft_pull_request_flow #!} -->
## Draft pull request flow

Open a draft pull request early. Complete the template, link the change record, add tests and observability, and describe rollout and rollback. Mark it ready only when CI passes and the change is safe for review. Never place secrets, customer data, or private incident evidence in a pull request.

<!-- {!# snippet: review_and_approval #!} -->
## Review and approval

Two approvals are required, including every matching CODEOWNER. The author cannot approve their own change. A reviewer checks correctness, security, compatibility, operability, tests, and rollback—not just style. New commits dismiss stale approvals and the last push requires approval from someone else.

<!-- {!# snippet: merge_queue_and_squash #!} -->
## Merge queue and squash

Resolve every review thread, update from `main`, and add the pull request to the merge queue. The queue tests the synthetic merge group; do not bypass it after a green branch build. Squash merge with an imperative, ticket-linked subject so `main` remains linear and each commit can be reverted.

<!-- {!# snippet: hotfix_flow #!} -->
## Emergency changes

Use the emergency-change runbook. Any break-glass bypass must be time-bound, attributed, incident-linked, tested as far as conditions permit, and followed by a normal pull request and retrospective.
MARKDOWN,
            1,
            'Pull request and merge policy',
            [$tags['pull-request'], $tags['code-review'], $tags['merge-queue']],
        );

        $seed(
            null,
            'Repository Security Policy',
            'SECURITY.md',
            'markdown',
            'Private vulnerability intake, supported versions, secret response, and Actions security.',
            <<<'MARKDOWN'
<!-- {!# snippet: private_vulnerability_reporting #!} -->
# Security policy

Report vulnerabilities through GitHub private vulnerability reporting. If that is unavailable, use the organisation's private security channel. Do not open a public issue. Include affected versions, impact, reproduction, and a safe contact method.

<!-- {!# snippet: supported_versions #!} -->
## Supported versions

The current production release and the immediately preceding release receive security fixes. Older versions must be upgraded or covered by an explicitly documented exception.

<!-- {!# snippet: secret_exposure_response #!} -->
## Exposed secret response

Revoke or rotate the credential first, then contain usage, preserve audit evidence, remove the secret from current content and history, scan related systems, and record the incident. Rewriting Git history alone does not make a leaked credential safe.

<!-- {!# snippet: actions_security_baseline #!} -->
## GitHub Actions baseline

Workflows use least-privilege permissions, approved actions pinned to full commit SHAs, protected environments, OIDC identities, trusted release inputs, and no direct interpolation of untrusted issue or pull-request text into shell commands. Fork-originated workflows never receive privileged secrets.
MARKDOWN,
            2,
            'Security reporting and automation baseline',
            [$tags['security'], $tags['governance']],
        );

        $seed(
            null,
            'Repository Support Routes',
            'SUPPORT.md',
            'markdown',
            'Routes product questions, defects, security reports, and incidents to the correct system.',
            <<<'MARKDOWN'
<!-- {!# snippet: support_routes #!} -->
# Support

- Product question: team support channel.
- Reproducible defect: GitHub bug form with non-sensitive evidence.
- Planned change: change-request form and product backlog.
- Vulnerability: private security reporting defined in `SECURITY.md`.

<!-- {!# snippet: production_incident_route #!} -->
## Production incidents

Page the owning service team through the incident system. Do not wait for a GitHub issue response. Link the incident, deployment, release, and rollback records after containment without copying sensitive logs into the repository.
MARKDOWN,
            3,
            'Support and incident routing',
            [$tags['governance'], $tags['runbook']],
        );

        $seed(
            null,
            'Release Changelog Template',
            'CHANGELOG.md',
            'markdown',
            'A concise changelog structure for user-facing releases and breaking changes.',
            <<<'MARKDOWN'
# Changelog

<!-- {!# snippet: unreleased_changes #!} -->
## Unreleased

### Added

- Describe user-visible additions and link the pull request.

### Changed

- Describe changed behaviour and any migration.

### Fixed

- Describe corrected behaviour and impact.

<!-- {!# snippet: release_entry_template #!} -->
## vX.Y.Z — YYYY-MM-DD

- Release: `https://github.example.com/organisation/repository/releases/tag/vX.Y.Z`
- Deployment record: `CHANGE-0000`
- Artifact digest: `sha256:…`

<!-- {!# snippet: breaking_change_entry #!} -->
### Breaking

State who is affected, the deadline, the exact migration, compatibility window, rollback constraint, and owner.
MARKDOWN,
            4,
            'Release changelog structure',
            [$tags['release'], $tags['release-management']],
        );

        $seed(
            $githubFolder,
            'Repository Code Owners',
            'CODEOWNERS',
            'plaintext',
            'Default, automation, security, deployment, and domain ownership with self-protection.',
            <<<'CODEOWNERS'
# {!# snippet: default_ownership #!}
*                           @organisation/platform-maintainers

# {!# snippet: protected_automation_ownership #!}
/.github/                   @organisation/platform-maintainers @organisation/security-engineering
/.github/CODEOWNERS         @organisation/platform-maintainers @organisation/security-engineering
/.github/workflows/         @organisation/platform-maintainers @organisation/security-engineering
/governance/                @organisation/platform-maintainers @organisation/security-engineering
/scripts/deploy.sh          @organisation/platform-maintainers @organisation/site-reliability

# {!# snippet: domain_ownership #!}
/src/payments/              @organisation/payments
/src/identity/              @organisation/identity
/runbooks/                  @organisation/site-reliability
/SECURITY.md                @organisation/security-engineering
CODEOWNERS,
            0,
            'Enterprise CODEOWNERS map',
            [$tags['codeowners'], $tags['code-review'], $tags['governance']],
        );

        $seed(
            $githubFolder,
            'Pull Request Template',
            'pull_request_template.md',
            'markdown',
            'Requires scope, risk, verification, observability, deployment, and rollback evidence.',
            <<<'MARKDOWN'
<!-- {!# snippet: pull_request_summary #!} -->
## Summary

- Change record:
- User or service outcome:
- Explicitly out of scope:

<!-- {!# snippet: risk_and_rollout #!} -->
## Risk and rollout

- Risk level and failure mode:
- Data/schema/API compatibility:
- Feature flag or staged rollout:
- Rollback release and trigger:

<!-- {!# snippet: verification_and_observability #!} -->
## Verification and observability

- [ ] Automated tests added or updated
- [ ] Security/privacy impact assessed
- [ ] Metrics, logs, traces, and alerts identified
- [ ] Staging evidence attached
- [ ] Runbook updated when operations changed

<!-- {!# snippet: reviewer_checklist #!} -->
## Reviewer checklist

- [ ] Behaviour and failure paths are understood
- [ ] Permissions and untrusted inputs are safe
- [ ] Deployment and rollback use a known artifact
- [ ] CODEOWNERS and specialist reviewers are present
MARKDOWN,
            1,
            'Risk-aware pull request template',
            [$tags['pull-request'], $tags['code-review'], $tags['deployment']],
        );

        $seed(
            $githubFolder,
            'Release Notes Configuration',
            'release.yml',
            'yaml',
            'Categorises generated release notes and excludes operational pull requests.',
            <<<'YAML'
# {!# snippet: release_note_exclusions #!}
changelog:
  exclude:
    labels:
      - skip-changelog
      - internal-only
    authors:
      - dependabot

  # {!# snippet: release_note_categories #!}
  categories:
    - title: Breaking changes
      labels: [breaking-change]
    - title: Features
      labels: [feature]
    - title: Fixes
      labels: [bug]
    - title: Security
      labels: [security]
    - title: Other changes
      labels: ['*']
YAML,
            2,
            'Generated release note categories',
            [$tags['release'], $tags['automation']],
        );

        $seed(
            $githubFolder,
            'Dependabot Configuration',
            'dependabot.yml',
            'yaml',
            'Maintains pinned GitHub Actions and grouped application dependencies on controlled schedules.',
            <<<'YAML'
version: 2
updates:
  # {!# snippet: github_actions_updates #!}
  - package-ecosystem: github-actions
    directory: /
    schedule:
      interval: weekly
      day: monday
      time: '06:00'
      timezone: Europe/London
    open-pull-requests-limit: 5
    labels: [dependencies, github-actions]
    reviewers: [organisation/platform-maintainers]

  # {!# snippet: application_dependency_updates #!}
  - package-ecosystem: npm
    directory: /
    schedule:
      interval: weekly
      day: monday
      time: '06:30'
      timezone: Europe/London
    versioning-strategy: increase-if-necessary
    labels: [dependencies, javascript]
    reviewers: [organisation/platform-maintainers]
    # {!# snippet: dependency_update_grouping #!}
    groups:
      development-dependencies:
        dependency-type: development
        update-types: [minor, patch]
      production-patches:
        dependency-type: production
        update-types: [patch]
YAML,
            3,
            'Actions and application dependency updates',
            [$tags['dependabot'], $tags['security'], $tags['automation']],
        );

        $seed(
            $issueTemplatesFolder,
            'Bug Issue Form',
            'bug.yml',
            'yaml',
            'Structured defect intake with impact, reproduction, environment, and safe evidence.',
            <<<'YAML'
# {!# snippet: bug_form_metadata #!}
name: Bug report
description: Report a reproducible, non-sensitive defect
title: '[Bug]: '
labels: [bug, triage]
body:
  # {!# snippet: reproduction_and_impact #!}
  - type: textarea
    id: impact
    attributes:
      label: Impact
      description: Who is affected, how severely, and since when?
    validations:
      required: true
  - type: textarea
    id: reproduction
    attributes:
      label: Reproduction
      description: Minimal steps, expected behaviour, and actual behaviour.
    validations:
      required: true
  # {!# snippet: evidence_and_environment #!}
  - type: input
    id: version
    attributes:
      label: Version or release
    validations:
      required: true
  - type: textarea
    id: evidence
    attributes:
      label: Sanitised evidence
      description: Remove secrets, customer data, and private incident details.
YAML,
            0,
            'Structured bug report',
            [$tags['governance']],
        );

        $seed(
            $issueTemplatesFolder,
            'Change Request Issue Form',
            'change-request.yml',
            'yaml',
            'Captures outcomes, acceptance criteria, operational impact, rollout, and rollback.',
            <<<'YAML'
# {!# snippet: change_request_metadata #!}
name: Change request
description: Propose a planned product or platform change
title: '[Change]: '
labels: [change-request, needs-triage]
body:
  - type: textarea
    id: outcome
    attributes:
      label: Desired outcome
      description: Describe the user or service outcome, not only the implementation.
    validations:
      required: true
  # {!# snippet: acceptance_criteria #!}
  - type: textarea
    id: acceptance
    attributes:
      label: Acceptance criteria
      value: |
        - [ ]
    validations:
      required: true
  # {!# snippet: rollout_and_operational_impact #!}
  - type: textarea
    id: operations
    attributes:
      label: Rollout and operational impact
      description: Include migrations, flags, monitoring, support, and rollback.
YAML,
            1,
            'Operational change request',
            [$tags['governance'], $tags['deployment']],
        );

        $seed(
            $issueTemplatesFolder,
            'Issue Template Chooser Configuration',
            'config.yml',
            'yaml',
            'Disables unstructured issues and routes security and support elsewhere.',
            <<<'YAML'
# {!# snippet: issue_template_chooser #!}
blank_issues_enabled: false
contact_links:
  - name: Security vulnerability
    url: https://github.example.com/organisation/repository/security/advisories/new
    about: Report vulnerabilities privately.
  - name: Production incident
    url: https://incident.example.com/new
    about: Page the owning team; do not open a GitHub issue.
YAML,
            2,
            'Governed issue routes',
            [$tags['governance'], $tags['security']],
        );

        $this->seedGitHubWorkflows(
            project: $project,
            folder: $workflowsFolder,
            user: $user,
            tags: $tags,
        );
        $this->seedGitHubGovernance(
            project: $project,
            folder: $governanceFolder,
            user: $user,
            tags: $tags,
        );
        $this->seedGitHubScriptsAndRunbooks(
            project: $project,
            scriptsFolder: $scriptsFolder,
            runbooksFolder: $runbooksFolder,
            user: $user,
            tags: $tags,
        );
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedGitHubWorkflows(
        Project $project,
        Folder $folder,
        User $user,
        array $tags,
    ): void {
        $workflowTags = [$tags['github'], $tags['enterprise'], $tags['github-actions']];

        $ci = <<<'YAML'
# {!# snippet: merge_queue_aware_ci_triggers #!}
name: CI

on:
  pull_request:
    branches: [main]
  merge_group:
    types: [checks_requested]
  push:
    branches: [main]

# {!# snippet: least_privilege_ci_permissions #!}
permissions:
  contents: read

# {!# snippet: cancellable_pull_request_ci #!}
concurrency:
  group: ci-${{ github.workflow }}-${{ github.ref }}
  cancel-in-progress: ${{ github.event_name == 'pull_request' }}

# {!# snippet: quality_gate_and_artifact #!}
jobs:
  quality-gates:
    name: Quality gates
    runs-on: ubuntu-latest
    timeout-minutes: 20
    steps:
      - name: Check out the candidate
        uses: actions/checkout@d23441a48e516b6c34aea4fa41551a30e30af803 # v6
      - name: Run the repository-owned quality contract
        run: bash ./scripts/ci.sh
      - name: Store the tested release candidate
        uses: actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02 # v4
        with:
          name: application-${{ github.sha }}
          path: |
            dist/application.tar.gz
            dist/application.tar.gz.sha256
          if-no-files-found: error
          retention-days: 14
YAML;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Merge Queue Aware CI',
                'filename' => 'ci.yml',
                'language' => 'yaml',
                'description' => 'A least-privilege required check that also runs for merge-group candidates and publishes the tested artifact.',
                'content' => $ci,
                'position' => 0,
            ],
            variationName: 'Required quality gate',
            tags: array_merge($workflowTags, [
                $tags['ci'],
                $tags['ci-cd'],
                $tags['merge-queue'],
            ]),
        );

        $dependencyReview = <<<'YAML'
# {!# snippet: dependency_review_gate #!}
name: Dependency review

on:
  pull_request:
    branches: [main]
  merge_group:
    types: [checks_requested]

permissions:
  contents: read

concurrency:
  group: dependency-review-${{ github.ref }}
  cancel-in-progress: true

jobs:
  dependency-review:
    name: Dependency review
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@d23441a48e516b6c34aea4fa41551a30e30af803 # v6
      - name: Check the dependency diff
        uses: actions/dependency-review-action@a1d282b36b6f3519aa1f3fc636f609c47dddb294 # v5.0.0
        with:
          fail-on-severity: moderate
          deny-licenses: GPL-3.0, AGPL-3.0
YAML;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Dependency Review Workflow',
                'filename' => 'dependency-review.yml',
                'language' => 'yaml',
                'description' => 'Blocks pull requests that introduce dependencies above the accepted severity or licence boundary.',
                'content' => $dependencyReview,
                'position' => 1,
            ],
            variationName: 'Pull request dependency gate',
            tags: array_merge($workflowTags, [
                $tags['security'],
                $tags['dependabot'],
            ]),
        );

        $codeQl = <<<'YAML'
# {!# snippet: codeql_triggers #!}
name: CodeQL

on:
  pull_request:
    branches: [main]
  merge_group:
    types: [checks_requested]
  push:
    branches: [main]
  schedule:
    - cron: '23 4 * * 1'

# {!# snippet: codeql_permission_boundary #!}
permissions:
  contents: read
  security-events: write

jobs:
  analyse:
    name: CodeQL (${{ matrix.language }})
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      # {!# snippet: codeql_language_matrix #!}
      matrix:
        language: [javascript-typescript]
    steps:
      - uses: actions/checkout@d23441a48e516b6c34aea4fa41551a30e30af803 # v6
      - name: Initialise CodeQL
        uses: github/codeql-action/init@e4fba868fa4b1b91e1fdab776edc8cfbe6e9fb81 # v4.37.3
        with:
          languages: ${{ matrix.language }}
      - name: Analyse
        uses: github/codeql-action/analyze@e4fba868fa4b1b91e1fdab776edc8cfbe6e9fb81 # v4.37.3
YAML;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'CodeQL Analysis Workflow',
                'filename' => 'codeql.yml',
                'language' => 'yaml',
                'description' => 'Pinned CodeQL analysis for pull requests, main, and a scheduled full scan.',
                'content' => $codeQl,
                'position' => 2,
            ],
            variationName: 'JavaScript and TypeScript CodeQL',
            tags: array_merge($workflowTags, [
                $tags['security'],
                $tags['codeql'],
            ]),
        );

        $release = <<<'YAML'
# {!# snippet: release_from_semantic_tag #!}
name: Build release

on:
  push:
    tags: ['v*.*.*']

permissions:
  contents: write
  id-token: write
  attestations: write

jobs:
  build-release:
    name: Build and attest release
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@d23441a48e516b6c34aea4fa41551a30e30af803 # v6
        with:
          ref: ${{ github.ref }}
          fetch-depth: 0
          persist-credentials: false
      - name: Require the tagged commit to be on protected main
        run: |
          git fetch origin main
          git merge-base --is-ancestor HEAD origin/main
      # {!# snippet: build_and_attest_release #!}
      - name: Build deterministic release files
        run: bash ./scripts/build-release.sh "${GITHUB_REF_NAME}"
      - name: Generate artifact provenance
        uses: actions/attest@f7c74d28b9d84cb8768d0b8ca14a4bac6ef463e6 # v4
        with:
          subject-path: dist/application.tar.gz
      # {!# snippet: create_draft_release #!}
      - name: Create a draft release
        env:
          GH_TOKEN: ${{ github.token }}
          RELEASE_TAG: ${{ github.ref_name }}
        run: |
          gh release create "${RELEASE_TAG}" \
            dist/application.tar.gz \
            dist/application.tar.gz.sha256 \
            --draft \
            --generate-notes \
            --title "${RELEASE_TAG}" \
            --verify-tag
YAML;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Attested Draft Release Workflow',
                'filename' => 'release.yml',
                'language' => 'yaml',
                'description' => 'Builds from a semantic tag, attests the exact artifact, and prepares a human-reviewed draft release.',
                'content' => $release,
                'position' => 3,
            ],
            variationName: 'Tagged draft release',
            tags: array_merge($workflowTags, [
                $tags['release'],
                $tags['release-management'],
                $tags['security'],
            ]),
        );

        $staging = <<<'YAML'
# {!# snippet: deploy_after_successful_main_ci #!}
name: Deploy staging

on:
  workflow_run:
    workflows: [CI]
    types: [completed]
    branches: [main]

permissions:
  actions: read
  contents: read
  id-token: write

concurrency:
  group: deploy-staging
  cancel-in-progress: true

jobs:
  deploy:
    if: ${{ github.event.workflow_run.conclusion == 'success' }}
    name: Deploy staging
    runs-on: ubuntu-latest
    # {!# snippet: staging_environment_gate #!}
    environment:
      name: staging
      url: ${{ steps.deploy.outputs.environment_url }}
    steps:
      - uses: actions/checkout@d23441a48e516b6c34aea4fa41551a30e30af803 # v6
        with:
          ref: ${{ github.event.workflow_run.head_sha }}
      # {!# snippet: download_tested_artifact #!}
      - name: Download the successful CI artifact
        env:
          GH_TOKEN: ${{ github.token }}
          RUN_ID: ${{ github.event.workflow_run.id }}
          HEAD_SHA: ${{ github.event.workflow_run.head_sha }}
        run: |
          mkdir -p dist
          gh run download "${RUN_ID}" --name "application-${HEAD_SHA}" --dir dist
          (cd dist && sha256sum --check application.tar.gz.sha256)
      - id: deploy
        name: Deploy the tested commit
        env:
          DEPLOYMENT_ENVIRONMENT: staging
          RELEASE_REFERENCE: ${{ github.event.workflow_run.head_sha }}
          ARTIFACT_PATH: dist/application.tar.gz
        run: bash ./scripts/deploy.sh
      - name: Smoke test staging
        env:
          HEALTHCHECK_URL: ${{ steps.deploy.outputs.environment_url }}/health
        run: bash ./scripts/smoke-test.sh
YAML;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Staging Deployment Workflow',
                'filename' => 'deploy-staging.yml',
                'language' => 'yaml',
                'description' => 'Deploys the exact artifact from successful main CI into a protected staging environment and smoke-tests it.',
                'content' => $staging,
                'position' => 4,
            ],
            variationName: 'Successful-main staging deployment',
            tags: array_merge($workflowTags, [
                $tags['deployment'],
                $tags['ci-cd'],
            ]),
        );

        $reusableDeploy = <<<'YAML'
# {!# snippet: reusable_deployment_contract #!}
name: Deploy release artifact

on:
  workflow_call:
    inputs:
      release_tag:
        description: Existing published release tag to deploy
        required: true
        type: string
      target_environment:
        description: Protected GitHub Environment name
        required: true
        type: string

permissions:
  attestations: read
  contents: read
  id-token: write

jobs:
  deploy:
    name: Deploy ${{ inputs.release_tag }} to ${{ inputs.target_environment }}
    runs-on: ubuntu-latest
    environment:
      name: ${{ inputs.target_environment }}
      url: ${{ steps.deploy.outputs.environment_url }}
    concurrency:
      group: deploy-${{ inputs.target_environment }}
      cancel-in-progress: false
    steps:
      - uses: actions/checkout@d23441a48e516b6c34aea4fa41551a30e30af803 # v6
        with:
          ref: ${{ inputs.release_tag }}
          fetch-depth: 0
      # {!# snippet: download_and_verify_release #!}
      - name: Download and verify the published artifact
        env:
          GH_TOKEN: ${{ github.token }}
          RELEASE_TAG: ${{ inputs.release_tag }}
        run: |
          release_state="$(gh release view "${RELEASE_TAG}" --json isDraft,isPrerelease --jq '[.isDraft, .isPrerelease] | @tsv')"
          if [[ "${release_state}" != $'false\tfalse' ]]; then
            printf 'Release must be published and not a prerelease.\n' >&2
            exit 65
          fi
          git fetch origin main
          git merge-base --is-ancestor HEAD origin/main
          mkdir -p dist
          gh release download "${RELEASE_TAG}" \
            --pattern 'application.tar.gz' \
            --pattern 'application.tar.gz.sha256' \
            --dir dist
          (cd dist && sha256sum --check application.tar.gz.sha256)
          gh attestation verify dist/application.tar.gz --repo "${GITHUB_REPOSITORY}"
      # {!# snippet: environment_gated_deploy #!}
      - id: deploy
        name: Deploy through the platform adapter
        env:
          DEPLOYMENT_ENVIRONMENT: ${{ inputs.target_environment }}
          RELEASE_REFERENCE: ${{ inputs.release_tag }}
          ARTIFACT_PATH: dist/application.tar.gz
        run: bash ./scripts/deploy.sh
      # {!# snippet: post_deploy_smoke_test #!}
      - name: Verify the deployed release
        env:
          HEALTHCHECK_URL: ${{ steps.deploy.outputs.environment_url }}/health
        run: bash ./scripts/smoke-test.sh
YAML;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Reusable Release Deployment',
                'filename' => 'reusable-deploy-release.yml',
                'language' => 'yaml',
                'description' => 'One environment-gated path verifies and deploys a published artifact for both production and rollback.',
                'content' => $reusableDeploy,
                'position' => 5,
            ],
            variationName: 'Protected reusable deployment',
            tags: array_merge($workflowTags, [
                $tags['deployment'],
                $tags['reusable-workflow'],
                $tags['security'],
            ]),
        );

        $production = <<<'YAML'
# {!# snippet: deploy_published_release #!}
name: Deploy production

on:
  release:
    types: [published]

permissions:
  contents: read
  id-token: write

jobs:
  deploy-production:
    uses: ./.github/workflows/reusable-deploy-release.yml
    with:
      release_tag: ${{ github.event.release.tag_name }}
      target_environment: production
YAML;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Production Deployment Workflow',
                'filename' => 'deploy-production.yml',
                'language' => 'yaml',
                'description' => 'Routes a published immutable release through the protected production environment.',
                'content' => $production,
                'position' => 6,
            ],
            variationName: 'Published-release production deployment',
            tags: array_merge($workflowTags, [
                $tags['deployment'],
                $tags['release'],
            ]),
        );

        $rollback = <<<'YAML'
# {!# snippet: manual_rollback_contract #!}
name: Roll back production

on:
  workflow_dispatch:
    inputs:
      release_tag:
        description: Known-good published release tag
        required: true
        type: string
      incident:
        description: Incident or change record authorising rollback
        required: true
        type: string

permissions:
  contents: read
  id-token: write

run-name: Roll back production to ${{ inputs.release_tag }} for ${{ inputs.incident }}

jobs:
  # {!# snippet: redeploy_known_good_release #!}
  rollback-production:
    uses: ./.github/workflows/reusable-deploy-release.yml
    with:
      release_tag: ${{ inputs.release_tag }}
      target_environment: production
YAML;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Production Rollback Workflow',
                'filename' => 'rollback.yml',
                'language' => 'yaml',
                'description' => 'Requires an incident reference and redeploys a known-good release through the same protected production path.',
                'content' => $rollback,
                'position' => 7,
            ],
            variationName: 'Known-good release rollback',
            tags: array_merge($workflowTags, [
                $tags['deployment'],
                $tags['rollback'],
                $tags['runbook'],
            ]),
        );
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedGitHubGovernance(
        Project $project,
        Folder $folder,
        User $user,
        array $tags,
    ): void {
        $governanceTags = [
            $tags['github'],
            $tags['enterprise'],
            $tags['governance'],
        ];

        $mainRuleset = <<<'JSON'
{
    "name": "Enterprise main",
    "target": "branch",
    "source_type": "Repository",
    "enforcement": "evaluate",
    "conditions": {
        "ref_name": {
            "exclude": [],
            "include": ["~DEFAULT_BRANCH"]
        }
    },
    "rules": [
        { "type": "deletion" },
        { "type": "non_fast_forward" },
        { "type": "required_linear_history" },
        {
            "type": "pull_request",
            "parameters": {
                "allowed_merge_methods": ["squash"],
                "dismiss_stale_reviews_on_push": true,
                "require_code_owner_review": true,
                "require_last_push_approval": true,
                "required_approving_review_count": 2,
                "required_review_thread_resolution": true
            }
        },
        {
            "type": "required_status_checks",
            "parameters": {
                "do_not_enforce_on_create": false,
                "required_status_checks": [
                    { "context": "Quality gates" },
                    { "context": "Dependency review" },
                    { "context": "CodeQL (javascript-typescript)" }
                ],
                "strict_required_status_checks_policy": true
            }
        },
        {
            "type": "merge_queue",
            "parameters": {
                "check_response_timeout_minutes": 60,
                "grouping_strategy": "ALLGREEN",
                "max_entries_to_build": 5,
                "max_entries_to_merge": 5,
                "merge_method": "SQUASH",
                "min_entries_to_merge": 1,
                "min_entries_to_merge_wait_minutes": 5
            }
        }
    ],
    "bypass_actors": []
}
JSON;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Main Branch Ruleset',
                'filename' => 'main-branch-ruleset.json',
                'language' => 'json',
                'description' => 'An importable default-branch ruleset requiring squash PRs, two approvals, CODEOWNERS, Quality gates, and merge queue.',
                'content' => $mainRuleset,
                'position' => 0,
            ],
            variationName: 'Importable enterprise main ruleset',
            tags: array_merge($governanceTags, [
                $tags['branch-protection'],
                $tags['pull-request'],
                $tags['merge-queue'],
            ]),
        );

        $releaseRuleset = <<<'JSON'
{
    "name": "Protect release tags",
    "target": "tag",
    "source_type": "Repository",
    "enforcement": "active",
    "conditions": {
        "ref_name": {
            "exclude": [],
            "include": ["refs/tags/v*.*.*"]
        }
    },
    "rules": [
        { "type": "deletion" },
        { "type": "non_fast_forward" }
    ],
    "bypass_actors": []
}
JSON;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Release Tag Ruleset',
                'filename' => 'release-tag-ruleset.json',
                'language' => 'json',
                'description' => 'Protects semantic release tags from deletion and non-fast-forward updates.',
                'content' => $releaseRuleset,
                'position' => 1,
            ],
            variationName: 'Protected semantic release tags',
            tags: array_merge($governanceTags, [
                $tags['release'],
                $tags['branch-protection'],
            ]),
        );

        $rulesets = <<<'MARKDOWN'
<!-- {!# snippet: import_ruleset #!} -->
# Ruleset rollout

Import each JSON file through repository rulesets or the REST API. Replace example team references, use `evaluate` while checking its effect, and record the owner and approval for activation.

<!-- {!# snippet: confirm_required_check_context #!} -->
## Confirm the check context

Run the CI workflow once and confirm that GitHub reports the exact job name `Quality gates`. A required status context is a string contract; changing the workflow job name without updating the ruleset blocks every merge.

Also verify `Dependency review` and `CodeQL (javascript-typescript)`, and bind each required check to the expected GitHub Actions source in repository settings after the first successful run. This prevents another integration from satisfying a same-named status context.

<!-- {!# snippet: evaluate_before_enforcement #!} -->
## Evaluate before enforcement

Observe pull requests, bots, release automation, merge queue, and emergency access in evaluation mode. Resolve unexpected bypasses or missing checks before changing enforcement to active.

<!-- {!# snippet: break_glass_bypass #!} -->
## Break-glass bypass

Prefer no standing bypass. If the organisation requires one, use a tightly controlled team or integration, log every use, require an incident/change reference, expire access, and mandate retrospective review.
MARKDOWN;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Ruleset Rollout Guide',
                'filename' => 'rulesets.md',
                'language' => 'markdown',
                'description' => 'Safe import, check-name verification, evaluation, activation, and break-glass governance.',
                'content' => $rulesets,
                'position' => 2,
            ],
            variationName: 'Evaluate then enforce rulesets',
            tags: array_merge($governanceTags, [
                $tags['branch-protection'],
                $tags['merge-queue'],
            ]),
        );

        $repositorySettings = <<<'MARKDOWN'
<!-- {!# snippet: merge_settings #!} -->
# Repository settings

Enable squash merge and the merge queue. Disable merge commits and rebase merge unless a documented repository constraint requires them. Automatically delete head branches and require contributors to update pull request branches through the queue rather than bypassing checks.

<!-- {!# snippet: actions_policy #!} -->
## Actions policy

Allow GitHub-owned and organisation-approved actions only. Require actions to be pinned to a full commit SHA, set the default workflow token to read-only, and grant write permissions explicitly per workflow. Dependabot updates pinned action revisions through reviewed pull requests.

<!-- {!# snippet: security_features #!} -->
## Security features

Enable secret scanning with push protection, Dependabot alerts and security updates, dependency graph, private vulnerability reporting, code scanning, and audit-log streaming where the plan supports it. Protect fork workflows from privileged secrets.

<!-- {!# snippet: immutable_releases #!} -->
## Immutable releases

Enable immutable releases only after the draft-release workflow reliably uploads every required artifact and checksum. Once published, never replace assets in place; issue a new version or roll back by redeploying an older published release.
MARKDOWN;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Repository Settings Baseline',
                'filename' => 'repository-settings.md',
                'language' => 'markdown',
                'description' => 'Merge, Actions, security feature, token, and immutable release settings that live outside repository files.',
                'content' => $repositorySettings,
                'position' => 3,
            ],
            variationName: 'Enterprise repository settings',
            tags: array_merge($governanceTags, [
                $tags['security'],
                $tags['release'],
            ]),
        );

        $environmentPolicy = <<<'MARKDOWN'
<!-- {!# snippet: staging_environment #!} -->
# Environment policy

## Staging

Staging accepts only successful `main` artifacts. It may deploy automatically, but it uses a distinct cloud identity and data boundary. Its URL is recorded on the deployment and smoke-tested after every rollout.

<!-- {!# snippet: production_environment #!} -->
## Production

Production accepts published release artifacts only. Require independent service-owner or operations approval, disallow self-review, prevent administrator bypass, restrict deployment branches/tags, and set an appropriate wait timer for high-risk services.

<!-- {!# snippet: oidc_credentials #!} -->
## Short-lived identity

Trust the repository, workflow, ref, and GitHub Environment in the cloud OIDC policy. Grant each environment the minimum deployment role. Avoid long-lived cloud keys and never expose production credentials to pull-request workflows.

<!-- {!# snippet: deployment_concurrency #!} -->
## Concurrency

Allow one deployment per environment. Staging may cancel an obsolete in-progress deployment; production and rollback must queue and finish deterministically. Record release, actor, approval, environment, artifact digest, start, result, and observation window.
MARKDOWN;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Environment Protection Policy',
                'filename' => 'environment-policy.md',
                'language' => 'markdown',
                'description' => 'Staging and production approvals, OIDC identities, deployment restrictions, concurrency, and evidence.',
                'content' => $environmentPolicy,
                'position' => 4,
            ],
            variationName: 'Protected staging and production environments',
            tags: array_merge($governanceTags, [
                $tags['deployment'],
                $tags['security'],
            ]),
        );

        $teamOwnership = <<<'MARKDOWN'
<!-- {!# snippet: team_responsibilities #!} -->
# Team ownership

| Team | Responsibility |
| --- | --- |
| Product/domain team | Behaviour, tests, compatibility, product rollout |
| Platform maintainers | Repository controls, CI contract, reusable workflows |
| Security engineering | Threat boundary, workflow supply chain, vulnerabilities |
| Site reliability | Production readiness, observability, deployment, rollback |
| Release manager | Version, release evidence, change window, communication |

<!-- {!# snippet: codeowner_requirements #!} -->
## CODEOWNER requirements

Every owned path has at least two active maintainers in a team. The `.github` directory, CODEOWNERS file, workflows, ruleset material, deployment adapter, and security policy require specialist ownership. Audit team membership quarterly and before reorganisations.

<!-- {!# snippet: escalation_path #!} -->
## Escalation

The service catalogue names the primary and secondary on-call teams, engineering owner, product owner, security contact, data owner, and executive incident route. If ownership is ambiguous, stop the change and resolve ownership before merge.
MARKDOWN;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: [
                'title' => 'Team Ownership Model',
                'filename' => 'team-ownership.md',
                'language' => 'markdown',
                'description' => 'Clear engineering, platform, security, SRE, and release responsibilities behind CODEOWNERS.',
                'content' => $teamOwnership,
                'position' => 5,
            ],
            variationName: 'Enterprise responsibility map',
            tags: array_merge($governanceTags, [
                $tags['codeowners'],
                $tags['code-review'],
            ]),
        );
    }

    /**
     * @param  array<string, Tag>  $tags
     */
    private function seedGitHubScriptsAndRunbooks(
        Project $project,
        Folder $scriptsFolder,
        Folder $runbooksFolder,
        User $user,
        array $tags,
    ): void {
        $githubTags = [$tags['github'], $tags['enterprise']];

        $ci = <<<'BASH'
#!/usr/bin/env bash
# {!# snippet: fail_fast_ci_entrypoint #!}
set -Eeuo pipefail

repository_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${repository_root}"

# {!# snippet: quality_commands #!}
composer install --no-interaction --prefer-dist --no-progress
npm ci
composer lint
composer test
npm run lint
npm run test -- --run
npm run build

# {!# snippet: package_release_candidate #!}
composer install --no-dev --classmap-authoritative --no-interaction --prefer-dist --no-progress
export SOURCE_DATE_EPOCH="$(git show -s --format=%ct HEAD)"
rm -rf "${repository_root}/dist/release"
mkdir -p dist/release
git archive --format=tar --prefix=application/ HEAD | tar -xf - -C dist/release
mkdir -p dist/release/application/vendor dist/release/application/public/build
cp -R vendor/. dist/release/application/vendor/
cp -R public/build/. dist/release/application/public/build/
tar \
    --sort=name \
    --mtime="@${SOURCE_DATE_EPOCH}" \
    --owner=0 \
    --group=0 \
    --numeric-owner \
    -czf dist/application.tar.gz \
    -C dist/release application
(cd dist && sha256sum application.tar.gz > application.tar.gz.sha256)
BASH;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $scriptsFolder,
            user: $user,
            attributes: [
                'title' => 'Repository CI Contract',
                'filename' => 'ci.sh',
                'language' => 'bash',
                'description' => 'One local-and-CI entrypoint installs locked dependencies, runs quality gates, builds, and packages the candidate.',
                'content' => $ci,
                'position' => 0,
            ],
            variationName: 'Fail-fast quality pipeline',
            tags: array_merge($githubTags, [$tags['ci'], $tags['ci-cd']]),
        );

        $buildRelease = <<<'BASH'
#!/usr/bin/env bash
set -Eeuo pipefail

# {!# snippet: validate_release_tag #!}
release_tag="${1:?Usage: build-release.sh vX.Y.Z}"

if [[ ! "${release_tag}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    printf 'Release tag must match vMAJOR.MINOR.PATCH.\n' >&2
    exit 64
fi

repository_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${repository_root}"

test "$(git describe --tags --exact-match)" = "${release_tag}"
export SOURCE_DATE_EPOCH="$(git show -s --format=%ct HEAD)"

composer install --no-dev --classmap-authoritative --no-interaction --prefer-dist --no-progress
npm ci
npm run build

# {!# snippet: reproducible_release_archive #!}
rm -rf "${repository_root}/dist/release"
mkdir -p dist/release
git archive --format=tar --prefix=application/ HEAD | tar -xf - -C dist/release
mkdir -p dist/release/application/vendor dist/release/application/public/build
cp -R vendor/. dist/release/application/vendor/
cp -R public/build/. dist/release/application/public/build/
tar \
    --sort=name \
    --mtime="@${SOURCE_DATE_EPOCH}" \
    --owner=0 \
    --group=0 \
    --numeric-owner \
    -czf dist/application.tar.gz \
    -C dist/release application
(cd dist && sha256sum application.tar.gz > application.tar.gz.sha256)
BASH;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $scriptsFolder,
            user: $user,
            attributes: [
                'title' => 'Deterministic Release Builder',
                'filename' => 'build-release.sh',
                'language' => 'bash',
                'description' => 'Validates a semantic tag and creates a checksummed release archive with stable metadata.',
                'content' => $buildRelease,
                'position' => 1,
            ],
            variationName: 'Semantic tagged release archive',
            tags: array_merge($githubTags, [
                $tags['release'],
                $tags['security'],
            ]),
        );

        $deploy = <<<'BASH'
#!/usr/bin/env bash
set -Eeuo pipefail

# {!# snippet: validate_deployment_contract #!}
: "${DEPLOYMENT_ENVIRONMENT:?DEPLOYMENT_ENVIRONMENT is required}"
: "${RELEASE_REFERENCE:?RELEASE_REFERENCE is required}"
: "${ARTIFACT_PATH:?ARTIFACT_PATH is required}"

if [[ ! -f "${ARTIFACT_PATH}" ]]; then
    printf 'Artifact not found: %s\n' "${ARTIFACT_PATH}" >&2
    exit 66
fi

case "${DEPLOYMENT_ENVIRONMENT}" in
    staging|production) ;;
    *)
        printf 'Unsupported deployment environment: %s\n' "${DEPLOYMENT_ENVIRONMENT}" >&2
        exit 64
        ;;
esac

# {!# snippet: invoke_platform_adapter #!}
# Replace this explicit failure with the organisation's reviewed adapter, for
# example a signed Argo CD promotion, Kubernetes deployment, or cloud release
# command authenticated through the workflow's OIDC identity.
printf 'No platform deployment adapter is configured; refusing to report success.\n' >&2
exit 78
BASH;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $scriptsFolder,
            user: $user,
            attributes: [
                'title' => 'Deployment Adapter Contract',
                'filename' => 'deploy.sh',
                'language' => 'bash',
                'description' => 'Validates trusted deployment inputs and intentionally fails until an approved platform adapter is supplied.',
                'content' => $deploy,
                'position' => 2,
            ],
            variationName: 'Fail-closed deployment adapter',
            tags: array_merge($githubTags, [
                $tags['deployment'],
                $tags['security'],
            ]),
        );

        $smokeTest = <<<'BASH'
#!/usr/bin/env bash
set -Eeuo pipefail

# {!# snippet: healthcheck_with_retries #!}
: "${HEALTHCHECK_URL:?HEALTHCHECK_URL is required}"

for attempt in 1 2 3 4 5; do
    if curl \
        --fail \
        --silent \
        --show-error \
        --location \
        --max-time 10 \
        "${HEALTHCHECK_URL}" >/dev/null; then
        printf 'Health check passed on attempt %s.\n' "${attempt}"
        exit 0
    fi

    sleep "$((attempt * 2))"
done

printf 'Health check failed after five attempts: %s\n' "${HEALTHCHECK_URL}" >&2
exit 1
BASH;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $scriptsFolder,
            user: $user,
            attributes: [
                'title' => 'Post-deployment Smoke Test',
                'filename' => 'smoke-test.sh',
                'language' => 'bash',
                'description' => 'A bounded, retrying health check that fails the deployment record when the service remains unhealthy.',
                'content' => $smokeTest,
                'position' => 3,
            ],
            variationName: 'Bounded deployment health check',
            tags: array_merge($githubTags, [$tags['deployment']]),
        );

        $deployment = <<<'MARKDOWN'
<!-- {!# snippet: deployment_preflight #!} -->
# Deployment runbook

Confirm the release is published, attested, checksummed, approved for the change window, compatible with schema and API consumers, and already verified in staging. Identify the dashboard, alerts, support contact, and exact known-good rollback release.

<!-- {!# snippet: approval_and_execution #!} -->
## Approval and execution

Open the production workflow from the release event. The protected Environment pauses for an independent approver. Verify release tag, artifact digest, change record, actor, and current incidents before approval. Never rebuild during deployment.

<!-- {!# snippet: post_deploy_observation #!} -->
## Observation

Confirm the health check, release marker, error rate, latency, saturation, business transactions, queues, and logs. Observe for the service-defined window and record a clear success or invoke the rollback decision process.
MARKDOWN;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $runbooksFolder,
            user: $user,
            attributes: [
                'title' => 'Deployment Runbook',
                'filename' => 'deployment.md',
                'language' => 'markdown',
                'description' => 'Preflight, protected approval, exact-artifact execution, and post-deploy observation.',
                'content' => $deployment,
                'position' => 0,
            ],
            variationName: 'Production deployment procedure',
            tags: array_merge($githubTags, [$tags['deployment'], $tags['runbook']]),
        );

        $rollback = <<<'MARKDOWN'
<!-- {!# snippet: rollback_decision #!} -->
# Rollback runbook

Roll back when deployment-correlated errors, latency, data risk, security impact, or critical business failures breach the defined threshold and forward repair is less certain than restoring a known-good release. The incident commander owns the decision.

<!-- {!# snippet: known_good_release_selection #!} -->
## Select the release

Choose a previously published, attested release known to be compatible with the current database and external contracts. Record the incident, failed release, target release, artifact digest, and any irreversible migration constraint. Trigger `rollback.yml`; do not rebuild or edit the old artifact.

<!-- {!# snippet: rollback_verification #!} -->
## Verify and follow up

Use the same protected production workflow, smoke test, and observation dashboard. Stop harmful jobs or feature flags when required. After recovery, preserve evidence, communicate status, reconcile schema/data, open the corrective pull request, and schedule a retrospective.
MARKDOWN;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $runbooksFolder,
            user: $user,
            attributes: [
                'title' => 'Rollback Runbook',
                'filename' => 'rollback.md',
                'language' => 'markdown',
                'description' => 'Decision thresholds, known-good release selection, protected redeployment, verification, and follow-up.',
                'content' => $rollback,
                'position' => 1,
            ],
            variationName: 'Known-good artifact rollback procedure',
            tags: array_merge($githubTags, [
                $tags['rollback'],
                $tags['deployment'],
                $tags['runbook'],
            ]),
        );

        $failedCheck = <<<'MARKDOWN'
<!-- {!# snippet: classify_failure #!} -->
# Failed required check runbook

Classify the failure as deterministic product failure, security/policy failure, infrastructure failure, or suspected flake. Read the first causal error and preserve the run, job URL, commit, merge-group SHA, runner image, and relevant sanitised logs.

<!-- {!# snippet: reproduce_before_rerun #!} -->
## Reproduce before rerun

Run the repository-owned CI command at the failing commit. Fix deterministic failures in a new commit. For service incidents, link the provider incident and wait for recovery. Do not repeatedly rerun until a required check happens to pass.

<!-- {!# snippet: flaky_test_escalation #!} -->
## Flaky test escalation

Create a tracked defect with owner, frequency, evidence, and deadline. Quarantine only with explicit approval and an equivalent safety control. A required check may be removed or renamed only through a ruleset change reviewed by platform and the owning team.
MARKDOWN;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $runbooksFolder,
            user: $user,
            attributes: [
                'title' => 'Failed Required Check Runbook',
                'filename' => 'failed-required-check.md',
                'language' => 'markdown',
                'description' => 'Classifies failures, requires reproduction, and prevents rerun-until-green or unowned flaky tests.',
                'content' => $failedCheck,
                'position' => 2,
            ],
            variationName: 'Required check failure response',
            tags: array_merge($githubTags, [$tags['ci'], $tags['runbook']]),
        );

        $emergencyChange = <<<'MARKDOWN'
<!-- {!# snippet: declare_emergency #!} -->
# Emergency change runbook

The incident commander declares the emergency, states the active harm, assigns engineering and operations owners, and records why the normal merge/deployment path cannot meet the response window. A tight deadline alone is not an emergency.

<!-- {!# snippet: audited_break_glass #!} -->
## Audited break glass

Use the smallest time-bound bypass available. Require a second person, retain audit logs, run the maximum safe tests, deploy a versioned artifact, keep rollback ready, and communicate the exact deviation. Never disable security controls globally or share credentials.

<!-- {!# snippet: mandatory_follow_up #!} -->
## Mandatory follow-up

Restore controls immediately after containment. Within one business day, create the normal pull request, reconcile repository and production state, rotate temporary access, complete missing tests and reviews, and schedule a retrospective with corrective actions and owners.
MARKDOWN;

        $this->createSingleVariationSnippet(
            project: $project,
            folder: $runbooksFolder,
            user: $user,
            attributes: [
                'title' => 'Emergency Change Runbook',
                'filename' => 'emergency-change.md',
                'language' => 'markdown',
                'description' => 'Defines a genuine emergency, audited minimum bypass, immediate control restoration, and mandatory follow-up.',
                'content' => $emergencyChange,
                'position' => 3,
            ],
            variationName: 'Audited break-glass procedure',
            tags: array_merge($githubTags, [
                $tags['governance'],
                $tags['runbook'],
                $tags['security'],
            ]),
        );
    }

    /**
     * @param  list<string>  $signatureFilenames
     * @param  array{name: string, kind: string, description: string, position: int}  $attributes
     */
    private function exampleProject(User $user, array $signatureFilenames, array $attributes): ?Project
    {
        $projectQuery = $user->projects();

        foreach ($signatureFilenames as $filename) {
            $projectQuery->whereHas(
                'snippets',
                fn (Builder $query): Builder => $query->where('filename', $filename),
            );
        }

        $existingProject = $projectQuery->first();

        if ($existingProject !== null) {
            return $existingProject;
        }

        if ($user->projects()->where('name', $attributes['name'])->exists()) {
            return null;
        }

        return $user->projects()->create($attributes);
    }

    /**
     * @return array<string, Tag>
     */
    private function tags(User $user): array
    {
        $definitions = [
            'php' => ['name' => 'PHP', 'color' => '#777bb4'],
            'javascript' => ['name' => 'JavaScript', 'color' => '#f7df1e'],
            'twig' => ['name' => 'Twig', 'color' => '#a5b4fc'],
            'timber' => ['name' => 'Timber', 'color' => '#22c55e'],
            'wordpress' => ['name' => 'WordPress', 'color' => '#21759b'],
            'loop' => ['name' => 'Loop', 'color' => '#f59e0b'],
            'reusable' => ['name' => 'Reusable', 'color' => '#14b8a6'],
            'template-variables' => [
                'name' => 'Template Variables',
                'color' => '#c084fc',
            ],
            'gutenberg' => ['name' => 'Gutenberg', 'color' => '#3858e9'],
            'block-theme' => ['name' => 'Block Theme', 'color' => '#64748b'],
            'theme-json' => ['name' => 'Theme JSON', 'color' => '#0ea5e9'],
            'block-pattern' => ['name' => 'Block Pattern', 'color' => '#8b5cf6'],
            'dynamic-block' => ['name' => 'Dynamic Block', 'color' => '#2563eb'],
            'wordpress-standards' => [
                'name' => 'WordPress Standards',
                'color' => '#334155',
            ],
            'wordpress-classes' => [
                'name' => 'WordPress Classes',
                'color' => '#475569',
            ],
            'accessibility' => ['name' => 'Accessibility', 'color' => '#0f766e'],
            'design-tokens' => ['name' => 'Design Tokens', 'color' => '#6366f1'],
            'figma-mcp' => ['name' => 'Figma MCP', 'color' => '#a855f7'],
            'docker' => ['name' => 'Docker', 'color' => '#2496ed'],
            'meilisearch' => ['name' => 'Meilisearch', 'color' => '#ff5caa'],
            'rest-api' => ['name' => 'REST API', 'color' => '#0284c7'],
            'visual-hero' => ['name' => 'Visual Hero', 'color' => '#2563eb'],
            'code-stack' => ['name' => 'Code Stack', 'color' => '#3b82f6'],
            'animation' => ['name' => 'Animation', 'color' => '#6366f1'],
            'responsive' => ['name' => 'Responsive', 'color' => '#0ea5e9'],
            'reduced-motion' => ['name' => 'Reduced Motion', 'color' => '#475569'],
            'progressive-enhancement' => [
                'name' => 'Progressive Enhancement',
                'color' => '#0891b2',
            ],
            'testing' => ['name' => 'Testing', 'color' => '#14b8a6'],
            'performance' => ['name' => 'Performance', 'color' => '#0f766e'],
            'github' => ['name' => 'GitHub', 'color' => '#64748b'],
            'github-actions' => ['name' => 'GitHub Actions', 'color' => '#2563eb'],
            'enterprise' => ['name' => 'Enterprise', 'color' => '#475569'],
            'pull-request' => ['name' => 'Pull Request', 'color' => '#0ea5e9'],
            'code-review' => ['name' => 'Code Review', 'color' => '#8b5cf6'],
            'merge-queue' => ['name' => 'Merge Queue', 'color' => '#0969da'],
            'ci' => ['name' => 'Continuous Integration', 'color' => '#0ea5e9'],
            'ci-cd' => ['name' => 'CI/CD', 'color' => '#0284c7'],
            'deployment' => ['name' => 'Deployment', 'color' => '#0369a1'],
            'release' => ['name' => 'Release', 'color' => '#8b5cf6'],
            'release-management' => [
                'name' => 'Release Management',
                'color' => '#4f46e5',
            ],
            'rollback' => ['name' => 'Rollback', 'color' => '#dc2626'],
            'governance' => ['name' => 'Governance', 'color' => '#334155'],
            'security' => ['name' => 'Security', 'color' => '#b42318'],
            'automation' => ['name' => 'Automation', 'color' => '#7c3aed'],
            'branch-protection' => ['name' => 'Branch Protection', 'color' => '#334155'],
            'codeowners' => ['name' => 'CODEOWNERS', 'color' => '#475569'],
            'dependabot' => ['name' => 'Dependabot', 'color' => '#0257d8'],
            'codeql' => ['name' => 'CodeQL', 'color' => '#6f42c1'],
            'runbook' => ['name' => 'Runbook', 'color' => '#7c3aed'],
            'reusable-workflow' => [
                'name' => 'Reusable Workflow',
                'color' => '#4f46e5',
            ],
            'plugin-development' => ['name' => 'Plugin Development', 'color' => '#3858e9'],
            'custom-database-table' => [
                'name' => 'Custom Database Table',
                'color' => '#0f766e',
            ],
            'wpdb' => ['name' => 'WPDB', 'color' => '#21759b'],
            'database' => ['name' => 'Database', 'color' => '#0f766e'],
            'ajax' => ['name' => 'AJAX', 'color' => '#0284c7'],
            'data-integration' => ['name' => 'Data Integration', 'color' => '#0891b2'],
            'facetwp' => ['name' => 'FacetWP', 'color' => '#1d4ed8'],
            'woocommerce' => ['name' => 'WooCommerce', 'color' => '#7c3aed'],
            'filtering' => ['name' => 'Filtering', 'color' => '#2563eb'],
        ];
        $tags = [];

        foreach ($definitions as $slug => $definition) {
            $tags[$slug] = $user->tags()->firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'color' => $definition['color'],
                ],
            );
        }

        return $tags;
    }

    /**
     * @param  array{title: string, filename: string, language: string, description: string, content: string, position: int}  $attributes
     * @param  list<array{position: int, name: string, content: string}>  $variations
     * @param  list<array{name: string, values: array<string, string>}>  $presets
     * @param  list<Tag>  $tags
     */
    private function createSnippet(
        Project $project,
        ?Folder $folder,
        User $user,
        array $attributes,
        array $variations,
        array $presets,
        array $tags,
    ): void {
        $locationKey = SnippetLocation::key($project->id, $folder?->id);
        $snippet = $user->snippets()->firstOrCreate(
            ['location_key' => $locationKey, 'filename' => $attributes['filename']],
            [
                ...Arr::except($attributes, ['content']),
                'project_id' => $project->id,
                'folder_id' => $folder?->id,
            ],
        );

        $snippet->tags()->syncWithoutDetaching(collect($tags)->pluck('id')->all());
        $frameworkSlugs = collect($tags)
            ->pluck('slug')
            ->intersect(['wordpress', 'laravel', 'react']);
        $snippet->frameworks()->syncWithoutDetaching(
            $user->frameworks()->whereIn('slug', $frameworkSlugs)->pluck('id')->all(),
        );

        if (! $snippet->wasRecentlyCreated) {
            return;
        }

        $snippet->variations()->createMany(
            collect($variations)
                ->map(fn (array $variation): array => [
                    ...$variation,
                    'created_by_id' => $user->id,
                    'is_default' => $variation['content'] === $attributes['content'],
                ])
                ->all(),
        );
        $snippet->variablePresets()->createMany($presets);
    }

    /**
     * @param  array{title: string, filename: string, language: string, description: string, content: string, position: int}  $attributes
     * @param  list<Tag>  $tags
     */
    private function createSingleVariationSnippet(
        Project $project,
        ?Folder $folder,
        User $user,
        array $attributes,
        string $variationName,
        array $tags,
    ): void {
        $this->createSnippet(
            project: $project,
            folder: $folder,
            user: $user,
            attributes: $attributes,
            variations: [
                [
                    'position' => 1,
                    'name' => $variationName,
                    'content' => $attributes['content'],
                ],
            ],
            presets: [],
            tags: $tags,
        );
    }
}

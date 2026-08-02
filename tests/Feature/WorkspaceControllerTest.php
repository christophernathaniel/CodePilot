<?php

use App\Models\Folder;
use App\Models\Framework;
use App\Models\Pin;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\SnippetVariation;
use App\Models\Tag;
use App\Models\User;
use App\Models\VariablePreset;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

test('workspace is account scoped and serializes the complete project tree', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create(['name' => 'My Project', 'position' => 2]);
    $framework = Framework::factory()->for($user)->create([
        'name' => 'Laravel',
        'slug' => 'laravel',
    ]);
    $project->frameworks()->attach($framework);
    $folder = Folder::factory()->for($project)->create(['name' => 'Source', 'position' => 1]);
    $snippet = Snippet::factory()->for($project)->inFolder($folder)->create([
        'title' => 'Fetch client',
        'filename' => 'fetch-client.js',
        'language' => 'javascript',
    ]);
    $compactVariation = SnippetVariation::factory()->for($snippet)->create([
        'created_by_id' => $user->id,
        'name' => 'Compact',
        'content' => 'const current=true;',
        'position' => 2,
        'is_default' => false,
    ]);
    $defaultVariation = SnippetVariation::factory()->for($snippet)->default()->create([
        'created_by_id' => $user->id,
        'name' => 'Readable',
        'content' => "const current = true;\n\n{!# snippet: export_current #!}\nexport { current };",
        'position' => 1,
    ]);
    VariablePreset::factory()->for($snippet)->create([
        'name' => 'Production',
        'values' => ['base_url' => 'https://example.com'],
    ]);
    $tag = Tag::factory()->for($user)->create(['name' => 'API', 'slug' => 'api']);
    $snippet->tags()->attach($tag);

    Project::factory()->for(User::factory()->create())->create(['name' => 'Private project']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('snippets/workspace')
            ->has('projects', 1)
            ->where('projects.0.id', $project->id)
            ->where('projects.0.frameworks.0.id', $framework->id)
            ->where('projects.0.frameworks.0.slug', 'laravel')
            ->where('projects.0.folders.0.id', $folder->id)
            ->where('projects.0.snippets.0.id', $snippet->id)
            ->missing('projects.0.snippets.0.content')
            ->missing('projects.0.snippets.0.versions')
            ->has('projects.0.snippets.0.variations', 2)
            ->where('projects.0.snippets.0.variations.0.id', $defaultVariation->id)
            ->where('projects.0.snippets.0.variations.0.is_default', true)
            ->has('projects.0.snippets.0.variations.0.sections', 1)
            ->where('projects.0.snippets.0.variations.0.sections.0.name', 'export_current')
            ->where('projects.0.snippets.0.variations.0.sections.0.content', 'export { current };')
            ->where('projects.0.snippets.0.variations.1.id', $compactVariation->id)
            ->has('projects.0.snippets.0.variations.1.sections', 0)
            ->where('projects.0.snippets.0.presets.0.name', 'Production')
            ->where('projects.0.snippets.0.tags.0.slug', 'api')
            ->where('tags.0.id', $tag->id)
            ->where('languages', fn (Collection $languages): bool => $languages->contains('javascript')),
        );
});

test('workspace serializes distinct file favourites without leaking another account', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create([
        'name' => 'Owned project',
    ]);
    $favouriteProjectFile = Snippet::factory()->forProject($project)->create([
        'filename' => 'a-favourite.php',
        'position' => 1,
        'is_favourite' => true,
    ]);
    $pinnedProjectFile = Snippet::factory()->forProject($project)->create([
        'filename' => 'b-pinned.php',
        'position' => 2,
        'is_favourite' => false,
    ]);
    $favouriteStandaloneFile = Snippet::factory()->for($user)->create([
        'filename' => 'a-standalone-favourite.php',
        'position' => 1,
        'is_favourite' => true,
    ]);
    $regularStandaloneFile = Snippet::factory()->for($user)->create([
        'filename' => 'b-standalone-regular.php',
        'position' => 2,
        'is_favourite' => false,
    ]);

    Pin::factory()->for($user)->create([
        'pinnable_type' => 'snippet',
        'pinnable_key' => (string) $pinnedProjectFile->id,
    ]);

    $otherUser = User::factory()->create();
    $otherProject = Project::factory()->for($otherUser)->create([
        'name' => 'Private project',
    ]);
    Snippet::factory()->forProject($otherProject)->create([
        'filename' => 'private-project-favourite.php',
        'is_favourite' => true,
    ]);
    Snippet::factory()->for($otherUser)->create([
        'filename' => 'private-standalone-favourite.php',
        'is_favourite' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('snippets/workspace')
            ->has('projects', 1)
            ->has('projects.0.snippets', 2)
            ->where('projects.0.snippets.0.id', $favouriteProjectFile->id)
            ->where('projects.0.snippets.0.is_favourite', true)
            ->where('projects.0.snippets.0.is_pinned', false)
            ->where('projects.0.snippets.1.id', $pinnedProjectFile->id)
            ->where('projects.0.snippets.1.is_favourite', false)
            ->where('projects.0.snippets.1.is_pinned', true)
            ->has('standalone_snippets', 2)
            ->where('standalone_snippets.0.id', $favouriteStandaloneFile->id)
            ->where('standalone_snippets.0.is_favourite', true)
            ->where('standalone_snippets.1.id', $regularStandaloneFile->id)
            ->where('standalone_snippets.1.is_favourite', false)
            ->where('pins.snippet_ids', [$pinnedProjectFile->id]),
        );
});

test('workspace serializes parsed guide steps only for guide files', function () {
    $this->withoutVite();

    $user = User::factory()->create();
    $guideCollection = Project::factory()->for($user)->guide()->create([
        'name' => 'WordPress Gutenberg',
    ]);
    $guide = Snippet::factory()->forProject($guideCollection)->guide()->withVariation(
        "{!# guide-step: install | Install WordPress #!}\nInstall the files.\n\n```bash\nwp core download\n```",
    )->create([
        'title' => 'Install WordPress',
        'filename' => 'install-wordpress.guide.md',
    ]);
    $snippet = Snippet::factory()->forProject($guideCollection)->withVariation(
        "{!# guide-step: ignored | Not a guide #!}\nThis remains ordinary snippet content.",
    )->create([
        'filename' => 'ordinary.php',
        'position' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('projects.0.kind', Project::KIND_GUIDE)
            ->where('projects.0.snippets.0.id', $guide->id)
            ->where('projects.0.snippets.0.content_type', Snippet::CONTENT_TYPE_GUIDE)
            ->has('projects.0.snippets.0.variations.0.guide_steps', 1)
            ->where('projects.0.snippets.0.variations.0.guide_steps.0.key', 'install')
            ->where('projects.0.snippets.0.variations.0.guide_steps.0.title', 'Install WordPress')
            ->where('projects.0.snippets.0.variations.0.guide_steps.0.instructions', 'Install the files.')
            ->where('projects.0.snippets.0.variations.0.guide_steps.0.code_blocks.0.language', 'bash')
            ->where('projects.0.snippets.0.variations.0.guide_steps.0.code_blocks.0.content', "wp core download\n")
            ->where('projects.0.snippets.1.id', $snippet->id)
            ->where('projects.0.snippets.1.content_type', Snippet::CONTENT_TYPE_SNIPPET)
            ->has('projects.0.snippets.1.variations.0.guide_steps', 0),
        );
});

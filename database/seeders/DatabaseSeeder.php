<?php

namespace Database\Seeders;

use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\Tag;
use App\Models\User;
use App\Support\Snippets\FrameworkCatalog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $developmentUser = User::factory()->create([
            'name' => 'Development User',
            'email' => 'dev@dev.dev',
        ]);

        FrameworkCatalog::seedFor($user);
        FrameworkCatalog::seedFor($developmentUser);

        $integrationProject = Project::factory()->for($user)->create([
            'kind' => 'project',
            'name' => 'Multi-platform Integration',
            'description' => 'Reusable API clients and integration helpers arranged like a working codebase.',
            'position' => 0,
        ]);

        $filesFolder = Folder::factory()->for($integrationProject)->create([
            'name' => 'files',
            'position' => 0,
        ]);
        $includesFolder = Folder::factory()->nestedUnder($filesFolder)->create([
            'name' => 'includes',
            'position' => 0,
        ]);
        $siteFolder = Folder::factory()->nestedUnder($includesFolder)->create([
            'name' => 'Site',
            'position' => 0,
        ]);
        $integrationFolder = Folder::factory()->nestedUnder($siteFolder)->create([
            'name' => 'Integration',
            'position' => 0,
        ]);
        $patchesFolder = Folder::factory()->for($integrationProject)->create([
            'name' => 'patches',
            'position' => 1,
        ]);

        $javascriptTag = Tag::factory()->for($user)->create([
            'name' => 'JavaScript',
            'slug' => 'javascript',
            'color' => '#f7df1e',
        ]);
        $phpTag = Tag::factory()->for($user)->create([
            'name' => 'PHP',
            'slug' => 'php',
            'color' => '#777bb4',
        ]);
        $apiTag = Tag::factory()->for($user)->create([
            'name' => 'API',
            'slug' => 'api',
            'color' => '#38bdf8',
        ]);
        $templateTag = Tag::factory()->for($user)->create([
            'name' => 'Template Variables',
            'slug' => 'template-variables',
            'color' => '#c084fc',
        ]);
        $gitTag = Tag::factory()->for($user)->create([
            'name' => 'Git',
            'slug' => 'git',
            'color' => '#f97316',
        ]);

        $apiClientSimpleVariation = <<<'JAVASCRIPT'
export async function fetchUsers() {
    const response = await fetch('{{{base_url:https://api.example.com}}}/users', {
        headers: {
            Authorization: 'Bearer {{{api_token:demo-token}}}',
        },
    });

    return response.json();
}
JAVASCRIPT;

        $apiClientDefaultVariation = <<<'JAVASCRIPT'
export async function fetchResource() {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), {{{timeout:5000}}});

    try {
        const response = await fetch(
            '{{{base_url:https://api.example.com}}}/{{{resource:users}}}',
            {
                headers: {
                    Authorization: 'Bearer {{{api_token:demo-token}}}',
                },
                signal: controller.signal,
            },
        );

        return await response.json();
    } finally {
        clearTimeout(timeout);
    }
}
JAVASCRIPT;

        $apiClient = Snippet::factory()->inFolder($integrationFolder)->create([
            'title' => 'Fetch API Client',
            'filename' => 'fetch-client.js',
            'language' => 'javascript',
            'description' => 'Fetch a configurable API resource with bearer authentication and a timeout.',
            'position' => 0,
            'last_opened_at' => now(),
        ]);

        $apiClient->variations()->createMany([
            [
                'created_by_id' => $user->id,
                'position' => 1,
                'name' => 'Simple users request',
                'content' => $apiClientSimpleVariation,
                'is_default' => false,
            ],
            [
                'created_by_id' => $user->id,
                'position' => 2,
                'name' => 'Configurable resource with timeout',
                'content' => $apiClientDefaultVariation,
                'is_default' => true,
            ],
        ]);
        $apiClient->variablePresets()->createMany([
            [
                'name' => 'Local development',
                'values' => [
                    'base_url' => 'http://localhost:8000',
                    'resource' => 'users',
                    'api_token' => 'local-development-token',
                    'timeout' => '3000',
                ],
            ],
            [
                'name' => 'Production placeholders',
                'values' => [
                    'base_url' => 'https://api.example.com',
                    'resource' => 'customers',
                    'api_token' => 'replace-before-copying',
                    'timeout' => '10000',
                ],
            ],
        ]);
        $apiClient->tags()->attach([
            $javascriptTag->id,
            $apiTag->id,
            $templateTag->id,
        ]);

        $courseFieldsContent = <<<'PHP'
<?php

final class CourseFields
{
    public static function defaults(): array
    {
        return [
            'places' => 0,
            'price' => 0,
            'trial_price' => 0,
            'deposit' => 0,
        ];
    }
}
PHP;

        $courseFields = Snippet::factory()->inFolder($integrationFolder)->create([
            'title' => 'Course Dynamic Fields',
            'filename' => 'Course.php',
            'language' => 'php',
            'description' => 'A compact field map for course pricing and availability.',
            'position' => 1,
        ]);
        $courseFields->variations()->create([
            'created_by_id' => $user->id,
            'position' => 1,
            'name' => 'Default',
            'content' => $courseFieldsContent,
            'is_default' => true,
        ]);
        $courseFields->tags()->attach($phpTag);

        $patchContent = <<<'DIFF'
diff --git a/src/client.js b/src/client.js
index 6c44f82..1db52cd 100644
--- a/src/client.js
+++ b/src/client.js
@@ -1,3 +1,4 @@
 export async function fetchResource() {
+    // Integration-specific override.
 }
DIFF;

        $patch = Snippet::factory()->inFolder($patchesFolder)->create([
            'title' => 'Stagecoach Theme Patch',
            'filename' => 'stagecoach-theme.patch',
            'language' => 'diff',
            'description' => 'An example patch stored alongside the integration files.',
            'position' => 0,
        ]);
        $patch->variations()->create([
            'created_by_id' => $user->id,
            'position' => 1,
            'name' => 'Default',
            'content' => $patchContent,
            'is_default' => true,
        ]);

        $releaseBundle = Project::factory()->bundle()->for($user)->create([
            'name' => 'Release Commands',
            'description' => 'Copy-ready terminal recipes for committing and shipping a focused change.',
            'position' => 1,
        ]);

        $releaseContent = <<<'SHELL'
git status --short
git add {{{path:.}}}
git commit -m "{{{commit_message:ship visual hero}}}"
git push origin {{{branch_name:main}}}
SHELL;

        $releaseCommands = Snippet::factory()->for($releaseBundle)->create([
            'title' => 'Commit and Push',
            'filename' => 'commit-and-push.sh',
            'language' => 'shell',
            'description' => 'A reusable Git commit and push sequence.',
            'position' => 0,
        ]);
        $releaseCommands->variations()->create([
            'created_by_id' => $user->id,
            'position' => 1,
            'name' => 'Default',
            'content' => $releaseContent,
            'is_default' => true,
        ]);
        $releaseCommands->variablePresets()->create([
            'name' => 'Main branch release',
            'values' => [
                'path' => '.',
                'commit_message' => 'ship visual hero',
                'branch_name' => 'main',
            ],
        ]);
        $releaseCommands->tags()->attach([
            $gitTag->id,
            $templateTag->id,
        ]);

        $this->call(SnippetExamplesSeeder::class);
        $this->call(WordPressGutenbergGuidesSeeder::class);
        $this->call(ClassesBundleSeeder::class);
        $this->call(RequestsBundleSeeder::class);
        $this->call(MySqlBundleSeeder::class);
        $this->call(BooksCategorySeeder::class);
    }
}

<?php

use App\Models\ClipboardClip;
use App\Models\ClipboardSession;
use App\Models\Folder;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\SnippetVariation;
use App\Models\User;
use App\Support\Snippets\GuideStepParser;

test('a clipboard creates a standalone file in visible order without consuming its mixed language clips', function () {
    $user = User::factory()->create();
    $clipboard = ClipboardSession::factory()->for($user)->active()->create([
        'name' => 'Combined helpers',
    ]);
    $oldest = ClipboardClip::factory()->for($clipboard)->create([
        'content' => "    first();\n",
        'language' => 'php',
        'created_at' => now()->subMinute(),
    ]);
    $sameTimeEarlier = ClipboardClip::factory()->for($clipboard)->create([
        'content' => 'second();',
        'language' => 'javascript',
        'created_at' => now(),
    ]);
    $sameTimeLater = ClipboardClip::factory()->for($clipboard)->create([
        'content' => "\tthird();\n",
        'language' => 'php',
        'created_at' => $sameTimeEarlier->created_at,
    ]);

    $this->actingAs($user)
        ->post(route('clipboards.files.store', $clipboard), clipboardFilePayload([
            'title' => 'Combined helpers',
            'filename' => 'combined-helpers.txt',
            'language' => 'plaintext',
            'content' => 'This client-supplied value must be ignored.',
        ]))
        ->assertSessionHasNoErrors();

    $file = $user->snippets()->where('filename', 'combined-helpers.txt')->sole();
    $variation = $file->variations()->sole();

    expect($file->project_id)->toBeNull()
        ->and($file->folder_id)->toBeNull()
        ->and($file->location_key)->toBe('standalone')
        ->and($file->content_type)->toBe(Snippet::CONTENT_TYPE_SNIPPET)
        ->and($file->language)->toBe('plaintext')
        ->and($variation->name)->toBe('Default')
        ->and($variation->position)->toBe(1)
        ->and($variation->is_default)->toBeTrue()
        ->and($variation->content)->toBe("\tthird();\n\n\nsecond();\n\n    first();\n")
        ->and($clipboard->fresh()->clips()->count())->toBe(3)
        ->and($clipboard->is_active)->toBeTrue();

    foreach ([$oldest, $sameTimeEarlier, $sameTimeLater] as $clip) {
        $this->assertModelExists($clip);
    }

    expect($oldest->fresh()->content)->toBe("    first();\n")
        ->and($sameTimeEarlier->fresh()->content)->toBe('second();')
        ->and($sameTimeLater->fresh()->content)->toBe("\tthird();\n");
});

test('clipboard files can be assigned to a project folder and a bundle root', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $folder = Folder::factory()->for($project)->create();
    $bundle = Project::factory()->for($user)->create([
        'kind' => Project::KIND_BUNDLE,
    ]);
    Snippet::factory()->inFolder($folder)->create(['position' => 5]);
    Snippet::factory()->forProject($bundle)->create(['position' => 4]);
    $folderClipboard = ClipboardSession::factory()->for($user)->active()->create();
    $folderClip = ClipboardClip::factory()->for($folderClipboard)->create([
        'content' => '<?php return true;',
        'language' => 'php',
    ]);
    $bundleClipboard = ClipboardSession::factory()->for($user)->create();
    $bundleClip = ClipboardClip::factory()->for($bundleClipboard)->create([
        'content' => 'export const ready = true;',
        'language' => 'javascript',
    ]);

    $this->actingAs($user)
        ->post(route('clipboards.files.store', $folderClipboard), clipboardFilePayload([
            'title' => 'Folder helper',
            'filename' => 'folder-helper.php',
            'language' => 'php',
            'project_id' => $project->id,
            'folder_id' => $folder->id,
        ]))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('clipboards.files.store', $bundleClipboard), clipboardFilePayload([
            'title' => 'Bundle helper',
            'filename' => 'bundle-helper.js',
            'language' => 'javascript',
            'project_id' => $bundle->id,
        ]))
        ->assertSessionHasNoErrors();

    $folderFile = $user->snippets()->where('filename', 'folder-helper.php')->sole();
    $bundleFile = $user->snippets()->where('filename', 'bundle-helper.js')->sole();

    expect($folderFile->project_id)->toBe($project->id)
        ->and($folderFile->folder_id)->toBe($folder->id)
        ->and($folderFile->location_key)->toBe('folder:'.$folder->id)
        ->and($folderFile->position)->toBe(6)
        ->and($folderFile->variations()->sole()->content)->toBe('<?php return true;')
        ->and($bundleFile->project_id)->toBe($bundle->id)
        ->and($bundleFile->folder_id)->toBeNull()
        ->and($bundleFile->location_key)->toBe('project:'.$bundle->id)
        ->and($bundleFile->position)->toBe(5)
        ->and($bundleFile->content_type)->toBe(Snippet::CONTENT_TYPE_SNIPPET)
        ->and($bundleFile->variations()->sole()->content)->toBe('export const ready = true;');

    $this->assertModelExists($folderClip);
    $this->assertModelExists($bundleClip);
});

test('a clipboard creates a valid guide with one provenanced step per clip', function () {
    $user = User::factory()->create();
    $guideCollection = Project::factory()->for($user)->guide()->create();
    $clipboard = ClipboardSession::factory()->for($user)->active()->create([
        'name' => 'Release guide',
    ]);
    $older = ClipboardClip::factory()->for($clipboard)->create([
        'content' => "npm run build\n",
        'language' => 'bash',
        'source_title' => 'Build release',
        'source_filename' => 'package.json',
        'source_project' => 'CodePilot',
        'source_folders' => ['resources', 'scripts'],
        'source_variation' => 'Production',
        'line_start' => 8,
        'line_end' => 8,
        'created_at' => now()->subMinute(),
    ]);
    $newer = ClipboardClip::factory()->for($clipboard)->create([
        'content' => "<?php\necho 'ready';\n",
        'language' => 'php',
        'source_title' => 'Build release',
        'source_filename' => 'Release.php',
        'source_project' => 'CodePilot',
        'source_folders' => ['app', 'Services'],
        'source_variation' => 'Readable',
        'line_start' => 12,
        'line_end' => 13,
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('clipboards.files.store', $clipboard), clipboardFilePayload([
            'title' => 'Release guide',
            'filename' => 'release.guide.md',
            'language' => 'php',
            'content_type' => Snippet::CONTENT_TYPE_GUIDE,
            'project_id' => $guideCollection->id,
        ]))
        ->assertSessionHasNoErrors();

    $guide = $user->snippets()->where('filename', 'release.guide.md')->sole();
    $source = $guide->variations()->sole()->content;
    $steps = (new GuideStepParser)->parse($source);

    expect($guide->project_id)->toBe($guideCollection->id)
        ->and($guide->folder_id)->toBeNull()
        ->and($guide->content_type)->toBe(Snippet::CONTENT_TYPE_GUIDE)
        ->and($guide->language)->toBe('markdown')
        ->and($steps)->toHaveCount(2)
        ->and($steps[0]['key'])->toBe('clip-'.$newer->id)
        ->and($steps[0]['title'])->toBe('Build release')
        ->and($steps[0]['position'])->toBe(1)
        ->and($steps[0]['instructions'])->toBe(
            'Source: CodePilot / app / Services / Release.php. Variation: Readable. Lines 12–13.',
        )
        ->and($steps[0]['code_blocks'])->toHaveCount(1)
        ->and($steps[0]['code_blocks'][0]['language'])->toBe('php')
        ->and($steps[0]['code_blocks'][0]['content'])->toBe("<?php\necho 'ready';\n")
        ->and($steps[1]['key'])->toBe('clip-'.$older->id)
        ->and($steps[1]['title'])->toBe('Build release')
        ->and($steps[1]['position'])->toBe(2)
        ->and($steps[1]['instructions'])->toBe(
            'Source: CodePilot / resources / scripts / package.json. Variation: Production. Line 8.',
        )
        ->and($steps[1]['code_blocks'])->toHaveCount(1)
        ->and($steps[1]['code_blocks'][0]['language'])->toBe('bash')
        ->and($steps[1]['code_blocks'][0]['content'])->toBe("npm run build\n")
        ->and($steps[0]['key'])->not->toBe($steps[1]['key'])
        ->and($clipboard->fresh()->clips()->count())->toBe(2);

    $this->assertModelExists($newer);
    $this->assertModelExists($older);
});

test('an empty clipboard cannot create a file', function () {
    $user = User::factory()->create();
    $clipboard = ClipboardSession::factory()->for($user)->active()->create();

    $this->actingAs($user)
        ->post(route('clipboards.files.store', $clipboard), clipboardFilePayload())
        ->assertSessionHasErrors('clipboard');

    expect($user->snippets()->doesntExist())->toBeTrue()
        ->and(SnippetVariation::query()->doesntExist())->toBeTrue();

    $this->assertModelExists($clipboard);
});

test('clipboard file creation is account scoped for the clipboard project and folder', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $clipboard = ClipboardSession::factory()->for($owner)->active()->create();
    $clip = ClipboardClip::factory()->for($clipboard)->create();
    $ownerProject = Project::factory()->for($owner)->create();
    $foreignProject = Project::factory()->for($intruder)->create();
    $foreignFolder = Folder::factory()->for($foreignProject)->create();

    $this->actingAs($intruder)
        ->post(route('clipboards.files.store', $clipboard), clipboardFilePayload())
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('clipboards.files.store', $clipboard), clipboardFilePayload([
            'project_id' => $foreignProject->id,
        ]))
        ->assertSessionHasErrors('project_id');

    $this->actingAs($owner)
        ->post(route('clipboards.files.store', $clipboard), clipboardFilePayload([
            'project_id' => $ownerProject->id,
            'folder_id' => $foreignFolder->id,
        ]))
        ->assertSessionHasErrors('folder_id');

    expect($owner->snippets()->doesntExist())->toBeTrue()
        ->and($intruder->snippets()->doesntExist())->toBeTrue()
        ->and($clipboard->fresh()->clips()->count())->toBe(1);

    $this->assertModelExists($clip);
});

test('a filename collision does not create a file or consume the clipboard', function () {
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();
    $existing = Snippet::factory()->forProject($project)->create([
        'filename' => 'combined.php',
    ]);
    $clipboard = ClipboardSession::factory()->for($user)->active()->create();
    $clip = ClipboardClip::factory()->for($clipboard)->create([
        'content' => '<?php return true;',
        'language' => 'php',
    ]);

    $this->actingAs($user)
        ->post(route('clipboards.files.store', $clipboard), clipboardFilePayload([
            'filename' => 'combined.php',
            'language' => 'php',
            'project_id' => $project->id,
        ]))
        ->assertSessionHasErrors('filename');

    expect($project->snippets()->count())->toBe(1)
        ->and($clipboard->fresh()->clips()->count())->toBe(1);

    $this->assertModelExists($existing);
    $this->assertModelExists($clip);
});

test('a clipboard file can be recovered after its original source snippet is deleted', function () {
    $user = User::factory()->create();
    $source = Snippet::factory()->for($user)->withVariation('original source')->create();
    $sourceVariation = $source->variations()->sole();
    $clipboard = ClipboardSession::factory()->for($user)->active()->create();
    $clip = ClipboardClip::factory()->for($clipboard)->create([
        'snippet_id' => $source->id,
        'snippet_variation_id' => $sourceVariation->id,
        'content' => "immutable snapshot\n",
        'language' => 'plaintext',
        'source_title' => $source->title,
        'source_filename' => $source->filename,
    ]);

    $source->forceDelete();
    $clip->refresh();

    expect($clip->snippet_id)->toBeNull()
        ->and($clip->snippet_variation_id)->toBeNull();

    $this->actingAs($user)
        ->post(route('clipboards.files.store', $clipboard), clipboardFilePayload([
            'title' => 'Recovered source',
            'filename' => 'recovered.txt',
            'language' => 'plaintext',
        ]))
        ->assertSessionHasNoErrors();

    $recovered = $user->snippets()->where('filename', 'recovered.txt')->sole();

    expect($recovered->variations()->sole()->content)->toBe("immutable snapshot\n")
        ->and($clipboard->fresh()->clips()->count())->toBe(1)
        ->and($clip->fresh()->content)->toBe("immutable snapshot\n");

    $this->assertModelExists($clip);
});

/** @param array<string, mixed> $overrides */
function clipboardFilePayload(array $overrides = []): array
{
    return array_replace([
        'title' => 'Combined clipboard',
        'filename' => 'combined.php',
        'language' => 'php',
        'content_type' => Snippet::CONTENT_TYPE_SNIPPET,
        'description' => null,
        'project_id' => null,
        'folder_id' => null,
        'tags' => [],
        'frameworks' => [],
    ], $overrides);
}

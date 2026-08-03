<?php

use Illuminate\Support\Str;

test('the highlighted snippet row is the file open hit area', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/project-explorer.tsx',
    );
    $snippetRow = Str::between(
        $source,
        'function SnippetRow(',
        'function BrowseGroupSection(',
    );
    $entityMenu = Str::between(
        $source,
        'function EntityMenu(',
        'function EmptyBrowserMessage(',
    );

    expect($snippetRow)
        ->toContain('onClick={() => onOpen(snippet)}')
        ->toContain('cursor-pointer')
        ->and($entityMenu)
        ->toContain('onClick={(event) => event.stopPropagation()}');
});

test('workspace children are indented beneath their workspace', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/project-explorer.tsx',
    );
    $projectTree = Str::between(
        $source,
        'function ProjectTreeContent(',
        'type FolderNodeProps =',
    );

    expect($projectTree)
        ->toContain('const projectChildDepth = onReorderProjects ? 2 : 0;')
        ->and(substr_count($projectTree, 'depth={projectChildDepth}'))->toBe(2);
});

test('library categories expose persistent rename and delete controls', function () {
    $projectDirectory = dirname(__DIR__, 2);
    $projectExplorer = file_get_contents(
        $projectDirectory.'/resources/js/components/snippets/project-explorer.tsx',
    );
    $sidePanel = file_get_contents(
        $projectDirectory.'/resources/js/components/snippets/workspace-side-panel.tsx',
    );
    $categoryMenu = Str::between(
        $projectExplorer,
        'function LibraryCategoryMenu(',
        'function ProjectTreeContent(',
    );
    $managementMenu = Str::between(
        $sidePanel,
        'function LibraryCategoryManagementMenu(',
        'function SearchFilterBar(',
    );

    expect($categoryMenu)
        ->toContain('aria-label={`${category.name} category actions`}')
        ->toContain('title={`Manage ${category.name} category`}')
        ->toContain('Rename category', 'Delete category')
        ->and($managementMenu)
        ->toContain('aria-label="Manage library categories"')
        ->toContain('libraryCategories.map((category)')
        ->toContain('onRename(category)', 'onDelete(category)')
        ->toContain('New category');
});

test('framework browsing exposes an action to add a framework', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/workspace-side-panel.tsx',
    );

    expect($source)
        ->toContain("displayedBrowseMode === 'framework'")
        ->toContain('onClick={onNewFramework}')
        ->toContain('New framework');
});

<?php

test('workspace chrome uses surfaces instead of stacked structural borders', function () {
    $componentDirectory = dirname(__DIR__, 2).'/resources/js/components/snippets/';
    $activityBar = file_get_contents($componentDirectory.'workspace-activity-bar.tsx');
    $sidePanel = file_get_contents($componentDirectory.'workspace-side-panel.tsx');
    $search = file_get_contents($componentDirectory.'snippet-search.tsx');
    $editorChrome = file_get_contents($componentDirectory.'snippet-editor-chrome.tsx');
    $inspector = file_get_contents($componentDirectory.'snippet-inspector.tsx');

    expect($activityBar)
        ->not->toContain('border-r border-code-border bg-code-canvas')
        ->and($sidePanel)
        ->not->toContain('border-r border-code-border bg-code-panel')
        ->not->toContain('border-b border-code-border p-2.5')
        ->and($search)
        ->not->toContain('items-center border border-code-border bg-code-raised')
        ->toContain('aria-label="Clear search"')
        ->toContain("onQueryChange('')")
        ->toContain('searchInputRef.current?.focus()')
        ->and($editorChrome)
        ->not->toContain('border-b border-code-border bg-code-canvas')
        ->not->toContain('border-b border-code-border bg-code-panel')
        ->not->toContain('border-t border-code-border bg-code-panel')
        ->and($inspector)
        ->not->toContain('border-l border-code-border bg-code-panel')
        ->not->toContain('grid-cols-3 border-b border-code-border')
        ->not->toContain('rounded-lg border border-dashed border-code-border');
});

test('workspace search keeps sidebar hero and mega search state separate', function () {
    $projectDirectory = dirname(__DIR__, 2);
    $workspace = file_get_contents($projectDirectory.'/resources/js/pages/snippets/workspace.tsx');
    $sidePanel = file_get_contents($projectDirectory.'/resources/js/components/snippets/workspace-side-panel.tsx');
    $search = file_get_contents($projectDirectory.'/resources/js/components/snippets/snippet-search.tsx');
    $megaSearch = file_get_contents($projectDirectory.'/resources/js/components/snippets/workspace-mega-search.tsx');
    $searchPreview = file_get_contents($projectDirectory.'/resources/js/components/snippets/snippet-search-preview.tsx');
    $syntaxHighlightedCode = file_get_contents($projectDirectory.'/resources/js/components/snippets/syntax-highlighted-code.tsx');
    $dialogComponent = file_get_contents($projectDirectory.'/resources/js/components/ui/dialog.tsx');
    $activityBar = file_get_contents($projectDirectory.'/resources/js/components/snippets/workspace-activity-bar.tsx');

    expect($workspace)
        ->toContain('const sidebarSearchInputRef = useRef<HTMLInputElement>(null)')
        ->toContain('const heroSearchInputRef = useRef<HTMLInputElement>(null)')
        ->toContain('const megaSearchInputRef = useRef<HTMLInputElement>(null)')
        ->toContain('const megaSearchReturnFocusRef = useRef<HTMLElement | null>(null)')
        ->toContain("const [megaSearchQuery, setMegaSearchQuery] = useState('')")
        ->toContain("event.key.toLowerCase() === 'p'")
        ->toContain('sidebarSearchInputRef.current?.focus({ preventScroll: true })')
        ->toContain('megaSearchInputRef.current?.focus({ preventScroll: true })')
        ->toContain('inputRef={sidebarSearchInputRef}')
        ->toContain('inputRef={heroSearchInputRef}')
        ->toContain('inputRef={megaSearchInputRef}')
        ->not->toContain('const searchInputRef = useRef<HTMLInputElement>(null)')
        ->and($sidePanel)
        ->toContain('behavior="filter"')
        ->toContain('<SearchFilterBar')
        ->and($search)
        ->toContain("shortcutAriaLabel = 'Meta+K Control+K'")
        ->toContain('showResultsWithoutQuery ||')
        ->toContain('(isOpen && query.trim().length > 0)');

    expect($activityBar)
        ->toContain('aria-label="Quick file search"')
        ->toContain('aria-keyshortcuts="Meta+P Control+P"')
        ->toContain('onClick={onMegaSearchOpen}')
        ->and($megaSearch)
        ->toContain('<DialogContent')
        ->toContain('<DialogTitle className="sr-only">Quick file search</DialogTitle>')
        ->toContain('resultsLabel="Files & sections"')
        ->toContain('totalResults={totalResults}')
        ->toContain('resultsMode={hasSearch ? \'workspace\' : \'popover\'}')
        ->toContain('showResultsWithoutQuery={hasTaxonomyFilters}')
        ->toContain('const [areFiltersOpen, setAreFiltersOpen] = useState(false)')
        ->toContain('inputActions={')
        ->toContain('aria-expanded={areFiltersOpen}')
        ->toContain('aria-controls={filterPanelId}')
        ->toContain('aria-label={filterToggleLabel}')
        ->toContain('hidden={!areFiltersOpen}')
        ->toContain('<MegaSearchFilters')
        ->toContain("searchCode ? null : 'code off'")
        ->toContain('symbol="@"')
        ->toContain('symbol="$"')
        ->toContain('symbol="%"')
        ->toContain('role="switch"')
        ->toContain('<option value="">Language</option>')
        ->not->toContain('<span>filters combine</span>')
        ->toContain('<SnippetSearchPreview')
        ->toContain('shortcutAriaLabel="Meta+P Control+P"')
        ->toContain('deferEscapeToParent')
        ->toContain('onCloseAutoFocus={(event) => event.preventDefault()}')
        ->not->toContain('onCopySection=');

    expect($search)
        ->toContain('role="combobox"')
        ->toContain("completionSuggestion ? 'both' : 'list'")
        ->toContain('getSearchSuggestionCaretPosition(')
        ->toContain('onCaretChange?.(nextCaretPosition)')
        ->toContain('onKeyUp={(event) => {')
        ->toContain('onMouseUp={(event) =>')
        ->toContain("'ArrowLeft',")
        ->toContain('restoreSelection = false')
        ->toContain('syncCaretPosition(event.currentTarget, true)')
        ->toContain('inputActions?: ReactNode')
        ->toContain('{inputActions}')
        ->toContain('searchHelp ? searchHelpId : null')
        ->toContain('aria-activedescendant={activeOptionId}')
        ->toContain('role="listbox"')
        ->toContain('role="option"')
        ->toContain("resultsMode?: 'popover' | 'workspace'")
        ->toContain("scrollIntoView({ block: 'nearest' })")
        ->toContain('tabIndex={-1}')
        ->toContain('if (deferEscapeToParent)')
        ->toContain('!event.shiftKey')
        ->toContain('min-[32rem]:grid-cols-')
        ->not->toContain('landscape:grid-cols-')
        ->not->toContain('Complete query')
        ->and($searchPreview)
        ->toContain('resolveTemplatePreview(')
        ->toContain('<SyntaxHighlightedCode')
        ->toContain('source={resolvedPreview.source}')
        ->toContain('highlightRange={resolvedPreview.highlightRange}')
        ->toContain('startLine={preview.startLine}')
        ->toContain('onClick={() => onOpen(result)}');

    expect($syntaxHighlightedCode)
        ->toContain('highlightRange?: CodeHighlightRange | null')
        ->toContain('startLine + index')
        ->toContain('<mark className=')
        ->and($dialogComponent)
        ->toContain('data-slot="dialog-close"');

    expect($workspace)
        ->toContain('const results = rankMegaSearchCandidates<SnippetSearchResult>(')
        ->toContain('items: results.slice(0, 80)')
        ->toContain('totalResults={megaSearchResults.total}')
        ->toContain('excerpt: match.excerpt')
        ->toContain('includeCode: megaSearchIncludesCode')
        ->toContain('hasMegaSearchTaxonomyFilters')
        ->toContain('languageValue={megaSearchLanguage}')
        ->toContain('categoryValue={megaSearchLibraryCategoryId}')
        ->toContain('frameworkValue={megaSearchFrameworkId}')
        ->toContain('searchCode={megaSearchIncludesCode}')
        ->not->toContain("megaSearchOpen && event.key === 'Escape'");
});

test('workspace sidebars expose persistent accessible resize handles', function () {
    $projectDirectory = dirname(__DIR__, 2);
    $workspace = file_get_contents($projectDirectory.'/resources/js/pages/snippets/workspace.tsx');
    $sidePanel = file_get_contents($projectDirectory.'/resources/js/components/snippets/workspace-side-panel.tsx');
    $inspector = file_get_contents($projectDirectory.'/resources/js/components/snippets/snippet-inspector.tsx');
    $resizeHandle = file_get_contents($projectDirectory.'/resources/js/components/snippets/workspace-resize-handle.tsx');

    expect($workspace)
        ->toContain('codepilot.workspace.library-panel-width.v1.')
        ->toContain('codepilot.workspace.inspector-panel-width.v1.')
        ->toContain('label="Resize library sidebar"')
        ->toContain('label="Resize snippet details sidebar"')
        ->toContain('workspaceCenterMinimumWidth')
        ->toContain('workspacePanelMaximumWidth(')
        ->not->toContain('className="hidden md:flex"')
        ->not->toContain('className="hidden xl:flex"')
        ->and($sidePanel)
        ->toContain('id="workspace-library-panel"')
        ->toContain('h-full w-full min-w-0')
        ->and($inspector)
        ->toContain('id="workspace-inspector-panel"')
        ->toContain('h-full w-full min-w-0')
        ->and($resizeHandle)
        ->toContain('role="separator"')
        ->toContain('aria-controls={controls}')
        ->toContain('aria-orientation="vertical"')
        ->toContain('setPointerCapture(event.pointerId)')
        ->toContain("event.key === 'Home'")
        ->toContain("event.key === 'End'");
});

test('workspace code editor persists an accessible word-wrap preference', function () {
    $projectDirectory = dirname(__DIR__, 2);
    $workspace = file_get_contents($projectDirectory.'/resources/js/pages/snippets/workspace.tsx');
    $editor = file_get_contents($projectDirectory.'/resources/js/components/snippets/snippet-editor.tsx');
    $chrome = file_get_contents($projectDirectory.'/resources/js/components/snippets/snippet-editor-chrome.tsx');

    expect($workspace)
        ->toContain('codepilot.workspace.word-wrap.v1.')
        ->toContain('wordWrap={wordWrap}')
        ->toContain('onWordWrapToggle={() =>')
        ->and($chrome)
        ->toContain("'Turn on word wrap'")
        ->toContain('aria-pressed={wordWrap}')
        ->and($editor)
        ->toContain("wrap={wordWrap ? 'soft' : 'off'}")
        ->toContain("wordWrap ? 'left-0' : 'left-14'");
});

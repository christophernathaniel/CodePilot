import {
    Braces,
    ChevronsUp,
    FilePlus2,
    FolderTree,
    LayoutGrid,
    PackagePlus,
    Pencil,
    Pin,
    Plus,
    Search,
    Tags,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode, RefObject } from 'react';
import {
    ProjectExplorer,
    libraryPinKey,
} from '@/components/snippets/project-explorer';
import type {
    ExplorerDragItem,
    ExplorerDropTarget,
    ExplorerEntity,
    InlineRenameCallbacks,
    LibraryBrowseMode,
    LibraryPinTarget,
} from '@/components/snippets/project-explorer';
import { SnippetSearch } from '@/components/snippets/snippet-search';
import type { SnippetSearchResult } from '@/components/snippets/snippet-search';
import type { SnippetSectionSearchResult } from '@/components/snippets/snippet-search';
import type { WorkspacePanel } from '@/components/snippets/workspace-activity-bar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuPortal,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { libraryCategoryGroupKey } from '@/lib/snippets/library-category-groups';
import type {
    SnippetCodeExcerpt,
    SnippetExcerptMode,
    SnippetSearchScope,
    WorkspaceSearchEntity,
} from '@/lib/snippets/search-query';
import { hasActiveSearchQuery } from '@/lib/snippets/search-query';
import { cn } from '@/lib/utils';
import type {
    Framework,
    LanguageOption,
    LibraryCategory,
    LibraryTrash,
    LibraryTrashItem,
    Snippet,
    SnippetFolder,
    SnippetProject,
    Tag,
} from '@/types';

const browseModes: Array<{ value: LibraryBrowseMode; label: string }> = [
    { value: 'projects', label: 'Workspaces' },
    { value: 'flat', label: 'All snippets' },
    { value: 'guides', label: 'Guides' },
    { value: 'favourites', label: 'Favourites' },
    { value: 'language', label: 'Languages' },
    { value: 'tag', label: 'Tags' },
    { value: 'framework', label: 'Frameworks' },
    { value: 'pinned', label: 'Pinned' },
    { value: 'trash', label: 'Trash' },
];

const searchEntityOptions: Array<{
    value: WorkspaceSearchEntity;
    label: string;
}> = [
    { value: 'all', label: 'Everything' },
    { value: 'projects', label: 'Projects' },
    { value: 'snippets', label: 'Snippets' },
    { value: 'guides', label: 'Guides' },
];

const searchScopeOptions: Array<{
    value: SnippetSearchScope;
    label: string;
}> = [
    { value: 'all', label: 'Anywhere' },
    { value: 'file', label: 'File name' },
    { value: 'code', label: 'Code in files' },
    { value: 'project', label: 'Project' },
    { value: 'folder', label: 'Folder' },
    { value: 'framework', label: 'Framework' },
    { value: 'tag', label: 'Tag' },
    { value: 'language', label: 'Language' },
];

const excerptModeOptions: Array<{
    value: SnippetExcerptMode;
    label: string;
}> = [
    { value: 'off', label: 'Off' },
    { value: 'hover', label: 'Hover' },
    { value: 'always', label: 'Always' },
];

type ExpansionState = {
    storageKey: string;
    collapsedLibraryCategoryKeys: Set<string>;
    projectIds: Set<number>;
    folderIds: Set<number>;
};

export type WorkspaceSidePanelProps = {
    panel: WorkspacePanel;
    libraryCategories: LibraryCategory[];
    projects: SnippetProject[];
    standaloneSnippets?: Snippet[];
    visibleSnippets?: Snippet[];
    matchedProjectIds?: ReadonlySet<number>;
    matchedFolderIds?: ReadonlySet<number>;
    languageOptions?: LanguageOption[];
    frameworks?: Framework[];
    tags: Tag[];
    query: string;
    searchEntity: WorkspaceSearchEntity;
    searchScope: SnippetSearchScope;
    searchFrameworkId: number | null;
    searchExcerptMode: SnippetExcerptMode;
    searchCodeMatches: ReadonlyMap<number, SnippetCodeExcerpt>;
    searchResultCount: number;
    searchFiltering: boolean;
    suggestions: string[];
    results: SnippetSearchResult[];
    inputRef: RefObject<HTMLInputElement | null>;
    activeSnippetId: number | null;
    dirtySnippetIds: Set<number>;
    revealedProjectId: number | null;
    revealedFolderId: number | null;
    accountKey?: string | number;
    browseMode?: LibraryBrowseMode;
    defaultBrowseMode?: LibraryBrowseMode;
    pinnedKeys?: ReadonlySet<string>;
    trash?: LibraryTrash;
    onBrowseModeChange?: (mode: LibraryBrowseMode) => void;
    onQueryChange: (query: string) => void;
    onSearchEntityChange: (entity: WorkspaceSearchEntity) => void;
    onSearchScopeChange: (scope: SnippetSearchScope) => void;
    onSearchFrameworkChange: (frameworkId: number | null) => void;
    onSearchExcerptModeChange: (mode: SnippetExcerptMode) => void;
    onSearchFocus?: () => void;
    onSuggestionAccept: (suggestion: string) => void;
    onSearchOpen: (result: SnippetSearchResult) => void;
    onCopySection?: (result: SnippetSectionSearchResult) => void;
    onOpenSnippet: (snippet: Snippet) => void;
    onNewProject: (category?: LibraryCategory | null) => void;
    onNewFramework: () => void;
    onNewLibraryCategory: () => void;
    onRenameLibraryCategory: (category: LibraryCategory) => void;
    onDeleteLibraryCategory: (category: LibraryCategory) => void;
    onCreateSnippet: () => void;
    onNewFolder: (
        project: SnippetProject,
        parent: SnippetFolder | null,
    ) => void;
    onNewSnippet: (
        project: SnippetProject,
        folder: SnippetFolder | null,
    ) => void;
    onRename: (entity: ExplorerEntity) => void;
    onInlineRename: (
        entity: ExplorerEntity,
        name: string,
        callbacks: InlineRenameCallbacks,
    ) => void;
    onDelete: (entity: ExplorerEntity) => void;
    onRestore: (item: LibraryTrashItem) => void;
    onPermanentlyDelete: (item: LibraryTrashItem) => void;
    onToggleFavourite?: (snippet: Snippet) => void;
    onTogglePin?: (target: LibraryPinTarget) => void;
    onMove?: (item: ExplorerDragItem, target: ExplorerDropTarget) => void;
    onReorderProjects?: (projectIds: number[]) => void;
};

export function WorkspaceSidePanel({
    panel,
    libraryCategories,
    projects,
    standaloneSnippets = [],
    visibleSnippets,
    matchedProjectIds = new Set<number>(),
    matchedFolderIds = new Set<number>(),
    languageOptions = [],
    frameworks = [],
    tags,
    query,
    searchEntity,
    searchScope,
    searchFrameworkId,
    searchExcerptMode,
    searchCodeMatches,
    searchResultCount,
    searchFiltering,
    suggestions,
    results,
    inputRef,
    activeSnippetId,
    dirtySnippetIds,
    revealedProjectId,
    revealedFolderId,
    accountKey = 'anonymous',
    browseMode,
    defaultBrowseMode = 'projects',
    pinnedKeys = new Set<string>(),
    trash = { projects: [], folders: [], snippets: [] },
    onBrowseModeChange,
    onQueryChange,
    onSearchEntityChange,
    onSearchScopeChange,
    onSearchFrameworkChange,
    onSearchExcerptModeChange,
    onSearchFocus,
    onSuggestionAccept,
    onSearchOpen,
    onCopySection,
    onOpenSnippet,
    onNewProject,
    onNewFramework,
    onNewLibraryCategory,
    onRenameLibraryCategory,
    onDeleteLibraryCategory,
    onCreateSnippet,
    onNewFolder,
    onNewSnippet,
    onRename,
    onInlineRename,
    onDelete,
    onRestore,
    onPermanentlyDelete,
    onToggleFavourite,
    onTogglePin,
    onMove,
    onReorderProjects,
}: WorkspaceSidePanelProps) {
    const [localBrowseMode, setLocalBrowseMode] =
        useState<LibraryBrowseMode>(defaultBrowseMode);
    const resolvedBrowseMode = browseMode ?? localBrowseMode;
    const expansionStorageKey = `codepilot.library.expansion.v1:${String(accountKey)}`;
    const [expansion, setExpansion] = useState<ExpansionState>(() =>
        readExpansionState(expansionStorageKey),
    );
    const allSnippets = useMemo(
        () => [
            ...standaloneSnippets,
            ...projects.flatMap((project) => project.snippets),
        ],
        [projects, standaloneSnippets],
    );
    const resolvedVisibleSnippets = useMemo(
        () =>
            visibleSnippets ??
            inferVisibleSnippets(allSnippets, projects, results, query),
        [allSnippets, projects, query, results, visibleSnippets],
    );
    const hasQuery = hasActiveSearchQuery(query);
    const isTrash = panel === 'explorer' && resolvedBrowseMode === 'trash';
    const title = isTrash
        ? 'Trash'
        : panel === 'explorer'
          ? 'Library'
          : panel === 'search'
            ? 'Search'
            : 'Tags';
    const TitleIcon = isTrash
        ? Trash2
        : panel === 'explorer'
          ? FolderTree
          : panel === 'search'
            ? Search
            : Tags;
    const displayedBrowseMode =
        panel === 'search'
            ? searchEntity === 'snippets'
                ? 'flat'
                : searchEntity === 'guides'
                  ? 'guides'
                  : 'projects'
            : resolvedBrowseMode;
    const displayedItemCount =
        panel === 'search'
            ? searchResultCount
            : displayedBrowseMode === 'trash'
              ? trash.projects.length +
                trash.folders.length +
                trash.snippets.length
              : resolvedVisibleSnippets.length;
    const libraryCategoryKeys = useMemo(() => {
        const categoryIds = new Set(
            libraryCategories.map((category) => category.id),
        );

        return [
            ...libraryCategories.map((category) =>
                libraryCategoryGroupKey(category),
            ),
            ...(projects.some(
                (project) =>
                    project.library_category_id === null ||
                    !categoryIds.has(project.library_category_id),
            )
                ? [libraryCategoryGroupKey(null)]
                : []),
        ];
    }, [libraryCategories, projects]);
    const allLibraryCategoriesCollapsed = libraryCategoryKeys.every((key) =>
        expansion.collapsedLibraryCategoryKeys.has(key),
    );

    useEffect(() => {
        if (
            expansion.storageKey !== expansionStorageKey ||
            typeof window === 'undefined'
        ) {
            return;
        }

        window.localStorage.setItem(
            expansionStorageKey,
            JSON.stringify({
                collapsedCategories: [
                    ...expansion.collapsedLibraryCategoryKeys,
                ],
                projects: [...expansion.projectIds],
                folders: [...expansion.folderIds],
            }),
        );
    }, [expansion, expansionStorageKey]);

    const changeBrowseMode = (mode: LibraryBrowseMode) => {
        if (browseMode === undefined) {
            setLocalBrowseMode(mode);
        }

        onBrowseModeChange?.(mode);
    };

    const setProjectExpanded = (projectId: number, expanded: boolean) => {
        setExpansion((current) => ({
            ...current,
            projectIds: setSetValue(current.projectIds, projectId, expanded),
        }));
    };

    const setLibraryCategoryExpanded = (
        categoryKey: string,
        expanded: boolean,
    ) => {
        setExpansion((current) => ({
            ...current,
            collapsedLibraryCategoryKeys: setSetValue(
                current.collapsedLibraryCategoryKeys,
                categoryKey,
                !expanded,
            ),
        }));
    };

    const setFolderExpanded = (folderId: number, expanded: boolean) => {
        setExpansion((current) => ({
            ...current,
            folderIds: setSetValue(current.folderIds, folderId, expanded),
        }));
    };

    const collapseAll = () => {
        setExpansion((current) => ({
            ...current,
            collapsedLibraryCategoryKeys: new Set(libraryCategoryKeys),
            projectIds: new Set(),
            folderIds: new Set(),
        }));
    };

    return (
        <aside
            id="workspace-library-panel"
            className="flex h-full w-full min-w-0 shrink-0 flex-col bg-code-panel"
        >
            <header className="flex min-h-12 shrink-0 items-center gap-2 px-3 py-2">
                <TitleIcon className="size-3.5 text-code-muted" />
                <h1 className="text-xs font-medium text-code-text">{title}</h1>
                <span
                    role={panel === 'search' ? 'status' : undefined}
                    aria-live={panel === 'search' ? 'polite' : undefined}
                    className="ml-auto font-mono text-[9px] text-code-faint"
                >
                    {displayedItemCount}{' '}
                    {panel === 'search'
                        ? displayedItemCount === 1
                            ? 'result'
                            : 'results'
                        : displayedBrowseMode === 'trash'
                          ? 'deleted'
                          : hasQuery
                            ? 'matching'
                            : 'snippets'}
                </span>
            </header>

            <div className="relative z-20 shrink-0 p-2.5">
                <SnippetSearch
                    query={query}
                    suggestions={suggestions}
                    results={results}
                    inputRef={inputRef}
                    behavior="filter"
                    onFocus={onSearchFocus}
                    onQueryChange={onQueryChange}
                    onSuggestionAccept={onSuggestionAccept}
                    onOpen={onSearchOpen}
                    onCopySection={onCopySection}
                />
                {panel === 'search' && hasQuery && (
                    <SearchFilterBar
                        entity={searchEntity}
                        scope={searchScope}
                        frameworkId={searchFrameworkId}
                        excerptMode={searchExcerptMode}
                        frameworks={frameworks}
                        onEntityChange={onSearchEntityChange}
                        onScopeChange={onSearchScopeChange}
                        onFrameworkChange={onSearchFrameworkChange}
                        onExcerptModeChange={onSearchExcerptModeChange}
                    />
                )}
            </div>

            {panel !== 'tags' && (
                <div className="flex h-9 shrink-0 items-center gap-1 px-2">
                    {panel === 'explorer' ? (
                        <label className="relative min-w-0 flex-1">
                            <span className="sr-only">Browse library by</span>
                            <select
                                value={resolvedBrowseMode}
                                onChange={(event) =>
                                    changeBrowseMode(
                                        event.target.value as LibraryBrowseMode,
                                    )
                                }
                                className="h-6 w-full appearance-none rounded bg-code-canvas px-2 pr-6 text-[10px] font-medium text-code-muted transition outline-none hover:bg-code-hover hover:text-code-text focus-visible:ring-1 focus-visible:ring-code-accent/60"
                            >
                                {browseModes.map((mode) => (
                                    <option key={mode.value} value={mode.value}>
                                        {mode.label}
                                    </option>
                                ))}
                            </select>
                            <ChevronDownIcon />
                        </label>
                    ) : (
                        <span className="min-w-0 flex-1 truncate px-1 text-[9px] font-semibold tracking-[0.12em] text-code-faint uppercase">
                            {searchEntity === 'projects'
                                ? 'Matching projects'
                                : searchEntity === 'guides'
                                  ? 'Matching guides'
                                  : searchEntity === 'snippets'
                                    ? 'Matching snippets'
                                    : 'Matching library items'}
                        </span>
                    )}
                    {displayedBrowseMode === 'projects' && (
                        <button
                            type="button"
                            onClick={collapseAll}
                            disabled={
                                expansion.projectIds.size === 0 &&
                                expansion.folderIds.size === 0 &&
                                allLibraryCategoriesCollapsed
                            }
                            className="flex h-6 items-center gap-1 rounded px-1.5 text-[9px] text-code-faint transition hover:bg-code-hover hover:text-code-text disabled:cursor-not-allowed disabled:opacity-35"
                        >
                            <ChevronsUp className="size-3" /> Collapse all
                        </button>
                    )}
                    {displayedBrowseMode === 'framework' && (
                        <button
                            type="button"
                            onClick={onNewFramework}
                            className="flex h-6 items-center gap-1 rounded px-1.5 text-[9px] text-code-faint transition hover:bg-code-hover hover:text-code-text"
                        >
                            <Braces className="size-3" /> New framework
                        </button>
                    )}
                </div>
            )}

            {panel === 'tags' ? (
                <TagPanel
                    tags={tags}
                    query={query}
                    pinnedKeys={pinnedKeys}
                    onQueryChange={onQueryChange}
                    onTogglePin={onTogglePin}
                />
            ) : (
                <ProjectExplorer
                    libraryCategories={libraryCategories}
                    projects={projects}
                    standaloneSnippets={standaloneSnippets}
                    visibleSnippets={resolvedVisibleSnippets}
                    matchedProjectIds={matchedProjectIds}
                    matchedFolderIds={matchedFolderIds}
                    languageOptions={languageOptions}
                    frameworks={frameworks}
                    tags={tags}
                    browseMode={displayedBrowseMode}
                    filtering={searchFiltering}
                    includeMatchedProjectContents={searchEntity === 'all'}
                    searchCodeMatches={searchCodeMatches}
                    searchExcerptMode={searchExcerptMode}
                    activeSnippetId={activeSnippetId}
                    dirtySnippetIds={dirtySnippetIds}
                    revealedProjectId={revealedProjectId}
                    revealedFolderId={revealedFolderId}
                    expandedProjectIds={expansion.projectIds}
                    expandedFolderIds={expansion.folderIds}
                    collapsedLibraryCategoryKeys={
                        expansion.collapsedLibraryCategoryKeys
                    }
                    pinnedKeys={pinnedKeys}
                    trash={trash}
                    onProjectExpandedChange={setProjectExpanded}
                    onFolderExpandedChange={setFolderExpanded}
                    onLibraryCategoryExpandedChange={setLibraryCategoryExpanded}
                    onOpen={onOpenSnippet}
                    onNewProject={onNewProject}
                    onRenameLibraryCategory={onRenameLibraryCategory}
                    onDeleteLibraryCategory={onDeleteLibraryCategory}
                    onNewStandaloneSnippet={onCreateSnippet}
                    onNewFolder={onNewFolder}
                    onNewSnippet={onNewSnippet}
                    onRename={onRename}
                    onInlineRename={onInlineRename}
                    onDelete={onDelete}
                    onRestore={onRestore}
                    onPermanentlyDelete={onPermanentlyDelete}
                    onToggleFavourite={onToggleFavourite}
                    onTogglePin={onTogglePin}
                    onMove={onMove}
                    onReorderProjects={onReorderProjects}
                />
            )}

            <div className="mt-auto flex h-9 shrink-0 items-center gap-2 bg-code-canvas/25 px-2 text-[9px] text-code-faint">
                <LibraryCategoryManagementMenu
                    libraryCategories={libraryCategories}
                    onCreate={onNewLibraryCategory}
                    onRename={onRenameLibraryCategory}
                    onDelete={onDeleteLibraryCategory}
                />
                <button
                    type="button"
                    onClick={onCreateSnippet}
                    className="ml-auto flex h-6 items-center gap-1 rounded bg-code-accent px-2 font-semibold text-code-canvas transition hover:bg-white"
                >
                    <FilePlus2 className="size-3" /> New snippet
                </button>
                <button
                    type="button"
                    aria-label="New project, bundle, or guide collection"
                    title="New project, bundle, or guide collection"
                    onClick={() => onNewProject()}
                    className="flex size-6 items-center justify-center rounded bg-code-raised text-code-muted transition hover:bg-code-hover hover:text-code-text"
                >
                    <PackagePlus className="size-3" />
                </button>
            </div>
        </aside>
    );
}

function LibraryCategoryManagementMenu({
    libraryCategories,
    onCreate,
    onRename,
    onDelete,
}: {
    libraryCategories: LibraryCategory[];
    onCreate: () => void;
    onRename: (category: LibraryCategory) => void;
    onDelete: (category: LibraryCategory) => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label="Manage library categories"
                    title="Manage library categories"
                    className="flex h-6 items-center gap-1 rounded bg-code-raised px-2 font-semibold text-code-muted transition hover:bg-code-hover hover:text-code-text"
                >
                    <LayoutGrid className="size-3" /> Categories
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent side="top" align="start" className="w-56">
                <DropdownMenuLabel className="text-[10px] tracking-[0.08em] text-code-muted uppercase">
                    Library categories
                </DropdownMenuLabel>
                {libraryCategories.length > 0 ? (
                    libraryCategories.map((category) => (
                        <DropdownMenuSub key={category.id}>
                            <DropdownMenuSubTrigger className="gap-2 text-xs">
                                <LayoutGrid className="size-3.5 shrink-0 text-code-muted" />
                                <span className="min-w-0 flex-1 truncate">
                                    {category.name}
                                </span>
                            </DropdownMenuSubTrigger>
                            <DropdownMenuPortal>
                                <DropdownMenuSubContent className="w-40">
                                    <DropdownMenuItem
                                        onSelect={() => onRename(category)}
                                    >
                                        <Pencil /> Rename category
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onSelect={() => onDelete(category)}
                                    >
                                        <Trash2 /> Delete category
                                    </DropdownMenuItem>
                                </DropdownMenuSubContent>
                            </DropdownMenuPortal>
                        </DropdownMenuSub>
                    ))
                ) : (
                    <DropdownMenuItem disabled>
                        No categories yet
                    </DropdownMenuItem>
                )}
                <DropdownMenuSeparator />
                <DropdownMenuItem onSelect={onCreate}>
                    <Plus /> New category
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function SearchFilterBar({
    entity,
    scope,
    frameworkId,
    excerptMode,
    frameworks,
    onEntityChange,
    onScopeChange,
    onFrameworkChange,
    onExcerptModeChange,
}: {
    entity: WorkspaceSearchEntity;
    scope: SnippetSearchScope;
    frameworkId: number | null;
    excerptMode: SnippetExcerptMode;
    frameworks: Framework[];
    onEntityChange: (entity: WorkspaceSearchEntity) => void;
    onScopeChange: (scope: SnippetSearchScope) => void;
    onFrameworkChange: (frameworkId: number | null) => void;
    onExcerptModeChange: (mode: SnippetExcerptMode) => void;
}) {
    const hasActiveFilters =
        entity !== 'all' || scope !== 'all' || frameworkId !== null;

    return (
        <div className="mt-2 space-y-2 rounded-md bg-code-canvas/45 p-2">
            <div className="flex h-4 items-center">
                <span className="text-[8px] font-semibold tracking-[0.14em] text-code-faint uppercase">
                    Search filters
                </span>
                {hasActiveFilters && (
                    <button
                        type="button"
                        onClick={() => {
                            onEntityChange('all');
                            onScopeChange('all');
                            onFrameworkChange(null);
                        }}
                        className="ml-auto rounded px-1 text-[8px] text-code-faint transition hover:bg-code-hover hover:text-code-text focus-visible:outline-1 focus-visible:outline-code-accent"
                    >
                        Reset
                    </button>
                )}
            </div>
            <fieldset>
                <legend className="mb-1 text-[8px] font-semibold tracking-[0.14em] text-code-faint uppercase">
                    Find
                </legend>
                <div className="grid grid-cols-4 gap-0.5 rounded bg-code-panel/70 p-0.5">
                    {searchEntityOptions.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            aria-pressed={entity === option.value}
                            onClick={() => onEntityChange(option.value)}
                            className={cn(
                                'h-6 rounded px-1 text-[9px] font-medium transition focus-visible:outline-1 focus-visible:outline-code-accent',
                                entity === option.value
                                    ? 'bg-code-raised text-code-text shadow-sm'
                                    : 'text-code-faint hover:bg-code-hover hover:text-code-text',
                            )}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            </fieldset>

            <div className="grid grid-cols-2 gap-1.5">
                <SearchFilterSelect
                    label="Match in"
                    value={scope}
                    onChange={(value) =>
                        onScopeChange(value as SnippetSearchScope)
                    }
                >
                    {searchScopeOptions.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </SearchFilterSelect>

                <SearchFilterSelect
                    label="Framework"
                    value={frameworkId === null ? '' : String(frameworkId)}
                    disabled={frameworks.length === 0}
                    onChange={(value) =>
                        onFrameworkChange(value === '' ? null : Number(value))
                    }
                >
                    <option value="">Any framework</option>
                    {frameworks.map((framework) => (
                        <option key={framework.id} value={framework.id}>
                            {framework.name}
                        </option>
                    ))}
                </SearchFilterSelect>
            </div>

            <fieldset className="flex items-center gap-1.5">
                <legend className="float-left mr-auto text-[8px] font-semibold tracking-[0.12em] text-code-faint uppercase">
                    Code preview
                </legend>
                <div className="flex rounded bg-code-panel/70 p-0.5">
                    {excerptModeOptions.map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            aria-pressed={excerptMode === option.value}
                            onClick={() => onExcerptModeChange(option.value)}
                            className={cn(
                                'h-5 rounded px-2 text-[8px] transition focus-visible:outline-1 focus-visible:outline-code-accent',
                                excerptMode === option.value
                                    ? 'bg-code-raised text-code-text'
                                    : 'text-code-faint hover:text-code-text',
                            )}
                        >
                            {option.label}
                        </button>
                    ))}
                </div>
            </fieldset>
        </div>
    );
}

function SearchFilterSelect({
    label,
    value,
    disabled = false,
    onChange,
    children,
}: {
    label: string;
    value: string;
    disabled?: boolean;
    onChange: (value: string) => void;
    children: ReactNode;
}) {
    return (
        <label className="min-w-0">
            <span className="mb-1 block text-[8px] font-semibold tracking-[0.12em] text-code-faint uppercase">
                {label}
            </span>
            <span className="relative block">
                <select
                    value={value}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.value)}
                    className="h-7 w-full appearance-none rounded bg-code-panel px-2 pr-6 text-[9px] text-code-muted transition outline-none hover:bg-code-hover hover:text-code-text focus-visible:ring-1 focus-visible:ring-code-accent/60 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {children}
                </select>
                <ChevronDownIcon />
            </span>
        </label>
    );
}

function ChevronDownIcon() {
    return (
        <svg
            aria-hidden="true"
            viewBox="0 0 12 12"
            className="pointer-events-none absolute top-1/2 right-2 size-3 -translate-y-1/2 text-code-faint"
        >
            <path
                d="m3 4.5 3 3 3-3"
                fill="none"
                stroke="currentColor"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}

function TagPanel({
    tags,
    query,
    pinnedKeys,
    onQueryChange,
    onTogglePin,
}: {
    tags: Tag[];
    query: string;
    pinnedKeys: ReadonlySet<string>;
    onQueryChange: (query: string) => void;
    onTogglePin?: (target: LibraryPinTarget) => void;
}) {
    return (
        <section className="min-h-0 flex-1 overflow-y-auto px-2 py-3">
            <p className="px-2 pb-2 text-[9px] leading-4 text-code-faint">
                Select a tag to add an exact filter to the current query. Pin
                frequently used tags to the Library.
            </p>
            <div className="flex flex-col gap-1 px-1">
                {tags.map((tag) => {
                    const token = `tag==${tag.slug}`;
                    const isActive = query.includes(token);
                    const pinTarget: LibraryPinTarget = {
                        type: 'tag',
                        id: tag.id,
                    };
                    const isPinned = pinnedKeys.has(libraryPinKey(pinTarget));

                    return (
                        <div
                            key={tag.id}
                            className="group flex items-center gap-1"
                        >
                            <button
                                type="button"
                                onClick={() =>
                                    onQueryChange(
                                        isActive
                                            ? query
                                                  .replace(token, '')
                                                  .replace(/\s{2,}/gu, ' ')
                                                  .trim()
                                            : `${query.trim()} ${token}`.trim(),
                                    )
                                }
                                className={cn(
                                    'min-w-0 flex-1 rounded-md px-2 py-1 text-left text-[10px] transition',
                                    isActive
                                        ? 'bg-code-raised text-code-text'
                                        : 'bg-code-canvas/40 text-code-muted hover:bg-code-hover hover:text-code-text',
                                )}
                                style={
                                    !isActive && tag.color
                                        ? {
                                              color: tag.color,
                                          }
                                        : undefined
                                }
                            >
                                {tag.name}
                            </button>
                            {onTogglePin && (
                                <button
                                    type="button"
                                    aria-label={`${isPinned ? 'Unpin' : 'Pin'} ${tag.name}`}
                                    aria-pressed={isPinned}
                                    onClick={() => onTogglePin(pinTarget)}
                                    className={cn(
                                        'flex size-6 items-center justify-center rounded transition hover:bg-code-hover hover:text-sky-200',
                                        isPinned
                                            ? 'text-sky-300'
                                            : 'text-code-faint opacity-0 group-focus-within:opacity-100 group-hover:opacity-100',
                                    )}
                                >
                                    <Pin
                                        className={cn(
                                            'size-3',
                                            isPinned && 'fill-sky-300/30',
                                        )}
                                    />
                                </button>
                            )}
                        </div>
                    );
                })}
            </div>
            {tags.length === 0 && (
                <div className="mx-2 rounded-lg bg-code-canvas/35 py-5 text-center text-[10px] text-code-faint">
                    Tags appear here after you add them to a snippet.
                </div>
            )}
        </section>
    );
}

function inferVisibleSnippets(
    allSnippets: Snippet[],
    projects: SnippetProject[],
    results: SnippetSearchResult[],
    query: string,
): Snippet[] {
    if (query.trim().length === 0) {
        return allSnippets;
    }

    const visibleIds = new Set<number>();

    results.forEach((result) => {
        if (result.kind === 'snippet' || result.kind === 'section') {
            visibleIds.add(result.snippet.id);

            return;
        }

        if (result.kind === 'project') {
            result.project.snippets.forEach((snippet) =>
                visibleIds.add(snippet.id),
            );

            return;
        }

        const project = projects.find(
            (candidate) => candidate.id === result.project.id,
        );

        if (!project) {
            return;
        }

        const descendantFolderIds = collectDescendantFolderIds(
            project,
            result.folder.id,
        );
        project.snippets
            .filter(
                (snippet) =>
                    snippet.folder_id !== null &&
                    descendantFolderIds.has(snippet.folder_id),
            )
            .forEach((snippet) => visibleIds.add(snippet.id));
    });

    return allSnippets.filter((snippet) => visibleIds.has(snippet.id));
}

function collectDescendantFolderIds(
    project: SnippetProject,
    folderId: number,
): Set<number> {
    const folderIds = new Set([folderId]);
    let discoveredFolder = true;

    while (discoveredFolder) {
        discoveredFolder = false;

        project.folders.forEach((folder) => {
            if (
                folder.parent_id !== null &&
                folderIds.has(folder.parent_id) &&
                !folderIds.has(folder.id)
            ) {
                folderIds.add(folder.id);
                discoveredFolder = true;
            }
        });
    }

    return folderIds;
}

function readExpansionState(storageKey: string): ExpansionState {
    if (typeof window === 'undefined') {
        return {
            storageKey,
            collapsedLibraryCategoryKeys: new Set(),
            projectIds: new Set(),
            folderIds: new Set(),
        };
    }

    try {
        const value = JSON.parse(
            window.localStorage.getItem(storageKey) ?? '{}',
        ) as {
            collapsedCategories?: unknown;
            projects?: unknown;
            folders?: unknown;
        };

        return {
            storageKey,
            collapsedLibraryCategoryKeys: stringSet(value.collapsedCategories),
            projectIds: numberSet(value.projects),
            folderIds: numberSet(value.folders),
        };
    } catch {
        return {
            storageKey,
            collapsedLibraryCategoryKeys: new Set(),
            projectIds: new Set(),
            folderIds: new Set(),
        };
    }
}

function stringSet(value: unknown): Set<string> {
    return new Set(
        Array.isArray(value)
            ? value.filter((item): item is string => typeof item === 'string')
            : [],
    );
}

function numberSet(value: unknown): Set<number> {
    return new Set(
        Array.isArray(value)
            ? value.filter((item): item is number => Number.isInteger(item))
            : [],
    );
}

function setSetValue<T>(
    current: ReadonlySet<T>,
    value: T,
    enabled: boolean,
): Set<T> {
    const next = new Set(current);

    if (enabled) {
        next.add(value);
    } else {
        next.delete(value);
    }

    return next;
}

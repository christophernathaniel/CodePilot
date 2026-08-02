import {
    ChevronsUp,
    FileCode2,
    FilePlus2,
    FolderTree,
    PackagePlus,
    Pin,
    Search,
    Tags,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { RefObject } from 'react';
import {
    ProjectExplorer,
    libraryPinKey,
} from '@/components/snippets/project-explorer';
import type {
    ExplorerDragItem,
    ExplorerDropTarget,
    ExplorerEntity,
    LibraryBrowseMode,
    LibraryPinTarget,
} from '@/components/snippets/project-explorer';
import { SnippetSearch } from '@/components/snippets/snippet-search';
import type { SnippetSearchResult } from '@/components/snippets/snippet-search';
import type { SnippetSectionSearchResult } from '@/components/snippets/snippet-search';
import type { WorkspacePanel } from '@/components/snippets/workspace-activity-bar';
import { cn } from '@/lib/utils';
import type {
    Framework,
    LanguageOption,
    Snippet,
    SnippetFolder,
    SnippetProject,
    Tag,
} from '@/types';

const browseModes: Array<{ value: LibraryBrowseMode; label: string }> = [
    { value: 'projects', label: 'Projects' },
    { value: 'flat', label: 'All snippets' },
    { value: 'guides', label: 'Guides' },
    { value: 'favourites', label: 'Favourites' },
    { value: 'language', label: 'Languages' },
    { value: 'tag', label: 'Tags' },
    { value: 'framework', label: 'Frameworks' },
    { value: 'pinned', label: 'Pinned' },
];

type ExpansionState = {
    storageKey: string;
    projectIds: Set<number>;
    folderIds: Set<number>;
};

export type WorkspaceSidePanelProps = {
    panel: WorkspacePanel;
    projects: SnippetProject[];
    standaloneSnippets?: Snippet[];
    visibleSnippets?: Snippet[];
    matchedProjectIds?: ReadonlySet<number>;
    matchedFolderIds?: ReadonlySet<number>;
    languageOptions?: LanguageOption[];
    frameworks?: Framework[];
    tags: Tag[];
    query: string;
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
    onBrowseModeChange?: (mode: LibraryBrowseMode) => void;
    onQueryChange: (query: string) => void;
    onSuggestionAccept: (suggestion: string) => void;
    onSearchOpen: (result: SnippetSearchResult) => void;
    onCopySection?: (result: SnippetSectionSearchResult) => void;
    onOpenSnippet: (snippet: Snippet) => void;
    onNewProject: () => void;
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
    onDelete: (entity: ExplorerEntity) => void;
    onToggleFavourite?: (snippet: Snippet) => void;
    onTogglePin?: (target: LibraryPinTarget) => void;
    onMove?: (item: ExplorerDragItem, target: ExplorerDropTarget) => void;
};

export function WorkspaceSidePanel({
    panel,
    projects,
    standaloneSnippets = [],
    visibleSnippets,
    matchedProjectIds = new Set<number>(),
    matchedFolderIds = new Set<number>(),
    languageOptions = [],
    frameworks = [],
    tags,
    query,
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
    onBrowseModeChange,
    onQueryChange,
    onSuggestionAccept,
    onSearchOpen,
    onCopySection,
    onOpenSnippet,
    onNewProject,
    onCreateSnippet,
    onNewFolder,
    onNewSnippet,
    onRename,
    onDelete,
    onToggleFavourite,
    onTogglePin,
    onMove,
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
    const hasQuery = query.trim().length > 0;
    const title =
        panel === 'explorer'
            ? 'Library'
            : panel === 'search'
              ? 'Search'
              : 'Tags';
    const TitleIcon =
        panel === 'explorer' ? FolderTree : panel === 'search' ? Search : Tags;
    const displayedBrowseMode =
        panel === 'search' ? 'flat' : resolvedBrowseMode;

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

    const setFolderExpanded = (folderId: number, expanded: boolean) => {
        setExpansion((current) => ({
            ...current,
            folderIds: setSetValue(current.folderIds, folderId, expanded),
        }));
    };

    const collapseAll = () => {
        setExpansion((current) => ({
            ...current,
            projectIds: new Set(),
            folderIds: new Set(),
        }));
    };

    return (
        <aside className="flex w-[19rem] shrink-0 flex-col border-r border-code-border bg-code-panel 2xl:w-80">
            <header className="flex min-h-12 shrink-0 items-center gap-2 border-b border-code-border px-3 py-2">
                <TitleIcon className="size-3.5 text-code-muted" />
                <h1 className="text-xs font-medium text-code-text">{title}</h1>
                <span className="ml-auto font-mono text-[9px] text-code-faint">
                    {resolvedVisibleSnippets.length}{' '}
                    {hasQuery ? 'matching' : 'snippets'}
                </span>
            </header>

            <div className="relative z-20 shrink-0 border-b border-code-border p-2.5">
                <SnippetSearch
                    query={query}
                    suggestions={suggestions}
                    results={results}
                    inputRef={inputRef}
                    onQueryChange={onQueryChange}
                    onSuggestionAccept={onSuggestionAccept}
                    onOpen={onSearchOpen}
                    onCopySection={onCopySection}
                />
                <p className="mt-1.5 px-0.5 font-mono text-[8px] text-code-faint">
                    section==theme_setup · project==name · !legacy
                </p>
            </div>

            {panel !== 'tags' && (
                <div className="flex h-9 shrink-0 items-center gap-1 border-b border-code-border px-2">
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
                                className="h-6 w-full appearance-none rounded border border-code-border bg-code-canvas px-2 pr-6 text-[10px] font-medium text-code-muted transition outline-none hover:border-code-muted hover:text-code-text focus:border-code-accent/70"
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
                            Matching snippets
                        </span>
                    )}
                    {displayedBrowseMode === 'projects' && (
                        <button
                            type="button"
                            onClick={collapseAll}
                            disabled={
                                expansion.projectIds.size === 0 &&
                                expansion.folderIds.size === 0
                            }
                            className="flex h-6 items-center gap-1 rounded px-1.5 text-[9px] text-code-faint transition hover:bg-code-hover hover:text-code-text disabled:cursor-not-allowed disabled:opacity-35"
                        >
                            <ChevronsUp className="size-3" /> Collapse all
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
                    projects={projects}
                    standaloneSnippets={standaloneSnippets}
                    visibleSnippets={resolvedVisibleSnippets}
                    matchedProjectIds={matchedProjectIds}
                    matchedFolderIds={matchedFolderIds}
                    languageOptions={languageOptions}
                    frameworks={frameworks}
                    tags={tags}
                    browseMode={displayedBrowseMode}
                    filtering={hasQuery}
                    activeSnippetId={activeSnippetId}
                    dirtySnippetIds={dirtySnippetIds}
                    revealedProjectId={revealedProjectId}
                    revealedFolderId={revealedFolderId}
                    expandedProjectIds={expansion.projectIds}
                    expandedFolderIds={expansion.folderIds}
                    pinnedKeys={pinnedKeys}
                    onProjectExpandedChange={setProjectExpanded}
                    onFolderExpandedChange={setFolderExpanded}
                    onOpen={onOpenSnippet}
                    onNewProject={onNewProject}
                    onNewStandaloneSnippet={onCreateSnippet}
                    onNewFolder={onNewFolder}
                    onNewSnippet={onNewSnippet}
                    onRename={onRename}
                    onDelete={onDelete}
                    onToggleFavourite={onToggleFavourite}
                    onTogglePin={onTogglePin}
                    onMove={onMove}
                />
            )}

            <div className="mt-auto flex h-9 shrink-0 items-center gap-2 border-t border-code-border px-2 text-[9px] text-code-faint">
                <FileCode2 className="size-3" />
                <span>Account library</span>
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
                    onClick={onNewProject}
                    className="flex size-6 items-center justify-center rounded border border-code-border text-code-muted transition hover:bg-code-hover hover:text-code-text"
                >
                    <PackagePlus className="size-3" />
                </button>
            </div>
        </aside>
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
                                    'min-w-0 flex-1 rounded-md border px-2 py-1 text-left text-[10px] transition',
                                    isActive
                                        ? 'border-code-muted bg-code-raised text-code-text'
                                        : 'border-code-border bg-code-canvas/40 text-code-muted hover:border-code-muted hover:text-code-text',
                                )}
                                style={
                                    !isActive && tag.color
                                        ? {
                                              borderColor: `${tag.color}26`,
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
                <div className="mx-2 rounded-lg border border-dashed border-code-border py-5 text-center text-[10px] text-code-faint">
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
            projectIds: new Set(),
            folderIds: new Set(),
        };
    }

    try {
        const value = JSON.parse(
            window.localStorage.getItem(storageKey) ?? '{}',
        ) as { projects?: unknown; folders?: unknown };

        return {
            storageKey,
            projectIds: numberSet(value.projects),
            folderIds: numberSet(value.folders),
        };
    } catch {
        return {
            storageKey,
            projectIds: new Set(),
            folderIds: new Set(),
        };
    }
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

import {
    ArrowRight,
    BookOpenText,
    ChevronDown,
    ChevronRight,
    CornerDownRight,
    FilePenLine,
    FilePlus2,
    Folder,
    FolderOpen,
    FolderPlus,
    GripVertical,
    Inbox,
    LayoutGrid,
    MoreHorizontal,
    Package,
    Pencil,
    Pin,
    Plus,
    RotateCcw,
    Star,
    Trash2,
} from 'lucide-react';
import {
    createContext,
    useContext,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
} from 'react';
import type {
    DragEvent as ReactDragEvent,
    KeyboardEvent as ReactKeyboardEvent,
    MouseEvent as ReactMouseEvent,
} from 'react';
import { createPortal } from 'react-dom';
import { SnippetFileIcon } from '@/components/snippets/snippet-file-icon';
import { SnippetUsageIndicator } from '@/components/snippets/snippet-usage-indicator';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    groupProjectsByLibraryCategory,
    mergeLibraryCategoryProjectOrder,
} from '@/lib/snippets/library-category-groups';
import type { LibraryCategoryProjectGroup } from '@/lib/snippets/library-category-groups';
import { sortPinnedFirst } from '@/lib/snippets/pinned-order';
import type {
    SnippetCodeExcerpt,
    SnippetExcerptMode,
} from '@/lib/snippets/search-query';
import { reorderWorkspaceIds } from '@/lib/snippets/workspace-order';
import type { WorkspaceDropPlacement } from '@/lib/snippets/workspace-order';
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

const explorerDragMime = 'application/x-codepilot-library-item';
const workspaceReorderMime = 'application/x-codepilot-workspace-order';

export type LibraryBrowseMode =
    | 'projects'
    | 'flat'
    | 'guides'
    | 'favourites'
    | 'language'
    | 'tag'
    | 'framework'
    | 'pinned'
    | 'trash';

export type ExplorerEntity =
    | { type: 'project'; project: SnippetProject }
    | { type: 'folder'; project: SnippetProject; folder: SnippetFolder }
    | {
          type: 'snippet';
          project: SnippetProject | null;
          snippet: Snippet;
      };

export type LibraryPinTarget =
    | { type: 'snippet'; id: number }
    | { type: 'project'; id: number }
    | { type: 'tag'; id: number }
    | { type: 'language'; key: string }
    | { type: 'framework'; id: number | string };

export type ExplorerDragItem =
    | {
          type: 'snippet';
          id: number;
          projectId: number | null;
          folderId: number | null;
      }
    | {
          type: 'folder';
          id: number;
          projectId: number;
          parentId: number | null;
      };

export type ExplorerDropTarget =
    | { type: 'standalone' }
    | { type: 'project'; projectId: number }
    | { type: 'folder'; projectId: number; folderId: number };

export type ProjectExplorerProps = {
    libraryCategories?: LibraryCategory[];
    projects: SnippetProject[];
    standaloneSnippets?: Snippet[];
    visibleSnippets?: Snippet[];
    matchedProjectIds?: ReadonlySet<number>;
    matchedFolderIds?: ReadonlySet<number>;
    languageOptions?: LanguageOption[];
    frameworks?: Framework[];
    tags?: Tag[];
    browseMode?: LibraryBrowseMode;
    filtering?: boolean;
    includeMatchedProjectContents?: boolean;
    searchCodeMatches?: ReadonlyMap<number, SnippetCodeExcerpt>;
    searchExcerptMode?: SnippetExcerptMode;
    activeSnippetId: number | null;
    dirtySnippetIds: Set<number>;
    revealedProjectId: number | null;
    revealedFolderId: number | null;
    expandedProjectIds?: ReadonlySet<number>;
    expandedFolderIds?: ReadonlySet<number>;
    collapsedLibraryCategoryKeys?: ReadonlySet<string>;
    pinnedKeys?: ReadonlySet<string>;
    trash?: LibraryTrash;
    onProjectExpandedChange?: (projectId: number, expanded: boolean) => void;
    onFolderExpandedChange?: (folderId: number, expanded: boolean) => void;
    onLibraryCategoryExpandedChange?: (
        categoryKey: string,
        expanded: boolean,
    ) => void;
    onOpen: (snippet: Snippet) => void;
    onNewProject: (category?: LibraryCategory | null) => void;
    onRenameLibraryCategory?: (category: LibraryCategory) => void;
    onDeleteLibraryCategory?: (category: LibraryCategory) => void;
    onNewStandaloneSnippet?: () => void;
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

export type InlineRenameCallbacks = {
    onSuccess: () => void;
    onError: (message: string) => void;
    onFinish: () => void;
};

type InlineRenameState = {
    entity: ExplorerEntity;
    value: string;
    processing: boolean;
    error: string | null;
};

type ExplorerContextMenuState = {
    entity: ExplorerEntity;
    x: number;
    y: number;
};

type WorkspaceReorderDrop = {
    projectId: number;
    placement: WorkspaceDropPlacement;
};

type ExplorerInteractionContextValue = {
    renameState: InlineRenameState | null;
    beginRename: (entity: ExplorerEntity) => void;
    updateRename: (value: string) => void;
    commitRename: () => void;
    cancelRename: () => void;
    openContextMenu: (
        event: ReactMouseEvent<HTMLElement>,
        entity: ExplorerEntity,
    ) => void;
    searchCodeMatches: ReadonlyMap<number, SnippetCodeExcerpt>;
    searchExcerptMode: SnippetExcerptMode;
};

const ExplorerInteractionContext =
    createContext<ExplorerInteractionContextValue | null>(null);

const emptyTrash: LibraryTrash = {
    projects: [],
    folders: [],
    snippets: [],
};

type BrowseGroup = {
    key: string;
    label: string;
    snippets: Snippet[];
    pinTarget?: LibraryPinTarget;
};

type FrameworkProjectGroup = {
    key: string;
    label: string;
    projects: SnippetProject[];
    pinTarget?: LibraryPinTarget;
};

export function ProjectExplorer({
    libraryCategories = [],
    projects,
    standaloneSnippets = [],
    visibleSnippets,
    matchedProjectIds = new Set<number>(),
    matchedFolderIds = new Set<number>(),
    languageOptions = [],
    frameworks = [],
    tags = [],
    browseMode = 'projects',
    filtering = false,
    includeMatchedProjectContents = true,
    searchCodeMatches = new Map<number, SnippetCodeExcerpt>(),
    searchExcerptMode = 'off',
    activeSnippetId,
    dirtySnippetIds,
    revealedProjectId,
    revealedFolderId,
    expandedProjectIds,
    expandedFolderIds,
    collapsedLibraryCategoryKeys = new Set<string>(),
    pinnedKeys = new Set<string>(),
    trash = emptyTrash,
    onProjectExpandedChange,
    onFolderExpandedChange,
    onLibraryCategoryExpandedChange,
    onOpen,
    onNewProject,
    onRenameLibraryCategory,
    onDeleteLibraryCategory,
    onNewStandaloneSnippet,
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
}: ProjectExplorerProps) {
    const [localExpandedProjectIds, setLocalExpandedProjectIds] = useState<
        Set<number>
    >(() => new Set());
    const [localExpandedFolderIds, setLocalExpandedFolderIds] = useState<
        Set<number>
    >(() => new Set());
    const [dragItem, setDragItem] = useState<ExplorerDragItem | null>(null);
    const [activeDropKey, setActiveDropKey] = useState<string | null>(null);
    const [renameState, setRenameState] = useState<InlineRenameState | null>(
        null,
    );
    const [contextMenu, setContextMenu] =
        useState<ExplorerContextMenuState | null>(null);
    const contextMenuRef = useRef<HTMLDivElement>(null);
    const beginRename = (entity: ExplorerEntity) => {
        setContextMenu(null);
        setRenameState({
            entity,
            value: explorerEntityName(entity),
            processing: false,
            error: null,
        });
    };
    const cancelRename = () => setRenameState(null);
    const updateRename = (value: string) =>
        setRenameState((current) =>
            current ? { ...current, value, error: null } : current,
        );
    const commitRename = () => {
        if (!renameState || renameState.processing) {
            return;
        }

        const name = renameState.value.trim();

        if (name === explorerEntityName(renameState.entity)) {
            cancelRename();

            return;
        }

        if (name === '') {
            setRenameState((current) =>
                current ? { ...current, error: 'A name is required.' } : null,
            );

            return;
        }

        const entityKey = explorerEntityKey(renameState.entity);
        setRenameState((current) =>
            current ? { ...current, processing: true, error: null } : null,
        );
        onInlineRename(renameState.entity, name, {
            onSuccess: cancelRename,
            onError: (message) =>
                setRenameState((current) =>
                    current && explorerEntityKey(current.entity) === entityKey
                        ? { ...current, processing: false, error: message }
                        : current,
                ),
            onFinish: () =>
                setRenameState((current) =>
                    current && explorerEntityKey(current.entity) === entityKey
                        ? { ...current, processing: false }
                        : current,
                ),
        });
    };
    const openContextMenu = (
        event: ReactMouseEvent<HTMLElement>,
        entity: ExplorerEntity,
    ) => {
        event.preventDefault();
        setContextMenu({ entity, x: event.clientX, y: event.clientY });
    };

    useEffect(() => {
        if (!contextMenu) {
            return;
        }

        const focusFrame = window.requestAnimationFrame(() => {
            contextMenuRef.current
                ?.querySelector<HTMLButtonElement>('[role="menuitem"]')
                ?.focus();
        });
        const closeOnPointerDown = (event: PointerEvent) => {
            if (
                event.target instanceof Node &&
                !contextMenuRef.current?.contains(event.target)
            ) {
                setContextMenu(null);
            }
        };
        const closeOnEscape = (event: globalThis.KeyboardEvent) => {
            if (event.key === 'Escape') {
                setContextMenu(null);
            }
        };

        window.addEventListener('pointerdown', closeOnPointerDown);
        window.addEventListener('keydown', closeOnEscape);

        return () => {
            window.cancelAnimationFrame(focusFrame);
            window.removeEventListener('pointerdown', closeOnPointerDown);
            window.removeEventListener('keydown', closeOnEscape);
        };
    }, [contextMenu]);

    const interactionContext: ExplorerInteractionContextValue = {
        renameState,
        beginRename,
        updateRename,
        commitRename,
        cancelRename,
        openContextMenu,
        searchCodeMatches,
        searchExcerptMode,
    };
    const expandedProjects = expandedProjectIds ?? localExpandedProjectIds;
    const expandedFolders = expandedFolderIds ?? localExpandedFolderIds;
    const allSnippets = useMemo(
        () => [
            ...standaloneSnippets,
            ...projects.flatMap((project) => project.snippets),
        ],
        [projects, standaloneSnippets],
    );
    const visibleSnippetIds = useMemo(
        () =>
            visibleSnippets
                ? new Set(visibleSnippets.map((snippet) => snippet.id))
                : null,
        [visibleSnippets],
    );
    const browserSnippets = useMemo(
        () =>
            visibleSnippetIds
                ? allSnippets.filter((snippet) =>
                      visibleSnippetIds.has(snippet.id),
                  )
                : allSnippets,
        [allSnippets, visibleSnippetIds],
    );
    const browserStandaloneSnippets = useMemo(
        () =>
            visibleSnippetIds
                ? standaloneSnippets.filter((snippet) =>
                      visibleSnippetIds.has(snippet.id),
                  )
                : standaloneSnippets,
        [standaloneSnippets, visibleSnippetIds],
    );
    const browserProjects = useMemo(
        () =>
            filtering
                ? projects.filter(
                      (project) =>
                          matchedProjectIds.has(project.id) ||
                          project.folders.some((folder) =>
                              matchedFolderIds.has(folder.id),
                          ) ||
                          project.snippets.some((snippet) =>
                              visibleSnippetIds?.has(snippet.id),
                          ),
                  )
                : projects,
        [
            filtering,
            matchedFolderIds,
            matchedProjectIds,
            projects,
            visibleSnippetIds,
        ],
    );
    const libraryCategoryGroups = useMemo(
        () =>
            groupProjectsByLibraryCategory(
                libraryCategories,
                browserProjects,
                !filtering,
            ),
        [browserProjects, filtering, libraryCategories],
    );
    const snippetProjects = useMemo(() => {
        const entries = new Map<number, SnippetProject>();

        projects.forEach((project) => {
            project.snippets.forEach((snippet) =>
                entries.set(snippet.id, project),
            );
        });

        return entries;
    }, [projects]);
    const eligibleDropKeys = useMemo(() => {
        if (!dragItem || !onMove) {
            return new Set<string>();
        }

        return new Set(
            collectDropTargets(projects)
                .filter((target) => canDrop(dragItem, target, projects))
                .map(dropTargetKey),
        );
    }, [dragItem, onMove, projects]);
    const dragItemLabel = useMemo(
        () =>
            dragItem
                ? describeDragItem(dragItem, projects, standaloneSnippets)
                : null,
        [dragItem, projects, standaloneSnippets],
    );
    const activeDropLabel = useMemo(
        () =>
            activeDropKey ? describeDropTarget(activeDropKey, projects) : null,
        [activeDropKey, projects],
    );

    const setProjectExpanded = (projectId: number, expanded: boolean) => {
        if (onProjectExpandedChange) {
            onProjectExpandedChange(projectId, expanded);

            return;
        }

        setLocalExpandedProjectIds((current) =>
            setSetValue(current, projectId, expanded),
        );
    };

    const setFolderExpanded = (folderId: number, expanded: boolean) => {
        if (onFolderExpandedChange) {
            onFolderExpandedChange(folderId, expanded);

            return;
        }

        setLocalExpandedFolderIds((current) =>
            setSetValue(current, folderId, expanded),
        );
    };

    useEffect(() => {
        const revealedProject = revealedProjectId
            ? projects.find((project) => project.id === revealedProjectId)
            : revealedFolderId
              ? projects.find((project) =>
                    project.folders.some(
                        (folder) => folder.id === revealedFolderId,
                    ),
                )
              : null;

        if (!revealedProject) {
            return;
        }

        let scrollFrame = 0;
        const revealFrame = window.requestAnimationFrame(() => {
            setProjectExpanded(revealedProject.id, true);

            if (revealedFolderId) {
                const foldersById = new Map(
                    revealedProject.folders.map((folder) => [
                        folder.id,
                        folder,
                    ]),
                );
                const folderIds = new Set<number>();
                let folderId: number | null = revealedFolderId;

                while (folderId !== null && !folderIds.has(folderId)) {
                    folderIds.add(folderId);
                    folderId = foldersById.get(folderId)?.parent_id ?? null;
                }

                folderIds.forEach((id) => setFolderExpanded(id, true));
            }

            scrollFrame = window.requestAnimationFrame(() => {
                const selector = revealedFolderId
                    ? `[data-folder-id="${revealedFolderId}"]`
                    : `[data-project-id="${revealedProject.id}"]`;

                document
                    .querySelector(selector)
                    ?.scrollIntoView({ block: 'center', behavior: 'smooth' });
            });
        });

        return () => {
            window.cancelAnimationFrame(revealFrame);
            window.cancelAnimationFrame(scrollFrame);
        };
        // Expansion callbacks intentionally run only when the reveal target changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [projects, revealedFolderId, revealedProjectId]);

    const startDragging = (
        event: ReactDragEvent<HTMLElement>,
        item: ExplorerDragItem,
    ) => {
        event.stopPropagation();
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData(explorerDragMime, JSON.stringify(item));
        setActiveDropKey(null);
        setDragItem(item);
    };

    const finishDragging = () => {
        setDragItem(null);
        setActiveDropKey(null);
    };

    const resolveDragItem = (
        event: ReactDragEvent<HTMLElement>,
    ): ExplorerDragItem | null => {
        if (dragItem) {
            return dragItem;
        }

        return parseDragItem(event.dataTransfer.getData(explorerDragMime));
    };

    const dragOverTarget = (
        event: ReactDragEvent<HTMLElement>,
        target: ExplorerDropTarget,
    ) => {
        const item = resolveDragItem(event);

        if (!item || !onMove || !canDrop(item, target, projects)) {
            event.dataTransfer.dropEffect = 'none';
            setActiveDropKey(null);

            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.dataTransfer.dropEffect = 'move';
        setActiveDropKey(dropTargetKey(target));
    };

    const dropOnTarget = (
        event: ReactDragEvent<HTMLElement>,
        target: ExplorerDropTarget,
    ) => {
        const item = resolveDragItem(event);

        if (!item || !onMove || !canDrop(item, target, projects)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        onMove(item, target);
        finishDragging();
    };

    const treeProps: TreeContentProps = {
        projects,
        standaloneSnippets: browserStandaloneSnippets,
        visibleSnippetIds,
        matchedProjectIds,
        matchedFolderIds,
        filtering,
        includeMatchedProjectContents,
        activeSnippetId,
        dirtySnippetIds,
        revealedProjectId,
        expandedProjects,
        expandedFolders,
        pinnedKeys,
        dragItem,
        eligibleDropKeys,
        activeDropKey,
        onProjectExpandedChange: setProjectExpanded,
        onFolderExpandedChange: setFolderExpanded,
        onOpen,
        onNewStandaloneSnippet,
        onNewFolder,
        onNewSnippet,
        onRename,
        onDelete,
        onToggleFavourite,
        onTogglePin,
        onReorderProjects:
            browseMode === 'projects' && !filtering
                ? onReorderProjects
                : undefined,
        onDragStart: startDragging,
        onDragEnd: finishDragging,
        onDragOverTarget: dragOverTarget,
        onDropTarget: dropOnTarget,
    };

    let content: React.ReactNode;

    if (browseMode === 'trash') {
        content = (
            <TrashPanel
                trash={trash}
                onRestore={onRestore}
                onPermanentlyDelete={onPermanentlyDelete}
            />
        );
    } else if (browseMode === 'projects') {
        content = (
            <>
                <ProjectTreeContent
                    key={filtering ? 'filtered-standalone' : 'all-standalone'}
                    {...treeProps}
                    projects={[]}
                />
                {libraryCategoryGroups.map((group) => (
                    <LibraryCategoryProjectSection
                        key={group.key}
                        group={group}
                        treeProps={treeProps}
                        allProjectIds={projects.map((project) => project.id)}
                        expanded={
                            filtering ||
                            !collapsedLibraryCategoryKeys.has(group.key)
                        }
                        onExpandedChange={(expanded) =>
                            onLibraryCategoryExpandedChange?.(
                                group.key,
                                expanded,
                            )
                        }
                        onNewProject={onNewProject}
                        onRenameCategory={onRenameLibraryCategory}
                        onDeleteCategory={onDeleteLibraryCategory}
                    />
                ))}
            </>
        );
    } else if (browseMode === 'flat') {
        content = (
            <BrowseGroupSection
                label="All snippets"
                snippets={browserSnippets}
                snippetProjects={snippetProjects}
                activeSnippetId={activeSnippetId}
                dirtySnippetIds={dirtySnippetIds}
                pinnedKeys={pinnedKeys}
                onOpen={onOpen}
                onRename={onRename}
                onDelete={onDelete}
                onToggleFavourite={onToggleFavourite}
                onTogglePin={onTogglePin}
                dragItem={dragItem}
                onDragStart={startDragging}
                onDragEnd={finishDragging}
            />
        );
    } else if (browseMode === 'guides') {
        const guideSnippets = browserSnippets.filter(
            (snippet) => snippet.content_type === 'guide',
        );

        content =
            guideSnippets.length > 0 ? (
                <BrowseGroupSection
                    label="Guides"
                    snippets={guideSnippets}
                    snippetProjects={snippetProjects}
                    activeSnippetId={activeSnippetId}
                    dirtySnippetIds={dirtySnippetIds}
                    pinnedKeys={pinnedKeys}
                    onOpen={onOpen}
                    onRename={onRename}
                    onDelete={onDelete}
                    onToggleFavourite={onToggleFavourite}
                    onTogglePin={onTogglePin}
                    dragItem={dragItem}
                    onDragStart={startDragging}
                    onDragEnd={finishDragging}
                />
            ) : (
                <EmptyBrowserMessage
                    icon={BookOpenText}
                    title={
                        filtering
                            ? 'No guides match this search'
                            : 'No guides yet'
                    }
                    detail={
                        filtering
                            ? 'Try a broader query or clear the current search.'
                            : 'Create a guide collection, then add its first guide file.'
                    }
                />
            );
    } else if (browseMode === 'favourites') {
        const favouriteSnippets = browserSnippets.filter(
            (snippet) => snippet.is_favourite,
        );

        content =
            favouriteSnippets.length > 0 ? (
                <BrowseGroupSection
                    label="Favourite files"
                    snippets={favouriteSnippets}
                    snippetProjects={snippetProjects}
                    activeSnippetId={activeSnippetId}
                    dirtySnippetIds={dirtySnippetIds}
                    pinnedKeys={pinnedKeys}
                    onOpen={onOpen}
                    onRename={onRename}
                    onDelete={onDelete}
                    onToggleFavourite={onToggleFavourite}
                    onTogglePin={onTogglePin}
                    dragItem={dragItem}
                    onDragStart={startDragging}
                    onDragEnd={finishDragging}
                />
            ) : (
                <EmptyBrowserMessage
                    icon={Star}
                    title={
                        filtering
                            ? 'No favourite files match this search'
                            : 'No favourite files yet'
                    }
                    detail={
                        filtering
                            ? 'Try a broader query or clear the current search.'
                            : 'Use the star beside a file or editor tab to keep it here.'
                    }
                />
            );
    } else if (browseMode === 'pinned') {
        const pinnedProjects = projects.filter((project) =>
            pinnedKeys.has(libraryPinKey({ type: 'project', id: project.id })),
        );
        const pinnedSnippets = browserSnippets.filter((snippet) =>
            pinnedKeys.has(libraryPinKey({ type: 'snippet', id: snippet.id })),
        );
        const pinnedGroups = sortBrowseGroups(
            (['language', 'tag', 'framework'] as const).flatMap((mode) =>
                buildBrowseGroups(mode, browserSnippets, {
                    frameworks,
                    includeEmptyCatalog: !filtering,
                    languageOptions,
                    tags,
                }).filter(
                    (group) =>
                        group.pinTarget &&
                        pinnedKeys.has(libraryPinKey(group.pinTarget)),
                ),
            ),
            pinnedKeys,
        );

        content = (
            <>
                {pinnedProjects.length > 0 && (
                    <ProjectTreeContent
                        {...treeProps}
                        projects={pinnedProjects}
                        standaloneSnippets={[]}
                        showStandalone={false}
                    />
                )}
                {pinnedSnippets.length > 0 && (
                    <BrowseGroupSection
                        label="Pinned snippets"
                        snippets={pinnedSnippets}
                        snippetProjects={snippetProjects}
                        activeSnippetId={activeSnippetId}
                        dirtySnippetIds={dirtySnippetIds}
                        pinnedKeys={pinnedKeys}
                        onOpen={onOpen}
                        onRename={onRename}
                        onDelete={onDelete}
                        onToggleFavourite={onToggleFavourite}
                        onTogglePin={onTogglePin}
                        dragItem={dragItem}
                        onDragStart={startDragging}
                        onDragEnd={finishDragging}
                    />
                )}
                {pinnedGroups.map((group) => (
                    <BrowseGroupSection
                        key={`pinned-${group.key}`}
                        label={group.label}
                        snippets={group.snippets}
                        pinTarget={group.pinTarget}
                        snippetProjects={snippetProjects}
                        activeSnippetId={activeSnippetId}
                        dirtySnippetIds={dirtySnippetIds}
                        pinnedKeys={pinnedKeys}
                        onOpen={onOpen}
                        onRename={onRename}
                        onDelete={onDelete}
                        onToggleFavourite={onToggleFavourite}
                        onTogglePin={onTogglePin}
                        dragItem={dragItem}
                        onDragStart={startDragging}
                        onDragEnd={finishDragging}
                    />
                ))}
                {pinnedProjects.length === 0 &&
                    pinnedSnippets.length === 0 &&
                    pinnedGroups.length === 0 && (
                        <EmptyBrowserMessage
                            icon={Pin}
                            title="Nothing pinned yet"
                            detail="Pin projects, snippets, languages, tags or frameworks for quick access."
                        />
                    )}
            </>
        );
    } else if (browseMode === 'framework') {
        content = sortFrameworkProjectGroups(
            buildFrameworkProjectGroups(browserProjects, frameworks, {
                includeEmptyCatalog: !filtering,
            }),
            pinnedKeys,
        ).map((group) => (
            <FrameworkProjectGroupSection
                key={group.key}
                group={group}
                treeProps={treeProps}
                pinnedKeys={pinnedKeys}
                onTogglePin={onTogglePin}
            />
        ));
    } else {
        content = sortBrowseGroups(
            buildBrowseGroups(browseMode, browserSnippets, {
                frameworks,
                includeEmptyCatalog: !filtering,
                languageOptions,
                tags,
            }),
            pinnedKeys,
        ).map((group) => (
            <BrowseGroupSection
                key={group.key}
                label={group.label}
                snippets={group.snippets}
                pinTarget={group.pinTarget}
                snippetProjects={snippetProjects}
                activeSnippetId={activeSnippetId}
                dirtySnippetIds={dirtySnippetIds}
                pinnedKeys={pinnedKeys}
                onOpen={onOpen}
                onRename={onRename}
                onDelete={onDelete}
                onToggleFavourite={onToggleFavourite}
                onTogglePin={onTogglePin}
                dragItem={dragItem}
                onDragStart={startDragging}
                onDragEnd={finishDragging}
            />
        ));
    }

    const hasModeContent =
        browseMode === 'trash'
            ? trash.projects.length +
                  trash.folders.length +
                  trash.snippets.length >
              0
            : browseMode === 'projects'
              ? filtering
                  ? browserSnippets.length > 0 ||
                    matchedProjectIds.size > 0 ||
                    matchedFolderIds.size > 0
                  : projects.length > 0 ||
                    standaloneSnippets.length > 0 ||
                    libraryCategories.length > 0
              : browseMode === 'framework'
                ? browserProjects.length > 0 ||
                  (!filtering && frameworks.length > 0)
                : browseMode === 'guides' ||
                  browseMode === 'pinned' ||
                  browseMode === 'favourites' ||
                  browserSnippets.length > 0 ||
                  (!filtering &&
                      browseMode === 'language' &&
                      languageOptions.length > 0) ||
                  (!filtering && browseMode === 'tag' && tags.length > 0);

    return (
        <ExplorerInteractionContext.Provider value={interactionContext}>
            <section
                className="relative min-h-0 flex-1 overflow-y-auto pb-8"
                onDragLeave={(event) => {
                    if (
                        !event.currentTarget.contains(
                            event.relatedTarget as Node,
                        )
                    ) {
                        setActiveDropKey(null);
                    }
                }}
            >
                {dragItem && dragItemLabel && (
                    <DragMoveStatus
                        sourceLabel={dragItemLabel}
                        targetLabel={activeDropLabel}
                    />
                )}
                {hasModeContent ? (
                    <div className="py-1 select-none">{content}</div>
                ) : (
                    <EmptyBrowserMessage
                        icon={
                            browseMode === 'trash'
                                ? Trash2
                                : filtering
                                  ? Inbox
                                  : Package
                        }
                        title={
                            browseMode === 'trash'
                                ? 'Trash is empty'
                                : filtering
                                  ? 'No matching library items'
                                  : 'No snippets yet'
                        }
                        detail={
                            browseMode === 'trash'
                                ? 'Deleted files, folders, and workspaces will appear here.'
                                : filtering
                                  ? 'Try a broader query or change the search filters.'
                                  : 'Create a standalone snippet or a project to get started.'
                        }
                        actionLabel={
                            !filtering && browseMode !== 'trash'
                                ? 'Create your first project'
                                : undefined
                        }
                        onAction={
                            !filtering && browseMode !== 'trash'
                                ? onNewProject
                                : undefined
                        }
                    />
                )}
            </section>

            {contextMenu
                ? createPortal(
                      <div
                          ref={contextMenuRef}
                          role="menu"
                          aria-label={`${explorerEntityName(contextMenu.entity)} actions`}
                          className="fixed z-100 min-w-44 rounded-md border border-code-border bg-code-raised p-1 text-[11px] text-code-text shadow-2xl"
                          style={explorerContextMenuPosition(contextMenu)}
                      >
                          {contextMenu.entity.type !== 'snippet' ? (
                              <>
                                  <button
                                      type="button"
                                      role="menuitem"
                                      onClick={() => {
                                          const entity = contextMenu.entity;

                                          if (entity.type === 'snippet') {
                                              return;
                                          }

                                          onNewSnippet(
                                              entity.project,
                                              entity.type === 'folder'
                                                  ? entity.folder
                                                  : null,
                                          );
                                          setContextMenu(null);
                                      }}
                                      className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left outline-none hover:bg-code-hover focus:bg-code-hover"
                                  >
                                      <FilePlus2 className="size-3.5 text-code-muted" />
                                      New file
                                  </button>
                                  <div className="my-1 h-px bg-code-border" />
                              </>
                          ) : null}
                          <button
                              type="button"
                              role="menuitem"
                              onClick={() => beginRename(contextMenu.entity)}
                              className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left outline-none hover:bg-code-hover focus:bg-code-hover"
                          >
                              <Pencil className="size-3.5 text-code-muted" />
                              Rename
                          </button>
                          <button
                              type="button"
                              role="menuitem"
                              onClick={() => {
                                  onRename(contextMenu.entity);
                                  setContextMenu(null);
                              }}
                              className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left outline-none hover:bg-code-hover focus:bg-code-hover"
                          >
                              <FilePenLine className="size-3.5 text-code-muted" />
                              {explorerEntityEditLabel(contextMenu.entity)}
                          </button>
                          <div className="my-1 h-px bg-code-border" />
                          <button
                              type="button"
                              role="menuitem"
                              onClick={() => {
                                  onDelete(contextMenu.entity);
                                  setContextMenu(null);
                              }}
                              className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-red-300 outline-none hover:bg-red-500/10 focus:bg-red-500/10"
                          >
                              <Trash2 className="size-3.5" />
                              Move to Trash
                          </button>
                      </div>,
                      document.body,
                  )
                : null}
        </ExplorerInteractionContext.Provider>
    );
}

type TreeContentProps = Pick<
    ProjectExplorerProps,
    | 'projects'
    | 'activeSnippetId'
    | 'dirtySnippetIds'
    | 'revealedProjectId'
    | 'onOpen'
    | 'onNewStandaloneSnippet'
    | 'onNewFolder'
    | 'onNewSnippet'
    | 'onRename'
    | 'onDelete'
    | 'onToggleFavourite'
    | 'onTogglePin'
    | 'onReorderProjects'
> & {
    standaloneSnippets: Snippet[];
    visibleSnippetIds: Set<number> | null;
    matchedProjectIds: ReadonlySet<number>;
    matchedFolderIds: ReadonlySet<number>;
    filtering: boolean;
    includeMatchedProjectContents: boolean;
    showStandalone?: boolean;
    expandedProjects: ReadonlySet<number>;
    expandedFolders: ReadonlySet<number>;
    pinnedKeys: ReadonlySet<string>;
    dragItem: ExplorerDragItem | null;
    eligibleDropKeys: ReadonlySet<string>;
    activeDropKey: string | null;
    onProjectExpandedChange: (projectId: number, expanded: boolean) => void;
    onFolderExpandedChange: (folderId: number, expanded: boolean) => void;
    onDragStart: (
        event: ReactDragEvent<HTMLElement>,
        item: ExplorerDragItem,
    ) => void;
    onDragEnd: () => void;
    onDragOverTarget: (
        event: ReactDragEvent<HTMLElement>,
        target: ExplorerDropTarget,
    ) => void;
    onDropTarget: (
        event: ReactDragEvent<HTMLElement>,
        target: ExplorerDropTarget,
    ) => void;
};

function LibraryCategoryProjectSection({
    group,
    treeProps,
    allProjectIds,
    expanded,
    onExpandedChange,
    onNewProject,
    onRenameCategory,
    onDeleteCategory,
}: {
    group: LibraryCategoryProjectGroup;
    treeProps: TreeContentProps;
    allProjectIds: number[];
    expanded: boolean;
    onExpandedChange: (expanded: boolean) => void;
    onNewProject: (category?: LibraryCategory | null) => void;
    onRenameCategory?: (category: LibraryCategory) => void;
    onDeleteCategory?: (category: LibraryCategory) => void;
}) {
    const projectIds = group.projects.map((project) => project.id);
    const projectCountLabel = `${group.projects.length} ${group.projects.length === 1 ? 'workspace' : 'workspaces'}`;
    const onReorderProjects = treeProps.onReorderProjects
        ? (reorderedProjectIds: number[]) =>
              treeProps.onReorderProjects?.(
                  mergeLibraryCategoryProjectOrder(
                      allProjectIds,
                      projectIds,
                      reorderedProjectIds,
                  ),
              )
        : undefined;

    return (
        <section aria-label={`${group.label} category`}>
            <div className="group/category flex h-8 items-center gap-1 bg-code-canvas/35 px-2">
                <button
                    type="button"
                    aria-expanded={expanded}
                    aria-label={`${expanded ? 'Collapse' : 'Expand'} ${group.label}`}
                    onClick={() => onExpandedChange(!expanded)}
                    className="flex min-w-0 flex-1 items-center gap-1.5 text-left"
                >
                    {expanded ? (
                        <ChevronDown className="size-3 shrink-0 text-code-faint" />
                    ) : (
                        <ChevronRight className="size-3 shrink-0 text-code-faint" />
                    )}
                    <LayoutGrid className="size-3.5 shrink-0 text-code-muted" />
                    <span className="min-w-0 flex-1 truncate text-[10px] font-semibold tracking-[0.08em] text-code-muted uppercase">
                        {group.label}
                    </span>
                    <span className="font-mono text-[8px] text-code-faint">
                        {group.projects.length}
                    </span>
                </button>

                <div className="flex items-center">
                    <div className="pointer-events-none opacity-0 transition-opacity group-focus-within/category:pointer-events-auto group-focus-within/category:opacity-100 group-hover/category:pointer-events-auto group-hover/category:opacity-100">
                        <IconButton
                            label={`New workspace in ${group.label}`}
                            onClick={() => onNewProject(group.category)}
                        >
                            <Plus className="size-3.5" />
                        </IconButton>
                    </div>
                    {group.category && onRenameCategory && onDeleteCategory && (
                        <LibraryCategoryMenu
                            category={group.category}
                            onRename={onRenameCategory}
                            onDelete={onDeleteCategory}
                        />
                    )}
                </div>
            </div>

            {expanded &&
                (group.projects.length > 0 ? (
                    <ProjectTreeContent
                        {...treeProps}
                        projects={group.projects}
                        standaloneSnippets={[]}
                        showStandalone={false}
                        onReorderProjects={onReorderProjects}
                    />
                ) : (
                    <button
                        type="button"
                        onClick={() => onNewProject(group.category)}
                        className="flex h-8 w-full items-center gap-1.5 px-8 text-left text-[9px] text-code-faint transition hover:bg-code-hover hover:text-code-muted"
                    >
                        <Plus className="size-3" /> Add the first workspace
                        <span className="sr-only"> to {group.label}</span>
                    </button>
                ))}

            <span className="sr-only">{projectCountLabel}</span>
        </section>
    );
}

function LibraryCategoryMenu({
    category,
    onRename,
    onDelete,
}: {
    category: LibraryCategory;
    onRename: (category: LibraryCategory) => void;
    onDelete: (category: LibraryCategory) => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={`${category.name} category actions`}
                    title={`Manage ${category.name} category`}
                    className="rounded bg-code-raised/60 p-1 text-code-muted transition hover:bg-code-hover hover:text-code-text"
                >
                    <MoreHorizontal className="size-3" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent side="right" align="start" className="w-40">
                <DropdownMenuItem onSelect={() => onRename(category)}>
                    <Pencil /> Rename category
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    variant="destructive"
                    onSelect={() => onDelete(category)}
                >
                    <Trash2 /> Delete category
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function ProjectTreeContent({
    projects,
    standaloneSnippets,
    visibleSnippetIds,
    matchedProjectIds,
    matchedFolderIds,
    filtering,
    includeMatchedProjectContents,
    showStandalone = true,
    activeSnippetId,
    dirtySnippetIds,
    revealedProjectId,
    expandedProjects,
    expandedFolders,
    pinnedKeys,
    dragItem,
    eligibleDropKeys,
    activeDropKey,
    onProjectExpandedChange,
    onFolderExpandedChange,
    onOpen,
    onNewStandaloneSnippet,
    onNewFolder,
    onNewSnippet,
    onRename,
    onDelete,
    onToggleFavourite,
    onTogglePin,
    onReorderProjects,
    onDragStart,
    onDragEnd,
    onDragOverTarget,
    onDropTarget,
}: TreeContentProps) {
    const interactions = useExplorerInteractions();
    const reorderStatusId = useId();
    const [workspaceDragId, setWorkspaceDragId] = useState<number | null>(null);
    const [workspaceDrop, setWorkspaceDrop] =
        useState<WorkspaceReorderDrop | null>(null);
    const [reorderStatus, setReorderStatus] = useState('');
    const workspaceDropCommittedRef = useRef(false);
    const visibleProjects = sortPinnedProjects(
        projects.filter(
            (project) =>
                !filtering ||
                matchedProjectIds.has(project.id) ||
                project.folders.some((folder) =>
                    matchedFolderIds.has(folder.id),
                ) ||
                project.snippets.some((snippet) =>
                    visibleSnippetIds?.has(snippet.id),
                ),
        ),
        pinnedKeys,
    );
    const workspaceIds = visibleProjects.map((project) => project.id);
    const projectChildDepth = onReorderProjects ? 2 : 0;
    const isPinnedWorkspace = (projectId: number) =>
        pinnedKeys.has(libraryPinKey({ type: 'project', id: projectId }));
    const resolveWorkspaceDragId = (event: ReactDragEvent<HTMLElement>) => {
        if (workspaceDragId !== null) {
            return workspaceDragId;
        }

        const transferredId = Number(
            event.dataTransfer.getData(workspaceReorderMime),
        );

        return Number.isInteger(transferredId) && transferredId > 0
            ? transferredId
            : null;
    };
    const startWorkspaceReorder = (
        event: ReactDragEvent<HTMLElement>,
        project: SnippetProject,
    ) => {
        event.stopPropagation();
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData(workspaceReorderMime, String(project.id));
        event.dataTransfer.setData('text/plain', project.name);
        workspaceDropCommittedRef.current = false;
        setWorkspaceDragId(project.id);
        setWorkspaceDrop(null);
        setReorderStatus(
            `Moving ${project.name}. Choose a ${isPinnedWorkspace(project.id) ? 'pinned' : 'unpinned'} workspace and place it before or after.`,
        );
    };
    const finishWorkspaceReorder = (project: SnippetProject) => {
        if (!workspaceDropCommittedRef.current) {
            setReorderStatus(`Stopped moving ${project.name}.`);
        }

        workspaceDropCommittedRef.current = false;
        setWorkspaceDragId(null);
        setWorkspaceDrop(null);
    };
    const dragOverWorkspace = (
        event: ReactDragEvent<HTMLElement>,
        targetProject: SnippetProject,
    ) => {
        const sourceId = resolveWorkspaceDragId(event);
        const sourceProject = visibleProjects.find(
            (project) => project.id === sourceId,
        );

        if (
            sourceId === null ||
            sourceId === targetProject.id ||
            !sourceProject ||
            isPinnedWorkspace(sourceId) !== isPinnedWorkspace(targetProject.id)
        ) {
            event.dataTransfer.dropEffect = 'none';
            setWorkspaceDrop(null);

            if (
                sourceProject &&
                isPinnedWorkspace(sourceProject.id) !==
                    isPinnedWorkspace(targetProject.id)
            ) {
                setReorderStatus(
                    `${sourceProject.name} can only move within the same pinned group.`,
                );
            }

            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.dataTransfer.dropEffect = 'move';

        const bounds = event.currentTarget.getBoundingClientRect();
        const placement: WorkspaceDropPlacement =
            event.clientY < bounds.top + bounds.height / 2 ? 'before' : 'after';

        setWorkspaceDrop((current) =>
            current?.projectId === targetProject.id &&
            current.placement === placement
                ? current
                : { projectId: targetProject.id, placement },
        );
        setReorderStatus(
            `Move ${sourceProject.name} ${placement} ${targetProject.name}.`,
        );
    };
    const dropWorkspace = (
        event: ReactDragEvent<HTMLElement>,
        targetProject: SnippetProject,
    ) => {
        const sourceId = resolveWorkspaceDragId(event);
        const sourceProject = visibleProjects.find(
            (project) => project.id === sourceId,
        );
        const bounds = event.currentTarget.getBoundingClientRect();
        const pointerPlacement: WorkspaceDropPlacement =
            event.clientY < bounds.top + bounds.height / 2 ? 'before' : 'after';
        const placement =
            workspaceDrop?.projectId === targetProject.id
                ? workspaceDrop.placement
                : pointerPlacement;

        if (
            !onReorderProjects ||
            sourceId === null ||
            sourceId === targetProject.id ||
            !sourceProject ||
            isPinnedWorkspace(sourceId) !== isPinnedWorkspace(targetProject.id)
        ) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        onReorderProjects(
            reorderWorkspaceIds(
                workspaceIds,
                sourceId,
                targetProject.id,
                placement,
            ),
        );
        workspaceDropCommittedRef.current = true;
        setWorkspaceDragId(null);
        setWorkspaceDrop(null);
        setReorderStatus(
            `Moved ${sourceProject.name} ${placement} ${targetProject.name}.`,
        );
    };
    const reorderWorkspaceWithKeyboard = (
        event: ReactKeyboardEvent<HTMLButtonElement>,
        project: SnippetProject,
    ) => {
        if (
            !onReorderProjects ||
            (event.key !== 'ArrowUp' && event.key !== 'ArrowDown')
        ) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const pinned = isPinnedWorkspace(project.id);
        const group = visibleProjects.filter(
            (candidate) => isPinnedWorkspace(candidate.id) === pinned,
        );
        const currentIndex = group.findIndex(
            (candidate) => candidate.id === project.id,
        );
        const targetIndex =
            event.key === 'ArrowUp' ? currentIndex - 1 : currentIndex + 1;
        const target = group[targetIndex];

        if (!target) {
            setReorderStatus(
                `${project.name} is already ${event.key === 'ArrowUp' ? 'first' : 'last'} in the ${pinned ? 'pinned' : 'unpinned'} workspace group.`,
            );

            return;
        }

        const placement: WorkspaceDropPlacement =
            event.key === 'ArrowUp' ? 'before' : 'after';
        onReorderProjects(
            reorderWorkspaceIds(workspaceIds, project.id, target.id, placement),
        );
        setReorderStatus(
            `Moved ${project.name} ${placement} ${target.name}. Position ${targetIndex + 1} of ${group.length} in the ${pinned ? 'pinned' : 'unpinned'} workspace group.`,
        );
    };

    return (
        <>
            {onReorderProjects && (
                <p
                    id={reorderStatusId}
                    role="status"
                    aria-live="polite"
                    aria-atomic="true"
                    className="sr-only"
                >
                    {reorderStatus}
                </p>
            )}
            {showStandalone && (
                <StandaloneSection
                    snippets={standaloneSnippets}
                    filtering={filtering}
                    activeSnippetId={activeSnippetId}
                    dirtySnippetIds={dirtySnippetIds}
                    pinnedKeys={pinnedKeys}
                    dragItem={dragItem}
                    eligibleDropKeys={eligibleDropKeys}
                    activeDropKey={activeDropKey}
                    onOpen={onOpen}
                    onNewSnippet={onNewStandaloneSnippet}
                    onRename={onRename}
                    onDelete={onDelete}
                    onToggleFavourite={onToggleFavourite}
                    onTogglePin={onTogglePin}
                    onDragStart={onDragStart}
                    onDragEnd={onDragEnd}
                    onDragOverTarget={onDragOverTarget}
                    onDropTarget={onDropTarget}
                />
            )}

            {visibleProjects.map((project) => {
                const projectMatches = matchedProjectIds.has(project.id);
                const isPersistedExpanded = expandedProjects.has(project.id);
                const target: ExplorerDropTarget = {
                    type: 'project',
                    projectId: project.id,
                };
                const treeVisibleSnippetIds = collectTreeVisibleSnippetIds(
                    project,
                    visibleSnippetIds,
                    filtering,
                    projectMatches,
                    matchedFolderIds,
                    includeMatchedProjectContents,
                );
                const visibleFolderIds = collectVisibleFolderIds(
                    project,
                    treeVisibleSnippetIds,
                    filtering,
                    projectMatches,
                    matchedFolderIds,
                    includeMatchedProjectContents,
                );
                const hasFilteredChildren =
                    (treeVisibleSnippetIds?.size ?? 0) > 0 ||
                    visibleFolderIds.size > 0;
                const isExpanded = filtering
                    ? hasFilteredChildren
                    : isPersistedExpanded;
                const rootFolders = project.folders.filter(
                    (folder) =>
                        folder.parent_id === null &&
                        (!filtering || visibleFolderIds.has(folder.id)),
                );
                const rootSnippets = sortPinnedSnippets(
                    project.snippets.filter(
                        (snippet) =>
                            snippet.folder_id === null &&
                            (!treeVisibleSnippetIds ||
                                treeVisibleSnippetIds.has(snippet.id)),
                    ),
                    pinnedKeys,
                );
                const projectPinTarget: LibraryPinTarget = {
                    type: 'project',
                    id: project.id,
                };
                const projectEntity: ExplorerEntity = {
                    type: 'project',
                    project,
                };
                const projectDropState = resolveDropVisualState(
                    dragItem,
                    target,
                    eligibleDropKeys,
                    activeDropKey,
                );
                const isWorkspaceDragOrigin = workspaceDragId === project.id;
                const workspaceDropPlacement =
                    workspaceDrop?.projectId === project.id
                        ? workspaceDrop.placement
                        : null;
                const projectIsPinned = isPinnedWorkspace(project.id);
                const pinnedGroup = visibleProjects.filter(
                    (candidate) =>
                        isPinnedWorkspace(candidate.id) === projectIsPinned,
                );
                const groupPosition =
                    pinnedGroup.findIndex(
                        (candidate) => candidate.id === project.id,
                    ) + 1;

                return (
                    <div key={project.id}>
                        <div
                            data-project-id={project.id}
                            data-drop-target="project"
                            data-drop-state={projectDropState}
                            data-workspace-drag-origin={
                                isWorkspaceDragOrigin || undefined
                            }
                            data-workspace-drop-placement={
                                workspaceDropPlacement ?? undefined
                            }
                            onDragOver={(event) => {
                                if (
                                    workspaceDragId !== null ||
                                    Array.from(
                                        event.dataTransfer.types,
                                    ).includes(workspaceReorderMime)
                                ) {
                                    dragOverWorkspace(event, project);

                                    return;
                                }

                                onDragOverTarget(event, target);
                            }}
                            onDragLeave={(event) => {
                                if (
                                    workspaceDrop?.projectId === project.id &&
                                    !(
                                        event.relatedTarget instanceof Node &&
                                        event.currentTarget.contains(
                                            event.relatedTarget,
                                        )
                                    )
                                ) {
                                    setWorkspaceDrop(null);
                                }
                            }}
                            onDrop={(event) => {
                                if (
                                    workspaceDragId !== null ||
                                    Array.from(
                                        event.dataTransfer.types,
                                    ).includes(workspaceReorderMime)
                                ) {
                                    dropWorkspace(event, project);

                                    return;
                                }

                                onDropTarget(event, target);
                            }}
                            onContextMenu={(event) =>
                                interactions.openContextMenu(
                                    event,
                                    projectEntity,
                                )
                            }
                            className={cn(
                                'group group/workspace relative flex h-8 items-center gap-1 border-l-2 px-1.5 transition-[background-color,border-color,box-shadow,opacity] hover:bg-code-hover',
                                revealedProjectId === project.id
                                    ? 'border-code-accent bg-code-hover/70'
                                    : 'border-transparent',
                                isWorkspaceDragOrigin &&
                                    'border-sky-300/70 bg-sky-400/10 opacity-65 ring-1 ring-sky-300/30 ring-inset',
                                workspaceDropPlacement && 'bg-sky-400/8',
                                dropTargetClasses(projectDropState),
                            )}
                        >
                            {workspaceDropPlacement && (
                                <WorkspaceInsertionIndicator
                                    placement={workspaceDropPlacement}
                                />
                            )}
                            {onReorderProjects && !dragItem && (
                                <WorkspaceReorderHandle
                                    project={project}
                                    pinned={projectIsPinned}
                                    position={groupPosition}
                                    total={pinnedGroup.length}
                                    isDragging={isWorkspaceDragOrigin}
                                    statusId={reorderStatusId}
                                    onDragStart={startWorkspaceReorder}
                                    onDragEnd={finishWorkspaceReorder}
                                    onKeyDown={reorderWorkspaceWithKeyboard}
                                />
                            )}
                            <div className="flex min-w-0 flex-1 items-center gap-1">
                                <button
                                    type="button"
                                    aria-label={`${isExpanded ? 'Collapse' : 'Expand'} ${project.name}`}
                                    aria-expanded={isExpanded}
                                    onClick={() =>
                                        onProjectExpandedChange(
                                            project.id,
                                            !isPersistedExpanded,
                                        )
                                    }
                                    className="flex shrink-0 items-center gap-1"
                                >
                                    {isExpanded ? (
                                        <ChevronDown className="size-3 shrink-0 text-code-faint" />
                                    ) : (
                                        <ChevronRight className="size-3 shrink-0 text-code-faint" />
                                    )}
                                    <ProjectIcon kind={project.kind} />
                                </button>
                                <InlineEntityName
                                    entity={projectEntity}
                                    name={project.name}
                                    onActivate={() =>
                                        onProjectExpandedChange(
                                            project.id,
                                            !isPersistedExpanded,
                                        )
                                    }
                                    className="min-w-0 flex-1 truncate text-[11px] font-semibold tracking-[0.02em] text-code-text uppercase"
                                />
                                <ProjectFrameworkTags
                                    frameworks={project.frameworks}
                                />
                            </div>
                            {workspaceDragId !== null ? (
                                isWorkspaceDragOrigin ? (
                                    <DragOriginHint />
                                ) : workspaceDropPlacement ? (
                                    <WorkspaceDropHint
                                        placement={workspaceDropPlacement}
                                    />
                                ) : null
                            ) : dragItem ? (
                                <DropTargetHint state={projectDropState} />
                            ) : (
                                <>
                                    <PinButton
                                        label={`Pin ${project.name}`}
                                        target={projectPinTarget}
                                        pinnedKeys={pinnedKeys}
                                        onToggle={onTogglePin}
                                    />
                                    <div className="pointer-events-none flex items-center opacity-0 transition-opacity group-focus-within:pointer-events-auto group-focus-within:opacity-100 group-hover:pointer-events-auto group-hover:opacity-100">
                                        <IconButton
                                            label={
                                                project.kind === 'guide'
                                                    ? 'New guide'
                                                    : 'New snippet'
                                            }
                                            onClick={() =>
                                                onNewSnippet(project, null)
                                            }
                                        >
                                            <FilePlus2 className="size-3.5" />
                                        </IconButton>
                                        <IconButton
                                            label="New folder"
                                            onClick={() =>
                                                onNewFolder(project, null)
                                            }
                                        >
                                            <FolderPlus className="size-3.5" />
                                        </IconButton>
                                        <EntityMenu
                                            entity={projectEntity}
                                            label={`${project.name} actions`}
                                            editLabel="Edit workspace"
                                            onEdit={() =>
                                                onRename(projectEntity)
                                            }
                                            onDelete={() =>
                                                onDelete(projectEntity)
                                            }
                                        />
                                    </div>
                                </>
                            )}
                        </div>

                        {isExpanded && (
                            <div>
                                {rootFolders.map((folder) => (
                                    <FolderNode
                                        key={folder.id}
                                        project={project}
                                        folder={folder}
                                        depth={projectChildDepth}
                                        filtering={filtering}
                                        visibleSnippetIds={
                                            treeVisibleSnippetIds
                                        }
                                        visibleFolderIds={visibleFolderIds}
                                        expandedFolders={expandedFolders}
                                        activeSnippetId={activeSnippetId}
                                        dirtySnippetIds={dirtySnippetIds}
                                        pinnedKeys={pinnedKeys}
                                        dragItem={dragItem}
                                        eligibleDropKeys={eligibleDropKeys}
                                        activeDropKey={activeDropKey}
                                        onToggle={onFolderExpandedChange}
                                        onOpen={onOpen}
                                        onNewFolder={onNewFolder}
                                        onNewSnippet={onNewSnippet}
                                        onRename={onRename}
                                        onDelete={onDelete}
                                        onToggleFavourite={onToggleFavourite}
                                        onTogglePin={onTogglePin}
                                        onDragStart={onDragStart}
                                        onDragEnd={onDragEnd}
                                        onDragOverTarget={onDragOverTarget}
                                        onDropTarget={onDropTarget}
                                    />
                                ))}
                                {rootSnippets.map((snippet) => (
                                    <SnippetRow
                                        key={snippet.id}
                                        snippet={snippet}
                                        project={project}
                                        depth={projectChildDepth}
                                        isActive={
                                            activeSnippetId === snippet.id
                                        }
                                        isDirty={dirtySnippetIds.has(
                                            snippet.id,
                                        )}
                                        pinnedKeys={pinnedKeys}
                                        dragItem={dragItem}
                                        onOpen={onOpen}
                                        onRename={onRename}
                                        onDelete={onDelete}
                                        onToggleFavourite={onToggleFavourite}
                                        onTogglePin={onTogglePin}
                                        onDragStart={onDragStart}
                                        onDragEnd={onDragEnd}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </>
    );
}

type FolderNodeProps = {
    project: SnippetProject;
    folder: SnippetFolder;
    depth: number;
    filtering: boolean;
    visibleSnippetIds: Set<number> | null;
    visibleFolderIds: ReadonlySet<number>;
    expandedFolders: ReadonlySet<number>;
    activeSnippetId: number | null;
    dirtySnippetIds: Set<number>;
    pinnedKeys: ReadonlySet<string>;
    dragItem: ExplorerDragItem | null;
    eligibleDropKeys: ReadonlySet<string>;
    activeDropKey: string | null;
    onToggle: (folderId: number, expanded: boolean) => void;
    onOpen: (snippet: Snippet) => void;
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
    onDragStart: (
        event: ReactDragEvent<HTMLElement>,
        item: ExplorerDragItem,
    ) => void;
    onDragEnd: () => void;
    onDragOverTarget: (
        event: ReactDragEvent<HTMLElement>,
        target: ExplorerDropTarget,
    ) => void;
    onDropTarget: (
        event: ReactDragEvent<HTMLElement>,
        target: ExplorerDropTarget,
    ) => void;
};

function FolderNode({
    project,
    folder,
    depth,
    filtering,
    visibleSnippetIds,
    visibleFolderIds,
    expandedFolders,
    activeSnippetId,
    dirtySnippetIds,
    pinnedKeys,
    dragItem,
    eligibleDropKeys,
    activeDropKey,
    onToggle,
    onOpen,
    onNewFolder,
    onNewSnippet,
    onRename,
    onDelete,
    onToggleFavourite,
    onTogglePin,
    onDragStart,
    onDragEnd,
    onDragOverTarget,
    onDropTarget,
}: FolderNodeProps) {
    const interactions = useExplorerInteractions();
    const folderEntity: ExplorerEntity = {
        type: 'folder',
        project,
        folder,
    };
    const childFolders = project.folders.filter(
        (candidate) =>
            candidate.parent_id === folder.id &&
            (!filtering || visibleFolderIds.has(candidate.id)),
    );
    const snippets = sortPinnedSnippets(
        project.snippets.filter(
            (snippet) =>
                snippet.folder_id === folder.id &&
                (!visibleSnippetIds || visibleSnippetIds.has(snippet.id)),
        ),
        pinnedKeys,
    );
    const isPersistedExpanded = expandedFolders.has(folder.id);
    const isExpanded = filtering || isPersistedExpanded;
    const target: ExplorerDropTarget = {
        type: 'folder',
        projectId: project.id,
        folderId: folder.id,
    };
    const item: ExplorerDragItem = {
        type: 'folder',
        id: folder.id,
        projectId: project.id,
        parentId: folder.parent_id,
    };
    const isDragOrigin = isSameDragItem(dragItem, item);
    const dropState = resolveDropVisualState(
        dragItem,
        target,
        eligibleDropKeys,
        activeDropKey,
    );

    return (
        <div>
            <div
                data-folder-id={folder.id}
                data-drop-target="folder"
                data-drag-origin={isDragOrigin || undefined}
                data-drop-state={dropState}
                onDragOver={(event) => onDragOverTarget(event, target)}
                onDrop={(event) => onDropTarget(event, target)}
                onContextMenu={(event) =>
                    interactions.openContextMenu(event, folderEntity)
                }
                className={cn(
                    'group flex h-7 items-center gap-1 border-l-2 border-transparent pr-1.5 transition-[background-color,border-color,box-shadow,opacity] hover:bg-code-hover',
                    dropTargetClasses(dropState),
                    isDragOrigin &&
                        'border-sky-200 bg-sky-400/20 text-sky-50 opacity-100 ring-1 ring-sky-300/60 ring-inset',
                )}
                style={{ paddingLeft: `${depth * 12 + 8}px` }}
            >
                <DragHandle
                    label={`Move ${folder.name}`}
                    item={item}
                    isDragging={isDragOrigin}
                    onDragStart={onDragStart}
                    onDragEnd={onDragEnd}
                />
                <button
                    type="button"
                    aria-label={`${isExpanded ? 'Collapse' : 'Expand'} ${folder.name}`}
                    aria-expanded={isExpanded}
                    onClick={() => onToggle(folder.id, !isPersistedExpanded)}
                    className="flex shrink-0 items-center gap-1"
                >
                    {isExpanded ? (
                        <ChevronDown className="size-3 shrink-0 text-code-faint" />
                    ) : (
                        <ChevronRight className="size-3 shrink-0 text-code-faint" />
                    )}
                    {isExpanded ? (
                        <FolderOpen className="size-3.5 shrink-0 text-code-muted" />
                    ) : (
                        <Folder className="size-3.5 shrink-0 text-code-faint" />
                    )}
                </button>
                <InlineEntityName
                    entity={folderEntity}
                    name={folder.name}
                    onActivate={() => onToggle(folder.id, !isPersistedExpanded)}
                    className="min-w-0 flex-1 truncate text-[11px] text-code-muted"
                />
                {dragItem ? (
                    isDragOrigin ? (
                        <DragOriginHint />
                    ) : (
                        <DropTargetHint state={dropState} />
                    )
                ) : (
                    <div className="pointer-events-none flex items-center opacity-0 transition-opacity group-focus-within:pointer-events-auto group-focus-within:opacity-100 group-hover:pointer-events-auto group-hover:opacity-100">
                        <IconButton
                            label="New snippet"
                            onClick={() => onNewSnippet(project, folder)}
                        >
                            <FilePlus2 className="size-3" />
                        </IconButton>
                        <IconButton
                            label="New subfolder"
                            onClick={() => onNewFolder(project, folder)}
                        >
                            <FolderPlus className="size-3" />
                        </IconButton>
                        <EntityMenu
                            entity={folderEntity}
                            label={`${folder.name} actions`}
                            editLabel="Edit folder"
                            onEdit={() => onRename(folderEntity)}
                            onDelete={() => onDelete(folderEntity)}
                        />
                    </div>
                )}
            </div>

            {isExpanded && (
                <div className="relative">
                    <span
                        aria-hidden="true"
                        className="absolute top-0 bottom-0 w-px bg-code-border/65"
                        style={{ left: `${depth * 12 + 20}px` }}
                    />
                    {childFolders.map((childFolder) => (
                        <FolderNode
                            key={childFolder.id}
                            project={project}
                            folder={childFolder}
                            depth={depth + 1}
                            filtering={filtering}
                            visibleSnippetIds={visibleSnippetIds}
                            visibleFolderIds={visibleFolderIds}
                            expandedFolders={expandedFolders}
                            activeSnippetId={activeSnippetId}
                            dirtySnippetIds={dirtySnippetIds}
                            pinnedKeys={pinnedKeys}
                            dragItem={dragItem}
                            eligibleDropKeys={eligibleDropKeys}
                            activeDropKey={activeDropKey}
                            onToggle={onToggle}
                            onOpen={onOpen}
                            onNewFolder={onNewFolder}
                            onNewSnippet={onNewSnippet}
                            onRename={onRename}
                            onDelete={onDelete}
                            onToggleFavourite={onToggleFavourite}
                            onTogglePin={onTogglePin}
                            onDragStart={onDragStart}
                            onDragEnd={onDragEnd}
                            onDragOverTarget={onDragOverTarget}
                            onDropTarget={onDropTarget}
                        />
                    ))}
                    {snippets.map((snippet) => (
                        <SnippetRow
                            key={snippet.id}
                            snippet={snippet}
                            project={project}
                            depth={depth + 1}
                            isActive={activeSnippetId === snippet.id}
                            isDirty={dirtySnippetIds.has(snippet.id)}
                            pinnedKeys={pinnedKeys}
                            dragItem={dragItem}
                            onOpen={onOpen}
                            onRename={onRename}
                            onDelete={onDelete}
                            onToggleFavourite={onToggleFavourite}
                            onTogglePin={onTogglePin}
                            onDragStart={onDragStart}
                            onDragEnd={onDragEnd}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function StandaloneSection({
    snippets,
    filtering,
    activeSnippetId,
    dirtySnippetIds,
    pinnedKeys,
    dragItem,
    eligibleDropKeys,
    activeDropKey,
    onOpen,
    onNewSnippet,
    onRename,
    onDelete,
    onToggleFavourite,
    onTogglePin,
    onDragStart,
    onDragEnd,
    onDragOverTarget,
    onDropTarget,
}: {
    snippets: Snippet[];
    filtering: boolean;
    activeSnippetId: number | null;
    dirtySnippetIds: Set<number>;
    pinnedKeys: ReadonlySet<string>;
    dragItem: ExplorerDragItem | null;
    eligibleDropKeys: ReadonlySet<string>;
    activeDropKey: string | null;
    onOpen: (snippet: Snippet) => void;
    onNewSnippet?: () => void;
    onRename: (entity: ExplorerEntity) => void;
    onDelete: (entity: ExplorerEntity) => void;
    onToggleFavourite?: (snippet: Snippet) => void;
    onTogglePin?: (target: LibraryPinTarget) => void;
    onDragStart: (
        event: ReactDragEvent<HTMLElement>,
        item: ExplorerDragItem,
    ) => void;
    onDragEnd: () => void;
    onDragOverTarget: (
        event: ReactDragEvent<HTMLElement>,
        target: ExplorerDropTarget,
    ) => void;
    onDropTarget: (
        event: ReactDragEvent<HTMLElement>,
        target: ExplorerDropTarget,
    ) => void;
}) {
    const [isPersistedExpanded, setIsPersistedExpanded] = useState(false);
    const isExpanded = filtering || isPersistedExpanded;
    const target: ExplorerDropTarget = { type: 'standalone' };
    const dropState = resolveDropVisualState(
        dragItem,
        target,
        eligibleDropKeys,
        activeDropKey,
    );

    if (filtering && snippets.length === 0) {
        return null;
    }

    return (
        <div>
            <div
                data-drop-target="standalone"
                data-drop-state={dropState}
                onDragOver={(event) => onDragOverTarget(event, target)}
                onDrop={(event) => onDropTarget(event, target)}
                className={cn(
                    'group flex h-8 items-center gap-1 border-l-2 border-transparent px-1.5 transition-[background-color,border-color,box-shadow,opacity] hover:bg-code-hover',
                    dropTargetClasses(dropState),
                )}
            >
                <button
                    type="button"
                    aria-expanded={isExpanded}
                    onClick={() =>
                        setIsPersistedExpanded((expanded) => !expanded)
                    }
                    className="flex min-w-0 flex-1 items-center gap-1 text-left"
                >
                    {isExpanded ? (
                        <ChevronDown className="size-3 shrink-0 text-code-faint" />
                    ) : (
                        <ChevronRight className="size-3 shrink-0 text-code-faint" />
                    )}
                    <Inbox className="size-3.5 shrink-0 text-sky-300" />
                    <span className="truncate text-[11px] font-semibold tracking-[0.02em] text-code-text uppercase">
                        Standalone
                    </span>
                    <span className="font-mono text-[9px] text-code-faint">
                        {snippets.length}
                    </span>
                </button>
                {dragItem ? (
                    <DropTargetHint state={dropState} />
                ) : onNewSnippet ? (
                    <IconButton
                        label="New standalone snippet"
                        onClick={onNewSnippet}
                    >
                        <FilePlus2 className="size-3.5" />
                    </IconButton>
                ) : null}
            </div>
            {isExpanded && (
                <div>
                    {sortPinnedSnippets(snippets, pinnedKeys).map((snippet) => (
                        <SnippetRow
                            key={snippet.id}
                            snippet={snippet}
                            project={null}
                            depth={0}
                            isActive={activeSnippetId === snippet.id}
                            isDirty={dirtySnippetIds.has(snippet.id)}
                            pinnedKeys={pinnedKeys}
                            dragItem={dragItem}
                            onOpen={onOpen}
                            onRename={onRename}
                            onDelete={onDelete}
                            onToggleFavourite={onToggleFavourite}
                            onTogglePin={onTogglePin}
                            onDragStart={onDragStart}
                            onDragEnd={onDragEnd}
                        />
                    ))}
                    {snippets.length === 0 && (
                        <p className="px-7 py-2 text-[9px] text-code-faint">
                            Drop snippets here to remove them from a project.
                        </p>
                    )}
                </div>
            )}
        </div>
    );
}

type SnippetRowProps = {
    snippet: Snippet;
    project: SnippetProject | null;
    depth: number;
    isActive: boolean;
    isDirty: boolean;
    pinnedKeys: ReadonlySet<string>;
    dragItem: ExplorerDragItem | null;
    onOpen: (snippet: Snippet) => void;
    onRename: (entity: ExplorerEntity) => void;
    onDelete: (entity: ExplorerEntity) => void;
    onToggleFavourite?: (snippet: Snippet) => void;
    onTogglePin?: (target: LibraryPinTarget) => void;
    onDragStart: (
        event: ReactDragEvent<HTMLElement>,
        item: ExplorerDragItem,
    ) => void;
    onDragEnd: () => void;
};

function SnippetRow({
    snippet,
    project,
    depth,
    isActive,
    isDirty,
    pinnedKeys,
    dragItem: activeDragItem,
    onOpen,
    onRename,
    onDelete,
    onToggleFavourite,
    onTogglePin,
    onDragStart,
    onDragEnd,
}: SnippetRowProps) {
    const interactions = useExplorerInteractions();
    const snippetEntity: ExplorerEntity = {
        type: 'snippet',
        project,
        snippet,
    };
    const pinTarget: LibraryPinTarget = { type: 'snippet', id: snippet.id };
    const rowDragItem: ExplorerDragItem = {
        type: 'snippet',
        id: snippet.id,
        projectId: project?.id ?? null,
        folderId: snippet.folder_id,
    };
    const isDragOrigin = isSameDragItem(activeDragItem, rowDragItem);
    const searchCodeMatch = interactions.searchCodeMatches.get(snippet.id);

    return (
        <div
            data-snippet-id={snippet.id}
            data-drag-origin={isDragOrigin || undefined}
            onContextMenu={(event) =>
                interactions.openContextMenu(event, snippetEntity)
            }
            onClick={() => onOpen(snippet)}
            className={cn(
                'group min-h-7 cursor-pointer border-l-2 border-transparent transition-[background-color,border-color,box-shadow,opacity]',
                isActive
                    ? 'bg-code-raised text-code-text'
                    : 'text-code-muted hover:bg-code-hover',
                activeDragItem && !isDragOrigin && 'opacity-45',
                isDragOrigin &&
                    'border-sky-200 bg-sky-400/20 text-sky-50 opacity-100 ring-1 ring-sky-300/60 ring-inset',
            )}
            style={{ paddingLeft: `${depth * 12 + 11}px` }}
        >
            <div className="flex h-7 min-w-0 items-center pr-1.5">
                <DragHandle
                    label={`Move ${snippet.filename}`}
                    item={rowDragItem}
                    isDragging={isDragOrigin}
                    onDragStart={onDragStart}
                    onDragEnd={onDragEnd}
                />
                <SnippetFileIcon
                    language={snippet.language}
                    contentType={snippet.content_type}
                    className="mr-1.5 shrink-0"
                />
                <InlineEntityName
                    entity={snippetEntity}
                    name={snippet.filename}
                    onActivate={() => onOpen(snippet)}
                    className="min-w-0 flex-1 truncate text-[11px]"
                />
                {isDirty && (
                    <span
                        aria-label="Unsaved changes"
                        className="size-1.5 shrink-0 rounded-full bg-[#d5a85e]"
                    />
                )}
                {!activeDragItem && (
                    <>
                        <FavouriteButton
                            snippet={snippet}
                            onToggle={onToggleFavourite}
                        />
                        <SnippetUsageIndicator
                            usage={snippet.usage}
                            className="mr-0.5"
                        />
                        <div className="pointer-events-none flex items-center opacity-0 transition-opacity group-focus-within:pointer-events-auto group-focus-within:opacity-100 group-hover:pointer-events-auto group-hover:opacity-100">
                            <PinButton
                                label={`Pin ${snippet.filename}`}
                                target={pinTarget}
                                pinnedKeys={pinnedKeys}
                                onToggle={onTogglePin}
                            />
                            <EntityMenu
                                entity={snippetEntity}
                                label={`${snippet.filename} actions`}
                                editLabel="Edit details"
                                onEdit={() => onRename(snippetEntity)}
                                onDelete={() => onDelete(snippetEntity)}
                            />
                        </div>
                    </>
                )}
                {isDragOrigin && <DragOriginHint />}
            </div>
            {searchCodeMatch && interactions.searchExcerptMode !== 'off' && (
                <SearchCodePreview
                    excerpt={searchCodeMatch}
                    mode={interactions.searchExcerptMode}
                />
            )}
        </div>
    );
}

function SearchCodePreview({
    excerpt,
    mode,
}: {
    excerpt: SnippetCodeExcerpt;
    mode: Exclude<SnippetExcerptMode, 'off'>;
}) {
    const matchStart = Math.max(
        0,
        Math.min(excerpt.matchStart, excerpt.text.length),
    );
    const matchEnd = Math.max(
        matchStart,
        Math.min(excerpt.matchEnd, excerpt.text.length),
    );
    const lineLabel =
        excerpt.lineStart === excerpt.lineEnd
            ? `L${excerpt.lineStart}`
            : `L${excerpt.lineStart}–${excerpt.lineEnd}`;

    return (
        <div
            aria-label={`Code match in ${excerpt.variationName}, ${lineLabel}`}
            className={cn(
                'min-w-0 pr-2 pb-2 pl-6',
                mode === 'hover' &&
                    'hidden group-focus-within:block group-hover:block [@media(hover:none)]:block',
            )}
        >
            <div className="mb-0.5 flex items-center gap-1.5 font-mono text-[8px] text-code-faint">
                <span>{lineLabel}</span>
                <span aria-hidden="true">·</span>
                <span className="truncate">{excerpt.variationName}</span>
            </div>
            <code className="block max-h-12 overflow-hidden text-[9px] leading-4 break-all whitespace-pre-wrap text-code-muted">
                {excerpt.text.slice(0, matchStart)}
                <mark className="rounded-sm bg-code-accent/20 text-code-text">
                    {excerpt.text.slice(matchStart, matchEnd)}
                </mark>
                {excerpt.text.slice(matchEnd)}
            </code>
        </div>
    );
}

function BrowseGroupSection({
    label,
    snippets,
    pinTarget,
    snippetProjects,
    activeSnippetId,
    dirtySnippetIds,
    pinnedKeys,
    dragItem,
    onOpen,
    onRename,
    onDelete,
    onToggleFavourite,
    onTogglePin,
    onDragStart,
    onDragEnd,
}: Pick<BrowseGroup, 'label' | 'snippets' | 'pinTarget'> & {
    snippetProjects: ReadonlyMap<number, SnippetProject>;
    activeSnippetId: number | null;
    dirtySnippetIds: Set<number>;
    pinnedKeys: ReadonlySet<string>;
    dragItem: ExplorerDragItem | null;
    onOpen: (snippet: Snippet) => void;
    onRename: (entity: ExplorerEntity) => void;
    onDelete: (entity: ExplorerEntity) => void;
    onToggleFavourite?: (snippet: Snippet) => void;
    onTogglePin?: (target: LibraryPinTarget) => void;
    onDragStart: (
        event: ReactDragEvent<HTMLElement>,
        item: ExplorerDragItem,
    ) => void;
    onDragEnd: () => void;
}) {
    const [isExpanded, setIsExpanded] = useState(() => snippets.length > 0);

    return (
        <div>
            <div className="group flex h-8 items-center gap-1 px-2 hover:bg-code-hover/70">
                <button
                    type="button"
                    aria-expanded={isExpanded}
                    onClick={() => setIsExpanded((expanded) => !expanded)}
                    className="flex min-w-0 flex-1 items-center gap-1 text-left"
                >
                    {isExpanded ? (
                        <ChevronDown className="size-3 text-code-faint" />
                    ) : (
                        <ChevronRight className="size-3 text-code-faint" />
                    )}
                    <span className="truncate text-[10px] font-semibold tracking-[0.08em] text-code-muted uppercase">
                        {label}
                    </span>
                    <span className="font-mono text-[9px] text-code-faint">
                        {snippets.length}
                    </span>
                </button>
                {pinTarget && (
                    <PinButton
                        label={`Pin ${label}`}
                        target={pinTarget}
                        pinnedKeys={pinnedKeys}
                        onToggle={onTogglePin}
                    />
                )}
            </div>
            {isExpanded &&
                sortPinnedSnippets(snippets, pinnedKeys).map((snippet) => (
                    <SnippetRow
                        key={`${label}-${snippet.id}`}
                        snippet={snippet}
                        project={snippetProjects.get(snippet.id) ?? null}
                        depth={0}
                        isActive={activeSnippetId === snippet.id}
                        isDirty={dirtySnippetIds.has(snippet.id)}
                        pinnedKeys={pinnedKeys}
                        dragItem={dragItem}
                        onOpen={onOpen}
                        onRename={onRename}
                        onDelete={onDelete}
                        onToggleFavourite={onToggleFavourite}
                        onTogglePin={onTogglePin}
                        onDragStart={onDragStart}
                        onDragEnd={onDragEnd}
                    />
                ))}
        </div>
    );
}

function FrameworkProjectGroupSection({
    group,
    treeProps,
    pinnedKeys,
    onTogglePin,
}: {
    group: FrameworkProjectGroup;
    treeProps: TreeContentProps;
    pinnedKeys: ReadonlySet<string>;
    onTogglePin?: (target: LibraryPinTarget) => void;
}) {
    const [isExpanded, setIsExpanded] = useState(
        () => group.projects.length > 0,
    );

    return (
        <div>
            <div className="group flex h-8 items-center gap-1 px-2 hover:bg-code-hover/70">
                <button
                    type="button"
                    aria-expanded={isExpanded}
                    onClick={() => setIsExpanded((expanded) => !expanded)}
                    className="flex min-w-0 flex-1 items-center gap-1 text-left"
                >
                    {isExpanded ? (
                        <ChevronDown className="size-3 text-code-faint" />
                    ) : (
                        <ChevronRight className="size-3 text-code-faint" />
                    )}
                    <span className="truncate text-[10px] font-semibold tracking-[0.08em] text-code-muted uppercase">
                        {group.label}
                    </span>
                    <span className="font-mono text-[9px] text-code-faint">
                        {group.projects.length}
                    </span>
                </button>
                {group.pinTarget && (
                    <PinButton
                        label={`Pin ${group.label}`}
                        target={group.pinTarget}
                        pinnedKeys={pinnedKeys}
                        onToggle={onTogglePin}
                    />
                )}
            </div>
            {isExpanded && group.projects.length > 0 && (
                <div className="ml-2 border-l border-code-border/60">
                    <ProjectTreeContent
                        {...treeProps}
                        projects={group.projects}
                        standaloneSnippets={[]}
                        showStandalone={false}
                    />
                </div>
            )}
        </div>
    );
}

function DragMoveStatus({
    sourceLabel,
    targetLabel,
}: {
    sourceLabel: string;
    targetLabel: string | null;
}) {
    return (
        <div className="pointer-events-none sticky top-1 z-30 h-0 px-1.5">
            <div
                id="explorer-drag-status"
                role="status"
                aria-live="polite"
                aria-atomic="true"
                className="flex min-h-9 items-center gap-2 rounded-md border border-sky-300/60 bg-code-panel/95 px-2.5 text-[10px] text-code-text shadow-lg shadow-black/30 backdrop-blur"
            >
                <span className="shrink-0 rounded-sm bg-sky-300 px-1.5 py-0.5 font-semibold tracking-[0.08em] text-slate-950 uppercase">
                    Moving
                </span>
                <span className="min-w-0 truncate font-mono text-sky-100">
                    {sourceLabel}
                </span>
                <ArrowRight className="size-3 shrink-0 text-sky-300" />
                <span className="min-w-0 truncate text-code-muted">
                    {targetLabel
                        ? `Drop into ${targetLabel}`
                        : 'Choose a blue outlined destination'}
                </span>
            </div>
        </div>
    );
}

type DropVisualState = 'active' | 'eligible' | 'unavailable' | undefined;

function DropTargetHint({ state }: { state: DropVisualState }) {
    if (!state) {
        return null;
    }

    if (state === 'unavailable') {
        return (
            <span
                aria-hidden="true"
                className="shrink-0 font-mono text-[8px] tracking-[0.04em] text-code-faint uppercase"
            >
                Not here
            </span>
        );
    }

    return (
        <span
            aria-hidden="true"
            className={cn(
                'flex shrink-0 items-center gap-1 rounded-sm border px-1.5 py-0.5 font-mono text-[8px] font-semibold tracking-[0.06em] uppercase',
                state === 'active'
                    ? 'border-sky-200 bg-sky-300 text-slate-950'
                    : 'border-sky-400/50 bg-sky-400/10 text-sky-200',
            )}
        >
            <CornerDownRight className="size-2.5" />
            {state === 'active' ? 'Drop here' : 'Drop'}
        </span>
    );
}

function WorkspaceInsertionIndicator({
    placement,
}: {
    placement: WorkspaceDropPlacement;
}) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'pointer-events-none absolute right-1 left-1 z-20 h-0.5 rounded-full bg-sky-300 shadow-[0_0_10px_rgba(125,211,252,0.85)]',
                placement === 'before' ? '-top-px' : '-bottom-px',
            )}
        >
            <span className="absolute top-1/2 -left-0.5 size-1.5 -translate-y-1/2 rounded-full bg-sky-200" />
        </span>
    );
}

function WorkspaceDropHint({
    placement,
}: {
    placement: WorkspaceDropPlacement;
}) {
    return (
        <span
            aria-hidden="true"
            className="shrink-0 rounded-sm border border-sky-300/50 bg-sky-300/10 px-1.5 py-0.5 font-mono text-[8px] font-semibold tracking-[0.06em] text-sky-100 uppercase"
        >
            Insert {placement}
        </span>
    );
}

function DragOriginHint() {
    return (
        <span
            aria-hidden="true"
            className="shrink-0 rounded-sm border border-sky-300/50 bg-sky-300/10 px-1.5 py-0.5 font-mono text-[8px] font-semibold tracking-[0.06em] text-sky-100 uppercase"
        >
            Origin
        </span>
    );
}

function WorkspaceReorderHandle({
    project,
    pinned,
    position,
    total,
    isDragging,
    statusId,
    onDragStart,
    onDragEnd,
    onKeyDown,
}: {
    project: SnippetProject;
    pinned: boolean;
    position: number;
    total: number;
    isDragging: boolean;
    statusId: string;
    onDragStart: (
        event: ReactDragEvent<HTMLElement>,
        project: SnippetProject,
    ) => void;
    onDragEnd: (project: SnippetProject) => void;
    onKeyDown: (
        event: ReactKeyboardEvent<HTMLButtonElement>,
        project: SnippetProject,
    ) => void;
}) {
    const groupLabel = pinned ? 'pinned' : 'unpinned';
    const label = `Reorder ${project.name}. Position ${position} of ${total} in the ${groupLabel} workspace group. Use Arrow Up or Arrow Down.`;

    return (
        <button
            type="button"
            draggable
            aria-label={label}
            aria-describedby={statusId}
            aria-keyshortcuts="ArrowUp ArrowDown"
            title={label}
            onClick={(event) => event.stopPropagation()}
            onDragStart={(event) => onDragStart(event, project)}
            onDragEnd={() => onDragEnd(project)}
            onKeyDown={(event) => onKeyDown(event, project)}
            className={cn(
                'flex size-5 shrink-0 cursor-grab items-center justify-center rounded-sm text-code-faint opacity-0 transition group-focus-within/workspace:opacity-100 group-hover/workspace:opacity-100 hover:bg-code-raised hover:text-sky-300 focus:opacity-100 focus-visible:opacity-100 focus-visible:ring-1 focus-visible:ring-sky-300/70 focus-visible:outline-none active:cursor-grabbing',
                isDragging &&
                    'bg-sky-400/10 text-sky-200 opacity-100 ring-1 ring-sky-300/50',
            )}
        >
            <GripVertical className="size-3" />
        </button>
    );
}

function FavouriteButton({
    snippet,
    onToggle,
}: {
    snippet: Snippet;
    onToggle?: (snippet: Snippet) => void;
}) {
    const label = snippet.is_favourite
        ? `Remove ${snippet.filename} from favourites`
        : `Add ${snippet.filename} to favourites`;

    return (
        <button
            type="button"
            aria-label={label}
            aria-pressed={snippet.is_favourite}
            title={label}
            disabled={!onToggle}
            onClick={(event) => {
                event.stopPropagation();
                onToggle?.(snippet);
            }}
            className={cn(
                'flex size-6 shrink-0 items-center justify-center rounded transition hover:bg-code-hover hover:text-sky-200 disabled:hidden',
                snippet.is_favourite ? 'text-sky-300' : 'text-code-faint',
            )}
        >
            <Star
                className={cn(
                    'size-3',
                    snippet.is_favourite && 'fill-sky-300/30',
                )}
            />
        </button>
    );
}

function PinButton({
    label,
    target,
    pinnedKeys,
    onToggle,
}: {
    label: string;
    target: LibraryPinTarget;
    pinnedKeys: ReadonlySet<string>;
    onToggle?: (target: LibraryPinTarget) => void;
}) {
    const isPinned = pinnedKeys.has(libraryPinKey(target));

    return (
        <button
            type="button"
            aria-label={isPinned ? label.replace('Pin ', 'Unpin ') : label}
            aria-pressed={isPinned}
            title={isPinned ? label.replace('Pin ', 'Unpin ') : label}
            disabled={!onToggle}
            onClick={(event) => {
                event.stopPropagation();
                onToggle?.(target);
            }}
            className={cn(
                'rounded p-1 transition hover:bg-code-hover hover:text-sky-200 disabled:hidden',
                isPinned
                    ? 'text-sky-300'
                    : 'text-code-faint opacity-0 group-focus-within:opacity-100 group-hover:opacity-100',
            )}
        >
            <Pin className={cn('size-3', isPinned && 'fill-sky-300/30')} />
        </button>
    );
}

function DragHandle({
    label,
    item,
    isDragging,
    onDragStart,
    onDragEnd,
}: {
    label: string;
    item: ExplorerDragItem;
    isDragging: boolean;
    onDragStart: (
        event: ReactDragEvent<HTMLElement>,
        item: ExplorerDragItem,
    ) => void;
    onDragEnd: () => void;
}) {
    return (
        <button
            type="button"
            draggable
            aria-label={label}
            aria-describedby={isDragging ? 'explorer-drag-status' : undefined}
            title={label}
            onClick={(event) => event.stopPropagation()}
            onDragStart={(event) => onDragStart(event, item)}
            onDragEnd={onDragEnd}
            className={cn(
                'cursor-grab rounded-sm text-code-faint opacity-0 transition group-focus-within:opacity-100 group-hover:opacity-100 hover:text-sky-300 active:cursor-grabbing',
                isDragging && 'text-sky-200 opacity-100',
            )}
        >
            <GripVertical className="size-3" />
        </button>
    );
}

function ProjectIcon({ kind }: { kind: SnippetProject['kind'] }) {
    if (kind === 'guide') {
        return <BookOpenText className="size-3.5 shrink-0 text-sky-300/80" />;
    }

    return kind === 'bundle' ? (
        <Package className="size-3.5 shrink-0 text-code-muted" />
    ) : (
        <FolderOpen className="size-3.5 shrink-0 text-code-muted" />
    );
}

function ProjectFrameworkTags({ frameworks }: { frameworks: Framework[] }) {
    const firstFramework = frameworks[0];

    if (!firstFramework) {
        return null;
    }

    const label = frameworks.map((framework) => framework.name).join(', ');

    return (
        <span
            aria-label={`Frameworks: ${label}`}
            title={label}
            className="flex max-w-20 shrink-0 items-center gap-1 truncate rounded border border-code-border/70 bg-code-canvas/70 px-1 py-0.5 text-[8px] font-medium tracking-normal text-code-faint normal-case"
        >
            <span
                aria-hidden="true"
                className="size-1.5 shrink-0 rounded-full"
                style={{
                    backgroundColor: firstFramework.color ?? '#64748b',
                }}
            />
            <span className="truncate">{firstFramework.name}</span>
            {frameworks.length > 1 && (
                <span className="shrink-0">+{frameworks.length - 1}</span>
            )}
        </span>
    );
}

function IconButton({
    label,
    children,
    onClick,
}: {
    label: string;
    children: React.ReactNode;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            onClick={onClick}
            className="rounded p-1 text-code-faint transition hover:bg-code-hover hover:text-code-text"
        >
            {children}
        </button>
    );
}

function EntityMenu({
    entity,
    label,
    editLabel,
    onEdit,
    onDelete,
}: {
    entity: ExplorerEntity;
    label: string;
    editLabel: string;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const interactions = useExplorerInteractions();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label={label}
                    onClick={(event) => event.stopPropagation()}
                    className="rounded p-1 text-code-faint transition hover:bg-code-hover hover:text-code-text"
                >
                    <MoreHorizontal className="size-3" />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent side="right" align="start" className="w-36">
                <DropdownMenuItem
                    onSelect={() => interactions.beginRename(entity)}
                >
                    <Pencil /> Rename
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={onEdit}>
                    <FilePenLine /> {editLabel}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem variant="destructive" onSelect={onDelete}>
                    <Trash2 /> Move to Trash
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function InlineEntityName({
    entity,
    name,
    className,
    onActivate,
}: {
    entity: ExplorerEntity;
    name: string;
    className?: string;
    onActivate: () => void;
}) {
    const interactions = useExplorerInteractions();
    const inputRef = useRef<HTMLInputElement>(null);
    const isRenaming =
        interactions.renameState !== null &&
        explorerEntityKey(interactions.renameState.entity) ===
            explorerEntityKey(entity);

    useEffect(() => {
        if (isRenaming) {
            inputRef.current?.focus();
            inputRef.current?.select();
        }
    }, [isRenaming]);

    if (isRenaming && interactions.renameState) {
        return (
            <input
                ref={inputRef}
                aria-label={`Rename ${name}`}
                aria-invalid={Boolean(interactions.renameState.error)}
                title={interactions.renameState.error ?? undefined}
                value={interactions.renameState.value}
                disabled={interactions.renameState.processing}
                onClick={(event) => event.stopPropagation()}
                onChange={(event) =>
                    interactions.updateRename(event.target.value)
                }
                onBlur={interactions.commitRename}
                onKeyDown={(event: ReactKeyboardEvent<HTMLInputElement>) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        interactions.commitRename();
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        interactions.cancelRename();
                    }
                }}
                className={cn(
                    'h-5 min-w-0 flex-1 rounded bg-code-canvas px-1.5 text-[11px] text-code-text ring-1 ring-code-accent/70 outline-none disabled:opacity-60',
                    interactions.renameState.error && 'ring-red-400/80',
                )}
            />
        );
    }

    return (
        <button
            type="button"
            title="Double-click to rename"
            onClick={onActivate}
            onDoubleClick={(event) => {
                event.preventDefault();
                event.stopPropagation();
                interactions.beginRename(entity);
            }}
            className={cn('text-left', className)}
        >
            {name}
        </button>
    );
}

function TrashPanel({
    trash,
    onRestore,
    onPermanentlyDelete,
}: {
    trash: LibraryTrash;
    onRestore: (item: LibraryTrashItem) => void;
    onPermanentlyDelete: (item: LibraryTrashItem) => void;
}) {
    const groups = [
        { label: 'Workspaces', items: trash.projects },
        { label: 'Folders', items: trash.folders },
        { label: 'Files', items: trash.snippets },
    ];

    return (
        <div className="flex flex-col gap-2 px-2 py-1">
            {groups.map((group) =>
                group.items.length > 0 ? (
                    <section key={group.label}>
                        <div className="flex h-7 items-center px-1 text-[9px] font-semibold tracking-[0.12em] text-code-faint uppercase">
                            {group.label}
                            <span className="ml-auto font-mono">
                                {group.items.length}
                            </span>
                        </div>
                        <div className="flex flex-col gap-0.5">
                            {group.items.map((item) => (
                                <div
                                    key={`${item.type}:${item.id}`}
                                    className="group flex min-h-9 items-center gap-2 rounded px-2 py-1 hover:bg-code-hover"
                                >
                                    {item.type === 'project' ? (
                                        <Package className="size-3.5 shrink-0 text-code-muted" />
                                    ) : item.type === 'folder' ? (
                                        <Folder className="size-3.5 shrink-0 text-code-muted" />
                                    ) : (
                                        <FilePenLine className="size-3.5 shrink-0 text-code-muted" />
                                    )}
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-[11px] text-code-text">
                                            {item.name}
                                        </span>
                                        <span className="block truncate text-[9px] text-code-faint">
                                            {item.context}
                                        </span>
                                    </span>
                                    <button
                                        type="button"
                                        aria-label={`Restore ${item.name}`}
                                        title={`Restore ${item.name}`}
                                        onClick={() => onRestore(item)}
                                        className="flex size-6 shrink-0 items-center justify-center rounded text-code-faint transition hover:bg-code-raised hover:text-sky-200"
                                    >
                                        <RotateCcw className="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        aria-label={`Permanently delete ${item.name}`}
                                        title={`Permanently delete ${item.name}`}
                                        onClick={() =>
                                            onPermanentlyDelete(item)
                                        }
                                        className="flex size-6 shrink-0 items-center justify-center rounded text-code-faint transition hover:bg-red-500/10 hover:text-red-300"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    </section>
                ) : null,
            )}
        </div>
    );
}

function EmptyBrowserMessage({
    icon: Icon,
    title,
    detail,
    actionLabel,
    onAction,
}: {
    icon: typeof Package;
    title: string;
    detail: string;
    actionLabel?: string;
    onAction?: () => void;
}) {
    return (
        <div className="mx-3 mt-3 rounded-lg border border-dashed border-code-border bg-code-canvas/35 px-3 py-6 text-center">
            <Icon className="mx-auto mb-2 size-5 text-code-faint" />
            <p className="text-xs text-code-muted">{title}</p>
            <p className="mt-1 text-[9px] leading-4 text-code-faint">
                {detail}
            </p>
            {actionLabel && onAction && (
                <button
                    type="button"
                    onClick={onAction}
                    className="mt-2 inline-flex items-center gap-1 text-[10px] font-medium text-sky-300 hover:text-sky-200"
                >
                    <Plus className="size-3" /> {actionLabel}
                </button>
            )}
        </div>
    );
}

function buildBrowseGroups(
    mode: Exclude<
        LibraryBrowseMode,
        'projects' | 'flat' | 'guides' | 'favourites' | 'pinned' | 'trash'
    >,
    snippets: Snippet[],
    {
        frameworks,
        includeEmptyCatalog,
        languageOptions,
        tags,
    }: {
        frameworks: Framework[];
        includeEmptyCatalog: boolean;
        languageOptions: LanguageOption[];
        tags: Tag[];
    },
): BrowseGroup[] {
    const groups = new Map<string, BrowseGroup>();

    if (includeEmptyCatalog && mode === 'language') {
        languageOptions.forEach((language) => {
            const key = language.value.trim().toLowerCase();

            groups.set(`language:${key}`, {
                key: `language:${key}`,
                label: language.label,
                snippets: [],
                pinTarget: { type: 'language', key },
            });
        });
    }

    if (includeEmptyCatalog && mode === 'framework') {
        frameworks.forEach((framework) => {
            groups.set(`framework:${framework.id}`, {
                key: `framework:${framework.id}`,
                label: framework.name,
                snippets: [],
                pinTarget: { type: 'framework', id: framework.id },
            });
        });
    }

    if (includeEmptyCatalog && mode === 'tag') {
        tags.forEach((tag) => {
            groups.set(`tag:${tag.id}`, {
                key: `tag:${tag.id}`,
                label: tag.name,
                snippets: [],
                pinTarget: { type: 'tag', id: tag.id },
            });
        });
    }

    const catalogueLanguageLabels = new Map(
        languageOptions.map((language) => [
            language.value.trim().toLowerCase(),
            language.label,
        ]),
    );

    snippets.forEach((snippet) => {
        if (mode === 'language') {
            const key = snippet.language.trim().toLowerCase() || 'plain-text';
            addSnippetToGroup(
                groups,
                {
                    key: `language:${key}`,
                    label:
                        catalogueLanguageLabels.get(key) ||
                        snippet.language ||
                        'Plain text',
                    snippets: [],
                    pinTarget: { type: 'language', key },
                },
                snippet,
            );

            return;
        }

        if (mode === 'tag') {
            if (snippet.tags.length === 0) {
                addSnippetToGroup(
                    groups,
                    {
                        key: 'tag:untagged',
                        label: 'Untagged',
                        snippets: [],
                    },
                    snippet,
                );

                return;
            }

            snippet.tags.forEach((tag: Tag) =>
                addSnippetToGroup(
                    groups,
                    {
                        key: `tag:${tag.id}`,
                        label: tag.name,
                        snippets: [],
                        pinTarget: { type: 'tag', id: tag.id },
                    },
                    snippet,
                ),
            );

            return;
        }

        const frameworks = snippet.frameworks;

        if (frameworks.length === 0) {
            addSnippetToGroup(
                groups,
                {
                    key: 'framework:none',
                    label: 'No framework',
                    snippets: [],
                },
                snippet,
            );

            return;
        }

        frameworks.forEach((framework) =>
            addSnippetToGroup(
                groups,
                {
                    key: `framework:${framework.id}`,
                    label: framework.name,
                    snippets: [],
                    pinTarget: { type: 'framework', id: framework.id },
                },
                snippet,
            ),
        );
    });

    return [...groups.values()];
}

function buildFrameworkProjectGroups(
    projects: SnippetProject[],
    frameworks: Framework[],
    { includeEmptyCatalog }: { includeEmptyCatalog: boolean },
): FrameworkProjectGroup[] {
    const groups = new Map<string, FrameworkProjectGroup>();

    if (includeEmptyCatalog) {
        frameworks.forEach((framework) => {
            groups.set(`framework:${framework.id}`, {
                key: `framework:${framework.id}`,
                label: framework.name,
                projects: [],
                pinTarget: { type: 'framework', id: framework.id },
            });
        });
    }

    projects.forEach((project) => {
        if (project.frameworks.length === 0) {
            addProjectToFrameworkGroup(
                groups,
                {
                    key: 'framework:none',
                    label: 'No framework',
                    projects: [],
                },
                project,
            );

            return;
        }

        project.frameworks.forEach((framework) =>
            addProjectToFrameworkGroup(
                groups,
                {
                    key: `framework:${framework.id}`,
                    label: framework.name,
                    projects: [],
                    pinTarget: { type: 'framework', id: framework.id },
                },
                project,
            ),
        );
    });

    return [...groups.values()];
}

function sortFrameworkProjectGroups(
    groups: FrameworkProjectGroup[],
    pinnedKeys: ReadonlySet<string>,
): FrameworkProjectGroup[] {
    return [...groups].sort((left, right) => {
        const leftPinned = left.pinTarget
            ? pinnedKeys.has(libraryPinKey(left.pinTarget))
            : false;
        const rightPinned = right.pinTarget
            ? pinnedKeys.has(libraryPinKey(right.pinTarget))
            : false;

        if (leftPinned !== rightPinned) {
            return leftPinned ? -1 : 1;
        }

        if (left.projects.length > 0 !== right.projects.length > 0) {
            return left.projects.length > 0 ? -1 : 1;
        }

        return left.label.localeCompare(right.label);
    });
}

function addProjectToFrameworkGroup(
    groups: Map<string, FrameworkProjectGroup>,
    group: FrameworkProjectGroup,
    project: SnippetProject,
): void {
    const existing = groups.get(group.key);

    if (existing) {
        existing.projects.push(project);

        return;
    }

    groups.set(group.key, { ...group, projects: [project] });
}

function sortBrowseGroups(
    groups: BrowseGroup[],
    pinnedKeys: ReadonlySet<string>,
): BrowseGroup[] {
    return [...groups].sort((left, right) => {
        const leftPinned = left.pinTarget
            ? pinnedKeys.has(libraryPinKey(left.pinTarget))
            : false;
        const rightPinned = right.pinTarget
            ? pinnedKeys.has(libraryPinKey(right.pinTarget))
            : false;

        if (leftPinned !== rightPinned) {
            return leftPinned ? -1 : 1;
        }

        const leftHasSnippets = left.snippets.length > 0;
        const rightHasSnippets = right.snippets.length > 0;

        if (leftHasSnippets !== rightHasSnippets) {
            return leftHasSnippets ? -1 : 1;
        }

        return left.label.localeCompare(right.label);
    });
}

function sortPinnedProjects(
    projects: readonly SnippetProject[],
    pinnedKeys: ReadonlySet<string>,
): SnippetProject[] {
    return sortPinnedFirst(projects, (project) =>
        pinnedKeys.has(libraryPinKey({ type: 'project', id: project.id })),
    );
}

function sortPinnedSnippets(
    snippets: readonly Snippet[],
    pinnedKeys: ReadonlySet<string>,
): Snippet[] {
    return sortPinnedFirst(snippets, (snippet) =>
        pinnedKeys.has(libraryPinKey({ type: 'snippet', id: snippet.id })),
    );
}

function addSnippetToGroup(
    groups: Map<string, BrowseGroup>,
    group: BrowseGroup,
    snippet: Snippet,
): void {
    const existing = groups.get(group.key);

    if (existing) {
        existing.snippets.push(snippet);

        return;
    }

    groups.set(group.key, { ...group, snippets: [snippet] });
}

function collectVisibleFolderIds(
    project: SnippetProject,
    visibleSnippetIds: ReadonlySet<number> | null,
    filtering: boolean,
    projectMatches: boolean,
    matchedFolderIds: ReadonlySet<number>,
    includeMatchedProjectContents: boolean,
): Set<number> {
    if (!filtering || (projectMatches && includeMatchedProjectContents)) {
        return new Set(project.folders.map((folder) => folder.id));
    }

    const foldersById = new Map(
        project.folders.map((folder) => [folder.id, folder]),
    );
    const folderIds = collectMatchedFolderSubtreeIds(project, matchedFolderIds);

    [...folderIds].forEach((folderId) => {
        let parentId = foldersById.get(folderId)?.parent_id ?? null;

        while (parentId !== null && !folderIds.has(parentId)) {
            folderIds.add(parentId);
            parentId = foldersById.get(parentId)?.parent_id ?? null;
        }
    });

    project.snippets.forEach((snippet) => {
        if (!visibleSnippetIds?.has(snippet.id) || snippet.folder_id === null) {
            return;
        }

        let folderId: number | null = snippet.folder_id;

        while (folderId !== null && !folderIds.has(folderId)) {
            folderIds.add(folderId);
            folderId = foldersById.get(folderId)?.parent_id ?? null;
        }
    });

    return folderIds;
}

function collectTreeVisibleSnippetIds(
    project: SnippetProject,
    visibleSnippetIds: ReadonlySet<number> | null,
    filtering: boolean,
    projectMatches: boolean,
    matchedFolderIds: ReadonlySet<number>,
    includeMatchedProjectContents: boolean,
): Set<number> | null {
    if (!filtering) {
        return visibleSnippetIds ? new Set(visibleSnippetIds) : null;
    }

    const snippetIds = new Set(visibleSnippetIds ?? []);

    if (projectMatches && includeMatchedProjectContents) {
        project.snippets.forEach((snippet) => snippetIds.add(snippet.id));

        return snippetIds;
    }

    const matchedSubtreeIds = collectMatchedFolderSubtreeIds(
        project,
        matchedFolderIds,
    );

    project.snippets.forEach((snippet) => {
        if (
            snippet.folder_id !== null &&
            matchedSubtreeIds.has(snippet.folder_id)
        ) {
            snippetIds.add(snippet.id);
        }
    });

    return snippetIds;
}

function collectMatchedFolderSubtreeIds(
    project: SnippetProject,
    matchedFolderIds: ReadonlySet<number>,
): Set<number> {
    const folderIds = new Set(
        project.folders
            .filter((folder) => matchedFolderIds.has(folder.id))
            .map((folder) => folder.id),
    );
    let addedFolder = true;

    while (addedFolder) {
        addedFolder = false;

        project.folders.forEach((folder) => {
            if (
                folder.parent_id !== null &&
                folderIds.has(folder.parent_id) &&
                !folderIds.has(folder.id)
            ) {
                folderIds.add(folder.id);
                addedFolder = true;
            }
        });
    }

    return folderIds;
}

function collectDropTargets(projects: SnippetProject[]): ExplorerDropTarget[] {
    const targets: ExplorerDropTarget[] = [{ type: 'standalone' }];

    projects.forEach((project) => {
        targets.push({ type: 'project', projectId: project.id });
        project.folders.forEach((folder) => {
            targets.push({
                type: 'folder',
                projectId: project.id,
                folderId: folder.id,
            });
        });
    });

    return targets;
}

function resolveDropVisualState(
    dragItem: ExplorerDragItem | null,
    target: ExplorerDropTarget,
    eligibleDropKeys: ReadonlySet<string>,
    activeDropKey: string | null,
): DropVisualState {
    if (!dragItem) {
        return undefined;
    }

    const targetKey = dropTargetKey(target);

    if (activeDropKey === targetKey) {
        return 'active';
    }

    return eligibleDropKeys.has(targetKey) ? 'eligible' : 'unavailable';
}

function dropTargetClasses(state: DropVisualState): string | undefined {
    if (state === 'active') {
        return 'cursor-move border-sky-200 bg-sky-400/25 ring-2 ring-sky-300/80 ring-inset shadow-[inset_3px_0_0_rgba(125,211,252,0.95)] hover:bg-sky-400/25';
    }

    if (state === 'eligible') {
        return 'cursor-move border-sky-400/60 bg-sky-400/10 outline-1 -outline-offset-1 outline-dashed outline-sky-300/40 hover:bg-sky-400/10';
    }

    if (state === 'unavailable') {
        return 'cursor-not-allowed opacity-45';
    }

    return undefined;
}

function isSameDragItem(
    activeItem: ExplorerDragItem | null,
    item: ExplorerDragItem,
): boolean {
    return activeItem?.type === item.type && activeItem.id === item.id;
}

function describeDragItem(
    item: ExplorerDragItem,
    projects: SnippetProject[],
    standaloneSnippets: Snippet[],
): string {
    if (item.type === 'folder') {
        const project = projects.find(
            (candidate) => candidate.id === item.projectId,
        );
        const folder = project?.folders.find(
            (candidate) => candidate.id === item.id,
        );

        return project && folder
            ? `${folder.name} — ${project.name}`
            : `Folder ${item.id}`;
    }

    const project = projects.find((candidate) =>
        candidate.snippets.some((snippet) => snippet.id === item.id),
    );
    const snippet =
        project?.snippets.find((candidate) => candidate.id === item.id) ??
        standaloneSnippets.find((candidate) => candidate.id === item.id);
    const snippetName = snippet?.filename ?? `Snippet ${item.id}`;

    if (!project) {
        return `${snippetName} — Standalone`;
    }

    return `${snippetName} — ${describeSnippetContainer(project, snippet)}`;
}

function describeDropTarget(
    targetKey: string,
    projects: SnippetProject[],
): string | null {
    if (targetKey === 'standalone') {
        return 'Standalone';
    }

    const [type, projectIdValue, folderIdValue] = targetKey.split(':');
    const project = projects.find(
        (candidate) => candidate.id === Number(projectIdValue),
    );

    if (!project) {
        return null;
    }

    if (type === 'project') {
        return project.name;
    }

    if (type !== 'folder') {
        return null;
    }

    const folderPath = describeFolderPath(project, Number(folderIdValue));

    return folderPath ? `${project.name} / ${folderPath}` : project.name;
}

function describeSnippetContainer(
    project: SnippetProject,
    snippet: Snippet | undefined,
): string {
    if (!snippet?.folder_id) {
        return project.name;
    }

    const folderPath = describeFolderPath(project, snippet.folder_id);

    return folderPath ? `${project.name} / ${folderPath}` : project.name;
}

function describeFolderPath(project: SnippetProject, folderId: number): string {
    const foldersById = new Map(
        project.folders.map((folder) => [folder.id, folder]),
    );
    const names: string[] = [];
    const visitedFolderIds = new Set<number>();
    let currentFolderId: number | null = folderId;

    while (currentFolderId !== null && !visitedFolderIds.has(currentFolderId)) {
        visitedFolderIds.add(currentFolderId);
        const folder = foldersById.get(currentFolderId);

        if (!folder) {
            break;
        }

        names.unshift(folder.name);
        currentFolderId = folder.parent_id;
    }

    return names.join(' / ');
}

function canDrop(
    item: ExplorerDragItem,
    target: ExplorerDropTarget,
    projects: SnippetProject[],
): boolean {
    if (item.type === 'snippet') {
        if (target.type === 'standalone') {
            return item.projectId !== null;
        }

        if (target.type === 'project') {
            return !(
                item.projectId === target.projectId && item.folderId === null
            );
        }

        return !(
            item.projectId === target.projectId &&
            item.folderId === target.folderId
        );
    }

    if (target.type === 'standalone') {
        return false;
    }

    if (target.type === 'project') {
        return target.projectId !== item.projectId || item.parentId !== null;
    }

    if (target.folderId === item.id || target.folderId === item.parentId) {
        return false;
    }

    if (target.projectId !== item.projectId) {
        return true;
    }

    const project = projects.find(
        (candidate) => candidate.id === item.projectId,
    );

    if (!project) {
        return false;
    }

    const foldersById = new Map(
        project.folders.map((folder) => [folder.id, folder]),
    );
    let folderId: number | null = target.folderId;

    while (folderId !== null) {
        if (folderId === item.id) {
            return false;
        }

        folderId = foldersById.get(folderId)?.parent_id ?? null;
    }

    return true;
}

function parseDragItem(value: string): ExplorerDragItem | null {
    if (!value) {
        return null;
    }

    try {
        const item = JSON.parse(value) as Partial<ExplorerDragItem>;

        if (
            item.type === 'snippet' &&
            typeof item.id === 'number' &&
            (typeof item.projectId === 'number' || item.projectId === null) &&
            (typeof item.folderId === 'number' || item.folderId === null)
        ) {
            return item as ExplorerDragItem;
        }

        if (
            item.type === 'folder' &&
            typeof item.id === 'number' &&
            typeof item.projectId === 'number' &&
            (typeof item.parentId === 'number' || item.parentId === null)
        ) {
            return item as ExplorerDragItem;
        }
    } catch {
        return null;
    }

    return null;
}

function dropTargetKey(target: ExplorerDropTarget): string {
    if (target.type === 'standalone') {
        return 'standalone';
    }

    if (target.type === 'project') {
        return `project:${target.projectId}`;
    }

    return `folder:${target.projectId}:${target.folderId}`;
}

export function libraryPinKey(target: LibraryPinTarget): string {
    if (target.type === 'language') {
        return `language:${target.key.toLowerCase()}`;
    }

    return `${target.type}:${target.id}`;
}

function useExplorerInteractions(): ExplorerInteractionContextValue {
    const context = useContext(ExplorerInteractionContext);

    if (!context) {
        throw new Error(
            'Explorer interactions must be used inside ProjectExplorer.',
        );
    }

    return context;
}

function explorerEntityKey(entity: ExplorerEntity): string {
    if (entity.type === 'project') {
        return `project:${entity.project.id}`;
    }

    if (entity.type === 'folder') {
        return `folder:${entity.folder.id}`;
    }

    return `snippet:${entity.snippet.id}`;
}

function explorerEntityName(entity: ExplorerEntity): string {
    if (entity.type === 'project') {
        return entity.project.name;
    }

    if (entity.type === 'folder') {
        return entity.folder.name;
    }

    return entity.snippet.filename;
}

function explorerEntityEditLabel(entity: ExplorerEntity): string {
    if (entity.type === 'project') {
        return 'Edit workspace';
    }

    if (entity.type === 'folder') {
        return 'Edit folder';
    }

    return 'Edit details';
}

function explorerContextMenuPosition({ x, y }: ExplorerContextMenuState): {
    left: number;
    top: number;
} {
    return {
        left: Math.max(8, Math.min(x, window.innerWidth - 184)),
        top: Math.max(8, Math.min(y, window.innerHeight - 152)),
    };
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

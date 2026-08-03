import {
    BrainCircuit,
    Braces,
    Boxes,
    ChevronLeft,
    ChevronDown,
    Columns2,
    FileCode2,
    Folder,
    FolderTree,
    Grid2x2,
    Maximize2,
    Search,
    Square,
    Tag,
    X,
    ZoomIn,
    ZoomOut,
} from 'lucide-react';
import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
} from 'react';
import type {
    CSSProperties,
    KeyboardEvent as ReactKeyboardEvent,
    PointerEvent as ReactPointerEvent,
    WheelEvent as ReactWheelEvent,
} from 'react';
import { SyntaxHighlightedCode } from '@/components/snippets/syntax-highlighted-code';
import { WorkspaceResizeHandle } from '@/components/snippets/workspace-resize-handle';
import {
    brainNodeKindLabel,
    buildBrainAdjacency,
    buildSecondBrainCategoryViews,
    buildSecondBrainGraph,
    filterBrainEdgesByViewport,
    filterSecondBrainGraphByDepth,
    findDirectionalBrainNode,
    focusedBrainPositions,
    resolveBrainCategorySelections,
    secondBrainHeight,
    secondBrainWidth,
    zoomSecondBrainAtPoint,
} from '@/lib/snippets/second-brain-graph';
import type {
    BrainCategoryView,
    BrainDirection,
    BrainEdge,
    BrainEdgeKind,
    BrainGraphDepth,
    BrainNode,
    BrainNodeKind,
    BrainPosition,
} from '@/lib/snippets/second-brain-graph';
import {
    clampWorkspacePanelWidth,
    restoreWorkspacePanelWidth,
} from '@/lib/snippets/workspace-panel-resize';
import { cn } from '@/lib/utils';
import type {
    LibraryCategory,
    Snippet,
    SnippetFolder,
    SnippetProject,
} from '@/types';

type Props = {
    accountKey: number;
    libraryCategories: LibraryCategory[];
    projects: SnippetProject[];
    standaloneSnippets: Snippet[];
    onClose: () => void;
    onOpenSnippet: (snippet: Snippet) => void;
    onRevealProject: (projectId: number) => void;
    onRevealFolder: (projectId: number, folderId: number) => void;
    onBrowseFilter: (
        scope: 'tag' | 'framework' | 'language',
        value: string,
        frameworkId?: number,
    ) => void;
};

const brainSelectionPanelDefaultWidth = 440;
const brainSelectionPanelMinWidth = 280;
const brainSelectionPanelMaxWidth = 640;

type BrainLayout = 'single' | 'split' | 'quad';

type GraphCustomProperties = CSSProperties & {
    '--brain-sway-x'?: string;
    '--brain-sway-y'?: string;
    '--brain-sway-delay'?: string;
    '--brain-sway-duration'?: string;
    '--brain-flicker-delay'?: string;
    '--brain-flicker-duration'?: string;
};

type DragState = {
    pointerId: number;
    clientX: number;
    clientY: number;
    centerX: number;
    centerY: number;
};

type BrainEdgePath = {
    id: string;
    kind: BrainEdgeKind;
    data: string;
    opacity: number;
    strokeWidth: number;
    strokeDasharray?: string;
    emphasized: boolean;
};

type BrainSignal = {
    id: string;
    data: string;
    delay: number;
    duration: number;
    opacity: number;
};

type BrainEdgeFlicker = {
    id: string;
    data: string;
    style: GraphCustomProperties;
};

type BrainFileBrowserItem = {
    id: string;
    kind: 'collection' | 'project' | 'folder' | 'snippet';
    label: string;
    detail?: string;
    children: BrainFileBrowserItem[];
};

const minimumBrainZoom = 0.7;
const maximumBrainZoom = 6;
const brainSemanticZoomThreshold = 4.5;
const brainZoomStep = 0.25;
const brainWheelZoomStep = 0.18;

const nodePalette: Record<
    BrainNodeKind,
    { fill: string; stroke: string; label: string }
> = {
    root: {
        fill: 'fill-sky-300',
        stroke: 'stroke-sky-100',
        label: 'text-sky-50',
    },
    category: {
        fill: 'fill-blue-400',
        stroke: 'stroke-blue-100',
        label: 'text-blue-50',
    },
    collection: {
        fill: 'fill-indigo-400',
        stroke: 'stroke-indigo-100',
        label: 'text-indigo-50',
    },
    project: {
        fill: 'fill-cyan-400',
        stroke: 'stroke-cyan-100',
        label: 'text-cyan-50',
    },
    folder: {
        fill: 'fill-blue-300',
        stroke: 'stroke-blue-50',
        label: 'text-blue-50',
    },
    snippet: {
        fill: 'fill-slate-200',
        stroke: 'stroke-sky-50',
        label: 'text-slate-50',
    },
    tag: {
        fill: 'fill-indigo-400',
        stroke: 'stroke-indigo-100',
        label: 'text-indigo-50',
    },
    framework: {
        fill: 'fill-violet-400',
        stroke: 'stroke-violet-100',
        label: 'text-violet-50',
    },
    language: {
        fill: 'fill-teal-300',
        stroke: 'stroke-teal-50',
        label: 'text-teal-50',
    },
};

const edgePalette: Record<BrainEdgeKind, string> = {
    hierarchy: 'stroke-sky-500',
    tag: 'stroke-indigo-400',
    framework: 'stroke-violet-400',
    language: 'stroke-teal-400',
};

const nodeIcons = {
    root: BrainCircuit,
    category: Boxes,
    collection: FolderTree,
    project: Braces,
    folder: Folder,
    snippet: FileCode2,
    tag: Tag,
    framework: Boxes,
    language: Braces,
} satisfies Record<BrainNodeKind, typeof BrainCircuit>;

const layoutOptions = [
    { id: 'single', label: 'One', icon: Square, requiredCategories: 0 },
    { id: 'split', label: 'Split', icon: Columns2, requiredCategories: 2 },
    { id: 'quad', label: 'Four', icon: Grid2x2, requiredCategories: 4 },
] satisfies {
    id: BrainLayout;
    label: string;
    icon: typeof Square;
    requiredCategories: number;
}[];

const relationshipDepthOptions = [
    { value: 3, label: '3 hops' },
    { value: 4, label: '4 hops' },
    { value: 5, label: '5 hops' },
    { value: 6, label: '6 hops' },
    { value: 'all', label: 'All' },
] satisfies { value: BrainGraphDepth; label: string }[];

export function SecondBrain({
    accountKey,
    libraryCategories,
    projects,
    standaloneSnippets,
    onClose,
    onOpenSnippet,
    onRevealProject,
    onRevealFolder,
    onBrowseFilter,
}: Props) {
    const categoryViews = useMemo(
        () =>
            buildSecondBrainCategoryViews({
                libraryCategories,
                projects,
                standaloneSnippets,
            }),
        [libraryCategories, projects, standaloneSnippets],
    );
    const comparisonViews = useMemo(
        () => categoryViews.filter((view) => view.key !== 'all'),
        [categoryViews],
    );
    const viewByKey = useMemo(
        () => new Map(categoryViews.map((view) => [view.key, view])),
        [categoryViews],
    );
    const comparisonViewKeys = useMemo(
        () => comparisonViews.map((view) => view.key),
        [comparisonViews],
    );
    const [layout, setLayout] = useState<BrainLayout>('single');
    const [singleSelection, setSingleSelection] = useState('all');
    const [comparisonSelections, setComparisonSelections] = useState<
        (string | null)[]
    >([]);
    const [relationshipDepths, setRelationshipDepths] = useState<
        BrainGraphDepth[]
    >(['all', 'all', 'all', 'all']);
    const [query, setQuery] = useState('');
    const [searchFocusRequest, setSearchFocusRequest] = useState(0);
    const effectiveLayout = availableLayout(layout, comparisonViews.length);
    const paneCount =
        effectiveLayout === 'quad' ? 4 : effectiveLayout === 'split' ? 2 : 1;
    const currentSingleSelection = viewByKey.has(singleSelection)
        ? singleSelection
        : 'all';
    const resolvedComparisonSelections = useMemo(
        () =>
            resolveBrainCategorySelections(
                comparisonViewKeys,
                comparisonSelections,
                paneCount,
            ),
        [comparisonSelections, comparisonViewKeys, paneCount],
    );
    const activeViews =
        effectiveLayout === 'single'
            ? [viewByKey.get(currentSingleSelection) ?? categoryViews[0]]
            : resolvedComparisonSelections
                  .map((selection) =>
                      selection ? viewByKey.get(selection) : undefined,
                  )
                  .filter(
                      (view): view is BrainCategoryView => view !== undefined,
                  );
    const activeFileCount = activeViews.reduce(
        (total, view) =>
            total +
            view.standaloneSnippets.length +
            view.projects.reduce(
                (projectTotal, project) =>
                    projectTotal + project.snippets.length,
                0,
            ),
        0,
    );

    const updateComparisonSelection = (index: number, key: string): void => {
        setComparisonSelections((current) => {
            const next = resolveBrainCategorySelections(
                comparisonViewKeys,
                current,
                paneCount,
            );
            next[index] = key;

            return next;
        });
    };

    return (
        <div className="flex min-h-0 flex-1 flex-col overflow-hidden bg-[#07111d] text-code-text">
            <div className="flex h-9 shrink-0 items-stretch justify-between border-b border-sky-950 bg-[#0a1421]">
                <div className="flex min-w-0 items-stretch">
                    <div className="flex min-w-0 items-center gap-2 border-r border-sky-950 bg-[#0d1b2a] px-3 text-[11px] text-sky-100 shadow-[inset_0_-1px_0_rgba(125,211,252,0.7)]">
                        <BrainCircuit className="size-3.5 text-sky-300" />
                        <span className="truncate">Second brain</span>
                        <button
                            type="button"
                            aria-label="Close Second brain"
                            onClick={onClose}
                            className="ml-1 rounded p-0.5 text-sky-200/45 transition hover:bg-sky-300/10 hover:text-sky-100 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-400"
                        >
                            <X className="size-3" />
                        </button>
                    </div>
                </div>
                <div className="flex items-center gap-3 px-3 text-[9px] tracking-[0.12em] text-sky-200/45 uppercase">
                    <span>{activeFileCount} files</span>
                    {effectiveLayout !== 'single' && (
                        <span className="hidden sm:inline">
                            {activeViews.length} categories
                        </span>
                    )}
                </div>
            </div>

            <div className="flex shrink-0 flex-wrap items-center gap-2 border-b border-sky-950/90 bg-[#081522] px-3 py-2">
                <label className="relative min-w-52 flex-1 sm:max-w-80">
                    <span className="sr-only">Search the knowledge map</span>
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-sky-200/45" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        onKeyDown={(event) => {
                            if (
                                event.key === 'Enter' &&
                                query.trim().length > 0
                            ) {
                                event.preventDefault();
                                setSearchFocusRequest((current) => current + 1);
                            }
                        }}
                        data-second-brain-search
                        placeholder="Find a node across these categories…"
                        className="h-8 w-full rounded-lg border border-sky-900/70 bg-[#091725] pr-8 pl-9 text-[11px] text-sky-50 outline-none placeholder:text-sky-200/30 focus-visible:border-sky-500/70 focus-visible:ring-2 focus-visible:ring-sky-400/10"
                    />
                    {query.length > 0 && (
                        <button
                            type="button"
                            aria-label="Clear graph search"
                            onClick={() => setQuery('')}
                            className="absolute top-1/2 right-2 -translate-y-1/2 rounded p-1 text-sky-200/40 transition hover:bg-sky-300/10 hover:text-sky-100 focus-visible:outline-2 focus-visible:outline-sky-400"
                        >
                            <X className="size-3" />
                        </button>
                    )}
                </label>

                <p className="hidden text-[9px] tracking-wide text-sky-200/35 lg:block">
                    Hover to expand · click to pin · double-click to open
                </p>

                <div
                    role="group"
                    aria-label="Category layout"
                    className="ml-auto flex items-center rounded-lg border border-sky-900/60 bg-[#091725] p-0.5"
                >
                    {layoutOptions.map((option) => {
                        const disabled =
                            comparisonViews.length < option.requiredCategories;

                        return (
                            <LayoutButton
                                key={option.id}
                                option={option}
                                active={effectiveLayout === option.id}
                                disabled={disabled}
                                onClick={() => setLayout(option.id)}
                            />
                        );
                    })}
                </div>
            </div>

            <div
                className={cn(
                    'grid min-h-0 flex-1 gap-px overflow-hidden bg-sky-950/90',
                    effectiveLayout === 'single' && 'grid-cols-1 grid-rows-1',
                    effectiveLayout === 'split' &&
                        'grid-cols-1 grid-rows-2 md:grid-cols-2 md:grid-rows-1',
                    effectiveLayout === 'quad' &&
                        'grid-cols-1 grid-rows-4 sm:grid-cols-2 sm:grid-rows-2',
                )}
            >
                {activeViews.map((view, index) => (
                    <BrainPane
                        key={`${index}:${view.key}`}
                        view={view}
                        accountKey={accountKey}
                        query={query}
                        searchFocusRequest={searchFocusRequest}
                        compact={effectiveLayout !== 'single'}
                        paneIndex={index}
                        relationshipDepth={relationshipDepths[index] ?? 'all'}
                        categoryViews={
                            effectiveLayout === 'single'
                                ? categoryViews
                                : comparisonViews
                        }
                        selectedCategoryKeys={
                            effectiveLayout === 'single'
                                ? [currentSingleSelection]
                                : resolvedComparisonSelections.filter(
                                      (selection): selection is string =>
                                          selection !== null,
                                  )
                        }
                        onCategoryChange={(key) => {
                            if (effectiveLayout === 'single') {
                                setSingleSelection(key);
                            } else {
                                updateComparisonSelection(index, key);
                            }
                        }}
                        onRelationshipDepthChange={(depth) =>
                            setRelationshipDepths((current) =>
                                current.map((value, depthIndex) =>
                                    depthIndex === index ? depth : value,
                                ),
                            )
                        }
                        onOpenSnippet={onOpenSnippet}
                        onRevealProject={onRevealProject}
                        onRevealFolder={onRevealFolder}
                        onBrowseFilter={onBrowseFilter}
                    />
                ))}
            </div>
        </div>
    );
}

function LayoutButton({
    option,
    active,
    disabled,
    onClick,
}: {
    option: (typeof layoutOptions)[number];
    active: boolean;
    disabled: boolean;
    onClick: () => void;
}) {
    const Icon = option.icon;
    const disabledReason = `${option.label} view needs ${option.requiredCategories} categories`;

    return (
        <button
            type="button"
            aria-label={`${option.label} category view`}
            aria-pressed={active}
            title={disabled ? disabledReason : `${option.label} category view`}
            disabled={disabled}
            onClick={onClick}
            className={cn(
                'flex h-7 items-center gap-1.5 rounded-md px-2 text-[9px] font-medium transition focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-400 disabled:cursor-not-allowed disabled:opacity-25',
                active
                    ? 'bg-sky-300/12 text-sky-100 shadow-[inset_0_0_0_1px_rgba(125,211,252,0.18)]'
                    : 'text-sky-200/45 hover:bg-sky-300/8 hover:text-sky-100',
            )}
        >
            <Icon className="size-3" />
            <span className="hidden sm:inline">{option.label}</span>
        </button>
    );
}

function BrainPane({
    accountKey,
    view,
    query,
    searchFocusRequest,
    compact,
    paneIndex,
    relationshipDepth,
    categoryViews,
    selectedCategoryKeys,
    onCategoryChange,
    onRelationshipDepthChange,
    onOpenSnippet,
    onRevealProject,
    onRevealFolder,
    onBrowseFilter,
}: {
    accountKey: number;
    view: BrainCategoryView;
    query: string;
    searchFocusRequest: number;
    compact: boolean;
    paneIndex: number;
    relationshipDepth: BrainGraphDepth;
    categoryViews: BrainCategoryView[];
    selectedCategoryKeys: string[];
    onCategoryChange: (key: string) => void;
    onRelationshipDepthChange: (depth: BrainGraphDepth) => void;
    onOpenSnippet: Props['onOpenSnippet'];
    onRevealProject: Props['onRevealProject'];
    onRevealFolder: Props['onRevealFolder'];
    onBrowseFilter: Props['onBrowseFilter'];
}) {
    const selectionPanelWidthStorageKey = `codepilot.second-brain.selection-panel-width.v1.${accountKey}`;
    const [selectionPanelWidth, setSelectionPanelWidth] = useState(
        brainSelectionPanelDefaultWidth,
    );
    const displayedSelectionPanelWidth = clampWorkspacePanelWidth(
        selectionPanelWidth,
        compact ? 240 : brainSelectionPanelMinWidth,
        compact ? 420 : brainSelectionPanelMaxWidth,
    );

    useEffect(() => {
        const frame = window.requestAnimationFrame(() => {
            try {
                setSelectionPanelWidth(
                    restoreWorkspacePanelWidth(
                        window.localStorage.getItem(
                            selectionPanelWidthStorageKey,
                        ),
                        brainSelectionPanelDefaultWidth,
                        compact ? 240 : brainSelectionPanelMinWidth,
                        compact ? 420 : brainSelectionPanelMaxWidth,
                    ),
                );
            } catch {
                setSelectionPanelWidth(brainSelectionPanelDefaultWidth);
            }
        });

        return () => window.cancelAnimationFrame(frame);
    }, [compact, selectionPanelWidthStorageKey]);

    const persistSelectionPanelWidth = useCallback(
        (width: number) => {
            try {
                window.localStorage.setItem(
                    selectionPanelWidthStorageKey,
                    String(width),
                );
            } catch {
                return;
            }
        },
        [selectionPanelWidthStorageKey],
    );
    const graph = useMemo(
        () =>
            buildSecondBrainGraph({
                libraryCategories: view.libraryCategories,
                projects: view.projects,
                standaloneSnippets: view.standaloneSnippets,
            }),
        [view.libraryCategories, view.projects, view.standaloneSnippets],
    );
    const graphNodeById = useMemo(
        () => new Map(graph.nodes.map((node) => [node.id, node])),
        [graph.nodes],
    );
    const snippetById = useMemo(
        () =>
            new Map(
                [
                    ...view.standaloneSnippets,
                    ...view.projects.flatMap((project) => project.snippets),
                ].map((snippet) => [snippet.id, snippet]),
            ),
        [view.projects, view.standaloneSnippets],
    );
    const nodeElements = useRef(new Map<string, SVGGElement>());
    const dragState = useRef<DragState | null>(null);
    const pendingFocusNodeId = useRef<string | null>(null);
    const pendingViewCenter = useRef<{ x: number; y: number } | null>(null);
    const panAnimationFrame = useRef<number | null>(null);
    const lastSearchFocusRequest = useRef(0);
    const instanceId = useId().replaceAll(':', '');
    const glowId = `second-brain-glow-${instanceId}`;
    const [hoveredNodeId, setHoveredNodeId] = useState<string | null>(null);
    const [selectedNodeId, setSelectedNodeId] = useState<string | null>(null);
    const [zoom, setZoom] = useState(1);
    const [viewCenter, setViewCenter] = useState({
        x: secondBrainWidth / 2,
        y: secondBrainHeight / 2,
    });
    const selectedAnchorNodeId =
        selectedNodeId && graphNodeById.has(selectedNodeId)
            ? selectedNodeId
            : null;
    const visibleGraph = useMemo(
        () =>
            filterSecondBrainGraphByDepth(
                graph,
                selectedAnchorNodeId ?? 'root',
                relationshipDepth,
            ),
        [graph, relationshipDepth, selectedAnchorNodeId],
    );
    const nodeById = useMemo(
        () => new Map(visibleGraph.nodes.map((node) => [node.id, node])),
        [visibleGraph.nodes],
    );
    const validHoveredNodeId =
        hoveredNodeId && nodeById.has(hoveredNodeId) ? hoveredNodeId : null;
    const validSelectedNodeId =
        selectedNodeId && nodeById.has(selectedNodeId) ? selectedNodeId : null;
    const adjacency = useMemo(
        () => buildBrainAdjacency(visibleGraph),
        [visibleGraph],
    );
    const focusNodeId = validHoveredNodeId ?? validSelectedNodeId;
    const positions = useMemo(
        () => focusedBrainPositions(visibleGraph, focusNodeId, adjacency),
        [adjacency, focusNodeId, visibleGraph],
    );
    const viewportWidth = secondBrainWidth / zoom;
    const viewportHeight = secondBrainHeight / zoom;
    const viewport = useMemo(
        () => ({
            minX: viewCenter.x - viewportWidth / 2,
            minY: viewCenter.y - viewportHeight / 2,
            maxX: viewCenter.x + viewportWidth / 2,
            maxY: viewCenter.y + viewportHeight / 2,
        }),
        [viewCenter, viewportHeight, viewportWidth],
    );
    const viewBox = `${viewport.minX} ${viewport.minY} ${viewportWidth} ${viewportHeight}`;
    const viewportEdges = useMemo(
        () =>
            filterBrainEdgesByViewport(visibleGraph.edges, positions, viewport),
        [positions, viewport, visibleGraph.edges],
    );
    const normalizedQuery = query.trim().toLocaleLowerCase();
    const matchingNodeIds = useMemo(() => {
        if (normalizedQuery.length === 0) {
            return new Set<string>();
        }

        return new Set(
            graph.nodes
                .filter((node) =>
                    [
                        node.label,
                        node.eyebrow,
                        node.description,
                        ...node.previewItems,
                    ]
                        .join(' ')
                        .toLocaleLowerCase()
                        .includes(normalizedQuery),
                )
                .map((node) => node.id),
        );
    }, [graph.nodes, normalizedQuery]);
    const edgePaths = useMemo(
        () =>
            buildBrainEdgePaths({
                edges: viewportEdges,
                nodes: nodeById,
                positions,
                focusNodeId,
                matchingNodeIds,
                hasQuery: normalizedQuery.length > 0,
            }),
        [
            focusNodeId,
            matchingNodeIds,
            nodeById,
            normalizedQuery.length,
            positions,
            viewportEdges,
        ],
    );
    const signals = useMemo(
        () => buildBrainSignals(viewportEdges, positions),
        [positions, viewportEdges],
    );
    const edgeFlickers = useMemo(
        () => buildBrainEdgeFlickers(viewportEdges, positions),
        [positions, viewportEdges],
    );
    const focusedNeighbours = focusNodeId
        ? (adjacency.get(focusNodeId) ?? new Set<string>())
        : new Set<string>();
    const nodeZoomScale =
        zoom <= brainSemanticZoomThreshold
            ? Math.min(1, zoom ** -1.12)
            : brainSemanticZoomThreshold ** -1.12 *
              (zoom / brainSemanticZoomThreshold) ** -0.6;

    const selectNode = useCallback((nodeId: string) => {
        setSelectedNodeId((current) => (current === nodeId ? null : nodeId));
    }, []);

    const openNode = useCallback(
        (node: BrainNode): void => {
            const action = node.action;

            if (!action) {
                selectNode(node.id);

                return;
            }

            if (action.type === 'snippet') {
                const snippet = snippetById.get(action.snippetId);

                if (snippet) {
                    onOpenSnippet(snippet);
                }

                return;
            }

            if (action.type === 'project') {
                onRevealProject(action.projectId);

                return;
            }

            if (action.type === 'folder') {
                onRevealFolder(action.projectId, action.folderId);

                return;
            }

            onBrowseFilter(action.scope, action.value, action.frameworkId);
        },
        [
            onBrowseFilter,
            onOpenSnippet,
            onRevealFolder,
            onRevealProject,
            selectNode,
            snippetById,
        ],
    );

    const focusNode = useCallback((nodeId: string) => {
        setSelectedNodeId(nodeId);

        const nodeElement = nodeElements.current.get(nodeId);

        if (nodeElement) {
            nodeElement.focus();
        } else {
            pendingFocusNodeId.current = nodeId;
        }
    }, []);

    useEffect(() => {
        const nodeId = pendingFocusNodeId.current;

        if (!nodeId || !nodeById.has(nodeId)) {
            return;
        }

        nodeElements.current.get(nodeId)?.focus();
        pendingFocusNodeId.current = null;
    }, [nodeById]);

    useEffect(
        () => () => {
            if (panAnimationFrame.current !== null) {
                window.cancelAnimationFrame(panAnimationFrame.current);
            }
        },
        [],
    );

    useEffect(() => {
        if (
            searchFocusRequest === 0 ||
            lastSearchFocusRequest.current === searchFocusRequest
        ) {
            return;
        }

        lastSearchFocusRequest.current = searchFocusRequest;
        const firstMatch = graph.nodes.find((node) =>
            matchingNodeIds.has(node.id),
        );

        if (!firstMatch) {
            return;
        }

        const animationFrame = window.requestAnimationFrame(() => {
            const activeElement = document.activeElement;

            if (
                activeElement instanceof HTMLInputElement &&
                activeElement.matches('[data-second-brain-search]')
            ) {
                focusNode(firstMatch.id);
            }
        });

        return () => window.cancelAnimationFrame(animationFrame);
    }, [focusNode, graph.nodes, matchingNodeIds, searchFocusRequest]);

    const handleNodeKeyDown = (
        event: ReactKeyboardEvent<SVGGElement>,
        node: BrainNode,
    ) => {
        const direction = {
            ArrowUp: 'up',
            ArrowRight: 'right',
            ArrowDown: 'down',
            ArrowLeft: 'left',
        }[event.key] as BrainDirection | undefined;

        if (direction) {
            event.preventDefault();
            const nextNodeId = findDirectionalBrainNode(
                visibleGraph,
                node.id,
                direction,
            );

            if (nextNodeId) {
                focusNode(nextNodeId);
            }

            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            openNode(node);
        } else if (event.key === ' ') {
            event.preventDefault();
            selectNode(node.id);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setSelectedNodeId(null);
            setHoveredNodeId(null);
        }
    };

    const handlePointerDown = (event: ReactPointerEvent<SVGSVGElement>) => {
        if ((event.target as Element).closest('[data-brain-node]')) {
            return;
        }

        event.currentTarget.setPointerCapture(event.pointerId);
        dragState.current = {
            pointerId: event.pointerId,
            clientX: event.clientX,
            clientY: event.clientY,
            centerX: viewCenter.x,
            centerY: viewCenter.y,
        };
    };

    const handlePointerMove = (event: ReactPointerEvent<SVGSVGElement>) => {
        const drag = dragState.current;

        if (!drag || drag.pointerId !== event.pointerId) {
            return;
        }

        const bounds = event.currentTarget.getBoundingClientRect();
        const deltaX =
            ((event.clientX - drag.clientX) / Math.max(1, bounds.width)) *
            viewportWidth;
        const deltaY =
            ((event.clientY - drag.clientY) / Math.max(1, bounds.height)) *
            viewportHeight;

        pendingViewCenter.current = {
            x: drag.centerX - deltaX,
            y: drag.centerY - deltaY,
        };

        if (panAnimationFrame.current === null) {
            panAnimationFrame.current = window.requestAnimationFrame(() => {
                panAnimationFrame.current = null;

                if (pendingViewCenter.current) {
                    setViewCenter(pendingViewCenter.current);
                    pendingViewCenter.current = null;
                }
            });
        }
    };

    const handlePointerUp = (event: ReactPointerEvent<SVGSVGElement>) => {
        if (dragState.current?.pointerId === event.pointerId) {
            dragState.current = null;

            if (event.currentTarget.hasPointerCapture(event.pointerId)) {
                event.currentTarget.releasePointerCapture(event.pointerId);
            }
        }
    };

    const handleWheel = (event: ReactWheelEvent<SVGSVGElement>) => {
        event.preventDefault();

        const bounds = event.currentTarget.getBoundingClientRect();
        const pointerX = clamp(
            (event.clientX - bounds.left) / Math.max(1, bounds.width),
            0,
            1,
        );
        const pointerY = clamp(
            (event.clientY - bounds.top) / Math.max(1, bounds.height),
            0,
            1,
        );
        const nextView = zoomSecondBrainAtPoint({
            zoom,
            viewCenter,
            pointerX,
            pointerY,
            zoomDelta:
                event.deltaY < 0 ? brainWheelZoomStep : -brainWheelZoomStep,
            minimumZoom: minimumBrainZoom,
            maximumZoom: maximumBrainZoom,
        });

        setZoom(nextView.zoom);
        setViewCenter(nextView.viewCenter);
    };

    const resetView = () => {
        if (panAnimationFrame.current !== null) {
            window.cancelAnimationFrame(panAnimationFrame.current);
            panAnimationFrame.current = null;
        }

        pendingViewCenter.current = null;
        setZoom(1);
        setViewCenter({
            x: secondBrainWidth / 2,
            y: secondBrainHeight / 2,
        });
    };

    return (
        <section
            aria-label={`${view.label} knowledge map`}
            className="relative min-h-0 min-w-0 overflow-hidden bg-[radial-gradient(circle_at_50%_48%,rgba(30,111,171,0.22),transparent_44%),linear-gradient(180deg,#081522_0%,#06101b_100%)]"
        >
            <div className="pointer-events-none absolute inset-0 [background-image:linear-gradient(rgba(82,148,196,0.045)_1px,transparent_1px),linear-gradient(90deg,rgba(82,148,196,0.045)_1px,transparent_1px)] [background-size:42px_42px] opacity-35" />

            <div className="absolute top-3 left-3 z-20 flex max-w-[calc(100%-1.5rem)] items-center gap-1 rounded-lg border border-sky-900/65 bg-[#091725]/92 p-1 shadow-[0_10px_30px_rgba(0,0,0,0.25)] backdrop-blur-md">
                <label className="relative min-w-0">
                    <span className="sr-only">
                        Category shown in pane {paneIndex + 1}
                    </span>
                    <select
                        value={view.key}
                        onChange={(event) =>
                            onCategoryChange(event.target.value)
                        }
                        className={cn(
                            'h-7 appearance-none rounded-md bg-transparent pr-7 pl-2 text-[10px] font-medium text-sky-100 transition outline-none hover:bg-sky-300/8 focus-visible:ring-1 focus-visible:ring-sky-400/70',
                            compact ? 'max-w-32' : 'max-w-48',
                        )}
                    >
                        {categoryViews.map((categoryView) => (
                            <option
                                key={categoryView.key}
                                value={categoryView.key}
                                disabled={
                                    categoryView.key !== view.key &&
                                    selectedCategoryKeys.includes(
                                        categoryView.key,
                                    )
                                }
                            >
                                {categoryView.label}
                            </option>
                        ))}
                    </select>
                    <ChevronDown className="pointer-events-none absolute top-1/2 right-2 size-3 -translate-y-1/2 text-sky-300/45" />
                </label>
                <label className="relative flex shrink-0 items-center border-l border-sky-900/65 pl-1">
                    <span
                        className={cn(
                            'pl-1 text-[8px] font-medium tracking-[0.08em] text-sky-200/40 uppercase',
                            compact && 'sr-only',
                        )}
                    >
                        Depth
                    </span>
                    <select
                        value={relationshipDepth}
                        aria-label={`Relationship depth in pane ${paneIndex + 1}`}
                        title={`Show relationship hops from ${validSelectedNodeId ? (nodeById.get(validSelectedNodeId)?.label ?? 'the selected node') : 'the graph root'}`}
                        onChange={(event) =>
                            onRelationshipDepthChange(
                                event.target.value === 'all'
                                    ? 'all'
                                    : (Number(
                                          event.target.value,
                                      ) as BrainGraphDepth),
                            )
                        }
                        className="h-7 appearance-none rounded-md bg-transparent pr-6 pl-1.5 text-[9px] font-medium text-sky-100 transition outline-none hover:bg-sky-300/8 focus-visible:ring-1 focus-visible:ring-sky-400/70"
                    >
                        {relationshipDepthOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <ChevronDown className="pointer-events-none absolute top-1/2 right-1.5 size-3 -translate-y-1/2 text-sky-300/45" />
                </label>
                {!compact && (
                    <span className="shrink-0 border-l border-sky-900/65 px-2 text-[8px] text-sky-200/40 tabular-nums">
                        {relationshipDepth === 'all'
                            ? `${graph.summary.snippets} files`
                            : `${visibleGraph.nodes.length}/${graph.nodes.length} nodes`}
                    </span>
                )}
            </div>

            <svg
                viewBox={viewBox}
                role="application"
                aria-label={`Interactive map of ${view.label}`}
                onClick={() => setSelectedNodeId(null)}
                onPointerDown={handlePointerDown}
                onPointerMove={handlePointerMove}
                onPointerUp={handlePointerUp}
                onPointerCancel={handlePointerUp}
                onWheel={handleWheel}
                className="absolute inset-0 size-full cursor-grab touch-none select-none active:cursor-grabbing"
            >
                <defs>
                    <filter
                        id={glowId}
                        x="-200%"
                        y="-200%"
                        width="400%"
                        height="400%"
                    >
                        <feGaussianBlur
                            stdDeviation={4 * nodeZoomScale}
                            result="blur"
                        />
                        <feMerge>
                            <feMergeNode in="blur" />
                            <feMergeNode in="SourceGraphic" />
                        </feMerge>
                    </filter>
                </defs>

                <g aria-hidden="true" pointerEvents="none">
                    {edgePaths.map((edgePath) => (
                        <path
                            key={edgePath.id}
                            d={edgePath.data}
                            fill="none"
                            style={{ opacity: edgePath.opacity }}
                            className={cn(
                                'second-brain-edge motion-reduce:transition-none',
                                edgePalette[edgePath.kind],
                            )}
                            strokeWidth={edgePath.strokeWidth}
                            strokeDasharray={edgePath.strokeDasharray}
                            strokeLinecap="round"
                            vectorEffect="non-scaling-stroke"
                        />
                    ))}
                    {signals.map((signal) => (
                        <circle
                            key={signal.id}
                            r={2.15}
                            opacity={signal.opacity}
                            className="second-brain-signal fill-white"
                        >
                            <animateMotion
                                path={signal.data}
                                dur={`${signal.duration}s`}
                                begin={`${signal.delay}s`}
                                repeatCount="indefinite"
                                calcMode="linear"
                                keyPoints="0;0;1;1"
                                keyTimes="0;0.74;0.92;1"
                            />
                        </circle>
                    ))}
                    {edgeFlickers.map((flicker) => (
                        <path
                            key={flicker.id}
                            d={flicker.data}
                            fill="none"
                            style={flicker.style}
                            className="second-brain-edge-flicker stroke-sky-100"
                            strokeWidth={1.7}
                            strokeLinecap="round"
                            vectorEffect="non-scaling-stroke"
                        />
                    ))}
                </g>

                <g>
                    {visibleGraph.nodes.map((node) => {
                        const position = positions.get(node.id) ?? node;
                        const isFocused = focusNodeId === node.id;
                        const isSelected = validSelectedNodeId === node.id;
                        const isNeighbour = focusedNeighbours.has(node.id);
                        const isMatch = matchingNodeIds.has(node.id);
                        const hasQuery = normalizedQuery.length > 0;
                        const opacity = nodeOpacity({
                            hasFocus: focusNodeId !== null,
                            isFocused,
                            isNeighbour,
                            hasQuery,
                            isMatch,
                        });
                        const scale = isFocused
                            ? 1.65
                            : isNeighbour
                              ? 1.28
                              : isMatch
                                ? 1.2
                                : 1;
                        const nodeRadius = node.size * scale * nodeZoomScale;
                        const labelOffset =
                            (isFocused ? 16 : 12) * nodeZoomScale;
                        const labelFontSize =
                            (isFocused ? 10 : 9) * nodeZoomScale;
                        const label = shortLabel(node.label, compact ? 22 : 28);
                        const labelPaddingX = 4 * nodeZoomScale;
                        const labelPaddingY = 2 * nodeZoomScale;
                        const labelWidth = Math.max(
                            labelFontSize * 2.4,
                            label.length * labelFontSize * 0.68 +
                                labelPaddingX * 2,
                        );
                        const labelHeight = labelFontSize + labelPaddingY * 2;
                        const labelCenterY =
                            nodeRadius + labelOffset + labelHeight / 2;
                        const palette = nodePalette[node.kind];
                        const showLabel =
                            isFocused ||
                            isSelected ||
                            isNeighbour ||
                            isMatch ||
                            [
                                'root',
                                'category',
                                'collection',
                                'project',
                            ].includes(node.kind);
                        const sway = brainNodeSway(node);
                        const style: GraphCustomProperties = {
                            ...sway,
                            opacity,
                        };

                        return (
                            <g
                                key={node.id}
                                ref={(element) => {
                                    if (element) {
                                        nodeElements.current.set(
                                            node.id,
                                            element,
                                        );
                                    } else {
                                        nodeElements.current.delete(node.id);
                                    }
                                }}
                                data-brain-node={node.id}
                                role="button"
                                tabIndex={
                                    node.id === (validSelectedNodeId ?? 'root')
                                        ? 0
                                        : -1
                                }
                                aria-label={`${brainNodeKindLabel(node.kind)}: ${node.label}. ${node.description}`}
                                aria-pressed={isSelected}
                                style={style}
                                transform={`translate(${position.x} ${position.y})`}
                                onMouseEnter={() => setHoveredNodeId(node.id)}
                                onMouseLeave={() => setHoveredNodeId(null)}
                                onFocus={() => setHoveredNodeId(node.id)}
                                onBlur={() => setHoveredNodeId(null)}
                                onClick={(event) => {
                                    event.stopPropagation();
                                    selectNode(node.id);
                                }}
                                onDoubleClick={(event) => {
                                    event.stopPropagation();
                                    openNode(node);
                                }}
                                onKeyDown={(event) =>
                                    handleNodeKeyDown(event, node)
                                }
                                className={cn(
                                    'second-brain-node cursor-pointer outline-none motion-reduce:transition-none',
                                    sway &&
                                        !isFocused &&
                                        !isSelected &&
                                        'second-brain-node--sway',
                                )}
                            >
                                <title>
                                    {node.label} · {node.description}
                                </title>
                                <circle
                                    r={
                                        Math.max(16, node.size * 2.1) *
                                        nodeZoomScale
                                    }
                                    className="fill-transparent"
                                />
                                {(isFocused || isSelected) && (
                                    <circle
                                        r={node.size * 2.05 * nodeZoomScale}
                                        className="fill-sky-300/10 stroke-sky-200/45"
                                        strokeWidth={1}
                                    />
                                )}
                                <circle
                                    r={nodeRadius}
                                    filter={
                                        isFocused
                                            ? `url(#${glowId})`
                                            : undefined
                                    }
                                    className={cn(
                                        palette.fill,
                                        palette.stroke,
                                        'transition-[r,fill,stroke] duration-300 ease-out motion-reduce:transition-none',
                                    )}
                                    strokeWidth={
                                        isFocused || isSelected ? 1.5 : 0.65
                                    }
                                />
                                {showLabel && (
                                    <g pointerEvents="none">
                                        <rect
                                            x={-labelWidth / 2}
                                            y={labelCenterY - labelHeight / 2}
                                            width={labelWidth}
                                            height={labelHeight}
                                            rx={labelHeight / 2}
                                            className="fill-[#07111d]/90"
                                        />
                                        <text
                                            y={labelCenterY}
                                            textAnchor="middle"
                                            dominantBaseline="middle"
                                            style={{
                                                fontSize: `${labelFontSize}px`,
                                            }}
                                            className={cn(
                                                'fill-current text-[9px] font-medium tracking-[0.01em]',
                                                palette.label,
                                                isFocused &&
                                                    'text-[10px] font-semibold',
                                            )}
                                        >
                                            {label}
                                        </text>
                                    </g>
                                )}
                            </g>
                        );
                    })}
                </g>
            </svg>

            {validSelectedNodeId && (
                <BrainSelectionPanel
                    node={nodeById.get(validSelectedNodeId)!}
                    view={view}
                    width={displayedSelectionPanelWidth}
                    minWidth={compact ? 240 : brainSelectionPanelMinWidth}
                    maxWidth={compact ? 420 : brainSelectionPanelMaxWidth}
                    onClose={() => setSelectedNodeId(null)}
                    onSelectNode={selectNode}
                    onResize={setSelectionPanelWidth}
                    onResizeEnd={persistSelectionPanelWidth}
                />
            )}

            <div className="absolute bottom-3 left-3 z-20 flex items-center gap-0.5 rounded-lg border border-sky-900/60 bg-[#091725]/90 p-0.5 shadow-[0_12px_35px_rgba(0,0,0,0.22)]">
                <button
                    type="button"
                    aria-label={`Zoom out ${view.label}`}
                    onClick={() =>
                        setZoom((current) =>
                            clamp(
                                current - brainZoomStep,
                                minimumBrainZoom,
                                maximumBrainZoom,
                            ),
                        )
                    }
                    className="rounded-md p-1.5 text-sky-200/50 transition hover:bg-sky-300/10 hover:text-sky-100 focus-visible:outline-2 focus-visible:outline-sky-400"
                >
                    <ZoomOut className="size-3.5" />
                </button>
                {!compact && (
                    <span className="min-w-9 text-center text-[8px] text-sky-200/40 tabular-nums">
                        {Math.round(zoom * 100)}%
                    </span>
                )}
                <button
                    type="button"
                    aria-label={`Zoom in ${view.label}`}
                    title="Zoom in to inspect individual connections"
                    onClick={() =>
                        setZoom((current) =>
                            clamp(
                                current + brainZoomStep,
                                minimumBrainZoom,
                                maximumBrainZoom,
                            ),
                        )
                    }
                    className="rounded-md p-1.5 text-sky-200/50 transition hover:bg-sky-300/10 hover:text-sky-100 focus-visible:outline-2 focus-visible:outline-sky-400"
                >
                    <ZoomIn className="size-3.5" />
                </button>
                <div className="mx-0.5 h-3.5 w-px bg-sky-800/50" />
                <button
                    type="button"
                    aria-label={`Fit ${view.label} graph to view`}
                    onClick={resetView}
                    className="rounded-md p-1.5 text-sky-200/50 transition hover:bg-sky-300/10 hover:text-sky-100 focus-visible:outline-2 focus-visible:outline-sky-400"
                >
                    <Maximize2 className="size-3.5" />
                </button>
            </div>

            {!compact && !validSelectedNodeId && (
                <div className="pointer-events-none absolute bottom-4 left-1/2 hidden -translate-x-1/2 items-center gap-4 text-[8px] tracking-[0.1em] text-sky-200/35 uppercase md:flex">
                    <LegendDot className="bg-cyan-400" label="Workspace" />
                    <LegendDot className="bg-blue-300" label="Folder" />
                    <LegendDot className="bg-slate-200" label="File" />
                    <LegendDot className="bg-indigo-400" label="Context" />
                </div>
            )}
        </section>
    );
}

function BrainSelectionPanel({
    node,
    view,
    width,
    minWidth,
    maxWidth,
    onClose,
    onSelectNode,
    onResize,
    onResizeEnd,
}: {
    node: BrainNode;
    view: BrainCategoryView;
    width: number;
    minWidth: number;
    maxWidth: number;
    onClose: () => void;
    onSelectNode: (nodeId: string) => void;
    onResize: (width: number) => void;
    onResizeEnd: (width: number) => void;
}) {
    const Icon = nodeIcons[node.kind];
    const selectedSnippet = resolveBrainSnippet(node, view);
    const variation = selectedSnippet
        ? (selectedSnippet.variations.find(
              (candidate) => candidate.is_default,
          ) ?? selectedSnippet.variations[0])
        : undefined;
    const fileBrowserItems = useMemo(
        () => buildBrainFileBrowser(node, view),
        [node, view],
    );
    const parent = buildBrainParent(node, view);

    return (
        <aside
            id="second-brain-selection-panel"
            aria-label={`${node.label} details`}
            style={{ width: `${width}px`, maxWidth: 'calc(100% - 0.75rem)' }}
            className="absolute inset-y-0 right-0 z-30 flex min-w-0 flex-col border-l border-sky-700/60 bg-[#071321]/97 shadow-[-18px_0_42px_rgba(0,0,0,0.34)] backdrop-blur-md"
        >
            <WorkspaceResizeHandle
                label="Resize selected item details"
                controls="second-brain-selection-panel"
                side="right"
                width={width}
                minWidth={minWidth}
                maxWidth={maxWidth}
                onResize={onResize}
                onResizeEnd={onResizeEnd}
                className="absolute inset-y-0 left-0 z-40"
            />
            <header className="flex shrink-0 items-start gap-2.5 border-b border-sky-900/70 px-3 py-3">
                <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-sky-300/8 text-sky-300/75">
                    <Icon className="size-4" />
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block text-[8px] font-semibold tracking-[0.14em] text-sky-300/45 uppercase">
                        {node.eyebrow}
                    </span>
                    <span className="mt-0.5 block truncate text-[12px] font-medium text-sky-50">
                        {node.label}
                    </span>
                    <span className="mt-1 block text-[9px] leading-4 text-sky-100/45">
                        {node.description}
                    </span>
                </span>
                <button
                    type="button"
                    aria-label={`Close ${node.label} details`}
                    title="Close details"
                    onClick={onClose}
                    className="grid size-7 shrink-0 place-items-center rounded-md text-sky-200/45 transition hover:bg-sky-300/10 hover:text-sky-50 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-400"
                >
                    <X className="size-3.5" />
                </button>
            </header>

            {parent && (
                <button
                    type="button"
                    aria-label={`Back to ${parent.label}`}
                    onClick={() => onSelectNode(parent.id)}
                    className="focus-visible:outline-inset flex shrink-0 items-center gap-1.5 border-b border-sky-900/55 px-3 py-2 text-left text-[9px] text-sky-200/60 transition hover:bg-sky-300/8 hover:text-sky-50 focus-visible:outline-2 focus-visible:outline-sky-400"
                >
                    <ChevronLeft className="size-3.5 shrink-0" />
                    <span className="truncate">Back to {parent.label}</span>
                </button>
            )}

            {selectedSnippet && variation ? (
                <section className="flex min-h-0 flex-1 flex-col">
                    <div className="flex shrink-0 items-center gap-2 border-b border-sky-900/55 px-3 py-2 text-[9px]">
                        <FileCode2 className="size-3.5 text-sky-300/65" />
                        <span className="min-w-0 flex-1 truncate font-mono text-sky-100/75">
                            {selectedSnippet.filename}
                        </span>
                        <span className="rounded bg-sky-300/8 px-1.5 py-0.5 text-[8px] tracking-[0.08em] text-sky-200/55 uppercase">
                            {selectedSnippet.language}
                        </span>
                    </div>
                    <SyntaxHighlightedCode
                        source={variation.content}
                        language={selectedSnippet.language}
                        ariaLabel={`${selectedSnippet.filename} source code`}
                        className="h-full max-h-none flex-1 bg-[#06101b]"
                    />
                </section>
            ) : (
                <section className="flex min-h-0 flex-1 flex-col">
                    <div className="flex shrink-0 items-center gap-2 border-b border-sky-900/55 px-3 py-2">
                        <FolderTree className="size-3.5 text-sky-300/65" />
                        <span className="text-[8px] font-semibold tracking-[0.14em] text-sky-200/45 uppercase">
                            Contents
                        </span>
                    </div>
                    {fileBrowserItems.length > 0 ? (
                        <BrainFileBrowser
                            items={fileBrowserItems}
                            onSelectNode={onSelectNode}
                        />
                    ) : (
                        <p className="px-3 py-4 text-[10px] leading-5 text-sky-100/45">
                            No files are connected to this item yet.
                        </p>
                    )}
                </section>
            )}
        </aside>
    );
}

function BrainFileBrowser({
    items,
    onSelectNode,
    depth = 0,
}: {
    items: BrainFileBrowserItem[];
    onSelectNode: (nodeId: string) => void;
    depth?: number;
}) {
    return (
        <div className="min-h-0 flex-1 overflow-auto py-1.5">
            {items.map((item) => {
                const Icon =
                    item.kind === 'snippet'
                        ? FileCode2
                        : item.kind === 'folder'
                          ? Folder
                          : item.kind === 'collection'
                            ? FolderTree
                            : Braces;

                return (
                    <div key={item.id}>
                        <button
                            type="button"
                            aria-label={`Select ${item.kind}: ${item.label}`}
                            onClick={() => onSelectNode(item.id)}
                            style={{ paddingLeft: `${12 + depth * 14}px` }}
                            className="focus-visible:outline-inset flex min-h-7 w-full items-center gap-1.5 pr-3 text-left text-[10px] text-sky-100/65 transition hover:bg-sky-300/8 hover:text-sky-50 focus-visible:outline-2 focus-visible:outline-sky-400"
                        >
                            {item.children.length > 0 ? (
                                <ChevronDown className="size-3 shrink-0 text-sky-300/40" />
                            ) : (
                                <span className="w-3 shrink-0" />
                            )}
                            <Icon className="size-3.5 shrink-0 text-sky-300/55" />
                            <span className="min-w-0 flex-1 truncate">
                                {item.label}
                            </span>
                            {item.detail && (
                                <span className="shrink-0 truncate text-[8px] text-sky-200/35">
                                    {item.detail}
                                </span>
                            )}
                        </button>
                        {item.children.length > 0 && (
                            <BrainFileBrowser
                                items={item.children}
                                onSelectNode={onSelectNode}
                                depth={depth + 1}
                            />
                        )}
                    </div>
                );
            })}
        </div>
    );
}

function LegendDot({ className, label }: { className: string; label: string }) {
    return (
        <span className="flex items-center gap-1.5">
            <span className={cn('size-1.5 rounded-full', className)} />
            {label}
        </span>
    );
}

function buildBrainParent(
    node: BrainNode,
    view: BrainCategoryView,
): { id: string; label: string } | null {
    const projectById = new Map(
        view.projects.map((project) => [project.id, project]),
    );

    if (node.kind === 'snippet') {
        const snippet = resolveBrainSnippet(node, view);

        if (!snippet) {
            return null;
        }

        if (snippet.project_id === null) {
            return { id: 'collection:standalone', label: 'Standalone files' };
        }

        const project = projectById.get(snippet.project_id);

        if (!project) {
            return null;
        }

        if (
            snippet.folder_id !== null &&
            project.folders.some((folder) => folder.id === snippet.folder_id)
        ) {
            const folder = project.folders.find(
                (candidate) => candidate.id === snippet.folder_id,
            );

            return folder
                ? { id: `folder:${folder.id}`, label: folder.name }
                : null;
        }

        return { id: `project:${project.id}`, label: project.name };
    }

    if (node.kind === 'folder') {
        const folderId = Number(node.id.replace('folder:', ''));
        const project = view.projects.find((candidate) =>
            candidate.folders.some((folder) => folder.id === folderId),
        );
        const folder = project?.folders.find(
            (candidate) => candidate.id === folderId,
        );

        if (!project || !folder) {
            return null;
        }

        if (
            folder.parent_id !== null &&
            project.folders.some(
                (candidate) => candidate.id === folder.parent_id,
            )
        ) {
            const parentFolder = project.folders.find(
                (candidate) => candidate.id === folder.parent_id,
            );

            return parentFolder
                ? { id: `folder:${parentFolder.id}`, label: parentFolder.name }
                : null;
        }

        return { id: `project:${project.id}`, label: project.name };
    }

    if (node.kind === 'project') {
        const project = projectById.get(
            Number(node.id.replace('project:', '')),
        );

        if (!project) {
            return { id: 'root', label: 'Second brain' };
        }

        if (project.library_category_id !== null) {
            const category = view.libraryCategories.find(
                (candidate) => candidate.id === project.library_category_id,
            );

            if (category) {
                return { id: `category:${category.id}`, label: category.name };
            }
        }
    }

    return node.kind === 'root' ? null : { id: 'root', label: 'Second brain' };
}

function resolveBrainSnippet(
    node: BrainNode,
    view: BrainCategoryView,
): Snippet | undefined {
    if (node.kind !== 'snippet') {
        return undefined;
    }

    return allBrainSnippets(view).find(
        (snippet) => node.id === `snippet:${snippet.id}`,
    );
}

function buildBrainFileBrowser(
    node: BrainNode,
    view: BrainCategoryView,
): BrainFileBrowserItem[] {
    const projectById = new Map(
        view.projects.map((project) => [project.id, project]),
    );
    const allSnippets = allBrainSnippets(view);
    const projectItem = (project: SnippetProject): BrainFileBrowserItem => ({
        id: `project:${project.id}`,
        kind: 'project',
        label: project.name,
        detail: project.kind,
        children: brainProjectContents(project),
    });
    const matchingSnippets = (
        predicate: (snippet: Snippet) => boolean,
    ): BrainFileBrowserItem[] =>
        allSnippets
            .filter(predicate)
            .sort((left, right) => left.filename.localeCompare(right.filename))
            .map((snippet) => brainSnippetItem(snippet));

    if (node.kind === 'project') {
        const project = projectById.get(
            Number(node.id.replace('project:', '')),
        );

        return project ? brainProjectContents(project) : [];
    }

    if (node.kind === 'collection') {
        return view.standaloneSnippets
            .slice()
            .sort((left, right) => left.filename.localeCompare(right.filename))
            .map((snippet) => brainSnippetItem(snippet));
    }

    if (node.kind === 'folder') {
        const folderId = Number(node.id.replace('folder:', ''));
        const project = view.projects.find((candidate) =>
            candidate.folders.some((folder) => folder.id === folderId),
        );

        return project ? brainProjectContents(project, folderId) : [];
    }

    if (node.kind === 'category') {
        const categoryId = node.id.startsWith('category:')
            ? Number(node.id.replace('category:', ''))
            : null;
        const categoryProjects = view.projects.filter((project) =>
            Number.isInteger(categoryId)
                ? project.library_category_id === categoryId
                : project.library_category_id === null,
        );

        return categoryProjects.map((project) => projectItem(project));
    }

    if (node.kind === 'tag') {
        return matchingSnippets((snippet) =>
            snippet.tags.some((tag) => node.id === `tag:${tag.id}`),
        );
    }

    if (node.kind === 'framework') {
        return matchingSnippets(
            (snippet) =>
                snippet.frameworks.some(
                    (framework) => node.id === `framework:${framework.id}`,
                ) ||
                (snippet.project_id !== null &&
                    projectById
                        .get(snippet.project_id)
                        ?.frameworks.some(
                            (framework) =>
                                node.id === `framework:${framework.id}`,
                        ) === true),
        );
    }

    if (node.kind === 'language') {
        return matchingSnippets(
            (snippet) => node.id === `language:${snippet.language}`,
        );
    }

    const projectItems = view.projects
        .slice()
        .sort((left, right) => left.name.localeCompare(right.name))
        .map((project) => projectItem(project));

    if (view.standaloneSnippets.length > 0) {
        projectItems.push({
            id: 'collection:standalone',
            kind: 'collection',
            label: 'Standalone files',
            children: view.standaloneSnippets
                .slice()
                .sort((left, right) =>
                    left.filename.localeCompare(right.filename),
                )
                .map((snippet) => brainSnippetItem(snippet)),
        });
    }

    return projectItems;
}

function allBrainSnippets(view: BrainCategoryView): Snippet[] {
    return [
        ...view.standaloneSnippets,
        ...view.projects.flatMap((project) => project.snippets),
    ];
}

function brainProjectContents(
    project: SnippetProject,
    parentFolderId: number | null = null,
    ancestry = new Set<number>(),
): BrainFileBrowserItem[] {
    const foldersById = new Map(
        project.folders.map((folder) => [folder.id, folder]),
    );
    const normalisedParentId = (folder: SnippetFolder): number | null =>
        folder.parent_id !== null && foldersById.has(folder.parent_id)
            ? folder.parent_id
            : null;
    const childFolders = project.folders
        .filter((folder) => normalisedParentId(folder) === parentFolderId)
        .sort((left, right) => left.name.localeCompare(right.name));
    const directSnippets = project.snippets
        .filter((snippet) => {
            const snippetFolderId =
                snippet.folder_id !== null && foldersById.has(snippet.folder_id)
                    ? snippet.folder_id
                    : null;

            return snippetFolderId === parentFolderId;
        })
        .sort((left, right) => left.filename.localeCompare(right.filename));

    return [
        ...childFolders.map((folder) => ({
            id: `folder:${folder.id}`,
            kind: 'folder' as const,
            label: folder.name,
            detail: `${brainFolderFileCount(project, folder.id)} files`,
            children: ancestry.has(folder.id)
                ? []
                : brainProjectContents(
                      project,
                      folder.id,
                      new Set(ancestry).add(folder.id),
                  ),
        })),
        ...directSnippets.map((snippet) => brainSnippetItem(snippet)),
    ];
}

function brainFolderFileCount(
    project: SnippetProject,
    folderId: number,
): number {
    const folderById = new Map(
        project.folders.map((folder) => [folder.id, folder]),
    );
    const nestedFolderIds = new Set<number>([folderId]);
    const folderQueue = [folderId];

    for (let index = 0; index < folderQueue.length; index += 1) {
        project.folders.forEach((folder) => {
            if (
                folder.parent_id === folderQueue[index] &&
                !nestedFolderIds.has(folder.id) &&
                folderById.has(folder.id)
            ) {
                nestedFolderIds.add(folder.id);
                folderQueue.push(folder.id);
            }
        });
    }

    return project.snippets.filter(
        (snippet) =>
            snippet.folder_id !== null &&
            nestedFolderIds.has(snippet.folder_id),
    ).length;
}

function brainSnippetItem(snippet: Snippet): BrainFileBrowserItem {
    return {
        id: `snippet:${snippet.id}`,
        kind: 'snippet',
        label: snippet.filename,
        detail: snippet.language,
        children: [],
    };
}

function availableLayout(
    requestedLayout: BrainLayout,
    categoryCount: number,
): BrainLayout {
    if (requestedLayout === 'quad' && categoryCount < 4) {
        return categoryCount >= 2 ? 'split' : 'single';
    }

    if (requestedLayout === 'split' && categoryCount < 2) {
        return 'single';
    }

    return requestedLayout;
}

function buildBrainEdgePaths({
    edges,
    nodes,
    positions,
    focusNodeId,
    matchingNodeIds,
    hasQuery,
}: {
    edges: BrainEdge[];
    nodes: ReadonlyMap<string, BrainNode>;
    positions: ReadonlyMap<string, BrainPosition>;
    focusNodeId: string | null;
    matchingNodeIds: ReadonlySet<string>;
    hasQuery: boolean;
}): BrainEdgePath[] {
    type MutableBrainEdgePath = Omit<BrainEdgePath, 'data'> & {
        commands: string[];
    };

    const pathGroups = new Map<string, MutableBrainEdgePath>();
    const hasFocus = focusNodeId !== null;

    edges.forEach((edge) => {
        const source = positions.get(edge.source);
        const target = positions.get(edge.target);

        if (!source || !target) {
            return;
        }

        const touchesFocus =
            hasFocus &&
            (edge.source === focusNodeId || edge.target === focusNodeId);
        const touchesMatch =
            matchingNodeIds.has(edge.source) ||
            matchingNodeIds.has(edge.target);
        const emphasized = hasFocus ? touchesFocus : hasQuery && touchesMatch;
        const connectedSnippets = [
            nodes.get(edge.source),
            nodes.get(edge.target),
        ].filter((node): node is BrainNode => node?.kind === 'snippet');
        const connectionStrength = Math.max(
            0,
            ...connectedSnippets.map((node) => node.connectionStrength ?? 0),
        );
        const isFavouriteConnection = connectedSnippets.some(
            (node) => node.isFavourite === true,
        );
        const strengthTier =
            connectionStrength >= 0.67
                ? 'strong'
                : connectionStrength >= 0.34
                  ? 'medium'
                  : 'light';
        const groupId = `${edge.kind}:${emphasized ? 'emphasized' : 'base'}:${strengthTier}:${isFavouriteConnection ? 'favourite' : 'standard'}`;
        const existingGroup = pathGroups.get(groupId);
        const pathGroup =
            existingGroup ??
            ({
                id: groupId,
                kind: edge.kind,
                opacity: edgeOpacity({
                    edgeKind: edge.kind,
                    hasFocus,
                    touchesFocus,
                    hasQuery,
                    touchesMatch,
                }),
                strokeWidth:
                    (touchesFocus
                        ? 1.8
                        : edge.kind === 'hierarchy'
                          ? 1.05
                          : 0.9) +
                    connectionStrength * 0.7 +
                    (isFavouriteConnection ? 0.65 : 0),
                strokeDasharray: isFavouriteConnection ? '11 5' : undefined,
                emphasized,
                commands: [],
            } satisfies MutableBrainEdgePath);

        pathGroup.commands.push(
            `M ${roundBrainCoordinate(source.x)} ${roundBrainCoordinate(source.y)} L ${roundBrainCoordinate(target.x)} ${roundBrainCoordinate(target.y)}`,
        );

        if (!existingGroup) {
            pathGroups.set(groupId, pathGroup);
        }
    });

    return [...pathGroups.values()]
        .sort(
            (left, right) => Number(left.emphasized) - Number(right.emphasized),
        )
        .map(({ commands, ...path }) => ({
            ...path,
            data: commands.join(' '),
        }));
}

function buildBrainSignals(
    edges: BrainEdge[],
    positions: ReadonlyMap<string, BrainPosition>,
): BrainSignal[] {
    return edges
        .map((edge) => {
            const source = positions.get(edge.source);
            const target = positions.get(edge.target);
            const hash = brainMotionHash(edge.id);

            if (!source || !target || hash % 3 !== 0) {
                return null;
            }

            return {
                id: `signal:${edge.id}`,
                data: `M ${roundBrainCoordinate(source.x)} ${roundBrainCoordinate(source.y)} L ${roundBrainCoordinate(target.x)} ${roundBrainCoordinate(target.y)}`,
                delay: (hash % 48) / 10,
                duration: 6.5 + ((hash >> 4) % 30) / 10,
                opacity: edge.kind === 'hierarchy' ? 0.8 : 0.68,
            } satisfies BrainSignal;
        })
        .filter((signal): signal is BrainSignal => signal !== null)
        .slice(0, 12);
}

function buildBrainEdgeFlickers(
    edges: BrainEdge[],
    positions: ReadonlyMap<string, BrainPosition>,
): BrainEdgeFlicker[] {
    return edges
        .map((edge) => ({ edge, hash: brainMotionHash(edge.id) }))
        .sort((left, right) => left.hash - right.hash)
        .slice(0, Math.min(8, Math.max(1, Math.ceil(edges.length / 10))))
        .flatMap(({ edge, hash }) => {
            const source = positions.get(edge.source);
            const target = positions.get(edge.target);

            if (!source || !target) {
                return [];
            }

            return [
                {
                    id: `flicker:${edge.id}`,
                    data: `M ${roundBrainCoordinate(source.x)} ${roundBrainCoordinate(source.y)} L ${roundBrainCoordinate(target.x)} ${roundBrainCoordinate(target.y)}`,
                    style: {
                        '--brain-flicker-delay': `-${(hash % 90) / 10}s`,
                        '--brain-flicker-duration': `${8 + ((hash >> 5) % 35) / 10}s`,
                    },
                } satisfies BrainEdgeFlicker,
            ];
        });
}

function brainNodeSway(node: BrainNode): GraphCustomProperties | null {
    if (
        !['folder', 'snippet', 'tag', 'framework', 'language'].includes(
            node.kind,
        )
    ) {
        return null;
    }

    const hash = brainMotionHash(node.id);

    if (hash % 3 !== 0) {
        return null;
    }

    const horizontalDistance = 1.5 + (hash % 20) / 10;
    const verticalDistance = 1.2 + ((hash >> 4) % 16) / 10;

    return {
        '--brain-sway-x': `${hash % 2 === 0 ? horizontalDistance : -horizontalDistance}px`,
        '--brain-sway-y': `${hash % 4 < 2 ? verticalDistance : -verticalDistance}px`,
        '--brain-sway-delay': `-${(hash % 70) / 10}s`,
        '--brain-sway-duration': `${7 + ((hash >> 7) % 30) / 10}s`,
    };
}

function brainMotionHash(value: string): number {
    let hash = 0;

    for (let index = 0; index < value.length; index += 1) {
        hash = (hash * 31 + value.charCodeAt(index)) >>> 0;
    }

    return hash;
}

function roundBrainCoordinate(value: number): number {
    return Math.round(value * 10) / 10;
}

function edgeOpacity({
    edgeKind,
    hasFocus,
    touchesFocus,
    hasQuery,
    touchesMatch,
}: {
    edgeKind: BrainEdgeKind;
    hasFocus: boolean;
    touchesFocus: boolean;
    hasQuery: boolean;
    touchesMatch: boolean;
}): number {
    if (hasFocus) {
        return touchesFocus ? 0.92 : edgeKind === 'hierarchy' ? 0.14 : 0.06;
    }

    if (hasQuery) {
        return touchesMatch ? 0.72 : edgeKind === 'hierarchy' ? 0.24 : 0.1;
    }

    return edgeKind === 'hierarchy' ? 0.42 : 0.22;
}

function nodeOpacity({
    hasFocus,
    isFocused,
    isNeighbour,
    hasQuery,
    isMatch,
}: {
    hasFocus: boolean;
    isFocused: boolean;
    isNeighbour: boolean;
    hasQuery: boolean;
    isMatch: boolean;
}): number {
    if (hasFocus) {
        return isFocused || isNeighbour ? 1 : 0.22;
    }

    if (hasQuery) {
        return isMatch ? 1 : 0.14;
    }

    return 0.86;
}

function shortLabel(label: string, maximumLength: number): string {
    return label.length > maximumLength
        ? `${label.slice(0, maximumLength - 2)}…`
        : label;
}

function clamp(value: number, minimum: number, maximum: number): number {
    return Math.min(maximum, Math.max(minimum, value));
}

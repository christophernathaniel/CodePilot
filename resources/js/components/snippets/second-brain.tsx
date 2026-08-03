import {
    ArrowUpRight,
    BrainCircuit,
    Braces,
    Boxes,
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
import {
    brainNodeKindLabel,
    buildBrainAdjacency,
    buildSecondBrainCategoryViews,
    buildSecondBrainGraph,
    filterSecondBrainGraphByDepth,
    findDirectionalBrainNode,
    focusedBrainPositions,
    resolveBrainCategorySelections,
    secondBrainHeight,
    secondBrainWidth,
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
import { cn } from '@/lib/utils';
import type { LibraryCategory, Snippet, SnippetProject } from '@/types';

type Props = {
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

type BrainLayout = 'single' | 'split' | 'quad';

type GraphCustomProperties = CSSProperties & {
    '--brain-x'?: string;
    '--brain-y'?: string;
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
    emphasized: boolean;
};

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
    const detailNodeId = validHoveredNodeId ?? validSelectedNodeId;
    const detailNode = detailNodeId ? nodeById.get(detailNodeId) : undefined;
    const positions = useMemo(
        () => focusedBrainPositions(visibleGraph, focusNodeId, adjacency),
        [adjacency, focusNodeId, visibleGraph],
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
                edges: visibleGraph.edges,
                positions,
                focusNodeId,
                matchingNodeIds,
                hasQuery: normalizedQuery.length > 0,
            }),
        [
            focusNodeId,
            matchingNodeIds,
            normalizedQuery.length,
            positions,
            visibleGraph.edges,
        ],
    );
    const focusedNeighbours = focusNodeId
        ? (adjacency.get(focusNodeId) ?? new Set<string>())
        : new Set<string>();
    const viewportWidth = secondBrainWidth / zoom;
    const viewportHeight = secondBrainHeight / zoom;
    const viewBox = `${viewCenter.x - viewportWidth / 2} ${viewCenter.y - viewportHeight / 2} ${viewportWidth} ${viewportHeight}`;

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
        setZoom((current) =>
            clamp(current + (event.deltaY < 0 ? 0.1 : -0.1), 0.72, 1.85),
        );
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
                        <feGaussianBlur stdDeviation="4" result="blur" />
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
                        const style: GraphCustomProperties = {
                            '--brain-x': `${position.x}px`,
                            '--brain-y': `${position.y}px`,
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
                                className="second-brain-node cursor-pointer outline-none motion-reduce:transition-none"
                            >
                                <title>
                                    {node.label} · {node.description}
                                </title>
                                <circle
                                    r={Math.max(16, node.size * 2.1)}
                                    className="fill-transparent"
                                />
                                {(isFocused || isSelected) && (
                                    <circle
                                        r={node.size * 2.05}
                                        className="fill-sky-300/10 stroke-sky-200/45"
                                        strokeWidth={1}
                                    />
                                )}
                                <circle
                                    r={node.size * scale}
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
                                    <text
                                        y={
                                            node.size * scale +
                                            (isFocused ? 16 : 12)
                                        }
                                        textAnchor="middle"
                                        className={cn(
                                            'pointer-events-none fill-current [stroke:#07111d] [stroke-width:4px] text-[9px] font-medium tracking-[0.01em] [paint-order:stroke]',
                                            palette.label,
                                            isFocused &&
                                                'text-[10px] font-semibold',
                                        )}
                                    >
                                        {shortLabel(
                                            node.label,
                                            compact ? 22 : 28,
                                        )}
                                    </text>
                                )}
                            </g>
                        );
                    })}
                </g>
            </svg>

            <NodePeek
                node={detailNode}
                selected={detailNode?.id === validSelectedNodeId}
                compact={compact}
                onOpen={detailNode ? () => openNode(detailNode) : undefined}
            />

            <div className="absolute bottom-3 left-3 z-20 flex items-center gap-0.5 rounded-lg border border-sky-900/60 bg-[#091725]/90 p-0.5 shadow-[0_12px_35px_rgba(0,0,0,0.22)]">
                <button
                    type="button"
                    aria-label={`Zoom out ${view.label}`}
                    onClick={() =>
                        setZoom((current) => clamp(current - 0.15, 0.72, 1.85))
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
                    onClick={() =>
                        setZoom((current) => clamp(current + 0.15, 0.72, 1.85))
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

            {!compact && !detailNode && (
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

function NodePeek({
    node,
    selected,
    compact,
    onOpen,
}: {
    node: BrainNode | undefined;
    selected: boolean;
    compact: boolean;
    onOpen?: () => void;
}) {
    if (!node) {
        return null;
    }

    const Icon = nodeIcons[node.kind];

    return (
        <aside
            className={cn(
                'pointer-events-none absolute right-3 bottom-3 z-20 flex w-[min(19rem,calc(100%-5.5rem))] items-center gap-2.5 rounded-xl border bg-[#0a1827]/94 p-2.5 shadow-[0_18px_50px_rgba(0,0,0,0.3)] backdrop-blur-md',
                selected ? 'border-sky-400/55' : 'border-sky-900/70',
            )}
        >
            <span className="grid size-8 shrink-0 place-items-center rounded-lg bg-sky-300/8 text-sky-300/65">
                <Icon className="size-4" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block truncate text-[8px] font-semibold tracking-[0.14em] text-sky-300/45 uppercase">
                    {node.eyebrow}
                </span>
                <span className="mt-0.5 block truncate text-[11px] font-medium text-sky-50">
                    {node.label}
                </span>
                <span
                    className={cn(
                        'mt-0.5 line-clamp-2 text-[9px] leading-4 text-sky-100/45',
                        compact && 'line-clamp-1',
                    )}
                >
                    {node.description}
                </span>
            </span>
            {selected && node.action && onOpen && (
                <button
                    type="button"
                    aria-label={`${actionLabel(node)}: ${node.label}`}
                    title={actionLabel(node)}
                    onClick={onOpen}
                    className="pointer-events-auto grid size-8 shrink-0 place-items-center rounded-lg bg-sky-300/10 text-sky-200/70 transition hover:bg-sky-300/18 hover:text-sky-50 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-400"
                >
                    <ArrowUpRight className="size-3.5" />
                </button>
            )}
        </aside>
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

function actionLabel(node: BrainNode): string {
    if (node.action?.type === 'snippet') {
        return 'Open file';
    }

    if (node.action?.type === 'project' || node.action?.type === 'folder') {
        return 'Reveal in library';
    }

    return 'Browse related files';
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
    positions,
    focusNodeId,
    matchingNodeIds,
    hasQuery,
}: {
    edges: BrainEdge[];
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
        const groupId = `${edge.kind}:${emphasized ? 'emphasized' : 'base'}`;
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
                strokeWidth: touchesFocus
                    ? 1.8
                    : edge.kind === 'hierarchy'
                      ? 1.05
                      : 0.9,
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

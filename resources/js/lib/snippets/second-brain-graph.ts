import type {
    LibraryCategory,
    Snippet,
    SnippetFolder,
    SnippetProject,
} from '@/types';
import { groupProjectsByLibraryCategory } from './library-category-groups.ts';

export const secondBrainWidth = 1200;
export const secondBrainHeight = 800;

const center = { x: secondBrainWidth / 2, y: secondBrainHeight / 2 };
const horizontalRadius = 520;
const verticalRadius = 338;
const maximumMetadataNodes = 60;
const maximumMetadataEdges = 12;

export type BrainNodeKind =
    | 'root'
    | 'category'
    | 'collection'
    | 'project'
    | 'folder'
    | 'snippet'
    | 'tag'
    | 'framework'
    | 'language';

export type BrainEdgeKind = 'hierarchy' | 'tag' | 'framework' | 'language';

export type BrainNodeAction =
    | { type: 'snippet'; snippetId: number }
    | { type: 'project'; projectId: number }
    | { type: 'folder'; projectId: number; folderId: number }
    | {
          type: 'filter';
          scope: 'tag' | 'framework' | 'language';
          value: string;
          frameworkId?: number;
      };

export type BrainNode = {
    id: string;
    kind: BrainNodeKind;
    label: string;
    eyebrow: string;
    description: string;
    x: number;
    y: number;
    size: number;
    childCount: number;
    connectionCount: number;
    connectionStrength?: number;
    isFavourite?: boolean;
    previewItems: string[];
    action: BrainNodeAction | null;
};

export type BrainEdge = {
    id: string;
    source: string;
    target: string;
    kind: BrainEdgeKind;
};

export type BrainGraph = {
    nodes: BrainNode[];
    edges: BrainEdge[];
    summary: {
        projects: number;
        folders: number;
        snippets: number;
        connections: number;
    };
};

export type BrainPosition = { x: number; y: number };
export type BrainViewport = {
    minX: number;
    minY: number;
    maxX: number;
    maxY: number;
};
export type BrainZoomState = {
    zoom: number;
    viewCenter: BrainPosition;
};
export type BrainDirection = 'up' | 'right' | 'down' | 'left';
export type BrainGraphDepth = 3 | 4 | 5 | 6 | 'all';

export type BrainGraphInput = {
    libraryCategories: LibraryCategory[];
    projects: SnippetProject[];
    standaloneSnippets: Snippet[];
};

export type BrainCategoryView = BrainGraphInput & {
    key: string;
    label: string;
};

type LayoutItem =
    | { type: 'folder'; folder: SnippetFolder; weight: number }
    | { type: 'snippet'; snippet: Snippet; weight: number };

type MetadataNode = {
    id: string;
    kind: Extract<BrainNodeKind, 'tag' | 'framework' | 'language'>;
    label: string;
    entityId: number | null;
    snippetIds: Set<number>;
    projectIds: Set<number>;
};

export function buildSecondBrainGraph({
    libraryCategories,
    projects,
    standaloneSnippets,
}: BrainGraphInput): BrainGraph {
    const nodes = new Map<string, BrainNode>();
    const edges = new Map<string, BrainEdge>();
    const snippetsById = new Map<number, Snippet>();
    const projectsById = new Map(
        projects.map((project) => [project.id, project]),
    );
    const metadata = new Map<string, MetadataNode>();
    const folderCount = projects.reduce(
        (total, project) => total + project.folders.length,
        0,
    );
    const allSnippets = [
        ...standaloneSnippets,
        ...projects.flatMap((project) => project.snippets),
    ];

    allSnippets.forEach((snippet) => snippetsById.set(snippet.id, snippet));

    addNode(nodes, {
        id: 'root',
        kind: 'root',
        label: 'Your second brain',
        eyebrow: 'Knowledge map',
        description: `${allSnippets.length} connected files across ${projects.length} workspaces.`,
        ...center,
        size: 17,
        childCount: 0,
        connectionCount: 0,
        previewItems: projects.slice(0, 6).map((project) => project.name),
        action: null,
    });

    const categoryIds = new Set(
        libraryCategories.map((category) => category.id),
    );
    const isLooseProject = (project: SnippetProject): boolean =>
        project.library_category_id === null ||
        !categoryIds.has(project.library_category_id);
    const hasLooseKnowledge =
        standaloneSnippets.length > 0 || projects.some(isLooseProject);
    const activeCategories = libraryCategories;
    const categoryKeys = [
        ...activeCategories.map((category) => `category:${category.id}`),
        ...(hasLooseKnowledge ? ['category:loose'] : []),
    ];
    const segments = [
        ...projects.map((project) => ({
            id: `project:${project.id}`,
            project,
            snippets: project.snippets,
            folders: project.folders,
            categoryKey: isLooseProject(project)
                ? 'category:loose'
                : `category:${project.library_category_id}`,
        })),
        ...(standaloneSnippets.length > 0
            ? [
                  {
                      id: 'collection:standalone',
                      project: null,
                      snippets: standaloneSnippets,
                      folders: [] as SnippetFolder[],
                      categoryKey: 'category:loose',
                  },
              ]
            : []),
    ];
    const segmentAngles = new Map<string, number>();
    const segmentWidths = new Map<string, number>();
    const categoryAngles = new Map<string, number>();
    const fullCircle = Math.PI * 2;
    const categorySectorWidth = fullCircle / Math.max(categoryKeys.length, 1);

    categoryKeys.forEach((categoryKey, categoryIndex) => {
        const categoryAngle =
            -Math.PI / 2 + categoryIndex * categorySectorWidth;
        const categorySegments = segments.filter(
            (segment) => segment.categoryKey === categoryKey,
        );
        const segmentSpacing =
            categoryKeys.length === 1
                ? fullCircle / Math.max(categorySegments.length, 1)
                : (categorySectorWidth * 0.78) /
                  Math.max(categorySegments.length, 1);

        categoryAngles.set(categoryKey, categoryAngle);
        categorySegments.forEach((segment, segmentIndex) => {
            const offset =
                categoryKeys.length === 1
                    ? segmentIndex * segmentSpacing
                    : (segmentIndex - (categorySegments.length - 1) / 2) *
                      segmentSpacing;

            segmentAngles.set(segment.id, categoryAngle + offset);
            segmentWidths.set(
                segment.id,
                segmentSpacing * (categoryKeys.length === 1 ? 0.82 : 0.86),
            );
        });
    });

    for (const categoryKey of categoryKeys) {
        const category = categoryKey.startsWith('category:loose')
            ? null
            : activeCategories.find(
                  (candidate) => categoryKey === `category:${candidate.id}`,
              );
        const categorySegments = segments.filter(
            (segment) => segment.categoryKey === categoryKey,
        );
        const angle = categoryAngles.get(categoryKey) ?? -Math.PI / 2;
        const position = polarPosition(angle, 0.16, categoryKey);
        const categorySnippetCount = categorySegments.reduce(
            (total, segment) => total + segment.snippets.length,
            0,
        );

        addNode(nodes, {
            id: categoryKey,
            kind: 'category',
            label: category?.name ?? 'Loose knowledge',
            eyebrow: category ? 'Library category' : 'Uncategorised',
            description: `${categorySegments.length} collections containing ${categorySnippetCount} files.`,
            ...position,
            size: 9 + Math.min(4, Math.sqrt(categorySegments.length)),
            childCount: categorySegments.length,
            connectionCount: 0,
            previewItems: categorySegments
                .map((segment) => segment.project?.name ?? 'Standalone files')
                .slice(0, 6),
            action: null,
        });
        addEdge(edges, 'root', categoryKey, 'hierarchy');
    }

    segments.forEach((segment, segmentIndex) => {
        const angle = segmentAngles.get(segment.id) ?? -Math.PI / 2;
        const projectPosition = polarPosition(angle, 0.34, segment.id);
        const segmentWidth = segmentWidths.get(segment.id) ?? Math.PI * 0.5;
        const project = segment.project;

        addNode(nodes, {
            id: segment.id,
            kind: project ? 'project' : 'collection',
            label: project?.name ?? 'Standalone files',
            eyebrow: project ? projectKindLabel(project.kind) : 'Collection',
            description:
                project?.description ??
                `${segment.snippets.length} files that are not assigned to a workspace.`,
            ...projectPosition,
            size:
                7 +
                Math.min(
                    6,
                    Math.sqrt(segment.snippets.length + segment.folders.length),
                ),
            childCount: segment.folders.length + segment.snippets.length,
            connectionCount: 0,
            previewItems: segment.snippets
                .slice(0, 6)
                .map((snippet) => snippet.title || snippet.filename),
            action: project ? { type: 'project', projectId: project.id } : null,
        });
        addEdge(edges, segment.categoryKey, segment.id, 'hierarchy');

        layoutSegment({
            nodes,
            edges,
            metadata,
            project,
            parentId: segment.id,
            folders: segment.folders,
            snippets: segment.snippets,
            angle,
            startAngle: angle - segmentWidth / 2,
            endAngle: angle + segmentWidth / 2,
            segmentIndex,
        });
    });

    const metadataNodes = [...metadata.values()]
        .sort((left, right) => {
            const connectionDifference =
                right.snippetIds.size +
                right.projectIds.size -
                (left.snippetIds.size + left.projectIds.size);

            return (
                connectionDifference || left.label.localeCompare(right.label)
            );
        })
        .slice(0, maximumMetadataNodes);

    metadataNodes.forEach((item, index) => {
        const angle =
            -Math.PI / 2 +
            (index * Math.PI * 2) / Math.max(metadataNodes.length, 1);
        const radius = index % 2 === 0 ? 0.91 : 0.99;
        const position = polarPosition(angle, radius, item.id);
        const relatedSnippets = [...item.snippetIds]
            .map((snippetId) => snippetsById.get(snippetId))
            .filter((snippet): snippet is Snippet => snippet !== undefined)
            .sort(compareSnippetsBySignal);
        const connectionCount = item.snippetIds.size + item.projectIds.size;

        addNode(nodes, {
            id: item.id,
            kind: item.kind,
            label: item.label,
            eyebrow: brainNodeKindLabel(item.kind),
            description: `${connectionCount} connected ${connectionCount === 1 ? 'item' : 'items'} in your library.`,
            ...position,
            size: 3.5 + Math.min(5, Math.sqrt(connectionCount) * 0.72),
            childCount: 0,
            connectionCount: 0,
            previewItems: relatedSnippets
                .slice(0, 6)
                .map((snippet) => snippet.title || snippet.filename),
            action: {
                type: 'filter',
                scope: item.kind,
                value: item.label,
                ...(item.kind === 'framework' && item.entityId !== null
                    ? { frameworkId: item.entityId }
                    : {}),
            },
        });

        relatedSnippets
            .slice(0, maximumMetadataEdges)
            .forEach((snippet) =>
                addEdge(edges, item.id, `snippet:${snippet.id}`, item.kind),
            );
        [...item.projectIds]
            .filter((projectId) => projectsById.has(projectId))
            .slice(0, maximumMetadataEdges)
            .forEach((projectId) =>
                addEdge(edges, item.id, `project:${projectId}`, item.kind),
            );
    });

    const adjacency = adjacencyFor([...edges.values()]);
    const hierarchyChildren = new Map<string, number>();

    edges.forEach((edge) => {
        if (edge.kind === 'hierarchy') {
            hierarchyChildren.set(
                edge.source,
                (hierarchyChildren.get(edge.source) ?? 0) + 1,
            );
        }
    });
    nodes.forEach((node) => {
        node.connectionCount = adjacency.get(node.id)?.size ?? 0;
        node.childCount = Math.max(
            node.childCount,
            hierarchyChildren.get(node.id) ?? 0,
        );
    });

    return {
        nodes: [...nodes.values()],
        edges: [...edges.values()],
        summary: {
            projects: projects.length,
            folders: folderCount,
            snippets: allSnippets.length,
            connections: edges.size,
        },
    };
}

export function buildSecondBrainCategoryViews({
    libraryCategories,
    projects,
    standaloneSnippets,
}: BrainGraphInput): BrainCategoryView[] {
    const categoryGroups = groupProjectsByLibraryCategory(
        libraryCategories,
        projects,
    );

    if (
        standaloneSnippets.length > 0 &&
        !categoryGroups.some((group) => group.category === null)
    ) {
        categoryGroups.push({
            key: 'category:uncategorised',
            label: 'Uncategorised',
            category: null,
            projects: [],
        });
    }

    return [
        {
            key: 'all',
            label: 'All categories',
            libraryCategories,
            projects,
            standaloneSnippets,
        },
        ...categoryGroups.map((group) => ({
            key: group.key,
            label: group.label,
            libraryCategories: group.category ? [group.category] : [],
            projects: group.projects,
            standaloneSnippets: group.category ? [] : standaloneSnippets,
        })),
    ];
}

export function resolveBrainCategorySelections(
    availableKeys: readonly string[],
    currentSelections: readonly (string | null)[],
    count: number,
): (string | null)[] {
    const uniqueAvailableKeys = [...new Set(availableKeys)];
    const availableKeySet = new Set(uniqueAvailableKeys);
    const usedKeys = new Set<string>();
    const selections = Array.from<string | null>({ length: count }).fill(null);

    selections.forEach((_, index) => {
        const currentKey = currentSelections[index];

        if (
            currentKey &&
            availableKeySet.has(currentKey) &&
            !usedKeys.has(currentKey)
        ) {
            selections[index] = currentKey;
            usedKeys.add(currentKey);
        }
    });

    selections.forEach((selection, index) => {
        if (selection !== null) {
            return;
        }

        const nextKey = uniqueAvailableKeys.find(
            (candidate) => !usedKeys.has(candidate),
        );

        if (nextKey) {
            selections[index] = nextKey;
            usedKeys.add(nextKey);
        }
    });

    return selections;
}

export function buildBrainAdjacency(
    graph: Pick<BrainGraph, 'nodes' | 'edges'>,
): Map<string, Set<string>> {
    const adjacency = adjacencyFor(graph.edges);

    graph.nodes.forEach((node) => {
        if (!adjacency.has(node.id)) {
            adjacency.set(node.id, new Set());
        }
    });

    return adjacency;
}

export function filterSecondBrainGraphByDepth(
    graph: BrainGraph,
    centerNodeId: string,
    depth: BrainGraphDepth,
): BrainGraph {
    if (depth === 'all') {
        return graph;
    }

    const nodeIds = new Set(graph.nodes.map((node) => node.id));
    const resolvedCenterNodeId = nodeIds.has(centerNodeId)
        ? centerNodeId
        : nodeIds.has('root')
          ? 'root'
          : graph.nodes[0]?.id;

    if (!resolvedCenterNodeId) {
        return graph;
    }

    const adjacency = buildBrainAdjacency(graph);
    const distances = new Map<string, number>([[resolvedCenterNodeId, 0]]);
    const queue = [resolvedCenterNodeId];

    for (let index = 0; index < queue.length; index += 1) {
        const nodeId = queue[index];
        const nodeDepth = distances.get(nodeId) ?? 0;

        if (nodeDepth >= depth) {
            continue;
        }

        adjacency.get(nodeId)?.forEach((neighbourId) => {
            if (nodeIds.has(neighbourId) && !distances.has(neighbourId)) {
                distances.set(neighbourId, nodeDepth + 1);
                queue.push(neighbourId);
            }
        });
    }

    const visibleNodeIds = new Set(distances.keys());
    const nodes = graph.nodes.filter((node) => visibleNodeIds.has(node.id));
    const edges = graph.edges.filter(
        (edge) =>
            visibleNodeIds.has(edge.source) && visibleNodeIds.has(edge.target),
    );

    return {
        nodes,
        edges,
        summary: {
            projects: nodes.filter((node) => node.kind === 'project').length,
            folders: nodes.filter((node) => node.kind === 'folder').length,
            snippets: nodes.filter((node) => node.kind === 'snippet').length,
            connections: edges.length,
        },
    };
}

export function filterBrainEdgesByViewport(
    edges: readonly BrainEdge[],
    positions: ReadonlyMap<string, BrainPosition>,
    viewport: BrainViewport,
): BrainEdge[] {
    return edges.filter((edge) => {
        const source = positions.get(edge.source);
        const target = positions.get(edge.target);

        if (source === undefined || target === undefined) {
            return false;
        }

        return (
            isBrainPositionInViewport(source, viewport) ||
            isBrainPositionInViewport(target, viewport)
        );
    });
}

/**
 * Changes the graph zoom while preserving the graph coordinate below the
 * pointer. This makes wheel zoom feel anchored to the item being inspected
 * instead of always pulling the view toward its centre.
 */
export function zoomSecondBrainAtPoint({
    zoom,
    viewCenter,
    pointerX,
    pointerY,
    zoomDelta,
    minimumZoom,
    maximumZoom,
}: {
    zoom: number;
    viewCenter: BrainPosition;
    pointerX: number;
    pointerY: number;
    zoomDelta: number;
    minimumZoom: number;
    maximumZoom: number;
}): BrainZoomState {
    const nextZoom = clamp(zoom + zoomDelta, minimumZoom, maximumZoom);

    if (nextZoom === zoom) {
        return { zoom, viewCenter };
    }

    const graphPoint = {
        x: viewCenter.x + (pointerX - 0.5) * (secondBrainWidth / zoom),
        y: viewCenter.y + (pointerY - 0.5) * (secondBrainHeight / zoom),
    };

    return {
        zoom: nextZoom,
        viewCenter: {
            x: graphPoint.x - (pointerX - 0.5) * (secondBrainWidth / nextZoom),
            y: graphPoint.y - (pointerY - 0.5) * (secondBrainHeight / nextZoom),
        },
    };
}

export function focusedBrainPositions(
    graph: BrainGraph,
    focusNodeId: string | null,
    adjacency = buildBrainAdjacency(graph),
): Map<string, BrainPosition> {
    const positions = new Map(
        graph.nodes.map((node) => [node.id, { x: node.x, y: node.y }]),
    );

    if (!focusNodeId) {
        return positions;
    }

    const focus = graph.nodes.find((node) => node.id === focusNodeId);

    if (!focus) {
        return positions;
    }

    const direct = adjacency.get(focus.id) ?? new Set<string>();
    const secondDegree = new Set<string>();

    direct.forEach((nodeId) => {
        adjacency
            .get(nodeId)
            ?.forEach((candidateId) => secondDegree.add(candidateId));
    });

    graph.nodes.forEach((node) => {
        if (node.id === focus.id) {
            return;
        }

        let deltaX = node.x - focus.x;
        let deltaY = node.y - focus.y;
        let distance = Math.hypot(deltaX, deltaY);

        if (distance < 1) {
            const angle = hashUnit(node.id) * Math.PI * 2;
            deltaX = Math.cos(angle);
            deltaY = Math.sin(angle);
            distance = 1;
        }

        const shift = direct.has(node.id)
            ? 38 + Math.min(18, focus.size)
            : secondDegree.has(node.id)
              ? 16
              : 0;
        const x = clamp(
            node.x + (deltaX / distance) * shift,
            20,
            secondBrainWidth - 20,
        );
        const y = clamp(
            node.y + (deltaY / distance) * shift,
            20,
            secondBrainHeight - 20,
        );

        positions.set(node.id, { x, y });
    });

    return positions;
}

export function findDirectionalBrainNode(
    graph: BrainGraph,
    currentNodeId: string,
    direction: BrainDirection,
): string | null {
    const current = graph.nodes.find((node) => node.id === currentNodeId);

    if (!current) {
        return null;
    }

    const vector = {
        up: { x: 0, y: -1 },
        right: { x: 1, y: 0 },
        down: { x: 0, y: 1 },
        left: { x: -1, y: 0 },
    }[direction];

    return (
        graph.nodes
            .filter((node) => node.id !== current.id)
            .map((node) => {
                const deltaX = node.x - current.x;
                const deltaY = node.y - current.y;
                const forward = deltaX * vector.x + deltaY * vector.y;
                const sideways = Math.abs(
                    deltaX * vector.y - deltaY * vector.x,
                );

                return {
                    id: node.id,
                    forward,
                    score: Math.hypot(deltaX, deltaY) + sideways * 1.8,
                };
            })
            .filter((candidate) => candidate.forward > 2)
            .sort((left, right) => left.score - right.score)[0]?.id ?? null
    );
}

export function brainNodeKindLabel(kind: BrainNodeKind): string {
    return {
        root: 'Second brain',
        category: 'Library category',
        collection: 'Collection',
        project: 'Workspace',
        folder: 'Folder',
        snippet: 'File',
        tag: 'Tag',
        framework: 'Framework',
        language: 'Language',
    }[kind];
}

function isBrainPositionInViewport(
    position: BrainPosition,
    viewport: BrainViewport,
): boolean {
    return (
        position.x >= viewport.minX &&
        position.x <= viewport.maxX &&
        position.y >= viewport.minY &&
        position.y <= viewport.maxY
    );
}

function layoutSegment({
    nodes,
    edges,
    metadata,
    project,
    parentId,
    folders,
    snippets,
    angle,
    startAngle,
    endAngle,
    segmentIndex,
}: {
    nodes: Map<string, BrainNode>;
    edges: Map<string, BrainEdge>;
    metadata: Map<string, MetadataNode>;
    project: SnippetProject | null;
    parentId: string;
    folders: SnippetFolder[];
    snippets: Snippet[];
    angle: number;
    startAngle: number;
    endAngle: number;
    segmentIndex: number;
}): void {
    const folderById = new Map(folders.map((folder) => [folder.id, folder]));
    const childFolders = new Map<number | null, SnippetFolder[]>();
    const snippetsByFolder = new Map<number | null, Snippet[]>();
    const folderWeights = new Map<number, number>();

    folders.forEach((folder) => {
        const parentFolderId =
            folder.parent_id !== null && folderById.has(folder.parent_id)
                ? folder.parent_id
                : null;
        const siblings = childFolders.get(parentFolderId) ?? [];
        siblings.push(folder);
        childFolders.set(parentFolderId, siblings);
    });
    snippets.forEach((snippet) => {
        const folderId =
            snippet.folder_id !== null && folderById.has(snippet.folder_id)
                ? snippet.folder_id
                : null;
        const siblings = snippetsByFolder.get(folderId) ?? [];
        siblings.push(snippet);
        snippetsByFolder.set(folderId, siblings);
        collectSnippetMetadata(metadata, snippet);
    });
    project?.frameworks.forEach((framework) => {
        const item = metadataItem(
            metadata,
            `framework:${framework.id}`,
            'framework',
            framework.name,
            framework.id,
        );
        item.projectIds.add(project.id);
    });

    const folderWeight = (
        folder: SnippetFolder,
        seen = new Set<number>(),
    ): number => {
        const cached = folderWeights.get(folder.id);

        if (cached !== undefined) {
            return cached;
        }

        if (seen.has(folder.id)) {
            return 1;
        }

        const nextSeen = new Set(seen).add(folder.id);
        const weight = Math.max(
            1,
            (snippetsByFolder.get(folder.id)?.length ?? 0) +
                (childFolders.get(folder.id) ?? []).reduce(
                    (total, child) => total + folderWeight(child, nextSeen),
                    0,
                ),
        );
        folderWeights.set(folder.id, weight);

        return weight;
    };

    const layoutItems = (
        layoutParentId: string,
        items: LayoutItem[],
        itemStartAngle: number,
        itemEndAngle: number,
        depth: number,
    ): void => {
        const totalWeight = Math.max(
            1,
            items.reduce((total, item) => total + item.weight, 0),
        );
        let cursor = itemStartAngle;

        items.forEach((item) => {
            const span =
                ((itemEndAngle - itemStartAngle) * item.weight) / totalWeight;
            const nextCursor = cursor + span;
            const itemAngle = (cursor + nextCursor) / 2;

            if (item.type === 'folder') {
                const folder = item.folder;
                const folderId = `folder:${folder.id}`;
                const folderChildren = childFolders.get(folder.id) ?? [];
                const folderSnippets = snippetsByFolder.get(folder.id) ?? [];
                const position = polarPosition(
                    itemAngle,
                    Math.min(0.78, 0.45 + depth * 0.085),
                    folderId,
                );
                const projectId = project?.id ?? folder.project_id;

                addNode(nodes, {
                    id: folderId,
                    kind: 'folder',
                    label: folder.name,
                    eyebrow:
                        depth === 1
                            ? 'Folder'
                            : `Nested folder · level ${depth}`,
                    description: `${folderSnippets.length} direct files and ${folderChildren.length} nested folders.`,
                    ...position,
                    size:
                        4.5 +
                        Math.min(4, Math.sqrt(folderWeight(folder)) * 0.55),
                    childCount: folderChildren.length + folderSnippets.length,
                    connectionCount: 0,
                    previewItems: [
                        ...folderChildren.map((child) => child.name),
                        ...folderSnippets.map(
                            (snippet) => snippet.title || snippet.filename,
                        ),
                    ].slice(0, 6),
                    action: {
                        type: 'folder',
                        projectId,
                        folderId: folder.id,
                    },
                });
                addEdge(edges, layoutParentId, folderId, 'hierarchy');

                const nestedItems: LayoutItem[] = [
                    ...folderChildren.map((child) => ({
                        type: 'folder' as const,
                        folder: child,
                        weight: folderWeight(child),
                    })),
                    ...folderSnippets.map((snippet) => ({
                        type: 'snippet' as const,
                        snippet,
                        weight: 1,
                    })),
                ];

                layoutItems(
                    folderId,
                    nestedItems,
                    cursor + span * 0.04,
                    nextCursor - span * 0.04,
                    depth + 1,
                );
            } else {
                const snippet = item.snippet;
                const snippetId = `snippet:${snippet.id}`;
                const radius = Math.min(
                    0.87,
                    0.65 + depth * 0.055 + (segmentIndex % 2) * 0.018,
                );
                const position = polarPosition(itemAngle, radius, snippetId);
                const detailItems = [
                    snippet.language,
                    ...snippet.frameworks.map((framework) => framework.name),
                    ...snippet.tags.map((tag) => tag.name),
                ].filter(Boolean);

                addNode(nodes, {
                    id: snippetId,
                    kind: 'snippet',
                    label: snippet.title || snippet.filename,
                    eyebrow:
                        snippet.content_type === 'guide'
                            ? `Guide · ${snippet.language}`
                            : `File · ${snippet.language}`,
                    description:
                        snippet.description ??
                        `${snippet.filename} has ${snippet.variations.length} ${snippet.variations.length === 1 ? 'variation' : 'variations'}.`,
                    ...position,
                    size:
                        3.5 +
                        (snippet.is_pinned ? 1.3 : 0) +
                        (snippet.is_favourite ? 1 : 0) +
                        Math.max(0, snippet.usage.indicator) * 0.35,
                    childCount: snippet.variations.length,
                    connectionCount: 0,
                    connectionStrength: snippet.usage.relative_score,
                    isFavourite: snippet.is_favourite,
                    previewItems: detailItems.slice(0, 6),
                    action: { type: 'snippet', snippetId: snippet.id },
                });
                addEdge(edges, layoutParentId, snippetId, 'hierarchy');
            }

            cursor = nextCursor;
        });
    };

    const rootItems: LayoutItem[] = [
        ...(childFolders.get(null) ?? []).map((folder) => ({
            type: 'folder' as const,
            folder,
            weight: folderWeight(folder),
        })),
        ...(snippetsByFolder.get(null) ?? []).map((snippet) => ({
            type: 'snippet' as const,
            snippet,
            weight: 1,
        })),
    ];

    layoutItems(parentId, rootItems, startAngle, endAngle, 1);

    if (rootItems.length === 0) {
        const node = nodes.get(parentId);

        if (node) {
            node.description = 'This collection is ready for its first file.';
        }
    }

    if (!Number.isFinite(angle)) {
        throw new Error('Second brain segment angle must be finite.');
    }
}

function collectSnippetMetadata(
    metadata: Map<string, MetadataNode>,
    snippet: Snippet,
): void {
    snippet.tags.forEach((tag) => {
        metadataItem(
            metadata,
            `tag:${tag.id}`,
            'tag',
            tag.name,
            tag.id,
        ).snippetIds.add(snippet.id);
    });
    snippet.frameworks.forEach((framework) => {
        metadataItem(
            metadata,
            `framework:${framework.id}`,
            'framework',
            framework.name,
            framework.id,
        ).snippetIds.add(snippet.id);
    });
    metadataItem(
        metadata,
        `language:${snippet.language}`,
        'language',
        snippet.language,
        null,
    ).snippetIds.add(snippet.id);
}

function metadataItem(
    metadata: Map<string, MetadataNode>,
    id: string,
    kind: MetadataNode['kind'],
    label: string,
    entityId: number | null,
): MetadataNode {
    const existing = metadata.get(id);

    if (existing) {
        return existing;
    }

    const item: MetadataNode = {
        id,
        kind,
        label,
        entityId,
        snippetIds: new Set(),
        projectIds: new Set(),
    };
    metadata.set(id, item);

    return item;
}

function addNode(nodes: Map<string, BrainNode>, node: BrainNode): void {
    nodes.set(node.id, node);
}

function addEdge(
    edges: Map<string, BrainEdge>,
    source: string,
    target: string,
    kind: BrainEdgeKind,
): void {
    const id = `${kind}:${source}->${target}`;

    if (!edges.has(id)) {
        edges.set(id, { id, source, target, kind });
    }
}

function adjacencyFor(edges: BrainEdge[]): Map<string, Set<string>> {
    const adjacency = new Map<string, Set<string>>();

    edges.forEach((edge) => {
        const source = adjacency.get(edge.source) ?? new Set<string>();
        const target = adjacency.get(edge.target) ?? new Set<string>();
        source.add(edge.target);
        target.add(edge.source);
        adjacency.set(edge.source, source);
        adjacency.set(edge.target, target);
    });

    return adjacency;
}

function polarPosition(
    angle: number,
    radius: number,
    seed: string,
): BrainPosition {
    const radialJitter = (hashUnit(`${seed}:radius`) - 0.5) * 0.022;
    const angleJitter = (hashUnit(`${seed}:angle`) - 0.5) * 0.018;

    return {
        x:
            center.x +
            Math.cos(angle + angleJitter) *
                horizontalRadius *
                (radius + radialJitter),
        y:
            center.y +
            Math.sin(angle + angleJitter) *
                verticalRadius *
                (radius + radialJitter),
    };
}

function hashUnit(value: string): number {
    let hash = 2166136261;

    for (let index = 0; index < value.length; index += 1) {
        hash ^= value.charCodeAt(index);
        hash = Math.imul(hash, 16777619);
    }

    return (hash >>> 0) / 4294967295;
}

function compareSnippetsBySignal(left: Snippet, right: Snippet): number {
    const leftSignal =
        Number(left.is_pinned) * 10_000 +
        Number(left.is_favourite) * 1_000 +
        left.usage.copies_30d * 10 +
        left.usage.copies_total;
    const rightSignal =
        Number(right.is_pinned) * 10_000 +
        Number(right.is_favourite) * 1_000 +
        right.usage.copies_30d * 10 +
        right.usage.copies_total;

    return (
        rightSignal - leftSignal ||
        (left.title || left.filename).localeCompare(
            right.title || right.filename,
        )
    );
}

function projectKindLabel(kind: SnippetProject['kind']): string {
    return {
        project: 'Workspace',
        bundle: 'Snippet bundle',
        guide: 'Guide collection',
    }[kind];
}

function clamp(value: number, minimum: number, maximum: number): number {
    return Math.min(maximum, Math.max(minimum, value));
}

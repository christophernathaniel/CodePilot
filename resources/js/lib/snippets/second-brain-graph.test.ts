import assert from 'node:assert/strict';
import test from 'node:test';
import type { LibraryCategory, Snippet, SnippetProject } from '@/types';
import {
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
} from './second-brain-graph.ts';

const snippet = (overrides: Partial<Snippet> = {}): Snippet => ({
    id: 10,
    project_id: 4,
    folder_id: 7,
    title: 'Reusable request helper',
    filename: 'request-helper.php',
    content_type: 'snippet',
    language: 'php',
    description: 'Sends a signed request.',
    position: 0,
    is_favourite: true,
    is_pinned: false,
    last_opened_at: null,
    updated_at: '2026-08-02T12:00:00.000000Z',
    variations: [
        {
            id: 20,
            name: 'Default',
            content: '<?php',
            position: 0,
            is_default: true,
            updated_at: '2026-08-02T12:00:00.000000Z',
            sections: [],
            guide_steps: [],
        },
    ],
    presets: [],
    tags: [
        {
            id: 30,
            name: 'HTTP',
            slug: 'http',
            color: null,
            is_pinned: false,
        },
    ],
    frameworks: [
        {
            id: 40,
            name: 'Laravel',
            slug: 'laravel',
            color: null,
            is_pinned: false,
        },
    ],
    usage: {
        copies_30d: 2,
        copies_total: 4,
        last_copied_at: null,
        views_30d: 0,
        views_total: 0,
        last_viewed_at: null,
        weighted_score: 2,
        relative_score: 1,
        indicator: 2,
    },
    ...overrides,
});

const project = (
    files: Snippet[],
    overrides: Partial<SnippetProject> = {},
): SnippetProject => ({
    id: 4,
    library_category_id: 2,
    name: 'API recipes',
    kind: 'bundle',
    description: 'Reusable API integrations.',
    is_pinned: false,
    frameworks: [
        {
            id: 40,
            name: 'Laravel',
            slug: 'laravel',
            color: null,
            is_pinned: false,
        },
    ],
    folders: [
        {
            id: 7,
            project_id: 4,
            parent_id: null,
            name: 'Requests',
            position: 0,
        },
    ],
    snippets: files,
    ...overrides,
});

test('builds containment and semantic links for real workspace items', () => {
    const graph = buildSecondBrainGraph({
        libraryCategories: [{ id: 2, name: 'Backend', position: 0 }],
        projects: [project([snippet()])],
        standaloneSnippets: [],
    });

    assert.equal(graph.summary.projects, 1);
    assert.equal(graph.summary.folders, 1);
    assert.equal(graph.summary.snippets, 1);
    assert.ok(graph.nodes.some((node) => node.id === 'category:2'));
    assert.equal(
        graph.nodes.find((node) => node.id === 'category:2')?.action,
        null,
    );
    assert.ok(graph.nodes.some((node) => node.id === 'project:4'));
    assert.ok(graph.nodes.some((node) => node.id === 'folder:7'));
    assert.deepEqual(
        graph.nodes.find((node) => node.id === 'snippet:10')?.action,
        { type: 'snippet', snippetId: 10 },
    );
    assert.deepEqual(
        graph.nodes.find((node) => node.id === 'snippet:10')
            ?.connectionStrength,
        1,
    );
    assert.equal(
        graph.nodes.find((node) => node.id === 'snippet:10')?.isFavourite,
        true,
    );
    assert.ok(graph.nodes.some((node) => node.id === 'tag:30'));
    assert.ok(graph.nodes.some((node) => node.id === 'framework:40'));
    assert.ok(graph.nodes.some((node) => node.id === 'language:php'));
    assert.ok(
        graph.edges.some(
            (edge) =>
                edge.source === 'folder:7' && edge.target === 'snippet:10',
        ),
    );
    assert.ok(
        graph.edges.some(
            (edge) => edge.source === 'tag:30' && edge.target === 'snippet:10',
        ),
    );
});

test('lays out the same workspace deterministically inside the canvas', () => {
    const input = {
        libraryCategories: [{ id: 2, name: 'Backend', position: 0 }],
        projects: [project([snippet()])],
        standaloneSnippets: [],
    };
    const first = buildSecondBrainGraph(input);
    const second = buildSecondBrainGraph(input);

    assert.deepEqual(
        first.nodes.map(({ id, x, y }) => ({ id, x, y })),
        second.nodes.map(({ id, x, y }) => ({ id, x, y })),
    );
    first.nodes.forEach((node) => {
        assert.ok(node.x >= 0 && node.x <= secondBrainWidth);
        assert.ok(node.y >= 0 && node.y <= secondBrainHeight);
    });
});

test('focus expands its neighbourhood without moving unrelated nodes', () => {
    const graph = buildSecondBrainGraph({
        libraryCategories: [
            { id: 2, name: 'Backend', position: 0 },
            { id: 3, name: 'Books', position: 1 },
        ],
        projects: [
            project([
                snippet(),
                snippet({
                    id: 11,
                    folder_id: null,
                    title: 'JSON response',
                    filename: 'json-response.php',
                }),
            ]),
        ],
        standaloneSnippets: [],
    });
    const positions = focusedBrainPositions(graph, 'project:4');

    const focusedProject = graph.nodes.find((node) => node.id === 'project:4');
    const directFolder = graph.nodes.find((node) => node.id === 'folder:7');
    const unrelatedCategory = graph.nodes.find(
        (node) => node.id === 'category:3',
    );

    assert.ok(focusedProject);
    assert.ok(directFolder);
    assert.ok(unrelatedCategory);
    assert.deepEqual(positions.get(focusedProject.id), {
        x: focusedProject.x,
        y: focusedProject.y,
    });
    assert.notDeepEqual(positions.get(directFolder.id), {
        x: directFolder.x,
        y: directFolder.y,
    });
    assert.deepEqual(positions.get(unrelatedCategory.id), {
        x: unrelatedCategory.x,
        y: unrelatedCategory.y,
    });
});

test('includes standalone knowledge and supports directional keyboard movement', () => {
    const graph = buildSecondBrainGraph({
        libraryCategories: [],
        projects: [],
        standaloneSnippets: [
            snippet({
                id: 99,
                project_id: null,
                folder_id: null,
                title: 'Loose thought',
                filename: 'thought.md',
                language: 'markdown',
                tags: [],
                frameworks: [],
            }),
        ],
    });

    assert.ok(graph.nodes.some((node) => node.id === 'category:loose'));
    assert.ok(graph.nodes.some((node) => node.id === 'collection:standalone'));
    assert.ok(graph.nodes.some((node) => node.id === 'snippet:99'));
    assert.ok(findDirectionalBrainNode(graph, 'root', 'up'));
});

test('builds ordered category views that keep each library segregated', () => {
    const categories: LibraryCategory[] = [
        { id: 2, name: 'Programming', position: 0 },
        { id: 3, name: 'Books', position: 1 },
    ];
    const programmingProject = project([snippet()], {
        id: 4,
        library_category_id: 2,
        name: 'Laravel patterns',
    });
    const booksProject = project(
        [
            snippet({
                id: 11,
                project_id: 5,
                title: 'Reading notes',
                filename: 'reading-notes.md',
            }),
        ],
        {
            id: 5,
            library_category_id: 3,
            name: 'Bookshelf',
        },
    );
    const views = buildSecondBrainCategoryViews({
        libraryCategories: categories,
        projects: [programmingProject, booksProject],
        standaloneSnippets: [],
    });

    assert.deepEqual(
        views.map((view) => view.key),
        ['all', 'category:2', 'category:3'],
    );
    assert.deepEqual(
        views
            .find((view) => view.key === 'category:2')
            ?.projects.map((item) => item.id),
        [4],
    );
    assert.deepEqual(
        views
            .find((view) => view.key === 'category:3')
            ?.projects.map((item) => item.id),
        [5],
    );
    assert.equal(views.find((view) => view.key === 'all')?.projects.length, 2);
});

test('combines loose, stale, and standalone knowledge in uncategorised', () => {
    const standalone = snippet({
        id: 99,
        project_id: null,
        folder_id: null,
        title: 'Loose thought',
    });
    const views = buildSecondBrainCategoryViews({
        libraryCategories: [{ id: 2, name: 'Programming', position: 0 }],
        projects: [
            project([], { id: 5, library_category_id: null }),
            project([], { id: 6, library_category_id: 999 }),
        ],
        standaloneSnippets: [standalone],
    });
    const uncategorised = views.find(
        (view) => view.key === 'category:uncategorised',
    );

    assert.deepEqual(
        uncategorised?.projects.map((item) => item.id),
        [5, 6],
    );
    assert.deepEqual(
        uncategorised?.standaloneSnippets.map((item) => item.id),
        [99],
    );
    assert.deepEqual(
        views.find((view) => view.key === 'category:2')?.standaloneSnippets,
        [],
    );

    const graph = buildSecondBrainGraph({
        libraryCategories: [{ id: 2, name: 'Programming', position: 0 }],
        projects: [project([], { id: 6, library_category_id: 999 })],
        standaloneSnippets: [],
    });

    assert.ok(graph.nodes.some((node) => node.id === 'category:loose'));
    assert.ok(
        graph.edges.some(
            (edge) =>
                edge.source === 'category:loose' && edge.target === 'project:6',
        ),
    );
});

test('keeps empty categories visible and selectable in the graph', () => {
    const views = buildSecondBrainCategoryViews({
        libraryCategories: [{ id: 8, name: 'Ideas', position: 0 }],
        projects: [],
        standaloneSnippets: [],
    });
    const ideas = views.find((view) => view.key === 'category:8');

    assert.ok(ideas);
    const graph = buildSecondBrainGraph(ideas);
    assert.ok(graph.nodes.some((node) => node.id === 'category:8'));
    assert.ok(
        graph.edges.some(
            (edge) => edge.source === 'root' && edge.target === 'category:8',
        ),
    );
});

test('gives empty and populated categories distinct sectors in the combined view', () => {
    const graph = buildSecondBrainGraph({
        libraryCategories: [
            { id: 1, name: 'Ideas', position: 0 },
            { id: 2, name: 'Programming', position: 1 },
            { id: 3, name: 'Books', position: 2 },
        ],
        projects: [project([snippet()], { library_category_id: 2 })],
        standaloneSnippets: [],
    });
    const ideas = graph.nodes.find((node) => node.id === 'category:1');
    const programming = graph.nodes.find((node) => node.id === 'category:2');

    assert.ok(ideas);
    assert.ok(programming);
    assert.ok(
        Math.hypot(ideas.x - programming.x, ideas.y - programming.y) > 40,
    );
});

test('resolves distinct comparison panes and safely fills missing choices', () => {
    assert.deepEqual(
        resolveBrainCategorySelections(
            ['category:1', 'category:2', 'category:3'],
            ['category:2', 'category:2', 'category:99'],
            4,
        ),
        ['category:2', 'category:1', 'category:3', null],
    );
});

test('limits the graph to relationship hops from the chosen center', () => {
    const graph = buildSecondBrainGraph({
        libraryCategories: [{ id: 2, name: 'Programming', position: 0 }],
        projects: [project([snippet()])],
        standaloneSnippets: [],
    });
    const fromRoot = filterSecondBrainGraphByDepth(graph, 'root', 3);
    const fromSnippet = filterSecondBrainGraphByDepth(graph, 'snippet:10', 3);

    assert.ok(fromRoot.nodes.some((node) => node.id === 'folder:7'));
    assert.ok(!fromRoot.nodes.some((node) => node.id === 'snippet:10'));
    assert.equal(fromRoot.summary.folders, 1);
    assert.equal(fromRoot.summary.snippets, 0);

    assert.ok(fromSnippet.nodes.some((node) => node.id === 'category:2'));
    assert.ok(!fromSnippet.nodes.some((node) => node.id === 'root'));
    assert.ok(
        fromSnippet.edges.every(
            (edge) =>
                fromSnippet.nodes.some((node) => node.id === edge.source) &&
                fromSnippet.nodes.some((node) => node.id === edge.target),
        ),
    );
});

test('returns the complete graph for all depth and falls back to root safely', () => {
    const graph = buildSecondBrainGraph({
        libraryCategories: [{ id: 2, name: 'Programming', position: 0 }],
        projects: [project([snippet()])],
        standaloneSnippets: [],
    });

    assert.equal(
        filterSecondBrainGraphByDepth(graph, 'snippet:10', 'all'),
        graph,
    );
    assert.deepEqual(
        filterSecondBrainGraphByDepth(graph, 'missing', 3).nodes.map(
            (node) => node.id,
        ),
        filterSecondBrainGraphByDepth(graph, 'root', 3).nodes.map(
            (node) => node.id,
        ),
    );
});

test('keeps relationship lines connected to a visible node', () => {
    const edges = [
        {
            id: 'inside',
            source: 'left',
            target: 'right',
            kind: 'hierarchy',
        },
        {
            id: 'outside',
            source: 'left',
            target: 'far-away',
            kind: 'hierarchy',
        },
        {
            id: 'off-screen',
            source: 'far-away',
            target: 'further-away',
            kind: 'hierarchy',
        },
    ] as const;
    const positions = new Map([
        ['left', { x: 10, y: 50 }],
        ['right', { x: 90, y: 50 }],
        ['far-away', { x: 130, y: 50 }],
        ['further-away', { x: 150, y: 50 }],
    ]);

    assert.deepEqual(
        filterBrainEdgesByViewport(edges, positions, {
            minX: 0,
            minY: 0,
            maxX: 100,
            maxY: 100,
        }).map((edge) => edge.id),
        ['inside', 'outside'],
    );
});

test('zooms around the graph position below the pointer', () => {
    assert.deepEqual(
        zoomSecondBrainAtPoint({
            zoom: 1,
            viewCenter: { x: 600, y: 400 },
            pointerX: 0.75,
            pointerY: 0.25,
            zoomDelta: 1,
            minimumZoom: 0.7,
            maximumZoom: 6,
        }),
        {
            zoom: 2,
            viewCenter: { x: 750, y: 300 },
        },
    );
});

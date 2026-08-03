<?php

test('second brain batches svg connections into compound paths', function () {
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/second-brain.tsx',
    );
    $stylesheet = file_get_contents(
        dirname(__DIR__, 2).'/resources/css/app.css',
    );

    expect($component)
        ->toContain('buildBrainEdgePaths')
        ->toContain('edgePaths.map((edgePath)')
        ->toContain('d={edgePath.data}')
        ->toContain('pointerEvents="none"')
        ->not->toContain('<line')
        ->and($stylesheet)
        ->not->toContain('x1: var(--brain-x1)')
        ->not->toContain('y1: var(--brain-y1)');
});

test('second brain adds subtle idle motion and travelling connection signals', function () {
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/second-brain.tsx',
    );
    $stylesheet = file_get_contents(
        dirname(__DIR__, 2).'/resources/css/app.css',
    );

    expect($component)
        ->toContain('buildBrainSignals(viewportEdges, positions)')
        ->toContain('buildBrainEdgeFlickers(viewportEdges, positions)')
        ->toContain('<animateMotion')
        ->toContain('keyPoints="0;0;1;1"')
        ->toContain('second-brain-node--sway')
        ->toContain('brainNodeSway(node)')
        ->and($stylesheet)
        ->toContain('@keyframes second-brain-node-sway')
        ->toContain('@keyframes second-brain-edge-flicker')
        ->toContain('.second-brain-edge-flicker')
        ->toContain('.second-brain-signal')
        ->toContain('prefers-reduced-motion: reduce')
        ->toContain('animation: none')
        ->toContain('display: none');
});

test('second brain supports close inspection with stronger zoom controls', function () {
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/second-brain.tsx',
    );

    expect($component)
        ->toContain('const maximumBrainZoom = 6;')
        ->toContain('const brainSemanticZoomThreshold = 4.5;')
        ->toContain('const brainZoomStep = 0.25')
        ->toContain('const brainWheelZoomStep = 0.18')
        ->toContain('title="Zoom in to inspect individual connections"');
});

test('second brain semantically shrinks nodes and labels when zooming in', function () {
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/second-brain.tsx',
    );

    expect($component)
        ->toContain('zoom <= brainSemanticZoomThreshold')
        ->toContain('(zoom / brainSemanticZoomThreshold) ** -0.6')
        ->toContain('Math.max(16, node.size * 2.1) *')
        ->toContain('r={node.size * 2.05 * nodeZoomScale}')
        ->toContain('r={nodeRadius}')
        ->toContain('fontSize: `${labelFontSize}px`')
        ->toContain('stdDeviation={4 * nodeZoomScale}')
        ->toContain('className="fill-[#07111d]/90"')
        ->not->toContain('[stroke:#07111d]')
        ->not->toContain('[paint-order:stroke]');
});

test('second brain keeps lines connected to nodes in the zoomed viewport', function () {
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/second-brain.tsx',
    );

    expect($component)
        ->toContain('filterBrainEdgesByViewport')
        ->toContain('const viewportEdges = useMemo(')
        ->toContain('edges: viewportEdges')
        ->toContain('transform={`translate(${position.x} ${position.y})`}')
        ->not->toContain("'--brain-x':")
        ->toContain('buildBrainSignals(viewportEdges, positions)')
        ->toContain('buildBrainEdgeFlickers(viewportEdges, positions)');
});

test('second brain keeps wheel zoom anchored to the pointer', function () {
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/second-brain.tsx',
    );

    expect($component)
        ->toContain('zoomSecondBrainAtPoint')
        ->toContain('pointerX')
        ->toContain('pointerY')
        ->toContain('setViewCenter(nextView.viewCenter)');
});

test('second brain uses a closable fixed detail panel only for selected nodes', function () {
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/second-brain.tsx',
    );

    expect($component)
        ->toContain('<BrainSelectionPanel')
        ->toContain('validSelectedNodeId &&')
        ->toContain('onClose={() => setSelectedNodeId(null)}')
        ->toContain('aria-label={`${node.label} details`}')
        ->toContain('<SyntaxHighlightedCode')
        ->toContain('<BrainFileBrowser')
        ->toContain('buildBrainFileBrowser(node, view)')
        ->toContain("if (node.kind === 'category')")
        ->toContain("if (node.kind === 'framework')")
        ->not->toContain('<NodePeek');
});

test('second brain selection panel supports directory back navigation and resizing', function () {
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/second-brain.tsx',
    );

    expect($component)
        ->toContain('function buildBrainParent(')
        ->toContain('Back to {parent.label}')
        ->toContain('id="second-brain-selection-panel"')
        ->toContain('label="Resize selected item details"')
        ->toContain('codepilot.second-brain.selection-panel-width.v1.')
        ->toContain('restoreWorkspacePanelWidth(');
});

test('second brain strengthens favourite and higher-usage connections', function () {
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/second-brain.tsx',
    );
    $graph = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/lib/snippets/second-brain-graph.ts',
    );

    expect($component)
        ->toContain('connectionStrength * 0.7')
        ->toContain("strokeDasharray: isFavouriteConnection ? '11 5' : undefined")
        ->toContain('nodes: nodeById')
        ->and($graph)
        ->toContain('connectionStrength: snippet.usage.relative_score')
        ->toContain('isFavourite: snippet.is_favourite');
});

test('second brain supports category comparison with the selected node drawer', function () {
    $component = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/second-brain.tsx',
    );

    expect($component)
        ->toContain("{ id: 'single', label: 'One'")
        ->toContain("{ id: 'split', label: 'Split'")
        ->toContain("{ id: 'quad', label: 'Four'")
        ->toContain('<BrainSelectionPanel')
        ->toContain('categoryViews.map((categoryView)')
        ->toContain("{ value: 3, label: '3 hops' }")
        ->toContain("{ value: 6, label: '6 hops' }")
        ->toContain("{ value: 'all', label: 'All' }")
        ->toContain('filterSecondBrainGraphByDepth')
        ->toContain('Relationship depth in pane')
        ->toContain('data-second-brain-search')
        ->toContain('hasPointerCapture(event.pointerId)')
        ->not->toContain('label="Connections"')
        ->not->toContain('label="Contains"')
        ->not->toContain('Connected information');
});

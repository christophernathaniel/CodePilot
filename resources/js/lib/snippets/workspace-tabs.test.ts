import assert from 'node:assert/strict';
import test from 'node:test';
import {
    closeUnpinnedWorkspaceTabs,
    openWorkspaceSnippet,
    reorderWorkspaceTabs,
    restoreMultiFileMode,
    restrictWorkspaceTabsToSingleFile,
} from './workspace-tabs.ts';

test('close all preserves pinned workspace tabs', () => {
    assert.deepEqual(
        closeUnpinnedWorkspaceTabs({
            openIds: [1, 2, 3],
            activeId: 2,
            pinnedIds: [1, 3],
        }),
        {
            openIds: [1, 3],
            activeId: 3,
            pinnedIds: [1, 3],
        },
    );
});

test('close all keeps the active pinned workspace tab selected', () => {
    assert.deepEqual(
        closeUnpinnedWorkspaceTabs({
            openIds: [1, 2, 3],
            activeId: 1,
            pinnedIds: [1],
        }),
        {
            openIds: [1],
            activeId: 1,
            pinnedIds: [1],
        },
    );
});

test('workspace tabs can be reordered before another tab', () => {
    assert.deepEqual(
        reorderWorkspaceTabs([1, 2, 3, 4], 4, 2, 'before'),
        [1, 4, 2, 3],
    );
});

test('workspace tabs can be reordered after another tab', () => {
    assert.deepEqual(
        reorderWorkspaceTabs([1, 2, 3, 4], 1, 3, 'after'),
        [2, 3, 1, 4],
    );
});

test('invalid workspace tab reorders preserve the existing state', () => {
    const openIds = [1, 2, 3];

    assert.equal(reorderWorkspaceTabs(openIds, 2, 2, 'before'), openIds);
    assert.equal(reorderWorkspaceTabs(openIds, 9, 2, 'before'), openIds);
    assert.equal(reorderWorkspaceTabs(openIds, 2, 9, 'after'), openIds);
});

test('multi-file mode opens another tab without closing existing tabs', () => {
    assert.deepEqual(openWorkspaceSnippet([1, 2], [1], 3, true), [1, 2, 3]);
});

test('single-file mode replaces unpinned tabs while preserving pinned tabs', () => {
    assert.deepEqual(
        openWorkspaceSnippet([1, 2, 3], [1, 3], 4, false),
        [1, 3, 4],
    );
    assert.deepEqual(openWorkspaceSnippet([1, 2, 3], [1, 3], 3, false), [1, 3]);
});

test('entering single-file mode keeps pinned tabs and the active file', () => {
    assert.deepEqual(
        restrictWorkspaceTabsToSingleFile({
            openIds: [1, 2, 3, 4],
            activeId: 2,
            pinnedIds: [1, 3],
        }),
        {
            openIds: [1, 2, 3],
            activeId: 2,
            pinnedIds: [1, 3],
        },
    );
});

test('multi-file mode defaults on and restores an explicit preference', () => {
    assert.equal(restoreMultiFileMode(null), true);
    assert.equal(restoreMultiFileMode({}), true);
    assert.equal(restoreMultiFileMode({ multiFileMode: false }), false);
    assert.equal(restoreMultiFileMode({ multiFileMode: true }), true);
});

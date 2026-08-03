import assert from 'node:assert/strict';
import test from 'node:test';
import { reorderWorkspaceIds } from './workspace-order.ts';

test('moves a workspace before another workspace', () => {
    assert.deepEqual(
        reorderWorkspaceIds([1, 2, 3, 4], 4, 2, 'before'),
        [1, 4, 2, 3],
    );
});

test('moves a workspace after another workspace', () => {
    assert.deepEqual(
        reorderWorkspaceIds([1, 2, 3, 4], 1, 3, 'after'),
        [2, 3, 1, 4],
    );
});

test('moves workspaces to the first and last positions', () => {
    assert.deepEqual(
        reorderWorkspaceIds([1, 2, 3, 4], 4, 1, 'before'),
        [4, 1, 2, 3],
    );
    assert.deepEqual(
        reorderWorkspaceIds([1, 2, 3, 4], 1, 4, 'after'),
        [2, 3, 4, 1],
    );
});

test('semantic no-ops preserve the existing order', () => {
    const ids = [1, 2, 3, 4];

    assert.deepEqual(reorderWorkspaceIds(ids, 2, 3, 'before'), ids);
    assert.deepEqual(reorderWorkspaceIds(ids, 3, 2, 'after'), ids);
    assert.deepEqual(reorderWorkspaceIds(ids, 2, 2, 'before'), ids);
});

test('unknown workspace ids preserve the existing order', () => {
    const ids = [1, 2, 3, 4];

    assert.deepEqual(reorderWorkspaceIds(ids, 9, 2, 'before'), ids);
    assert.deepEqual(reorderWorkspaceIds(ids, 2, 9, 'after'), ids);
});

test('reordering does not mutate its input', () => {
    const ids = Object.freeze([1, 2, 3, 4]);
    const reorderedIds = reorderWorkspaceIds(ids, 4, 2, 'before');

    assert.deepEqual(ids, [1, 2, 3, 4]);
    assert.deepEqual(reorderedIds, [1, 4, 2, 3]);
    assert.notEqual(reorderedIds, ids);
});

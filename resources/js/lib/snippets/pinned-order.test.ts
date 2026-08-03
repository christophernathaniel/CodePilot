import assert from 'node:assert/strict';
import test from 'node:test';
import { sortPinnedFirst } from './pinned-order.ts';

test('moves pinned items first while preserving the order of each group', () => {
    const items = [
        { id: 1, label: 'First' },
        { id: 2, label: 'Second' },
        { id: 3, label: 'Third' },
        { id: 4, label: 'Fourth' },
    ];
    const pinnedIds = new Set([2, 4]);

    const sortedItems = sortPinnedFirst(items, (item) =>
        pinnedIds.has(item.id),
    );

    assert.deepEqual(
        sortedItems.map((item) => item.id),
        [2, 4, 1, 3],
    );
    assert.deepEqual(
        items.map((item) => item.id),
        [1, 2, 3, 4],
    );
});

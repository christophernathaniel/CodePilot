import assert from 'node:assert/strict';
import test from 'node:test';
import { createClipboardSelection } from './clipboard-selection.ts';

test('normalises reversed offsets and returns the selected content', () => {
    assert.deepEqual(createClipboardSelection('alpha\nbeta\ngamma', 10, 2), {
        content: 'pha\nbeta',
        startOffset: 2,
        endOffset: 10,
        startLine: 1,
        endLine: 2,
    });
});

test('clamps offsets to the source bounds', () => {
    assert.deepEqual(createClipboardSelection('alpha\nbeta', -10, 99), {
        content: 'alpha\nbeta',
        startOffset: 0,
        endOffset: 10,
        startLine: 1,
        endLine: 2,
    });
});

test('reports inclusive lines when the selection ends at a newline', () => {
    const source = 'alpha\nbeta\ngamma';

    assert.deepEqual(createClipboardSelection(source, 0, 6), {
        content: 'alpha\n',
        startOffset: 0,
        endOffset: 6,
        startLine: 1,
        endLine: 1,
    });
    assert.deepEqual(createClipboardSelection(source, 2, 11), {
        content: 'pha\nbeta\n',
        startOffset: 2,
        endOffset: 11,
        startLine: 1,
        endLine: 2,
    });
    assert.deepEqual(createClipboardSelection(source, 6, 11), {
        content: 'beta\n',
        startOffset: 6,
        endOffset: 11,
        startLine: 2,
        endLine: 2,
    });
});

test('returns null for an empty selection', () => {
    assert.equal(createClipboardSelection('alpha', 3, 3), null);
});

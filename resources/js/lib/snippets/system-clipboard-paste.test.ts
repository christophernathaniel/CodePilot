import assert from 'node:assert/strict';
import test from 'node:test';
import { readClipboardText } from './system-clipboard-paste.ts';

test('prefers the plain text clipboard representation', () => {
    const clipboardData = {
        getData: (format: string) =>
            format === 'text/plain' ? 'Plain text' : '<p>Rich text</p>',
    };

    assert.equal(readClipboardText(clipboardData), 'Plain text');
});

test('falls back to HTML when the clipboard has no plain text', () => {
    const clipboardData = {
        getData: (format: string) =>
            format === 'text/html' ? '<p>Rich text</p>' : '',
    };

    assert.equal(readClipboardText(clipboardData), '<p>Rich text</p>');
    assert.equal(readClipboardText(null), '');
});

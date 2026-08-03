import assert from 'node:assert/strict';
import test from 'node:test';
import {
    editorOnlyModeShortcutLabel,
    isEditorOnlyModeShortcut,
} from './editor-only-mode.ts';
import type { KeyboardShortcutEvent } from './editor-only-mode.ts';

function keyboardEvent(
    overrides: Partial<KeyboardShortcutEvent> = {},
): KeyboardShortcutEvent {
    return {
        altKey: false,
        ctrlKey: false,
        key: 'e',
        metaKey: false,
        shiftKey: true,
        ...overrides,
    };
}

test('matches the editor-only mode shortcut on macOS and elsewhere', () => {
    assert.equal(
        isEditorOnlyModeShortcut(keyboardEvent({ metaKey: true })),
        true,
    );
    assert.equal(
        isEditorOnlyModeShortcut(keyboardEvent({ ctrlKey: true })),
        true,
    );
});

test('does not claim incomplete or conflicting shortcuts', () => {
    assert.equal(
        isEditorOnlyModeShortcut(
            keyboardEvent({ metaKey: true, shiftKey: false }),
        ),
        false,
    );
    assert.equal(
        isEditorOnlyModeShortcut(
            keyboardEvent({ altKey: true, ctrlKey: true }),
        ),
        false,
    );
    assert.equal(
        isEditorOnlyModeShortcut(keyboardEvent({ key: 'k', metaKey: true })),
        false,
    );
});

test('formats the shortcut for the current platform', () => {
    assert.equal(editorOnlyModeShortcutLabel('MacIntel'), '⌘⇧E');
    assert.equal(editorOnlyModeShortcutLabel('Win32'), 'Ctrl+Shift+E');
});

export type KeyboardShortcutEvent = Pick<
    KeyboardEvent,
    'altKey' | 'ctrlKey' | 'key' | 'metaKey' | 'shiftKey'
>;

export function isEditorOnlyModeShortcut(
    event: KeyboardShortcutEvent,
): boolean {
    return (
        (event.metaKey || event.ctrlKey) &&
        event.shiftKey &&
        !event.altKey &&
        event.key.toLowerCase() === 'e'
    );
}

export function editorOnlyModeShortcutLabel(platform: string): string {
    return /mac|iphone|ipad|ipod/iu.test(platform) ? '⌘⇧E' : 'Ctrl+Shift+E';
}

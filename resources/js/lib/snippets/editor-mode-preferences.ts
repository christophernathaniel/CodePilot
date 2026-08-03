import type { EditorMode } from '@/components/snippets/snippet-editor-chrome';

export type EditorModePreferenceScope =
    'guide' | 'project' | 'bundle' | 'standalone';

export type EditorModePreferences = Record<
    EditorModePreferenceScope,
    EditorMode
>;

export type EditorModePreferenceSnippet = {
    content_type: 'snippet' | 'guide';
};

export type EditorModePreferenceProject = {
    kind: 'project' | 'bundle' | 'guide';
};

export const defaultEditorModePreferences: EditorModePreferences = {
    guide: 'source',
    project: 'source',
    bundle: 'source',
    standalone: 'source',
};

export function restoreEditorModePreferences(
    storedValue: unknown,
): EditorModePreferences {
    if (!isRecord(storedValue)) {
        return { ...defaultEditorModePreferences };
    }

    return {
        guide: validMode(storedValue.guide, 'guide'),
        project: validMode(storedValue.project, 'project'),
        bundle: validMode(storedValue.bundle, 'bundle'),
        standalone: validMode(storedValue.standalone, 'standalone'),
    };
}

export function editorModePreferenceScope(
    snippet: EditorModePreferenceSnippet,
    project: EditorModePreferenceProject | null,
): EditorModePreferenceScope {
    if (snippet.content_type === 'guide') {
        return 'guide';
    }

    if (project?.kind === 'bundle') {
        return 'bundle';
    }

    if (project !== null) {
        return 'project';
    }

    return 'standalone';
}

export function updateEditorModePreference(
    preferences: EditorModePreferences,
    snippet: EditorModePreferenceSnippet,
    project: EditorModePreferenceProject | null,
    mode: EditorMode,
): EditorModePreferences {
    const scope = editorModePreferenceScope(snippet, project);

    return {
        ...preferences,
        [scope]: validMode(mode, scope),
    };
}

function validMode(
    value: unknown,
    scope: EditorModePreferenceScope,
): EditorMode {
    if (value === 'source' || value === 'preview') {
        return value;
    }

    if (scope === 'guide' && value === 'playback') {
        return value;
    }

    return 'source';
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

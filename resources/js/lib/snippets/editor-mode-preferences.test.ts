import assert from 'node:assert/strict';
import test from 'node:test';
import {
    defaultEditorModePreferences,
    editorModePreferenceScope,
    restoreEditorModePreferences,
    updateEditorModePreference,
} from './editor-mode-preferences.ts';

test('restores only modes allowed by each collection type', () => {
    assert.deepEqual(
        restoreEditorModePreferences({
            guide: 'playback',
            project: 'preview',
            bundle: 'playback',
            standalone: 'invalid',
            ignored: 'preview',
        }),
        {
            guide: 'playback',
            project: 'preview',
            bundle: 'source',
            standalone: 'source',
        },
    );
    assert.deepEqual(
        restoreEditorModePreferences(null),
        defaultEditorModePreferences,
    );
    assert.deepEqual(
        restoreEditorModePreferences(['playback']),
        defaultEditorModePreferences,
    );
});

test('prefers guide content type before its containing collection kind', () => {
    assert.equal(
        editorModePreferenceScope(
            { content_type: 'guide' },
            { kind: 'bundle' },
        ),
        'guide',
    );
    assert.equal(
        editorModePreferenceScope(
            { content_type: 'snippet' },
            { kind: 'bundle' },
        ),
        'bundle',
    );
    assert.equal(
        editorModePreferenceScope(
            { content_type: 'snippet' },
            { kind: 'project' },
        ),
        'project',
    );
    assert.equal(
        editorModePreferenceScope(
            { content_type: 'snippet' },
            { kind: 'guide' },
        ),
        'project',
    );
    assert.equal(
        editorModePreferenceScope({ content_type: 'snippet' }, null),
        'standalone',
    );
});

test('updates one shared collection preference without changing the others', () => {
    const guidePreferences = updateEditorModePreference(
        defaultEditorModePreferences,
        { content_type: 'guide' },
        { kind: 'guide' },
        'playback',
    );
    const projectPreferences = updateEditorModePreference(
        guidePreferences,
        { content_type: 'snippet' },
        { kind: 'project' },
        'preview',
    );

    assert.deepEqual(projectPreferences, {
        guide: 'playback',
        project: 'preview',
        bundle: 'source',
        standalone: 'source',
    });
    assert.deepEqual(
        updateEditorModePreference(
            projectPreferences,
            { content_type: 'snippet' },
            { kind: 'guide' },
            'playback',
        ),
        {
            guide: 'playback',
            project: 'source',
            bundle: 'source',
            standalone: 'source',
        },
    );
});

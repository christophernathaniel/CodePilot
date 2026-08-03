import assert from 'node:assert/strict';
import test from 'node:test';
import type { ClipboardSession, LanguageOption } from '../../types/snippets.ts';
import {
    canCreateClipboardFile,
    getClipboardFileDefaults,
    getClipboardFileLanguage,
} from './clipboard-file.ts';

const languageOptions: LanguageOption[] = [
    {
        value: 'plaintext',
        label: 'Plain Text',
        aliases: ['text', 'txt'],
        syntax: 'plaintext',
        extensions: ['txt'],
        is_pinned: false,
    },
    {
        value: 'php',
        label: 'PHP',
        aliases: [],
        syntax: 'php',
        extensions: ['php'],
        is_pinned: false,
    },
    {
        value: 'typescript',
        label: 'TypeScript',
        aliases: ['ts'],
        syntax: 'typescript',
        extensions: ['ts'],
        is_pinned: false,
    },
];

test('uses the clipboard name and shared clip language for file defaults', () => {
    const clipboard = createClipboard('API Helpers', ['php', 'php']);

    assert.deepEqual(getClipboardFileDefaults(clipboard, languageOptions), {
        title: 'API Helpers',
        filename: 'api-helpers.php',
        language: 'php',
        content_type: 'snippet',
    });
});

test('uses plain text defaults when clipboard clips have mixed languages', () => {
    const clipboard = createClipboard('Mixed utilities', ['php', 'typescript']);

    assert.deepEqual(getClipboardFileDefaults(clipboard, languageOptions), {
        title: 'Mixed utilities',
        filename: 'mixed-utilities.txt',
        language: 'plaintext',
        content_type: 'snippet',
    });
});

test('does not duplicate an existing primary extension', () => {
    const clipboard = createClipboard('helpers.php', ['php']);

    assert.equal(
        getClipboardFileDefaults(clipboard, languageOptions).filename,
        'helpers.php',
    );
});

test('guide defaults use markdown and switching back restores the clip language', () => {
    const clipboard = createClipboard('PHP guide', ['php', 'php']);

    assert.deepEqual(
        getClipboardFileDefaults(clipboard, languageOptions, 'guide'),
        {
            title: 'PHP guide',
            filename: 'php-guide.md',
            language: 'markdown',
            content_type: 'guide',
        },
    );
    assert.equal(getClipboardFileLanguage(clipboard, 'guide'), 'markdown');
    assert.equal(getClipboardFileLanguage(clipboard, 'snippet'), 'php');
});

test('only allows non-empty clipboards to create files', () => {
    assert.equal(canCreateClipboardFile(createClipboard('Empty', [])), false);
    assert.equal(
        canCreateClipboardFile(createClipboard('Ready', ['php'])),
        true,
    );
});

function createClipboard(name: string, languages: string[]): ClipboardSession {
    return {
        id: 1,
        name,
        is_active: true,
        clips_count: languages.length,
        clips: languages.map((language, index) => ({
            id: index + 1,
            content: `clip ${index + 1}`,
            language,
            representation: 'source',
            source: {
                snippet_id: index + 1,
                variation_id: index + 1,
                title: `Snippet ${index + 1}`,
                filename: `snippet-${index + 1}.txt`,
                project: null,
                folders: [],
                variation: 'Default',
                line_start: 1,
                line_end: 1,
            },
            created_at: '2026-08-02T09:00:00+00:00',
        })),
        created_at: '2026-08-02T09:00:00+00:00',
        updated_at: '2026-08-02T09:00:00+00:00',
    };
}

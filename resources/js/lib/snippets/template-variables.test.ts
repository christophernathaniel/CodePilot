import assert from 'node:assert/strict';
import test from 'node:test';
import {
    resolveTemplate,
    resolveTemplatePreview,
} from './template-variables.ts';

test('search previews show the declared defaults for the PHP foreach recipe', () => {
    const source = `<?php

foreach ({{{collection:$items}}} as {{{key:$key}}} => {{{value:$value}}}) {
    printf(
        '<li data-key="%s">%s</li>',
        htmlspecialchars((string) {{{key:$key}}}, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars((string) {{{value:$value}}}, ENT_QUOTES, 'UTF-8'),
    );
}`;
    const matchStart = source.indexOf('foreach');
    const preview = resolveTemplatePreview(source, {
        start: matchStart,
        end: matchStart + 'foreach'.length,
    });

    assert.match(preview.source, /foreach \(\$items as \$key => \$value\)/u);
    assert.doesNotMatch(preview.source, /\{\{\{/u);
    assert.deepEqual(preview.highlightRange, {
        start: preview.source.indexOf('foreach'),
        end: preview.source.indexOf('foreach') + 'foreach'.length,
    });
});

test('template resolution supports overrides and intentional empty values', () => {
    assert.equal(
        resolveTemplate(
            '{{{greeting:Hello}}}, {{{name:World}}}!{{{suffix:!}}}',
            {
                name: 'Chris',
                suffix: '',
            },
        ),
        'Hello, Chris!',
    );
});

test('search preview highlights stay aligned after earlier variables shrink', () => {
    const source = '{{{bootstrap:require_once vendor/autoload.php;}}}\nforeach';
    const matchStart = source.indexOf('foreach');
    const preview = resolveTemplatePreview(source, {
        start: matchStart,
        end: matchStart + 'foreach'.length,
    });

    assert.equal(preview.source, 'require_once vendor/autoload.php;\nforeach');
    assert.deepEqual(preview.highlightRange, {
        start: preview.source.indexOf('foreach'),
        end: preview.source.length,
    });
});

test('search preview drops matches that only exist in variable syntax', () => {
    const source = '{{{collection:$items}}}';
    const matchStart = source.indexOf('collection');
    const preview = resolveTemplatePreview(source, {
        start: matchStart,
        end: matchStart + 'collection'.length,
    });

    assert.equal(preview.source, '$items');
    assert.equal(preview.highlightRange, null);
});

<?php

use Symfony\Component\Process\Process;

test('workspace tabs restore pins and close tabs predictably', function () {
    $script = <<<'JS'
        import assert from 'node:assert/strict';
        import {
            closeWorkspaceTabs,
            restoreWorkspaceTabs,
            togglePinnedSnippet,
        } from './resources/js/lib/snippets/workspace-tabs.ts';

        const restored = restoreWorkspaceTabs({
            openIds: [2, 1, 2, 99],
            activeId: 99,
            pinnedIds: [1, 99, 1],
        }, new Set([1, 2, 3]));

        assert.deepEqual(restored, {
            openIds: [2, 1],
            activeId: 1,
            pinnedIds: [1],
        });

        assert.deepEqual(closeWorkspaceTabs({
            openIds: [1, 2, 3],
            activeId: 2,
            pinnedIds: [1, 2],
        }, [2]), {
            openIds: [1, 3],
            activeId: 3,
            pinnedIds: [1],
        });

        assert.deepEqual(togglePinnedSnippet([1], 2), [1, 2]);
        assert.deepEqual(togglePinnedSnippet([1, 2], 1), [2]);

        assert.deepEqual(restoreWorkspaceTabs({
            openIds: [3],
            activeId: 3,
        }, new Set([3])), {
            openIds: [3],
            activeId: 3,
            pinnedIds: [],
        });
        JS;

    $process = new Process(
        ['node', '--input-type=module', '--eval', $script],
        dirname(__DIR__, 2),
    );
    $process->run();

    expect($process->getExitCode())
        ->toBe(0)
        ->and($process->getErrorOutput())->toBe('');
});

test('tab bar uses compact controls without structural borders', function () {
    $tabBar = file_get_contents(
        dirname(__DIR__, 2).'/resources/js/components/snippets/editor-tab-bar.tsx',
    );

    expect($tabBar)
        ->toContain("'flex size-8 shrink-0 items-center justify-center")
        ->not->toContain("{editorOnlyMode ? 'Show UI' : 'Editor only'}")
        ->not->toContain('<kbd')
        ->not->toContain('border-b border-code-border bg-code-canvas')
        ->not->toContain("hasRegularSnippets && 'border-b border-code-border'")
        ->not->toContain('items-stretch border-r border-code-border')
        ->not->toContain('self-start border-l border-code-border');
});

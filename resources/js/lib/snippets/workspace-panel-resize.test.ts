import assert from 'node:assert/strict';
import test from 'node:test';
import {
    clampWorkspacePanelWidth,
    restoreWorkspacePanelWidth,
    workspacePanelMaximumWidth,
    workspacePanelWidthFromPointer,
} from './workspace-panel-resize.ts';

test('clamps panel widths to their configured bounds', () => {
    assert.equal(clampWorkspacePanelWidth(320, 240, 480), 320);
    assert.equal(clampWorkspacePanelWidth(180, 240, 480), 240);
    assert.equal(clampWorkspacePanelWidth(560, 240, 480), 480);
});

test('applies pointer movement to a left panel width', () => {
    assert.equal(
        workspacePanelWidthFromPointer(300, 400, 460, 'left', 240, 480),
        360,
    );
    assert.equal(
        workspacePanelWidthFromPointer(300, 400, 250, 'left', 240, 480),
        240,
    );
});

test('reverses pointer movement for a right panel width', () => {
    assert.equal(
        workspacePanelWidthFromPointer(300, 400, 340, 'right', 240, 480),
        360,
    );
    assert.equal(
        workspacePanelWidthFromPointer(300, 400, 550, 'right', 240, 480),
        240,
    );
});

test('restores finite persisted widths and clamps stale values', () => {
    assert.equal(restoreWorkspacePanelWidth(360, 300, 240, 480), 360);
    assert.equal(restoreWorkspacePanelWidth('420', 300, 240, 480), 420);
    assert.equal(restoreWorkspacePanelWidth('999', 300, 240, 480), 480);
});

test('falls back safely when a persisted width is invalid', () => {
    for (const invalidValue of [
        null,
        undefined,
        '',
        'not-a-width',
        Number.NaN,
        Number.POSITIVE_INFINITY,
        {},
    ]) {
        assert.equal(
            restoreWorkspacePanelWidth(invalidValue, 300, 240, 480),
            300,
        );
    }

    assert.equal(restoreWorkspacePanelWidth(null, 900, 240, 480), 480);
});

test('reserves room for the centre editor when panels are docked', () => {
    assert.equal(workspacePanelMaximumWidth(1280, 308, 560, 240, 440), 412);
    assert.equal(workspacePanelMaximumWidth(1280, 488, 560, 260, 440), 260);
    assert.equal(workspacePanelMaximumWidth(1024, 48, 560, 240, 440), 416);
    assert.equal(workspacePanelMaximumWidth(1600, 488, 560, 260, 440), 440);
});

test('uses the configured maximum until the viewport is known', () => {
    assert.equal(workspacePanelMaximumWidth(null, 48, 560, 240, 440), 440);
    assert.equal(
        workspacePanelMaximumWidth(Number.NaN, 48, 560, 240, 440),
        440,
    );
});

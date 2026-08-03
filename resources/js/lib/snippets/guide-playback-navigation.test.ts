import assert from 'node:assert/strict';
import test from 'node:test';
import {
    emptyGuideWheelIntent,
    resolveGuideWheelNavigation,
} from './guide-playback-navigation.ts';

const baseInput = {
    deltaX: 0,
    deltaY: 36,
    deltaMode: 0,
    viewportHeight: 600,
    atStart: false,
    atEnd: true,
    activeIndex: 1,
    stepCount: 4,
    timeStamp: 100,
};

test('advances only after continued scrolling beyond the current step', () => {
    const firstEvent = resolveGuideWheelNavigation(
        baseInput,
        emptyGuideWheelIntent(),
    );

    assert.equal(firstEvent.nextIndex, null);
    assert.deepEqual(firstEvent.intent, {
        direction: 1,
        distance: 36,
        time: 100,
    });

    const secondEvent = resolveGuideWheelNavigation(
        { ...baseInput, deltaY: 40, timeStamp: 160 },
        firstEvent.intent,
    );

    assert.equal(secondEvent.nextIndex, 2);
});

test('moves back at the top and respects the first and final steps', () => {
    const previous = resolveGuideWheelNavigation(
        {
            ...baseInput,
            deltaY: -80,
            atStart: true,
            atEnd: false,
            activeIndex: 2,
        },
        emptyGuideWheelIntent(),
    );
    const beforeFirst = resolveGuideWheelNavigation(
        {
            ...baseInput,
            deltaY: -80,
            atStart: true,
            atEnd: false,
            activeIndex: 0,
        },
        emptyGuideWheelIntent(),
    );
    const afterLast = resolveGuideWheelNavigation(
        { ...baseInput, deltaY: 80, activeIndex: 3 },
        emptyGuideWheelIntent(),
    );

    assert.equal(previous.nextIndex, 1);
    assert.equal(beforeFirst.nextIndex, null);
    assert.equal(afterLast.nextIndex, null);
});

test('does not navigate before the boundary or for horizontal gestures', () => {
    const withinStep = resolveGuideWheelNavigation(
        { ...baseInput, deltaY: 100, atEnd: false },
        { direction: 1, distance: 60, time: 80 },
    );
    const horizontal = resolveGuideWheelNavigation(
        { ...baseInput, deltaX: 100, deltaY: 80 },
        emptyGuideWheelIntent(),
    );

    assert.deepEqual(withinStep, {
        intent: emptyGuideWheelIntent(),
        nextIndex: null,
    });
    assert.equal(horizontal.nextIndex, null);
});

test('normalises line scrolling and expires an old gesture', () => {
    const lineScroll = resolveGuideWheelNavigation(
        { ...baseInput, deltaY: 5, deltaMode: 1 },
        emptyGuideWheelIntent(),
    );
    const expiredGesture = resolveGuideWheelNavigation(
        { ...baseInput, deltaY: 20, timeStamp: 700 },
        { direction: 1, distance: 60, time: 100 },
    );

    assert.equal(lineScroll.nextIndex, 2);
    assert.equal(expiredGesture.nextIndex, null);
    assert.equal(expiredGesture.intent.distance, 20);
});

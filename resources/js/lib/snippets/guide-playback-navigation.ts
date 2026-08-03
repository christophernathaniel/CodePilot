export type GuideWheelIntent = {
    direction: -1 | 0 | 1;
    distance: number;
    time: number;
};

type GuideWheelNavigationInput = {
    deltaX: number;
    deltaY: number;
    deltaMode: number;
    viewportHeight: number;
    atStart: boolean;
    atEnd: boolean;
    activeIndex: number;
    stepCount: number;
    timeStamp: number;
};

type GuideWheelNavigationResult = {
    intent: GuideWheelIntent;
    nextIndex: number | null;
};

const gestureWindow = 420;
const navigationThreshold = 72;

export function emptyGuideWheelIntent(): GuideWheelIntent {
    return { direction: 0, distance: 0, time: 0 };
}

export function resolveGuideWheelNavigation(
    input: GuideWheelNavigationInput,
    previousIntent: GuideWheelIntent,
): GuideWheelNavigationResult {
    if (
        input.deltaY === 0 ||
        Math.abs(input.deltaY) <= Math.abs(input.deltaX) ||
        input.stepCount < 2
    ) {
        return { intent: emptyGuideWheelIntent(), nextIndex: null };
    }

    const delta = normaliseWheelDelta(
        input.deltaY,
        input.deltaMode,
        input.viewportHeight,
    );
    const direction = delta > 0 ? 1 : -1;
    const canMove =
        (direction > 0 &&
            input.atEnd &&
            input.activeIndex < input.stepCount - 1) ||
        (direction < 0 && input.atStart && input.activeIndex > 0);

    if (!canMove) {
        return { intent: emptyGuideWheelIntent(), nextIndex: null };
    }

    const continuesGesture =
        previousIntent.direction === direction &&
        input.timeStamp - previousIntent.time < gestureWindow;
    const distance =
        (continuesGesture ? previousIntent.distance : 0) + Math.abs(delta);
    const intent: GuideWheelIntent = {
        direction,
        distance,
        time: input.timeStamp,
    };

    return {
        intent,
        nextIndex:
            distance >= navigationThreshold
                ? input.activeIndex + direction
                : null,
    };
}

function normaliseWheelDelta(
    deltaY: number,
    deltaMode: number,
    viewportHeight: number,
): number {
    if (deltaMode === 1) {
        return deltaY * 16;
    }

    if (deltaMode === 2) {
        return deltaY * viewportHeight;
    }

    return deltaY;
}

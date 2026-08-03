export type WorkspacePanelSide = 'left' | 'right';

export function clampWorkspacePanelWidth(
    width: number,
    minWidth: number,
    maxWidth: number,
): number {
    const lowerBound = Math.min(minWidth, maxWidth);
    const upperBound = Math.max(minWidth, maxWidth);

    return Math.min(Math.max(width, lowerBound), upperBound);
}

export function workspacePanelWidthFromPointer(
    startWidth: number,
    startClientX: number,
    clientX: number,
    side: WorkspacePanelSide,
    minWidth: number,
    maxWidth: number,
): number {
    const pointerDelta = clientX - startClientX;
    const widthDelta = side === 'left' ? pointerDelta : -pointerDelta;

    return clampWorkspacePanelWidth(
        startWidth + widthDelta,
        minWidth,
        maxWidth,
    );
}

export function restoreWorkspacePanelWidth(
    storedValue: unknown,
    fallbackWidth: number,
    minWidth: number,
    maxWidth: number,
): number {
    const parsedWidth = parseFiniteWidth(storedValue);
    const safeFallback = parseFiniteWidth(fallbackWidth) ?? minWidth;

    return clampWorkspacePanelWidth(
        parsedWidth ?? safeFallback,
        minWidth,
        maxWidth,
    );
}

export function workspacePanelMaximumWidth(
    viewportWidth: number | null,
    reservedWidth: number,
    minimumCenterWidth: number,
    minWidth: number,
    maxWidth: number,
): number {
    if (viewportWidth === null || !Number.isFinite(viewportWidth)) {
        return Math.max(minWidth, maxWidth);
    }

    return clampWorkspacePanelWidth(
        viewportWidth - reservedWidth - minimumCenterWidth,
        minWidth,
        maxWidth,
    );
}

function parseFiniteWidth(value: unknown): number | null {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    const parsedValue = Number(value);

    return Number.isFinite(parsedValue) ? parsedValue : null;
}

import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent, PointerEvent as ReactPointerEvent } from 'react';
import {
    clampWorkspacePanelWidth,
    workspacePanelWidthFromPointer,
} from '@/lib/snippets/workspace-panel-resize';
import type { WorkspacePanelSide } from '@/lib/snippets/workspace-panel-resize';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    controls: string;
    side: WorkspacePanelSide;
    width: number;
    minWidth: number;
    maxWidth: number;
    onResize: (width: number) => void;
    onResizeEnd?: (width: number) => void;
    keyboardStep?: number;
    className?: string;
};

type PointerResize = {
    pointerId: number;
    startClientX: number;
    startWidth: number;
};

let activeBodyResizeLocks = 0;
let originalBodyCursor = '';
let originalBodyUserSelect = '';

function lockBodyResizeStyles(): void {
    if (activeBodyResizeLocks === 0) {
        originalBodyCursor = document.body.style.cursor;
        originalBodyUserSelect = document.body.style.userSelect;
    }

    activeBodyResizeLocks += 1;
    document.body.style.cursor = 'col-resize';
    document.body.style.userSelect = 'none';
}

function unlockBodyResizeStyles(): void {
    activeBodyResizeLocks = Math.max(0, activeBodyResizeLocks - 1);

    if (activeBodyResizeLocks > 0) {
        return;
    }

    document.body.style.cursor = originalBodyCursor;
    document.body.style.userSelect = originalBodyUserSelect;
}

export function WorkspaceResizeHandle({
    label,
    controls,
    side,
    width,
    minWidth,
    maxWidth,
    onResize,
    onResizeEnd,
    keyboardStep = 16,
    className,
}: Props) {
    const [isActive, setIsActive] = useState(false);
    const pointerResizeRef = useRef<PointerResize | null>(null);
    const currentWidthRef = useRef(width);
    const hasBodyResizeLockRef = useRef(false);

    const restoreBodyStyles = () => {
        if (!hasBodyResizeLockRef.current) {
            return;
        }

        hasBodyResizeLockRef.current = false;
        unlockBodyResizeStyles();
    };

    const finishPointerResize = (commit: boolean) => {
        if (!pointerResizeRef.current) {
            return;
        }

        pointerResizeRef.current = null;
        setIsActive(false);
        restoreBodyStyles();

        if (commit) {
            onResizeEnd?.(currentWidthRef.current);
        }
    };

    useEffect(() => {
        currentWidthRef.current = width;
    }, [width]);

    useEffect(() => {
        return () => {
            pointerResizeRef.current = null;
            restoreBodyStyles();
        };
    }, []);

    const resizeTo = (nextWidth: number, commit = false) => {
        const clampedWidth = clampWorkspacePanelWidth(
            nextWidth,
            minWidth,
            maxWidth,
        );

        currentWidthRef.current = clampedWidth;
        onResize(clampedWidth);

        if (commit) {
            onResizeEnd?.(clampedWidth);
        }
    };

    const handlePointerDown = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (event.button !== 0 || pointerResizeRef.current) {
            return;
        }

        event.preventDefault();
        event.currentTarget.setPointerCapture(event.pointerId);
        pointerResizeRef.current = {
            pointerId: event.pointerId,
            startClientX: event.clientX,
            startWidth: clampWorkspacePanelWidth(width, minWidth, maxWidth),
        };
        currentWidthRef.current = pointerResizeRef.current.startWidth;
        hasBodyResizeLockRef.current = true;
        lockBodyResizeStyles();
        setIsActive(true);
    };

    const handlePointerMove = (event: ReactPointerEvent<HTMLDivElement>) => {
        const pointerResize = pointerResizeRef.current;

        if (!pointerResize || pointerResize.pointerId !== event.pointerId) {
            return;
        }

        resizeTo(
            workspacePanelWidthFromPointer(
                pointerResize.startWidth,
                pointerResize.startClientX,
                event.clientX,
                side,
                minWidth,
                maxWidth,
            ),
        );
    };

    const handlePointerEnd = (event: ReactPointerEvent<HTMLDivElement>) => {
        if (pointerResizeRef.current?.pointerId !== event.pointerId) {
            return;
        }

        if (event.currentTarget.hasPointerCapture(event.pointerId)) {
            event.currentTarget.releasePointerCapture(event.pointerId);
        }

        finishPointerResize(true);
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        let nextWidth: number | null = null;

        if (event.key === 'Home') {
            nextWidth = minWidth;
        } else if (event.key === 'End') {
            nextWidth = maxWidth;
        } else if (event.key === 'ArrowLeft') {
            nextWidth =
                width + (side === 'left' ? -keyboardStep : keyboardStep);
        } else if (event.key === 'ArrowRight') {
            nextWidth =
                width + (side === 'left' ? keyboardStep : -keyboardStep);
        }

        if (nextWidth === null) {
            return;
        }

        event.preventDefault();
        resizeTo(nextWidth, true);
    };

    const accessibleWidth = clampWorkspacePanelWidth(width, minWidth, maxWidth);

    return (
        <div
            role="separator"
            aria-label={label}
            aria-controls={controls}
            aria-orientation="vertical"
            aria-valuemin={Math.min(minWidth, maxWidth)}
            aria-valuemax={Math.max(minWidth, maxWidth)}
            aria-valuenow={Math.round(accessibleWidth)}
            tabIndex={0}
            onPointerDown={handlePointerDown}
            onPointerMove={handlePointerMove}
            onPointerUp={handlePointerEnd}
            onPointerCancel={handlePointerEnd}
            onLostPointerCapture={() => finishPointerResize(true)}
            onKeyDown={handleKeyDown}
            className={cn(
                'group relative z-30 -mx-1 flex w-2 shrink-0 cursor-col-resize touch-none items-stretch justify-center outline-none',
                className,
            )}
        >
            <span
                aria-hidden="true"
                className={cn(
                    'pointer-events-none w-px bg-code-border transition-[width,background-color,box-shadow] group-hover:w-0.5 group-hover:bg-code-accent/70 group-focus-visible:w-0.5 group-focus-visible:bg-code-accent group-focus-visible:shadow-[0_0_0_1px_rgba(139,196,232,0.2)]',
                    isActive &&
                        'w-0.5 bg-code-accent shadow-[0_0_12px_rgba(139,196,232,0.38)]',
                )}
            />
        </div>
    );
}

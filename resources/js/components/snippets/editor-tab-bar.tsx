import {
    Maximize2,
    Minimize2,
    Pin,
    PinOff,
    Star,
    X,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { DragEvent, KeyboardEvent, MouseEvent } from 'react';
import { createPortal } from 'react-dom';
import { SnippetFileIcon } from '@/components/snippets/snippet-file-icon';
import type { WorkspaceTabDropPosition } from '@/lib/snippets/workspace-tabs';
import { cn } from '@/lib/utils';
import type { Snippet } from '@/types';

type Props = {
    snippets: Snippet[];
    activeSnippetId: number | null;
    dirtySnippetIds: Set<number>;
    pinnedSnippetIds: Set<number>;
    multiFileMode?: boolean;
    editorOnlyMode?: boolean;
    editorOnlyModeShortcut?: string;
    onActivate: (snippet: Snippet) => void;
    onClose: (snippet: Snippet) => void;
    onCloseAll: () => void;
    onReorder: (
        sourceId: number,
        targetId: number,
        position: WorkspaceTabDropPosition,
    ) => void;
    onToggleFavourite: (snippet: Snippet) => void;
    onTogglePinned: (snippet: Snippet) => void;
    onEditorOnlyModeToggle?: () => void;
};

type ContextMenuState = {
    snippet: Snippet;
    x: number;
    y: number;
};

type DropTargetState = {
    snippetId: number;
    position: WorkspaceTabDropPosition;
};

export function EditorTabBar({
    snippets,
    activeSnippetId,
    dirtySnippetIds,
    pinnedSnippetIds,
    multiFileMode = true,
    editorOnlyMode = false,
    editorOnlyModeShortcut = '',
    onActivate,
    onClose,
    onCloseAll,
    onReorder,
    onToggleFavourite,
    onTogglePinned,
    onEditorOnlyModeToggle,
}: Props) {
    const [contextMenu, setContextMenu] = useState<ContextMenuState | null>(
        null,
    );
    const [draggedSnippetId, setDraggedSnippetId] = useState<number | null>(
        null,
    );
    const [dropTarget, setDropTarget] = useState<DropTargetState | null>(null);
    const contextMenuRef = useRef<HTMLDivElement>(null);
    const draggedSnippetIdRef = useRef<number | null>(null);
    const dropTargetRef = useRef<DropTargetState | null>(null);
    const pinnedSnippets = useMemo(
        () => snippets.filter((snippet) => pinnedSnippetIds.has(snippet.id)),
        [pinnedSnippetIds, snippets],
    );
    const regularSnippets = useMemo(
        () =>
            multiFileMode
                ? snippets.filter(
                      (snippet) => !pinnedSnippetIds.has(snippet.id),
                  )
                : [],
        [multiFileMode, pinnedSnippetIds, snippets],
    );
    const hasPinnedSnippets = pinnedSnippets.length > 0;
    const hasRegularSnippets = regularSnippets.length > 0;
    const editorOnlyModeLabel = editorOnlyMode
        ? 'Show workspace UI'
        : 'Enter editor-only mode';

    useEffect(() => {
        if (!contextMenu) {
            return;
        }

        const focusFrame = window.requestAnimationFrame(() => {
            contextMenuRef.current
                ?.querySelector<HTMLButtonElement>('[role="menuitem"]')
                ?.focus();
        });
        const closeOnPointerDown = (event: PointerEvent) => {
            if (
                event.target instanceof Node &&
                !contextMenuRef.current?.contains(event.target)
            ) {
                setContextMenu(null);
            }
        };
        const closeOnEscape = (event: globalThis.KeyboardEvent) => {
            if (event.key === 'Escape') {
                setContextMenu(null);
            }
        };

        window.addEventListener('pointerdown', closeOnPointerDown);
        window.addEventListener('keydown', closeOnEscape);

        return () => {
            window.cancelAnimationFrame(focusFrame);
            window.removeEventListener('pointerdown', closeOnPointerDown);
            window.removeEventListener('keydown', closeOnEscape);
        };
    }, [contextMenu]);

    const openContextMenu = (
        snippet: Snippet,
        position: { x: number; y: number },
    ) => {
        setContextMenu({ snippet, ...position });
    };

    const handleContextMenu = (
        event: MouseEvent<HTMLDivElement>,
        snippet: Snippet,
    ) => {
        event.preventDefault();
        openContextMenu(snippet, { x: event.clientX, y: event.clientY });
    };

    const handleContextMenuKey = (
        event: KeyboardEvent<HTMLButtonElement>,
        snippet: Snippet,
    ) => {
        if (
            event.key !== 'ContextMenu' &&
            !(event.shiftKey && event.key === 'F10')
        ) {
            return;
        }

        event.preventDefault();
        const bounds = event.currentTarget.getBoundingClientRect();
        openContextMenu(snippet, {
            x: bounds.left + Math.min(bounds.width, 32),
            y: bounds.bottom,
        });
    };

    const finishTabDrag = () => {
        draggedSnippetIdRef.current = null;
        dropTargetRef.current = null;
        setDraggedSnippetId(null);
        setDropTarget(null);
    };

    const handleTabDragStart = (
        event: DragEvent<HTMLDivElement>,
        snippet: Snippet,
    ) => {
        setContextMenu(null);
        draggedSnippetIdRef.current = snippet.id;
        setDraggedSnippetId(snippet.id);
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(snippet.id));
    };

    const handleTabDragOver = (
        event: DragEvent<HTMLDivElement>,
        snippet: Snippet,
    ) => {
        const sourceId = draggedSnippetIdRef.current;

        if (
            sourceId === null ||
            sourceId === snippet.id ||
            pinnedSnippetIds.has(sourceId) !== pinnedSnippetIds.has(snippet.id)
        ) {
            return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        const bounds = event.currentTarget.getBoundingClientRect();
        const position: WorkspaceTabDropPosition =
            event.clientX < bounds.left + bounds.width / 2 ? 'before' : 'after';

        const nextDropTarget = { snippetId: snippet.id, position };

        dropTargetRef.current = nextDropTarget;
        setDropTarget(nextDropTarget);
    };

    const handleTabDrop = (
        event: DragEvent<HTMLDivElement>,
        snippet: Snippet,
    ) => {
        const sourceId = draggedSnippetIdRef.current;
        const currentDropTarget = dropTargetRef.current;

        if (
            sourceId === null ||
            !currentDropTarget ||
            currentDropTarget.snippetId !== snippet.id
        ) {
            finishTabDrag();

            return;
        }

        event.preventDefault();
        onReorder(
            sourceId,
            currentDropTarget.snippetId,
            currentDropTarget.position,
        );
        finishTabDrag();
    };

    const handleTabDragLeave = (
        event: DragEvent<HTMLDivElement>,
        snippet: Snippet,
    ) => {
        if (
            event.relatedTarget instanceof Node &&
            event.currentTarget.contains(event.relatedTarget)
        ) {
            return;
        }

        if (dropTargetRef.current?.snippetId === snippet.id) {
            dropTargetRef.current = null;
            setDropTarget(null);
        }
    };

    return (
        <div
            className={cn(
                'flex shrink-0 bg-code-canvas',
                hasPinnedSnippets && hasRegularSnippets
                    ? 'h-20'
                    : hasPinnedSnippets
                      ? 'h-8'
                      : 'h-12',
            )}
        >
            <div className="flex min-w-0 flex-1 flex-col">
                {hasPinnedSnippets ? (
                    <div
                        aria-label="Pinned tabs"
                        className="flex h-8 shrink-0 overflow-x-auto bg-code-panel/60"
                        role="tablist"
                    >
                        {pinnedSnippets.map((snippet) => (
                            <EditorTab
                                key={snippet.id}
                                snippet={snippet}
                                active={snippet.id === activeSnippetId}
                                dirty={dirtySnippetIds.has(snippet.id)}
                                compact
                                onActivate={onActivate}
                                onClose={onClose}
                                onContextMenu={handleContextMenu}
                                onContextMenuKey={handleContextMenuKey}
                                dragged={draggedSnippetId === snippet.id}
                                dropPosition={
                                    dropTarget?.snippetId === snippet.id
                                        ? dropTarget.position
                                        : null
                                }
                                onDragStart={handleTabDragStart}
                                onDragOver={handleTabDragOver}
                                onDragLeave={handleTabDragLeave}
                                onDrop={handleTabDrop}
                                onDragEnd={finishTabDrag}
                            />
                        ))}
                        <div className="min-w-3 flex-1 bg-code-panel/60" />
                    </div>
                ) : null}

                {hasRegularSnippets ? (
                    <div
                        aria-label="Open tabs"
                        className="flex h-12 min-w-0 flex-1 overflow-x-auto"
                        role="tablist"
                    >
                        {regularSnippets.map((snippet) => (
                            <EditorTab
                                key={snippet.id}
                                snippet={snippet}
                                active={snippet.id === activeSnippetId}
                                dirty={dirtySnippetIds.has(snippet.id)}
                                onActivate={onActivate}
                                onClose={onClose}
                                onContextMenu={handleContextMenu}
                                onContextMenuKey={handleContextMenuKey}
                                onToggleFavourite={onToggleFavourite}
                                dragged={draggedSnippetId === snippet.id}
                                dropPosition={
                                    dropTarget?.snippetId === snippet.id
                                        ? dropTarget.position
                                        : null
                                }
                                onDragStart={handleTabDragStart}
                                onDragOver={handleTabDragOver}
                                onDragLeave={handleTabDragLeave}
                                onDrop={handleTabDrop}
                                onDragEnd={finishTabDrag}
                            />
                        ))}
                        <div className="min-w-6 flex-1 bg-code-canvas" />
                    </div>
                ) : null}
            </div>

            {onEditorOnlyModeToggle ? (
                <button
                    type="button"
                    aria-label={editorOnlyModeLabel}
                    aria-pressed={editorOnlyMode}
                    title={`${editorOnlyModeLabel} (${editorOnlyModeShortcut})`}
                    onClick={onEditorOnlyModeToggle}
                    className={cn(
                        'flex size-8 shrink-0 items-center justify-center self-start transition hover:bg-code-hover hover:text-code-text',
                        editorOnlyMode ? 'text-sky-300' : 'text-code-faint',
                    )}
                >
                    {editorOnlyMode ? (
                        <Minimize2 className="size-3.5" />
                    ) : (
                        <Maximize2 className="size-3.5" />
                    )}
                </button>
            ) : null}

            {contextMenu
                ? createPortal(
                      <div
                          ref={contextMenuRef}
                          role="menu"
                          aria-label={`Actions for ${contextMenu.snippet.filename}`}
                          className="fixed z-100 min-w-44 rounded-md border border-code-border bg-code-raised p-1 text-[11px] text-code-text shadow-2xl"
                          style={contextMenuPosition(contextMenu)}
                      >
                          <button
                              type="button"
                              role="menuitem"
                              onClick={() => {
                                  onTogglePinned(contextMenu.snippet);
                                  setContextMenu(null);
                              }}
                              className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left outline-none hover:bg-code-hover focus:bg-code-hover"
                          >
                              {pinnedSnippetIds.has(contextMenu.snippet.id) ? (
                                  <PinOff className="size-3.5 text-code-muted" />
                              ) : (
                                  <Pin className="size-3.5 text-code-muted" />
                              )}
                              {pinnedSnippetIds.has(contextMenu.snippet.id)
                                  ? 'Unpin tab'
                                  : 'Pin tab'}
                          </button>
                          <div className="my-1 h-px bg-code-border" />
                          <button
                              type="button"
                              role="menuitem"
                              onClick={() => {
                                  onCloseAll();
                                  setContextMenu(null);
                              }}
                              className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-red-300 outline-none hover:bg-red-500/10 focus:bg-red-500/10"
                          >
                              <XCircle className="size-3.5" />
                              Close all
                          </button>
                      </div>,
                      document.body,
                  )
                : null}
        </div>
    );
}

type EditorTabProps = {
    snippet: Snippet;
    active: boolean;
    dirty: boolean;
    compact?: boolean;
    onActivate: (snippet: Snippet) => void;
    onClose: (snippet: Snippet) => void;
    onContextMenu: (
        event: MouseEvent<HTMLDivElement>,
        snippet: Snippet,
    ) => void;
    onContextMenuKey: (
        event: KeyboardEvent<HTMLButtonElement>,
        snippet: Snippet,
    ) => void;
    onToggleFavourite?: (snippet: Snippet) => void;
    dragged: boolean;
    dropPosition: WorkspaceTabDropPosition | null;
    onDragStart: (event: DragEvent<HTMLDivElement>, snippet: Snippet) => void;
    onDragOver: (event: DragEvent<HTMLDivElement>, snippet: Snippet) => void;
    onDragLeave: (event: DragEvent<HTMLDivElement>, snippet: Snippet) => void;
    onDrop: (event: DragEvent<HTMLDivElement>, snippet: Snippet) => void;
    onDragEnd: () => void;
};

function EditorTab({
    snippet,
    active,
    dirty,
    compact = false,
    onActivate,
    onClose,
    onContextMenu,
    onContextMenuKey,
    onToggleFavourite,
    dragged,
    dropPosition,
    onDragStart,
    onDragOver,
    onDragLeave,
    onDrop,
    onDragEnd,
}: EditorTabProps) {
    return (
        <div
            onContextMenu={(event) => onContextMenu(event, snippet)}
            onDragStart={(event) => onDragStart(event, snippet)}
            onDragOver={(event) => onDragOver(event, snippet)}
            onDragLeave={(event) => onDragLeave(event, snippet)}
            onDrop={(event) => onDrop(event, snippet)}
            onDragEnd={onDragEnd}
            className={cn(
                'group relative flex shrink-0 items-stretch',
                compact ? 'h-8 w-36' : 'max-w-64 min-w-40',
                dragged && 'opacity-40',
                active
                    ? 'bg-code-raised text-code-text'
                    : compact
                      ? 'bg-code-panel/60 text-code-muted hover:bg-code-hover hover:text-code-text'
                      : 'bg-code-canvas text-code-muted hover:bg-code-panel hover:text-code-text',
            )}
        >
            {active ? (
                <span className="absolute inset-x-0 top-0 h-px bg-code-accent" />
            ) : null}
            {dropPosition ? (
                <span
                    className={cn(
                        'pointer-events-none absolute inset-y-1 z-10 w-0.5 rounded-full bg-sky-300',
                        dropPosition === 'before' ? 'left-0' : 'right-0',
                    )}
                />
            ) : null}
            <button
                type="button"
                draggable
                role="tab"
                aria-selected={active}
                title={compact ? snippet.filename : undefined}
                onClick={() => onActivate(snippet)}
                onKeyDown={(event) => onContextMenuKey(event, snippet)}
                className={cn(
                    'flex min-w-0 flex-1 cursor-grab items-center text-left active:cursor-grabbing',
                    compact ? 'gap-1.5 px-2' : 'gap-2 px-3',
                )}
            >
                <SnippetFileIcon
                    language={snippet.language}
                    contentType={snippet.content_type}
                    className="shrink-0"
                />
                <span
                    className={cn(
                        'min-w-0 flex-1 truncate',
                        compact ? 'text-[10px]' : 'text-xs',
                    )}
                >
                    {snippet.filename}
                </span>
            </button>

            {onToggleFavourite ? (
                <button
                    type="button"
                    aria-label={
                        snippet.is_favourite
                            ? `Remove ${snippet.filename} from favourites`
                            : `Add ${snippet.filename} to favourites`
                    }
                    aria-pressed={snippet.is_favourite}
                    title={
                        snippet.is_favourite
                            ? `Remove ${snippet.filename} from favourites`
                            : `Add ${snippet.filename} to favourites`
                    }
                    onClick={() => onToggleFavourite(snippet)}
                    className={cn(
                        'flex size-6 shrink-0 items-center justify-center self-center rounded transition hover:bg-code-hover hover:text-sky-200',
                        snippet.is_favourite
                            ? 'text-sky-300'
                            : 'text-code-faint',
                    )}
                >
                    <Star
                        className={cn(
                            'size-3.5',
                            snippet.is_favourite && 'fill-sky-300/30',
                        )}
                    />
                </button>
            ) : null}

            <button
                type="button"
                aria-label={`Close ${snippet.filename}`}
                onClick={() => onClose(snippet)}
                className={cn(
                    'flex shrink-0 items-center justify-center self-center rounded text-code-faint transition hover:bg-code-hover hover:text-code-text',
                    compact ? 'mr-1 size-5' : 'mr-2 size-6',
                )}
            >
                {dirty ? (
                    <span className="size-2 rounded-full bg-code-muted group-hover:hidden" />
                ) : null}
                <X
                    className={cn(
                        compact ? 'size-3' : 'size-3.5',
                        dirty && 'hidden group-hover:block',
                    )}
                />
            </button>
        </div>
    );
}

function contextMenuPosition({ x, y }: ContextMenuState): {
    left: number;
    top: number;
} {
    return {
        left: Math.max(8, Math.min(x, window.innerWidth - 184)),
        top: Math.max(8, Math.min(y, window.innerHeight - 88)),
    };
}

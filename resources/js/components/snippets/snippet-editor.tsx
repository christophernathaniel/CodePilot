import { ClipboardPlus, Copy } from 'lucide-react';
import {
    forwardRef,
    useEffect,
    useImperativeHandle,
    useMemo,
    useRef,
    useState,
} from 'react';
import type { ClipboardEvent, KeyboardEvent, MouseEvent } from 'react';
import { createPortal } from 'react-dom';
import { SyntaxHighlightedText } from '@/components/snippets/syntax-highlighted-code';
import { useClipboard } from '@/hooks/use-clipboard';
import { createClipboardSelection } from '@/lib/snippets/clipboard-selection';
import type { ClipboardSelection } from '@/lib/snippets/clipboard-selection';

export type SnippetEditorHandle = {
    focus: () => void;
    selectAll: () => void;
    selectRange: (start: number, end: number) => void;
};

type Props = {
    value: string;
    language: string;
    readOnly?: boolean;
    preview?: boolean;
    activeClipboardName?: string | null;
    onChange: (value: string) => void;
    onSave: () => void;
    onCopy?: (selectionLength: number, method: 'keyboard' | 'button') => void;
    onAddToClipboard?: (selection: ClipboardSelection) => void;
    onCursorChange?: (line: number, column: number) => void;
};

type SelectionContextMenuState = {
    selection: ClipboardSelection;
    x: number;
    y: number;
};

export const SnippetEditor = forwardRef<SnippetEditorHandle, Props>(
    function SnippetEditor(
        {
            value,
            language,
            readOnly = false,
            preview = false,
            activeClipboardName = null,
            onChange,
            onSave,
            onCopy,
            onAddToClipboard,
            onCursorChange,
        },
        ref,
    ) {
        const textareaRef = useRef<HTMLTextAreaElement>(null);
        const contextMenuRef = useRef<HTMLDivElement>(null);
        const [, copyToSystemClipboard] = useClipboard();
        const [contextMenu, setContextMenu] =
            useState<SelectionContextMenuState | null>(null);
        const [scrollPosition, setScrollPosition] = useState({
            left: 0,
            top: 0,
        });
        const lineCount = Math.max(1, value.split('\n').length);
        const highlightedSource = useMemo(
            () => <SyntaxHighlightedText source={value} language={language} />,
            [language, value],
        );

        useEffect(() => {
            if (!contextMenu) {
                return;
            }

            const focusFrame = window.requestAnimationFrame(() => {
                contextMenuRef.current
                    ?.querySelector<HTMLButtonElement>(
                        '[role="menuitem"]:not(:disabled)',
                    )
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
                    textareaRef.current?.focus();
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

        useImperativeHandle(ref, () => ({
            focus() {
                textareaRef.current?.focus();
            },
            selectAll() {
                const textarea = textareaRef.current;

                if (!textarea) {
                    return;
                }

                textarea.focus();
                textarea.setSelectionRange(0, textarea.value.length);
                reportCursor(textarea, onCursorChange);
            },
            selectRange(start, end) {
                const textarea = textareaRef.current;

                if (!textarea) {
                    return;
                }

                const selectionStart = Math.max(
                    0,
                    Math.min(start, textarea.value.length),
                );
                const selectionEnd = Math.max(
                    selectionStart,
                    Math.min(end, textarea.value.length),
                );
                const line =
                    textarea.value.slice(0, selectionStart).split('\n').length -
                    1;
                const lineHeight = 24;
                const scrollTop = Math.max(
                    0,
                    line * lineHeight - textarea.clientHeight / 3,
                );

                textarea.focus();
                textarea.setSelectionRange(selectionStart, selectionEnd);
                textarea.scrollTop = scrollTop;
                textarea.scrollLeft = 0;
                setScrollPosition({ left: 0, top: scrollTop });
                reportCursor(textarea, onCursorChange);
            },
        }));

        const openContextMenu = (
            textarea: HTMLTextAreaElement,
            position: { x: number; y: number },
        ): boolean => {
            const selection = createClipboardSelection(
                textarea.value,
                textarea.selectionStart,
                textarea.selectionEnd,
            );

            if (!selection) {
                setContextMenu(null);

                return false;
            }

            setContextMenu({ selection, ...position });

            return true;
        };

        const handleContextMenu = (event: MouseEvent<HTMLTextAreaElement>) => {
            if (
                openContextMenu(event.currentTarget, {
                    x: event.clientX,
                    y: event.clientY,
                })
            ) {
                event.preventDefault();
            }
        };

        const handleKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
            if (
                event.key === 'ContextMenu' ||
                (event.shiftKey && event.key === 'F10')
            ) {
                const bounds = event.currentTarget.getBoundingClientRect();

                if (
                    openContextMenu(event.currentTarget, {
                        x: bounds.left + Math.min(bounds.width, 88),
                        y: bounds.top + Math.min(bounds.height, 48),
                    })
                ) {
                    event.preventDefault();
                }

                return;
            }

            if ((event.metaKey || event.ctrlKey) && event.key === 's') {
                event.preventDefault();
                onSave();

                return;
            }

            if (event.key !== 'Tab' || readOnly) {
                return;
            }

            event.preventDefault();
            const textarea = event.currentTarget;
            const selectionStart = textarea.selectionStart;
            const selectionEnd = textarea.selectionEnd;
            const indentation = '    ';
            const nextValue = `${value.slice(0, selectionStart)}${indentation}${value.slice(selectionEnd)}`;

            onChange(nextValue);

            requestAnimationFrame(() => {
                textarea.selectionStart = selectionStart + indentation.length;
                textarea.selectionEnd = selectionStart + indentation.length;
                reportCursor(textarea, onCursorChange);
            });
        };

        const closeContextMenu = () => {
            setContextMenu(null);
            textareaRef.current?.focus();
        };

        const handleContextMenuKeyDown = (
            event: KeyboardEvent<HTMLDivElement>,
        ) => {
            if (event.key === 'Tab') {
                setContextMenu(null);

                return;
            }

            if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                return;
            }

            const menuItems = Array.from(
                contextMenuRef.current?.querySelectorAll<HTMLButtonElement>(
                    '[role="menuitem"]:not(:disabled)',
                ) ?? [],
            );

            if (menuItems.length === 0) {
                return;
            }

            event.preventDefault();

            if (event.key === 'Home') {
                menuItems[0]?.focus();

                return;
            }

            if (event.key === 'End') {
                menuItems.at(-1)?.focus();

                return;
            }

            const currentIndex = menuItems.findIndex(
                (menuItem) => menuItem === document.activeElement,
            );
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            const nextIndex =
                (currentIndex + direction + menuItems.length) %
                menuItems.length;

            menuItems[nextIndex]?.focus();
        };

        const copyContextSelection = async () => {
            if (!contextMenu) {
                return;
            }

            const { selection } = contextMenu;
            const wasCopied = await copyToSystemClipboard(selection.content);

            if (wasCopied) {
                onCopy?.(selection.content.length, 'button');
            }

            closeContextMenu();
        };

        return (
            <div
                className="relative min-h-0 flex-1 overflow-hidden bg-code-canvas"
                data-language={language}
            >
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-y-0 left-0 z-20 w-14 overflow-hidden border-r border-code-border/60 bg-code-canvas pt-4 pr-3 text-right font-mono text-[12px] leading-6 text-code-faint select-none"
                >
                    <div
                        style={{
                            transform: `translateY(-${scrollPosition.top}px)`,
                        }}
                    >
                        {Array.from({ length: lineCount }, (_, index) => (
                            <div key={index}>{index + 1}</div>
                        ))}
                    </div>
                </div>

                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-y-0 right-0 left-14 overflow-hidden"
                >
                    <pre
                        className="min-h-full min-w-full py-4 pr-6 pl-4 font-mono text-[13px] leading-6 font-medium whitespace-pre text-code-text"
                        style={{
                            tabSize: 4,
                            transform: `translate(${-scrollPosition.left}px, ${-scrollPosition.top}px)`,
                        }}
                    >
                        {highlightedSource}
                        {value.endsWith('\n') ? '\n' : null}
                    </pre>
                </div>

                <textarea
                    ref={textareaRef}
                    value={value}
                    readOnly={readOnly}
                    spellCheck={false}
                    aria-label={
                        preview
                            ? `Resolved ${language} snippet preview`
                            : readOnly
                              ? `Read-only ${language} snippet source`
                              : `${language} snippet editor`
                    }
                    onChange={(event) => onChange(event.target.value)}
                    onContextMenu={handleContextMenu}
                    onKeyDown={handleKeyDown}
                    onCopy={(event: ClipboardEvent<HTMLTextAreaElement>) => {
                        const selectionLength = Math.abs(
                            event.currentTarget.selectionEnd -
                                event.currentTarget.selectionStart,
                        );

                        if (selectionLength > 0) {
                            onCopy?.(selectionLength, 'keyboard');
                        }
                    }}
                    onScroll={(event) =>
                        setScrollPosition({
                            left: event.currentTarget.scrollLeft,
                            top: event.currentTarget.scrollTop,
                        })
                    }
                    onClick={(event) =>
                        reportCursor(event.currentTarget, onCursorChange)
                    }
                    onKeyUp={(event) =>
                        reportCursor(event.currentTarget, onCursorChange)
                    }
                    className="absolute inset-0 z-10 size-full resize-none overflow-auto bg-transparent py-4 pr-6 pl-[4.5rem] font-mono text-[13px] leading-6 font-medium whitespace-pre text-transparent caret-code-text outline-none selection:bg-[#31506a]/75 disabled:cursor-not-allowed"
                    style={{
                        tabSize: 4,
                        WebkitTextFillColor: 'transparent',
                    }}
                />
                {contextMenu
                    ? createPortal(
                          <div
                              ref={contextMenuRef}
                              role="menu"
                              aria-label="Selected code actions"
                              onKeyDown={handleContextMenuKeyDown}
                              className="fixed z-100 w-56 max-w-[calc(100vw-1rem)] rounded-md border border-code-border bg-code-raised p-1 text-[11px] text-code-text shadow-2xl"
                              style={selectionContextMenuPosition(contextMenu)}
                          >
                              <button
                                  type="button"
                                  role="menuitem"
                                  disabled={!onAddToClipboard}
                                  onClick={() => {
                                      onAddToClipboard?.(contextMenu.selection);
                                      closeContextMenu();
                                  }}
                                  className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left outline-none hover:bg-code-hover focus:bg-code-hover disabled:cursor-not-allowed disabled:opacity-50"
                              >
                                  <ClipboardPlus className="size-3.5 text-code-muted" />
                                  <span className="min-w-0 truncate">
                                      {activeClipboardName
                                          ? `Add to ${activeClipboardName}`
                                          : 'Add to new clipboard'}
                                  </span>
                              </button>
                              <div className="my-1 h-px bg-code-border" />
                              <button
                                  type="button"
                                  role="menuitem"
                                  onClick={() => void copyContextSelection()}
                                  className="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left outline-none hover:bg-code-hover focus:bg-code-hover"
                              >
                                  <Copy className="size-3.5 text-code-muted" />
                                  Copy selection
                              </button>
                          </div>,
                          document.body,
                      )
                    : null}
                {readOnly && (
                    <div className="pointer-events-none absolute top-3 right-4 z-30 rounded border border-code-border bg-code-raised px-2 py-1 text-[9px] font-semibold tracking-[0.12em] text-code-muted uppercase">
                        {preview ? 'Rendered preview' : 'Read-only source'}
                    </div>
                )}
            </div>
        );
    },
);

function reportCursor(
    textarea: HTMLTextAreaElement,
    onCursorChange?: (line: number, column: number) => void,
) {
    if (!onCursorChange) {
        return;
    }

    const beforeCursor = textarea.value.slice(0, textarea.selectionStart);
    const lines = beforeCursor.split('\n');
    onCursorChange(lines.length, (lines.at(-1)?.length ?? 0) + 1);
}

function selectionContextMenuPosition({ x, y }: SelectionContextMenuState): {
    left: number;
    top: number;
} {
    return {
        left: Math.max(8, Math.min(x, window.innerWidth - 232)),
        top: Math.max(8, Math.min(y, window.innerHeight - 88)),
    };
}

import {
    forwardRef,
    useImperativeHandle,
    useMemo,
    useRef,
    useState,
} from 'react';
import type { ClipboardEvent, KeyboardEvent } from 'react';
import { SyntaxHighlightedText } from '@/components/snippets/syntax-highlighted-code';

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
    onChange: (value: string) => void;
    onSave: () => void;
    onCopy?: (selectionLength: number) => void;
    onCursorChange?: (line: number, column: number) => void;
};

export const SnippetEditor = forwardRef<SnippetEditorHandle, Props>(
    function SnippetEditor(
        {
            value,
            language,
            readOnly = false,
            preview = false,
            onChange,
            onSave,
            onCopy,
            onCursorChange,
        },
        ref,
    ) {
        const textareaRef = useRef<HTMLTextAreaElement>(null);
        const [scrollPosition, setScrollPosition] = useState({
            left: 0,
            top: 0,
        });
        const lineCount = Math.max(1, value.split('\n').length);
        const highlightedSource = useMemo(
            () => (
                <SyntaxHighlightedText source={value} language={language} />
            ),
            [language, value],
        );

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

        const handleKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
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
                    onKeyDown={handleKeyDown}
                    onCopy={(event: ClipboardEvent<HTMLTextAreaElement>) => {
                        const selectionLength = Math.abs(
                            event.currentTarget.selectionEnd -
                                event.currentTarget.selectionStart,
                        );

                        if (selectionLength > 0) {
                            onCopy?.(selectionLength);
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

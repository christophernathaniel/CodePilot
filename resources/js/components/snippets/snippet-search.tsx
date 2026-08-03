import {
    BookOpenText,
    Braces,
    Copy,
    CornerDownLeft,
    Eye,
    FolderOpen,
    Package,
    Search,
    X,
} from 'lucide-react';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent, ReactNode, RefObject } from 'react';
import { SnippetFileIcon } from '@/components/snippets/snippet-file-icon';
import {
    getInlineSearchSuggestion,
    getSearchSuggestionCaretPosition,
} from '@/lib/snippets/search-query';
import type { SnippetCodeExcerpt } from '@/lib/snippets/search-query';
import type { ParsedSnippetSection } from '@/lib/snippets/snippet-sections';
import { cn } from '@/lib/utils';
import type {
    Project,
    Snippet,
    SnippetFolder,
    SnippetVariation,
} from '@/types';

export type SnippetSectionSearchResult = {
    kind: 'section';
    snippet: Snippet;
    variation: SnippetVariation;
    section: ParsedSnippetSection;
    projectName: string;
    path: string;
};

export type SnippetSearchResult =
    | {
          kind: 'project';
          project: Project;
      }
    | {
          kind: 'folder';
          project: Project;
          folder: SnippetFolder;
          path: string;
      }
    | {
          kind: 'snippet';
          snippet: Snippet;
          projectName: string;
          path: string;
          variationId: number | null;
          variationName: string | null;
          excerpt: SnippetCodeExcerpt | null;
      }
    | SnippetSectionSearchResult;

type Props = {
    query: string;
    suggestions: string[];
    results: SnippetSearchResult[];
    totalResults?: number;
    inputRef?: RefObject<HTMLInputElement | null>;
    variant?: 'panel' | 'hero';
    behavior?: 'filter' | 'command';
    resultsMode?: 'popover' | 'workspace';
    placeholder?: string;
    resultsLabel?: string;
    shortcutKey?: string;
    shortcutAriaLabel?: string;
    searchHelp?: string;
    onFocus?: () => void;
    deferEscapeToParent?: boolean;
    showResultsWithoutQuery?: boolean;
    onQueryChange: (query: string) => void;
    onCaretChange?: (caretPosition: number) => void;
    onSuggestionAccept: (suggestion: string, caretPosition?: number) => void;
    onOpen: (result: SnippetSearchResult) => boolean | void;
    onCopySection?: (result: SnippetSectionSearchResult) => void;
    renderPreview?: (result: SnippetSearchResult | null) => ReactNode;
    inputActions?: ReactNode;
    controls?: ReactNode;
};

export function SnippetSearch({
    query,
    suggestions,
    results,
    totalResults = results.length,
    inputRef,
    variant = 'panel',
    behavior = 'command',
    resultsMode = 'popover',
    placeholder,
    resultsLabel = 'Projects, folders, files & sections',
    shortcutKey = 'K',
    shortcutAriaLabel = 'Meta+K Control+K',
    searchHelp,
    onFocus,
    deferEscapeToParent = false,
    showResultsWithoutQuery = false,
    onQueryChange,
    onCaretChange,
    onSuggestionAccept,
    onOpen,
    onCopySection,
    renderPreview,
    inputActions,
    controls,
}: Props) {
    const isHero = variant === 'hero';
    const isCommand = behavior === 'command';
    const isWorkspaceResults = resultsMode === 'workspace';
    const listboxId = useId();
    const completionDescriptionId = useId();
    const searchHelpId = useId();
    const fallbackInputRef = useRef<HTMLInputElement>(null);
    const activeOptionRef = useRef<HTMLButtonElement>(null);
    const searchInputRef = inputRef ?? fallbackInputRef;
    const resultsKey = results.map(getSearchResultKey).join('|');
    const [activeIndexState, setActiveIndexState] = useState({
        resultsKey,
        index: 0,
    });
    const activeIndex =
        activeIndexState.resultsKey === resultsKey ? activeIndexState.index : 0;
    const setActiveIndex = (
        nextIndex: number | ((currentIndex: number) => number),
    ) => {
        setActiveIndexState((current) => {
            const currentIndex =
                current.resultsKey === resultsKey ? current.index : 0;

            return {
                resultsKey,
                index:
                    typeof nextIndex === 'function'
                        ? nextIndex(currentIndex)
                        : nextIndex,
            };
        });
    };
    const [isOpen, setIsOpen] = useState(false);
    const [inputScrollLeft, setInputScrollLeft] = useState(0);
    const [caretPosition, setCaretPosition] = useState(query.length);
    const completionSuggestion = useMemo(
        () =>
            isCommand
                ? getInlineSearchSuggestion(
                      query,
                      suggestions[0],
                      caretPosition,
                  )
                : null,
        [caretPosition, isCommand, query, suggestions],
    );
    const inlineSuggestion =
        caretPosition === query.length ? completionSuggestion : null;
    const hasInput = query.length > 0;
    const optionCount = isCommand ? results.length : 0;
    const showOptions =
        isCommand &&
        (showResultsWithoutQuery || (isOpen && query.trim().length > 0));
    const navigableIndex = Math.min(activeIndex, Math.max(optionCount - 1, 0));
    const activeResult = showOptions ? (results[navigableIndex] ?? null) : null;
    const activeOptionId =
        showOptions && optionCount > 0
            ? `${listboxId}-option-${navigableIndex}`
            : undefined;

    useEffect(() => {
        if (!showOptions || !isWorkspaceResults) {
            return;
        }

        activeOptionRef.current?.scrollIntoView({ block: 'nearest' });
    }, [isWorkspaceResults, navigableIndex, showOptions]);

    const syncCaretPosition = (
        input: HTMLInputElement,
        restoreSelection = false,
    ) => {
        const nextCaretPosition = input.selectionStart ?? input.value.length;
        const nextSelectionEnd = input.selectionEnd ?? nextCaretPosition;

        setCaretPosition(nextCaretPosition);
        onCaretChange?.(nextCaretPosition);

        if (restoreSelection) {
            window.requestAnimationFrame(() => {
                searchInputRef.current?.setSelectionRange(
                    nextCaretPosition,
                    nextSelectionEnd,
                );
            });
        }
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Escape') {
            if (deferEscapeToParent) {
                return;
            }

            event.preventDefault();
            setIsOpen(false);

            return;
        }

        if (
            event.key === 'Tab' &&
            !event.shiftKey &&
            !event.altKey &&
            !event.ctrlKey &&
            !event.metaKey &&
            completionSuggestion &&
            suggestions[0]
        ) {
            event.preventDefault();
            setActiveIndex(0);
            const nextCaretPosition = getSearchSuggestionCaretPosition(
                query,
                suggestions[0],
                caretPosition,
            );

            setCaretPosition(nextCaretPosition);
            onCaretChange?.(nextCaretPosition);
            onSuggestionAccept(suggestions[0], caretPosition);
            window.requestAnimationFrame(() => {
                searchInputRef.current?.setSelectionRange(
                    nextCaretPosition,
                    nextCaretPosition,
                );
            });

            return;
        }

        if (event.key === 'ArrowDown' && showOptions && optionCount > 0) {
            event.preventDefault();
            setActiveIndex((current) => (current + 1) % optionCount);

            return;
        }

        if (event.key === 'ArrowUp' && showOptions && optionCount > 0) {
            event.preventDefault();
            setActiveIndex(
                (current) => (current - 1 + optionCount) % optionCount,
            );

            return;
        }

        if (event.key !== 'Enter' || !showOptions || optionCount === 0) {
            return;
        }

        event.preventDefault();

        const result = results[navigableIndex];

        if (result) {
            const didOpen = onOpen(result);

            if (didOpen !== false) {
                setIsOpen(false);
            }
        }
    };

    return (
        <div
            className={cn(
                'relative w-full',
                isHero && !isWorkspaceResults && 'max-w-3xl',
                isWorkspaceResults && 'flex min-h-0 flex-1 flex-col',
            )}
            onBlur={(event) => {
                if (
                    !event.currentTarget.contains(
                        event.relatedTarget as Node | null,
                    )
                ) {
                    setIsOpen(false);
                }
            }}
        >
            <div
                className={cn(
                    'group flex items-center bg-code-raised shadow-[0_18px_60px_rgba(0,0,0,0.35)] transition focus-within:ring-1 focus-within:ring-code-accent/50',
                    isHero
                        ? 'h-16 rounded-2xl px-5'
                        : 'h-9 rounded-md px-2.5 shadow-none',
                    isWorkspaceResults &&
                        'mx-auto w-full max-w-4xl shrink-0 shadow-[0_12px_38px_rgba(0,0,0,0.28)]',
                )}
            >
                <Search
                    aria-hidden="true"
                    className={cn(
                        'shrink-0 text-code-faint group-focus-within:text-code-text',
                        isHero ? 'size-5' : 'size-3.5',
                    )}
                    strokeWidth={1.8}
                />
                <div className="relative min-w-0 flex-1 self-stretch overflow-hidden">
                    {inlineSuggestion && (
                        <div
                            aria-hidden="true"
                            className={cn(
                                'pointer-events-none absolute inset-0 flex items-center overflow-hidden whitespace-pre',
                                isHero
                                    ? 'px-4 font-mono text-[15px]'
                                    : 'px-2 text-xs',
                            )}
                        >
                            <span
                                className="flex min-w-max"
                                style={{
                                    transform: `translateX(-${inputScrollLeft}px)`,
                                }}
                            >
                                <span className="text-transparent">
                                    {query}
                                </span>
                                <span className="text-code-faint/65">
                                    {inlineSuggestion.suffix}
                                </span>
                            </span>
                        </div>
                    )}
                    <input
                        ref={searchInputRef}
                        value={query}
                        onFocus={(event) => {
                            onFocus?.();
                            syncCaretPosition(event.currentTarget);

                            if (isCommand) {
                                setIsOpen(true);
                            }
                        }}
                        onChange={(event) => {
                            setActiveIndex(0);
                            setInputScrollLeft(event.currentTarget.scrollLeft);
                            syncCaretPosition(event.currentTarget);

                            if (isCommand) {
                                setIsOpen(true);
                            }

                            onQueryChange(event.target.value);
                        }}
                        onMouseUp={(event) =>
                            syncCaretPosition(event.currentTarget, true)
                        }
                        onKeyUp={(event) => {
                            if (
                                [
                                    'ArrowLeft',
                                    'ArrowRight',
                                    'Home',
                                    'End',
                                ].includes(event.key)
                            ) {
                                syncCaretPosition(event.currentTarget, true);
                            }
                        }}
                        onScroll={(event) =>
                            setInputScrollLeft(event.currentTarget.scrollLeft)
                        }
                        onKeyDown={handleKeyDown}
                        role="combobox"
                        aria-label="Search code snippets"
                        aria-autocomplete={
                            completionSuggestion ? 'both' : 'list'
                        }
                        aria-describedby={
                            [
                                searchHelp ? searchHelpId : null,
                                completionSuggestion
                                    ? completionDescriptionId
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(' ') || undefined
                        }
                        aria-keyshortcuts={shortcutAriaLabel}
                        aria-expanded={showOptions}
                        aria-haspopup="listbox"
                        aria-controls={showOptions ? listboxId : undefined}
                        aria-activedescendant={activeOptionId}
                        placeholder={
                            placeholder ??
                            (isHero
                                ? 'Find code, language==javascript, !deprecated…'
                                : 'Search snippets…')
                        }
                        className={cn(
                            'relative z-10 h-full w-full min-w-0 bg-transparent text-code-text outline-none placeholder:text-code-faint',
                            isHero
                                ? 'px-4 font-mono text-[15px]'
                                : 'px-2 text-xs',
                        )}
                    />
                    {completionSuggestion && (
                        <span
                            id={completionDescriptionId}
                            role="status"
                            aria-live="polite"
                            className="sr-only"
                        >
                            Suggested completion:{' '}
                            {completionSuggestion.completion}. Press Tab to
                            accept.
                        </span>
                    )}
                    {searchHelp && (
                        <span id={searchHelpId} className="sr-only">
                            {searchHelp}
                        </span>
                    )}
                </div>
                {inputActions}
                {hasInput ? (
                    <button
                        type="button"
                        aria-label="Clear search"
                        title="Clear search"
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => {
                            setActiveIndex(0);
                            setInputScrollLeft(0);
                            setIsOpen(showResultsWithoutQuery);
                            setCaretPosition(0);
                            onCaretChange?.(0);
                            onQueryChange('');
                            searchInputRef.current?.focus();
                        }}
                        className={cn(
                            'flex shrink-0 items-center justify-center rounded text-code-faint transition hover:bg-code-hover hover:text-code-text focus-visible:outline-1 focus-visible:outline-code-accent',
                            isHero ? 'size-8' : 'size-6',
                        )}
                    >
                        <X className={isHero ? 'size-4' : 'size-3.5'} />
                    </button>
                ) : isHero ? (
                    <kbd className="hidden items-center gap-1 rounded-md bg-code-hover px-2 py-1 font-sans text-[10px] text-code-faint sm:flex">
                        <span>⌘</span>
                        {shortcutKey}
                    </kbd>
                ) : (
                    <span className="font-mono text-[9px] text-code-faint">
                        ⌘K
                    </span>
                )}
            </div>

            {controls}

            {showOptions &&
                (isWorkspaceResults ? (
                    <div
                        className={cn(
                            'grid min-h-0 flex-1 grid-rows-[minmax(9rem,30vh)_minmax(12rem,1fr)] gap-4 overflow-y-auto min-[32rem]:grid-cols-[minmax(14rem,18rem)_minmax(0,1fr)] min-[32rem]:grid-rows-1 min-[32rem]:overflow-hidden lg:grid-cols-[minmax(17rem,22rem)_minmax(0,1fr)]',
                            controls ? 'mt-2' : 'mt-4',
                        )}
                    >
                        <SearchResultsPanel
                            listboxId={listboxId}
                            results={results}
                            totalResults={totalResults}
                            resultsLabel={resultsLabel}
                            navigableIndex={navigableIndex}
                            activeOptionRef={activeOptionRef}
                            inlineSuggestionAvailable={
                                completionSuggestion !== null
                            }
                            variant={variant}
                            mode="workspace"
                            onActiveIndexChange={setActiveIndex}
                            onOpen={onOpen}
                            onOpenStateChange={setIsOpen}
                            onCopySection={onCopySection}
                        />
                        <div className="min-h-0 overflow-hidden rounded-xl border border-code-border bg-code-panel/75 shadow-2xl">
                            {renderPreview?.(activeResult)}
                        </div>
                    </div>
                ) : (
                    <SearchResultsPanel
                        listboxId={listboxId}
                        results={results}
                        totalResults={totalResults}
                        resultsLabel={resultsLabel}
                        navigableIndex={navigableIndex}
                        activeOptionRef={activeOptionRef}
                        inlineSuggestionAvailable={
                            completionSuggestion !== null
                        }
                        variant={variant}
                        mode="popover"
                        onActiveIndexChange={setActiveIndex}
                        onOpen={onOpen}
                        onOpenStateChange={setIsOpen}
                        onCopySection={onCopySection}
                    />
                ))}
        </div>
    );
}

type SearchResultsPanelProps = {
    listboxId: string;
    results: SnippetSearchResult[];
    totalResults: number;
    resultsLabel: string;
    navigableIndex: number;
    activeOptionRef: RefObject<HTMLButtonElement | null>;
    inlineSuggestionAvailable: boolean;
    variant: 'panel' | 'hero';
    mode: 'popover' | 'workspace';
    onActiveIndexChange: (index: number) => void;
    onOpen: (result: SnippetSearchResult) => boolean | void;
    onOpenStateChange: (open: boolean) => void;
    onCopySection?: (result: SnippetSectionSearchResult) => void;
};

function SearchResultsPanel({
    listboxId,
    results,
    totalResults,
    resultsLabel,
    navigableIndex,
    activeOptionRef,
    inlineSuggestionAvailable,
    variant,
    mode,
    onActiveIndexChange,
    onOpen,
    onOpenStateChange,
    onCopySection,
}: SearchResultsPanelProps) {
    const isWorkspace = mode === 'workspace';

    const openResult = (result: SnippetSearchResult) => {
        const didOpen = onOpen(result);

        if (didOpen !== false) {
            onOpenStateChange(false);
        }
    };

    return (
        <div
            id={listboxId}
            role="listbox"
            aria-label="Search results"
            className={cn(
                'z-40 flex min-h-0 flex-col overflow-hidden border border-code-border bg-code-panel/98 shadow-2xl backdrop-blur-xl',
                isWorkspace ? 'relative rounded-xl' : 'absolute right-0 left-0',
                !isWorkspace &&
                    (variant === 'hero'
                        ? 'top-[calc(100%+0.65rem)] rounded-xl'
                        : 'top-[calc(100%+0.4rem)] rounded-lg'),
            )}
        >
            <div
                className={cn(
                    'overflow-y-auto py-1.5',
                    isWorkspace ? 'min-h-0 flex-1' : 'max-h-[min(22rem,52vh)]',
                )}
            >
                <div className="flex items-center justify-between px-3 py-1 text-[9px] font-semibold tracking-[0.16em] text-code-faint uppercase">
                    <span>{resultsLabel}</span>
                    <span>
                        {results.length < totalResults
                            ? `${results.length} of ${totalResults}`
                            : results.length}
                    </span>
                </div>
                {results.length === 0 ? (
                    <div className="px-3 py-5 text-center text-xs text-code-muted">
                        No workspace item matches the current query and filters.
                    </div>
                ) : (
                    results.map((result, resultIndex) => {
                        const isActive = navigableIndex === resultIndex;

                        return (
                            <div
                                key={getSearchResultKey(result)}
                                className={cn(
                                    'group flex items-stretch',
                                    isActive
                                        ? 'bg-code-hover'
                                        : 'hover:bg-code-hover',
                                )}
                                onMouseEnter={() =>
                                    onActiveIndexChange(resultIndex)
                                }
                            >
                                <button
                                    ref={isActive ? activeOptionRef : undefined}
                                    id={`${listboxId}-option-${resultIndex}`}
                                    type="button"
                                    tabIndex={-1}
                                    role="option"
                                    aria-selected={isActive}
                                    onFocus={() =>
                                        onActiveIndexChange(resultIndex)
                                    }
                                    onMouseDown={(event) => {
                                        if (isWorkspace) {
                                            event.preventDefault();
                                        }
                                    }}
                                    onClick={() => {
                                        if (isWorkspace) {
                                            onActiveIndexChange(resultIndex);

                                            return;
                                        }

                                        openResult(result);
                                    }}
                                    onDoubleClick={() => {
                                        if (isWorkspace) {
                                            openResult(result);
                                        }
                                    }}
                                    className="flex min-w-0 flex-1 items-start gap-2.5 px-3 py-2.5 text-left"
                                >
                                    <SearchResultContent result={result} />
                                    {isWorkspace ? (
                                        <Eye className="mt-0.5 size-3 shrink-0 text-code-faint" />
                                    ) : (
                                        <CornerDownLeft className="mt-0.5 size-3 shrink-0 text-code-faint" />
                                    )}
                                </button>
                                {result.kind === 'section' && onCopySection && (
                                    <button
                                        type="button"
                                        aria-label={`Copy ${result.section.label}`}
                                        title="Copy embedded snippet"
                                        disabled={
                                            result.section.content.length === 0
                                        }
                                        onClick={() => {
                                            onOpenStateChange(false);
                                            onCopySection(result);
                                        }}
                                        className="flex w-9 shrink-0 items-center justify-center border-l border-code-border/70 text-code-faint transition hover:bg-code-raised hover:text-code-accent disabled:cursor-not-allowed disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-code-faint"
                                    >
                                        <Copy className="size-3.5" />
                                    </button>
                                )}
                            </div>
                        );
                    })
                )}
            </div>

            {variant === 'hero' && (
                <div className="flex shrink-0 items-center gap-4 border-t border-code-border px-3 py-2 text-[9px] text-code-faint">
                    <span>↑↓ Preview</span>
                    <span>↵ Open</span>
                    {inlineSuggestionAvailable && <span>Tab Complete</span>}
                    {isWorkspace && (
                        <span className="ml-auto hidden sm:inline">
                            Double-click opens
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}

function SearchResultContent({ result }: { result: SnippetSearchResult }) {
    if (result.kind === 'project') {
        const ProjectIcon =
            result.project.kind === 'guide' ? BookOpenText : Package;

        return (
            <>
                <ProjectIcon className="mt-0.5 size-3.5 shrink-0 text-code-muted" />
                <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-2">
                        <span className="truncate text-xs font-medium text-code-text">
                            {result.project.name}
                        </span>
                        <span className="rounded border border-code-border px-1.5 py-0.5 text-[8px] tracking-wide text-code-faint uppercase">
                            {result.project.kind}
                        </span>
                    </span>
                    <span className="mt-0.5 block truncate text-[10px] text-code-muted">
                        {result.project.description ||
                            `${result.project.snippets.length} snippets`}
                    </span>
                </span>
            </>
        );
    }

    if (result.kind === 'folder') {
        return (
            <>
                <FolderOpen className="mt-0.5 size-3.5 shrink-0 text-code-muted" />
                <span className="min-w-0 flex-1">
                    <span className="block truncate text-xs font-medium text-code-text">
                        {result.folder.name}
                    </span>
                    <span className="mt-0.5 block truncate text-[10px] text-code-muted">
                        {result.project.name} / {result.path}
                    </span>
                </span>
            </>
        );
    }

    if (result.kind === 'section') {
        return (
            <>
                <Braces className="mt-0.5 size-3.5 shrink-0 text-code-accent" />
                <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-2">
                        <span className="truncate font-mono text-xs font-medium text-code-text">
                            {result.section.label}
                        </span>
                        <span className="rounded border border-code-accent/20 bg-code-accent/5 px-1.5 py-0.5 text-[8px] text-code-accent">
                            embedded
                        </span>
                    </span>
                    <span className="mt-0.5 block truncate text-[10px] text-code-muted">
                        {result.projectName} / {result.path} ·{' '}
                        {result.variation.name}
                    </span>
                </span>
            </>
        );
    }

    return (
        <>
            <SnippetFileIcon
                language={result.snippet.language}
                contentType={result.snippet.content_type}
                className="mt-0.5 shrink-0"
            />
            <span className="min-w-0 flex-1">
                <span className="flex items-center gap-2">
                    <span className="truncate text-xs font-medium text-code-text">
                        {result.snippet.title}
                    </span>
                    <span className="truncate font-mono text-[10px] text-code-faint">
                        {result.snippet.filename}
                    </span>
                    {result.snippet.content_type === 'guide' && (
                        <span className="rounded border border-sky-400/20 bg-sky-400/5 px-1.5 py-0.5 text-[8px] text-sky-200">
                            guide
                        </span>
                    )}
                    {result.variationName && (
                        <span className="truncate rounded border border-code-border px-1.5 py-0.5 text-[8px] text-code-muted">
                            {result.variationName}
                        </span>
                    )}
                </span>
                <span className="mt-0.5 block truncate text-[10px] text-code-muted">
                    {result.projectName} / {result.path}
                </span>
            </span>
        </>
    );
}

function getSearchResultKey(result: SnippetSearchResult): string {
    if (result.kind === 'project') {
        return `project-${result.project.id}`;
    }

    if (result.kind === 'folder') {
        return `folder-${result.folder.id}`;
    }

    if (result.kind === 'section') {
        return `section-${result.snippet.id}-${result.variation.id}-${result.section.key}`;
    }

    return `snippet-${result.snippet.id}`;
}

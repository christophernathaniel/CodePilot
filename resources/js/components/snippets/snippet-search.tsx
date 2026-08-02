import {
    Braces,
    Copy,
    CornerDownLeft,
    FolderOpen,
    Package,
    Search,
    Sparkles,
} from 'lucide-react';
import { useId, useMemo, useState } from 'react';
import type { KeyboardEvent, RefObject } from 'react';
import { SnippetFileIcon } from '@/components/snippets/snippet-file-icon';
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
      }
    | SnippetSectionSearchResult;

type Props = {
    query: string;
    suggestions: string[];
    results: SnippetSearchResult[];
    inputRef?: RefObject<HTMLInputElement | null>;
    variant?: 'panel' | 'hero';
    onQueryChange: (query: string) => void;
    onSuggestionAccept: (suggestion: string) => void;
    onOpen: (result: SnippetSearchResult) => void;
    onCopySection?: (result: SnippetSectionSearchResult) => void;
};

export function SnippetSearch({
    query,
    suggestions,
    results,
    inputRef,
    variant = 'panel',
    onQueryChange,
    onSuggestionAccept,
    onOpen,
    onCopySection,
}: Props) {
    const isHero = variant === 'hero';
    const listboxId = useId();
    const [activeIndex, setActiveIndex] = useState(0);
    const [isOpen, setIsOpen] = useState(false);
    const visibleSuggestions = useMemo(
        () => suggestions.slice(0, isHero ? 5 : 3),
        [isHero, suggestions],
    );
    const hasQuery = query.trim().length > 0;
    const optionCount = visibleSuggestions.length + results.length;
    const showOptions = isOpen && hasQuery;
    const navigableIndex = Math.min(activeIndex, Math.max(optionCount - 1, 0));

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            setIsOpen(false);

            return;
        }

        if (event.key === 'Tab' && visibleSuggestions[0]) {
            event.preventDefault();
            onSuggestionAccept(visibleSuggestions[0]);

            return;
        }

        if (event.key === 'ArrowDown' && optionCount > 0) {
            event.preventDefault();
            setActiveIndex((current) => (current + 1) % optionCount);

            return;
        }

        if (event.key === 'ArrowUp' && optionCount > 0) {
            event.preventDefault();
            setActiveIndex(
                (current) => (current - 1 + optionCount) % optionCount,
            );

            return;
        }

        if (event.key !== 'Enter' || optionCount === 0) {
            return;
        }

        event.preventDefault();

        if (navigableIndex < visibleSuggestions.length) {
            onSuggestionAccept(visibleSuggestions[navigableIndex]);

            return;
        }

        const result = results[navigableIndex - visibleSuggestions.length];

        if (result) {
            setIsOpen(false);
            onOpen(result);
        }
    };

    return (
        <div
            className={cn('relative w-full', isHero && 'max-w-3xl')}
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
                    'group flex items-center border border-code-border bg-code-raised shadow-[0_18px_60px_rgba(0,0,0,0.35)] transition focus-within:border-code-accent/70 focus-within:ring-1 focus-within:ring-code-accent/15',
                    isHero
                        ? 'h-16 rounded-2xl px-5'
                        : 'h-9 rounded-md px-2.5 shadow-none',
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
                <input
                    ref={inputRef}
                    value={query}
                    onFocus={() => setIsOpen(true)}
                    onChange={(event) => {
                        setActiveIndex(0);
                        setIsOpen(true);
                        onQueryChange(event.target.value);
                    }}
                    onKeyDown={handleKeyDown}
                    aria-label="Search code snippets"
                    aria-expanded={showOptions}
                    aria-controls={listboxId}
                    placeholder={
                        isHero
                            ? 'Find code, language==javascript, !deprecated…'
                            : 'Search snippets…'
                    }
                    className={cn(
                        'min-w-0 flex-1 bg-transparent text-code-text outline-none placeholder:text-code-faint',
                        isHero ? 'px-4 font-mono text-[15px]' : 'px-2 text-xs',
                    )}
                />
                {isHero ? (
                    <kbd className="hidden items-center gap-1 rounded-md border border-code-border bg-code-hover px-2 py-1 font-sans text-[10px] text-code-faint sm:flex">
                        <span>⌘</span>K
                    </kbd>
                ) : (
                    <span className="font-mono text-[9px] text-code-faint">
                        ⌘K
                    </span>
                )}
            </div>

            {showOptions && (
                <div
                    id={listboxId}
                    role="dialog"
                    aria-label="Search suggestions and results"
                    className={cn(
                        'absolute right-0 left-0 z-40 overflow-hidden border border-code-border bg-code-panel/98 shadow-2xl backdrop-blur-xl',
                        isHero
                            ? 'top-[calc(100%+0.65rem)] rounded-xl'
                            : 'top-[calc(100%+0.4rem)] rounded-lg',
                    )}
                >
                    {visibleSuggestions.length > 0 && (
                        <div className="border-b border-code-border py-1.5">
                            <div className="flex items-center justify-between px-3 py-1 text-[9px] font-semibold tracking-[0.16em] text-code-faint uppercase">
                                <span>Complete query</span>
                                <span>Tab</span>
                            </div>
                            {visibleSuggestions.map((suggestion, index) => (
                                <button
                                    key={suggestion}
                                    id={`${listboxId}-option-${index}`}
                                    type="button"
                                    aria-current={
                                        navigableIndex === index
                                            ? 'true'
                                            : undefined
                                    }
                                    onMouseEnter={() => setActiveIndex(index)}
                                    onClick={() =>
                                        onSuggestionAccept(suggestion)
                                    }
                                    className={cn(
                                        'flex w-full items-center gap-2 px-3 py-2 text-left font-mono text-xs',
                                        navigableIndex === index
                                            ? 'bg-code-hover text-code-text'
                                            : 'text-code-muted hover:bg-code-hover',
                                    )}
                                >
                                    <Sparkles className="size-3 text-code-muted" />
                                    <span className="min-w-0 flex-1 truncate">
                                        {suggestion}
                                    </span>
                                    <span className="text-[9px] text-code-faint">
                                        complete
                                    </span>
                                </button>
                            ))}
                        </div>
                    )}

                    <div className="max-h-[min(22rem,52vh)] overflow-y-auto py-1.5">
                        <div className="flex items-center justify-between px-3 py-1 text-[9px] font-semibold tracking-[0.16em] text-code-faint uppercase">
                            <span>Projects, folders, files &amp; sections</span>
                            <span>{results.length}</span>
                        </div>
                        {results.length === 0 ? (
                            <div className="px-3 py-5 text-center text-xs text-code-muted">
                                No workspace item matches this query.
                            </div>
                        ) : (
                            results.map((result, resultIndex) => {
                                const optionIndex =
                                    visibleSuggestions.length + resultIndex;

                                return (
                                    <div
                                        key={getSearchResultKey(result)}
                                        className={cn(
                                            'group flex items-stretch',
                                            navigableIndex === optionIndex
                                                ? 'bg-code-hover'
                                                : 'hover:bg-code-hover',
                                        )}
                                        onMouseEnter={() =>
                                            setActiveIndex(optionIndex)
                                        }
                                    >
                                        <button
                                            id={`${listboxId}-option-${optionIndex}`}
                                            type="button"
                                            aria-current={
                                                navigableIndex === optionIndex
                                                    ? 'true'
                                                    : undefined
                                            }
                                            onClick={() => {
                                                setIsOpen(false);
                                                onOpen(result);
                                            }}
                                            className="flex min-w-0 flex-1 items-start gap-2.5 px-3 py-2.5 text-left"
                                        >
                                            <SearchResultContent
                                                result={result}
                                            />
                                            <CornerDownLeft className="mt-0.5 size-3 shrink-0 text-code-faint" />
                                        </button>
                                        {result.kind === 'section' &&
                                            onCopySection && (
                                                <button
                                                    type="button"
                                                    aria-label={`Copy ${result.section.label}`}
                                                    title="Copy embedded snippet"
                                                    disabled={
                                                        result.section.content
                                                            .length === 0
                                                    }
                                                    onClick={() => {
                                                        setIsOpen(false);
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

                    {isHero && (
                        <div className="flex items-center gap-4 border-t border-code-border px-3 py-2 text-[9px] text-code-faint">
                            <span>↑↓ Navigate</span>
                            <span>↵ Open</span>
                            <span>Tab Complete</span>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

function SearchResultContent({ result }: { result: SnippetSearchResult }) {
    if (result.kind === 'project') {
        return (
            <>
                <Package className="mt-0.5 size-3.5 shrink-0 text-code-muted" />
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

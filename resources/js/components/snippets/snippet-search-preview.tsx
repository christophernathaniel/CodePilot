import { ArrowUpRight, Braces, FileCode2 } from 'lucide-react';
import { SnippetFileIcon } from '@/components/snippets/snippet-file-icon';
import type { SnippetSearchResult } from '@/components/snippets/snippet-search';
import { SyntaxHighlightedCode } from '@/components/snippets/syntax-highlighted-code';
import { resolveTemplatePreview } from '@/lib/snippets/template-variables';

type Props = {
    result: SnippetSearchResult | null;
    onOpen: (result: SnippetSearchResult) => boolean | void;
};

export function SnippetSearchPreview({ result, onOpen }: Props) {
    const preview = getSearchPreview(result);

    if (!result || !preview) {
        return (
            <div className="flex h-full min-h-48 items-center justify-center px-6 py-10 text-center">
                <div className="max-w-xs">
                    <span className="mx-auto flex size-10 items-center justify-center rounded-xl bg-code-raised text-code-faint">
                        <FileCode2 className="size-4" />
                    </span>
                    <p className="mt-3 text-xs font-medium text-code-text">
                        No code to preview
                    </p>
                    <p className="mt-1 text-[10px] leading-4 text-code-muted">
                        Refine the search to find a file or embedded snippet.
                    </p>
                </div>
            </div>
        );
    }

    const resolvedPreview = resolveTemplatePreview(
        preview.source,
        preview.highlightRange,
    );

    return (
        <article
            aria-label={`Preview ${preview.title}`}
            className="flex h-full min-h-0 flex-col"
        >
            <header className="flex shrink-0 items-center gap-3 border-b border-code-border bg-code-panel px-4 py-3">
                {result.kind === 'section' ? (
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-code-accent/8 text-code-accent">
                        <Braces className="size-3.5" />
                    </span>
                ) : result.kind === 'snippet' ? (
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-code-raised">
                        <SnippetFileIcon
                            language={preview.language}
                            contentType={result.snippet.content_type}
                        />
                    </span>
                ) : (
                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-code-raised text-code-faint">
                        <FileCode2 className="size-3.5" />
                    </span>
                )}

                <div className="min-w-0 flex-1">
                    <div className="flex min-w-0 items-center gap-2">
                        <h2 className="truncate text-xs font-semibold text-code-text">
                            {preview.title}
                        </h2>
                        {result.kind === 'section' && (
                            <span className="shrink-0 rounded bg-code-accent/8 px-1.5 py-0.5 text-[8px] text-code-accent">
                                embedded
                            </span>
                        )}
                    </div>
                    <p className="mt-0.5 truncate font-mono text-[9px] text-code-muted">
                        {preview.path}
                    </p>
                </div>

                <div className="hidden shrink-0 items-center gap-1.5 sm:flex">
                    <span className="rounded bg-code-canvas px-1.5 py-0.5 font-mono text-[8px] text-code-muted">
                        {preview.language}
                    </span>
                    <span className="max-w-36 truncate rounded bg-code-canvas px-1.5 py-0.5 text-[8px] text-code-faint">
                        {preview.variationName}
                    </span>
                    {preview.lineLabel && (
                        <span className="rounded bg-code-accent/8 px-1.5 py-0.5 font-mono text-[8px] text-code-accent">
                            {preview.lineLabel}
                        </span>
                    )}
                </div>

                <button
                    type="button"
                    onClick={() => onOpen(result)}
                    className="flex h-8 shrink-0 items-center gap-1.5 rounded-lg bg-code-accent/10 px-2.5 text-[9px] font-medium text-code-accent transition hover:bg-code-accent/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-code-accent"
                >
                    Open <ArrowUpRight className="size-3" />
                </button>
            </header>

            <div className="min-h-0 flex-1 overflow-hidden">
                <SyntaxHighlightedCode
                    source={resolvedPreview.source}
                    language={preview.language}
                    ariaLabel={`${preview.title}, ${preview.language} code preview`}
                    className="h-full max-h-none"
                    startLine={preview.startLine}
                    highlightRange={resolvedPreview.highlightRange}
                />
            </div>
        </article>
    );
}

type SearchPreview = {
    title: string;
    path: string;
    language: string;
    variationName: string;
    source: string;
    startLine: number;
    lineLabel: string | null;
    highlightRange: { start: number; end: number } | null;
};

function getSearchPreview(
    result: SnippetSearchResult | null,
): SearchPreview | null {
    if (!result || result.kind === 'project' || result.kind === 'folder') {
        return null;
    }

    if (result.kind === 'section') {
        return {
            title: result.section.label,
            path: `${result.projectName} / ${result.path}`,
            language: result.snippet.language,
            variationName: result.variation.name,
            source: result.section.content,
            startLine: result.section.start_line,
            lineLabel: `L${result.section.start_line}–${result.section.end_line}`,
            highlightRange: null,
        };
    }

    const variation =
        result.snippet.variations.find(
            (candidate) => candidate.id === result.variationId,
        ) ??
        result.snippet.variations.find((candidate) => candidate.is_default) ??
        result.snippet.variations[0];

    if (!variation) {
        return null;
    }

    return {
        title: result.snippet.title,
        path: `${result.projectName} / ${result.path}`,
        language: result.snippet.language,
        variationName: variation.name,
        source: result.excerpt?.text ?? variation.content,
        startLine: result.excerpt?.lineStart ?? 1,
        lineLabel: result.excerpt
            ? `L${result.excerpt.lineStart}–${result.excerpt.lineEnd}`
            : null,
        highlightRange: result.excerpt
            ? {
                  start: result.excerpt.matchStart,
                  end: result.excerpt.matchEnd,
              }
            : null,
    };
}

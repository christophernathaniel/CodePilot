import { useMemo } from 'react';
import { tokenizeCode } from '@/lib/snippets/syntax-highlighter';
import type { SyntaxTokenKind } from '@/lib/snippets/syntax-highlighter';
import { cn } from '@/lib/utils';

type SyntaxHighlightedTextProps = {
    source: string;
    language: string;
    highlightRange?: CodeHighlightRange | null;
};

type CodeHighlightRange = {
    start: number;
    end: number;
};

export function SyntaxHighlightedText({
    source,
    language,
    highlightRange,
}: SyntaxHighlightedTextProps) {
    const positionedTokens = useMemo(
        () => positionSyntaxTokens(tokenizeCode(source, language)),
        [language, source],
    );

    return positionedTokens.map(({ token, start, end }, index) => {
        if (
            !highlightRange ||
            highlightRange.end <= start ||
            highlightRange.start >= end
        ) {
            return (
                <span
                    key={`${index}-${token.kind}`}
                    className={syntaxTokenClassNames[token.kind]}
                >
                    {token.text}
                </span>
            );
        }

        const highlightStart = Math.max(0, highlightRange.start - start);
        const highlightEnd = Math.min(
            token.text.length,
            highlightRange.end - start,
        );

        return (
            <span
                key={`${index}-${token.kind}`}
                className={syntaxTokenClassNames[token.kind]}
            >
                {token.text.slice(0, highlightStart)}
                <mark className="rounded-sm bg-code-accent/20 text-inherit ring-1 ring-code-accent/35">
                    {token.text.slice(highlightStart, highlightEnd)}
                </mark>
                {token.text.slice(highlightEnd)}
            </span>
        );
    });
}

function positionSyntaxTokens(tokens: ReturnType<typeof tokenizeCode>) {
    let characterOffset = 0;

    return tokens.map((token) => {
        const start = characterOffset;
        const end = start + token.text.length;

        characterOffset = end;

        return { token, start, end };
    });
}

type SyntaxHighlightedCodeProps = SyntaxHighlightedTextProps & {
    className?: string;
    ariaLabel?: string;
    startLine?: number;
};

export function SyntaxHighlightedCode({
    source,
    language,
    className,
    ariaLabel,
    startLine = 1,
    highlightRange,
}: SyntaxHighlightedCodeProps) {
    const lineCount = Math.max(1, source.split('\n').length);

    return (
        <div
            role="region"
            aria-label={ariaLabel ?? `${language} code example`}
            tabIndex={0}
            className={cn(
                'grid max-h-[min(28rem,48vh)] grid-cols-[3rem_minmax(max-content,1fr)] overflow-auto bg-code-canvas outline-none focus-visible:ring-1 focus-visible:ring-code-accent/70 focus-visible:ring-inset',
                className,
            )}
        >
            <div
                aria-hidden="true"
                className="sticky left-0 border-r border-code-border/70 bg-code-canvas py-3 pr-3 text-right font-mono text-[10px] leading-6 text-code-faint select-none"
            >
                {Array.from({ length: lineCount }, (_, index) => (
                    <div key={index}>{startLine + index}</div>
                ))}
            </div>
            <pre className="min-h-full min-w-full py-3 pr-5 pl-4 font-mono text-[12px] leading-6 font-medium whitespace-pre text-code-text">
                <code>
                    <SyntaxHighlightedText
                        source={source}
                        language={language}
                        highlightRange={highlightRange}
                    />
                </code>
            </pre>
        </div>
    );
}

const syntaxTokenClassNames: Record<SyntaxTokenKind, string> = {
    plain: 'text-code-text',
    comment: 'text-code-syntax-comment italic',
    keyword: 'text-code-syntax-keyword',
    string: 'text-code-syntax-string',
    number: 'text-code-syntax-number',
    function: 'text-code-syntax-function',
    variable: 'text-code-syntax-variable',
    tag: 'text-code-syntax-tag',
    property: 'text-code-syntax-property',
    template: 'text-code-syntax-template',
    operator: 'text-code-syntax-operator',
    heading: 'font-semibold text-code-syntax-heading',
    inserted: 'text-code-syntax-inserted',
    deleted: 'text-code-syntax-deleted',
    section: 'font-semibold text-code-syntax-template',
};

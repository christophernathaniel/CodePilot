import { useMemo } from 'react';
import { tokenizeCode } from '@/lib/snippets/syntax-highlighter';
import type { SyntaxTokenKind } from '@/lib/snippets/syntax-highlighter';
import { cn } from '@/lib/utils';

type SyntaxHighlightedTextProps = {
    source: string;
    language: string;
};

export function SyntaxHighlightedText({
    source,
    language,
}: SyntaxHighlightedTextProps) {
    const tokens = useMemo(
        () => tokenizeCode(source, language),
        [language, source],
    );

    return tokens.map((token, index) => (
        <span
            key={`${index}-${token.kind}`}
            className={syntaxTokenClassNames[token.kind]}
        >
            {token.text}
        </span>
    ));
}

type SyntaxHighlightedCodeProps = SyntaxHighlightedTextProps & {
    className?: string;
    ariaLabel?: string;
};

export function SyntaxHighlightedCode({
    source,
    language,
    className,
    ariaLabel,
}: SyntaxHighlightedCodeProps) {
    const lineCount = Math.max(1, source.split('\n').length);

    return (
        <div
            role="region"
            aria-label={ariaLabel ?? `${language} code example`}
            tabIndex={0}
            className={cn(
                'grid max-h-[min(28rem,48vh)] grid-cols-[3rem_minmax(max-content,1fr)] overflow-auto bg-code-canvas outline-none focus-visible:ring-1 focus-visible:ring-inset focus-visible:ring-code-accent/70',
                className,
            )}
        >
            <div
                aria-hidden="true"
                className="sticky left-0 border-r border-code-border/70 bg-code-canvas py-3 pr-3 text-right font-mono text-[10px] leading-6 text-code-faint select-none"
            >
                {Array.from({ length: lineCount }, (_, index) => (
                    <div key={index}>{index + 1}</div>
                ))}
            </div>
            <pre className="min-h-full min-w-full py-3 pr-5 pl-4 font-mono text-[12px] leading-6 font-medium whitespace-pre text-code-text">
                <code>
                    <SyntaxHighlightedText
                        source={source}
                        language={language}
                    />
                    {source.endsWith('\n') ? '\n' : null}
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

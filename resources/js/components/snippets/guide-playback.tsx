import {
    ArrowLeft,
    ArrowRight,
    BookOpenText,
    Check,
    Copy,
    Play,
} from 'lucide-react';
import { Fragment, useEffect, useMemo, useState } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
import { SyntaxHighlightedCode } from '@/components/snippets/syntax-highlighted-code';
import { cn } from '@/lib/utils';
import type { GuideStep } from '@/types';

type Props = {
    title: string;
    steps: GuideStep[];
    onCopyCode?: (source: string, label: string) => Promise<void> | void;
};

export function GuidePlayback({ title, steps, onCopyCode }: Props) {
    const [activeIndex, setActiveIndex] = useState(0);
    const [copiedBlock, setCopiedBlock] = useState<string | null>(null);
    const safeActiveIndex = Math.min(
        Math.max(activeIndex, 0),
        Math.max(steps.length - 1, 0),
    );
    const activeStep = steps[safeActiveIndex];
    const progress =
        steps.length > 0 ? ((safeActiveIndex + 1) / steps.length) * 100 : 0;

    useEffect(() => {
        if (activeIndex !== safeActiveIndex) {
            setActiveIndex(safeActiveIndex);
        }
    }, [activeIndex, safeActiveIndex]);

    useEffect(() => {
        setActiveIndex(0);
        setCopiedBlock(null);
    }, [title]);

    const goToStep = (index: number) => {
        setActiveIndex(Math.min(Math.max(index, 0), steps.length - 1));
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
            event.preventDefault();
            goToStep(safeActiveIndex - 1);
        } else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
            event.preventDefault();
            goToStep(safeActiveIndex + 1);
        } else if (event.key === 'Home') {
            event.preventDefault();
            goToStep(0);
        } else if (event.key === 'End') {
            event.preventDefault();
            goToStep(steps.length - 1);
        }
    };

    if (!activeStep) {
        return <EmptyGuidePlayback />;
    }

    return (
        <div
            role="region"
            aria-label={`${title} guide playback`}
            tabIndex={0}
            onKeyDown={handleKeyDown}
            className="min-h-0 flex-1 overflow-y-auto bg-code-canvas outline-none focus-visible:ring-1 focus-visible:ring-inset focus-visible:ring-code-accent/60"
        >
            <div className="mx-auto flex w-full max-w-5xl flex-col px-4 py-5 sm:px-6 lg:px-8">
                <header className="mb-5 flex flex-col gap-3 rounded-xl border border-code-border/80 bg-code-panel/65 px-4 py-3 shadow-sm sm:flex-row sm:items-center">
                    <div className="flex min-w-0 items-center gap-3">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg border border-sky-400/25 bg-sky-400/8 text-sky-200">
                            <Play className="size-3.5 fill-current" />
                        </span>
                        <span className="min-w-0">
                            <span className="block text-[9px] font-semibold tracking-[0.16em] text-code-faint uppercase">
                                Live guide
                            </span>
                            <span className="block truncate text-sm font-medium text-code-text">
                                {title}
                            </span>
                        </span>
                    </div>
                    <div className="flex min-w-48 flex-1 items-center gap-3 sm:ml-auto sm:max-w-xs">
                        <div
                            className="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-code-raised"
                            role="progressbar"
                            aria-label="Guide progress"
                            aria-valuemin={1}
                            aria-valuemax={steps.length}
                            aria-valuenow={safeActiveIndex + 1}
                        >
                            <div
                                className="h-full rounded-full bg-sky-300 transition-[width] duration-300"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <span className="shrink-0 font-mono text-[9px] text-code-muted">
                            {safeActiveIndex + 1} / {steps.length}
                        </span>
                    </div>
                </header>

                <div className="-space-y-2.5 pb-5">
                    {steps.map((step, index) => {
                        const isActive = index === safeActiveIndex;
                        const distance = Math.abs(index - safeActiveIndex);

                        if (!isActive) {
                            return (
                                <button
                                    key={`${step.key}-${step.position}`}
                                    type="button"
                                    onClick={() => goToStep(index)}
                                    aria-label={`Open step ${index + 1}: ${step.title}`}
                                    className={cn(
                                        'group relative flex min-h-17 w-full items-center gap-3 rounded-xl border border-code-border bg-code-raised/95 px-4 pt-3 pb-5 text-left shadow-[0_10px_28px_rgba(0,0,0,0.16)] transition hover:z-30 hover:-translate-y-0.5 hover:border-sky-400/35 hover:bg-code-hover focus-visible:z-30 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300/70',
                                        distance > 2 && 'opacity-55',
                                    )}
                                    style={{ zIndex: Math.max(1, 12 - distance) }}
                                >
                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-full border border-code-border bg-code-canvas font-mono text-[10px] font-semibold text-code-muted transition group-hover:border-sky-400/35 group-hover:text-sky-200">
                                        {index + 1}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-[8px] font-semibold tracking-[0.14em] text-code-faint uppercase">
                                            Step {index + 1}
                                        </span>
                                        <span className="mt-0.5 block truncate text-xs font-medium text-code-muted group-hover:text-code-text">
                                            {step.title}
                                        </span>
                                    </span>
                                    <ArrowRight className="size-3.5 shrink-0 text-code-faint transition group-hover:translate-x-0.5 group-hover:text-sky-200" />
                                </button>
                            );
                        }

                        return (
                            <article
                                key={`${step.key}-${step.position}`}
                                aria-current="step"
                                className="relative z-20 overflow-hidden rounded-2xl border border-sky-400/35 bg-code-panel shadow-[0_24px_70px_rgba(0,0,0,0.34)] ring-1 ring-sky-400/8"
                            >
                                <div className="border-b border-code-border bg-[linear-gradient(115deg,rgba(56,139,191,0.13),transparent_48%)] px-5 py-5 sm:px-7">
                                    <div className="flex items-start gap-4">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl border border-sky-400/35 bg-sky-400/10 font-mono text-sm font-semibold text-sky-100 shadow-inner">
                                            {index + 1}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="text-[9px] font-semibold tracking-[0.18em] text-sky-300/80 uppercase">
                                                Current step
                                            </p>
                                            <h2
                                                className="mt-1 text-lg leading-7 font-medium tracking-[-0.015em] text-code-text"
                                                aria-live="polite"
                                            >
                                                {step.title}
                                            </h2>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex flex-col gap-5 px-5 py-5 sm:px-7 sm:py-6">
                                    {step.instructions ? (
                                        <GuideInstructions
                                            source={step.instructions}
                                        />
                                    ) : step.code_blocks.length === 0 ? (
                                        <p className="text-sm text-code-muted">
                                            This step does not have instructions
                                            yet.
                                        </p>
                                    ) : null}

                                    {step.code_blocks.map((block, blockIndex) => {
                                        const blockKey = `${step.key}-${blockIndex}`;
                                        const isCopied = copiedBlock === blockKey;

                                        return (
                                            <section
                                                key={blockKey}
                                                className="overflow-hidden rounded-xl border border-code-border bg-code-canvas shadow-inner"
                                            >
                                                <header className="flex h-9 items-center gap-2 border-b border-code-border bg-code-raised px-3">
                                                    <span className="size-1.5 rounded-full bg-sky-300/75" />
                                                    <span className="font-mono text-[9px] font-semibold tracking-[0.08em] text-code-muted uppercase">
                                                        {block.language}
                                                    </span>
                                                    <span className="font-mono text-[8px] text-code-faint">
                                                        Lines {block.start_line}–
                                                        {block.end_line}
                                                    </span>
                                                    {onCopyCode && (
                                                        <button
                                                            type="button"
                                                            onClick={async () => {
                                                                await onCopyCode(
                                                                    block.content,
                                                                    `${step.title} code example`,
                                                                );
                                                                setCopiedBlock(
                                                                    blockKey,
                                                                );
                                                                window.setTimeout(
                                                                    () =>
                                                                        setCopiedBlock(
                                                                            (
                                                                                current,
                                                                            ) =>
                                                                                current ===
                                                                                blockKey
                                                                                    ? null
                                                                                    : current,
                                                                        ),
                                                                    1800,
                                                                );
                                                            }}
                                                            className="ml-auto flex h-6 items-center gap-1.5 rounded px-2 text-[9px] text-code-faint transition hover:bg-code-hover hover:text-code-text focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-sky-300/70"
                                                        >
                                                            {isCopied ? (
                                                                <Check className="size-3 text-code-success" />
                                                            ) : (
                                                                <Copy className="size-3" />
                                                            )}
                                                            {isCopied
                                                                ? 'Copied'
                                                                : 'Copy'}
                                                        </button>
                                                    )}
                                                </header>
                                                <SyntaxHighlightedCode
                                                    source={block.content}
                                                    language={block.language}
                                                    ariaLabel={`${step.title}, ${block.language} code example ${blockIndex + 1}`}
                                                />
                                            </section>
                                        );
                                    })}
                                </div>

                                <footer className="flex items-center justify-between gap-3 border-t border-code-border bg-code-raised/45 px-4 py-3 sm:px-6">
                                    <button
                                        type="button"
                                        onClick={() => goToStep(index - 1)}
                                        disabled={index === 0}
                                        className="flex h-8 items-center gap-2 rounded-md border border-code-border px-3 text-[10px] font-medium text-code-muted transition hover:border-code-muted hover:bg-code-hover hover:text-code-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300/70 disabled:cursor-not-allowed disabled:opacity-35"
                                    >
                                        <ArrowLeft className="size-3" /> Previous
                                    </button>
                                    <span className="hidden text-center text-[9px] text-code-faint sm:block">
                                        Use arrow keys to move through steps
                                    </span>
                                    <button
                                        type="button"
                                        onClick={() => goToStep(index + 1)}
                                        disabled={index === steps.length - 1}
                                        className="flex h-8 items-center gap-2 rounded-md bg-code-accent px-3 text-[10px] font-semibold text-code-canvas transition hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300 disabled:cursor-not-allowed disabled:bg-code-raised disabled:text-code-faint"
                                    >
                                        Next <ArrowRight className="size-3" />
                                    </button>
                                </footer>
                            </article>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

function EmptyGuidePlayback() {
    return (
        <div className="flex min-h-0 flex-1 items-center justify-center overflow-y-auto bg-code-canvas px-5 py-10">
            <div className="max-w-xl rounded-2xl border border-dashed border-code-border bg-code-panel/55 px-7 py-8 text-center">
                <BookOpenText className="mx-auto size-8 text-code-faint" />
                <h2 className="mt-4 text-sm font-medium text-code-text">
                    Add the first guide step
                </h2>
                <p className="mt-2 text-xs leading-5 text-code-muted">
                    Playback is built from step markers and fenced Markdown code
                    blocks in the source file.
                </p>
                <code className="mt-4 block overflow-x-auto rounded-lg border border-code-border bg-code-canvas px-3 py-2 text-left font-mono text-[10px] text-sky-200">
                    {'{!# guide-step: first-step | Human title #!}'}
                </code>
            </div>
        </div>
    );
}

type InstructionBlock =
    | { type: 'heading'; level: number; content: string }
    | { type: 'paragraph'; content: string }
    | { type: 'unordered-list'; items: string[] }
    | { type: 'ordered-list'; items: string[] }
    | { type: 'quote'; content: string };

function GuideInstructions({ source }: { source: string }) {
    const blocks = useMemo(() => parseInstructionBlocks(source), [source]);

    return (
        <div className="flex flex-col gap-3 text-[13px] leading-6 text-code-muted">
            {blocks.map((block, index) => {
                if (block.type === 'heading') {
                    return (
                        <h3
                            key={index}
                            className={cn(
                                'font-semibold text-code-text',
                                block.level <= 2 ? 'text-base' : 'text-sm',
                            )}
                        >
                            {renderInlineMarkdown(block.content)}
                        </h3>
                    );
                }

                if (block.type === 'unordered-list') {
                    return (
                        <ul
                            key={index}
                            className="flex list-disc flex-col gap-1 pl-5 marker:text-sky-300/70"
                        >
                            {block.items.map((item, itemIndex) => (
                                <li key={itemIndex}>
                                    {renderInlineMarkdown(item)}
                                </li>
                            ))}
                        </ul>
                    );
                }

                if (block.type === 'ordered-list') {
                    return (
                        <ol
                            key={index}
                            className="flex list-decimal flex-col gap-1 pl-5 marker:font-mono marker:text-sky-300/80"
                        >
                            {block.items.map((item, itemIndex) => (
                                <li key={itemIndex}>
                                    {renderInlineMarkdown(item)}
                                </li>
                            ))}
                        </ol>
                    );
                }

                if (block.type === 'quote') {
                    return (
                        <blockquote
                            key={index}
                            className="border-l-2 border-sky-400/45 bg-sky-400/5 px-3 py-2 text-code-muted"
                        >
                            {renderInlineMarkdown(block.content)}
                        </blockquote>
                    );
                }

                return (
                    <p key={index}>{renderInlineMarkdown(block.content)}</p>
                );
            })}
        </div>
    );
}

function parseInstructionBlocks(source: string): InstructionBlock[] {
    const lines = source.split('\n');
    const blocks: InstructionBlock[] = [];
    let paragraph: string[] = [];

    const flushParagraph = () => {
        if (paragraph.length > 0) {
            blocks.push({ type: 'paragraph', content: paragraph.join(' ') });
            paragraph = [];
        }
    };

    for (let index = 0; index < lines.length; index += 1) {
        const line = lines[index].trim();

        if (!line) {
            flushParagraph();
            continue;
        }

        const heading = /^(#{1,6})\s+(.+)$/u.exec(line);

        if (heading) {
            flushParagraph();
            blocks.push({
                type: 'heading',
                level: heading[1].length,
                content: heading[2],
            });
            continue;
        }

        if (/^[-*+]\s+/u.test(line)) {
            flushParagraph();
            const items: string[] = [];

            while (index < lines.length) {
                const item = /^[-*+]\s+(.+)$/u.exec(lines[index].trim());

                if (!item) {
                    break;
                }

                items.push(item[1]);
                index += 1;
            }

            index -= 1;
            blocks.push({ type: 'unordered-list', items });
            continue;
        }

        if (/^\d+[.)]\s+/u.test(line)) {
            flushParagraph();
            const items: string[] = [];

            while (index < lines.length) {
                const item = /^\d+[.)]\s+(.+)$/u.exec(lines[index].trim());

                if (!item) {
                    break;
                }

                items.push(item[1]);
                index += 1;
            }

            index -= 1;
            blocks.push({ type: 'ordered-list', items });
            continue;
        }

        if (line.startsWith('>')) {
            flushParagraph();
            blocks.push({ type: 'quote', content: line.slice(1).trim() });
            continue;
        }

        paragraph.push(line);
    }

    flushParagraph();

    return blocks;
}

function renderInlineMarkdown(source: string): ReactNode {
    const fragments = source.split(/(`[^`]+`|\*\*[^*]+\*\*)/gu);

    return fragments.map((fragment, index) => {
        if (fragment.startsWith('`') && fragment.endsWith('`')) {
            return (
                <code
                    key={index}
                    className="rounded border border-code-border bg-code-canvas px-1.5 py-0.5 font-mono text-[0.88em] text-sky-200"
                >
                    {fragment.slice(1, -1)}
                </code>
            );
        }

        if (fragment.startsWith('**') && fragment.endsWith('**')) {
            return (
                <strong key={index} className="font-semibold text-code-text">
                    {fragment.slice(2, -2)}
                </strong>
            );
        }

        return <Fragment key={index}>{fragment}</Fragment>;
    });
}

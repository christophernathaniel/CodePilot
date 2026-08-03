import {
    ArrowLeft,
    ArrowRight,
    BookOpenText,
    Check,
    Copy,
    Play,
} from 'lucide-react';
import {
    Fragment,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import type { KeyboardEvent, ReactNode, WheelEvent } from 'react';
import { SyntaxHighlightedCode } from '@/components/snippets/syntax-highlighted-code';
import {
    emptyGuideWheelIntent,
    resolveGuideWheelNavigation,
} from '@/lib/snippets/guide-playback-navigation';
import type { GuideWheelIntent } from '@/lib/snippets/guide-playback-navigation';
import { cn } from '@/lib/utils';
import type { GuideStep } from '@/types';

type Props = {
    title: string;
    steps: GuideStep[];
    onCopyCode?: (source: string, label: string) => Promise<boolean> | boolean;
};

export function GuidePlayback({ title, steps, onCopyCode }: Props) {
    if (steps.length === 0) {
        return <EmptyGuidePlayback />;
    }

    const playbackIdentity = `${title}:${steps
        .map((step) => `${step.key}:${step.position}`)
        .join('|')}`;

    return (
        <GuidePlaybackSession
            key={playbackIdentity}
            title={title}
            steps={steps}
            onCopyCode={onCopyCode}
        />
    );
}

function GuidePlaybackSession({ title, steps, onCopyCode }: Props) {
    const [activeIndex, setActiveIndex] = useState(0);
    const [copiedBlock, setCopiedBlock] = useState<string | null>(null);
    const playbackRef = useRef<HTMLDivElement>(null);
    const controlsRef = useRef<HTMLElement>(null);
    const activeStepRef = useRef<HTMLElement>(null);
    const navigationLockedRef = useRef(false);
    const navigationUnlockTimerRef = useRef<number | null>(null);
    const wheelIntentRef = useRef<GuideWheelIntent>(emptyGuideWheelIntent());
    const safeActiveIndex = Math.min(
        Math.max(activeIndex, 0),
        Math.max(steps.length - 1, 0),
    );
    const activeStep = steps[safeActiveIndex];
    const progress =
        steps.length > 0 ? ((safeActiveIndex + 1) / steps.length) * 100 : 0;
    const previousStackStart = Math.max(0, safeActiveIndex - 2);
    const previousStackSteps = steps
        .slice(previousStackStart, safeActiveIndex)
        .map((step, offset) => ({
            step,
            index: previousStackStart + offset,
        }));
    const nextStackSteps = steps
        .slice(safeActiveIndex + 1, safeActiveIndex + 3)
        .map((step, offset) => ({
            step,
            index: safeActiveIndex + 1 + offset,
        }));

    useEffect(
        () => () => {
            if (navigationUnlockTimerRef.current !== null) {
                window.clearTimeout(navigationUnlockTimerRef.current);
            }
        },
        [],
    );

    const goToStep = useCallback(
        (
            index: number,
            behaviour: ScrollBehavior = 'smooth',
            lockDuration = 450,
        ) => {
            const nextIndex = Math.min(Math.max(index, 0), steps.length - 1);

            if (nextIndex === safeActiveIndex) {
                return;
            }

            navigationLockedRef.current = true;
            wheelIntentRef.current = emptyGuideWheelIntent();
            setActiveIndex(nextIndex);

            window.requestAnimationFrame(() => {
                const reducedMotion = window.matchMedia(
                    '(prefers-reduced-motion: reduce)',
                ).matches;

                const playback = playbackRef.current;
                const activeStepElement = activeStepRef.current;

                if (playback && activeStepElement) {
                    const playbackBounds = playback.getBoundingClientRect();
                    const activeStepBounds =
                        activeStepElement.getBoundingClientRect();
                    const controlsHeight =
                        controlsRef.current?.getBoundingClientRect().height ??
                        48;
                    const stackPeek = activeStepElement.previousElementSibling
                        ? 64
                        : 18;
                    const top =
                        playback.scrollTop +
                        activeStepBounds.top -
                        playbackBounds.top -
                        controlsHeight -
                        stackPeek;

                    playback.scrollTo({
                        top: Math.max(0, top),
                        behavior: reducedMotion ? 'auto' : behaviour,
                    });
                    activeStepElement.focus({ preventScroll: true });
                }

                if (navigationUnlockTimerRef.current !== null) {
                    window.clearTimeout(navigationUnlockTimerRef.current);
                }

                navigationUnlockTimerRef.current = window.setTimeout(() => {
                    navigationLockedRef.current = false;
                }, lockDuration);
            });
        },
        [safeActiveIndex, steps.length],
    );

    const handleKeyDown = (event: KeyboardEvent<HTMLElement>) => {
        if (event.target !== event.currentTarget) {
            return;
        }

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

    const handleWheel = (event: WheelEvent<HTMLDivElement>) => {
        const playback = playbackRef.current;
        const activeStepElement = activeStepRef.current;

        if (
            !playback ||
            !activeStepElement ||
            navigationLockedRef.current ||
            nestedScrollerCanConsumeWheel(event.target, playback, event.deltaY)
        ) {
            wheelIntentRef.current = emptyGuideWheelIntent();

            return;
        }

        const playbackBounds = playback.getBoundingClientRect();
        const activeStepBounds = activeStepElement.getBoundingClientRect();
        const controlsBottom =
            controlsRef.current?.getBoundingClientRect().bottom ??
            playbackBounds.top;
        const atStart =
            activeStepBounds.top >=
            controlsBottom + (safeActiveIndex > 0 ? 52 : 0) - 2;
        const atEnd = activeStepBounds.bottom <= playbackBounds.bottom + 2;

        const navigation = resolveGuideWheelNavigation(
            {
                deltaX: event.deltaX,
                deltaY: event.deltaY,
                deltaMode: event.deltaMode,
                viewportHeight: playback.clientHeight,
                atStart,
                atEnd,
                activeIndex: safeActiveIndex,
                stepCount: steps.length,
                timeStamp: event.timeStamp,
            },
            wheelIntentRef.current,
        );
        wheelIntentRef.current = navigation.intent;

        if (navigation.nextIndex === null) {
            return;
        }

        goToStep(navigation.nextIndex, 'auto', 700);
    };

    if (!activeStep) {
        return <EmptyGuidePlayback />;
    }

    const activeStepHeadingId = `guide-step-${safeActiveIndex + 1}-heading`;

    return (
        <div
            ref={playbackRef}
            role="region"
            aria-label={`${title} guide playback`}
            tabIndex={0}
            onKeyDown={handleKeyDown}
            onWheel={handleWheel}
            className="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-code-canvas outline-none focus-visible:ring-1 focus-visible:ring-code-accent/60 focus-visible:ring-inset"
        >
            <span
                role="status"
                aria-live="polite"
                aria-atomic="true"
                className="sr-only"
            >
                Step {safeActiveIndex + 1} of {steps.length}: {activeStep.title}
            </span>

            <header
                ref={controlsRef}
                className="sticky top-0 z-40 border-b border-code-border bg-code-panel/95 shadow-[0_10px_28px_rgba(0,0,0,0.2)] backdrop-blur"
            >
                <div className="mx-auto flex h-12 w-full max-w-6xl items-center gap-3 px-3 sm:px-5">
                    <div className="flex min-w-0 items-center gap-2.5">
                        <span className="flex size-7 shrink-0 items-center justify-center rounded-md border border-sky-400/25 bg-sky-400/8 text-sky-200">
                            <Play className="size-3 fill-current" />
                        </span>
                        <span className="min-w-0">
                            <span className="block text-[8px] font-semibold tracking-[0.16em] text-code-faint uppercase">
                                Live guide
                            </span>
                            <span className="block max-w-32 truncate text-[11px] font-medium text-code-text sm:max-w-52">
                                {title}
                            </span>
                        </span>
                    </div>

                    <div className="ml-auto hidden max-w-48 min-w-28 flex-1 items-center gap-2 sm:flex">
                        <div
                            className="h-1 min-w-0 flex-1 overflow-hidden rounded-full bg-code-raised"
                            role="progressbar"
                            aria-label="Guide progress"
                            aria-valuemin={1}
                            aria-valuemax={steps.length}
                            aria-valuenow={safeActiveIndex + 1}
                        >
                            <div
                                className="h-full rounded-full bg-sky-300 transition-[width] duration-300 motion-reduce:transition-none"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <span className="shrink-0 font-mono text-[8px] text-code-muted">
                            {safeActiveIndex + 1} / {steps.length}
                        </span>
                    </div>

                    <div className="flex shrink-0 items-center overflow-hidden rounded-md border border-code-border bg-code-canvas">
                        <button
                            type="button"
                            onClick={() => goToStep(safeActiveIndex - 1)}
                            disabled={safeActiveIndex === 0}
                            aria-label="Previous guide step"
                            className="flex h-7 items-center gap-1.5 border-r border-code-border px-2 text-[9px] font-medium text-code-muted transition hover:bg-code-hover hover:text-code-text focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-sky-300/70 disabled:cursor-not-allowed disabled:opacity-35 sm:px-2.5"
                        >
                            <ArrowLeft className="size-3" />
                            <span className="hidden md:inline">Previous</span>
                        </button>
                        <button
                            type="button"
                            onClick={() => goToStep(safeActiveIndex + 1)}
                            disabled={safeActiveIndex === steps.length - 1}
                            aria-label="Next guide step"
                            className="flex h-7 items-center gap-1.5 px-2 text-[9px] font-semibold text-sky-200 transition hover:bg-code-hover hover:text-white focus-visible:z-10 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-sky-300/70 disabled:cursor-not-allowed disabled:text-code-faint sm:px-2.5"
                        >
                            <span className="hidden md:inline">Next</span>
                            <ArrowRight className="size-3" />
                        </button>
                    </div>
                </div>
            </header>

            <main className="mx-auto w-full max-w-4xl px-4 py-5 sm:px-6 sm:py-7 lg:px-8">
                <div className="isolate -space-y-3">
                    {previousStackSteps.map(({ step, index }) => (
                        <StackedStepPreview
                            key={`${step.key}-${step.position}`}
                            step={step}
                            index={index}
                            activeIndex={safeActiveIndex}
                            onSelect={goToStep}
                        />
                    ))}

                    <article
                        ref={activeStepRef}
                        tabIndex={-1}
                        aria-current="step"
                        aria-labelledby={activeStepHeadingId}
                        onKeyDown={handleKeyDown}
                        className="relative z-30 overflow-hidden rounded-2xl border border-sky-400/35 bg-code-panel shadow-[0_24px_70px_rgba(0,0,0,0.34)] ring-1 ring-sky-400/8 outline-none focus-visible:ring-2 focus-visible:ring-sky-300/60"
                    >
                        <div className="border-b border-code-border bg-[linear-gradient(115deg,rgba(56,139,191,0.13),transparent_48%)] px-5 py-5 sm:px-7">
                            <div className="flex items-start gap-4">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl border border-sky-400/35 bg-sky-400/10 font-mono text-sm font-semibold text-sky-100 shadow-inner">
                                    {safeActiveIndex + 1}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="text-[9px] font-semibold tracking-[0.18em] text-sky-300/80 uppercase">
                                        Current step · {safeActiveIndex + 1} of{' '}
                                        {steps.length}
                                    </p>
                                    <h2
                                        id={activeStepHeadingId}
                                        className="mt-1 text-lg leading-7 font-medium tracking-[-0.015em] text-code-text"
                                    >
                                        {activeStep.title}
                                    </h2>
                                </div>
                            </div>
                        </div>

                        <div className="flex flex-col gap-5 px-5 py-5 sm:px-7 sm:py-6">
                            {activeStep.instructions ? (
                                <GuideInstructions
                                    source={activeStep.instructions}
                                />
                            ) : activeStep.code_blocks.length === 0 ? (
                                <p className="text-sm text-code-muted">
                                    This step does not have instructions yet.
                                </p>
                            ) : null}

                            {activeStep.code_blocks.map((block, blockIndex) => {
                                const blockKey = `${activeStep.key}-${blockIndex}`;
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
                                                        const wasCopied =
                                                            await onCopyCode(
                                                                block.content,
                                                                `${activeStep.title} code example`,
                                                            );

                                                        if (!wasCopied) {
                                                            return;
                                                        }

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
                                            ariaLabel={`${activeStep.title}, ${block.language} code example ${blockIndex + 1}`}
                                        />
                                    </section>
                                );
                            })}
                        </div>
                    </article>

                    {nextStackSteps.map(({ step, index }) => (
                        <StackedStepPreview
                            key={`${step.key}-${step.position}`}
                            step={step}
                            index={index}
                            activeIndex={safeActiveIndex}
                            onSelect={goToStep}
                        />
                    ))}
                </div>

                <p
                    aria-hidden="true"
                    className="pt-5 text-center font-mono text-[9px] text-code-faint"
                >
                    {safeActiveIndex < steps.length - 1
                        ? 'Keep scrolling at the end of this step to continue'
                        : 'Guide complete'}
                </p>
            </main>
        </div>
    );
}

function StackedStepPreview({
    step,
    index,
    activeIndex,
    onSelect,
}: {
    step: GuideStep;
    index: number;
    activeIndex: number;
    onSelect: (index: number) => void;
}) {
    const distance = Math.abs(index - activeIndex);
    const isPrevious = index < activeIndex;
    const DirectionIcon = isPrevious ? ArrowLeft : ArrowRight;
    const relationship =
        distance === 1
            ? isPrevious
                ? 'Previous step'
                : 'Next step'
            : isPrevious
              ? 'Earlier step'
              : 'Later step';

    return (
        <button
            type="button"
            onClick={() => onSelect(index)}
            aria-label={`Open step ${index + 1}: ${step.title}`}
            className={cn(
                'group relative flex min-h-16 w-auto items-center gap-3 rounded-xl border border-code-border px-4 text-left shadow-[0_12px_34px_rgba(0,0,0,0.2)] transition-[transform,background-color,border-color] motion-reduce:transition-none',
                'hover:z-40 hover:-translate-y-0.5 hover:border-sky-400/35 hover:bg-code-hover focus-visible:z-40 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-300/70',
                isPrevious ? 'pt-3 pb-5' : 'pt-5 pb-3',
                distance === 1
                    ? 'mx-2 bg-code-raised/95 sm:mx-3'
                    : 'mx-5 bg-code-raised/70 sm:mx-7',
            )}
            style={{ zIndex: Math.max(1, 20 - distance) }}
        >
            <span className="flex size-7 shrink-0 items-center justify-center rounded-full border border-code-border bg-code-canvas font-mono text-[10px] font-semibold text-code-muted transition group-hover:border-sky-400/35 group-hover:text-sky-200">
                {index + 1}
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-[8px] font-semibold tracking-[0.14em] text-code-faint uppercase">
                    {relationship}
                </span>
                <span className="mt-0.5 block truncate text-xs font-medium text-code-muted group-hover:text-code-text">
                    {step.title}
                </span>
            </span>
            <DirectionIcon className="size-3.5 shrink-0 text-code-faint transition group-hover:text-sky-200" />
        </button>
    );
}

function nestedScrollerCanConsumeWheel(
    target: EventTarget,
    playback: HTMLElement,
    deltaY: number,
): boolean {
    let element = target instanceof Element ? target : null;

    while (element && element !== playback) {
        const canScrollVertically = element.scrollHeight > element.clientHeight;

        if (
            canScrollVertically &&
            ((deltaY > 0 &&
                element.scrollTop + element.clientHeight <
                    element.scrollHeight - 1) ||
                (deltaY < 0 && element.scrollTop > 1))
        ) {
            return true;
        }

        element = element.parentElement;
    }

    return false;
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

                return <p key={index}>{renderInlineMarkdown(block.content)}</p>;
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

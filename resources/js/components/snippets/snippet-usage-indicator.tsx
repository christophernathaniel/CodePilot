import { cn } from '@/lib/utils';
import type { SnippetUsage } from '@/types';

type Props = {
    usage: SnippetUsage;
    className?: string;
};

export function SnippetUsageIndicator({ usage, className }: Props) {
    const mark =
        usage.indicator === -1
            ? '−'
            : usage.indicator === 0
              ? '·'
              : '+'.repeat(usage.indicator);
    const label =
        usage.indicator === -1
            ? `Previously used, with no copies in the last 30 days (${usage.copies_total} total)`
            : usage.indicator === 0
              ? 'Never copied'
              : `${usage.copies_30d} copies in the last 30 days (${usage.copies_total} total)`;

    return (
        <span
            aria-label={label}
            title={label}
            className={cn(
                'inline-flex min-w-5 shrink-0 items-center justify-center rounded border border-sky-400/15 bg-sky-400/5 px-1 font-mono text-[9px] font-bold tracking-[-0.08em]',
                usage.indicator === 0 ? 'text-code-faint' : 'text-sky-300',
                className,
            )}
        >
            {mark}
        </span>
    );
}

import {
    Check,
    Clipboard,
    Copy,
    Eraser,
    FileCode2,
    FilePlus2,
    GitBranch,
    LoaderCircle,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { SyntaxHighlightedCode } from '@/components/snippets/syntax-highlighted-code';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useClipboard } from '@/hooks/use-clipboard';
import { canCreateClipboardFile } from '@/lib/snippets/clipboard-file';
import { cn } from '@/lib/utils';
import type { ClipboardSession } from '@/types';

type ClipboardPanelProps = {
    clipboards: ClipboardSession[];
    processing?: boolean;
    onCreate: () => void;
    onActivate: (id: number) => void;
    onRename: (id: number, name: string) => void;
    onCreateFile: (clipboard: ClipboardSession) => void;
    onDeleteClip: (id: number) => void;
    onClear: (id: number) => void;
    onDelete: (id: number) => void;
};

type ClipboardClip = ClipboardSession['clips'][number];

type RenameState = {
    id: number;
    name: string;
};

type CopyState = {
    clipId: number;
    status: 'copied' | 'failed';
} | null;

export function ClipboardPanel({
    clipboards,
    processing = false,
    onCreate,
    onActivate,
    onRename,
    onCreateFile,
    onDeleteClip,
    onClear,
    onDelete,
}: ClipboardPanelProps) {
    const [open, setOpen] = useState(false);
    const [rename, setRename] = useState<RenameState | null>(null);
    const [copyState, setCopyState] = useState<CopyState>(null);
    const copyStateTimerRef = useRef<number | null>(null);
    const [, copy] = useClipboard();
    const activeClipboard =
        clipboards.find((clipboard) => clipboard.is_active) ?? null;

    useEffect(
        () => () => {
            if (copyStateTimerRef.current !== null) {
                window.clearTimeout(copyStateTimerRef.current);
            }
        },
        [],
    );

    const closePanel = (nextOpen: boolean) => {
        if (!nextOpen && rename) {
            setRename(null);

            return;
        }

        setOpen(nextOpen);

        if (!nextOpen) {
            setRename(null);
        }
    };

    const beginRename = (clipboard: ClipboardSession) => {
        if (processing) {
            return;
        }

        setRename({ id: clipboard.id, name: clipboard.name });
    };

    const commitRename = () => {
        if (!rename) {
            return;
        }

        const clipboard = clipboards.find(
            (candidate) => candidate.id === rename.id,
        );
        const name = rename.name.trim();

        setRename(null);

        if (!clipboard || name.length === 0 || name === clipboard.name) {
            return;
        }

        onRename(clipboard.id, name);
    };

    const copyClip = async (clip: ClipboardClip) => {
        const wasCopied = await copy(clip.content);

        setCopyState({
            clipId: clip.id,
            status: wasCopied ? 'copied' : 'failed',
        });

        if (copyStateTimerRef.current !== null) {
            window.clearTimeout(copyStateTimerRef.current);
        }

        copyStateTimerRef.current = window.setTimeout(
            () => setCopyState(null),
            1800,
        );
    };

    const clearClipboard = (clipboard: ClipboardSession) => {
        if (
            clipboard.clips_count === 0 ||
            !window.confirm(
                `Clear all code from the “${clipboard.name}” clipboard?`,
            )
        ) {
            return;
        }

        onClear(clipboard.id);
    };

    const deleteClipboard = (clipboard: ClipboardSession) => {
        if (
            !window.confirm(
                `Delete the “${clipboard.name}” clipboard and all of its code?`,
            )
        ) {
            return;
        }

        onDelete(clipboard.id);
    };

    const createFile = (clipboard: ClipboardSession) => {
        if (processing || !canCreateClipboardFile(clipboard)) {
            return;
        }

        setRename(null);
        setOpen(false);
        onCreateFile(clipboard);
    };

    const deleteClip = (clip: ClipboardClip) => {
        if (
            processing ||
            !window.confirm(
                `Remove “${clip.source.title}” from this clipboard? This will not change the source file.`,
            )
        ) {
            return;
        }

        onDeleteClip(clip.id);
    };

    return (
        <Dialog open={open} onOpenChange={closePanel}>
            <DialogTrigger asChild>
                <button
                    type="button"
                    aria-label={
                        activeClipboard
                            ? `Open ${activeClipboard.name} clipboard with ${formatClipCount(activeClipboard.clips_count)}`
                            : 'Open clipboards'
                    }
                    className="fixed right-0 bottom-0 z-40 flex h-9 max-w-[min(18rem,calc(100vw-1rem))] items-center gap-2 rounded-tl-lg border-t border-l border-code-border bg-code-raised px-3 text-[10px] font-medium text-code-muted shadow-[-8px_-8px_28px_rgba(0,0,0,0.2)] transition hover:bg-code-hover hover:text-code-text"
                >
                    <Clipboard className="size-3.5 shrink-0 text-code-accent" />
                    <span className="truncate">
                        {activeClipboard?.name ?? 'Clipboard'}
                    </span>
                    <span className="shrink-0 rounded-full border border-code-border bg-code-canvas px-1.5 py-0.5 font-mono text-[8px] text-code-faint">
                        {activeClipboard?.clips_count ?? 0}
                    </span>
                </button>
            </DialogTrigger>

            <DialogContent
                aria-busy={processing}
                onEscapeKeyDown={(event) => {
                    if (rename) {
                        event.preventDefault();
                        setRename(null);
                    }
                }}
                className="snippet-workspace inset-0 top-0 left-0 flex h-svh w-screen max-w-none translate-x-0 translate-y-0 flex-col gap-0 overflow-hidden rounded-none border-0 bg-code-canvas p-0 text-code-text shadow-none sm:max-w-none"
            >
                <DialogHeader className="shrink-0 gap-1 border-b border-code-border bg-code-panel px-4 py-4 pr-16 text-left sm:px-6 sm:py-5 sm:pr-16">
                    <div className="flex items-center gap-2.5">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg border border-code-accent/25 bg-code-accent/8 text-code-accent">
                            <Clipboard className="size-4" />
                        </span>
                        <div className="min-w-0">
                            <DialogTitle className="truncate text-base text-code-text sm:text-lg">
                                Code clipboards
                            </DialogTitle>
                            <DialogDescription className="mt-1 text-[10px] text-code-muted sm:text-xs">
                                Switch sessions, paste with Ctrl+V (⌘V on Mac),
                                and copy content back to your system clipboard.
                            </DialogDescription>
                        </div>
                        {processing ? (
                            <span
                                role="status"
                                className="ml-auto flex shrink-0 items-center gap-1.5 text-[9px] text-code-faint"
                            >
                                <LoaderCircle className="size-3 animate-spin" />
                                Saving…
                            </span>
                        ) : null}
                    </div>
                </DialogHeader>

                <div className="min-h-0 flex-1 overflow-y-auto">
                    <section
                        aria-labelledby="clipboard-sessions-heading"
                        className="border-b border-code-border bg-code-panel/55 px-3 py-4 sm:px-6 sm:py-5"
                    >
                        <div className="mb-3 flex items-center gap-3">
                            <div className="min-w-0">
                                <h2
                                    id="clipboard-sessions-heading"
                                    className="text-[10px] font-semibold tracking-[0.14em] text-code-muted uppercase"
                                >
                                    Clipboard sessions
                                </h2>
                                <p className="mt-1 text-[9px] text-code-faint">
                                    Double-click a clipboard name to rename it.
                                </p>
                            </div>
                            <span className="ml-auto shrink-0 font-mono text-[9px] text-code-faint">
                                {clipboards.length}{' '}
                                {clipboards.length === 1
                                    ? 'session'
                                    : 'sessions'}
                            </span>
                        </div>

                        <div className="grid auto-cols-[7.5rem] grid-flow-col gap-3 overflow-x-auto pb-2 sm:auto-cols-[9rem] sm:gap-4">
                            <button
                                type="button"
                                onClick={onCreate}
                                disabled={processing}
                                aria-label="Create a new clipboard"
                                className="group aspect-square rounded-xl border border-dashed border-code-border bg-code-canvas/45 p-3 text-code-faint transition hover:border-code-accent/50 hover:bg-code-hover/70 hover:text-code-text disabled:cursor-not-allowed disabled:opacity-45"
                            >
                                <span className="flex h-full flex-col items-center justify-center gap-2">
                                    <span className="flex size-10 items-center justify-center rounded-full border border-code-border bg-code-raised transition group-hover:border-code-accent/35 group-hover:text-code-accent">
                                        <Plus className="size-4" />
                                    </span>
                                    <span className="text-[10px] font-medium">
                                        New clipboard
                                    </span>
                                </span>
                            </button>

                            {clipboards.map((clipboard) => (
                                <ClipboardCard
                                    key={clipboard.id}
                                    clipboard={clipboard}
                                    processing={processing}
                                    rename={
                                        rename?.id === clipboard.id
                                            ? rename
                                            : null
                                    }
                                    onActivate={() => onActivate(clipboard.id)}
                                    onBeginRename={() => beginRename(clipboard)}
                                    onRenameChange={(name) =>
                                        setRename({ id: clipboard.id, name })
                                    }
                                    onRenameCommit={commitRename}
                                    onRenameCancel={() => setRename(null)}
                                />
                            ))}
                        </div>
                    </section>

                    {activeClipboard ? (
                        <section
                            aria-labelledby="active-clipboard-heading"
                            className="mx-auto flex w-full max-w-7xl flex-col gap-4 px-3 py-4 sm:gap-5 sm:px-6 sm:py-6 lg:px-8"
                        >
                            <header className="flex flex-col gap-3 border-b border-code-border pb-4 sm:flex-row sm:items-center">
                                <div className="min-w-0">
                                    <p className="text-[9px] font-semibold tracking-[0.14em] text-code-faint uppercase">
                                        Active clipboard
                                    </p>
                                    <h2
                                        id="active-clipboard-heading"
                                        className="mt-1 truncate text-lg font-medium text-code-text"
                                    >
                                        {activeClipboard.name}
                                    </h2>
                                    <p className="mt-1 text-[10px] text-code-muted">
                                        {formatClipCount(
                                            activeClipboard.clips_count,
                                        )}
                                    </p>
                                </div>

                                <div className="flex flex-wrap items-center gap-2 sm:ml-auto">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            createFile(activeClipboard)
                                        }
                                        disabled={
                                            processing ||
                                            !canCreateClipboardFile(
                                                activeClipboard,
                                            )
                                        }
                                        aria-label={`Create a file from ${activeClipboard.name}`}
                                        className="flex h-8 items-center gap-1.5 rounded-md bg-code-accent px-3 text-[10px] font-semibold text-code-canvas transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-35"
                                    >
                                        <FilePlus2 className="size-3" /> Create
                                        file
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            clearClipboard(activeClipboard)
                                        }
                                        disabled={
                                            processing ||
                                            activeClipboard.clips_count === 0
                                        }
                                        className="flex h-8 items-center gap-1.5 rounded-md border border-code-border px-3 text-[10px] text-code-muted transition hover:bg-code-hover hover:text-code-text disabled:cursor-not-allowed disabled:opacity-35"
                                    >
                                        <Eraser className="size-3" /> Clear
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            deleteClipboard(activeClipboard)
                                        }
                                        disabled={processing}
                                        className="flex h-8 items-center gap-1.5 rounded-md border border-rose-400/20 px-3 text-[10px] text-rose-300/80 transition hover:bg-rose-400/8 hover:text-rose-200 disabled:cursor-not-allowed disabled:opacity-35"
                                    >
                                        <Trash2 className="size-3" /> Delete
                                    </button>
                                </div>
                            </header>

                            {activeClipboard.clips.length > 0 ? (
                                <div className="flex flex-col gap-4 sm:gap-5">
                                    {activeClipboard.clips.map((clip) => (
                                        <ClipboardClipCard
                                            key={clip.id}
                                            clip={clip}
                                            copyState={copyState}
                                            processing={processing}
                                            onCopy={() => void copyClip(clip)}
                                            onDelete={() => deleteClip(clip)}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <EmptyClipboard />
                            )}
                        </section>
                    ) : (
                        <NoActiveClipboard
                            hasClipboards={clipboards.length > 0}
                        />
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function ClipboardCard({
    clipboard,
    processing,
    rename,
    onActivate,
    onBeginRename,
    onRenameChange,
    onRenameCommit,
    onRenameCancel,
}: {
    clipboard: ClipboardSession;
    processing: boolean;
    rename: RenameState | null;
    onActivate: () => void;
    onBeginRename: () => void;
    onRenameChange: (name: string) => void;
    onRenameCommit: () => void;
    onRenameCancel: () => void;
}) {
    const activate = () => {
        if (!clipboard.is_active && !processing) {
            onActivate();
        }
    };

    return (
        <article
            className={cn(
                'group aspect-square overflow-hidden rounded-xl border bg-code-canvas shadow-[0_12px_34px_rgba(0,0,0,0.16)] transition',
                clipboard.is_active
                    ? 'border-code-accent/65 ring-1 ring-code-accent/15'
                    : 'border-code-border hover:border-code-muted/70 hover:bg-code-hover/35',
            )}
        >
            <div className="flex h-full flex-col">
                <button
                    type="button"
                    onClick={activate}
                    disabled={processing}
                    aria-label={
                        clipboard.is_active
                            ? `${clipboard.name} is the active clipboard`
                            : `Switch to ${clipboard.name} clipboard`
                    }
                    aria-pressed={clipboard.is_active}
                    className="flex min-h-0 flex-1 flex-col items-center justify-center gap-2 px-3 text-code-muted transition group-hover:text-code-text disabled:cursor-not-allowed disabled:opacity-45"
                >
                    <span
                        className={cn(
                            'flex size-10 items-center justify-center rounded-xl border bg-code-raised',
                            clipboard.is_active
                                ? 'border-code-accent/35 text-code-accent'
                                : 'border-code-border text-code-faint',
                        )}
                    >
                        <Clipboard className="size-4" />
                    </span>
                    <span className="font-mono text-[9px] text-code-faint">
                        {formatClipCount(clipboard.clips_count)}
                    </span>
                    {clipboard.is_active ? (
                        <span className="rounded-full border border-code-accent/20 bg-code-accent/8 px-2 py-0.5 text-[8px] font-semibold tracking-[0.08em] text-code-accent uppercase">
                            Active
                        </span>
                    ) : null}
                </button>

                <div
                    className="flex h-10 shrink-0 items-center gap-1 border-t border-code-border/70 px-2"
                    onDoubleClick={(event) => {
                        event.stopPropagation();
                        onBeginRename();
                    }}
                >
                    {rename ? (
                        <input
                            autoFocus
                            value={rename.name}
                            disabled={processing}
                            aria-label={`Rename ${clipboard.name} clipboard`}
                            onFocus={(event) => event.currentTarget.select()}
                            onChange={(event) =>
                                onRenameChange(event.target.value)
                            }
                            onBlur={onRenameCommit}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    onRenameCommit();
                                }

                                if (event.key === 'Escape') {
                                    event.preventDefault();
                                    onRenameCancel();
                                }
                            }}
                            className="h-7 min-w-0 flex-1 rounded border border-code-accent/60 bg-code-panel px-2 text-[10px] text-code-text outline-none placeholder:text-code-faint"
                        />
                    ) : (
                        <>
                            <span
                                className="min-w-0 flex-1 truncate text-[10px] font-medium text-code-muted"
                                title={`${clipboard.name} — double-click to rename`}
                            >
                                {clipboard.name}
                            </span>
                            <button
                                type="button"
                                onClick={onBeginRename}
                                disabled={processing}
                                aria-label={`Rename ${clipboard.name} clipboard`}
                                title="Rename clipboard"
                                className="flex size-6 shrink-0 items-center justify-center rounded text-code-faint opacity-0 transition group-focus-within:opacity-100 group-hover:opacity-100 hover:bg-code-hover hover:text-code-text focus:opacity-100 disabled:cursor-not-allowed disabled:opacity-35"
                            >
                                <Pencil className="size-3" />
                            </button>
                        </>
                    )}
                </div>
            </div>
        </article>
    );
}

function ClipboardClipCard({
    clip,
    copyState,
    processing,
    onCopy,
    onDelete,
}: {
    clip: ClipboardClip;
    copyState: CopyState;
    processing: boolean;
    onCopy: () => void;
    onDelete: () => void;
}) {
    const path = [
        clip.source.project ?? 'Standalone',
        ...clip.source.folders,
        clip.source.filename,
    ];
    const copied =
        copyState?.clipId === clip.id && copyState.status === 'copied';
    const copyFailed =
        copyState?.clipId === clip.id && copyState.status === 'failed';
    const lineLabel =
        clip.source.line_start === clip.source.line_end
            ? `Line ${clip.source.line_start}`
            : `Lines ${clip.source.line_start}–${clip.source.line_end}`;

    return (
        <article className="overflow-hidden rounded-xl border border-code-border bg-code-panel shadow-[0_18px_48px_rgba(0,0,0,0.2)]">
            <header className="border-b border-code-border bg-code-raised px-3 py-3 sm:px-4">
                <div className="flex items-start gap-3">
                    <span className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-md border border-code-border bg-code-canvas text-code-muted">
                        <FileCode2 className="size-3.5" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <h3 className="truncate text-xs font-medium text-code-text">
                            {clip.source.title}
                        </h3>
                        <div
                            className="mt-1 flex min-w-0 flex-wrap items-center gap-x-1 gap-y-0.5 font-mono text-[9px] text-code-faint"
                            aria-label={`Source: ${path.join(' / ')}`}
                        >
                            {path.map((segment, index) => (
                                <span
                                    key={`${index}-${segment}`}
                                    className="contents"
                                >
                                    {index > 0 ? (
                                        <span aria-hidden="true">/</span>
                                    ) : null}
                                    <span className="max-w-48 truncate">
                                        {segment}
                                    </span>
                                </span>
                            ))}
                        </div>
                    </div>

                    <div className="flex shrink-0 items-center gap-1.5">
                        <button
                            type="button"
                            onClick={onCopy}
                            aria-label={
                                copied
                                    ? `${clip.source.title} code copied to the system clipboard`
                                    : copyFailed
                                      ? `Could not copy ${clip.source.title} code to the system clipboard`
                                      : `Copy ${clip.source.title} code to the system clipboard`
                            }
                            className={cn(
                                'flex h-7 shrink-0 items-center gap-1.5 rounded-md border px-2.5 text-[9px] font-medium transition',
                                copied
                                    ? 'border-code-success/30 bg-code-success/8 text-code-success'
                                    : copyFailed
                                      ? 'border-rose-400/25 bg-rose-400/8 text-rose-200'
                                      : 'border-code-border text-code-muted hover:bg-code-hover hover:text-code-text',
                            )}
                        >
                            {copied ? (
                                <Check className="size-3" />
                            ) : (
                                <Copy className="size-3" />
                            )}
                            {copied
                                ? 'Copied'
                                : copyFailed
                                  ? 'Copy failed'
                                  : 'Copy'}
                        </button>
                        <button
                            type="button"
                            onClick={onDelete}
                            disabled={processing}
                            aria-label={`Remove ${clip.source.title} from this clipboard`}
                            title="Remove clip"
                            className="flex size-7 shrink-0 items-center justify-center rounded-md border border-rose-400/20 text-rose-300/75 transition hover:bg-rose-400/8 hover:text-rose-200 disabled:cursor-not-allowed disabled:opacity-35"
                        >
                            <Trash2 className="size-3" />
                        </button>
                    </div>
                </div>

                <div className="mt-3 flex flex-wrap items-center gap-2 text-[8px] text-code-faint">
                    <span className="rounded border border-code-border bg-code-canvas px-1.5 py-0.5 font-mono font-semibold tracking-[0.08em] text-code-muted uppercase">
                        {clip.language}
                    </span>
                    <span>{lineLabel}</span>
                    <span className="flex items-center gap-1">
                        <GitBranch className="size-2.5" />
                        {clip.source.variation}
                    </span>
                    <span className="capitalize">
                        {clip.representation} selection
                    </span>
                    {clip.source.snippet_id === null ? (
                        <span className="rounded border border-amber-300/15 bg-amber-300/5 px-1.5 py-0.5 text-amber-200/70">
                            Source unavailable
                        </span>
                    ) : null}
                </div>
            </header>

            <SyntaxHighlightedCode
                source={clip.content}
                language={clip.language}
                ariaLabel={`${clip.source.title}, ${clip.language} code from ${lineLabel.toLowerCase()}`}
                className="max-h-[min(34rem,58vh)]"
            />
        </article>
    );
}

function EmptyClipboard() {
    return (
        <div className="flex min-h-72 items-center justify-center rounded-xl border border-dashed border-code-border bg-code-panel/35 px-5 py-10 text-center">
            <div className="max-w-sm">
                <span className="mx-auto flex size-11 items-center justify-center rounded-xl border border-code-border bg-code-raised text-code-faint">
                    <Clipboard className="size-4" />
                </span>
                <h3 className="mt-4 text-sm font-medium text-code-text">
                    This clipboard is empty
                </h3>
                <p className="mt-2 text-xs leading-5 text-code-muted">
                    Press Ctrl+V (⌘V on Mac) outside a text field, or highlight
                    code in the editor and choose Add to clipboard.
                </p>
            </div>
        </div>
    );
}

function NoActiveClipboard({ hasClipboards }: { hasClipboards: boolean }) {
    return (
        <div className="flex min-h-[24rem] items-center justify-center px-5 py-12 text-center">
            <div className="max-w-sm">
                <span className="mx-auto flex size-11 items-center justify-center rounded-xl border border-code-border bg-code-raised text-code-faint">
                    <Clipboard className="size-4" />
                </span>
                <h2 className="mt-4 text-sm font-medium text-code-text">
                    {hasClipboards
                        ? 'Choose a clipboard session'
                        : 'Create your first clipboard'}
                </h2>
                <p className="mt-2 text-xs leading-5 text-code-muted">
                    {hasClipboards
                        ? 'Select one of the clipboard squares above to view and add code.'
                        : 'Use the plus square above to start collecting related pieces of code.'}
                </p>
            </div>
        </div>
    );
}

function formatClipCount(count: number): string {
    return `${count} ${count === 1 ? 'clip' : 'clips'}`;
}

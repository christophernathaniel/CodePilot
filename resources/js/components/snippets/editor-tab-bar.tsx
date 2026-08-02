import { Star, X } from 'lucide-react';
import { SnippetFileIcon } from '@/components/snippets/snippet-file-icon';
import { cn } from '@/lib/utils';
import type { Snippet } from '@/types';

type Props = {
    snippets: Snippet[];
    activeSnippetId: number | null;
    dirtySnippetIds: Set<number>;
    onActivate: (snippet: Snippet) => void;
    onClose: (snippet: Snippet) => void;
    onToggleFavourite: (snippet: Snippet) => void;
};

export function EditorTabBar({
    snippets,
    activeSnippetId,
    dirtySnippetIds,
    onActivate,
    onClose,
    onToggleFavourite,
}: Props) {
    return (
        <div className="flex h-12 shrink-0 overflow-x-auto border-b border-code-border bg-code-canvas">
            {snippets.map((snippet) => {
                const isActive = snippet.id === activeSnippetId;
                const isDirty = dirtySnippetIds.has(snippet.id);

                return (
                    <div
                        key={snippet.id}
                        className={cn(
                            'group relative flex max-w-64 min-w-40 shrink-0 items-stretch border-r border-code-border',
                            isActive
                                ? 'bg-code-raised text-code-text'
                                : 'bg-code-canvas text-code-muted hover:bg-code-panel hover:text-code-text',
                        )}
                    >
                        {isActive && (
                            <span className="absolute inset-x-0 top-0 h-px bg-code-accent" />
                        )}
                        <button
                            type="button"
                            onClick={() => onActivate(snippet)}
                            className="flex min-w-0 flex-1 items-center gap-2 px-3 text-left"
                        >
                            <SnippetFileIcon
                                language={snippet.language}
                                contentType={snippet.content_type}
                                className="shrink-0"
                            />
                            <span className="min-w-0 flex-1 truncate text-xs">
                                {snippet.filename}
                            </span>
                        </button>
                        <button
                            type="button"
                            aria-label={
                                snippet.is_favourite
                                    ? `Remove ${snippet.filename} from favourites`
                                    : `Add ${snippet.filename} to favourites`
                            }
                            aria-pressed={snippet.is_favourite}
                            title={
                                snippet.is_favourite
                                    ? `Remove ${snippet.filename} from favourites`
                                    : `Add ${snippet.filename} to favourites`
                            }
                            onClick={() => onToggleFavourite(snippet)}
                            className={cn(
                                'flex size-6 shrink-0 items-center justify-center self-center rounded transition hover:bg-code-hover hover:text-sky-200',
                                snippet.is_favourite
                                    ? 'text-sky-300'
                                    : 'text-code-faint',
                            )}
                        >
                            <Star
                                className={cn(
                                    'size-3.5',
                                    snippet.is_favourite && 'fill-sky-300/30',
                                )}
                            />
                        </button>
                        <button
                            type="button"
                            aria-label={`Close ${snippet.filename}`}
                            onClick={() => onClose(snippet)}
                            className="mr-2 flex size-6 shrink-0 items-center justify-center self-center rounded text-code-faint transition hover:bg-code-hover hover:text-code-text"
                        >
                            {isDirty ? (
                                <span className="size-2 rounded-full bg-code-muted group-hover:hidden" />
                            ) : null}
                            <X
                                className={cn(
                                    'size-3.5',
                                    isDirty && 'hidden group-hover:block',
                                )}
                            />
                        </button>
                    </div>
                );
            })}
            <div className="min-w-6 flex-1 bg-code-canvas" />
        </div>
    );
}

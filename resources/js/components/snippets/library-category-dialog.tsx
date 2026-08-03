import type { FormDataConvertible } from '@inertiajs/core';
import { AlertTriangle, LayoutGrid } from 'lucide-react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { LibraryCategory } from '@/types';

export type LibraryCategoryDialogState =
    | { kind: 'create' }
    | { kind: 'rename'; category: LibraryCategory }
    | { kind: 'delete'; category: LibraryCategory }
    | null;

type LibraryCategoryDialogProps = {
    state: LibraryCategoryDialogState;
    processing: boolean;
    errors: Record<string, string>;
    onClose: () => void;
    onSubmit: (payload: Record<string, FormDataConvertible>) => void;
};

export function LibraryCategoryDialog(props: LibraryCategoryDialogProps) {
    if (!props.state) {
        return null;
    }

    return (
        <LibraryCategoryDialogBody
            key={
                props.state.kind === 'create'
                    ? 'create-category'
                    : `${props.state.kind}-category-${props.state.category.id}`
            }
            {...props}
            state={props.state}
        />
    );
}

function LibraryCategoryDialogBody({
    state,
    processing,
    errors,
    onClose,
    onSubmit,
}: Omit<LibraryCategoryDialogProps, 'state'> & {
    state: NonNullable<LibraryCategoryDialogState>;
}) {
    const [name, setName] = useState(
        state.kind === 'create' ? '' : state.category.name,
    );
    const isDelete = state.kind === 'delete';
    const title =
        state.kind === 'create'
            ? 'New category'
            : state.kind === 'rename'
              ? 'Rename category'
              : `Delete ${state.category.name}?`;
    const description =
        state.kind === 'create'
            ? 'Create a subject area such as Programming, Books, or Work.'
            : state.kind === 'rename'
              ? 'Update this category across the whole library.'
              : 'The workspaces inside it will remain intact and move to Uncategorised.';

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="bg-code-raised text-code-text shadow-2xl sm:max-w-sm">
                <DialogHeader>
                    <div className="mb-1 flex size-9 items-center justify-center rounded-lg bg-code-hover text-code-text">
                        {isDelete ? (
                            <AlertTriangle className="size-4 text-rose-300" />
                        ) : (
                            <LayoutGrid className="size-4" />
                        )}
                    </div>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription className="text-code-muted">
                        {description}
                    </DialogDescription>
                </DialogHeader>

                {isDelete ? (
                    <p className="rounded-md bg-rose-400/5 px-3 py-3 text-xs leading-5 text-rose-100/80">
                        No projects, guide collections, folders, or files will
                        be deleted.
                    </p>
                ) : (
                    <form
                        id="library-category-dialog-form"
                        onSubmit={(event) => {
                            event.preventDefault();
                            onSubmit({ name });
                        }}
                    >
                        <label className="flex flex-col gap-1.5 text-xs text-code-muted">
                            Name
                            <input
                                name="name"
                                value={name}
                                autoFocus
                                onChange={(event) =>
                                    setName(event.target.value)
                                }
                                className="h-9 w-full rounded-md bg-code-canvas px-3 text-xs text-code-text outline-none placeholder:text-code-faint focus:ring-1 focus:ring-code-accent/60"
                            />
                            {errors.name && (
                                <span className="text-[10px] text-rose-300">
                                    {errors.name}
                                </span>
                            )}
                        </label>
                    </form>
                )}

                <DialogFooter>
                    <button
                        type="button"
                        onClick={onClose}
                        className="h-9 rounded-md px-3 text-xs text-code-muted transition hover:bg-code-hover hover:text-code-text"
                    >
                        Cancel
                    </button>
                    <button
                        type={isDelete ? 'button' : 'submit'}
                        form={
                            isDelete
                                ? undefined
                                : 'library-category-dialog-form'
                        }
                        disabled={processing}
                        onClick={isDelete ? () => onSubmit({}) : undefined}
                        className={
                            isDelete
                                ? 'h-9 rounded-md bg-rose-400 px-3 text-xs font-semibold text-[#251012] transition hover:bg-rose-300 disabled:opacity-50'
                                : 'h-9 rounded-md bg-code-accent px-3 text-xs font-semibold text-code-canvas transition hover:bg-white disabled:opacity-50'
                        }
                    >
                        {processing
                            ? 'Working…'
                            : isDelete
                              ? 'Delete category'
                              : state.kind === 'create'
                                ? 'Create category'
                                : 'Save name'}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

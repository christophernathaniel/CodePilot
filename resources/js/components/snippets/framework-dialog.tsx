import type { FormDataConvertible } from '@inertiajs/core';
import { Braces } from 'lucide-react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type FrameworkDialogProps = {
    open: boolean;
    processing: boolean;
    errors: Record<string, string>;
    onClose: () => void;
    onSubmit: (payload: Record<string, FormDataConvertible>) => void;
};

export function FrameworkDialog({
    open,
    processing,
    errors,
    onClose,
    onSubmit,
}: FrameworkDialogProps) {
    const [name, setName] = useState('');

    return (
        <Dialog open={open} onOpenChange={(nextOpen) => !nextOpen && onClose()}>
            <DialogContent className="bg-code-raised text-code-text shadow-2xl sm:max-w-sm">
                <DialogHeader>
                    <div className="mb-1 flex size-9 items-center justify-center rounded-lg bg-code-hover text-code-text">
                        <Braces className="size-4" />
                    </div>
                    <DialogTitle>New framework</DialogTitle>
                    <DialogDescription className="text-code-muted">
                        Add a framework such as Vue, Django, or Rails for
                        filtering and tagging workspaces and files.
                    </DialogDescription>
                </DialogHeader>

                <form
                    id="framework-dialog-form"
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
                            onChange={(event) => setName(event.target.value)}
                            className="h-9 w-full rounded-md bg-code-canvas px-3 text-xs text-code-text outline-none placeholder:text-code-faint focus:ring-1 focus:ring-code-accent/60"
                        />
                        {errors.name && (
                            <span className="text-[10px] text-rose-300">
                                {errors.name}
                            </span>
                        )}
                    </label>
                </form>

                <DialogFooter>
                    <button
                        type="button"
                        onClick={onClose}
                        className="h-9 rounded-md px-3 text-xs text-code-muted transition hover:bg-code-hover hover:text-code-text"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        form="framework-dialog-form"
                        disabled={processing}
                        className="h-9 rounded-md bg-code-accent px-3 text-xs font-semibold text-code-canvas transition hover:bg-white disabled:opacity-50"
                    >
                        {processing ? 'Creating…' : 'Create framework'}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

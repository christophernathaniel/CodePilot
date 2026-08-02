import type { PropsWithChildren } from 'react';

export default function SnippetWorkspaceLayout({
    children,
}: PropsWithChildren) {
    return (
        <main className="h-svh min-h-0 overflow-hidden bg-code-canvas text-code-text">
            {children}
        </main>
    );
}

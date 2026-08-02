import {
    BookOpenText,
    Braces,
    Check,
    ChevronRight,
    Clipboard,
    Code2,
    Copy,
    Eye,
    GitBranch,
    Plus,
    Save,
    TextCursorInput,
} from 'lucide-react';
import { SnippetFileIcon } from '@/components/snippets/snippet-file-icon';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ParsedSnippetSection } from '@/lib/snippets/snippet-sections';
import { cn } from '@/lib/utils';
import type { Snippet, SnippetProject, SnippetVariation } from '@/types';

export type EditorMode = 'source' | 'preview' | 'playback';

export type SnippetEditorToolbarProps = {
    snippet: Snippet;
    project: SnippetProject | null;
    folderPath: string[];
    activeVariation: SnippetVariation;
    variations: SnippetVariation[];
    mode: EditorMode;
    dirty: boolean;
    saving: boolean;
    copied: boolean;
    sections?: ParsedSnippetSection[];
    activeSectionKey?: string | null;
    onModeChange: (mode: EditorMode) => void;
    onVariationSelect: (variation: SnippetVariation) => void;
    onCreateVariation: () => void;
    onSectionSelect?: (section: ParsedSnippetSection) => void;
    onCopySection?: (section: ParsedSnippetSection) => void;
    onSave: () => void;
    onCopyRendered: () => void;
    onCopySource: () => void;
    onSelectAll: () => void;
};

export function SnippetEditorToolbar({
    snippet,
    project,
    folderPath,
    activeVariation,
    variations,
    mode,
    dirty,
    saving,
    copied,
    sections = [],
    activeSectionKey = null,
    onModeChange,
    onVariationSelect,
    onCreateVariation,
    onSectionSelect,
    onCopySection,
    onSave,
    onCopyRendered,
    onCopySource,
    onSelectAll,
}: SnippetEditorToolbarProps) {
    const activeSection =
        sections.find((section) => section.key === activeSectionKey) ?? null;

    return (
        <>
            <div className="flex h-8 shrink-0 items-center gap-1 border-b border-code-border bg-code-canvas px-3 text-[10px] text-code-faint">
                <span className="truncate">
                    {project?.name ?? 'Standalone'}
                </span>
                {folderPath.map((segment) => (
                    <span key={segment} className="contents">
                        <ChevronRight className="size-3 shrink-0 text-code-faint" />
                        <span className="truncate">{segment}</span>
                    </span>
                ))}
                <ChevronRight className="size-3 shrink-0 text-code-faint" />
                <SnippetFileIcon
                    language={snippet.language}
                    contentType={snippet.content_type}
                    className="ml-0.5 shrink-0"
                />
                <span className="truncate text-code-muted">
                    {snippet.filename}
                </span>
            </div>

            <div className="flex h-11 shrink-0 items-center gap-2 border-b border-code-border bg-code-panel px-3">
                <div className="flex h-7 items-center rounded-md border border-code-border bg-code-canvas p-0.5">
                    <ModeButton
                        active={mode === 'source'}
                        label="Source"
                        icon={Code2}
                        onClick={() => onModeChange('source')}
                    />
                    <ModeButton
                        active={mode === 'preview'}
                        label="Preview"
                        icon={Eye}
                        onClick={() => onModeChange('preview')}
                    />
                    {snippet.content_type === 'guide' && (
                        <ModeButton
                            active={mode === 'playback'}
                            label="Playback"
                            icon={BookOpenText}
                            onClick={() => onModeChange('playback')}
                        />
                    )}
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            className="flex h-7 max-w-52 min-w-0 items-center gap-1.5 rounded-md border border-code-border bg-code-canvas px-2 text-[10px] text-code-muted transition hover:bg-code-hover hover:text-code-text"
                            aria-label={`Active variation: ${activeVariation.name}`}
                        >
                            <GitBranch className="size-3 shrink-0" />
                            <span className="truncate">
                                {activeVariation.name}
                            </span>
                            {activeVariation.is_default && (
                                <span className="shrink-0 rounded bg-code-raised px-1 py-0.5 text-[8px] font-semibold tracking-[0.08em] text-code-faint uppercase">
                                    Default
                                </span>
                            )}
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" className="w-60">
                        {variations.map((variation) => (
                            <DropdownMenuItem
                                key={variation.id}
                                onSelect={() => onVariationSelect(variation)}
                                className="gap-2"
                            >
                                <Check
                                    className={cn(
                                        'size-3.5',
                                        variation.id === activeVariation.id
                                            ? 'opacity-100'
                                            : 'opacity-0',
                                    )}
                                />
                                <span className="min-w-0 flex-1 truncate">
                                    {variation.name}
                                </span>
                                {variation.is_default && (
                                    <span className="text-[9px] text-code-faint">
                                        Default
                                    </span>
                                )}
                            </DropdownMenuItem>
                        ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onSelect={onCreateVariation}>
                            <Plus /> New variation
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                {sections.length > 0 && (
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                type="button"
                                className="flex h-7 max-w-52 min-w-0 items-center gap-1.5 rounded-md border border-code-border bg-code-canvas px-2 text-[10px] text-code-muted transition hover:bg-code-hover hover:text-code-text"
                                aria-label={
                                    activeSection
                                        ? `Active embedded snippet: ${activeSection.label}`
                                        : 'Choose an embedded snippet'
                                }
                            >
                                <Braces className="size-3 shrink-0 text-sky-300/80" />
                                <span className="truncate">
                                    {activeSection?.label ??
                                        `${sections.length} embedded snippets`}
                                </span>
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" className="w-64">
                            {sections.map((section) => (
                                <DropdownMenuItem
                                    key={section.key}
                                    onSelect={() => onSectionSelect?.(section)}
                                    className="gap-2"
                                >
                                    <Check
                                        className={cn(
                                            'size-3.5',
                                            section.key === activeSectionKey
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    <span className="min-w-0 flex-1 truncate">
                                        {section.label}
                                    </span>
                                    <span className="font-mono text-[9px] text-code-faint">
                                        L{section.start_line}–{section.end_line}
                                    </span>
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                )}

                <div className="min-w-0 flex-1" />

                <button
                    type="button"
                    onClick={onSelectAll}
                    className={cn(
                        'hidden h-7 items-center gap-1.5 rounded px-2 text-[10px] text-code-muted transition hover:bg-code-hover hover:text-code-text md:flex',
                        mode === 'playback' && 'md:hidden',
                    )}
                >
                    <TextCursorInput className="size-3" /> Select all
                </button>

                {sections.length > 0 && (
                    <button
                        type="button"
                        onClick={() => {
                            if (activeSection) {
                                onCopySection?.(activeSection);
                            }
                        }}
                        disabled={
                            !activeSection ||
                            activeSection.content.length === 0 ||
                            !onCopySection
                        }
                        className="flex h-7 shrink-0 items-center gap-1.5 rounded border border-sky-400/20 bg-sky-400/5 px-2 text-[10px] text-sky-200 transition hover:border-sky-300/30 hover:bg-sky-400/10 disabled:cursor-not-allowed disabled:border-code-border disabled:bg-transparent disabled:text-code-faint"
                    >
                        <Braces className="size-3" />
                        Copy section
                    </button>
                )}

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <button
                            type="button"
                            className="flex h-7 items-center gap-1.5 rounded border border-code-border px-2 text-[10px] text-code-muted transition hover:bg-code-hover hover:text-code-text"
                        >
                            {copied ? (
                                <Check className="size-3 text-code-success" />
                            ) : (
                                <Copy className="size-3" />
                            )}
                            {copied ? 'Copied' : 'Copy'}
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-44">
                        <DropdownMenuItem onSelect={onCopyRendered}>
                            <Clipboard /> Copy rendered output
                        </DropdownMenuItem>
                        <DropdownMenuItem onSelect={onCopySource}>
                            <Code2 /> Copy template source
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <button
                    type="button"
                    onClick={onSave}
                    disabled={!dirty || saving || mode !== 'source'}
                    className="flex h-7 items-center gap-1.5 rounded bg-code-accent px-2.5 text-[10px] font-semibold text-code-canvas transition hover:bg-white disabled:cursor-not-allowed disabled:bg-code-raised disabled:text-code-faint"
                >
                    <Save className="size-3" />
                    {saving ? 'Saving…' : 'Save'}
                </button>
            </div>
        </>
    );
}

export type SnippetEditorStatusProps = {
    language: string;
    activeVariation: SnippetVariation;
    activeSection?: ParsedSnippetSection | null;
    dirty: boolean;
    line: number;
    column: number;
    variableCount: number;
};

export function SnippetEditorStatus({
    language,
    activeVariation,
    activeSection = null,
    dirty,
    line,
    column,
    variableCount,
}: SnippetEditorStatusProps) {
    return (
        <footer className="flex h-6 shrink-0 items-center gap-4 border-t border-code-border bg-code-panel px-3 font-mono text-[9px] text-code-faint">
            <span className="flex items-center gap-1.5">
                <span
                    className={cn(
                        'size-1.5 rounded-full',
                        dirty ? 'bg-[#d5a85e]' : 'bg-code-success',
                    )}
                />
                {dirty ? 'Modified' : 'Saved'}
            </span>
            <span>
                Ln {line}, Col {column}
            </span>
            <span>Spaces: 4</span>
            {variableCount > 0 && <span>{variableCount} variables</span>}
            {activeSection && (
                <span
                    className="flex min-w-0 items-center gap-1 text-sky-200/80"
                    title={`Embedded snippet: ${activeSection.label}`}
                >
                    <Braces className="size-3 shrink-0" />
                    <span className="max-w-40 truncate">
                        {activeSection.label}
                    </span>
                </span>
            )}
            <span className="ml-auto">UTF-8</span>
            <span
                className="flex min-w-0 items-center gap-1.5"
                title={`Variation: ${activeVariation.name}`}
            >
                <GitBranch className="size-3 shrink-0" />
                <span className="max-w-40 truncate">
                    {activeVariation.name}
                </span>
                {activeVariation.is_default && (
                    <span className="text-code-muted">Default</span>
                )}
            </span>
            <span className="capitalize">{language}</span>
        </footer>
    );
}

function ModeButton({
    active,
    label,
    icon: Icon,
    onClick,
}: {
    active: boolean;
    label: string;
    icon: typeof Code2;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex h-6 items-center gap-1.5 rounded px-2 text-[9px] font-medium transition',
                active
                    ? 'bg-code-raised text-code-text'
                    : 'text-code-faint hover:text-code-muted',
            )}
        >
            <Icon className="size-3" /> {label}
        </button>
    );
}

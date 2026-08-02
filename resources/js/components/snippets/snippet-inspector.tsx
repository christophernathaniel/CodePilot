import {
    Braces,
    Check,
    Copy,
    GitBranch,
    Layers3,
    Pencil,
    Plus,
    Save,
    SlidersHorizontal,
    Star,
    Tag,
    Trash2,
    Variable,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { SnippetFileIcon } from '@/components/snippets/snippet-file-icon';
import type { ParsedSnippetSection } from '@/lib/snippets/snippet-sections';
import { cn } from '@/lib/utils';
import type { Snippet, SnippetVariation, VariablePreset } from '@/types';

export type InspectorVariable = {
    name: string;
    defaultValue: string;
};

export type SnippetInspectorProps = {
    snippet: Snippet;
    activeVariation: SnippetVariation;
    variables: InspectorVariable[];
    variableValues: Record<string, string>;
    selectedPresetId: number | null;
    sections?: ParsedSnippetSection[];
    activeSectionKey?: string | null;
    onVariableChange: (name: string, value: string) => void;
    onPresetSelect: (preset: VariablePreset | null) => void;
    onCreatePreset: () => void;
    onUpdatePreset: (preset: VariablePreset) => void;
    onDeletePreset: (preset: VariablePreset) => void;
    onVariationSelect: (variation: SnippetVariation) => void;
    onCreateVariation: () => void;
    onRenameVariation: (variation: SnippetVariation) => void;
    onMakeDefaultVariation: (variation: SnippetVariation) => void;
    onDeleteVariation: (variation: SnippetVariation) => void;
    onSectionSelect?: (section: ParsedSnippetSection) => void;
    onCopySection?: (section: ParsedSnippetSection) => void;
    onEditMetadata: () => void;
};

type InspectorView = 'variables' | 'variations' | 'sections';

export function SnippetInspector({
    snippet,
    activeVariation,
    variables,
    variableValues,
    selectedPresetId,
    sections = [],
    activeSectionKey = null,
    onVariableChange,
    onPresetSelect,
    onCreatePreset,
    onUpdatePreset,
    onDeletePreset,
    onVariationSelect,
    onCreateVariation,
    onRenameVariation,
    onMakeDefaultVariation,
    onDeleteVariation,
    onSectionSelect,
    onCopySection,
    onEditMetadata,
}: SnippetInspectorProps) {
    const [view, setView] = useState<InspectorView>('variables');
    const selectedPreset = useMemo(
        () =>
            snippet.presets.find((preset) => preset.id === selectedPresetId) ??
            null,
        [selectedPresetId, snippet.presets],
    );

    return (
        <aside className="flex w-72 shrink-0 flex-col border-l border-code-border bg-code-panel xl:w-80">
            <div className="flex h-12 shrink-0 items-center gap-2 border-b border-code-border px-4">
                <SnippetFileIcon
                    language={snippet.language}
                    contentType={snippet.content_type}
                />
                <div className="min-w-0 flex-1">
                    <p className="truncate text-xs font-medium text-code-text">
                        {snippet.title}
                    </p>
                    <p className="truncate font-mono text-[9px] text-code-faint">
                        {snippet.language}
                    </p>
                </div>
                <button
                    type="button"
                    onClick={onEditMetadata}
                    className="rounded p-1.5 text-code-faint transition hover:bg-code-hover hover:text-code-text"
                    aria-label="Edit snippet details"
                >
                    <SlidersHorizontal className="size-3.5" />
                </button>
            </div>

            <div className="border-b border-code-border px-3 py-3">
                <div className="flex flex-wrap gap-1.5">
                    <span className="inline-flex h-5 items-center gap-1 rounded border border-code-border bg-code-raised px-1.5 text-[9px] font-medium text-code-text">
                        <Tag className="size-2.5" /> {snippet.language}
                    </span>
                    {snippet.frameworks.map((framework) => (
                        <span
                            key={framework.id}
                            className="inline-flex h-5 items-center gap-1 rounded border border-sky-400/20 bg-sky-400/5 px-1.5 text-[9px] text-sky-200"
                        >
                            <Layers3 className="size-2.5" /> {framework.name}
                        </span>
                    ))}
                    {snippet.tags.map((tag) => (
                        <span
                            key={tag.id}
                            className="inline-flex h-5 items-center rounded border border-code-border bg-code-canvas/45 px-1.5 text-[9px] text-code-muted"
                            style={
                                tag.color
                                    ? {
                                          borderColor: `${tag.color}33`,
                                          color: tag.color,
                                      }
                                    : undefined
                            }
                        >
                            {tag.name}
                        </span>
                    ))}
                    {snippet.tags.length === 0 && (
                        <button
                            type="button"
                            onClick={onEditMetadata}
                            className="text-[9px] text-code-faint hover:text-code-text"
                        >
                            + add tags
                        </button>
                    )}
                </div>
                <p className="mt-2 font-mono text-[8px] text-code-faint">
                    {snippet.usage.copies_30d} copies in 30 days ·{' '}
                    {snippet.usage.copies_total} total
                    {snippet.usage.last_copied_at
                        ? ` · last ${formatDate(snippet.usage.last_copied_at)}`
                        : ''}
                </p>
            </div>

            <div className="grid h-9 shrink-0 grid-cols-3 border-b border-code-border p-1">
                <InspectorTab
                    active={view === 'variables'}
                    onClick={() => setView('variables')}
                >
                    <Variable className="size-3" /> Variables
                </InspectorTab>
                <InspectorTab
                    active={view === 'variations'}
                    onClick={() => setView('variations')}
                >
                    <GitBranch className="size-3" /> Variations
                </InspectorTab>
                <InspectorTab
                    active={view === 'sections'}
                    onClick={() => setView('sections')}
                >
                    <Braces className="size-3" /> Sections
                </InspectorTab>
            </div>

            {view === 'variables' ? (
                <div className="min-h-0 flex-1 overflow-y-auto p-3">
                    <div className="mb-3 flex items-center gap-2">
                        <label htmlFor="snippet-preset" className="sr-only">
                            Variable preset
                        </label>
                        <select
                            id="snippet-preset"
                            value={selectedPresetId ?? ''}
                            onChange={(event) => {
                                const presetId = Number(event.target.value);
                                onPresetSelect(
                                    snippet.presets.find(
                                        (preset) => preset.id === presetId,
                                    ) ?? null,
                                );
                            }}
                            className="h-8 min-w-0 flex-1 rounded border border-code-border bg-code-canvas px-2 text-[10px] text-code-text outline-none focus:border-code-accent/60"
                        >
                            <option value="">My default</option>
                            {snippet.presets.map((preset) => (
                                <option key={preset.id} value={preset.id}>
                                    {preset.name}
                                </option>
                            ))}
                        </select>
                        <button
                            type="button"
                            onClick={onCreatePreset}
                            disabled={variables.length === 0}
                            aria-label="Create preset"
                            className="flex size-8 items-center justify-center rounded border border-code-border text-code-faint transition hover:bg-code-hover hover:text-code-text disabled:cursor-not-allowed disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-code-faint"
                        >
                            <Plus className="size-3.5" />
                        </button>
                    </div>

                    {variables.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-code-border px-3 py-5 text-center">
                            <Variable className="mx-auto mb-2 size-4 text-code-faint" />
                            <p className="text-[10px] leading-4 text-code-muted">
                                Add{' '}
                                <code className="text-[#d5a85e]">
                                    {'{{{name:default}}}'}
                                </code>{' '}
                                to turn values into reusable variables.
                            </p>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {variables.map((variable) => (
                                <label
                                    key={variable.name}
                                    className="flex flex-col gap-1.5"
                                >
                                    <span className="flex items-center justify-between gap-2 font-mono text-[9px] text-code-muted">
                                        <span className="truncate">
                                            {variable.name}
                                        </span>
                                        <span className="truncate text-code-faint">
                                            default: {variable.defaultValue}
                                        </span>
                                    </span>
                                    <input
                                        value={
                                            variableValues[variable.name] ??
                                            variable.defaultValue
                                        }
                                        onChange={(event) =>
                                            onVariableChange(
                                                variable.name,
                                                event.target.value,
                                            )
                                        }
                                        className="h-8 rounded border border-code-border bg-code-canvas px-2.5 font-mono text-[11px] text-code-text outline-none placeholder:text-code-faint focus:border-code-accent/60"
                                    />
                                </label>
                            ))}
                        </div>
                    )}

                    <div className="mt-4 border-t border-code-border pt-3">
                        {selectedPreset ? (
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() =>
                                        onUpdatePreset(selectedPreset)
                                    }
                                    className="flex h-8 flex-1 items-center justify-center gap-1.5 rounded bg-code-accent text-[10px] font-semibold text-code-canvas transition hover:bg-white"
                                >
                                    <Save className="size-3" /> Save preset
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        onDeletePreset(selectedPreset)
                                    }
                                    aria-label="Delete selected preset"
                                    className="flex size-8 items-center justify-center rounded border border-rose-400/15 text-rose-300/70 transition hover:bg-rose-400/8 hover:text-rose-200"
                                >
                                    <Trash2 className="size-3" />
                                </button>
                            </div>
                        ) : (
                            <p className="text-[9px] leading-4 text-code-faint">
                                Defaults come from the template. Create a preset
                                to keep another named set of values.
                            </p>
                        )}
                    </div>
                </div>
            ) : view === 'variations' ? (
                <div className="min-h-0 flex-1 overflow-y-auto p-3">
                    <div className="mb-3 flex items-start justify-between gap-3">
                        <div>
                            <p className="text-[10px] font-medium text-code-text">
                                Code variations
                            </p>
                            <p className="mt-1 text-[9px] leading-4 text-code-faint">
                                Alternate implementations of this snippet — not
                                revision history.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={onCreateVariation}
                            className="flex h-7 shrink-0 items-center gap-1.5 rounded border border-code-border bg-code-canvas px-2 text-[9px] font-medium text-code-muted transition hover:bg-code-hover hover:text-code-text"
                        >
                            <Plus className="size-3" /> New
                        </button>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        {snippet.variations.map((variation) => {
                            const isActive =
                                variation.id === activeVariation.id;
                            const isOnlyVariation =
                                snippet.variations.length === 1;
                            const deleteDisabled =
                                variation.is_default || isOnlyVariation;

                            return (
                                <div
                                    key={variation.id}
                                    className={cn(
                                        'rounded-md border transition',
                                        isActive
                                            ? 'border-code-border bg-code-raised'
                                            : 'border-transparent hover:border-code-border/60 hover:bg-code-hover',
                                    )}
                                >
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onVariationSelect(variation)
                                        }
                                        className="flex w-full items-start gap-2.5 px-2.5 py-2.5 text-left"
                                        aria-current={
                                            isActive ? 'true' : undefined
                                        }
                                    >
                                        {isActive ? (
                                            <Check className="mt-0.5 size-3.5 shrink-0 text-code-success" />
                                        ) : (
                                            <GitBranch className="mt-0.5 size-3.5 shrink-0 text-code-faint" />
                                        )}
                                        <span className="min-w-0 flex-1">
                                            <span className="flex items-center gap-1.5">
                                                <span className="truncate text-[10px] font-medium text-code-text">
                                                    {variation.name}
                                                </span>
                                                {variation.is_default && (
                                                    <span className="shrink-0 rounded bg-code-canvas px-1.5 py-0.5 text-[8px] font-semibold tracking-[0.08em] text-code-muted uppercase">
                                                        Default
                                                    </span>
                                                )}
                                            </span>
                                            <span className="mt-0.5 block text-[9px] text-code-faint">
                                                Updated{' '}
                                                {formatDate(
                                                    variation.updated_at,
                                                )}
                                            </span>
                                        </span>
                                    </button>

                                    {isActive && (
                                        <div className="grid grid-cols-3 gap-1 border-t border-code-border/70 p-1.5">
                                            <VariationAction
                                                label="Rename"
                                                onClick={() =>
                                                    onRenameVariation(variation)
                                                }
                                            >
                                                <Pencil className="size-3" />
                                            </VariationAction>
                                            <VariationAction
                                                label={
                                                    variation.is_default
                                                        ? 'Default'
                                                        : 'Make default'
                                                }
                                                disabled={variation.is_default}
                                                onClick={() =>
                                                    onMakeDefaultVariation(
                                                        variation,
                                                    )
                                                }
                                            >
                                                <Star className="size-3" />
                                            </VariationAction>
                                            <VariationAction
                                                label="Delete"
                                                destructive
                                                disabled={deleteDisabled}
                                                title={
                                                    variation.is_default
                                                        ? 'Choose another default before deleting this variation.'
                                                        : isOnlyVariation
                                                          ? 'A snippet must keep at least one variation.'
                                                          : 'Delete variation'
                                                }
                                                onClick={() =>
                                                    onDeleteVariation(variation)
                                                }
                                            >
                                                <Trash2 className="size-3" />
                                            </VariationAction>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            ) : (
                <div className="min-h-0 flex-1 overflow-y-auto p-3">
                    <div className="mb-3">
                        <p className="text-[10px] font-medium text-code-text">
                            Embedded snippets
                        </p>
                        <p className="mt-1 text-[9px] leading-4 text-code-faint">
                            Search, open, and copy focused pieces of this file.
                        </p>
                    </div>

                    <div className="mb-3 rounded-md border border-sky-400/15 bg-sky-400/5 px-2.5 py-2 text-[9px] leading-4 text-sky-100/70">
                        Each section inherits this file&apos;s{' '}
                        <span className="font-medium text-sky-100">
                            {snippet.language}
                        </span>
                        , frameworks, and tags.
                    </div>

                    {sections.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-code-border px-3 py-5 text-center">
                            <Braces className="mx-auto mb-2 size-4 text-code-faint" />
                            <p className="text-[10px] leading-4 text-code-muted">
                                Add this marker before each reusable section:
                            </p>
                            <code className="mt-2 block rounded border border-code-border bg-code-canvas px-2 py-1.5 text-[9px] text-sky-200">
                                {'{!# snippet: snippet_name #!}'}
                            </code>
                            <p className="mt-2 text-[9px] leading-4 text-code-faint">
                                The next marker starts a new embedded snippet.
                                Wrap it in your language&apos;s line or block
                                comment to keep the full file runnable.
                            </p>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-2">
                            {sections.map((section) => {
                                const isActive =
                                    section.key === activeSectionKey;
                                const lineLabel =
                                    section.start_line === section.end_line
                                        ? `Line ${section.start_line}`
                                        : `Lines ${section.start_line}–${section.end_line}`;

                                return (
                                    <div
                                        key={section.key}
                                        className={cn(
                                            'overflow-hidden rounded-md border transition',
                                            isActive
                                                ? 'border-sky-400/30 bg-sky-400/5'
                                                : 'border-code-border bg-code-canvas/35 hover:border-code-muted/50',
                                        )}
                                    >
                                        <button
                                            type="button"
                                            onClick={() =>
                                                onSectionSelect?.(section)
                                            }
                                            disabled={!onSectionSelect}
                                            className="flex w-full items-start gap-2.5 px-2.5 py-2.5 text-left disabled:cursor-default"
                                            aria-current={
                                                isActive ? 'true' : undefined
                                            }
                                        >
                                            {isActive ? (
                                                <Check className="mt-0.5 size-3.5 shrink-0 text-sky-300" />
                                            ) : (
                                                <Braces className="mt-0.5 size-3.5 shrink-0 text-code-faint" />
                                            )}
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-[10px] font-medium text-code-text">
                                                    {section.label}
                                                </span>
                                                <span className="mt-0.5 block font-mono text-[8px] text-code-faint">
                                                    {lineLabel}
                                                </span>
                                            </span>
                                        </button>

                                        <div className="grid grid-cols-2 gap-1 border-t border-code-border/70 p-1.5">
                                            <SectionAction
                                                label="Open section"
                                                disabled={!onSectionSelect}
                                                onClick={() =>
                                                    onSectionSelect?.(section)
                                                }
                                            >
                                                <Braces className="size-3" />
                                            </SectionAction>
                                            <SectionAction
                                                label="Copy"
                                                disabled={
                                                    !onCopySection ||
                                                    section.content.length === 0
                                                }
                                                onClick={() =>
                                                    onCopySection?.(section)
                                                }
                                            >
                                                <Copy className="size-3" />
                                            </SectionAction>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}
        </aside>
    );
}

function VariationAction({
    label,
    children,
    disabled = false,
    destructive = false,
    title,
    onClick,
}: {
    label: string;
    children: React.ReactNode;
    disabled?: boolean;
    destructive?: boolean;
    title?: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            title={title}
            onClick={onClick}
            className={cn(
                'flex h-7 min-w-0 items-center justify-center gap-1 rounded px-1.5 text-[8px] font-medium transition disabled:cursor-not-allowed disabled:opacity-35',
                destructive
                    ? 'text-rose-300/75 hover:bg-rose-400/8 hover:text-rose-200 disabled:hover:bg-transparent'
                    : 'text-code-muted hover:bg-code-hover hover:text-code-text disabled:hover:bg-transparent disabled:hover:text-code-muted',
            )}
        >
            {children}
            <span className="truncate">{label}</span>
        </button>
    );
}

function SectionAction({
    label,
    children,
    disabled = false,
    onClick,
}: {
    label: string;
    children: React.ReactNode;
    disabled?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className="flex h-7 min-w-0 items-center justify-center gap-1 rounded px-1.5 text-[8px] font-medium text-code-muted transition hover:bg-code-hover hover:text-code-text disabled:cursor-not-allowed disabled:opacity-35 disabled:hover:bg-transparent disabled:hover:text-code-muted"
        >
            {children}
            <span className="truncate">{label}</span>
        </button>
    );
}

function InspectorTab({
    active,
    children,
    onClick,
}: {
    active: boolean;
    children: React.ReactNode;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex items-center justify-center gap-1.5 rounded text-[9px] font-semibold tracking-[0.08em] uppercase transition',
                active
                    ? 'bg-code-raised text-code-text'
                    : 'text-code-faint hover:text-code-muted',
            )}
        >
            {children}
        </button>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

import { ChevronDown, Code2, ListFilter } from 'lucide-react';
import { useId, useState } from 'react';
import type { ReactNode, RefObject } from 'react';
import { SnippetSearch } from '@/components/snippets/snippet-search';
import type { SnippetSearchResult } from '@/components/snippets/snippet-search';
import { SnippetSearchPreview } from '@/components/snippets/snippet-search-preview';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    getActiveMegaSearchFilterCount,
    hasActiveMegaSearchFilters,
} from '@/lib/snippets/mega-search-filters';
import { cn } from '@/lib/utils';
import type { Framework, LanguageOption, LibraryCategory } from '@/types';

type WorkspaceMegaSearchProps = {
    query: string;
    suggestions: string[];
    results: SnippetSearchResult[];
    totalResults: number;
    inputRef: RefObject<HTMLInputElement | null>;
    languageValue: string | null;
    languageOptions: LanguageOption[];
    categoryValue: number | null;
    categoryOptions: LibraryCategory[];
    frameworkValue: number | null;
    frameworkOptions: Framework[];
    searchCode: boolean;
    onQueryChange: (query: string) => void;
    onCaretChange: (caretPosition: number) => void;
    onSuggestionAccept: (suggestion: string, caretPosition?: number) => void;
    onLanguageChange: (language: string | null) => void;
    onCategoryChange: (categoryId: number | null) => void;
    onFrameworkChange: (frameworkId: number | null) => void;
    onSearchCodeChange: (searchCode: boolean) => void;
    onOpen: (result: SnippetSearchResult) => boolean | void;
    onClose: () => void;
};

export function WorkspaceMegaSearch({
    query,
    suggestions,
    results,
    totalResults,
    inputRef,
    languageValue,
    languageOptions,
    categoryValue,
    categoryOptions,
    frameworkValue,
    frameworkOptions,
    searchCode,
    onQueryChange,
    onCaretChange,
    onSuggestionAccept,
    onLanguageChange,
    onCategoryChange,
    onFrameworkChange,
    onSearchCodeChange,
    onOpen,
    onClose,
}: WorkspaceMegaSearchProps) {
    const [areFiltersOpen, setAreFiltersOpen] = useState(false);
    const filterPanelId = useId();
    const taxonomyFilters = {
        language: languageValue,
        libraryCategoryId: categoryValue,
        frameworkId: frameworkValue,
    };
    const hasTaxonomyFilters = hasActiveMegaSearchFilters(taxonomyFilters);
    const activeFilterCount = getActiveMegaSearchFilterCount(
        taxonomyFilters,
        searchCode,
    );
    const activeFilterSummary = [
        languageValue ? `@${languageValue}` : null,
        categoryValue !== null
            ? `$${categoryOptions.find((category) => category.id === categoryValue)?.name ?? 'category'}`
            : null,
        frameworkValue !== null
            ? `%${frameworkOptions.find((framework) => framework.id === frameworkValue)?.name ?? 'framework'}`
            : null,
        searchCode ? null : 'code off',
    ]
        .filter((value): value is string => value !== null)
        .join(', ');
    const filterToggleLabel = `${areFiltersOpen ? 'Hide' : 'Show'} search filters${activeFilterCount > 0 ? `, ${activeFilterCount} active: ${activeFilterSummary}` : ''}`;
    const hasSearch = query.trim().length > 0 || hasTaxonomyFilters;

    return (
        <Dialog
            open
            onOpenChange={(open) => {
                if (!open) {
                    onClose();
                }
            }}
        >
            <DialogContent
                aria-describedby="quick-file-search-description"
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    inputRef.current?.focus({ preventScroll: true });
                }}
                onCloseAutoFocus={(event) => event.preventDefault()}
                className={cn(
                    'snippet-workspace inset-0 top-0 left-0 z-100 flex h-svh w-screen max-w-none translate-x-0 translate-y-0 gap-0 rounded-none border-0 bg-code-canvas text-code-text shadow-none sm:max-w-none [&_[data-slot=dialog-close]]:top-5 [&_[data-slot=dialog-close]]:right-5 [&_[data-slot=dialog-close]]:flex [&_[data-slot=dialog-close]]:size-9 [&_[data-slot=dialog-close]]:items-center [&_[data-slot=dialog-close]]:justify-center [&_[data-slot=dialog-close]]:rounded-lg [&_[data-slot=dialog-close]]:text-code-faint [&_[data-slot=dialog-close]]:opacity-100 [&_[data-slot=dialog-close]]:ring-0 [&_[data-slot=dialog-close]]:ring-offset-0 [&_[data-slot=dialog-close]]:hover:bg-code-hover [&_[data-slot=dialog-close]]:hover:text-code-text',
                    hasSearch
                        ? 'items-stretch justify-start overflow-x-hidden overflow-y-auto px-4 pt-16 pb-4 sm:px-7 sm:pt-16 sm:pb-6'
                        : 'items-center justify-center overflow-hidden px-5 pb-[12vh]',
                )}
            >
                <DialogTitle className="sr-only">Quick file search</DialogTitle>
                <DialogDescription
                    id="quick-file-search-description"
                    className="sr-only"
                >
                    Search and open any file or embedded snippet in your
                    account.
                </DialogDescription>
                <div className="pointer-events-none absolute inset-0 [background-image:radial-gradient(circle_at_center,rgba(99,163,207,0.12),transparent_46%)] opacity-70" />

                <div
                    className={cn(
                        'relative w-full transition-[max-width,height] duration-300',
                        hasSearch
                            ? 'mx-auto flex h-full min-h-128 max-w-7xl flex-col'
                            : 'max-w-3xl',
                    )}
                >
                    <SnippetSearch
                        query={query}
                        suggestions={suggestions}
                        results={results}
                        totalResults={totalResults}
                        inputRef={inputRef}
                        variant="hero"
                        resultsMode={hasSearch ? 'workspace' : 'popover'}
                        showResultsWithoutQuery={hasTaxonomyFilters}
                        placeholder="Search… @language $category %framework ^title"
                        resultsLabel="Files & sections"
                        shortcutKey="P"
                        shortcutAriaLabel="Meta+P Control+P"
                        searchHelp="Use at for language, dollar for category, percent for framework, and caret for title-only search. Filters combine with the query. Press Tab to complete the active token."
                        deferEscapeToParent
                        onQueryChange={onQueryChange}
                        onCaretChange={onCaretChange}
                        onSuggestionAccept={onSuggestionAccept}
                        onOpen={onOpen}
                        inputActions={
                            <button
                                type="button"
                                aria-label={filterToggleLabel}
                                aria-expanded={areFiltersOpen}
                                aria-controls={filterPanelId}
                                title={filterToggleLabel}
                                onClick={() =>
                                    setAreFiltersOpen((open) => !open)
                                }
                                className={cn(
                                    'relative ml-1 flex size-8 shrink-0 items-center justify-center rounded-md text-code-faint transition hover:bg-code-hover hover:text-code-text focus-visible:outline-1 focus-visible:outline-code-accent',
                                    (areFiltersOpen || activeFilterCount > 0) &&
                                        'bg-code-hover text-code-accent',
                                )}
                            >
                                <ListFilter
                                    aria-hidden="true"
                                    className="size-3.5"
                                />
                                {activeFilterCount > 0 && (
                                    <span
                                        aria-hidden="true"
                                        className="absolute -top-0.5 -right-0.5 flex size-3.5 items-center justify-center rounded-full bg-code-accent font-mono text-[7px] font-semibold text-code-canvas"
                                    >
                                        {activeFilterCount}
                                    </span>
                                )}
                            </button>
                        }
                        controls={
                            <div id={filterPanelId} hidden={!areFiltersOpen}>
                                <MegaSearchFilters
                                    languageValue={languageValue}
                                    languageOptions={languageOptions}
                                    categoryValue={categoryValue}
                                    categoryOptions={categoryOptions}
                                    frameworkValue={frameworkValue}
                                    frameworkOptions={frameworkOptions}
                                    searchCode={searchCode}
                                    onLanguageChange={onLanguageChange}
                                    onCategoryChange={onCategoryChange}
                                    onFrameworkChange={onFrameworkChange}
                                    onSearchCodeChange={onSearchCodeChange}
                                />
                            </div>
                        }
                        renderPreview={(result) => (
                            <SnippetSearchPreview
                                result={result}
                                onOpen={onOpen}
                            />
                        )}
                    />
                </div>
            </DialogContent>
        </Dialog>
    );
}

type MegaSearchFiltersProps = Pick<
    WorkspaceMegaSearchProps,
    | 'languageValue'
    | 'languageOptions'
    | 'categoryValue'
    | 'categoryOptions'
    | 'frameworkValue'
    | 'frameworkOptions'
    | 'searchCode'
    | 'onLanguageChange'
    | 'onCategoryChange'
    | 'onFrameworkChange'
    | 'onSearchCodeChange'
>;

function MegaSearchFilters({
    languageValue,
    languageOptions,
    categoryValue,
    categoryOptions,
    frameworkValue,
    frameworkOptions,
    searchCode,
    onLanguageChange,
    onCategoryChange,
    onFrameworkChange,
    onSearchCodeChange,
}: MegaSearchFiltersProps) {
    return (
        <fieldset className="mx-auto mt-2 grid w-full max-w-sm grid-cols-2 gap-1.5 rounded-lg border border-code-border/70 bg-code-panel/75 p-1.5 shadow-[0_6px_18px_rgba(0,0,0,0.14)] backdrop-blur sm:flex sm:w-fit sm:max-w-full sm:flex-wrap sm:items-center sm:justify-end">
            <legend className="sr-only">Quick search filters</legend>
            <MegaSearchFilterSelect
                symbol="@"
                label="Language"
                value={languageValue ?? ''}
                disabled={languageOptions.length === 0}
                onChange={(value) => onLanguageChange(value || null)}
            >
                <option value="">Language</option>
                {languageOptions.map((language) => (
                    <option key={language.value} value={language.value}>
                        {language.label}
                    </option>
                ))}
            </MegaSearchFilterSelect>

            <MegaSearchFilterSelect
                symbol="$"
                label="Category"
                value={categoryValue === null ? '' : String(categoryValue)}
                disabled={categoryOptions.length === 0}
                onChange={(value) =>
                    onCategoryChange(value === '' ? null : Number(value))
                }
            >
                <option value="">Category</option>
                {categoryOptions.map((category) => (
                    <option key={category.id} value={category.id}>
                        {category.name}
                    </option>
                ))}
            </MegaSearchFilterSelect>

            <MegaSearchFilterSelect
                symbol="%"
                label="Framework"
                value={frameworkValue === null ? '' : String(frameworkValue)}
                disabled={frameworkOptions.length === 0}
                onChange={(value) =>
                    onFrameworkChange(value === '' ? null : Number(value))
                }
            >
                <option value="">Framework</option>
                {frameworkOptions.map((framework) => (
                    <option key={framework.id} value={framework.id}>
                        {framework.name}
                    </option>
                ))}
            </MegaSearchFilterSelect>

            <button
                type="button"
                role="switch"
                aria-checked={searchCode}
                aria-label="Search code content"
                title={`Search code ${searchCode ? 'on' : 'off'}`}
                onClick={() => onSearchCodeChange(!searchCode)}
                className={cn(
                    'flex h-8 items-center justify-center gap-1.5 rounded-md border px-2.5 text-[10px] font-medium transition focus-visible:outline-1 focus-visible:outline-code-accent sm:justify-start',
                    searchCode
                        ? 'border-code-accent/35 bg-code-accent/10 text-code-text'
                        : 'border-code-border bg-code-canvas/55 text-code-muted hover:bg-code-hover hover:text-code-text',
                )}
            >
                <Code2
                    aria-hidden="true"
                    className={cn(
                        'size-3.5',
                        searchCode ? 'text-code-accent' : 'text-code-faint',
                    )}
                />
                <span>Code</span>
                <span
                    aria-hidden="true"
                    className={cn(
                        'size-1.5 rounded-full transition',
                        searchCode ? 'bg-code-accent' : 'bg-code-faint',
                    )}
                />
                <span className="sr-only">{searchCode ? 'on' : 'off'}</span>
            </button>
        </fieldset>
    );
}

function MegaSearchFilterSelect({
    symbol,
    label,
    value,
    disabled = false,
    onChange,
    children,
}: {
    symbol: string;
    label: string;
    value: string;
    disabled?: boolean;
    onChange: (value: string) => void;
    children: ReactNode;
}) {
    return (
        <label className="flex h-8 min-w-0 items-center rounded-md border border-code-border bg-code-canvas/55 pl-2 transition focus-within:border-code-accent/50 focus-within:ring-1 focus-within:ring-code-accent/20 hover:bg-code-hover sm:w-32 sm:flex-none">
            <span
                aria-hidden="true"
                className="font-mono text-[10px] font-semibold text-code-accent"
            >
                {symbol}
            </span>
            <span className="sr-only">Filter by {label.toLowerCase()}</span>
            <span className="relative min-w-0 flex-1 self-stretch">
                <select
                    aria-label={`Filter by ${label.toLowerCase()}`}
                    value={value}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.value)}
                    className="h-full w-full appearance-none bg-transparent pr-6 pl-1.5 text-[10px] font-medium text-code-text outline-none disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {children}
                </select>
                <ChevronDown
                    aria-hidden="true"
                    className="pointer-events-none absolute top-1/2 right-1.5 size-3 -translate-y-1/2 text-code-faint"
                />
            </span>
        </label>
    );
}

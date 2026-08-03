import type { ParsedSnippetSection } from '@/lib/snippets/snippet-sections';
import type {
    Folder,
    Framework,
    LanguageOption,
    LibraryCategory,
    Project,
    Snippet,
    SnippetVariation,
    Tag,
} from '@/types/snippets';
import { parseSnippetSections } from './snippet-sections.ts';

export const snippetSearchFields = [
    'language',
    'category',
    'framework',
    'tag',
    'project',
    'folder',
    'type',
    'variation',
    'section',
    'title',
] as const;

export const workspaceSearchEntities = [
    'all',
    'projects',
    'snippets',
    'guides',
] as const;

export const snippetSearchScopes = [
    'all',
    'file',
    'code',
    'project',
    'folder',
    'framework',
    'tag',
    'language',
] as const;

export type SnippetSearchField = (typeof snippetSearchFields)[number];
export type WorkspaceSearchEntity = (typeof workspaceSearchEntities)[number];
export type SnippetSearchScope = (typeof snippetSearchScopes)[number];
export type SnippetExcerptMode = 'off' | 'hover' | 'always';
export const defaultSnippetExcerptMode: SnippetExcerptMode = 'always';
export type SnippetSearchOperator =
    'contains' | 'not_contains' | 'equals' | 'not_equals';

export type SnippetSearchToken = {
    raw: string;
    value: string;
    field: string | null;
    operator: SnippetSearchOperator;
    quoted: boolean;
    start: number;
    end: number;
};

export type ParsedSnippetSearchQuery = {
    source: string;
    tokens: SnippetSearchToken[];
};

export type SnippetSearchContext = {
    projects?: readonly Pick<
        Project,
        | 'id'
        | 'library_category_id'
        | 'name'
        | 'kind'
        | 'folders'
        | 'frameworks'
    >[];
    libraryCategories?: readonly Pick<LibraryCategory, 'id' | 'name'>[];
    scope?: SnippetSearchScope;
    includeCode?: boolean;
};

export type SnippetCodeExcerpt = {
    variationId: number;
    variationName: string;
    lineStart: number;
    lineEnd: number;
    text: string;
    matchStart: number;
    matchEnd: number;
};

export type SnippetSearchMatch = {
    snippet: Snippet;
    variation: SnippetVariation | null;
    excerpt: SnippetCodeExcerpt | null;
    score: number;
};

export function hasActiveSearchQuery(query: string): boolean {
    return query.trim().length > 0;
}

export function snippetMatchesWorkspaceSearchEntity(
    snippet: Snippet,
    entity: WorkspaceSearchEntity,
): boolean {
    if (entity === 'projects') {
        return false;
    }

    if (entity === 'snippets') {
        return snippet.content_type === 'snippet';
    }

    if (entity === 'guides') {
        return snippet.content_type === 'guide';
    }

    return true;
}

export type SnippetSearchSuggestionSources = {
    languages: readonly LanguageOption[];
    frameworks: readonly Pick<Framework, 'name' | 'slug'>[];
    tags: readonly (string | Pick<Tag, 'name' | 'slug'>)[];
    projects: readonly (string | Pick<Project, 'name'>)[];
    folders?: readonly (string | Pick<Folder, 'name'>)[];
    libraryCategories?: readonly (string | Pick<LibraryCategory, 'name'>)[];
    titles?: readonly string[];
    variations?: readonly string[];
    sections?: readonly string[];
    limit?: number;
};

export type SnippetSectionSearchResult = {
    snippet: Snippet;
    variation: SnippetVariation;
    section: ParsedSnippetSection;
    score: number;
};

type SearchSegment = {
    raw: string;
    start: number;
    end: number;
};

type SearchOperatorMatch = {
    index: number;
    source: '==' | '!=';
};

const fieldPattern = /^[a-z][a-z0-9_-]*$/i;
const shorthandSearchFields = {
    '@': 'language',
    $: 'category',
    '%': 'framework',
    '^': 'title',
} as const satisfies Record<string, SnippetSearchField>;

type ShorthandSearchSymbol = keyof typeof shorthandSearchFields;

export function parseSnippetSearchQuery(
    source: string,
): ParsedSnippetSearchQuery {
    return {
        source,
        tokens: scanSearchSegments(source).map(parseSearchToken),
    };
}

export function searchSnippets(
    snippets: readonly Snippet[],
    query: string,
    context: SnippetSearchContext = {},
): Snippet[] {
    return searchSnippetMatches(snippets, query, context).map(
        (match) => match.snippet,
    );
}

export function searchSnippetMatches(
    snippets: readonly Snippet[],
    query: string,
    context: SnippetSearchContext = {},
): SnippetSearchMatch[] {
    const tokens = parseSnippetSearchQuery(query).tokens.filter(
        (token) => token.value.length > 0,
    );
    const searchContext = createSearchContext(
        context.projects ?? [],
        context.scope,
        context.libraryCategories ?? [],
        context.includeCode,
    );

    return snippets
        .flatMap((snippet) => {
            if (
                !tokens.every((token) =>
                    matchesSnippetSearchToken(snippet, token, searchContext),
                )
            ) {
                return [];
            }

            const variation = findMatchingVariation(
                snippet,
                tokens,
                searchContext,
            );

            if (
                tokens.length > 0 &&
                snippet.variations.length > 0 &&
                !variation
            ) {
                return [];
            }

            return [
                {
                    snippet,
                    variation,
                    excerpt: variation
                        ? createCodeExcerpt(variation, tokens, searchContext)
                        : null,
                    score: scoreSnippetSearchMatch(
                        snippet,
                        variation,
                        tokens,
                        searchContext,
                    ),
                },
            ];
        })
        .sort((left, right) => right.score - left.score);
}

export function findMatchingSnippetVariation(
    snippet: Snippet,
    query: string,
    context: SnippetSearchContext = {},
): SnippetVariation | null {
    const tokens = parseSnippetSearchQuery(query).tokens.filter(
        (token) => token.value.length > 0,
    );
    const searchContext = createSearchContext(
        context.projects ?? [],
        context.scope,
        context.libraryCategories ?? [],
        context.includeCode,
    );

    if (
        !tokens.every((token) =>
            matchesSnippetSearchToken(snippet, token, searchContext),
        )
    ) {
        return null;
    }

    return findMatchingVariation(snippet, tokens, searchContext);
}

export function searchSnippetSections(
    snippets: readonly Snippet[],
    query: string,
    context: SnippetSearchContext = {},
): SnippetSectionSearchResult[] {
    const tokens = parseSnippetSearchQuery(query).tokens.filter(
        (token) => token.value.length > 0,
    );

    if (!tokens.some(isPositiveSectionSearchToken)) {
        return [];
    }

    if (
        context.scope !== undefined &&
        context.scope !== 'all' &&
        context.scope !== 'code' &&
        !tokens.some(
            (token) => token.field === 'section' && token.operator === 'equals',
        )
    ) {
        return [];
    }

    const searchContext = createSearchContext(
        context.projects ?? [],
        context.scope,
        context.libraryCategories ?? [],
        context.includeCode,
    );
    const metadataTokens = tokens.filter(isSnippetMetadataToken);
    const sectionTokens = tokens.filter(
        (token) => !isSnippetMetadataToken(token),
    );

    if (
        !searchContext.includeCode &&
        !tokens.some(
            (token) => token.field === 'section' && token.operator === 'equals',
        )
    ) {
        return [];
    }

    return snippets
        .flatMap((snippet) =>
            snippet.variations.flatMap((variation) => {
                const variationScopedSnippet = {
                    ...snippet,
                    variations: [variation],
                };

                if (
                    !metadataTokens.every((token) =>
                        matchesSnippetSearchToken(
                            variationScopedSnippet,
                            token,
                            searchContext,
                        ),
                    )
                ) {
                    return [];
                }

                return parseSnippetSections(variation.content)
                    .filter((section) =>
                        sectionTokens.every((token) =>
                            matchesSnippetSectionToken(section, token),
                        ),
                    )
                    .map((section) => ({
                        snippet,
                        variation,
                        section,
                        score: scoreSnippetSectionSearchResult(
                            variationScopedSnippet,
                            section,
                            metadataTokens,
                            sectionTokens,
                            searchContext,
                        ),
                    }));
            }),
        )
        .sort((left, right) => right.score - left.score);
}

export function searchProjects(
    projects: readonly Project[],
    query: string,
    context: Pick<SnippetSearchContext, 'scope' | 'libraryCategories'> = {},
): Project[] {
    const tokens = parseSnippetSearchQuery(query).tokens.filter(
        (token) => token.value.length > 0,
    );

    if (tokens.length === 0) {
        return [...projects];
    }

    const libraryCategoryNames = createLibraryCategoryNames(
        context.libraryCategories ?? [],
    );

    return projects.filter((project) =>
        tokens.every((token) =>
            matchesProjectSearchToken(
                project,
                token,
                context.scope ?? 'all',
                libraryCategoryNames,
            ),
        ),
    );
}

export type FolderSearchResult = {
    folder: Folder;
    project: Project;
    path: string[];
};

export function searchFolders(
    projects: readonly Project[],
    query: string,
    context: Pick<SnippetSearchContext, 'scope' | 'libraryCategories'> = {},
): FolderSearchResult[] {
    const tokens = parseSnippetSearchQuery(query).tokens.filter(
        (token) => token.value.length > 0,
    );

    if (tokens.length === 0) {
        return [];
    }

    const libraryCategoryNames = createLibraryCategoryNames(
        context.libraryCategories ?? [],
    );

    return projects.flatMap((project) =>
        project.folders
            .map((folder) => ({
                folder,
                project,
                path: getFolderSearchPath(project.folders, folder),
            }))
            .filter((result) =>
                tokens.every((token) =>
                    matchesFolderSearchToken(
                        result,
                        token,
                        context.scope ?? 'all',
                        libraryCategoryNames,
                    ),
                ),
            ),
    );
}

export function getSearchSuggestions(
    query: string,
    sources: SnippetSearchSuggestionSources,
    caretPosition: number = query.length,
): string[] {
    const activeSegment = getActiveSearchSegment(query, caretPosition);
    const shorthand = findShorthandSearch(activeSegment.raw);
    const operator = findSearchOperator(activeSegment.raw);
    const limit = Math.max(0, sources.limit ?? 8);

    if (limit === 0) {
        return [];
    }

    if (shorthand) {
        const rawValue = activeSegment.raw.slice(shorthand.valueStart);
        const decodedValue = decodeSearchValue(rawValue);
        const forceQuote = startsWithQuote(rawValue.trim());

        return getFieldSuggestionValues(
            shorthand.field,
            sources,
            decodedValue.value,
        )
            .map((value) => {
                const encodedValue = encodeSearchValue(value, forceQuote);

                return `${shorthand.isExcluded ? '!' : ''}${shorthand.symbol}${encodedValue}`;
            })
            .filter((suggestion) => suggestion !== activeSegment.raw)
            .slice(0, limit);
    }

    if (operator) {
        const field = activeSegment.raw
            .slice(0, operator.index)
            .trim()
            .toLocaleLowerCase();

        if (isSnippetSearchField(field)) {
            const rawValue = activeSegment.raw.slice(operator.index + 2);
            const partialValue = decodeSearchValue(rawValue).value;
            const forceQuote = startsWithQuote(rawValue.trim());

            return getFieldSuggestionValues(field, sources, partialValue)
                .map(
                    (value) =>
                        `${field}${operator.source}${encodeSearchValue(value, forceQuote)}`,
                )
                .filter((suggestion) => suggestion !== activeSegment.raw)
                .slice(0, limit);
        }

        return [];
    }

    const isExcluded = activeSegment.raw.startsWith('!');
    const rawValue = isExcluded
        ? activeSegment.raw.slice(1)
        : activeSegment.raw;
    const decodedValue = decodeSearchValue(rawValue);
    const partialValue = decodedValue.value;
    const fieldSuggestions: string[] = [];

    if (!isExcluded && !decodedValue.quoted) {
        for (const field of snippetSearchFields) {
            if (field.startsWith(normalizeSearchValue(partialValue))) {
                fieldSuggestions.push(`${field}==`);
            }
        }
    }

    const languageSuggestions = rankTaxonomySuggestions(
        sources.languages.map((language) => ({
            value: language.value,
            searchValues: [language.label, language.value, ...language.aliases],
        })),
        partialValue,
    ).map((languageValue) => {
        const value = encodeSearchValue(languageValue, decodedValue.quoted);

        return isExcluded ? `!${value}` : value;
    });

    return uniqueSuggestions([...languageSuggestions, ...fieldSuggestions])
        .filter((suggestion) => suggestion !== activeSegment.raw)
        .slice(0, limit);
}

export function applySearchSuggestion(
    query: string,
    suggestion: string,
    caretPosition: number = query.length,
): string {
    const activeSegment = getActiveSearchSegment(query, caretPosition);

    return `${query.slice(0, activeSegment.start)}${suggestion}${query.slice(activeSegment.end)}`;
}

export function getSearchSuggestionCaretPosition(
    query: string,
    suggestion: string,
    caretPosition: number = query.length,
): number {
    const activeSegment = getActiveSearchSegment(query, caretPosition);

    return activeSegment.start + suggestion.length;
}

export type InlineSearchSuggestion = {
    completion: string;
    suffix: string;
};

export function getInlineSearchSuggestion(
    query: string,
    suggestion: string | null | undefined,
    caretPosition: number = query.length,
): InlineSearchSuggestion | null {
    if (!suggestion || query.length === 0) {
        return null;
    }

    const completion = applySearchSuggestion(query, suggestion, caretPosition);
    const normalizedCompletion = normalizeSearchValue(completion);
    const normalizedQuery = normalizeSearchValue(query);

    if (normalizedCompletion === normalizedQuery) {
        return null;
    }

    if (normalizedCompletion.startsWith(normalizedQuery)) {
        return {
            completion,
            suffix: completion.slice(query.length),
        };
    }

    return {
        completion,
        suffix: ` → ${suggestion}`,
    };
}

function scanSearchSegments(source: string): SearchSegment[] {
    const segments: SearchSegment[] = [];
    let index = 0;

    while (index < source.length) {
        while (index < source.length && /\s/u.test(source[index])) {
            index += 1;
        }

        if (index >= source.length) {
            break;
        }

        const start = index;
        let quote: '"' | "'" | null = null;

        while (index < source.length) {
            const character = source[index];

            if (character === '\\' && index + 1 < source.length) {
                index += 2;

                continue;
            }

            if (quote) {
                if (character === quote) {
                    quote = null;
                }

                index += 1;

                continue;
            }

            if (character === '"' || character === "'") {
                quote = character;
                index += 1;

                continue;
            }

            if (/\s/u.test(character)) {
                break;
            }

            index += 1;
        }

        segments.push({
            raw: source.slice(start, index),
            start,
            end: index,
        });
    }

    return segments;
}

function parseSearchToken(segment: SearchSegment): SnippetSearchToken {
    const shorthand = findShorthandSearch(segment.raw);

    if (shorthand) {
        const decodedValue = decodeSearchValue(
            segment.raw.slice(shorthand.valueStart),
        );
        const usesContains = shorthand.field === 'title';

        return {
            ...segment,
            value: decodedValue.value,
            field: shorthand.field,
            operator: shorthand.isExcluded
                ? usesContains
                    ? 'not_contains'
                    : 'not_equals'
                : usesContains
                  ? 'contains'
                  : 'equals',
            quoted: decodedValue.quoted,
        };
    }

    const operator = findSearchOperator(segment.raw);

    if (operator) {
        const field = segment.raw.slice(0, operator.index).trim();

        if (fieldPattern.test(field)) {
            const decodedValue = decodeSearchValue(
                segment.raw.slice(operator.index + 2),
            );

            return {
                ...segment,
                value: decodedValue.value,
                field: field.toLocaleLowerCase(),
                operator: operator.source === '==' ? 'equals' : 'not_equals',
                quoted: decodedValue.quoted,
            };
        }
    }

    const isExcluded = segment.raw.startsWith('!');
    const decodedValue = decodeSearchValue(
        isExcluded ? segment.raw.slice(1) : segment.raw,
    );

    return {
        ...segment,
        value: decodedValue.value,
        field: null,
        operator: isExcluded ? 'not_contains' : 'contains',
        quoted: decodedValue.quoted,
    };
}

type ShorthandSearchMatch = {
    symbol: ShorthandSearchSymbol;
    field: SnippetSearchField;
    isExcluded: boolean;
    valueStart: number;
};

function findShorthandSearch(source: string): ShorthandSearchMatch | null {
    const isExcluded = source.startsWith('!');
    const symbolIndex = isExcluded ? 1 : 0;
    const symbol = source[symbolIndex] as ShorthandSearchSymbol | undefined;

    if (!symbol || !(symbol in shorthandSearchFields)) {
        return null;
    }

    return {
        symbol,
        field: shorthandSearchFields[symbol],
        isExcluded,
        valueStart: symbolIndex + 1,
    };
}

function findSearchOperator(source: string): SearchOperatorMatch | null {
    let quote: '"' | "'" | null = null;

    for (let index = 0; index < source.length - 1; index += 1) {
        const character = source[index];

        if (character === '\\') {
            index += 1;

            continue;
        }

        if (quote) {
            if (character === quote) {
                quote = null;
            }

            continue;
        }

        if (character === '"' || character === "'") {
            quote = character;

            continue;
        }

        const operator = source.slice(index, index + 2);

        if (operator === '==' || operator === '!=') {
            return {
                index,
                source: operator,
            };
        }
    }

    return null;
}

function decodeSearchValue(source: string): {
    value: string;
    quoted: boolean;
} {
    const value = source.trim();
    const quote = startsWithQuote(value) ? value[0] : null;

    if (!quote) {
        return {
            value: unescapeSearchValue(value),
            quoted: false,
        };
    }

    const hasClosingQuote =
        value.length > 1 &&
        value.endsWith(quote) &&
        !isEscapedCharacter(value, value.length - 1);
    const body = value.slice(1, hasClosingQuote ? -1 : undefined);

    return {
        value: unescapeSearchValue(body),
        quoted: true,
    };
}

function unescapeSearchValue(value: string): string {
    return value.replace(/\\(["'\\])/gu, '$1');
}

function encodeSearchValue(value: string, forceQuote = false): string {
    if (!forceQuote && !/[\s"']/u.test(value)) {
        return value;
    }

    return `"${value.replace(/\\/gu, '\\\\').replace(/"/gu, '\\"')}"`;
}

function startsWithQuote(value: string): value is `"${string}` | `'${string}` {
    return value.startsWith('"') || value.startsWith("'");
}

function isEscapedCharacter(source: string, index: number): boolean {
    let backslashCount = 0;

    for (
        let characterIndex = index - 1;
        characterIndex >= 0 && source[characterIndex] === '\\';
        characterIndex -= 1
    ) {
        backslashCount += 1;
    }

    return backslashCount % 2 === 1;
}

function getActiveSearchSegment(
    query: string,
    caretPosition: number = query.length,
): SearchSegment {
    const segments = scanSearchSegments(query);
    const boundedCaretPosition = Math.max(
        0,
        Math.min(query.length, caretPosition),
    );
    const activeSegment = segments.find(
        (segment) =>
            boundedCaretPosition >= segment.start &&
            boundedCaretPosition <= segment.end,
    );

    if (!activeSegment) {
        return {
            raw: '',
            start: boundedCaretPosition,
            end: boundedCaretPosition,
        };
    }

    return {
        ...activeSegment,
        raw: query.slice(activeSegment.start, boundedCaretPosition),
    };
}

function matchesSnippetSearchToken(
    snippet: Snippet,
    token: SnippetSearchToken,
    context: SearchContext,
): boolean {
    const expectedValue = normalizeSearchValue(token.value);

    if (!token.field) {
        const isContained = getContainsValues(snippet, context).some((value) =>
            normalizeSearchValue(value).includes(expectedValue),
        );

        return token.operator === 'not_contains' ? !isContained : isContained;
    }

    return matchesValues(
        getExactFieldValues(snippet, token.field, context),
        token,
    );
}

type SnippetSearchValueGroups = {
    title: string[];
    filename: string[];
    namedCode: string[];
    taxonomy: string[];
    location: string[];
    descriptive: string[];
    code: string[];
};

function getSnippetSearchValueGroups(
    snippet: Snippet,
    context: SearchContext,
): SnippetSearchValueGroups {
    const projectValues =
        snippet.project_id === null
            ? ['standalone']
            : [
                  context.projectNames.get(snippet.project_id) ??
                      String(snippet.project_id),
                  context.projectKinds.get(snippet.project_id) ?? '',
              ];
    const folderValues =
        snippet.folder_id === null
            ? []
            : (context.folderPaths.get(snippet.folder_id) ?? []);
    const frameworkValues = [
        ...snippet.frameworks.flatMap((framework) => [
            framework.name,
            framework.slug,
        ]),
        ...(snippet.project_id === null
            ? []
            : (context.projectFrameworks.get(snippet.project_id) ?? [])),
    ];
    const tagValues = snippet.tags.flatMap((tag) => [tag.name, tag.slug]);
    const categoryValues =
        snippet.project_id === null
            ? []
            : (context.projectLibraryCategories.get(snippet.project_id) ?? []);
    const codeValues = snippet.variations.flatMap((variation) => [
        variation.content,
    ]);

    return {
        title: [snippet.title],
        filename: [snippet.filename],
        namedCode: snippet.variations.flatMap((variation) => [
            variation.name,
            ...(variation.sections ?? []).flatMap((section) => [
                section.key,
                section.name,
                section.label,
            ]),
        ]),
        taxonomy: [
            snippet.content_type,
            snippet.language,
            ...tagValues,
            ...frameworkValues,
            ...categoryValues,
        ],
        location: [...projectValues, ...folderValues],
        descriptive: [
            snippet.description ?? '',
            ...snippet.presets.map((preset) => preset.name),
        ],
        code: context.includeCode ? codeValues : [],
    };
}

function getContainsValues(snippet: Snippet, context: SearchContext): string[] {
    const groups = getSnippetSearchValueGroups(snippet, context);

    switch (context.scope) {
        case 'file':
            return [...groups.title, ...groups.filename];
        case 'code':
            return groups.code;
        case 'project':
            return snippet.project_id === null
                ? ['standalone']
                : [
                      context.projectNames.get(snippet.project_id) ??
                          String(snippet.project_id),
                      context.projectKinds.get(snippet.project_id) ?? '',
                  ];
        case 'folder':
            return snippet.folder_id === null
                ? []
                : (context.folderPaths.get(snippet.folder_id) ?? []);
        case 'framework':
            return getExactFieldValues(snippet, 'framework', context);
        case 'tag':
            return snippet.tags.flatMap((tag) => [tag.name, tag.slug]);
        case 'language':
            return [snippet.language];
        case 'all':
            break;
    }

    return [
        ...groups.title,
        ...groups.filename,
        ...groups.namedCode,
        ...groups.taxonomy,
        ...groups.location,
        ...groups.descriptive,
        ...groups.code,
    ];
}

function getExactFieldValues(
    snippet: Snippet,
    field: string,
    context: SearchContext,
): string[] {
    switch (field) {
        case 'language':
            return [snippet.language];
        case 'category':
        case 'library_category':
        case 'library-category':
            return snippet.project_id === null
                ? []
                : (context.projectLibraryCategories.get(snippet.project_id) ??
                      []);
        case 'framework':
        case 'frameworks':
            return [
                ...snippet.frameworks.flatMap((framework) => [
                    framework.name,
                    framework.slug,
                ]),
                ...(snippet.project_id === null
                    ? []
                    : (context.projectFrameworks.get(snippet.project_id) ??
                      [])),
            ];
        case 'tag':
        case 'tags':
            return snippet.tags.flatMap((tag) => [tag.name, tag.slug]);
        case 'project':
            if (snippet.project_id === null) {
                return ['standalone'];
            }

            return [
                context.projectNames.get(snippet.project_id) ?? '',
                String(snippet.project_id),
            ];
        case 'folder':
            return snippet.folder_id === null
                ? []
                : (context.folderPaths.get(snippet.folder_id) ?? []);
        case 'type':
            return [snippet.content_type, 'file'];
        case 'title':
        case 'name':
            return [snippet.title];
        case 'filename':
        case 'file':
            return [snippet.filename];
        case 'description':
            return [snippet.description ?? ''];
        case 'content':
            return context.includeCode
                ? snippet.variations.map((variation) => variation.content)
                : [];
        case 'variation':
            return snippet.variations.map((variation) => variation.name);
        case 'section':
        case 'sections':
            return snippet.variations.flatMap((variation) =>
                (variation.sections ?? []).flatMap((section) => [
                    section.key,
                    section.name,
                    section.label,
                ]),
            );
        default:
            return [];
    }
}

function normalizeSearchValue(value: string): string {
    return value.normalize('NFKC').toLocaleLowerCase();
}

function isSnippetSearchField(field: string): field is SnippetSearchField {
    return snippetSearchFields.some((candidate) => candidate === field);
}

function getFieldSuggestionValues(
    field: SnippetSearchField,
    sources: SnippetSearchSuggestionSources,
    partialValue: string,
): string[] {
    switch (field) {
        case 'language':
            return rankTaxonomySuggestions(
                sources.languages.map((language) => ({
                    value: language.value,
                    searchValues: [
                        language.label,
                        language.value,
                        ...language.aliases,
                    ],
                })),
                partialValue,
            );
        case 'category':
            return rankSuggestions(
                (sources.libraryCategories ?? []).map((category) =>
                    typeof category === 'string' ? category : category.name,
                ),
                partialValue,
            );
        case 'framework':
            return rankTaxonomySuggestions(
                sources.frameworks.map((framework) => ({
                    value: framework.slug,
                    searchValues: [framework.name, framework.slug],
                })),
                partialValue,
            );
        case 'tag':
            return rankSuggestions(
                sources.tags.flatMap((tag) =>
                    typeof tag === 'string' ? [tag] : [tag.name, tag.slug],
                ),
                partialValue,
            );
        case 'project':
            return rankSuggestions(
                sources.projects.map((project) =>
                    typeof project === 'string' ? project : project.name,
                ),
                partialValue,
            );
        case 'folder':
            return rankSuggestions(
                (sources.folders ?? []).map((folder) =>
                    typeof folder === 'string' ? folder : folder.name,
                ),
                partialValue,
            );
        case 'type':
            return rankSuggestions(
                ['project', 'bundle', 'guide', 'folder', 'snippet'],
                partialValue,
            );
        case 'variation':
            return rankSuggestions(
                [...(sources.variations ?? [])],
                partialValue,
            );
        case 'section':
            return rankSuggestions([...(sources.sections ?? [])], partialValue);
        case 'title':
            return rankSuggestions([...(sources.titles ?? [])], partialValue);
    }
}

type ScoredSearchValues = {
    values: readonly string[];
    weight: number;
};

function scoreSnippetSearchMatch(
    snippet: Snippet,
    variation: SnippetVariation | null,
    tokens: readonly SnippetSearchToken[],
    context: SearchContext,
): number {
    const variationScopedSnippet = variation
        ? { ...snippet, variations: [variation] }
        : snippet;

    return tokens.reduce(
        (score, token) =>
            score +
            scoreSnippetSearchToken(variationScopedSnippet, token, context),
        0,
    );
}

function scoreSnippetSearchToken(
    snippet: Snippet,
    token: SnippetSearchToken,
    context: SearchContext,
): number {
    if (token.operator === 'not_contains' || token.operator === 'not_equals') {
        return 0;
    }

    if (token.field) {
        return scoreSearchValues(
            [
                {
                    values: getExactFieldValues(snippet, token.field, context),
                    weight: getFieldSearchWeight(token.field),
                },
            ],
            token,
        );
    }

    const groups = getSnippetSearchValueGroups(snippet, context);
    let scoredGroups: ScoredSearchValues[];

    switch (context.scope) {
        case 'file':
            scoredGroups = [
                { values: groups.title, weight: 1_000 },
                { values: groups.filename, weight: 900 },
            ];
            break;
        case 'code':
            scoredGroups = [{ values: groups.code, weight: 100 }];
            break;
        case 'project':
            scoredGroups = [
                {
                    values:
                        snippet.project_id === null
                            ? ['standalone']
                            : [
                                  context.projectNames.get(
                                      snippet.project_id,
                                  ) ?? String(snippet.project_id),
                                  context.projectKinds.get(
                                      snippet.project_id,
                                  ) ?? '',
                              ],
                    weight: 350,
                },
            ];
            break;
        case 'folder':
            scoredGroups = [
                {
                    values:
                        snippet.folder_id === null
                            ? []
                            : (context.folderPaths.get(snippet.folder_id) ??
                              []),
                    weight: 350,
                },
            ];
            break;
        case 'framework':
            scoredGroups = [
                {
                    values: getExactFieldValues(snippet, 'framework', context),
                    weight: 500,
                },
            ];
            break;
        case 'tag':
            scoredGroups = [
                {
                    values: snippet.tags.flatMap((tag) => [tag.name, tag.slug]),
                    weight: 500,
                },
            ];
            break;
        case 'language':
            scoredGroups = [{ values: [snippet.language], weight: 500 }];
            break;
        case 'all':
            scoredGroups = [
                { values: groups.title, weight: 1_000 },
                { values: groups.filename, weight: 900 },
                { values: groups.namedCode, weight: 650 },
                { values: groups.taxonomy, weight: 500 },
                { values: groups.location, weight: 350 },
                { values: groups.descriptive, weight: 250 },
                { values: groups.code, weight: 100 },
            ];
            break;
    }

    return scoreSearchValues(scoredGroups, token);
}

function scoreSnippetSectionSearchResult(
    snippet: Snippet,
    section: ParsedSnippetSection,
    metadataTokens: readonly SnippetSearchToken[],
    sectionTokens: readonly SnippetSearchToken[],
    context: SearchContext,
): number {
    const metadataScore = metadataTokens.reduce(
        (score, token) =>
            score + scoreSnippetSearchToken(snippet, token, context),
        0,
    );
    const sectionScore = sectionTokens.reduce((score, token) => {
        if (
            token.operator === 'not_contains' ||
            token.operator === 'not_equals'
        ) {
            return score;
        }

        return (
            score +
            scoreSearchValues(
                token.field === 'section'
                    ? [
                          {
                              values: [
                                  section.key,
                                  section.name,
                                  section.label,
                              ],
                              weight: 800,
                          },
                      ]
                    : [
                          {
                              values: [
                                  section.key,
                                  section.name,
                                  section.label,
                              ],
                              weight: 800,
                          },
                          { values: [section.content], weight: 100 },
                      ],
                token,
            )
        );
    }, 0);

    return metadataScore + sectionScore;
}

function getFieldSearchWeight(field: string): number {
    switch (field) {
        case 'title':
        case 'name':
            return 1_000;
        case 'filename':
        case 'file':
            return 900;
        case 'section':
        case 'sections':
            return 800;
        case 'variation':
            return 650;
        case 'language':
        case 'category':
        case 'library_category':
        case 'library-category':
        case 'framework':
        case 'frameworks':
        case 'tag':
        case 'tags':
        case 'type':
            return 500;
        case 'project':
        case 'folder':
            return 350;
        case 'description':
            return 250;
        case 'content':
            return 100;
        default:
            return 300;
    }
}

function scoreSearchValues(
    groups: readonly ScoredSearchValues[],
    token: SnippetSearchToken,
): number {
    const expectedValue = normalizeSearchValue(token.value);

    return groups.reduce((bestScore, group) => {
        const groupScore = group.values.reduce((bestValueScore, value) => {
            const normalizedValue = normalizeSearchValue(value);
            const isMatch =
                token.operator === 'equals'
                    ? normalizedValue === expectedValue
                    : normalizedValue.includes(expectedValue);

            if (!isMatch) {
                return bestValueScore;
            }

            const qualityBonus =
                normalizedValue === expectedValue
                    ? 100
                    : normalizedValue.startsWith(expectedValue)
                      ? 50
                      : 0;

            return Math.max(bestValueScore, group.weight + qualityBonus);
        }, 0);

        return Math.max(bestScore, groupScore);
    }, 0);
}

function findMatchingVariation(
    snippet: Snippet,
    tokens: readonly SnippetSearchToken[],
    context: SearchContext,
): SnippetVariation | null {
    const candidates = [...snippet.variations].sort(
        (left, right) => Number(right.is_default) - Number(left.is_default),
    );

    if (tokens.length === 0) {
        return candidates[0] ?? null;
    }

    return (
        candidates.find((variation) => {
            const variationScopedSnippet = {
                ...snippet,
                variations: [variation],
            };

            return tokens.every((token) =>
                matchesSnippetSearchToken(
                    variationScopedSnippet,
                    token,
                    context,
                ),
            );
        }) ?? null
    );
}

function createCodeExcerpt(
    variation: SnippetVariation,
    tokens: readonly SnippetSearchToken[],
    context: SearchContext,
): SnippetCodeExcerpt | null {
    const codeTokens = tokens.filter(
        (token) =>
            (token.operator === 'contains' || token.operator === 'equals') &&
            (token.field === 'content' ||
                (token.field === null &&
                    context.includeCode &&
                    (context.scope === 'all' || context.scope === 'code'))),
    );

    for (const token of codeTokens) {
        const matchOffset = normalizeSearchValue(variation.content).indexOf(
            normalizeSearchValue(token.value),
        );

        if (matchOffset === -1) {
            continue;
        }

        return excerptAroundMatch(variation, matchOffset, token.value.length);
    }

    return null;
}

function excerptAroundMatch(
    variation: SnippetVariation,
    matchOffset: number,
    matchLength: number,
): SnippetCodeExcerpt {
    const lineStarts = [0];

    for (const match of variation.content.matchAll(/\r\n|\n|\r/gu)) {
        lineStarts.push(match.index + match[0].length);
    }

    const matchLineIndex = Math.max(
        0,
        lineStarts.findLastIndex((lineStart) => lineStart <= matchOffset),
    );
    const desiredLineCount = 6;
    const initialLineStartIndex = Math.max(0, matchLineIndex - 2);
    const excerptLineEndIndex = Math.min(
        lineStarts.length - 1,
        initialLineStartIndex + desiredLineCount - 1,
    );
    const excerptLineStartIndex = Math.max(
        0,
        excerptLineEndIndex - desiredLineCount + 1,
    );
    const excerptStartOffset = lineStarts[excerptLineStartIndex];
    const excerptEndOffset =
        lineStarts[excerptLineEndIndex + 1] ?? variation.content.length;
    const rawText = variation.content
        .slice(excerptStartOffset, excerptEndOffset)
        .replace(/(?:\r\n|\n|\r)$/u, '');
    const rawLines = rawText.split(/\r\n|\n|\r/u);
    const relativeMatchLineIndex = matchLineIndex - excerptLineStartIndex;
    const matchColumn = matchOffset - lineStarts[matchLineIndex];
    const maximumLineLength = 360;
    let adjustedMatchStart = 0;
    let displayedMatchLineEnd = 0;
    let precedingTextLength = 0;
    const displayedLines = rawLines.map((line, lineIndex) => {
        let sliceStart = 0;

        if (
            line.length > maximumLineLength &&
            lineIndex === relativeMatchLineIndex
        ) {
            sliceStart = Math.max(
                0,
                Math.min(matchColumn - 100, line.length - maximumLineLength),
            );
        }

        const sliceEnd = Math.min(line.length, sliceStart + maximumLineLength);
        const prefix = sliceStart > 0 ? '…' : '';
        const suffix = sliceEnd < line.length ? '…' : '';
        const displayedLine = `${prefix}${line.slice(sliceStart, sliceEnd)}${suffix}`;

        if (lineIndex === relativeMatchLineIndex) {
            adjustedMatchStart =
                precedingTextLength + prefix.length + matchColumn - sliceStart;
            displayedMatchLineEnd =
                precedingTextLength + displayedLine.length - suffix.length;
        }

        precedingTextLength += displayedLine.length + 1;

        return displayedLine;
    });
    const text = displayedLines.join('\n');

    return {
        variationId: variation.id,
        variationName: variation.name,
        lineStart: excerptLineStartIndex + 1,
        lineEnd: excerptLineEndIndex + 1,
        text,
        matchStart: adjustedMatchStart,
        matchEnd: Math.min(
            displayedMatchLineEnd,
            adjustedMatchStart + matchLength,
        ),
    };
}

function isPositiveSectionSearchToken(token: SnippetSearchToken): boolean {
    return (
        (!token.field && token.operator === 'contains') ||
        (token.field === 'section' && token.operator === 'equals')
    );
}

function isSnippetMetadataToken(token: SnippetSearchToken): boolean {
    return token.field !== null && token.field !== 'section';
}

function matchesSnippetSectionToken(
    section: ParsedSnippetSection,
    token: SnippetSearchToken,
): boolean {
    const values =
        token.field === 'section'
            ? [section.key, section.name, section.label]
            : [section.key, section.name, section.label, section.content];

    return matchesValues(values, token);
}

type SearchContext = {
    scope: SnippetSearchScope;
    includeCode: boolean;
    projectNames: ReadonlyMap<number, string>;
    projectKinds: ReadonlyMap<number, Project['kind']>;
    projectFrameworks: ReadonlyMap<number, string[]>;
    projectLibraryCategories: ReadonlyMap<number, string[]>;
    folderPaths: ReadonlyMap<number, string[]>;
};

function createSearchContext(
    projects: readonly Pick<
        Project,
        | 'id'
        | 'library_category_id'
        | 'name'
        | 'kind'
        | 'folders'
        | 'frameworks'
    >[],
    scope: SnippetSearchScope = 'all',
    libraryCategories: readonly Pick<LibraryCategory, 'id' | 'name'>[] = [],
    includeCode = true,
): SearchContext {
    const libraryCategoryNames = createLibraryCategoryNames(libraryCategories);

    return {
        scope,
        includeCode,
        projectNames: new Map(
            projects.map((project) => [project.id, project.name]),
        ),
        projectKinds: new Map(
            projects.map((project) => [project.id, project.kind]),
        ),
        projectFrameworks: new Map(
            projects.map((project) => [
                project.id,
                project.frameworks.flatMap((framework) => [
                    framework.name,
                    framework.slug,
                ]),
            ]),
        ),
        projectLibraryCategories: new Map(
            projects.map((project) => [
                project.id,
                project.library_category_id === null
                    ? []
                    : [
                          libraryCategoryNames.get(
                              project.library_category_id,
                          ) ?? String(project.library_category_id),
                      ],
            ]),
        ),
        folderPaths: new Map(
            projects.flatMap((project) =>
                project.folders.map((folder) => [
                    folder.id,
                    getFolderSearchPath(project.folders, folder),
                ]),
            ),
        ),
    };
}

function createLibraryCategoryNames(
    libraryCategories: readonly Pick<LibraryCategory, 'id' | 'name'>[],
): ReadonlyMap<number, string> {
    return new Map(
        libraryCategories.map((category) => [category.id, category.name]),
    );
}

function matchesProjectSearchToken(
    project: Project,
    token: SnippetSearchToken,
    scope: SnippetSearchScope,
    libraryCategoryNames: ReadonlyMap<number, string>,
): boolean {
    const values = token.field
        ? getProjectExactValues(project, token.field, libraryCategoryNames)
        : getProjectContainsValues(project, scope, libraryCategoryNames);

    return matchesValues(values, token);
}

function getProjectExactValues(
    project: Project,
    field: string,
    libraryCategoryNames: ReadonlyMap<number, string>,
): string[] {
    switch (field) {
        case 'project':
        case 'name':
        case 'title':
            return [project.name];
        case 'type':
        case 'kind':
            return [project.kind];
        case 'description':
            return [project.description ?? ''];
        case 'category':
        case 'library_category':
        case 'library-category':
            return project.library_category_id === null
                ? []
                : [
                      libraryCategoryNames.get(project.library_category_id) ??
                          String(project.library_category_id),
                  ];
        case 'framework':
        case 'frameworks':
            return project.frameworks.flatMap((framework) => [
                framework.name,
                framework.slug,
            ]);
        default:
            return [];
    }
}

function getProjectContainsValues(
    project: Project,
    scope: SnippetSearchScope,
    libraryCategoryNames: ReadonlyMap<number, string>,
): string[] {
    const projectValues = [
        project.name,
        project.description ?? '',
        project.kind,
    ];
    const frameworkValues = project.frameworks.flatMap((framework) => [
        framework.name,
        framework.slug,
    ]);
    const categoryValues =
        project.library_category_id === null
            ? []
            : [
                  libraryCategoryNames.get(project.library_category_id) ??
                      String(project.library_category_id),
              ];

    if (scope === 'framework') {
        return frameworkValues;
    }

    if (scope === 'all' || scope === 'project') {
        return [...projectValues, ...categoryValues, ...frameworkValues];
    }

    return [];
}

function matchesFolderSearchToken(
    result: FolderSearchResult,
    token: SnippetSearchToken,
    scope: SnippetSearchScope,
    libraryCategoryNames: ReadonlyMap<number, string>,
): boolean {
    const values = token.field
        ? getFolderExactValues(result, token.field, libraryCategoryNames)
        : getFolderContainsValues(result, scope, libraryCategoryNames);

    return matchesValues(values, token);
}

function getFolderExactValues(
    result: FolderSearchResult,
    field: string,
    libraryCategoryNames: ReadonlyMap<number, string>,
): string[] {
    switch (field) {
        case 'folder':
        case 'name':
            return [result.folder.name, result.path.join(' / ')];
        case 'project':
            return [result.project.name];
        case 'type':
            return ['folder'];
        case 'category':
        case 'library_category':
        case 'library-category':
            return result.project.library_category_id === null
                ? []
                : [
                      libraryCategoryNames.get(
                          result.project.library_category_id,
                      ) ?? String(result.project.library_category_id),
                  ];
        case 'framework':
        case 'frameworks':
            return result.project.frameworks.flatMap((framework) => [
                framework.name,
                framework.slug,
            ]);
        default:
            return [];
    }
}

function getFolderContainsValues(
    result: FolderSearchResult,
    scope: SnippetSearchScope,
    libraryCategoryNames: ReadonlyMap<number, string>,
): string[] {
    if (scope === 'project') {
        return [result.project.name, result.project.kind];
    }

    if (scope === 'framework') {
        return result.project.frameworks.flatMap((framework) => [
            framework.name,
            framework.slug,
        ]);
    }

    if (scope === 'all' || scope === 'folder') {
        return [
            result.folder.name,
            result.project.name,
            result.project.kind,
            result.path.join(' / '),
            ...(result.project.library_category_id === null
                ? []
                : [
                      libraryCategoryNames.get(
                          result.project.library_category_id,
                      ) ?? String(result.project.library_category_id),
                  ]),
            ...result.project.frameworks.flatMap((framework) => [
                framework.name,
                framework.slug,
            ]),
        ];
    }

    return [];
}

function matchesValues(
    values: readonly string[],
    token: SnippetSearchToken,
): boolean {
    const expectedValue = normalizeSearchValue(token.value);
    const isMatch = values.some((value) => {
        const normalizedValue = normalizeSearchValue(value);

        return token.operator === 'equals' || token.operator === 'not_equals'
            ? normalizedValue === expectedValue
            : normalizedValue.includes(expectedValue);
    });

    return token.operator === 'not_contains' || token.operator === 'not_equals'
        ? !isMatch
        : isMatch;
}

function getFolderSearchPath(
    folders: readonly Folder[],
    folder: Folder,
): string[] {
    const foldersById = new Map(folders.map((item) => [item.id, item]));
    const path = [folder.name];
    const visited = new Set([folder.id]);
    let parentId = folder.parent_id;

    while (parentId !== null && !visited.has(parentId)) {
        const parent = foldersById.get(parentId);

        if (!parent) {
            break;
        }

        path.unshift(parent.name);
        visited.add(parent.id);
        parentId = parent.parent_id;
    }

    return path;
}

function rankSuggestions(
    values: readonly string[],
    partialValue: string,
): string[] {
    const normalizedPartial = normalizeSearchValue(partialValue);

    return uniqueSuggestions(values)
        .map((value) => ({
            value,
            normalizedValue: normalizeSearchValue(value),
        }))
        .filter(({ normalizedValue }) =>
            normalizedValue.includes(normalizedPartial),
        )
        .sort((first, second) => {
            const firstStartsWith =
                first.normalizedValue.startsWith(normalizedPartial);
            const secondStartsWith =
                second.normalizedValue.startsWith(normalizedPartial);

            if (firstStartsWith !== secondStartsWith) {
                return firstStartsWith ? -1 : 1;
            }

            if (first.value.length !== second.value.length) {
                return first.value.length - second.value.length;
            }

            return first.value.localeCompare(second.value);
        })
        .map(({ value }) => value);
}

function rankTaxonomySuggestions(
    suggestions: readonly {
        value: string;
        searchValues: readonly string[];
    }[],
    partialValue: string,
): string[] {
    const normalizedPartial = normalizeSearchValue(partialValue);

    return suggestions
        .map((suggestion) => {
            const normalizedValues = suggestion.searchValues.map((value) =>
                normalizeSearchValue(value),
            );
            const matchingValues = normalizedValues.filter((value) =>
                value.includes(normalizedPartial),
            );

            return {
                ...suggestion,
                matchingValues,
                exact: matchingValues.some(
                    (value) => value === normalizedPartial,
                ),
                startsWith: matchingValues.some((value) =>
                    value.startsWith(normalizedPartial),
                ),
            };
        })
        .filter(({ matchingValues }) => matchingValues.length > 0)
        .sort((left, right) => {
            if (left.exact !== right.exact) {
                return left.exact ? -1 : 1;
            }

            if (left.startsWith !== right.startsWith) {
                return left.startsWith ? -1 : 1;
            }

            if (left.value.length !== right.value.length) {
                return left.value.length - right.value.length;
            }

            return left.value.localeCompare(right.value);
        })
        .map(({ value }) => value)
        .filter(
            (value, index, values) =>
                values.findIndex(
                    (candidate) =>
                        normalizeSearchValue(candidate) ===
                        normalizeSearchValue(value),
                ) === index,
        );
}

function uniqueSuggestions(suggestions: readonly string[]): string[] {
    const seen = new Set<string>();

    return suggestions.filter((suggestion) => {
        const normalizedSuggestion = normalizeSearchValue(suggestion);

        if (seen.has(normalizedSuggestion)) {
            return false;
        }

        seen.add(normalizedSuggestion);

        return true;
    });
}

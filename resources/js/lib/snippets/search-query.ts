import { parseSnippetSections } from '@/lib/snippets/snippet-sections';
import type { ParsedSnippetSection } from '@/lib/snippets/snippet-sections';
import type {
    Folder,
    Framework,
    LanguageOption,
    Project,
    Snippet,
    SnippetVariation,
    Tag,
} from '@/types/snippets';

export const snippetSearchFields = [
    'language',
    'framework',
    'tag',
    'project',
    'folder',
    'type',
    'variation',
    'section',
] as const;

export type SnippetSearchField = (typeof snippetSearchFields)[number];
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
    projects?: readonly Pick<Project, 'id' | 'name' | 'kind' | 'folders'>[];
};

export type SnippetSearchSuggestionSources = {
    languages: readonly LanguageOption[];
    frameworks: readonly Pick<Framework, 'name' | 'slug'>[];
    tags: readonly (string | Pick<Tag, 'name' | 'slug'>)[];
    projects: readonly (string | Pick<Project, 'name'>)[];
    folders?: readonly (string | Pick<Folder, 'name'>)[];
    variations?: readonly string[];
    sections?: readonly string[];
    limit?: number;
};

export type SnippetSectionSearchResult = {
    snippet: Snippet;
    variation: SnippetVariation;
    section: ParsedSnippetSection;
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
    const tokens = parseSnippetSearchQuery(query).tokens.filter(
        (token) => token.value.length > 0,
    );

    if (tokens.length === 0) {
        return [...snippets];
    }

    const searchContext = createSearchContext(context.projects ?? []);

    return snippets.filter((snippet) =>
        tokens.every((token) =>
            matchesSnippetSearchToken(snippet, token, searchContext),
        ),
    );
}

export function findMatchingSnippetVariation(
    snippet: Snippet,
    query: string,
    context: SnippetSearchContext = {},
): SnippetVariation | null {
    const tokens = parseSnippetSearchQuery(query).tokens.filter(
        (token) => token.value.length > 0,
    );
    const searchContext = createSearchContext(context.projects ?? []);
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
                    searchContext,
                ),
            );
        }) ?? null
    );
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

    const searchContext = createSearchContext(context.projects ?? []);
    const metadataTokens = tokens.filter(isSnippetMetadataToken);
    const sectionTokens = tokens.filter(
        (token) => !isSnippetMetadataToken(token),
    );

    return snippets.flatMap((snippet) =>
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
                }));
        }),
    );
}

export function searchProjects(
    projects: readonly Project[],
    query: string,
): Project[] {
    const tokens = parseSnippetSearchQuery(query).tokens.filter(
        (token) => token.value.length > 0,
    );

    if (tokens.length === 0) {
        return [...projects];
    }

    return projects.filter((project) =>
        tokens.every((token) => matchesProjectSearchToken(project, token)),
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
): FolderSearchResult[] {
    const tokens = parseSnippetSearchQuery(query).tokens.filter(
        (token) => token.value.length > 0,
    );

    if (tokens.length === 0) {
        return [];
    }

    return projects.flatMap((project) =>
        project.folders
            .map((folder) => ({
                folder,
                project,
                path: getFolderSearchPath(project.folders, folder),
            }))
            .filter((result) =>
                tokens.every((token) =>
                    matchesFolderSearchToken(result, token),
                ),
            ),
    );
}

export function getSearchSuggestions(
    query: string,
    sources: SnippetSearchSuggestionSources,
): string[] {
    const activeSegment = getActiveSearchSegment(query);
    const operator = findSearchOperator(activeSegment.raw);
    const limit = Math.max(0, sources.limit ?? 8);

    if (limit === 0) {
        return [];
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
    const suggestions: string[] = [];

    if (!isExcluded && !decodedValue.quoted) {
        for (const field of snippetSearchFields) {
            if (field.startsWith(normalizeSearchValue(partialValue))) {
                suggestions.push(`${field}==`);
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

    suggestions.push(...languageSuggestions);

    return uniqueSuggestions(suggestions)
        .filter((suggestion) => suggestion !== activeSegment.raw)
        .slice(0, limit);
}

export function applySearchSuggestion(
    query: string,
    suggestion: string,
): string {
    const activeSegment = getActiveSearchSegment(query);

    return `${query.slice(0, activeSegment.start)}${suggestion}${query.slice(activeSegment.end)}`;
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

function getActiveSearchSegment(query: string): SearchSegment {
    const segments = scanSearchSegments(query);
    const lastSegment = segments.at(-1);

    if (!lastSegment || /\s/u.test(query.at(-1) ?? '')) {
        return {
            raw: '',
            start: query.length,
            end: query.length,
        };
    }

    return lastSegment;
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

    const isEqual = getExactFieldValues(snippet, token.field, context).some(
        (value) => normalizeSearchValue(value) === expectedValue,
    );

    return token.operator === 'not_equals' ? !isEqual : isEqual;
}

function getContainsValues(snippet: Snippet, context: SearchContext): string[] {
    return [
        snippet.title,
        snippet.filename,
        snippet.language,
        snippet.description ?? '',
        ...(snippet.project_id === null
            ? ['standalone']
            : [
                  context.projectNames.get(snippet.project_id) ??
                      String(snippet.project_id),
                  context.projectKinds.get(snippet.project_id) ?? '',
              ]),
        ...(snippet.folder_id === null
            ? []
            : (context.folderPaths.get(snippet.folder_id) ?? [])),
        ...snippet.tags.flatMap((tag) => [tag.name, tag.slug]),
        ...(snippet.frameworks ?? []).flatMap((framework) => [
            framework.name,
            framework.slug,
        ]),
        ...snippet.presets.map((preset) => preset.name),
        ...snippet.variations.flatMap((variation) => [
            variation.name,
            variation.content,
            ...(variation.sections ?? []).flatMap((section) => [
                section.key,
                section.name,
                section.label,
            ]),
        ]),
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
        case 'framework':
        case 'frameworks':
            return (snippet.frameworks ?? []).flatMap((framework) => [
                framework.name,
                framework.slug,
            ]);
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
            return ['snippet'];
        case 'title':
        case 'name':
            return [snippet.title];
        case 'filename':
        case 'file':
            return [snippet.filename];
        case 'description':
            return [snippet.description ?? ''];
        case 'content':
            return snippet.variations.map((variation) => variation.content);
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
                ['project', 'bundle', 'folder', 'snippet'],
                partialValue,
            );
        case 'variation':
            return rankSuggestions(
                [...(sources.variations ?? [])],
                partialValue,
            );
        case 'section':
            return rankSuggestions([...(sources.sections ?? [])], partialValue);
    }
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
    projectNames: ReadonlyMap<number, string>;
    projectKinds: ReadonlyMap<number, Project['kind']>;
    folderPaths: ReadonlyMap<number, string[]>;
};

function createSearchContext(
    projects: readonly Pick<Project, 'id' | 'name' | 'kind' | 'folders'>[],
): SearchContext {
    return {
        projectNames: new Map(
            projects.map((project) => [project.id, project.name]),
        ),
        projectKinds: new Map(
            projects.map((project) => [project.id, project.kind]),
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

function matchesProjectSearchToken(
    project: Project,
    token: SnippetSearchToken,
): boolean {
    const values = token.field
        ? getProjectExactValues(project, token.field)
        : [project.name, project.description ?? '', project.kind];

    return matchesValues(values, token);
}

function getProjectExactValues(project: Project, field: string): string[] {
    switch (field) {
        case 'project':
        case 'name':
        case 'title':
            return [project.name];
        case 'type':
        case 'kind':
            return [
                project.kind,
                project.kind === 'project' ? 'project' : 'bundle',
            ];
        case 'description':
            return [project.description ?? ''];
        default:
            return [];
    }
}

function matchesFolderSearchToken(
    result: FolderSearchResult,
    token: SnippetSearchToken,
): boolean {
    const values = token.field
        ? getFolderExactValues(result, token.field)
        : [
              result.folder.name,
              result.project.name,
              result.project.kind,
              result.path.join(' / '),
          ];

    return matchesValues(values, token);
}

function getFolderExactValues(
    result: FolderSearchResult,
    field: string,
): string[] {
    switch (field) {
        case 'folder':
        case 'name':
            return [result.folder.name, result.path.join(' / ')];
        case 'project':
            return [result.project.name];
        case 'type':
            return ['folder'];
        default:
            return [];
    }
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
                startsWith: matchingValues.some((value) =>
                    value.startsWith(normalizedPartial),
                ),
            };
        })
        .filter(({ matchingValues }) => matchingValues.length > 0)
        .sort((left, right) => {
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

import type { Framework, Project, Snippet } from '@/types/snippets';

export type MegaSearchFilters = {
    language: string | null;
    libraryCategoryId: number | null;
    frameworkId: number | null;
};

export function hasActiveMegaSearchFilters(
    filters: MegaSearchFilters,
): boolean {
    return (
        filters.language !== null ||
        filters.libraryCategoryId !== null ||
        filters.frameworkId !== null
    );
}

export function getActiveMegaSearchFilterCount(
    filters: MegaSearchFilters,
    searchCode: boolean,
): number {
    return (
        Number(filters.language !== null) +
        Number(filters.libraryCategoryId !== null) +
        Number(filters.frameworkId !== null) +
        Number(!searchCode)
    );
}

type FilterableSnippet = Pick<Snippet, 'project_id' | 'language'> & {
    frameworks: readonly Pick<Framework, 'id'>[];
};

type FilterableProject = Pick<Project, 'library_category_id'> & {
    frameworks: readonly Pick<Framework, 'id'>[];
};

export function matchesMegaSearchFilters(
    snippet: FilterableSnippet,
    project: FilterableProject | null,
    filters: MegaSearchFilters,
): boolean {
    if (
        filters.language !== null &&
        normalizeFilterValue(snippet.language) !==
            normalizeFilterValue(filters.language)
    ) {
        return false;
    }

    if (
        filters.libraryCategoryId !== null &&
        project?.library_category_id !== filters.libraryCategoryId
    ) {
        return false;
    }

    if (
        filters.frameworkId !== null &&
        !hasFramework(snippet.frameworks, filters.frameworkId) &&
        !hasFramework(project?.frameworks ?? [], filters.frameworkId)
    ) {
        return false;
    }

    return true;
}

function hasFramework(
    frameworks: readonly Pick<Framework, 'id'>[],
    frameworkId: number,
): boolean {
    return frameworks.some((framework) => framework.id === frameworkId);
}

function normalizeFilterValue(value: string): string {
    return value.normalize('NFKC').toLocaleLowerCase();
}

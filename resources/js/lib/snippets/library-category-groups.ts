import type { LibraryCategory, Project } from '@/types';

export type LibraryCategoryProjectGroup = {
    key: string;
    label: string;
    category: LibraryCategory | null;
    projects: Project[];
};

export function groupProjectsByLibraryCategory(
    categories: readonly LibraryCategory[],
    projects: readonly Project[],
    includeEmptyCategories = true,
): LibraryCategoryProjectGroup[] {
    const categoryIds = new Set(categories.map((category) => category.id));
    const groups: LibraryCategoryProjectGroup[] = categories
        .map((category) => ({
            key: libraryCategoryGroupKey(category),
            label: category.name,
            category,
            projects: projects.filter(
                (project) => project.library_category_id === category.id,
            ),
        }))
        .filter((group) => includeEmptyCategories || group.projects.length > 0);
    const uncategorisedProjects = projects.filter(
        (project) =>
            project.library_category_id === null ||
            !categoryIds.has(project.library_category_id),
    );

    if (uncategorisedProjects.length > 0) {
        groups.push({
            key: libraryCategoryGroupKey(null),
            label: 'Uncategorised',
            category: null,
            projects: uncategorisedProjects,
        });
    }

    return groups;
}

export function libraryCategoryGroupKey(
    category: LibraryCategory | null,
): string {
    return category ? `category:${category.id}` : 'category:uncategorised';
}

export function mergeLibraryCategoryProjectOrder(
    allProjectIds: readonly number[],
    categoryProjectIds: readonly number[],
    reorderedCategoryProjectIds: readonly number[],
): number[] {
    const categoryIds = new Set(categoryProjectIds);
    const reorderedIds = [...reorderedCategoryProjectIds];
    let nextCategoryIndex = 0;

    return allProjectIds.map((projectId) => {
        if (!categoryIds.has(projectId)) {
            return projectId;
        }

        const reorderedId = reorderedIds[nextCategoryIndex];
        nextCategoryIndex += 1;

        return reorderedId ?? projectId;
    });
}

import assert from 'node:assert/strict';
import test from 'node:test';
import type { LibraryCategory, Project } from '../../types/snippets.ts';
import {
    groupProjectsByLibraryCategory,
    libraryCategoryGroupKey,
    mergeLibraryCategoryProjectOrder,
} from './library-category-groups.ts';

const categories: LibraryCategory[] = [
    { id: 1, name: 'Programming', position: 1 },
    { id: 2, name: 'Books', position: 2 },
];

const project = (id: number, libraryCategoryId: number | null): Project => ({
    id,
    library_category_id: libraryCategoryId,
    name: `Workspace ${id}`,
    kind: 'project',
    description: null,
    is_pinned: false,
    frameworks: [],
    folders: [],
    snippets: [],
});

test('projects are grouped beneath categories with uncategorised last', () => {
    const groups = groupProjectsByLibraryCategory(categories, [
        project(1, 2),
        project(2, null),
        project(3, 1),
    ]);

    assert.deepEqual(
        groups.map((group) => ({
            key: group.key,
            label: group.label,
            projectIds: group.projects.map((item) => item.id),
        })),
        [
            {
                key: 'category:1',
                label: 'Programming',
                projectIds: [3],
            },
            { key: 'category:2', label: 'Books', projectIds: [1] },
            {
                key: 'category:uncategorised',
                label: 'Uncategorised',
                projectIds: [2],
            },
        ],
    );
});

test('empty categories remain manageable until a filter is active', () => {
    assert.equal(
        groupProjectsByLibraryCategory(categories, [project(1, 1)]).length,
        2,
    );
    assert.deepEqual(
        groupProjectsByLibraryCategory(categories, [project(1, 1)], false).map(
            (group) => group.label,
        ),
        ['Programming'],
    );
});

test('projects with stale category ids fall back to uncategorised', () => {
    const groups = groupProjectsByLibraryCategory(categories, [
        project(1, 999),
    ]);

    assert.equal(groups.at(-1)?.key, libraryCategoryGroupKey(null));
    assert.deepEqual(
        groups.at(-1)?.projects.map((item) => item.id),
        [1],
    );
});

test('category reordering is merged into the complete workspace order', () => {
    assert.deepEqual(
        mergeLibraryCategoryProjectOrder([1, 2, 3, 4, 5], [1, 3, 5], [5, 1, 3]),
        [5, 2, 1, 4, 3],
    );
});

import assert from 'node:assert/strict';
import test from 'node:test';
import {
    getActiveMegaSearchFilterCount,
    hasActiveMegaSearchFilters,
    matchesMegaSearchFilters,
} from './mega-search-filters.ts';

const laravel = { id: 10 };
const react = { id: 11 };
const project = {
    library_category_id: 4,
    frameworks: [laravel],
};
const snippet = {
    project_id: 2,
    language: 'PHP',
    frameworks: [react],
};

test('matches language, category, and direct or inherited frameworks', () => {
    assert.equal(
        matchesMegaSearchFilters(snippet, project, {
            language: 'php',
            libraryCategoryId: 4,
            frameworkId: 10,
        }),
        true,
    );
    assert.equal(
        matchesMegaSearchFilters(snippet, project, {
            language: 'php',
            libraryCategoryId: 4,
            frameworkId: 11,
        }),
        true,
    );
});

test('rejects mismatched filters and standalone category matches', () => {
    assert.equal(
        matchesMegaSearchFilters(snippet, project, {
            language: 'javascript',
            libraryCategoryId: null,
            frameworkId: null,
        }),
        false,
    );
    assert.equal(
        matchesMegaSearchFilters(snippet, project, {
            language: null,
            libraryCategoryId: 8,
            frameworkId: null,
        }),
        false,
    );
    assert.equal(
        matchesMegaSearchFilters({ ...snippet, project_id: null }, null, {
            language: null,
            libraryCategoryId: 4,
            frameworkId: null,
        }),
        false,
    );
});

test('allows every snippet when all taxonomy filters are clear', () => {
    assert.equal(
        matchesMegaSearchFilters(snippet, project, {
            language: null,
            libraryCategoryId: null,
            frameworkId: null,
        }),
        true,
    );
});

test('only treats taxonomy selections as active filters', () => {
    assert.equal(
        hasActiveMegaSearchFilters({
            language: null,
            libraryCategoryId: null,
            frameworkId: null,
        }),
        false,
    );
    assert.equal(
        hasActiveMegaSearchFilters({
            language: 'php',
            libraryCategoryId: null,
            frameworkId: null,
        }),
        true,
    );
});

test('counts selected taxonomies and disabled code search', () => {
    assert.equal(
        getActiveMegaSearchFilterCount(
            {
                language: null,
                libraryCategoryId: null,
                frameworkId: null,
            },
            true,
        ),
        0,
    );
    assert.equal(
        getActiveMegaSearchFilterCount(
            {
                language: 'php',
                libraryCategoryId: 4,
                frameworkId: 10,
            },
            false,
        ),
        4,
    );
});

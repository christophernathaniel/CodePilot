import assert from 'node:assert/strict';
import test from 'node:test';
import type {
    Framework,
    LibraryCategory,
    Project,
    Snippet,
    SnippetVariation,
} from '../../types/snippets.ts';
import {
    applySearchSuggestion,
    defaultSnippetExcerptMode,
    getSearchSuggestions,
    getSearchSuggestionCaretPosition,
    getInlineSearchSuggestion,
    hasActiveSearchQuery,
    parseSnippetSearchQuery,
    searchProjects,
    searchSnippetMatches,
    searchSnippetSections,
    snippetMatchesWorkspaceSearchEntity,
} from './search-query.ts';

test('shows matching code excerpts by default', () => {
    assert.equal(defaultSnippetExcerptMode, 'always');
});

test('turns a query completion into an inline suffix', () => {
    assert.deepEqual(getInlineSearchSuggestion('javascr', 'javascript'), {
        completion: 'javascript',
        suffix: 'ipt',
    });
    assert.deepEqual(
        getInlineSearchSuggestion(
            'foreach language==javascr',
            'language==javascript',
        ),
        {
            completion: 'foreach language==javascript',
            suffix: 'ipt',
        },
    );
    assert.deepEqual(getInlineSearchSuggestion('!javascr', '!javascript'), {
        completion: '!javascript',
        suffix: 'ipt',
    });
});

test('shows ranked aliases and contained matches as inline replacements', () => {
    assert.deepEqual(getInlineSearchSuggestion('js', 'javascript'), {
        completion: 'javascript',
        suffix: ' → javascript',
    });
    assert.deepEqual(
        getInlineSearchSuggestion('language==js', 'language==javascript'),
        {
            completion: 'language==javascript',
            suffix: ' → language==javascript',
        },
    );
    assert.deepEqual(getInlineSearchSuggestion('script', 'javascript'), {
        completion: 'javascript',
        suffix: ' → javascript',
    });

    for (const [alias, canonical] of [
        ['twiglang', 'twig'],
        ['node', 'javascript'],
        ['c++', 'cpp'],
        ['c#', 'csharp'],
        ['golang', 'go'],
        ['shell', 'bash'],
        ['zsh', 'bash'],
        ['ps1', 'powershell'],
        ['htaccess', 'apache'],
        ['patch', 'diff'],
    ]) {
        assert.equal(
            getInlineSearchSuggestion(alias, canonical)?.completion,
            canonical,
        );
    }
});

test('prioritises direct language matches over advanced field completions', () => {
    const sources = {
        languages: [
            {
                value: 'php',
                label: 'PHP',
                aliases: [],
                syntax: 'php',
                extensions: ['php'],
                is_pinned: false,
            },
            {
                value: 'javascript',
                label: 'JavaScript',
                aliases: ['js'],
                syntax: 'javascript',
                extensions: ['js'],
                is_pinned: false,
            },
            {
                value: 'jsx',
                label: 'JSX',
                aliases: [],
                syntax: 'jsx',
                extensions: ['jsx'],
                is_pinned: false,
            },
            {
                value: 'twig',
                label: 'TWIGLang',
                aliases: ['twiglang'],
                syntax: 'twig',
                extensions: ['twig'],
                is_pinned: false,
            },
            {
                value: 'cpp',
                label: 'C++',
                aliases: ['c++'],
                syntax: 'cpp',
                extensions: ['cpp'],
                is_pinned: false,
            },
            {
                value: 'go',
                label: 'Go',
                aliases: ['golang'],
                syntax: 'go',
                extensions: ['go'],
                is_pinned: false,
            },
        ],
        frameworks: [],
        tags: [],
        projects: [],
    };

    assert.equal(getSearchSuggestions('p', sources)[0], 'php');
    assert.equal(getSearchSuggestions('js', sources)[0], 'javascript');
    assert.equal(
        getSearchSuggestions('language==js', sources)[0],
        'language==javascript',
    );

    for (const [alias, canonical] of [
        ['twiglang', 'twig'],
        ['c++', 'cpp'],
        ['golang', 'go'],
    ]) {
        const suggestion = getSearchSuggestions(alias, sources)[0];

        assert.equal(suggestion, canonical);
        assert.equal(
            getInlineSearchSuggestion(alias, suggestion)?.completion,
            canonical,
        );
    }
});

test('does not show an empty or redundant inline completion', () => {
    assert.equal(getInlineSearchSuggestion('', 'javascript'), null);
    assert.equal(getInlineSearchSuggestion('javascript', 'javascript'), null);
});

test('parses shorthand taxonomy and title-only searches', () => {
    const tokens = parseSnippetSearchQuery(
        '@php $"Code Recipes" %wordpress ^foreach',
    ).tokens;

    assert.deepEqual(
        tokens.map(({ field, operator, value, quoted }) => ({
            field,
            operator,
            value,
            quoted,
        })),
        [
            {
                field: 'language',
                operator: 'equals',
                value: 'php',
                quoted: false,
            },
            {
                field: 'category',
                operator: 'equals',
                value: 'Code Recipes',
                quoted: true,
            },
            {
                field: 'framework',
                operator: 'equals',
                value: 'wordpress',
                quoted: false,
            },
            {
                field: 'title',
                operator: 'contains',
                value: 'foreach',
                quoted: false,
            },
        ],
    );

    assert.deepEqual(
        parseSnippetSearchQuery(
            'language==php framework==wordpress',
        ).tokens.map(({ field, operator, value }) => ({
            field,
            operator,
            value,
        })),
        [
            { field: 'language', operator: 'equals', value: 'php' },
            { field: 'framework', operator: 'equals', value: 'wordpress' },
        ],
    );
});

test('tab-completes shorthand values and quotes values containing spaces', () => {
    const sources = {
        languages: [
            {
                value: 'javascript',
                label: 'JavaScript',
                aliases: ['js'],
                syntax: 'javascript',
                extensions: ['js'],
                is_pinned: false,
            },
        ],
        frameworks: [
            {
                name: 'WordPress VIP',
                slug: 'wordpress-vip',
            },
        ],
        libraryCategories: ['Code Recipes', 'WordPress Recipes'],
        titles: ['PHP Foreach Loop'],
        tags: [],
        projects: [],
    };

    assert.equal(getSearchSuggestions('@js', sources)[0], '@javascript');
    assert.equal(
        getSearchSuggestions('$word', sources)[0],
        '$"WordPress Recipes"',
    );
    assert.equal(getSearchSuggestions('%vip', sources)[0], '%wordpress-vip');
    assert.equal(
        getSearchSuggestions('^"php f', sources)[0],
        '^"PHP Foreach Loop"',
    );
    assert.equal(
        getInlineSearchSuggestion(
            '@js',
            getSearchSuggestions('@js', sources)[0],
        )?.completion,
        '@javascript',
    );
});

test('completes the shorthand token containing the caret', () => {
    const query = '@ja foreach %wordpress';
    const caretPosition = 3;
    const sources = {
        languages: [
            {
                value: 'javascript',
                label: 'JavaScript',
                aliases: ['js'],
                syntax: 'javascript',
                extensions: ['js'],
                is_pinned: false,
            },
        ],
        frameworks: [],
        tags: [],
        projects: [],
    };
    const suggestion = getSearchSuggestions(query, sources, caretPosition)[0];

    assert.equal(suggestion, '@javascript');
    assert.equal(
        applySearchSuggestion(query, suggestion, caretPosition),
        '@javascript foreach %wordpress',
    );
    assert.equal(
        getSearchSuggestionCaretPosition(query, suggestion, caretPosition),
        '@javascript'.length,
    );
});

const laravelFramework: Framework = {
    id: 10,
    name: 'Laravel',
    slug: 'laravel',
    color: null,
    is_pinned: false,
};

const reactFramework: Framework = {
    id: 11,
    name: 'React',
    slug: 'react',
    color: null,
    is_pinned: false,
};

const codeRecipesCategory: LibraryCategory = {
    id: 20,
    name: 'Code Recipes',
    position: 0,
};

test('filters snippets with shorthand metadata and partial titles', () => {
    const project = createProject({
        library_category_id: codeRecipesCategory.id,
        frameworks: [laravelFramework],
    });
    const matchingSnippet = createSnippet({
        title: 'PHP Foreach Loop',
        project_id: project.id,
    });
    const otherSnippet = createSnippet({
        id: 2,
        title: 'JavaScript Map',
        project_id: project.id,
        language: 'javascript',
    });
    const context = {
        projects: [project],
        libraryCategories: [codeRecipesCategory],
    };

    assert.deepEqual(
        searchSnippetMatches(
            [otherSnippet, matchingSnippet],
            '@php $"Code Recipes" %laravel ^foreach',
            context,
        ).map((match) => match.snippet.id),
        [matchingSnippet.id],
    );
    assert.deepEqual(
        searchProjects([project], '$"Code Recipes"', context).map(
            (match) => match.id,
        ),
        [project.id],
    );
});

test('search controls stay hidden until the query contains text', () => {
    assert.equal(hasActiveSearchQuery(''), false);
    assert.equal(hasActiveSearchQuery('   '), false);
    assert.equal(hasActiveSearchQuery('laravel'), true);
});

test('file and code scopes search only their selected values', () => {
    const snippet = createSnippet({
        filename: 'loops.php',
        variations: [
            createVariation({ content: 'foreach ($items as $item) {}' }),
        ],
    });

    assert.equal(
        searchSnippetMatches([snippet], 'foreach', { scope: 'file' }).length,
        0,
    );
    assert.equal(
        searchSnippetMatches([snippet], 'foreach', { scope: 'code' }).length,
        1,
    );
    assert.equal(
        searchSnippetMatches([snippet], 'loops.php', { scope: 'code' }).length,
        0,
    );
});

test('can exclude variation code from bare searches without hiding metadata', () => {
    const codeOnlySnippet = createSnippet({
        variations: [
            createVariation({ content: 'foreach ($items as $item) {}' }),
        ],
    });
    const titleSnippet = createSnippet({
        id: 2,
        title: 'PHP Foreach Loop',
        variations: [createVariation({ id: 2, content: 'echo "ready";' })],
    });

    assert.equal(searchSnippetMatches([codeOnlySnippet], 'foreach').length, 1);
    assert.equal(
        searchSnippetMatches([codeOnlySnippet], 'foreach', {
            includeCode: false,
        }).length,
        0,
    );
    assert.equal(
        searchSnippetMatches([titleSnippet], 'foreach', {
            includeCode: false,
        })[0]?.snippet.id,
        titleSnippet.id,
    );
    assert.equal(
        searchSnippetSections([codeOnlySnippet], 'foreach', {
            includeCode: false,
        }).length,
        0,
    );
    assert.equal(
        searchSnippetMatches(
            [codeOnlySnippet],
            'content=="foreach ($items as $item) {}"',
            { includeCode: false },
        ).length,
        0,
    );
});

test('ranks title and filename matches above incidental code matches', () => {
    const codeMatch = createSnippet({
        id: 1,
        title: 'Register metadata',
        filename: 'register.php',
        variations: [
            createVariation({ content: 'foreach ($files as $file) {}' }),
        ],
    });
    const filenameMatch = createSnippet({
        id: 2,
        title: 'Loop recipe',
        filename: 'php-foreach.php',
    });
    const titleMatch = createSnippet({
        id: 3,
        title: 'PHP Foreach Loop',
        filename: 'loop.php',
    });
    const matches = searchSnippetMatches(
        [codeMatch, filenameMatch, titleMatch],
        'foreach',
    );

    assert.deepEqual(
        matches.map((match) => match.snippet.id),
        [titleMatch.id, filenameMatch.id, codeMatch.id],
    );
    assert.equal(matches[0].score > matches[1].score, true);
    assert.equal(matches[1].score > matches[2].score, true);
});

test('code terms must match within the same variation', () => {
    const splitAcrossVariations = createSnippet({
        variations: [
            createVariation({ id: 1, content: 'alpha' }),
            createVariation({ id: 2, content: 'beta', is_default: false }),
        ],
    });
    const togetherInVariation = createSnippet({
        id: 2,
        variations: [
            createVariation({ id: 3, content: 'nothing here' }),
            createVariation({
                id: 4,
                content: 'alpha and beta',
                is_default: false,
            }),
        ],
    });

    assert.equal(
        searchSnippetMatches([splitAcrossVariations], 'alpha beta', {
            scope: 'code',
        }).length,
        0,
    );
    assert.equal(
        searchSnippetMatches([togetherInVariation], 'alpha beta', {
            scope: 'code',
        })[0]?.variation?.id,
        4,
    );
});

test('code matches expose the matching variation, lines, and highlight range', () => {
    const snippet = createSnippet({
        variations: [
            createVariation({ content: '// default variation' }),
            createVariation({
                id: 2,
                name: 'PHP foreach',
                content: [
                    '<?php',
                    'foreach ($items as $item) {',
                    '    echo $item;',
                    '}',
                ].join('\n'),
                is_default: false,
            }),
        ],
    });
    const match = searchSnippetMatches([snippet], 'foreach', {
        scope: 'code',
    })[0];

    assert.equal(match?.variation?.id, 2);
    assert.equal(match?.excerpt?.variationName, 'PHP foreach');
    assert.equal(match?.excerpt?.lineStart, 1);
    assert.equal(match?.excerpt?.lineEnd, 4);
    assert.equal(
        match?.excerpt?.text.slice(
            match.excerpt.matchStart,
            match.excerpt.matchEnd,
        ),
        'foreach',
    );
});

test('code excerpts show up to six useful lines around a match', () => {
    const snippet = createSnippet({
        variations: [
            createVariation({
                content: [
                    'line 1',
                    'line 2',
                    'line 3',
                    'line 4',
                    'foreach ($items as $item) {',
                    '    echo $item;',
                    '}',
                    'line 8',
                    'line 9',
                ].join('\n'),
            }),
        ],
    });
    const excerpt = searchSnippetMatches([snippet], 'foreach', {
        scope: 'code',
    })[0]?.excerpt;

    assert.equal(excerpt?.lineStart, 3);
    assert.equal(excerpt?.lineEnd, 8);
    assert.equal(excerpt?.text.split('\n').length, 6);

    const nearEndExcerpt = searchSnippetMatches(
        [
            createSnippet({
                variations: [
                    createVariation({
                        content: [
                            'line 1',
                            'line 2',
                            'line 3',
                            'line 4',
                            'line 5',
                            'line 6',
                            'line 7',
                            'line 8',
                            'foreach ($items as $item) {}',
                            'line 10',
                        ].join('\n'),
                    }),
                ],
            }),
        ],
        'foreach',
        { scope: 'code' },
    )[0]?.excerpt;

    assert.equal(nearEndExcerpt?.lineStart, 5);
    assert.equal(nearEndExcerpt?.lineEnd, 10);
});

test('preserves six lines when surrounding code lines are long', () => {
    const longLine = 'x'.repeat(500);
    const snippet = createSnippet({
        variations: [
            createVariation({
                content: [
                    longLine,
                    longLine,
                    'foreach ($items as $item) {',
                    longLine,
                    longLine,
                    '}',
                ].join('\n'),
            }),
        ],
    });
    const excerpt = searchSnippetMatches([snippet], 'foreach', {
        scope: 'code',
    })[0]?.excerpt;

    assert.equal(excerpt?.lineStart, 1);
    assert.equal(excerpt?.lineEnd, 6);
    assert.equal(excerpt?.text.split('\n').length, 6);
    assert.equal(
        excerpt?.text.slice(excerpt.matchStart, excerpt.matchEnd),
        'foreach',
    );
});

test('long matched lines stay cropped without discarding surrounding lines', () => {
    const snippet = createSnippet({
        variations: [
            createVariation({
                content: `before\n${'x'.repeat(500)}needle${'y'.repeat(500)}\nafter`,
            }),
        ],
    });
    const excerpt = searchSnippetMatches([snippet], 'needle', {
        scope: 'code',
    })[0]?.excerpt;

    assert.equal(excerpt?.lineStart, 1);
    assert.equal(excerpt?.lineEnd, 3);
    assert.equal(excerpt?.text.split('\n').length, 3);
    assert.equal(excerpt?.text.includes('needle'), true);
});

test('section results expose scores that favour section names over code', () => {
    const namedSectionSnippet = createSnippet({
        id: 1,
        variations: [
            createVariation({
                content: [
                    '{!# snippet: foreach_loop #!}',
                    'echo "named section";',
                ].join('\n'),
            }),
        ],
    });
    const codeSectionSnippet = createSnippet({
        id: 2,
        variations: [
            createVariation({
                id: 2,
                content: [
                    '{!# snippet: register_loop #!}',
                    'foreach ($items as $item) {}',
                ].join('\n'),
            }),
        ],
    });
    const results = searchSnippetSections(
        [codeSectionSnippet, namedSectionSnippet],
        'foreach',
    );

    assert.deepEqual(
        results.map((result) => result.snippet.id),
        [namedSectionSnippet.id, codeSectionSnippet.id],
    );
    assert.equal(results[0].score > results[1].score, true);
});

test('framework scope includes direct and containing project frameworks', () => {
    const project = createProject({ frameworks: [laravelFramework] });
    const inheritedSnippet = createSnippet({ project_id: project.id });
    const directSnippet = createSnippet({
        id: 2,
        project_id: null,
        frameworks: [reactFramework],
    });

    assert.deepEqual(
        searchSnippetMatches([inheritedSnippet], 'laravel', {
            projects: [project],
            scope: 'framework',
        }).map((match) => match.snippet.id),
        [inheritedSnippet.id],
    );
    assert.deepEqual(
        searchSnippetMatches([directSnippet], 'react', {
            projects: [project],
            scope: 'framework',
        }).map((match) => match.snippet.id),
        [directSnippet.id],
    );
    assert.deepEqual(
        searchProjects([project], 'laravel', { scope: 'framework' }).map(
            (match) => match.id,
        ),
        [project.id],
    );
});

test('explicit advanced fields still combine with scoped text', () => {
    const snippet = createSnippet({
        language: 'php',
        variations: [
            createVariation({ content: 'foreach ($items as $item) {}' }),
        ],
    });

    assert.equal(
        searchSnippetMatches([snippet], 'foreach language==php', {
            scope: 'code',
        }).length,
        1,
    );
    assert.equal(
        searchSnippetMatches([snippet], 'foreach language==javascript', {
            scope: 'code',
        }).length,
        0,
    );
});

test('negative-only matches do not invent a code excerpt', () => {
    const match = searchSnippetMatches([createSnippet()], '!legacy')[0];

    assert.equal(match?.excerpt, null);
});

test('workspace entities isolate projects, snippet files, and guides', () => {
    const snippet = createSnippet();
    const guide = createSnippet({ id: 2, content_type: 'guide' });

    assert.equal(
        snippetMatchesWorkspaceSearchEntity(snippet, 'snippets'),
        true,
    );
    assert.equal(snippetMatchesWorkspaceSearchEntity(guide, 'snippets'), false);
    assert.equal(snippetMatchesWorkspaceSearchEntity(guide, 'guides'), true);
    assert.equal(
        snippetMatchesWorkspaceSearchEntity(snippet, 'projects'),
        false,
    );
    assert.deepEqual(
        searchSnippetMatches([snippet, guide], '')
            .filter((match) =>
                snippetMatchesWorkspaceSearchEntity(match.snippet, 'guides'),
            )
            .map((match) => match.snippet.id),
        [guide.id],
    );
});

function createProject(overrides: Partial<Project> = {}): Project {
    return {
        id: 1,
        library_category_id: null,
        name: 'Example workspace',
        kind: 'project',
        description: null,
        is_pinned: false,
        frameworks: [],
        folders: [],
        snippets: [],
        ...overrides,
    };
}

function createSnippet(overrides: Partial<Snippet> = {}): Snippet {
    return {
        id: 1,
        project_id: null,
        folder_id: null,
        title: 'Loop recipe',
        filename: 'recipe.php',
        content_type: 'snippet',
        language: 'php',
        description: null,
        position: 0,
        is_favourite: false,
        is_pinned: false,
        last_opened_at: null,
        updated_at: '2026-08-02T00:00:00.000Z',
        variations: [createVariation()],
        presets: [],
        tags: [],
        frameworks: [],
        usage: {
            copies_30d: 0,
            copies_total: 0,
            last_copied_at: null,
            relative_score: 0,
            indicator: 0,
        },
        ...overrides,
    };
}

function createVariation(
    overrides: Partial<SnippetVariation> = {},
): SnippetVariation {
    return {
        id: 1,
        name: 'Default',
        content: '<?php echo "ready";',
        position: 0,
        is_default: true,
        updated_at: '2026-08-02T00:00:00.000Z',
        sections: [],
        guide_steps: [],
        ...overrides,
    };
}

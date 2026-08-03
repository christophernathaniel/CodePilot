import assert from 'node:assert/strict';
import test from 'node:test';
import { rankMegaSearchCandidates } from './mega-search-ranking.ts';

test('puts a strong title result before incidental code matches', () => {
    const ranked = rankMegaSearchCandidates([
        {
            item: 'embedded code match',
            snippetId: 1,
            kind: 'section',
            score: 100,
            usageScore: 12,
            title: 'Register metadata',
        },
        {
            item: 'PHP Foreach Loop',
            snippetId: 2,
            kind: 'snippet',
            score: 1_050,
            usageScore: 0,
            title: 'PHP Foreach Loop',
        },
    ]);

    assert.deepEqual(ranked, ['PHP Foreach Loop', 'embedded code match']);
});

test('uses a directly matching file instead of weaker sections from that file', () => {
    const ranked = rankMegaSearchCandidates([
        {
            item: 'file',
            snippetId: 1,
            kind: 'snippet',
            score: 1_050,
            usageScore: 0,
            title: 'Foreach recipes',
        },
        {
            item: 'section',
            snippetId: 1,
            kind: 'section',
            score: 100,
            usageScore: 0,
            title: 'Foreach recipes',
        },
    ]);

    assert.deepEqual(ranked, ['file']);
});

test('uses embedded snippets for equal-strength code matches and usage for ties', () => {
    const ranked = rankMegaSearchCandidates([
        {
            item: 'parent file',
            snippetId: 1,
            kind: 'snippet',
            score: 100,
            usageScore: 40,
            title: 'Request examples',
        },
        {
            item: 'less-used section',
            snippetId: 1,
            kind: 'section',
            score: 100,
            usageScore: 40,
            title: 'Request examples',
        },
        {
            item: 'more-used section',
            snippetId: 2,
            kind: 'section',
            score: 100,
            usageScore: 90,
            title: 'Another request file',
        },
    ]);

    assert.deepEqual(ranked, ['more-used section', 'less-used section']);
});

test('keeps negative-only snippet matches with a zero score', () => {
    const ranked = rankMegaSearchCandidates([
        {
            item: 'current snippet',
            snippetId: 1,
            kind: 'snippet',
            score: 0,
            usageScore: 0,
            title: 'Current snippet',
        },
    ]);

    assert.deepEqual(ranked, ['current snippet']);
});

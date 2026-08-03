export type MegaSearchRankCandidate<Result> = {
    item: Result;
    snippetId: number;
    kind: 'snippet' | 'section';
    score: number;
    usageScore: number;
    title: string;
};

export function rankMegaSearchCandidates<Result>(
    candidates: readonly MegaSearchRankCandidate<Result>[],
): Result[] {
    const snippetScores = new Map<number, number>();
    const sectionScores = new Map<number, number>();

    candidates.forEach((candidate) => {
        const scores =
            candidate.kind === 'snippet' ? snippetScores : sectionScores;

        scores.set(
            candidate.snippetId,
            Math.max(scores.get(candidate.snippetId) ?? 0, candidate.score),
        );
    });

    return candidates
        .filter((candidate) => {
            if (candidate.kind === 'snippet') {
                const sectionScore = sectionScores.get(candidate.snippetId);

                return (
                    sectionScore === undefined || candidate.score > sectionScore
                );
            }

            const snippetScore = snippetScores.get(candidate.snippetId);

            return (
                snippetScore === undefined || candidate.score >= snippetScore
            );
        })
        .sort(
            (left, right) =>
                right.score - left.score ||
                right.usageScore - left.usageScore ||
                left.title.localeCompare(right.title),
        )
        .map((candidate) => candidate.item);
}

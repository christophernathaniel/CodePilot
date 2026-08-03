export type ClipboardSelection = {
    content: string;
    startOffset: number;
    endOffset: number;
    startLine: number;
    endLine: number;
};

export function createClipboardSelection(
    source: string,
    firstOffset: number,
    secondOffset: number,
): ClipboardSelection | null {
    const normalizedFirstOffset = normalizeOffset(source, firstOffset);
    const normalizedSecondOffset = normalizeOffset(source, secondOffset);
    const startOffset = Math.min(normalizedFirstOffset, normalizedSecondOffset);
    const endOffset = Math.max(normalizedFirstOffset, normalizedSecondOffset);
    const content = source.slice(startOffset, endOffset);

    if (content.length === 0) {
        return null;
    }

    const startLine = countLineBreaks(source.slice(0, startOffset)) + 1;
    const selectedLineBreaks = countLineBreaks(content);
    const endsAtLineBreak = content.endsWith('\n') || content.endsWith('\r');

    return {
        content,
        startOffset,
        endOffset,
        startLine,
        endLine: startLine + selectedLineBreaks - (endsAtLineBreak ? 1 : 0),
    };
}

function normalizeOffset(source: string, offset: number): number {
    if (!Number.isFinite(offset)) {
        return 0;
    }

    return Math.max(0, Math.min(Math.trunc(offset), source.length));
}

function countLineBreaks(value: string): number {
    return value.match(/\r\n|\r|\n/gu)?.length ?? 0;
}

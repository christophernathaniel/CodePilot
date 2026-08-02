import type { SnippetSection } from '@/types/snippets';

export type ParsedSnippetSection = SnippetSection & {
    markerStart: number;
    markerEnd: number;
    contentStart: number;
    contentEnd: number;
};

const markerPattern =
    /^[\t ]*(?:(?:\/\/|#|--|\/\*|<!--|\{#)[\t ]*)?\{!#[\t ]*snippet[\t ]*:[\t ]*([A-Za-z0-9][A-Za-z0-9._-]{0,99})[\t ]*#!\}(?:[\t ]*(?:\*\/|-->|#\}))?[\t ]*(?:\r\n|\n|\r|$)/gmu;

export function parseSnippetSections(source: string): ParsedSnippetSection[] {
    const markers = Array.from(source.matchAll(markerPattern), (match) => ({
        marker: match[0],
        name: match[1],
        start: match.index,
        end: match.index + match[0].length,
    }));

    if (markers.length === 0) {
        return [];
    }

    const keyOccurrences = new Map<string, number>();

    return markers.map((marker, index) => {
        const baseKey = marker.name.toLowerCase();
        const occurrence = (keyOccurrences.get(baseKey) ?? 0) + 1;
        const contentEnd = markers[index + 1]?.start ?? source.length;
        const content = source.slice(marker.end, contentEnd);
        const markerLine = lineAtOffset(source, marker.start);
        const startLine = markerLine + 1;

        keyOccurrences.set(baseKey, occurrence);

        return {
            key: occurrence === 1 ? baseKey : `${baseKey}#${occurrence}`,
            name: marker.name,
            label: sectionLabel(marker.name),
            position: index + 1,
            marker_line: markerLine,
            start_line: startLine,
            end_line:
                content === ''
                    ? startLine
                    : lineAtOffset(
                          source,
                          endOffset(source, marker.end, contentEnd),
                      ),
            content,
            markerStart: marker.start,
            markerEnd: marker.end,
            contentStart: marker.end,
            contentEnd,
        };
    });
}

function endOffset(
    source: string,
    contentStart: number,
    contentEnd: number,
): number {
    const offset = Math.max(contentStart, contentEnd - 1);

    return offset > contentStart &&
        source[offset] === '\n' &&
        source[offset - 1] === '\r'
        ? offset - 1
        : offset;
}

function lineAtOffset(source: string, offset: number): number {
    return (source.slice(0, offset).match(/\r\n|\n|\r/gu)?.length ?? 0) + 1;
}

function sectionLabel(name: string): string {
    return name
        .replace(/([a-z\d])([A-Z])/gu, '$1 $2')
        .replace(/[._-]+/gu, ' ')
        .trim()
        .split(/\s+/u)
        .map((word) => `${word.charAt(0).toUpperCase()}${word.slice(1)}`)
        .join(' ');
}

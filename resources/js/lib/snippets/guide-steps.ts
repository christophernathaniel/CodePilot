import type { GuideCodeBlock, GuideStep } from '@/types/snippets';

const guideStepMarkerPattern =
    /^[\t ]*\{!#[\t ]*guide-step[\t ]*:[\t ]*([A-Za-z0-9][A-Za-z0-9._-]{0,99})[\t ]*\|[\t ]*([^\r\n]{1,255}?)[\t ]*#!\}[\t ]*(?:\r\n|\n|\r|$)/gmu;

const guideStepMarkerLinePattern =
    /^[\t ]*\{!#[\t ]*guide-step[\t ]*:[\t ]*[A-Za-z0-9][A-Za-z0-9._-]{0,99}[\t ]*\|[\t ]*[^\r\n]{1,255}?[\t ]*#!\}[\t ]*$/u;

const codeFencePattern =
    /^[\t ]*(`{3,}|~{3,})[\t ]*([^\r\n]*)[\t ]*(?:\r\n|\n|\r)(.*?)(?:^[\t ]*\1[\t ]*(?:\r\n|\n|\r|$))/gmsu;

type GuideStepMarker = {
    key: string;
    title: string;
    offset: number;
    length: number;
};

type CodeFenceMatch = {
    offset: number;
    length: number;
    language: string;
    code: string;
    codeOffset: number;
};

/**
 * Parse the editable guide file format into ordered playback steps.
 *
 * Step markers use `{!# guide-step: step-key | Human title #!}` and each
 * complete fenced Markdown block inside the step becomes an independently
 * highlighted code example. Content before the first marker is file-level
 * prose and is intentionally not included in playback.
 */
export function parseGuideSteps(source: string): GuideStep[] {
    const markers = findGuideStepMarkers(source);

    return markers.map((marker, index) => {
        const contentStart = marker.offset + marker.length;
        const contentEnd = markers[index + 1]?.offset ?? source.length;
        const stepContent = source.slice(contentStart, contentEnd);
        const markerLine = lineAtOffset(source, marker.offset);
        const startLine = markerLine + 1;

        return {
            key: marker.key,
            title: marker.title,
            position: index + 1,
            marker_line: markerLine,
            start_line: startLine,
            end_line:
                stepContent === ''
                    ? startLine
                    : lineAtOffset(
                          source,
                          endOffset(source, contentStart, contentEnd),
                      ),
            ...parseStepContent(source, stepContent, contentStart),
        };
    });
}

export function isGuideStepMarker(line: string): boolean {
    return guideStepMarkerLinePattern.test(line);
}

function findGuideStepMarkers(source: string): GuideStepMarker[] {
    const markers: GuideStepMarker[] = [];
    const keyCounts = new Map<string, number>();

    for (const match of source.matchAll(guideStepMarkerPattern)) {
        const baseKey = match[1].toLowerCase();
        const occurrence = (keyCounts.get(baseKey) ?? 0) + 1;
        keyCounts.set(baseKey, occurrence);

        markers.push({
            key: occurrence === 1 ? baseKey : `${baseKey}#${occurrence}`,
            title: match[2].trim().replace(/\s+/gu, ' '),
            offset: match.index,
            length: match[0].length,
        });
    }

    return markers;
}

function parseStepContent(
    source: string,
    stepContent: string,
    contentStart: number,
): { instructions: string; code_blocks: GuideCodeBlock[] } {
    const matches = findCodeFences(stepContent);
    const codeBlocks = matches.map((match) => {
        const codeStart = contentStart + match.codeOffset;
        const codeEnd = codeStart + match.code.length;
        const startLine = lineAtOffset(source, codeStart);

        return {
            language: match.language,
            content: match.code,
            start_line: startLine,
            end_line:
                match.code === ''
                    ? startLine
                    : lineAtOffset(
                          source,
                          endOffset(source, codeStart, codeEnd),
                      ),
        };
    });
    let instructions = stepContent;

    [...matches].reverse().forEach((match) => {
        instructions = `${instructions.slice(0, match.offset)}${instructions.slice(match.offset + match.length)}`;
    });

    return {
        instructions: instructions
            .replace(/(?:\r\n|\n|\r){3,}/gu, '\n\n')
            .trim(),
        code_blocks: codeBlocks,
    };
}

function findCodeFences(stepContent: string): CodeFenceMatch[] {
    return [...stepContent.matchAll(codeFencePattern)].map((match) => {
        const openingLineBreak = /\r\n|\n|\r/u.exec(match[0]);
        const codeOffset =
            match.index +
            (openingLineBreak?.index ?? 0) +
            (openingLineBreak?.[0].length ?? 0);
        const language = match[2].trim().split(/\s+/u)[0]?.toLowerCase();

        return {
            offset: match.index,
            length: match[0].length,
            language: language || 'plaintext',
            code: match[3],
            codeOffset,
        };
    });
}

function lineAtOffset(source: string, offset: number): number {
    return (source.slice(0, offset).match(/\r\n|\n|\r/gu)?.length ?? 0) + 1;
}

function endOffset(
    source: string,
    contentStart: number,
    contentEnd: number,
): number {
    const offset = Math.max(contentStart, contentEnd - 1);

    if (
        offset > contentStart &&
        source[offset] === '\n' &&
        source[offset - 1] === '\r'
    ) {
        return offset - 1;
    }

    return offset;
}

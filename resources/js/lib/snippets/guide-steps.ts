import type { GuideCodeBlock, GuideStep } from '@/types/snippets';

const guideStepMarkerPattern =
    /^[\t ]*\{!#[\t ]*guide-step[\t ]*:[\t ]*([A-Za-z0-9][A-Za-z0-9._-]{0,99})[\t ]*\|[\t ]*(.+?)[\t ]*#!\}[\t ]*$/u;

const fenceStartPattern =
    /^[\t ]*(`{3,}|~{3,})[\t ]*([A-Za-z0-9_+#.-]*)[^\n]*$/u;

type GuideStepMarker = {
    key: string;
    title: string;
    lineIndex: number;
};

/**
 * Parse the editable guide file format into ordered playback steps.
 *
 * Step markers use `{!# guide-step: step-key | Human title #!}` and each
 * fenced Markdown block inside the step becomes an independently highlighted
 * code example. Content before the first marker is intentionally treated as
 * file-level prose and is not included in playback.
 */
export function parseGuideSteps(source: string): GuideStep[] {
    const lines = source.replace(/\r\n?/gu, '\n').split('\n');
    const markers = findGuideStepMarkers(lines);

    return markers.map((marker, position) => {
        const nextMarker = markers[position + 1];
        const contentStartIndex = marker.lineIndex + 1;
        const contentEndIndex = nextMarker
            ? nextMarker.lineIndex - 1
            : lines.length - 1;
        const parsedContent = parseStepContent(
            lines,
            contentStartIndex,
            contentEndIndex,
        );

        return {
            key: marker.key,
            title: marker.title,
            position: position + 1,
            marker_line: marker.lineIndex + 1,
            start_line: contentStartIndex + 1,
            end_line: Math.max(contentStartIndex + 1, contentEndIndex + 1),
            instructions: parsedContent.instructions,
            code_blocks: parsedContent.codeBlocks,
        };
    });
}

export function isGuideStepMarker(line: string): boolean {
    return guideStepMarkerPattern.test(line);
}

function findGuideStepMarkers(lines: readonly string[]): GuideStepMarker[] {
    const markers: GuideStepMarker[] = [];

    lines.forEach((line, lineIndex) => {
        const match = guideStepMarkerPattern.exec(line);

        if (!match) {
            return;
        }

        markers.push({
            key: match[1],
            title: match[2].trim(),
            lineIndex,
        });
    });

    return markers;
}

function parseStepContent(
    lines: readonly string[],
    startIndex: number,
    endIndex: number,
): { instructions: string; codeBlocks: GuideCodeBlock[] } {
    const instructionLines: string[] = [];
    const codeBlocks: GuideCodeBlock[] = [];
    let lineIndex = startIndex;

    while (lineIndex <= endIndex) {
        const fenceMatch = fenceStartPattern.exec(lines[lineIndex]);

        if (!fenceMatch) {
            instructionLines.push(lines[lineIndex]);
            lineIndex += 1;

            continue;
        }

        const fence = fenceMatch[1];
        const fenceCharacter = fence[0];
        const language = fenceMatch[2].trim().toLowerCase() || 'plaintext';
        const codeStartIndex = lineIndex + 1;
        let closingFenceIndex = endIndex + 1;

        for (
            let candidateIndex = codeStartIndex;
            candidateIndex <= endIndex;
            candidateIndex += 1
        ) {
            const candidate = lines[candidateIndex].trim();

            if (
                candidate.length >= fence.length &&
                candidate.split('').every((character) => character === fenceCharacter)
            ) {
                closingFenceIndex = candidateIndex;
                break;
            }
        }

        const codeEndIndex = Math.min(closingFenceIndex - 1, endIndex);
        const code = lines
            .slice(codeStartIndex, codeEndIndex + 1)
            .join('\n')
            .replace(/\s+$/u, '');

        codeBlocks.push({
            language,
            content: code,
            start_line: codeStartIndex + 1,
            end_line: Math.max(codeStartIndex + 1, codeEndIndex + 1),
        });

        if (
            instructionLines.length > 0 &&
            instructionLines.at(-1)?.trim() !== ''
        ) {
            instructionLines.push('');
        }

        lineIndex =
            closingFenceIndex <= endIndex
                ? closingFenceIndex + 1
                : endIndex + 1;
    }

    return {
        instructions: trimBlankLines(instructionLines).join('\n'),
        codeBlocks,
    };
}

function trimBlankLines(lines: string[]): string[] {
    let start = 0;
    let end = lines.length;

    while (start < end && lines[start].trim() === '') {
        start += 1;
    }

    while (end > start && lines[end - 1].trim() === '') {
        end -= 1;
    }

    return lines.slice(start, end);
}

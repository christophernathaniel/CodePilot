<?php

namespace App\Support\Snippets;

use Illuminate\Support\Str;

final class GuideStepParser
{
    private const CODE_FENCE_PATTERN = '/^[\t ]*(`{3,}|~{3,})[\t ]*([^\r\n]*)[\t ]*(?:\r\n|\n|\r)(.*?)(?:^[\t ]*\1[\t ]*(?:\r\n|\n|\r|\z))/ms';

    private const MARKER_PATTERN = '/^[\t ]*\{!#[\t ]*guide-step[\t ]*:[\t ]*([A-Za-z0-9][A-Za-z0-9._-]{0,99})[\t ]*\|[\t ]*([^\r\n]{1,255}?)[\t ]*#!\}[\t ]*(?:\r\n|\n|\r|\z)/m';

    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     position: int,
     *     marker_line: int,
     *     start_line: int,
     *     end_line: int,
     *     instructions: string,
     *     code_blocks: list<array{
     *         language: string,
     *         content: string,
     *         start_line: int,
     *         end_line: int
     *     }>
     * }>
     */
    public function parse(string $source): array
    {
        preg_match_all(
            self::MARKER_PATTERN,
            $source,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        if ($matches[0] === []) {
            return [];
        }

        $steps = [];
        $keyOccurrences = [];

        foreach ($matches[0] as $index => [$marker, $markerOffset]) {
            $name = $matches[1][$index][0];
            $baseKey = Str::lower($name);
            $occurrence = ($keyOccurrences[$baseKey] ?? 0) + 1;
            $keyOccurrences[$baseKey] = $occurrence;
            $contentStart = $markerOffset + strlen($marker);
            $contentEnd = $matches[0][$index + 1][1] ?? strlen($source);
            $stepContent = substr($source, $contentStart, $contentEnd - $contentStart);
            $markerLine = $this->lineAtOffset($source, $markerOffset);
            $startLine = $markerLine + 1;

            $steps[] = [
                'key' => $occurrence === 1 ? $baseKey : "{$baseKey}#{$occurrence}",
                'title' => Str::of($matches[2][$index][0])->trim()->squish()->toString(),
                'position' => $index + 1,
                'marker_line' => $markerLine,
                'start_line' => $startLine,
                'end_line' => $stepContent === ''
                    ? $startLine
                    : $this->lineAtOffset(
                        $source,
                        $this->endOffset($source, $contentStart, $contentEnd),
                    ),
                ...$this->parseStepContent($source, $stepContent, $contentStart),
            ];
        }

        return $steps;
    }

    /**
     * @return array{
     *     instructions: string,
     *     code_blocks: list<array{language: string, content: string, start_line: int, end_line: int}>
     * }
     */
    private function parseStepContent(string $source, string $stepContent, int $contentStart): array
    {
        preg_match_all(
            self::CODE_FENCE_PATTERN,
            $stepContent,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        $codeBlocks = [];
        $instructions = $stepContent;

        foreach ($matches as $match) {
            $code = $match[3][0];
            $codeStart = $contentStart + $match[3][1];
            $codeEnd = $codeStart + strlen($code);
            $startLine = $this->lineAtOffset($source, $codeStart);
            $language = Str::of($match[2][0])
                ->trim()
                ->before(' ')
                ->lower()
                ->toString();

            $codeBlocks[] = [
                'language' => $language !== '' ? $language : 'plaintext',
                'content' => $code,
                'start_line' => $startLine,
                'end_line' => $code === ''
                    ? $startLine
                    : $this->lineAtOffset(
                        $source,
                        $this->endOffset($source, $codeStart, $codeEnd),
                    ),
            ];
        }

        foreach (array_reverse($matches) as $match) {
            $instructions = substr_replace(
                $instructions,
                '',
                $match[0][1],
                strlen($match[0][0]),
            );
        }

        $instructions = preg_replace('/(?:\r\n|\n|\r){3,}/', "\n\n", $instructions) ?? $instructions;

        return [
            'instructions' => Str::of($instructions)->trim()->toString(),
            'code_blocks' => $codeBlocks,
        ];
    }

    private function lineAtOffset(string $source, int $offset): int
    {
        return preg_match_all('/\r\n|\n|\r/', substr($source, 0, $offset)) + 1;
    }

    private function endOffset(string $source, int $contentStart, int $contentEnd): int
    {
        $offset = max($contentStart, $contentEnd - 1);

        if (
            $offset > $contentStart
            && $source[$offset] === "\n"
            && $source[$offset - 1] === "\r"
        ) {
            return $offset - 1;
        }

        return $offset;
    }
}

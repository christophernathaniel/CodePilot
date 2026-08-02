<?php

namespace App\Support\Snippets;

use Illuminate\Support\Str;

final class SnippetSectionParser
{
    private const MARKER_PATTERN = '/^[\t ]*(?:(?:\/\/|#|--|\/\*|<!--|\{#)[\t ]*)?\{!#[\t ]*snippet[\t ]*:[\t ]*([A-Za-z0-9][A-Za-z0-9._-]{0,99})[\t ]*#!\}(?:[\t ]*(?:\*\/|-->|#\}))?[\t ]*(?:\r\n|\n|\r|\z)/m';

    /**
     * @return list<array{
     *     key: string,
     *     name: string,
     *     label: string,
     *     position: int,
     *     marker_line: int,
     *     start_line: int,
     *     end_line: int,
     *     content: string
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

        $sections = [];
        $keyOccurrences = [];

        foreach ($matches[0] as $index => [$marker, $markerOffset]) {
            $name = $matches[1][$index][0];
            $baseKey = Str::lower($name);
            $occurrence = ($keyOccurrences[$baseKey] ?? 0) + 1;
            $keyOccurrences[$baseKey] = $occurrence;
            $contentStart = $markerOffset + strlen($marker);
            $contentEnd = $matches[0][$index + 1][1] ?? strlen($source);
            $sectionContent = substr($source, $contentStart, $contentEnd - $contentStart);
            $markerLine = $this->lineAtOffset($source, $markerOffset);
            $startLine = $markerLine + 1;

            $sections[] = [
                'key' => $occurrence === 1 ? $baseKey : "{$baseKey}#{$occurrence}",
                'name' => $name,
                'label' => $this->label($name),
                'position' => $index + 1,
                'marker_line' => $markerLine,
                'start_line' => $startLine,
                'end_line' => $sectionContent === ''
                    ? $startLine
                    : $this->lineAtOffset(
                        $source,
                        $this->endOffset($source, $contentStart, $contentEnd),
                    ),
                'content' => $sectionContent,
            ];
        }

        return $sections;
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

    private function label(string $name): string
    {
        $withWordBoundaries = preg_replace(
            '/(?<=[a-z0-9])(?=[A-Z])/',
            ' ',
            str_replace(['.', '_', '-'], ' ', $name),
        );

        $words = preg_split('/\s+/', trim($withWordBoundaries ?? $name)) ?: [$name];

        return implode(' ', array_map(
            fn (string $word): string => ucfirst($word),
            array_values(array_filter($words)),
        ));
    }
}

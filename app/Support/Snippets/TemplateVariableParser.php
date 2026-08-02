<?php

namespace App\Support\Snippets;

final class TemplateVariableParser
{
    /** @return array<string, string> */
    public function parse(string $content): array
    {
        preg_match_all(
            '/\{\{\{\s*(?<name>[A-Za-z_][A-Za-z0-9_]*)\s*:(?<default>.*?)\}\}\}/s',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        $variables = [];

        foreach ($matches as $match) {
            if (! array_key_exists($match['name'], $variables)) {
                $variables[$match['name']] = $match['default'];
            }
        }

        return $variables;
    }
}

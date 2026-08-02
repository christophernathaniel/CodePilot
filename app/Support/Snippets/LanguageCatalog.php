<?php

namespace App\Support\Snippets;

class LanguageCatalog
{
    /**
     * @var list<array{value: string, label: string, aliases: list<string>, syntax: string, extensions: list<string>}>
     */
    private const OPTIONS = [
        ['value' => 'plaintext', 'label' => 'Plain Text', 'aliases' => ['text', 'txt'], 'syntax' => 'plaintext', 'extensions' => ['txt']],
        ['value' => 'php', 'label' => 'PHP', 'aliases' => [], 'syntax' => 'php', 'extensions' => ['php']],
        ['value' => 'scss', 'label' => 'SCSS', 'aliases' => [], 'syntax' => 'css', 'extensions' => ['scss']],
        ['value' => 'twig', 'label' => 'TWIGLang', 'aliases' => ['twiglang'], 'syntax' => 'twig', 'extensions' => ['twig']],
        ['value' => 'javascript', 'label' => 'JavaScript', 'aliases' => ['js', 'node'], 'syntax' => 'javascript', 'extensions' => ['js', 'mjs', 'cjs']],
        ['value' => 'typescript', 'label' => 'TypeScript', 'aliases' => ['ts'], 'syntax' => 'typescript', 'extensions' => ['ts']],
        ['value' => 'html', 'label' => 'HTML', 'aliases' => [], 'syntax' => 'html', 'extensions' => ['html', 'htm']],
        ['value' => 'css', 'label' => 'CSS', 'aliases' => [], 'syntax' => 'css', 'extensions' => ['css']],
        ['value' => 'sass', 'label' => 'Sass', 'aliases' => [], 'syntax' => 'css', 'extensions' => ['sass']],
        ['value' => 'less', 'label' => 'Less', 'aliases' => [], 'syntax' => 'css', 'extensions' => ['less']],
        ['value' => 'jsx', 'label' => 'JSX', 'aliases' => [], 'syntax' => 'javascript', 'extensions' => ['jsx']],
        ['value' => 'tsx', 'label' => 'TSX', 'aliases' => [], 'syntax' => 'typescript', 'extensions' => ['tsx']],
        ['value' => 'json', 'label' => 'JSON', 'aliases' => [], 'syntax' => 'json', 'extensions' => ['json']],
        ['value' => 'jsonc', 'label' => 'JSON with Comments', 'aliases' => [], 'syntax' => 'json', 'extensions' => ['jsonc']],
        ['value' => 'xml', 'label' => 'XML', 'aliases' => [], 'syntax' => 'html', 'extensions' => ['xml']],
        ['value' => 'yaml', 'label' => 'YAML', 'aliases' => ['yml'], 'syntax' => 'yaml', 'extensions' => ['yaml', 'yml']],
        ['value' => 'toml', 'label' => 'TOML', 'aliases' => [], 'syntax' => 'plaintext', 'extensions' => ['toml']],
        ['value' => 'markdown', 'label' => 'Markdown', 'aliases' => ['md'], 'syntax' => 'markdown', 'extensions' => ['md']],
        ['value' => 'mdx', 'label' => 'MDX', 'aliases' => [], 'syntax' => 'markdown', 'extensions' => ['mdx']],
        ['value' => 'blade', 'label' => 'Blade', 'aliases' => [], 'syntax' => 'php', 'extensions' => ['blade.php']],
        ['value' => 'vue', 'label' => 'Vue', 'aliases' => [], 'syntax' => 'html', 'extensions' => ['vue']],
        ['value' => 'svelte', 'label' => 'Svelte', 'aliases' => [], 'syntax' => 'html', 'extensions' => ['svelte']],
        ['value' => 'python', 'label' => 'Python', 'aliases' => ['py'], 'syntax' => 'python', 'extensions' => ['py']],
        ['value' => 'ruby', 'label' => 'Ruby', 'aliases' => ['rb'], 'syntax' => 'ruby', 'extensions' => ['rb']],
        ['value' => 'java', 'label' => 'Java', 'aliases' => [], 'syntax' => 'java', 'extensions' => ['java']],
        ['value' => 'kotlin', 'label' => 'Kotlin', 'aliases' => ['kt'], 'syntax' => 'kotlin', 'extensions' => ['kt', 'kts']],
        ['value' => 'c', 'label' => 'C', 'aliases' => [], 'syntax' => 'c', 'extensions' => ['c', 'h']],
        ['value' => 'cpp', 'label' => 'C++', 'aliases' => ['c++'], 'syntax' => 'cpp', 'extensions' => ['cpp', 'cc', 'hpp']],
        ['value' => 'csharp', 'label' => 'C#', 'aliases' => ['c#', 'cs'], 'syntax' => 'csharp', 'extensions' => ['cs']],
        ['value' => 'go', 'label' => 'Go', 'aliases' => ['golang'], 'syntax' => 'go', 'extensions' => ['go']],
        ['value' => 'rust', 'label' => 'Rust', 'aliases' => ['rs'], 'syntax' => 'rust', 'extensions' => ['rs']],
        ['value' => 'swift', 'label' => 'Swift', 'aliases' => [], 'syntax' => 'swift', 'extensions' => ['swift']],
        ['value' => 'objective-c', 'label' => 'Objective-C', 'aliases' => ['objc'], 'syntax' => 'objective-c', 'extensions' => ['m', 'mm']],
        ['value' => 'dart', 'label' => 'Dart', 'aliases' => [], 'syntax' => 'dart', 'extensions' => ['dart']],
        ['value' => 'scala', 'label' => 'Scala', 'aliases' => [], 'syntax' => 'scala', 'extensions' => ['scala']],
        ['value' => 'elixir', 'label' => 'Elixir', 'aliases' => ['ex'], 'syntax' => 'elixir', 'extensions' => ['ex', 'exs']],
        ['value' => 'erlang', 'label' => 'Erlang', 'aliases' => ['erl'], 'syntax' => 'erlang', 'extensions' => ['erl']],
        ['value' => 'haskell', 'label' => 'Haskell', 'aliases' => ['hs'], 'syntax' => 'haskell', 'extensions' => ['hs']],
        ['value' => 'lua', 'label' => 'Lua', 'aliases' => [], 'syntax' => 'lua', 'extensions' => ['lua']],
        ['value' => 'perl', 'label' => 'Perl', 'aliases' => ['pl'], 'syntax' => 'perl', 'extensions' => ['pl']],
        ['value' => 'r', 'label' => 'R', 'aliases' => [], 'syntax' => 'r', 'extensions' => ['r']],
        ['value' => 'bash', 'label' => 'Bash / Shell', 'aliases' => ['shell', 'sh', 'zsh'], 'syntax' => 'bash', 'extensions' => ['sh', 'bash', 'zsh']],
        ['value' => 'powershell', 'label' => 'PowerShell', 'aliases' => ['ps1'], 'syntax' => 'powershell', 'extensions' => ['ps1']],
        ['value' => 'sql', 'label' => 'SQL', 'aliases' => [], 'syntax' => 'sql', 'extensions' => ['sql']],
        ['value' => 'graphql', 'label' => 'GraphQL', 'aliases' => ['gql'], 'syntax' => 'graphql', 'extensions' => ['graphql', 'gql']],
        ['value' => 'dockerfile', 'label' => 'Dockerfile', 'aliases' => ['docker'], 'syntax' => 'dockerfile', 'extensions' => ['dockerfile']],
        ['value' => 'makefile', 'label' => 'Makefile', 'aliases' => ['make'], 'syntax' => 'makefile', 'extensions' => ['makefile']],
        ['value' => 'nginx', 'label' => 'Nginx', 'aliases' => [], 'syntax' => 'nginx', 'extensions' => ['conf']],
        ['value' => 'apache', 'label' => 'Apache', 'aliases' => ['htaccess'], 'syntax' => 'apache', 'extensions' => ['conf', 'htaccess']],
        ['value' => 'ini', 'label' => 'INI', 'aliases' => [], 'syntax' => 'ini', 'extensions' => ['ini']],
        ['value' => 'diff', 'label' => 'Diff / Patch', 'aliases' => ['patch'], 'syntax' => 'diff', 'extensions' => ['diff', 'patch']],
        ['value' => 'solidity', 'label' => 'Solidity', 'aliases' => ['sol'], 'syntax' => 'solidity', 'extensions' => ['sol']],
    ];

    /** @return list<array{value: string, label: string, aliases: list<string>, syntax: string, extensions: list<string>}> */
    public static function options(): array
    {
        return self::OPTIONS;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::OPTIONS, 'value');
    }

    public static function normalize(string $language): ?string
    {
        $candidate = strtolower(trim($language));

        foreach (self::OPTIONS as $option) {
            if ($candidate === $option['value'] || in_array($candidate, $option['aliases'], true)) {
                return $option['value'];
            }
        }

        return null;
    }
}

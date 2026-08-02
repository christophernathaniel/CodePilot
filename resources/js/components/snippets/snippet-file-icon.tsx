import {
    BookOpenText,
    Braces,
    Code2,
    FileCode2,
    Hash,
    TerminalSquare,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { SnippetContentType } from '@/types';

const languageLabels: Record<string, { label: string; className: string }> = {
    apache: { label: 'APC', className: 'text-[#cf8f8f]' },
    bash: { label: 'SH', className: 'text-[#91b491]' },
    blade: { label: 'BLD', className: 'text-[#cc8e82]' },
    c: { label: 'C', className: 'text-[#8eafd0]' },
    cpp: { label: 'C++', className: 'text-[#8eafd0]' },
    csharp: { label: 'C#', className: 'text-[#91b491]' },
    css: { label: 'CSS', className: 'text-[#8eafd0]' },
    dart: { label: 'DART', className: 'text-[#84b8c7]' },
    diff: { label: '±', className: 'text-[#cf8f8f]' },
    dockerfile: { label: 'DKR', className: 'text-[#84b8c7]' },
    elixir: { label: 'EX', className: 'text-[#c9a7dc]' },
    erlang: { label: 'ERL', className: 'text-[#cf8f8f]' },
    go: { label: 'GO', className: 'text-[#84b8c7]' },
    graphql: { label: 'GQL', className: 'text-[#cf8f8f]' },
    haskell: { label: 'HS', className: 'text-[#c9a7dc]' },
    html: { label: 'HTML', className: 'text-[#cc8e82]' },
    ini: { label: 'INI', className: 'text-code-muted' },
    java: { label: 'JAVA', className: 'text-[#cf8f8f]' },
    javascript: { label: 'JS', className: 'text-[#d9bd83]' },
    jsx: { label: 'JSX', className: 'text-[#d9bd83]' },
    json: { label: '{}', className: 'text-[#d5a85e]' },
    jsonc: { label: '{}', className: 'text-[#d5a85e]' },
    kotlin: { label: 'KT', className: 'text-[#c9a7dc]' },
    less: { label: 'LESS', className: 'text-[#8eafd0]' },
    lua: { label: 'LUA', className: 'text-[#8eafd0]' },
    makefile: { label: 'MK', className: 'text-code-muted' },
    markdown: { label: 'MD', className: 'text-code-muted' },
    mdx: { label: 'MDX', className: 'text-code-muted' },
    nginx: { label: 'NGX', className: 'text-[#91b491]' },
    'objective-c': { label: 'O-C', className: 'text-[#8eafd0]' },
    perl: { label: 'PL', className: 'text-[#c9a7dc]' },
    php: { label: 'PHP', className: 'text-[#c9a7dc]' },
    plaintext: { label: 'TXT', className: 'text-code-muted' },
    powershell: { label: 'PS', className: 'text-[#8eafd0]' },
    python: { label: 'PY', className: 'text-[#84b8c7]' },
    r: { label: 'R', className: 'text-[#8eafd0]' },
    ruby: { label: 'RB', className: 'text-[#cf8f8f]' },
    rust: { label: 'RS', className: 'text-[#cc8e82]' },
    sass: { label: 'SASS', className: 'text-[#cf8f8f]' },
    scala: { label: 'SCL', className: 'text-[#cf8f8f]' },
    scss: { label: 'SCSS', className: 'text-[#cf8f8f]' },
    shell: { label: 'SH', className: 'text-[#91b491]' },
    solidity: { label: 'SOL', className: 'text-[#8eafd0]' },
    sql: { label: 'SQL', className: 'text-[#84b8c7]' },
    svelte: { label: 'SV', className: 'text-[#cc8e82]' },
    swift: { label: 'SW', className: 'text-[#cc8e82]' },
    toml: { label: 'TML', className: 'text-code-muted' },
    twig: { label: 'TW', className: 'text-[#91b491]' },
    typescript: { label: 'TS', className: 'text-[#8eafd0]' },
    tsx: { label: 'TSX', className: 'text-[#8eafd0]' },
    vue: { label: 'VUE', className: 'text-[#91b491]' },
    xml: { label: 'XML', className: 'text-[#cc8e82]' },
    yaml: { label: 'YML', className: 'text-[#cf8f8f]' },
};

type Props = {
    language: string;
    contentType?: SnippetContentType;
    className?: string;
};

export function SnippetFileIcon({
    language,
    contentType = 'snippet',
    className,
}: Props) {
    if (contentType === 'guide') {
        return (
            <BookOpenText
                aria-hidden="true"
                className={cn('size-3.5 text-sky-300/85', className)}
            />
        );
    }

    const normalizedLanguage = language.toLowerCase();
    const configuration = languageLabels[normalizedLanguage];

    if (configuration) {
        return (
            <span
                aria-hidden="true"
                className={cn(
                    'inline-flex min-w-5 items-center justify-center font-mono text-[9px] font-bold tracking-[-0.04em]',
                    configuration.className,
                    className,
                )}
            >
                {configuration.label}
            </span>
        );
    }

    const Icon =
        normalizedLanguage === 'shell'
            ? TerminalSquare
            : normalizedLanguage === 'text'
              ? Hash
              : normalizedLanguage.includes('script')
                ? Braces
                : normalizedLanguage
                  ? Code2
                  : FileCode2;

    return (
        <Icon
            aria-hidden="true"
            className={cn('size-4 text-code-muted', className)}
        />
    );
}

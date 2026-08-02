export type SyntaxTokenKind =
    | 'comment'
    | 'deleted'
    | 'function'
    | 'heading'
    | 'inserted'
    | 'keyword'
    | 'number'
    | 'operator'
    | 'plain'
    | 'property'
    | 'section'
    | 'string'
    | 'tag'
    | 'template'
    | 'variable';

export type SyntaxToken = {
    kind: SyntaxTokenKind;
    text: string;
};

type SyntaxRule = {
    kind: Exclude<SyntaxTokenKind, 'plain'>;
    pattern: RegExp;
};

const templateVariableRule: SyntaxRule = {
    kind: 'template',
    pattern: /\{\{\{[\s\S]*?\}\}\}/gu,
};

const snippetSectionRule: SyntaxRule = {
    kind: 'section',
    pattern:
        /^[\t ]*(?:(?:\/\/|#|--|\/\*|<!--|\{#)[\t ]*)?\{!#[\t ]*snippet[\t ]*:[\t ]*[A-Za-z0-9][A-Za-z0-9._-]{0,99}[\t ]*#!\}(?:[\t ]*(?:\*\/|-->|#\}))?[\t ]*$/gmu,
};

const guideStepRule: SyntaxRule = {
    kind: 'section',
    pattern:
        /^[\t ]*\{!#[\t ]*guide-step[\t ]*:[\t ]*[A-Za-z0-9][A-Za-z0-9._-]{0,99}[\t ]*\|[\t ]*.+?[\t ]*#!\}[\t ]*$/gmu,
};

const commonRules: SyntaxRule[] = [
    templateVariableRule,
    {
        kind: 'comment',
        pattern: /\/\*[\s\S]*?\*\/|\/\/[^\n]*|#[^\n]*/gu,
    },
    {
        kind: 'string',
        pattern:
            /`(?:\\[\s\S]|[^`\\])*`|'(?:\\[\s\S]|[^'\\])*'|"(?:\\[\s\S]|[^"\\])*"/gu,
    },
    { kind: 'number', pattern: /\b(?:0x[\da-f]+|\d+(?:\.\d+)?)\b/giu },
    {
        kind: 'function',
        pattern: /\b[A-Za-z_][\w]*(?=\s*\()/gu,
    },
    {
        kind: 'operator',
        pattern:
            /(?:===|!==|==|!=|=>|->|\?\?|&&|\|\||[{}[\]();,.<>:+\-*/%=!?&|])/gu,
    },
];

const languageRules: Record<string, SyntaxRule[]> = {
    plaintext: [],
    php: [
        { kind: 'tag', pattern: /<\?(?:php|=)?|\?>/giu },
        {
            kind: 'keyword',
            pattern:
                /\b(?:abstract|and|array|as|break|callable|case|catch|class|clone|const|continue|declare|default|do|echo|else|elseif|empty|enddeclare|endfor|endforeach|endif|endswitch|endwhile|enum|eval|exit|extends|final|finally|fn|for|foreach|function|global|goto|if|implements|include|include_once|instanceof|insteadof|interface|isset|list|match|namespace|new|or|print|private|protected|public|readonly|require|require_once|return|static|switch|throw|trait|try|unset|use|var|while|xor|yield)\b/giu,
        },
        { kind: 'variable', pattern: /\$[A-Za-z_][\w]*/gu },
        ...commonRules,
    ],
    javascript: [
        {
            kind: 'keyword',
            pattern:
                /\b(?:as|async|await|break|case|catch|class|const|continue|debugger|default|delete|do|else|export|extends|false|finally|for|from|function|get|if|import|in|instanceof|let|new|null|of|return|set|static|super|switch|this|throw|true|try|typeof|undefined|var|void|while|with|yield)\b/gu,
        },
        ...commonRules,
    ],
    typescript: [
        {
            kind: 'keyword',
            pattern:
                /\b(?:abstract|any|as|asserts|async|await|boolean|break|case|catch|class|const|continue|declare|default|delete|do|else|enum|export|extends|false|finally|for|from|function|get|if|implements|import|in|infer|instanceof|interface|keyof|let|never|new|null|number|object|of|private|protected|public|readonly|return|satisfies|set|static|string|super|switch|symbol|this|throw|true|try|type|typeof|undefined|unknown|var|void|while|with|yield)\b/gu,
        },
        ...commonRules,
    ],
    json: [
        {
            kind: 'property',
            pattern: /"(?:\\[\s\S]|[^"\\])*"(?=\s*:)/gu,
        },
        {
            kind: 'keyword',
            pattern: /\b(?:false|null|true)\b/gu,
        },
        ...commonRules,
    ],
    css: [
        { kind: 'comment', pattern: /\/\*[\s\S]*?\*\//gu },
        {
            kind: 'string',
            pattern: /'(?:\\[\s\S]|[^'\\])*'|"(?:\\[\s\S]|[^"\\])*"/gu,
        },
        { kind: 'keyword', pattern: /@[\w-]+/gu },
        {
            kind: 'property',
            pattern: /(?:--)?[A-Za-z][\w-]*(?=\s*:)/gu,
        },
        {
            kind: 'number',
            pattern:
                /#[\da-f]{3,8}\b|\b\d+(?:\.\d+)?(?:%|px|rem|em|vh|vw|s|ms|deg)?\b/giu,
        },
        ...commonRules,
    ],
    html: [
        { kind: 'comment', pattern: /<!--[\s\S]*?-->/gu },
        templateVariableRule,
        { kind: 'tag', pattern: /<\/?[A-Za-z][^>]*>/gu },
        ...commonRules,
    ],
    twig: [
        { kind: 'comment', pattern: /\{#[\s\S]*?#\}/gu },
        templateVariableRule,
        {
            kind: 'keyword',
            pattern:
                /\b(?:apply|as|autoescape|block|do|else|elseif|embed|endapply|endautoescape|endblock|endembed|endfilter|endfor|endmacro|endset|endwith|extends|filter|flush|for|from|if|import|in|include|is|macro|not|only|set|use|verbatim|with)\b/gu,
        },
        { kind: 'tag', pattern: /<\/?[A-Za-z][^>]*>/gu },
        ...commonRules,
    ],
    python: [
        {
            kind: 'string',
            pattern:
                /'''[\s\S]*?'''|"""[\s\S]*?"""|'(?:\\[\s\S]|[^'\\])*'|"(?:\\[\s\S]|[^"\\])*"/gu,
        },
        { kind: 'comment', pattern: /#[^\n]*/gu },
        {
            kind: 'keyword',
            pattern:
                /\b(?:and|as|assert|async|await|break|class|continue|def|del|elif|else|except|False|finally|for|from|global|if|import|in|is|lambda|None|nonlocal|not|or|pass|raise|return|True|try|while|with|yield)\b/gu,
        },
        { kind: 'variable', pattern: /@[A-Za-z_][\w.]*/gu },
        ...commonRules,
    ],
    bash: [
        { kind: 'comment', pattern: /#[^\n]*/gu },
        {
            kind: 'string',
            pattern:
                /'(?:[^']*)'|"(?:\\[\s\S]|[^"\\])*"|`(?:\\[\s\S]|[^`\\])*`/gu,
        },
        {
            kind: 'keyword',
            pattern:
                /\b(?:case|do|done|elif|else|esac|fi|for|function|if|in|select|then|time|until|while)\b/gu,
        },
        { kind: 'variable', pattern: /\$(?:\{[^}]+\}|[A-Za-z_][\w]*|\d+)/gu },
        ...commonRules,
    ],
    sql: [
        { kind: 'comment', pattern: /--[^\n]*|\/\*[\s\S]*?\*\//gu },
        {
            kind: 'keyword',
            pattern:
                /\b(?:add|alter|and|as|asc|begin|between|by|case|commit|constraint|create|cross|database|default|delete|desc|distinct|drop|else|end|exists|foreign|from|full|group|having|in|index|inner|insert|into|is|join|key|left|like|limit|not|null|on|or|order|outer|primary|references|right|rollback|select|set|table|then|truncate|union|unique|update|values|view|when|where|with)\b/giu,
        },
        ...commonRules,
    ],
    markdown: [
        { kind: 'heading', pattern: /^#{1,6}\s.*$/gmu },
        { kind: 'template', pattern: /```[\s\S]*?```/gu },
        { kind: 'string', pattern: /`[^`\n]+`/gu },
        { kind: 'keyword', pattern: /\*\*[^*\n]+\*\*|__[^_\n]+__/gu },
        { kind: 'tag', pattern: /\[[^\]]+\]\([^)]+\)/gu },
        ...commonRules,
    ],
    diff: [
        { kind: 'heading', pattern: /^(?:diff|index|@@).*$/gmu },
        { kind: 'inserted', pattern: /^\+(?!\+\+\+).*$/gmu },
        { kind: 'deleted', pattern: /^-(?!---).*$/gmu },
        ...commonRules,
    ],
    yaml: [
        templateVariableRule,
        { kind: 'comment', pattern: /#[^\n]*/gu },
        {
            kind: 'property',
            pattern: /^(?:\s*-\s*)?[A-Za-z_][\w.-]*(?=\s*:)/gmu,
        },
        {
            kind: 'keyword',
            pattern: /\b(?:false|null|true|yes|no|on|off)\b/giu,
        },
        {
            kind: 'string',
            pattern:
                /'(?:''|[^'])*'|"(?:\\[\s\S]|[^"\\])*"|(?<=:\s)[^\s{}[\],#][^\n#]*/gu,
        },
        ...commonRules,
    ],
};

const aliases: Record<string, string> = {
    blade: 'html',
    dockerfile: 'bash',
    ini: 'yaml',
    js: 'javascript',
    jsx: 'javascript',
    jsonc: 'json',
    less: 'css',
    makefile: 'bash',
    md: 'markdown',
    mdx: 'markdown',
    py: 'python',
    sass: 'css',
    scss: 'css',
    shell: 'bash',
    sh: 'bash',
    svelte: 'html',
    text: 'plaintext',
    toml: 'yaml',
    ts: 'typescript',
    tsx: 'typescript',
    txt: 'plaintext',
    vue: 'html',
    xml: 'html',
    yml: 'yaml',
};

export function tokenizeCode(source: string, language: string): SyntaxToken[] {
    if (source.length === 0) {
        return [];
    }

    const normalizedLanguage =
        aliases[language.toLowerCase()] ?? language.toLowerCase();
    const rules = [
        guideStepRule,
        snippetSectionRule,
        ...(languageRules[normalizedLanguage] ?? commonRules),
    ];
    const tokens: SyntaxToken[] = [];
    let cursor = 0;

    while (cursor < source.length) {
        let nextMatch: RegExpExecArray | null = null;
        let nextRule: SyntaxRule | null = null;

        for (const rule of rules) {
            rule.pattern.lastIndex = cursor;
            const match = rule.pattern.exec(source);

            if (
                match &&
                (nextMatch === null || match.index < nextMatch.index)
            ) {
                nextMatch = match;
                nextRule = rule;
            }
        }

        if (nextMatch === null || nextRule === null) {
            pushToken(tokens, 'plain', source.slice(cursor));
            break;
        }

        if (nextMatch.index > cursor) {
            pushToken(tokens, 'plain', source.slice(cursor, nextMatch.index));
        }

        pushToken(tokens, nextRule.kind, nextMatch[0]);
        cursor = nextMatch.index + nextMatch[0].length;
    }

    return tokens;
}

function pushToken(
    tokens: SyntaxToken[],
    kind: SyntaxTokenKind,
    text: string,
): void {
    if (text.length === 0) {
        return;
    }

    const previous = tokens.at(-1);

    if (previous?.kind === kind) {
        previous.text += text;

        return;
    }

    tokens.push({ kind, text });
}

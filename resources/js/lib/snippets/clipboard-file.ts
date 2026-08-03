import type {
    ClipboardSession,
    LanguageOption,
    SnippetContentType,
} from '@/types';

export type ClipboardFileDefaults = {
    title: string;
    filename: string;
    language: string;
    content_type: SnippetContentType;
};

export function canCreateClipboardFile(clipboard: ClipboardSession): boolean {
    return clipboard.clips_count > 0;
}

export function getClipboardFileDefaults(
    clipboard: ClipboardSession,
    languageOptions: LanguageOption[],
    contentType: SnippetContentType = 'snippet',
): ClipboardFileDefaults {
    const language = getClipboardFileLanguage(clipboard, contentType);
    const languageOption = languageOptions.find(
        (option) => option.value === language,
    );
    const plaintextOption = languageOptions.find(
        (option) => option.value === 'plaintext',
    );
    const extension =
        languageOption?.extensions[0] ??
        (contentType === 'guide'
            ? 'md'
            : (plaintextOption?.extensions[0] ?? 'txt'));

    return {
        title: clipboard.name,
        filename: `${filenameStem(clipboard.name, extension)}.${extension}`,
        language:
            languageOption?.value ??
            (contentType === 'guide'
                ? 'markdown'
                : (plaintextOption?.value ?? 'plaintext')),
        content_type: contentType,
    };
}

export function getClipboardFileLanguage(
    clipboard: ClipboardSession,
    contentType: SnippetContentType,
): string {
    if (contentType === 'guide') {
        return 'markdown';
    }

    const firstLanguage = clipboard.clips[0]?.language;

    return firstLanguage &&
        clipboard.clips.every((clip) => clip.language === firstLanguage)
        ? firstLanguage
        : 'plaintext';
}

function filenameStem(name: string, extension: string): string {
    const extensionSuffix = `.${extension}`;
    const nameWithoutExtension = name.toLowerCase().endsWith(extensionSuffix)
        ? name.slice(0, -extensionSuffix.length)
        : name;

    return (
        nameWithoutExtension
            .normalize('NFKD')
            .replace(/\p{Mark}/gu, '')
            .toLowerCase()
            .replace(/[^\p{Letter}\p{Number}]+/gu, '-')
            .replace(/^-+|-+$/gu, '') || 'clipboard'
    );
}

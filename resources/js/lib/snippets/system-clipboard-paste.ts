export type ClipboardTextReader = {
    getData: (format: string) => string;
};

export function readClipboardText(
    clipboardData: ClipboardTextReader | null,
): string {
    if (clipboardData === null) {
        return '';
    }

    const plainText = clipboardData.getData('text/plain');

    return plainText.length > 0
        ? plainText
        : clipboardData.getData('text/html');
}

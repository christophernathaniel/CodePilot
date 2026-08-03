export function sortPinnedFirst<T>(
    items: readonly T[],
    isPinned: (item: T) => boolean,
): T[] {
    const pinned: T[] = [];
    const unpinned: T[] = [];

    items.forEach((item) => {
        (isPinned(item) ? pinned : unpinned).push(item);
    });

    return [...pinned, ...unpinned];
}

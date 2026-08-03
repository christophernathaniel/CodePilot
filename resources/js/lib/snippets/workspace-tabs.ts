export type WorkspaceTabsState = {
    openIds: number[];
    activeId: number | null;
    pinnedIds: number[];
};

export type WorkspaceTabDropPosition = 'before' | 'after';

export function restoreMultiFileMode(storedValue: unknown): boolean {
    if (!isRecord(storedValue)) {
        return true;
    }

    return typeof storedValue.multiFileMode === 'boolean'
        ? storedValue.multiFileMode
        : true;
}

export function restoreWorkspaceTabs(
    storedValue: unknown,
    validSnippetIds: ReadonlySet<number>,
): WorkspaceTabsState {
    if (!isRecord(storedValue)) {
        return emptyWorkspaceTabs();
    }

    const openIds = validUniqueIds(storedValue.openIds, validSnippetIds);
    const openIdSet = new Set(openIds);
    const pinnedIds = validUniqueIds(storedValue.pinnedIds, openIdSet);
    const activeId =
        typeof storedValue.activeId === 'number' &&
        openIdSet.has(storedValue.activeId)
            ? storedValue.activeId
            : (openIds.at(-1) ?? null);

    return { openIds, activeId, pinnedIds };
}

export function closeWorkspaceTabs(
    state: WorkspaceTabsState,
    snippetIds: Iterable<number>,
): WorkspaceTabsState {
    const idsToClose = new Set(snippetIds);
    const activeIndex =
        state.activeId !== null ? state.openIds.indexOf(state.activeId) : -1;
    const openIds = state.openIds.filter(
        (snippetId) => !idsToClose.has(snippetId),
    );
    const activeId =
        state.activeId !== null && idsToClose.has(state.activeId)
            ? (openIds[Math.min(activeIndex, openIds.length - 1)] ?? null)
            : state.activeId;

    return {
        openIds,
        activeId,
        pinnedIds: state.pinnedIds.filter(
            (snippetId) => !idsToClose.has(snippetId),
        ),
    };
}

export function closeUnpinnedWorkspaceTabs(
    state: WorkspaceTabsState,
): WorkspaceTabsState {
    const pinnedIds = new Set(state.pinnedIds);

    return closeWorkspaceTabs(
        state,
        state.openIds.filter((snippetId) => !pinnedIds.has(snippetId)),
    );
}

export function togglePinnedSnippet(
    pinnedIds: number[],
    snippetId: number,
): number[] {
    return pinnedIds.includes(snippetId)
        ? pinnedIds.filter((id) => id !== snippetId)
        : [...pinnedIds, snippetId];
}

export function reorderWorkspaceTabs(
    openIds: number[],
    sourceId: number,
    targetId: number,
    position: WorkspaceTabDropPosition,
): number[] {
    if (
        sourceId === targetId ||
        !openIds.includes(sourceId) ||
        !openIds.includes(targetId)
    ) {
        return openIds;
    }

    const reorderedIds = openIds.filter((snippetId) => snippetId !== sourceId);
    const targetIndex = reorderedIds.indexOf(targetId);
    const insertionIndex = position === 'after' ? targetIndex + 1 : targetIndex;

    reorderedIds.splice(insertionIndex, 0, sourceId);

    return reorderedIds;
}

export function openWorkspaceSnippet(
    openIds: number[],
    pinnedIds: number[],
    snippetId: number,
    multiFileMode: boolean,
): number[] {
    if (multiFileMode) {
        return openIds.includes(snippetId) ? openIds : [...openIds, snippetId];
    }

    const pinnedIdSet = new Set(pinnedIds);
    const nextOpenIds = openIds.filter((id) => pinnedIdSet.has(id));

    return nextOpenIds.includes(snippetId)
        ? nextOpenIds
        : [...nextOpenIds, snippetId];
}

export function restrictWorkspaceTabsToSingleFile(
    state: WorkspaceTabsState,
): WorkspaceTabsState {
    const pinnedIdSet = new Set(state.pinnedIds);
    const openIds = state.openIds.filter(
        (snippetId) =>
            pinnedIdSet.has(snippetId) || snippetId === state.activeId,
    );

    return { ...state, openIds };
}

function emptyWorkspaceTabs(): WorkspaceTabsState {
    return { openIds: [], activeId: null, pinnedIds: [] };
}

function validUniqueIds(
    value: unknown,
    validIds: ReadonlySet<number>,
): number[] {
    if (!Array.isArray(value)) {
        return [];
    }

    return [
        ...new Set(
            value.filter(
                (id): id is number =>
                    typeof id === 'number' &&
                    Number.isInteger(id) &&
                    validIds.has(id),
            ),
        ),
    ];
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

export type WorkspaceDropPlacement = 'before' | 'after';

export function reorderWorkspaceIds(
    ids: readonly number[],
    sourceId: number,
    targetId: number,
    placement: WorkspaceDropPlacement,
): number[] {
    const sourceIndex = ids.indexOf(sourceId);
    const targetIndex = ids.indexOf(targetId);

    if (
        sourceIndex === -1 ||
        targetIndex === -1 ||
        sourceIndex === targetIndex ||
        (placement === 'before' && sourceIndex + 1 === targetIndex) ||
        (placement === 'after' && sourceIndex - 1 === targetIndex)
    ) {
        return [...ids];
    }

    const reorderedIds = ids.filter((id) => id !== sourceId);
    const updatedTargetIndex = reorderedIds.indexOf(targetId);
    const insertionIndex =
        placement === 'after' ? updatedTargetIndex + 1 : updatedTargetIndex;

    reorderedIds.splice(insertionIndex, 0, sourceId);

    return reorderedIds;
}

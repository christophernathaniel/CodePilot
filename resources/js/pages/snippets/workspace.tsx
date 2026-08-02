import type { FormDataConvertible, Page } from '@inertiajs/core';
import { Head, router, useHttp } from '@inertiajs/react';
import { Braces, Command, FilePlus2, FolderPlus, Plus } from 'lucide-react';
import {
    useCallback,
    useDeferredValue,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { toast } from 'sonner';
import {
    destroy as destroyFolder,
    store as storeFolder,
    update as updateFolder,
} from '@/actions/App/Http/Controllers/FolderController';
import MoveFolderController from '@/actions/App/Http/Controllers/MoveFolderController';
import MoveSnippetController from '@/actions/App/Http/Controllers/MoveSnippetController';
import PinController from '@/actions/App/Http/Controllers/PinController';
import {
    destroy as destroyProject,
    store as storeProject,
    update as updateProject,
} from '@/actions/App/Http/Controllers/ProjectController';
import {
    destroy as destroySnippet,
    store as storeSnippet,
    update as updateSnippet,
} from '@/actions/App/Http/Controllers/SnippetController';
import SnippetFavouriteController from '@/actions/App/Http/Controllers/SnippetFavouriteController';
import SnippetUsageController from '@/actions/App/Http/Controllers/SnippetUsageController';
import {
    destroy as destroyVariation,
    makeDefault as makeDefaultVariation,
    store as storeVariation,
    update as updateVariation,
} from '@/actions/App/Http/Controllers/SnippetVariationController';
import {
    destroy as destroyPreset,
    store as storePreset,
    update as updatePreset,
} from '@/actions/App/Http/Controllers/VariablePresetController';
import { EditorTabBar } from '@/components/snippets/editor-tab-bar';
import { libraryPinKey } from '@/components/snippets/project-explorer';
import type {
    ExplorerDragItem,
    ExplorerDropTarget,
    ExplorerEntity,
    LibraryPinTarget,
} from '@/components/snippets/project-explorer';
import { SnippetEditor } from '@/components/snippets/snippet-editor';
import type { SnippetEditorHandle } from '@/components/snippets/snippet-editor';
import {
    SnippetEditorStatus,
    SnippetEditorToolbar,
} from '@/components/snippets/snippet-editor-chrome';
import type { EditorMode } from '@/components/snippets/snippet-editor-chrome';
import { SnippetInspector } from '@/components/snippets/snippet-inspector';
import type { InspectorVariable } from '@/components/snippets/snippet-inspector';
import { SnippetSearch } from '@/components/snippets/snippet-search';
import type {
    SnippetSearchResult,
    SnippetSectionSearchResult,
} from '@/components/snippets/snippet-search';
import { WorkspaceActivityBar } from '@/components/snippets/workspace-activity-bar';
import type { WorkspacePanel } from '@/components/snippets/workspace-activity-bar';
import { WorkspaceDialog } from '@/components/snippets/workspace-dialog';
import type { WorkspaceDialogState } from '@/components/snippets/workspace-dialog';
import { WorkspaceSidePanel } from '@/components/snippets/workspace-side-panel';
import { useClipboard } from '@/hooks/use-clipboard';
import {
    applySearchSuggestion,
    findMatchingSnippetVariation,
    getSearchSuggestions,
    searchFolders,
    searchProjects,
    searchSnippetSections,
    searchSnippets,
} from '@/lib/snippets/search-query';
import { parseSnippetSections } from '@/lib/snippets/snippet-sections';
import type { ParsedSnippetSection } from '@/lib/snippets/snippet-sections';
import {
    parseTemplateVariables,
    resolveTemplate,
} from '@/lib/snippets/template-variables';
import { cn } from '@/lib/utils';
import type {
    Project,
    Snippet,
    SnippetVariation,
    SnippetWorkspaceProps,
    TemplateVariableValues,
    User,
    VariablePreset,
} from '@/types';

type Props = SnippetWorkspaceProps & {
    auth: {
        user: User;
    };
};

type OpenTabsStorage = {
    openIds: number[];
    activeId: number | null;
};

type CopyUsagePayload = {
    event_uuid: string;
    snippet_variation_id: number | null;
    variable_preset_id: number | null;
    method: 'keyboard' | 'button';
    representation: 'source' | 'rendered';
    scope: 'selection' | 'full';
    selection_length: number;
};

type CopyTarget = {
    snippet: Snippet;
    variation: SnippetVariation;
    presetId: number | null;
};

type PendingSectionSelection = {
    snippetId: number;
    variationId: number;
    sectionKey: string;
};

export default function Workspace({
    projects,
    standalone_snippets: standaloneSnippets,
    language_options: languageOptions,
    tags,
    frameworks,
    pins,
    auth,
}: Props) {
    const searchInputRef = useRef<HTMLInputElement>(null);
    const editorRef = useRef<SnippetEditorHandle>(null);
    const [activePanel, setActivePanel] = useState<WorkspacePanel>('explorer');
    const [mobilePanelOpen, setMobilePanelOpen] = useState(false);
    const [inspectorOpen, setInspectorOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [openIds, setOpenIds] = useState<number[]>([]);
    const [activeSnippetId, setActiveSnippetId] = useState<number | null>(null);
    const [variationDrafts, setVariationDrafts] = useState<
        Record<number, string>
    >({});
    const [selectedVariationIds, setSelectedVariationIds] = useState<
        Record<number, number | null>
    >({});
    const [selectedSectionKeys, setSelectedSectionKeys] = useState<
        Record<number, string | null>
    >({});
    const [pendingSectionSelection, setPendingSectionSelection] =
        useState<PendingSectionSelection | null>(null);
    const [selectedPresetIds, setSelectedPresetIds] = useState<
        Record<number, number | null>
    >({});
    const [variableOverrides, setVariableOverrides] = useState<
        Record<number, TemplateVariableValues>
    >({});
    const [editorMode, setEditorMode] = useState<EditorMode>('source');
    const [cursor, setCursor] = useState({ line: 1, column: 1 });
    const [saving, setSaving] = useState(false);
    const [dialog, setDialog] = useState<WorkspaceDialogState>(null);
    const [dialogProcessing, setDialogProcessing] = useState(false);
    const [dialogErrors, setDialogErrors] = useState<Record<string, string>>(
        {},
    );
    const [createSnippetAfterWorkspace, setCreateSnippetAfterWorkspace] =
        useState(false);
    const [storageHydrated, setStorageHydrated] = useState(false);
    const [revealedProjectId, setRevealedProjectId] = useState<number | null>(
        null,
    );
    const [revealedFolderId, setRevealedFolderId] = useState<number | null>(
        null,
    );
    const [copiedText, copy] = useClipboard();
    const copyUsageRequest = useHttp<CopyUsagePayload>({
        event_uuid: '',
        snippet_variation_id: null,
        variable_preset_id: null,
        method: 'button',
        representation: 'source',
        scope: 'full',
        selection_length: 0,
    });

    const storageKey = `codepilot.workspace.tabs.${auth.user.id}`;

    useEffect(() => {
        const frame = window.requestAnimationFrame(() => {
            if (window.matchMedia('(min-width: 1280px)').matches) {
                setInspectorOpen(true);
            }
        });

        return () => window.cancelAnimationFrame(frame);
    }, []);

    const allSnippets = useMemo(
        () => [
            ...standaloneSnippets,
            ...projects.flatMap((project) => project.snippets),
        ],
        [projects, standaloneSnippets],
    );
    const snippetById = useMemo(
        () => new Map(allSnippets.map((snippet) => [snippet.id, snippet])),
        [allSnippets],
    );
    const projectById = useMemo(
        () => new Map(projects.map((project) => [project.id, project])),
        [projects],
    );
    const pinnedKeys = useMemo(
        () =>
            new Set([
                ...pins.snippet_ids.map((id) =>
                    libraryPinKey({ type: 'snippet', id }),
                ),
                ...pins.project_ids.map((id) =>
                    libraryPinKey({ type: 'project', id }),
                ),
                ...pins.tag_ids.map((id) => libraryPinKey({ type: 'tag', id })),
                ...pins.language_values.map((key) =>
                    libraryPinKey({ type: 'language', key }),
                ),
                ...pins.framework_ids.map((id) =>
                    libraryPinKey({ type: 'framework', id }),
                ),
            ]),
        [pins],
    );

    const openSnippets = useMemo(
        () =>
            openIds
                .map((snippetId) => snippetById.get(snippetId))
                .filter((snippet): snippet is Snippet => snippet !== undefined),
        [openIds, snippetById],
    );
    const activeSnippet = activeSnippetId
        ? (snippetById.get(activeSnippetId) ?? null)
        : null;
    const activeProject = activeSnippet
        ? activeSnippet.project_id === null
            ? null
            : (projectById.get(activeSnippet.project_id) ?? null)
        : null;
    const activeVariation = activeSnippet
        ? (activeSnippet.variations.find(
              (variation) =>
                  variation.id === selectedVariationIds[activeSnippet.id],
          ) ??
          activeSnippet.variations.find((variation) => variation.is_default) ??
          activeSnippet.variations[0] ??
          null)
        : null;
    const activeSource = activeVariation
        ? (variationDrafts[activeVariation.id] ?? activeVariation.content)
        : '';
    const activeSections = parseSnippetSections(activeSource);
    const activeSection = activeVariation
        ? (activeSections.find(
              (section) =>
                  section.key === selectedSectionKeys[activeVariation.id],
          ) ?? null)
        : null;
    const deferredActiveSource = useDeferredValue(activeSource);
    const activeVariables = useMemo(
        () => parseTemplateVariables(deferredActiveSource),
        [deferredActiveSource],
    );
    const activeSelectedPreset = activeSnippet
        ? (activeSnippet.presets.find(
              (preset) => preset.id === selectedPresetIds[activeSnippet.id],
          ) ?? null)
        : null;
    const activeVariableValues = useMemo(() => {
        if (!activeSnippet) {
            return {};
        }

        return {
            ...Object.fromEntries(
                activeVariables.map(({ name, defaultValue }) => [
                    name,
                    defaultValue,
                ]),
            ),
            ...(activeSelectedPreset?.values ?? {}),
            ...(variableOverrides[activeSnippet.id] ?? {}),
        };
    }, [
        activeSelectedPreset,
        activeSnippet,
        activeVariables,
        variableOverrides,
    ]);
    const renderedSource = useMemo(
        () =>
            editorMode === 'preview'
                ? resolveTemplate(activeSource, activeVariableValues)
                : '',
        [activeSource, activeVariableValues, editorMode],
    );
    const activeDirty = activeVariation
        ? variationDrafts[activeVariation.id] !== undefined &&
          variationDrafts[activeVariation.id] !== activeVariation.content
        : false;
    const dirtySnippetIds = useMemo(
        () =>
            new Set(
                allSnippets
                    .filter((snippet) =>
                        snippet.variations.some(
                            (variation) =>
                                variationDrafts[variation.id] !== undefined &&
                                variationDrafts[variation.id] !==
                                    variation.content,
                        ),
                    )
                    .map((snippet) => snippet.id),
            ),
        [allSnippets, variationDrafts],
    );
    const selectedFolderPath = useMemo(
        () =>
            activeSnippet && activeProject
                ? getFolderPath(activeProject, activeSnippet.folder_id)
                : [],
        [activeProject, activeSnippet],
    );

    const searchableSnippets = useMemo(
        () =>
            allSnippets.map((snippet) => ({
                ...snippet,
                variations: snippet.variations.map((variation) => {
                    const content =
                        variationDrafts[variation.id] ?? variation.content;

                    return {
                        ...variation,
                        content,
                        sections: parseSnippetSections(content),
                    };
                }),
            })),
        [allSnippets, variationDrafts],
    );

    const filteredSnippets = useMemo(
        () =>
            query.trim()
                ? searchSnippets(searchableSnippets, query, { projects })
                : [],
        [projects, query, searchableSnippets],
    );
    const visibleSnippets = query.trim() ? filteredSnippets : allSnippets;
    const filteredProjects = useMemo(
        () => (query.trim() ? searchProjects(projects, query) : []),
        [projects, query],
    );
    const filteredFolders = useMemo(
        () => (query.trim() ? searchFolders(projects, query) : []),
        [projects, query],
    );
    const filteredSections = useMemo(
        () =>
            query.trim()
                ? searchSnippetSections(searchableSnippets, query, {
                      projects,
                  })
                : [],
        [projects, query, searchableSnippets],
    );
    const matchedProjectIds = useMemo(
        () => new Set(filteredProjects.map((project) => project.id)),
        [filteredProjects],
    );
    const matchedFolderIds = useMemo(
        () => new Set(filteredFolders.map((result) => result.folder.id)),
        [filteredFolders],
    );
    const suggestions = useMemo(
        () =>
            getSearchSuggestions(query, {
                languages: languageOptions,
                frameworks,
                tags,
                projects,
                folders: projects.flatMap((project) => project.folders),
                variations: allSnippets.flatMap((snippet) =>
                    snippet.variations.map((variation) => variation.name),
                ),
                sections: searchableSnippets.flatMap((snippet) =>
                    snippet.variations.flatMap((variation) =>
                        variation.sections.flatMap((section) => [
                            section.name,
                            section.label,
                        ]),
                    ),
                ),
                limit: 6,
            }),
        [
            allSnippets,
            frameworks,
            languageOptions,
            projects,
            query,
            searchableSnippets,
            tags,
        ],
    );
    const searchResults = useMemo<SnippetSearchResult[]>(() => {
        const displayedSections = filteredSections.slice(0, 30);
        const sectionSnippetIds = new Set(
            displayedSections.map((result) => result.snippet.id),
        );

        return [
            ...filteredProjects.slice(0, 8).map((project) => ({
                kind: 'project' as const,
                project,
            })),
            ...filteredFolders.slice(0, 12).map((result) => ({
                kind: 'folder' as const,
                project: result.project,
                folder: result.folder,
                path: result.path.join(' / '),
            })),
            ...displayedSections.map((result) => {
                const project =
                    result.snippet.project_id === null
                        ? null
                        : projectById.get(result.snippet.project_id);
                const folderPath = project
                    ? getFolderPath(project, result.snippet.folder_id)
                    : [];

                return {
                    kind: 'section' as const,
                    ...result,
                    projectName: project?.name ?? 'Standalone',
                    path: [...folderPath, result.snippet.filename].join(' / '),
                };
            }),
            ...filteredSnippets
                .filter((snippet) => !sectionSnippetIds.has(snippet.id))
                .slice(0, 40)
                .map((snippet) => {
                    const project =
                        snippet.project_id === null
                            ? null
                            : projectById.get(snippet.project_id);
                    const folderPath = project
                        ? getFolderPath(project, snippet.folder_id)
                        : [];
                    const matchingVariation = findMatchingSnippetVariation(
                        snippet,
                        query,
                        { projects },
                    );

                    return {
                        kind: 'snippet' as const,
                        snippet,
                        projectName: project?.name ?? 'Standalone',
                        path: [...folderPath, snippet.filename].join(' / '),
                        variationId: matchingVariation?.id ?? null,
                        variationName: matchingVariation?.name ?? null,
                    };
                }),
        ];
    }, [
        filteredFolders,
        filteredProjects,
        filteredSections,
        filteredSnippets,
        projectById,
        projects,
        query,
    ]);

    const openSnippet = useCallback((snippet: Snippet) => {
        setOpenIds((current) =>
            current.includes(snippet.id) ? current : [...current, snippet.id],
        );
        const defaultVariation =
            snippet.variations.find((variation) => variation.is_default) ??
            snippet.variations[0];

        setSelectedVariationIds((current) => {
            const selectedId = current[snippet.id];
            const selectionStillExists = snippet.variations.some(
                (variation) => variation.id === selectedId,
            );

            return selectionStillExists
                ? current
                : { ...current, [snippet.id]: defaultVariation?.id ?? null };
        });
        setActiveSnippetId(snippet.id);
        setMobilePanelOpen(false);
        setEditorMode('source');
        setCursor({ line: 1, column: 1 });
    }, []);

    const saveActiveSnippet = useCallback(() => {
        if (!activeSnippet || !activeVariation || !activeDirty || saving) {
            return;
        }

        setSaving(true);
        router.patch(
            updateVariation.url({
                snippet: activeSnippet.id,
                snippetVariation: activeVariation.id,
            }),
            { name: activeVariation.name, content: activeSource },
            {
                preserveScroll: true,
                onError: () =>
                    toast.error(
                        'The variation could not be saved. Your draft is safe.',
                    ),
                onFinish: () => setSaving(false),
            },
        );
    }, [activeDirty, activeSource, activeSnippet, activeVariation, saving]);

    useEffect(() => {
        if (
            !pendingSectionSelection ||
            activeSnippet?.id !== pendingSectionSelection.snippetId ||
            activeVariation?.id !== pendingSectionSelection.variationId
        ) {
            return;
        }

        const section = activeSections.find(
            (candidate) => candidate.key === pendingSectionSelection.sectionKey,
        );

        const frame = window.requestAnimationFrame(() => {
            if (section) {
                editorRef.current?.selectRange(
                    section.contentStart,
                    section.contentEnd,
                );
            }

            setPendingSectionSelection(null);
        });

        return () => window.cancelAnimationFrame(frame);
    }, [
        activeSections,
        activeSnippet?.id,
        activeVariation?.id,
        pendingSectionSelection,
    ]);

    useEffect(() => {
        const validSnippetIds = new Set(snippetById.keys());
        const frame = window.requestAnimationFrame(() => {
            try {
                const stored = window.localStorage.getItem(storageKey);

                if (stored) {
                    const parsed = JSON.parse(stored) as OpenTabsStorage;
                    const validIds = parsed.openIds.filter((snippetId) =>
                        validSnippetIds.has(snippetId),
                    );
                    setOpenIds(validIds);
                    setActiveSnippetId(
                        parsed.activeId && validIds.includes(parsed.activeId)
                            ? parsed.activeId
                            : (validIds.at(-1) ?? null),
                    );
                }
            } catch {
                window.localStorage.removeItem(storageKey);
            } finally {
                setStorageHydrated(true);
            }
        });

        return () => window.cancelAnimationFrame(frame);
    }, [snippetById, storageKey]);

    useEffect(() => {
        if (!storageHydrated) {
            return;
        }

        window.localStorage.setItem(
            storageKey,
            JSON.stringify({ openIds, activeId: activeSnippetId }),
        );
    }, [activeSnippetId, openIds, storageHydrated, storageKey]);

    useEffect(() => {
        if (dirtySnippetIds.size === 0) {
            return;
        }

        const message =
            'You have unsaved snippet changes. Leave this workspace and discard them?';
        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };
        const removeBeforeVisitListener = router.on('before', (event) => {
            const visit = event.detail.visit;
            const method = visit.method.toLowerCase();
            const destination = new URL(visit.url, window.location.href);
            const isWorkspaceMutation =
                method !== 'get' &&
                /^(?:\/projects|\/snippets|\/folders|\/pins)(?:\/|$)/u.test(
                    destination.pathname,
                );
            const isWorkspaceReload =
                method === 'get' && destination.pathname === '/dashboard';

            if (isWorkspaceMutation || isWorkspaceReload) {
                return;
            }

            return window.confirm(message);
        });

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            removeBeforeVisitListener();
            window.removeEventListener('beforeunload', handleBeforeUnload);
        };
    }, [dirtySnippetIds.size]);

    useEffect(() => {
        const handleKeyboardShortcut = (event: KeyboardEvent) => {
            const focusedElement = document.activeElement;
            const isEditingText =
                focusedElement instanceof HTMLInputElement ||
                focusedElement instanceof HTMLTextAreaElement ||
                (focusedElement instanceof HTMLElement &&
                    focusedElement.isContentEditable);

            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();
                setActivePanel('search');
                searchInputRef.current?.focus();
            }

            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 's' &&
                !isEditingText
            ) {
                event.preventDefault();
                saveActiveSnippet();
            }
        };

        window.addEventListener('keydown', handleKeyboardShortcut);

        return () =>
            window.removeEventListener('keydown', handleKeyboardShortcut);
    }, [saveActiveSnippet]);

    const closeSnippet = (snippet: Snippet) => {
        if (
            dirtySnippetIds.has(snippet.id) &&
            !window.confirm(`Discard unsaved changes to ${snippet.filename}?`)
        ) {
            return;
        }

        const closingIndex = openIds.indexOf(snippet.id);
        const remainingIds = openIds.filter(
            (snippetId) => snippetId !== snippet.id,
        );
        setOpenIds(remainingIds);
        const variationIds = new Set(
            snippet.variations.map((variation) => variation.id),
        );
        setVariationDrafts((current) => {
            const next = { ...current };

            variationIds.forEach((variationId) => delete next[variationId]);

            return next;
        });

        if (activeSnippetId === snippet.id) {
            setActiveSnippetId(
                remainingIds[Math.min(closingIndex, remainingIds.length - 1)] ??
                    null,
            );
        }
    };

    const acceptSuggestion = (suggestion: string) => {
        setQuery((current) => applySearchSuggestion(current, suggestion));
        requestAnimationFrame(() => searchInputRef.current?.focus());
    };

    const recordCopy = async (
        target: CopyTarget,
        method: CopyUsagePayload['method'],
        representation: CopyUsagePayload['representation'],
        scope: CopyUsagePayload['scope'],
        selectionLength: number,
    ) => {
        copyUsageRequest.setData({
            event_uuid: crypto.randomUUID(),
            snippet_variation_id: target.variation.id,
            variable_preset_id: target.presetId,
            method,
            representation,
            scope,
            selection_length: selectionLength,
        });

        try {
            await copyUsageRequest.post(
                SnippetUsageController.url({ snippet: target.snippet.id }),
                {
                    onSuccess: () =>
                        router.reload({
                            only: ['projects', 'standalone_snippets'],
                        }),
                },
            );
        } catch {
            // Copying succeeded; telemetry must never interrupt the editor.
        }
    };

    const copyText = async (
        value: string,
        label: string,
        representation: CopyUsagePayload['representation'],
        scope: CopyUsagePayload['scope'],
        target: CopyTarget,
    ) => {
        if (await copy(value)) {
            toast.success(`${label} copied to the clipboard.`);
            void recordCopy(
                target,
                'button',
                representation,
                scope,
                value.length,
            );
        } else {
            toast.error('Clipboard access was not available.');
        }
    };

    const copyEmbeddedSnippet = async (
        target: CopyTarget,
        section: ParsedSnippetSection,
    ) => {
        if (section.content.length === 0) {
            toast.error('This embedded snippet is empty.');

            return;
        }

        await copyText(
            section.content,
            `${section.label} embedded snippet`,
            'source',
            'selection',
            target,
        );
    };

    const selectVariation = (variation: SnippetVariation) => {
        if (!activeSnippet) {
            return;
        }

        setSelectedVariationIds((current) => ({
            ...current,
            [activeSnippet.id]: variation.id,
        }));
        setEditorMode('source');
        setCursor({ line: 1, column: 1 });
    };

    const selectEmbeddedSection = (section: ParsedSnippetSection) => {
        if (!activeSnippet || !activeVariation) {
            return;
        }

        setSelectedSectionKeys((current) => ({
            ...current,
            [activeVariation.id]: section.key,
        }));
        setEditorMode('source');
        setPendingSectionSelection({
            snippetId: activeSnippet.id,
            variationId: activeVariation.id,
            sectionKey: section.key,
        });
    };

    const selectPreset = (preset: VariablePreset | null) => {
        if (!activeSnippet) {
            return;
        }

        setSelectedPresetIds((current) => ({
            ...current,
            [activeSnippet.id]: preset?.id ?? null,
        }));
        setVariableOverrides((current) => ({
            ...current,
            [activeSnippet.id]: {},
        }));
    };

    const currentPresetValues = () => ({
        ...(activeSelectedPreset?.values ?? {}),
        ...Object.fromEntries(
            activeVariables.map((variable) => [
                variable.name,
                activeVariableValues[variable.name] ?? variable.defaultValue,
            ]),
        ),
    });

    const beginCreateSnippet = () => {
        setCreateSnippetAfterWorkspace(false);
        setDialog({ kind: 'create-snippet', project: null, folder: null });
    };

    const submitDialog = (payload: Record<string, FormDataConvertible>) => {
        if (!dialog) {
            return;
        }

        setDialogProcessing(true);
        setDialogErrors({});
        const options = {
            preserveScroll: true,
            onSuccess: (page: Page) => {
                if (dialog.kind === 'delete') {
                    closeTabsForEntity(dialog.entity);
                }

                const refreshedProjects = page.props.projects as
                    Project[] | undefined;
                const refreshedStandaloneSnippets = page.props
                    .standalone_snippets as Snippet[] | undefined;

                if (
                    dialog.kind === 'create-project' &&
                    createSnippetAfterWorkspace &&
                    refreshedProjects
                ) {
                    const createdProject = refreshedProjects.find(
                        (project) => !projectById.has(project.id),
                    );

                    setCreateSnippetAfterWorkspace(false);
                    setDialog(
                        createdProject
                            ? {
                                  kind: 'create-snippet',
                                  project: createdProject,
                                  folder: null,
                              }
                            : null,
                    );
                    setDialogErrors({});

                    return;
                }

                if (dialog.kind === 'create-snippet') {
                    const projectId = payload.project_id
                        ? Number(payload.project_id)
                        : null;
                    const destinationSnippets =
                        projectId === null
                            ? (refreshedStandaloneSnippets ?? [])
                            : (refreshedProjects?.find(
                                  (project) => project.id === projectId,
                              )?.snippets ?? []);
                    const createdSnippet = destinationSnippets.find(
                        (snippet) =>
                            snippet.filename === payload.filename &&
                            snippet.folder_id ===
                                (payload.folder_id === null
                                    ? null
                                    : Number(payload.folder_id)),
                    );

                    if (createdSnippet) {
                        openSnippet(createdSnippet);
                    }
                }

                if (dialog.kind === 'create-variation' && refreshedProjects) {
                    const refreshedSnippet = [
                        ...(refreshedStandaloneSnippets ?? []),
                        ...refreshedProjects.flatMap(
                            (project) => project.snippets,
                        ),
                    ].find((snippet) => snippet.id === dialog.snippet.id);
                    const createdVariation = refreshedSnippet?.variations.find(
                        (variation) => variation.name === payload.name,
                    );

                    if (createdVariation) {
                        setSelectedVariationIds((current) => ({
                            ...current,
                            [dialog.snippet.id]: createdVariation.id,
                        }));
                    }
                }

                setDialog(null);
                setDialogErrors({});
            },
            onError: (errors: Record<string, string>) =>
                setDialogErrors(errors),
            onFinish: () => setDialogProcessing(false),
        };

        switch (dialog.kind) {
            case 'create-project': {
                router.post(storeProject.url(), payload, options);
                break;
            }
            case 'create-folder': {
                router.post(
                    storeFolder.url({ project: dialog.project.id }),
                    payload,
                    options,
                );
                break;
            }
            case 'create-snippet': {
                router.post(storeSnippet.url(), payload, options);
                break;
            }
            case 'create-variation': {
                router.post(
                    storeVariation.url({ snippet: dialog.snippet.id }),
                    payload,
                    options,
                );
                break;
            }
            case 'rename-variation': {
                router.patch(
                    updateVariation.url({
                        snippet: dialog.snippet.id,
                        snippetVariation: dialog.variation.id,
                    }),
                    {
                        ...payload,
                        content:
                            variationDrafts[dialog.variation.id] ??
                            dialog.variation.content,
                    },
                    options,
                );
                break;
            }
            case 'create-preset': {
                router.post(
                    storePreset.url({ snippet: dialog.snippet.id }),
                    { ...payload, values: currentPresetValues() },
                    options,
                );
                break;
            }
            case 'metadata': {
                router.patch(
                    updateSnippet.url({ snippet: dialog.snippet.id }),
                    {
                        ...buildSnippetPayload(dialog.snippet),
                        ...payload,
                    },
                    options,
                );
                break;
            }
            case 'rename': {
                submitRename(dialog.entity, payload, options);
                break;
            }
            case 'delete': {
                submitDelete(dialog.entity, options);
                break;
            }
        }
    };

    const submitRename = (
        entity: ExplorerEntity,
        payload: Record<string, FormDataConvertible>,
        options: VisitCallbacks,
    ) => {
        if (entity.type === 'project') {
            router.patch(
                updateProject.url({ project: entity.project.id }),
                payload,
                options,
            );

            return;
        }

        if (entity.type === 'folder') {
            router.patch(
                updateFolder.url({
                    project: entity.project.id,
                    folder: entity.folder.id,
                }),
                { name: payload.name, parent_id: entity.folder.parent_id },
                options,
            );

            return;
        }

        router.patch(
            updateSnippet.url({ snippet: entity.snippet.id }),
            {
                ...buildSnippetPayload(entity.snippet),
                title: payload.title,
            },
            options,
        );
    };

    const submitDelete = (entity: ExplorerEntity, options: VisitCallbacks) => {
        if (entity.type === 'project') {
            router.delete(
                destroyProject.url({ project: entity.project.id }),
                options,
            );

            return;
        }

        if (entity.type === 'folder') {
            router.delete(
                destroyFolder.url({
                    project: entity.project.id,
                    folder: entity.folder.id,
                }),
                options,
            );

            return;
        }

        router.delete(
            destroySnippet.url({ snippet: entity.snippet.id }),
            options,
        );
    };

    const closeTabsForEntity = (entity: ExplorerEntity) => {
        const idsToClose = new Set<number>();

        if (entity.type === 'snippet') {
            idsToClose.add(entity.snippet.id);
        } else if (entity.type === 'project') {
            entity.project.snippets.forEach((snippet) =>
                idsToClose.add(snippet.id),
            );
        } else {
            const descendantFolderIds = collectDescendantFolderIds(
                entity.project,
                entity.folder.id,
            );
            entity.project.snippets
                .filter(
                    (snippet) =>
                        snippet.folder_id !== null &&
                        descendantFolderIds.has(snippet.folder_id),
                )
                .forEach((snippet) => idsToClose.add(snippet.id));
        }

        setOpenIds((current) => current.filter((id) => !idsToClose.has(id)));

        if (activeSnippetId && idsToClose.has(activeSnippetId)) {
            setActiveSnippetId(null);
        }
    };

    const updateSelectedPreset = (preset: VariablePreset) => {
        if (!activeSnippet) {
            return;
        }

        router.patch(
            updatePreset.url({
                snippet: activeSnippet.id,
                variablePreset: preset.id,
            }),
            { name: preset.name, values: currentPresetValues() },
            { preserveScroll: true },
        );
    };

    const deleteSelectedPreset = (preset: VariablePreset) => {
        if (
            !activeSnippet ||
            !window.confirm(`Delete the “${preset.name}” preset?`)
        ) {
            return;
        }

        router.delete(
            destroyPreset.url({
                snippet: activeSnippet.id,
                variablePreset: preset.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => selectPreset(null),
            },
        );
    };

    const deleteSelectedVariation = (variation: SnippetVariation) => {
        if (
            !activeSnippet ||
            variation.is_default ||
            activeSnippet.variations.length <= 1 ||
            !window.confirm(`Delete the “${variation.name}” variation?`)
        ) {
            return;
        }

        router.delete(
            destroyVariation.url({
                snippet: activeSnippet.id,
                snippetVariation: variation.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    setVariationDrafts((current) => {
                        const next = { ...current };
                        delete next[variation.id];

                        return next;
                    });
                    const fallback =
                        activeSnippet.variations.find(
                            (candidate) => candidate.is_default,
                        ) ?? activeSnippet.variations[0];

                    if (fallback) {
                        selectVariation(fallback);
                    }
                },
            },
        );
    };

    const setDefaultVariation = (variation: SnippetVariation) => {
        if (!activeSnippet || variation.is_default) {
            return;
        }

        router.patch(
            makeDefaultVariation.url({
                snippet: activeSnippet.id,
                snippetVariation: variation.id,
            }),
            {},
            { preserveScroll: true },
        );
    };

    const togglePin = (target: LibraryPinTarget) => {
        const isPinned = pinnedKeys.has(libraryPinKey(target));
        const pinnableKey =
            target.type === 'language' ? target.key : String(target.id);

        router.put(
            PinController.url(),
            {
                pinnable_type: target.type,
                pinnable_key: pinnableKey,
                pinned: !isPinned,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => toast.error('The pin could not be updated.'),
            },
        );
    };

    const toggleFavourite = (snippet: Snippet) => {
        router.patch(
            SnippetFavouriteController.url({ snippet: snippet.id }),
            { is_favourite: !snippet.is_favourite },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () =>
                    toast.error('The favourite could not be updated.'),
            },
        );
    };

    const moveLibraryItem = (
        item: ExplorerDragItem,
        target: ExplorerDropTarget,
    ) => {
        if (item.type === 'snippet') {
            const destination =
                target.type === 'standalone'
                    ? { project_id: null, folder_id: null }
                    : target.type === 'project'
                      ? { project_id: target.projectId, folder_id: null }
                      : {
                            project_id: target.projectId,
                            folder_id: target.folderId,
                        };

            router.patch(
                MoveSnippetController.url({ snippet: item.id }),
                destination,
                {
                    preserveScroll: true,
                    onError: (errors) =>
                        toast.error(
                            errors.filename ??
                                errors.folder_id ??
                                'The snippet could not be moved.',
                        ),
                },
            );

            return;
        }

        if (target.type === 'standalone') {
            return;
        }

        router.patch(
            MoveFolderController.url({ folder: item.id }),
            {
                project_id: target.projectId,
                parent_id: target.type === 'folder' ? target.folderId : null,
            },
            {
                preserveScroll: true,
                onError: (errors) =>
                    toast.error(
                        errors.parent_id ??
                            errors.name ??
                            'The folder could not be moved.',
                    ),
            },
        );
    };

    const openSearchResult = (result: SnippetSearchResult) => {
        if (result.kind === 'section') {
            openSnippet(result.snippet);
            setSelectedVariationIds((current) => ({
                ...current,
                [result.snippet.id]: result.variation.id,
            }));
            setSelectedSectionKeys((current) => ({
                ...current,
                [result.variation.id]: result.section.key,
            }));
            setEditorMode('source');
            setPendingSectionSelection({
                snippetId: result.snippet.id,
                variationId: result.variation.id,
                sectionKey: result.section.key,
            });

            return;
        }

        if (result.kind === 'snippet') {
            openSnippet(result.snippet);

            if (result.variationId !== null) {
                setSelectedVariationIds((current) => ({
                    ...current,
                    [result.snippet.id]: result.variationId,
                }));
            }

            return;
        }

        setActivePanel('explorer');
        setMobilePanelOpen(true);
        setQuery('');
        setRevealedProjectId(null);
        setRevealedFolderId(null);

        window.requestAnimationFrame(() => {
            setRevealedProjectId(result.project.id);
            setRevealedFolderId(
                result.kind === 'folder' ? result.folder.id : null,
            );
        });
    };

    const copySearchSection = (result: SnippetSectionSearchResult) =>
        copyEmbeddedSnippet(
            {
                snippet: result.snippet,
                variation: result.variation,
                presetId: null,
            },
            result.section,
        );

    return (
        <>
            <Head title="Snippet workspace" />
            <div className="snippet-workspace relative flex h-full min-h-0 overflow-hidden bg-code-canvas">
                <WorkspaceActivityBar
                    activePanel={activePanel}
                    inspectorOpen={inspectorOpen}
                    user={auth.user}
                    onPanelChange={(panel) => {
                        setMobilePanelOpen((open) =>
                            activePanel === panel ? !open : true,
                        );
                        setActivePanel(panel);
                    }}
                    onInspectorToggle={() => setInspectorOpen((open) => !open)}
                />

                <div
                    className={cn(
                        'absolute inset-y-0 left-12 z-30 min-h-0 shadow-2xl md:static md:z-auto md:flex md:shadow-none',
                        mobilePanelOpen ? 'flex' : 'hidden',
                    )}
                >
                    <WorkspaceSidePanel
                        panel={activePanel}
                        projects={projects}
                        standaloneSnippets={standaloneSnippets}
                        visibleSnippets={visibleSnippets}
                        matchedProjectIds={matchedProjectIds}
                        matchedFolderIds={matchedFolderIds}
                        languageOptions={languageOptions}
                        frameworks={frameworks}
                        tags={tags}
                        query={query}
                        suggestions={suggestions}
                        results={searchResults}
                        inputRef={searchInputRef}
                        activeSnippetId={activeSnippetId}
                        dirtySnippetIds={dirtySnippetIds}
                        revealedProjectId={revealedProjectId}
                        revealedFolderId={revealedFolderId}
                        accountKey={auth.user.id}
                        pinnedKeys={pinnedKeys}
                        onQueryChange={setQuery}
                        onSuggestionAccept={acceptSuggestion}
                        onSearchOpen={openSearchResult}
                        onCopySection={copySearchSection}
                        onOpenSnippet={openSnippet}
                        onCreateSnippet={beginCreateSnippet}
                        onNewProject={() => {
                            setCreateSnippetAfterWorkspace(false);
                            setDialog({ kind: 'create-project' });
                        }}
                        onNewFolder={(project, parent) =>
                            setDialog({
                                kind: 'create-folder',
                                project,
                                parent,
                            })
                        }
                        onNewSnippet={(project, folder) =>
                            setDialog({
                                kind: 'create-snippet',
                                project,
                                folder,
                            })
                        }
                        onRename={(entity) =>
                            setDialog({ kind: 'rename', entity })
                        }
                        onDelete={(entity) =>
                            setDialog({ kind: 'delete', entity })
                        }
                        onToggleFavourite={toggleFavourite}
                        onTogglePin={togglePin}
                        onMove={moveLibraryItem}
                    />
                </div>

                <section className="flex min-w-0 flex-1 flex-col bg-code-canvas">
                    {activeSnippet && activeVariation ? (
                        <>
                            <EditorTabBar
                                snippets={openSnippets}
                                activeSnippetId={activeSnippet.id}
                                dirtySnippetIds={dirtySnippetIds}
                                onActivate={openSnippet}
                                onClose={closeSnippet}
                                onToggleFavourite={toggleFavourite}
                            />
                            <div className="relative flex min-h-0 flex-1">
                                <div className="flex min-w-0 flex-1 flex-col">
                                    <SnippetEditorToolbar
                                        snippet={activeSnippet}
                                        project={activeProject}
                                        folderPath={selectedFolderPath}
                                        activeVariation={activeVariation}
                                        variations={activeSnippet.variations}
                                        mode={editorMode}
                                        dirty={activeDirty}
                                        saving={saving}
                                        sections={activeSections}
                                        activeSectionKey={
                                            activeSection?.key ?? null
                                        }
                                        copied={
                                            copiedText === activeSource ||
                                            (editorMode === 'preview' &&
                                                copiedText === renderedSource)
                                        }
                                        onModeChange={setEditorMode}
                                        onVariationSelect={selectVariation}
                                        onCreateVariation={() =>
                                            setDialog({
                                                kind: 'create-variation',
                                                snippet: activeSnippet,
                                                source: activeSource,
                                            })
                                        }
                                        onSectionSelect={selectEmbeddedSection}
                                        onCopySection={(section) =>
                                            void copyEmbeddedSnippet(
                                                {
                                                    snippet: activeSnippet,
                                                    variation: activeVariation,
                                                    presetId:
                                                        activeSelectedPreset?.id ??
                                                        null,
                                                },
                                                section,
                                            )
                                        }
                                        onSave={saveActiveSnippet}
                                        onCopyRendered={() =>
                                            void copyText(
                                                resolveTemplate(
                                                    activeSource,
                                                    activeVariableValues,
                                                ),
                                                'Rendered snippet',
                                                'rendered',
                                                'full',
                                                {
                                                    snippet: activeSnippet,
                                                    variation: activeVariation,
                                                    presetId:
                                                        activeSelectedPreset?.id ??
                                                        null,
                                                },
                                            )
                                        }
                                        onCopySource={() =>
                                            void copyText(
                                                activeSource,
                                                'Template source',
                                                'source',
                                                'full',
                                                {
                                                    snippet: activeSnippet,
                                                    variation: activeVariation,
                                                    presetId:
                                                        activeSelectedPreset?.id ??
                                                        null,
                                                },
                                            )
                                        }
                                        onSelectAll={() =>
                                            editorRef.current?.selectAll()
                                        }
                                    />
                                    <SnippetEditor
                                        ref={editorRef}
                                        value={
                                            editorMode === 'preview'
                                                ? renderedSource
                                                : activeSource
                                        }
                                        language={activeSnippet.language}
                                        preview={editorMode === 'preview'}
                                        readOnly={editorMode === 'preview'}
                                        onChange={(value) => {
                                            if (editorMode === 'preview') {
                                                return;
                                            }

                                            setVariationDrafts((current) => ({
                                                ...current,
                                                [activeVariation.id]: value,
                                            }));
                                        }}
                                        onSave={saveActiveSnippet}
                                        onCopy={(selectionLength) =>
                                            void recordCopy(
                                                {
                                                    snippet: activeSnippet,
                                                    variation: activeVariation,
                                                    presetId:
                                                        activeSelectedPreset?.id ??
                                                        null,
                                                },
                                                'keyboard',
                                                editorMode === 'preview'
                                                    ? 'rendered'
                                                    : 'source',
                                                selectionLength >=
                                                    (editorMode === 'preview'
                                                        ? renderedSource.length
                                                        : activeSource.length)
                                                    ? 'full'
                                                    : 'selection',
                                                selectionLength,
                                            )
                                        }
                                        onCursorChange={(line, column) =>
                                            setCursor({ line, column })
                                        }
                                    />
                                    <SnippetEditorStatus
                                        language={activeSnippet.language}
                                        activeVariation={activeVariation}
                                        activeSection={activeSection}
                                        dirty={activeDirty}
                                        line={cursor.line}
                                        column={cursor.column}
                                        variableCount={activeVariables.length}
                                    />
                                </div>

                                {inspectorOpen && (
                                    <>
                                        <button
                                            type="button"
                                            aria-label="Close snippet details"
                                            onClick={() =>
                                                setInspectorOpen(false)
                                            }
                                            className="absolute inset-0 z-20 bg-black/35 xl:hidden"
                                        />
                                        <div className="absolute inset-y-0 right-0 z-30 flex min-h-0 shadow-2xl xl:static xl:z-auto xl:shadow-none">
                                            <SnippetInspector
                                                snippet={activeSnippet}
                                                activeVariation={
                                                    activeVariation
                                                }
                                                variables={
                                                    activeVariables as InspectorVariable[]
                                                }
                                                variableValues={
                                                    activeVariableValues
                                                }
                                                selectedPresetId={
                                                    selectedPresetIds[
                                                        activeSnippet.id
                                                    ] ?? null
                                                }
                                                sections={activeSections}
                                                activeSectionKey={
                                                    activeSection?.key ?? null
                                                }
                                                onVariableChange={(
                                                    name,
                                                    value,
                                                ) =>
                                                    setVariableOverrides(
                                                        (current) => ({
                                                            ...current,
                                                            [activeSnippet.id]:
                                                                {
                                                                    ...(current[
                                                                        activeSnippet
                                                                            .id
                                                                    ] ?? {}),
                                                                    [name]: value,
                                                                },
                                                        }),
                                                    )
                                                }
                                                onPresetSelect={selectPreset}
                                                onCreatePreset={() =>
                                                    setDialog({
                                                        kind: 'create-preset',
                                                        snippet: activeSnippet,
                                                    })
                                                }
                                                onUpdatePreset={
                                                    updateSelectedPreset
                                                }
                                                onDeletePreset={
                                                    deleteSelectedPreset
                                                }
                                                onVariationSelect={
                                                    selectVariation
                                                }
                                                onCreateVariation={() =>
                                                    setDialog({
                                                        kind: 'create-variation',
                                                        snippet: activeSnippet,
                                                        source: activeSource,
                                                    })
                                                }
                                                onRenameVariation={(
                                                    variation,
                                                ) =>
                                                    setDialog({
                                                        kind: 'rename-variation',
                                                        snippet: activeSnippet,
                                                        variation,
                                                    })
                                                }
                                                onMakeDefaultVariation={
                                                    setDefaultVariation
                                                }
                                                onDeleteVariation={
                                                    deleteSelectedVariation
                                                }
                                                onSectionSelect={
                                                    selectEmbeddedSection
                                                }
                                                onCopySection={(section) =>
                                                    void copyEmbeddedSnippet(
                                                        {
                                                            snippet:
                                                                activeSnippet,
                                                            variation:
                                                                activeVariation,
                                                            presetId:
                                                                activeSelectedPreset?.id ??
                                                                null,
                                                        },
                                                        section,
                                                    )
                                                }
                                                onEditMetadata={() =>
                                                    setDialog({
                                                        kind: 'metadata',
                                                        snippet: activeSnippet,
                                                    })
                                                }
                                            />
                                        </div>
                                    </>
                                )}
                            </div>
                        </>
                    ) : (
                        <WorkspaceHero
                            projects={projects}
                            query={query}
                            suggestions={suggestions}
                            results={searchResults}
                            inputRef={searchInputRef}
                            onQueryChange={setQuery}
                            onSuggestionAccept={acceptSuggestion}
                            onOpen={openSearchResult}
                            onCopySection={copySearchSection}
                            onNewProject={() => {
                                setCreateSnippetAfterWorkspace(false);
                                setDialog({ kind: 'create-project' });
                            }}
                            onNewSnippet={beginCreateSnippet}
                        />
                    )}
                </section>
            </div>

            <WorkspaceDialog
                state={dialog}
                projects={projects}
                languageOptions={languageOptions}
                frameworks={frameworks}
                processing={dialogProcessing}
                errors={dialogErrors}
                onClose={() => {
                    if (dialog?.kind === 'create-project') {
                        setCreateSnippetAfterWorkspace(false);
                    }

                    setDialog(null);
                    setDialogErrors({});
                }}
                onSubmit={submitDialog}
            />
        </>
    );
}

type WorkspaceHeroProps = {
    projects: Project[];
    query: string;
    suggestions: string[];
    results: SnippetSearchResult[];
    inputRef: React.RefObject<HTMLInputElement | null>;
    onQueryChange: (query: string) => void;
    onSuggestionAccept: (suggestion: string) => void;
    onOpen: (result: SnippetSearchResult) => void;
    onCopySection: (result: SnippetSectionSearchResult) => void;
    onNewProject: () => void;
    onNewSnippet: () => void;
};

function WorkspaceHero({
    projects,
    query,
    suggestions,
    results,
    inputRef,
    onQueryChange,
    onSuggestionAccept,
    onOpen,
    onCopySection,
    onNewProject,
    onNewSnippet,
}: WorkspaceHeroProps) {
    return (
        <div className="relative flex min-h-0 flex-1 items-center justify-center overflow-hidden px-5 pb-20">
            <div className="pointer-events-none absolute inset-0 [background-image:radial-gradient(circle_at_center,rgba(99,163,207,0.1),transparent_44%)] opacity-60" />
            <div className="relative flex w-full max-w-3xl flex-col items-center">
                <div className="mb-6 flex size-11 items-center justify-center rounded-xl border border-code-accent/25 bg-code-raised text-code-accent shadow-[0_18px_45px_rgba(42,103,143,0.2)]">
                    <Braces className="size-5" strokeWidth={2} />
                </div>
                <p className="mb-2 text-[10px] font-semibold tracking-[0.24em] text-code-faint uppercase">
                    CodePilot
                </p>
                <h2 className="mb-7 text-center text-xl font-medium tracking-[-0.02em] text-code-text">
                    Find the code you already solved.
                </h2>
                <SnippetSearch
                    query={query}
                    suggestions={suggestions}
                    results={results}
                    inputRef={inputRef}
                    variant="hero"
                    onQueryChange={onQueryChange}
                    onSuggestionAccept={onSuggestionAccept}
                    onOpen={onOpen}
                    onCopySection={onCopySection}
                />

                <div className="mt-6 flex flex-wrap items-center justify-center gap-2 text-[10px] text-code-faint">
                    <button
                        type="button"
                        onClick={onNewSnippet}
                        className="flex h-8 items-center gap-1.5 rounded-md border border-code-border bg-code-panel px-3 transition hover:bg-code-hover hover:text-code-text"
                    >
                        <FilePlus2 className="size-3" /> New snippet
                    </button>
                    <button
                        type="button"
                        onClick={onNewProject}
                        className="flex h-8 items-center gap-1.5 rounded-md px-3 transition hover:bg-code-hover hover:text-code-text"
                    >
                        {projects.length === 0 ? (
                            <Plus className="size-3" />
                        ) : (
                            <FolderPlus className="size-3" />
                        )}
                        {projects.length === 0
                            ? 'Create first workspace'
                            : 'New workspace'}
                    </button>
                </div>

                <div className="mt-10 hidden items-center gap-5 font-mono text-[9px] text-code-faint sm:flex">
                    <span className="flex items-center gap-1.5">
                        <Command className="size-3" /> K to search
                    </span>
                    <span>Tab to autocomplete</span>
                    <span>! excludes</span>
                    <span>== exact match</span>
                </div>
            </div>
        </div>
    );
}

type VisitCallbacks = {
    preserveScroll: boolean;
    onSuccess: (page: Page) => void;
    onError: (errors: Record<string, string>) => void;
    onFinish: () => void;
};

function buildSnippetPayload(snippet: Snippet) {
    return {
        title: snippet.title,
        filename: snippet.filename,
        language: snippet.language,
        description: snippet.description,
        tags: snippet.tags.map((tag) => tag.name),
        frameworks: snippet.frameworks.map((framework) => framework.name),
    };
}

function getFolderPath(project: Project, folderId: number | null): string[] {
    if (folderId === null) {
        return [];
    }

    const folders = new Map(
        project.folders.map((folder) => [folder.id, folder]),
    );
    const path: string[] = [];
    const visited = new Set<number>();
    let currentId: number | null = folderId;

    while (currentId !== null && !visited.has(currentId)) {
        const folder = folders.get(currentId);

        if (!folder) {
            break;
        }

        visited.add(currentId);
        path.unshift(folder.name);
        currentId = folder.parent_id;
    }

    return path;
}

function collectDescendantFolderIds(
    project: Project,
    rootFolderId: number,
): Set<number> {
    const folderIds = new Set([rootFolderId]);
    let foundDescendant = true;

    while (foundDescendant) {
        foundDescendant = false;

        for (const folder of project.folders) {
            if (
                folder.parent_id !== null &&
                folderIds.has(folder.parent_id) &&
                !folderIds.has(folder.id)
            ) {
                folderIds.add(folder.id);
                foundDescendant = true;
            }
        }
    }

    return folderIds;
}

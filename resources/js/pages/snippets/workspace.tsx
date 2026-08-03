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
import ClipboardActivationController from '@/actions/App/Http/Controllers/ClipboardActivationController';
import ClipboardClearController from '@/actions/App/Http/Controllers/ClipboardClearController';
import {
    destroy as destroyClipboardClip,
    store as storeClipboardClip,
} from '@/actions/App/Http/Controllers/ClipboardClipController';
import ClipboardFileController from '@/actions/App/Http/Controllers/ClipboardFileController';
import {
    destroy as destroyClipboardSession,
    store as storeClipboardSession,
    update as updateClipboardSession,
} from '@/actions/App/Http/Controllers/ClipboardSessionController';
import {
    destroy as destroyFolder,
    forceDestroy as forceDestroyFolder,
    restore as restoreFolder,
    store as storeFolder,
    update as updateFolder,
} from '@/actions/App/Http/Controllers/FolderController';
import { store as storeFramework } from '@/actions/App/Http/Controllers/FrameworkController';
import {
    destroy as destroyLibraryCategory,
    store as storeLibraryCategory,
    update as updateLibraryCategory,
} from '@/actions/App/Http/Controllers/LibraryCategoryController';
import MoveFolderController from '@/actions/App/Http/Controllers/MoveFolderController';
import MoveSnippetController from '@/actions/App/Http/Controllers/MoveSnippetController';
import PinController from '@/actions/App/Http/Controllers/PinController';
import {
    destroy as destroyProject,
    forceDestroy as forceDestroyProject,
    reorder as reorderProjects,
    restore as restoreProject,
    store as storeProject,
    update as updateProject,
} from '@/actions/App/Http/Controllers/ProjectController';
import {
    destroy as destroySnippet,
    forceDestroy as forceDestroySnippet,
    restore as restoreSnippet,
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
import { ClipboardPanel } from '@/components/snippets/clipboard-panel';
import { EditorTabBar } from '@/components/snippets/editor-tab-bar';
import { FrameworkDialog } from '@/components/snippets/framework-dialog';
import { GuidePlayback } from '@/components/snippets/guide-playback';
import { LibraryCategoryDialog } from '@/components/snippets/library-category-dialog';
import type { LibraryCategoryDialogState } from '@/components/snippets/library-category-dialog';
import { libraryPinKey } from '@/components/snippets/project-explorer';
import type {
    ExplorerDragItem,
    ExplorerDropTarget,
    ExplorerEntity,
    InlineRenameCallbacks,
    LibraryPinTarget,
} from '@/components/snippets/project-explorer';
import { SecondBrain } from '@/components/snippets/second-brain';
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
import { WorkspaceMegaSearch } from '@/components/snippets/workspace-mega-search';
import { WorkspaceResizeHandle } from '@/components/snippets/workspace-resize-handle';
import { WorkspaceSidePanel } from '@/components/snippets/workspace-side-panel';
import { useClipboard } from '@/hooks/use-clipboard';
import { createClipboardSelection } from '@/lib/snippets/clipboard-selection';
import type { ClipboardSelection } from '@/lib/snippets/clipboard-selection';
import {
    defaultEditorModePreferences,
    editorModePreferenceScope,
    restoreEditorModePreferences,
    updateEditorModePreference,
} from '@/lib/snippets/editor-mode-preferences';
import type { EditorModePreferences } from '@/lib/snippets/editor-mode-preferences';
import {
    editorOnlyModeShortcutLabel,
    isEditorOnlyModeShortcut,
} from '@/lib/snippets/editor-only-mode';
import { parseGuideSteps } from '@/lib/snippets/guide-steps';
import {
    hasActiveMegaSearchFilters,
    matchesMegaSearchFilters,
} from '@/lib/snippets/mega-search-filters';
import { rankMegaSearchCandidates } from '@/lib/snippets/mega-search-ranking';
import {
    applySearchSuggestion,
    defaultSnippetExcerptMode,
    getSearchSuggestions,
    searchFolders,
    searchProjects,
    searchSnippetSections,
    searchSnippetMatches,
    snippetMatchesWorkspaceSearchEntity,
} from '@/lib/snippets/search-query';
import type {
    SnippetExcerptMode,
    SnippetSearchScope,
    WorkspaceSearchEntity,
} from '@/lib/snippets/search-query';
import { parseSnippetSections } from '@/lib/snippets/snippet-sections';
import type { ParsedSnippetSection } from '@/lib/snippets/snippet-sections';
import { readClipboardText } from '@/lib/snippets/system-clipboard-paste';
import {
    parseTemplateVariables,
    resolveTemplate,
} from '@/lib/snippets/template-variables';
import {
    clampWorkspacePanelWidth,
    restoreWorkspacePanelWidth,
    workspacePanelMaximumWidth,
} from '@/lib/snippets/workspace-panel-resize';
import {
    closeUnpinnedWorkspaceTabs,
    closeWorkspaceTabs,
    openWorkspaceSnippet,
    reorderWorkspaceTabs,
    restoreMultiFileMode,
    restoreWorkspaceTabs,
    restrictWorkspaceTabsToSingleFile,
    togglePinnedSnippet,
} from '@/lib/snippets/workspace-tabs';
import type { WorkspaceTabDropPosition } from '@/lib/snippets/workspace-tabs';
import { cn } from '@/lib/utils';
import type {
    ClipboardSession,
    LibraryCategory,
    Project,
    LibraryTrashItem,
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

type EditorViewPreferenceStorage = {
    storageKey: string | null;
    preferences: EditorModePreferences;
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

type WorkspaceView = 'editor' | 'brain';

const libraryPanelDefaultWidth = 304;
const libraryPanelMinWidth = 240;
const libraryPanelMaxWidth = 440;
const inspectorPanelDefaultWidth = 320;
const inspectorPanelMinWidth = 260;
const inspectorPanelMaxWidth = 440;
const workspaceActivityBarWidth = 48;
const workspaceCenterMinimumWidth = 560;
const workspaceLibraryDockedBreakpoint = 1024;
const workspaceInspectorDockedBreakpoint = 1280;

export default function Workspace({
    library_categories: libraryCategories,
    projects,
    standalone_snippets: standaloneSnippets,
    language_options: languageOptions,
    tags,
    frameworks,
    pins,
    trash,
    clipboard_sessions: clipboardSessions,
    auth,
}: Props) {
    const sidebarSearchInputRef = useRef<HTMLInputElement>(null);
    const heroSearchInputRef = useRef<HTMLInputElement>(null);
    const megaSearchInputRef = useRef<HTMLInputElement>(null);
    const megaSearchReturnFocusRef = useRef<HTMLElement | null>(null);
    const editorRef = useRef<SnippetEditorHandle>(null);
    const [workspaceView, setWorkspaceView] = useState<WorkspaceView>('editor');
    const [activePanel, setActivePanel] = useState<WorkspacePanel>('explorer');
    const [mobilePanelOpen, setMobilePanelOpen] = useState(false);
    const [inspectorOpen, setInspectorOpen] = useState(false);
    const [editorOnlyMode, setEditorOnlyMode] = useState(false);
    const [multiFileMode, setMultiFileMode] = useState(true);
    const [editorOnlyModeShortcut, setEditorOnlyModeShortcut] =
        useState('Ctrl+Shift+E');
    const [query, setQuery] = useState('');
    const [searchEntity, setSearchEntity] =
        useState<WorkspaceSearchEntity>('all');
    const [searchScope, setSearchScope] = useState<SnippetSearchScope>('all');
    const [searchFrameworkId, setSearchFrameworkId] = useState<number | null>(
        null,
    );
    const [searchExcerptMode, setSearchExcerptMode] =
        useState<SnippetExcerptMode>(defaultSnippetExcerptMode);
    const [searchFocusRequest, setSearchFocusRequest] = useState(0);
    const [megaSearchOpen, setMegaSearchOpen] = useState(false);
    const [megaSearchQuery, setMegaSearchQuery] = useState('');
    const [megaSearchCaretPosition, setMegaSearchCaretPosition] = useState(0);
    const [megaSearchLanguage, setMegaSearchLanguage] = useState<string | null>(
        null,
    );
    const [megaSearchLibraryCategoryId, setMegaSearchLibraryCategoryId] =
        useState<number | null>(null);
    const [megaSearchFrameworkId, setMegaSearchFrameworkId] = useState<
        number | null
    >(null);
    const [megaSearchIncludesCode, setMegaSearchIncludesCode] = useState(true);
    const [workspaceViewportWidth, setWorkspaceViewportWidth] = useState<
        number | null
    >(null);
    const [libraryPanelWidth, setLibraryPanelWidth] = useState(
        libraryPanelDefaultWidth,
    );
    const [inspectorPanelWidth, setInspectorPanelWidth] = useState(
        inspectorPanelDefaultWidth,
    );
    const [openIds, setOpenIds] = useState<number[]>([]);
    const [pinnedIds, setPinnedIds] = useState<number[]>([]);
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
    const [editorViewPreferenceStorage, setEditorViewPreferenceStorage] =
        useState<EditorViewPreferenceStorage>({
            storageKey: null,
            preferences: defaultEditorModePreferences,
        });
    const [cursor, setCursor] = useState({ line: 1, column: 1 });
    const [saving, setSaving] = useState(false);
    const [clipboardProcessing, setClipboardProcessing] = useState(false);
    const [dialog, setDialog] = useState<WorkspaceDialogState>(null);
    const [libraryCategoryDialog, setLibraryCategoryDialog] =
        useState<LibraryCategoryDialogState>(null);
    const [libraryCategoryProcessing, setLibraryCategoryProcessing] =
        useState(false);
    const [libraryCategoryErrors, setLibraryCategoryErrors] = useState<
        Record<string, string>
    >({});
    const [frameworkDialogOpen, setFrameworkDialogOpen] = useState(false);
    const [frameworkProcessing, setFrameworkProcessing] = useState(false);
    const [frameworkErrors, setFrameworkErrors] = useState<
        Record<string, string>
    >({});
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
    const editorViewPreferencesStorageKey = `codepilot.workspace.editor-view-preferences.v1.${auth.user.id}`;
    const libraryPanelWidthStorageKey = `codepilot.workspace.library-panel-width.v1.${auth.user.id}`;
    const inspectorPanelWidthStorageKey = `codepilot.workspace.inspector-panel-width.v1.${auth.user.id}`;

    const isLibraryPanelDocked =
        workspaceViewportWidth !== null &&
        workspaceViewportWidth >= workspaceLibraryDockedBreakpoint;
    const isInspectorPanelDocked =
        workspaceViewportWidth !== null &&
        workspaceViewportWidth >= workspaceInspectorDockedBreakpoint &&
        inspectorOpen;
    const inspectorPanelEffectiveMaxWidth = workspacePanelMaximumWidth(
        workspaceViewportWidth,
        workspaceActivityBarWidth +
            (isInspectorPanelDocked
                ? clampWorkspacePanelWidth(
                      libraryPanelWidth,
                      libraryPanelMinWidth,
                      libraryPanelMaxWidth,
                  )
                : 0),
        isInspectorPanelDocked ? workspaceCenterMinimumWidth : 0,
        inspectorPanelMinWidth,
        inspectorPanelMaxWidth,
    );
    const displayedInspectorPanelWidth = clampWorkspacePanelWidth(
        inspectorPanelWidth,
        inspectorPanelMinWidth,
        inspectorPanelEffectiveMaxWidth,
    );
    const libraryPanelEffectiveMaxWidth = workspacePanelMaximumWidth(
        workspaceViewportWidth,
        workspaceActivityBarWidth +
            (isInspectorPanelDocked ? displayedInspectorPanelWidth : 0),
        isLibraryPanelDocked ? workspaceCenterMinimumWidth : 0,
        libraryPanelMinWidth,
        libraryPanelMaxWidth,
    );
    const displayedLibraryPanelWidth = clampWorkspacePanelWidth(
        libraryPanelWidth,
        libraryPanelMinWidth,
        libraryPanelEffectiveMaxWidth,
    );

    useEffect(() => {
        const updateViewportWidth = () => {
            setWorkspaceViewportWidth(window.innerWidth);
        };

        updateViewportWidth();
        window.addEventListener('resize', updateViewportWidth);

        return () => window.removeEventListener('resize', updateViewportWidth);
    }, []);

    useEffect(() => {
        const frame = window.requestAnimationFrame(() => {
            setEditorOnlyModeShortcut(
                editorOnlyModeShortcutLabel(window.navigator.platform),
            );

            if (window.matchMedia('(min-width: 1280px)').matches) {
                setInspectorOpen(true);
            }
        });

        return () => window.cancelAnimationFrame(frame);
    }, []);

    useEffect(() => {
        const frame = window.requestAnimationFrame(() => {
            let storedLibraryWidth: string | null = null;
            let storedInspectorWidth: string | null = null;

            try {
                storedLibraryWidth = window.localStorage.getItem(
                    libraryPanelWidthStorageKey,
                );
                storedInspectorWidth = window.localStorage.getItem(
                    inspectorPanelWidthStorageKey,
                );
            } catch {
                storedLibraryWidth = null;
                storedInspectorWidth = null;
            }

            setLibraryPanelWidth(
                restoreWorkspacePanelWidth(
                    storedLibraryWidth,
                    libraryPanelDefaultWidth,
                    libraryPanelMinWidth,
                    libraryPanelMaxWidth,
                ),
            );
            setInspectorPanelWidth(
                restoreWorkspacePanelWidth(
                    storedInspectorWidth,
                    inspectorPanelDefaultWidth,
                    inspectorPanelMinWidth,
                    inspectorPanelMaxWidth,
                ),
            );
        });

        return () => window.cancelAnimationFrame(frame);
    }, [inspectorPanelWidthStorageKey, libraryPanelWidthStorageKey]);

    const persistPanelWidth = useCallback(
        (storageKey: string, width: number) => {
            try {
                window.localStorage.setItem(storageKey, String(width));
            } catch {
                return;
            }
        },
        [],
    );
    const openMegaSearch = useCallback(() => {
        if (megaSearchOpen) {
            megaSearchInputRef.current?.focus({ preventScroll: true });

            return;
        }

        megaSearchReturnFocusRef.current =
            document.activeElement instanceof HTMLElement
                ? document.activeElement
                : null;
        setMegaSearchQuery('');
        setMegaSearchCaretPosition(0);
        setMegaSearchOpen(true);
    }, [megaSearchOpen]);
    const closeMegaSearch = useCallback((restoreFocus = true) => {
        const returnFocusTarget = megaSearchReturnFocusRef.current;

        megaSearchReturnFocusRef.current = null;
        setMegaSearchOpen(false);
        setMegaSearchQuery('');
        setMegaSearchCaretPosition(0);

        if (restoreFocus) {
            window.requestAnimationFrame(() => {
                if (returnFocusTarget?.isConnected) {
                    returnFocusTarget.focus({ preventScroll: true });

                    return;
                }

                editorRef.current?.focus();
            });
        }
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
    const activeClipboardSession =
        clipboardSessions.find((clipboard) => clipboard.is_active) ?? null;
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
    const pinnedSnippetIds = useMemo(() => new Set(pinnedIds), [pinnedIds]);
    const activeSnippet = activeSnippetId
        ? (snippetById.get(activeSnippetId) ?? null)
        : null;
    const activeProject = activeSnippet
        ? activeSnippet.project_id === null
            ? null
            : (projectById.get(activeSnippet.project_id) ?? null)
        : null;
    const activeEditorViewPreferenceScope = activeSnippet
        ? editorModePreferenceScope(activeSnippet, activeProject)
        : null;
    const editorViewPreferences =
        editorViewPreferenceStorage.storageKey ===
        editorViewPreferencesStorageKey
            ? editorViewPreferenceStorage.preferences
            : defaultEditorModePreferences;
    const editorMode = activeEditorViewPreferenceScope
        ? editorViewPreferences[activeEditorViewPreferenceScope]
        : 'source';
    const effectiveEditorMode =
        editorMode === 'playback' && activeSnippet?.content_type !== 'guide'
            ? 'source'
            : editorMode;
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
            effectiveEditorMode === 'preview'
                ? resolveTemplate(activeSource, activeVariableValues)
                : '',
        [activeSource, activeVariableValues, effectiveEditorMode],
    );
    const guidePlaybackSource = useMemo(
        () => resolveTemplate(activeSource, activeVariableValues),
        [activeSource, activeVariableValues],
    );
    const activeGuideSteps = useMemo(
        () => parseGuideSteps(guidePlaybackSource),
        [guidePlaybackSource],
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
                        guide_steps: parseGuideSteps(content),
                    };
                }),
            })),
        [allSnippets, variationDrafts],
    );

    const hasTextQuery = query.trim().length > 0;
    const isSearchFiltering =
        hasTextQuery || searchEntity !== 'all' || searchFrameworkId !== null;
    const selectedFrameworkProjectIds = useMemo(
        () =>
            new Set(
                searchFrameworkId === null
                    ? []
                    : projects
                          .filter((project) =>
                              project.frameworks.some(
                                  (framework) =>
                                      framework.id === searchFrameworkId,
                              ),
                          )
                          .map((project) => project.id),
            ),
        [projects, searchFrameworkId],
    );
    const rawSnippetSearchMatches = useMemo(
        () =>
            searchSnippetMatches(searchableSnippets, query, {
                libraryCategories,
                projects,
                scope: searchScope,
            }),
        [libraryCategories, projects, query, searchableSnippets, searchScope],
    );
    const snippetSearchMatches = useMemo(
        () =>
            rawSnippetSearchMatches.filter((match) => {
                if (
                    !snippetMatchesWorkspaceSearchEntity(
                        match.snippet,
                        searchEntity,
                    )
                ) {
                    return false;
                }

                if (searchFrameworkId === null) {
                    return true;
                }

                return (
                    match.snippet.frameworks.some(
                        (framework) => framework.id === searchFrameworkId,
                    ) ||
                    (match.snippet.project_id !== null &&
                        selectedFrameworkProjectIds.has(
                            match.snippet.project_id,
                        ))
                );
            }),
        [
            rawSnippetSearchMatches,
            searchEntity,
            searchFrameworkId,
            selectedFrameworkProjectIds,
        ],
    );
    const filteredSnippets = useMemo(
        () => snippetSearchMatches.map((match) => match.snippet),
        [snippetSearchMatches],
    );
    const visibleSnippets = isSearchFiltering ? filteredSnippets : allSnippets;
    const filteredProjects = useMemo(() => {
        if (searchEntity === 'snippets' || searchEntity === 'guides') {
            return [];
        }

        const matchingProjectIds = new Set(
            searchProjects(projects, query, {
                libraryCategories,
                scope: searchScope,
            }).map((project) => project.id),
        );

        if (searchEntity === 'projects' && hasTextQuery) {
            rawSnippetSearchMatches.forEach((match) => {
                if (match.snippet.project_id !== null) {
                    matchingProjectIds.add(match.snippet.project_id);
                }
            });

            searchFolders(projects, query, {
                libraryCategories,
                scope: searchScope,
            }).forEach((result) => matchingProjectIds.add(result.project.id));
        }

        return projects.filter(
            (project) =>
                matchingProjectIds.has(project.id) &&
                (searchFrameworkId === null ||
                    selectedFrameworkProjectIds.has(project.id)),
        );
    }, [
        hasTextQuery,
        libraryCategories,
        projects,
        query,
        rawSnippetSearchMatches,
        searchEntity,
        searchFrameworkId,
        searchScope,
        selectedFrameworkProjectIds,
    ]);
    const filteredFolders = useMemo(() => {
        if (searchEntity !== 'all' || !hasTextQuery) {
            return [];
        }

        return searchFolders(projects, query, {
            libraryCategories,
            scope: searchScope,
        }).filter(
            (result) =>
                searchFrameworkId === null ||
                selectedFrameworkProjectIds.has(result.project.id),
        );
    }, [
        hasTextQuery,
        libraryCategories,
        projects,
        query,
        searchEntity,
        searchFrameworkId,
        searchScope,
        selectedFrameworkProjectIds,
    ]);
    const filteredSections = useMemo(
        () =>
            hasTextQuery && searchEntity !== 'projects'
                ? searchSnippetSections(searchableSnippets, query, {
                      libraryCategories,
                      projects,
                      scope: searchScope,
                  }).filter((result) =>
                      snippetSearchMatches.some(
                          (match) => match.snippet.id === result.snippet.id,
                      ),
                  )
                : [],
        [
            hasTextQuery,
            libraryCategories,
            projects,
            query,
            searchEntity,
            searchableSnippets,
            searchScope,
            snippetSearchMatches,
        ],
    );
    const matchedProjectIds = useMemo(
        () => new Set(filteredProjects.map((project) => project.id)),
        [filteredProjects],
    );
    const matchedFolderIds = useMemo(
        () => new Set(filteredFolders.map((result) => result.folder.id)),
        [filteredFolders],
    );
    const searchCodeMatches = useMemo(
        () =>
            new Map(
                snippetSearchMatches.flatMap((match) =>
                    match.excerpt
                        ? ([[match.snippet.id, match.excerpt]] as const)
                        : [],
                ),
            ),
        [snippetSearchMatches],
    );
    const searchVariationIds = useMemo(
        () =>
            new Map(
                isSearchFiltering
                    ? snippetSearchMatches.flatMap((match) =>
                          match.variation
                              ? ([
                                    [match.snippet.id, match.variation.id],
                                ] as const)
                              : [],
                      )
                    : [],
            ),
        [isSearchFiltering, snippetSearchMatches],
    );
    const searchResultCount =
        searchEntity === 'projects'
            ? filteredProjects.length
            : searchEntity === 'snippets' || searchEntity === 'guides'
              ? filteredSnippets.length
              : filteredProjects.length +
                filteredFolders.length +
                filteredSnippets.length;
    const suggestions = useMemo(
        () =>
            getSearchSuggestions(query, {
                languages: languageOptions,
                frameworks,
                tags,
                projects,
                folders: projects.flatMap((project) => project.folders),
                libraryCategories,
                titles: searchableSnippets.map((snippet) => snippet.title),
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
            libraryCategories,
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
            ...snippetSearchMatches
                .filter((match) => !sectionSnippetIds.has(match.snippet.id))
                .slice(0, 40)
                .map((match) => {
                    const snippet = match.snippet;
                    const project =
                        snippet.project_id === null
                            ? null
                            : projectById.get(snippet.project_id);
                    const folderPath = project
                        ? getFolderPath(project, snippet.folder_id)
                        : [];

                    return {
                        kind: 'snippet' as const,
                        snippet,
                        projectName: project?.name ?? 'Standalone',
                        path: [...folderPath, snippet.filename].join(' / '),
                        variationId: match.variation?.id ?? null,
                        variationName: match.variation?.name ?? null,
                        excerpt: match.excerpt,
                    };
                }),
        ];
    }, [
        filteredFolders,
        filteredProjects,
        filteredSections,
        projectById,
        snippetSearchMatches,
    ]);
    const megaSearchableSnippets = useMemo(
        () =>
            searchableSnippets.filter((snippet) =>
                matchesMegaSearchFilters(
                    snippet,
                    snippet.project_id === null
                        ? null
                        : (projectById.get(snippet.project_id) ?? null),
                    {
                        language: megaSearchLanguage,
                        libraryCategoryId: megaSearchLibraryCategoryId,
                        frameworkId: megaSearchFrameworkId,
                    },
                ),
            ),
        [
            megaSearchFrameworkId,
            megaSearchLanguage,
            megaSearchLibraryCategoryId,
            projectById,
            searchableSnippets,
        ],
    );
    const hasMegaSearchTaxonomyFilters = hasActiveMegaSearchFilters({
        language: megaSearchLanguage,
        libraryCategoryId: megaSearchLibraryCategoryId,
        frameworkId: megaSearchFrameworkId,
    });
    const megaSearchMatches = useMemo(
        () =>
            megaSearchQuery.trim() === '' && !hasMegaSearchTaxonomyFilters
                ? []
                : searchSnippetMatches(
                      megaSearchableSnippets,
                      megaSearchQuery,
                      {
                          includeCode: megaSearchIncludesCode,
                          libraryCategories,
                          projects,
                          scope: 'all',
                      },
                  ),
        [
            hasMegaSearchTaxonomyFilters,
            libraryCategories,
            megaSearchIncludesCode,
            megaSearchQuery,
            megaSearchableSnippets,
            projects,
        ],
    );
    const megaSearchSections = useMemo(() => {
        if (megaSearchQuery.trim() === '') {
            return [];
        }

        const matchingSnippetIds = new Set(
            megaSearchMatches.map((match) => match.snippet.id),
        );

        return searchSnippetSections(megaSearchableSnippets, megaSearchQuery, {
            includeCode: megaSearchIncludesCode,
            libraryCategories,
            projects,
            scope: 'all',
        }).filter((result) => matchingSnippetIds.has(result.snippet.id));
    }, [
        libraryCategories,
        megaSearchIncludesCode,
        megaSearchMatches,
        megaSearchQuery,
        megaSearchableSnippets,
        projects,
    ]);
    const megaSearchSuggestions = useMemo(
        () =>
            getSearchSuggestions(
                megaSearchQuery,
                {
                    languages: languageOptions,
                    frameworks,
                    tags,
                    projects,
                    folders: projects.flatMap((project) => project.folders),
                    libraryCategories,
                    titles: searchableSnippets.map((snippet) => snippet.title),
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
                },
                megaSearchCaretPosition,
            ),
        [
            allSnippets,
            frameworks,
            languageOptions,
            libraryCategories,
            megaSearchCaretPosition,
            megaSearchQuery,
            projects,
            searchableSnippets,
            tags,
        ],
    );
    const megaSearchResults = useMemo<{
        items: SnippetSearchResult[];
        total: number;
    }>(() => {
        const sectionResults = megaSearchSections.map((result) => {
            const project =
                result.snippet.project_id === null
                    ? null
                    : projectById.get(result.snippet.project_id);
            const folderPath = project
                ? getFolderPath(project, result.snippet.folder_id)
                : [];

            return {
                item: {
                    kind: 'section' as const,
                    ...result,
                    projectName: project?.name ?? 'Standalone',
                    path: [...folderPath, result.snippet.filename].join(' / '),
                },
                snippetId: result.snippet.id,
                kind: 'section' as const,
                score: result.score,
                usageScore: result.snippet.usage.relative_score,
                title: result.snippet.title,
            };
        });
        const snippetResults = megaSearchMatches.map((match) => {
            const snippet = match.snippet;
            const project =
                snippet.project_id === null
                    ? null
                    : projectById.get(snippet.project_id);
            const folderPath = project
                ? getFolderPath(project, snippet.folder_id)
                : [];

            return {
                item: {
                    kind: 'snippet' as const,
                    snippet,
                    projectName: project?.name ?? 'Standalone',
                    path: [...folderPath, snippet.filename].join(' / '),
                    variationId: match.variation?.id ?? null,
                    variationName: match.variation?.name ?? null,
                    excerpt: match.excerpt,
                },
                snippetId: snippet.id,
                kind: 'snippet' as const,
                score: match.score,
                usageScore: snippet.usage.relative_score,
                title: snippet.title,
            };
        });
        const results = rankMegaSearchCandidates<SnippetSearchResult>([
            ...sectionResults,
            ...snippetResults,
        ]);

        return {
            items: results.slice(0, 80),
            total: results.length,
        };
    }, [megaSearchMatches, megaSearchSections, projectById]);

    const changeEditorMode = useCallback(
        (mode: EditorMode, snippet: Snippet | null = activeSnippet) => {
            if (!snippet) {
                return;
            }

            const project = snippet.project_id
                ? (projectById.get(snippet.project_id) ?? null)
                : null;

            setEditorViewPreferenceStorage((current) => {
                const preferences =
                    current.storageKey === editorViewPreferencesStorageKey
                        ? current.preferences
                        : defaultEditorModePreferences;

                return {
                    storageKey: editorViewPreferencesStorageKey,
                    preferences: updateEditorModePreference(
                        preferences,
                        snippet,
                        project,
                        mode,
                    ),
                };
            });
        },
        [activeSnippet, editorViewPreferencesStorageKey, projectById],
    );

    const openSnippet = useCallback(
        (snippet: Snippet): boolean => {
            const nextOpenIds = openWorkspaceSnippet(
                openIds,
                pinnedIds,
                snippet.id,
                multiFileMode,
            );
            const snippetsToClose = openIds
                .filter((snippetId) => !nextOpenIds.includes(snippetId))
                .map((snippetId) => snippetById.get(snippetId))
                .filter(
                    (candidate): candidate is Snippet =>
                        candidate !== undefined,
                );
            const dirtySnippetsToClose = snippetsToClose.filter((candidate) =>
                dirtySnippetIds.has(candidate.id),
            );

            if (
                dirtySnippetsToClose.length > 0 &&
                !window.confirm(
                    dirtySnippetsToClose.length === 1
                        ? `Discard unsaved changes to ${dirtySnippetsToClose[0].filename}?`
                        : `Discard unsaved changes in ${dirtySnippetsToClose.length} files?`,
                )
            ) {
                return false;
            }

            if (snippetsToClose.length > 0) {
                const variationIds = new Set(
                    snippetsToClose.flatMap((candidate) =>
                        candidate.variations.map((variation) => variation.id),
                    ),
                );

                setVariationDrafts((current) => {
                    const next = { ...current };

                    variationIds.forEach(
                        (variationId) => delete next[variationId],
                    );

                    return next;
                });
            }

            setOpenIds(nextOpenIds);
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
                    : {
                          ...current,
                          [snippet.id]: defaultVariation?.id ?? null,
                      };
            });
            setActiveSnippetId(snippet.id);
            setWorkspaceView('editor');
            setMobilePanelOpen(false);
            setCursor({ line: 1, column: 1 });

            return true;
        },
        [dirtySnippetIds, multiFileMode, openIds, pinnedIds, snippetById],
    );

    const openExplorerSnippet = useCallback(
        (snippet: Snippet) => {
            if (!openSnippet(snippet)) {
                return;
            }

            const matchingVariationId = searchVariationIds.get(snippet.id);

            if (matchingVariationId === undefined) {
                return;
            }

            setSelectedVariationIds((current) => ({
                ...current,
                [snippet.id]: matchingVariationId,
            }));
            changeEditorMode('source', snippet);
        },
        [changeEditorMode, openSnippet, searchVariationIds],
    );

    const toggleEditorOnlyMode = useCallback(() => {
        if (activeSnippetId === null) {
            return;
        }

        setEditorOnlyMode((enabled) => !enabled);
        setMobilePanelOpen(false);
    }, [activeSnippetId]);

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
                    const storedValue = JSON.parse(stored) as unknown;
                    const restoredMultiFileMode =
                        restoreMultiFileMode(storedValue);
                    const restoredTabs = restoreWorkspaceTabs(
                        storedValue,
                        validSnippetIds,
                    );
                    const initialTabs = restoredMultiFileMode
                        ? restoredTabs
                        : restrictWorkspaceTabsToSingleFile(restoredTabs);

                    setOpenIds(initialTabs.openIds);
                    setActiveSnippetId(initialTabs.activeId);
                    setPinnedIds(initialTabs.pinnedIds);
                    setMultiFileMode(restoredMultiFileMode);
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
            JSON.stringify({
                openIds,
                activeId: activeSnippetId,
                pinnedIds,
                multiFileMode,
            }),
        );
    }, [
        activeSnippetId,
        multiFileMode,
        openIds,
        pinnedIds,
        storageHydrated,
        storageKey,
    ]);

    useEffect(() => {
        const frame = window.requestAnimationFrame(() => {
            let preferences = defaultEditorModePreferences;

            try {
                const stored = window.localStorage.getItem(
                    editorViewPreferencesStorageKey,
                );

                if (stored !== null) {
                    preferences = restoreEditorModePreferences(
                        JSON.parse(stored) as unknown,
                    );
                }
            } catch {
                try {
                    window.localStorage.removeItem(
                        editorViewPreferencesStorageKey,
                    );
                } catch {
                    preferences = defaultEditorModePreferences;
                }
            }

            setEditorViewPreferenceStorage({
                storageKey: editorViewPreferencesStorageKey,
                preferences,
            });
        });

        return () => window.cancelAnimationFrame(frame);
    }, [editorViewPreferencesStorageKey]);

    useEffect(() => {
        if (
            editorViewPreferenceStorage.storageKey !==
            editorViewPreferencesStorageKey
        ) {
            return;
        }

        try {
            window.localStorage.setItem(
                editorViewPreferencesStorageKey,
                JSON.stringify(editorViewPreferenceStorage.preferences),
            );
        } catch {
            return;
        }
    }, [editorViewPreferenceStorage, editorViewPreferencesStorageKey]);

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
                /^(?:\/projects|\/snippets|\/folders|\/pins|\/clipboards|\/clipboard-clips)(?:\/|$)/u.test(
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
                !event.repeat &&
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'p'
            ) {
                event.preventDefault();

                if (
                    !megaSearchOpen &&
                    (dialog !== null ||
                        libraryCategoryDialog !== null ||
                        document.querySelector(
                            '[data-slot="dialog-content"]',
                        ) !== null)
                ) {
                    return;
                }

                openMegaSearch();

                return;
            }

            if (
                !event.repeat &&
                dialog === null &&
                activeSnippetId !== null &&
                isEditorOnlyModeShortcut(event)
            ) {
                event.preventDefault();
                toggleEditorOnlyMode();

                return;
            }

            if (
                (event.metaKey || event.ctrlKey) &&
                event.key.toLowerCase() === 'k'
            ) {
                event.preventDefault();

                if (megaSearchOpen) {
                    closeMegaSearch(false);
                }

                setEditorOnlyMode(false);
                setActivePanel('search');
                setMobilePanelOpen(true);
                setSearchFocusRequest((request) => request + 1);

                return;
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
    }, [
        activeSnippetId,
        closeMegaSearch,
        dialog,
        libraryCategoryDialog,
        megaSearchOpen,
        openMegaSearch,
        saveActiveSnippet,
        toggleEditorOnlyMode,
    ]);

    useEffect(() => {
        if (!megaSearchOpen) {
            return;
        }

        const frame = window.requestAnimationFrame(() =>
            megaSearchInputRef.current?.focus({ preventScroll: true }),
        );

        return () => window.cancelAnimationFrame(frame);
    }, [megaSearchOpen]);

    useEffect(() => {
        if (searchFocusRequest === 0 || activePanel !== 'search') {
            return;
        }

        const frame = window.requestAnimationFrame(() =>
            sidebarSearchInputRef.current?.focus({ preventScroll: true }),
        );

        return () => window.cancelAnimationFrame(frame);
    }, [activePanel, editorOnlyMode, mobilePanelOpen, searchFocusRequest]);

    useEffect(() => {
        if (activeSnippetId !== null) {
            return;
        }

        const frame = window.requestAnimationFrame(() =>
            setEditorOnlyMode(false),
        );

        return () => window.cancelAnimationFrame(frame);
    }, [activeSnippetId]);

    const closeSnippet = (snippet: Snippet) => {
        if (
            dirtySnippetIds.has(snippet.id) &&
            !window.confirm(`Discard unsaved changes to ${snippet.filename}?`)
        ) {
            return;
        }

        const remainingTabs = closeWorkspaceTabs(
            {
                openIds,
                activeId: activeSnippetId,
                pinnedIds,
            },
            [snippet.id],
        );
        setOpenIds(remainingTabs.openIds);
        setActiveSnippetId(remainingTabs.activeId);
        setPinnedIds(remainingTabs.pinnedIds);
        const variationIds = new Set(
            snippet.variations.map((variation) => variation.id),
        );
        setVariationDrafts((current) => {
            const next = { ...current };

            variationIds.forEach((variationId) => delete next[variationId]);

            return next;
        });
    };

    const closeAllSnippets = () => {
        const snippetsToClose = openSnippets.filter(
            (snippet) => !pinnedSnippetIds.has(snippet.id),
        );
        const dirtySnippetsToClose = snippetsToClose.filter((snippet) =>
            dirtySnippetIds.has(snippet.id),
        );

        if (
            dirtySnippetsToClose.length > 0 &&
            !window.confirm(
                `Discard unsaved changes in ${dirtySnippetsToClose.length} ${dirtySnippetsToClose.length === 1 ? 'tab' : 'tabs'}?`,
            )
        ) {
            return;
        }

        const variationIds = new Set(
            snippetsToClose.flatMap((snippet) =>
                snippet.variations.map((variation) => variation.id),
            ),
        );
        setVariationDrafts((current) => {
            const next = { ...current };

            variationIds.forEach((variationId) => delete next[variationId]);

            return next;
        });
        const remainingTabs = closeUnpinnedWorkspaceTabs({
            openIds,
            activeId: activeSnippetId,
            pinnedIds,
        });
        setOpenIds(remainingTabs.openIds);
        setActiveSnippetId(remainingTabs.activeId);
        setPinnedIds(remainingTabs.pinnedIds);
    };

    const togglePinnedTab = (snippet: Snippet) => {
        if (
            !multiFileMode &&
            pinnedSnippetIds.has(snippet.id) &&
            !openSnippet(snippet)
        ) {
            return;
        }

        setPinnedIds((current) => togglePinnedSnippet(current, snippet.id));
    };

    const reorderTabs = (
        sourceId: number,
        targetId: number,
        position: WorkspaceTabDropPosition,
    ) => {
        setOpenIds((current) =>
            reorderWorkspaceTabs(current, sourceId, targetId, position),
        );
    };

    const toggleMultiFileMode = () => {
        if (!multiFileMode) {
            setMultiFileMode(true);

            return;
        }

        const singleFileTabs = restrictWorkspaceTabsToSingleFile({
            openIds,
            activeId: activeSnippetId,
            pinnedIds,
        });
        const snippetsToClose = openSnippets.filter(
            (snippet) => !singleFileTabs.openIds.includes(snippet.id),
        );
        const dirtySnippetsToClose = snippetsToClose.filter((snippet) =>
            dirtySnippetIds.has(snippet.id),
        );

        if (
            dirtySnippetsToClose.length > 0 &&
            !window.confirm(
                `Discard unsaved changes in ${dirtySnippetsToClose.length} ${dirtySnippetsToClose.length === 1 ? 'file' : 'files'}?`,
            )
        ) {
            return;
        }

        const variationIds = new Set(
            snippetsToClose.flatMap((snippet) =>
                snippet.variations.map((variation) => variation.id),
            ),
        );
        setVariationDrafts((current) => {
            const next = { ...current };

            variationIds.forEach((variationId) => delete next[variationId]);

            return next;
        });
        setOpenIds(singleFileTabs.openIds);
        setMultiFileMode(false);
    };

    const acceptSuggestion = (suggestion: string) => {
        setQuery((current) => applySearchSuggestion(current, suggestion));
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
        const wasCopied = await copy(value);

        if (wasCopied) {
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

        return wasCopied;
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

    const clipboardMutationOptions = useCallback(
        (errorMessage: string) => ({
            preserveScroll: true,
            preserveState: true,
            only: ['clipboard_sessions'],
            onError: (errors: Record<string, string>) =>
                toast.error(Object.values(errors)[0] ?? errorMessage),
            onFinish: () => setClipboardProcessing(false),
        }),
        [],
    );

    const createClipboardSession = () => {
        if (clipboardProcessing) {
            return;
        }

        setClipboardProcessing(true);
        router.post(
            storeClipboardSession.url(),
            {},
            clipboardMutationOptions('The clipboard could not be created.'),
        );
    };

    const activateClipboardSession = (clipboardSessionId: number) => {
        if (
            clipboardProcessing ||
            activeClipboardSession?.id === clipboardSessionId
        ) {
            return;
        }

        setClipboardProcessing(true);
        router.patch(
            ClipboardActivationController.url({
                clipboardSession: clipboardSessionId,
            }),
            {},
            clipboardMutationOptions('The clipboard could not be opened.'),
        );
    };

    const renameClipboardSession = (
        clipboardSessionId: number,
        name: string,
    ) => {
        if (clipboardProcessing) {
            return;
        }

        setClipboardProcessing(true);
        router.patch(
            updateClipboardSession.url({
                clipboardSession: clipboardSessionId,
            }),
            { name },
            clipboardMutationOptions('The clipboard could not be renamed.'),
        );
    };

    const clearClipboardSession = (clipboardSessionId: number) => {
        const clipboard = clipboardSessions.find(
            (candidate) => candidate.id === clipboardSessionId,
        );

        if (clipboardProcessing || !clipboard || clipboard.clips_count === 0) {
            return;
        }

        setClipboardProcessing(true);
        router.delete(
            ClipboardClearController.url({
                clipboardSession: clipboardSessionId,
            }),
            clipboardMutationOptions('The clipboard could not be cleared.'),
        );
    };

    const deleteClipboardSession = (clipboardSessionId: number) => {
        const clipboard = clipboardSessions.find(
            (candidate) => candidate.id === clipboardSessionId,
        );

        if (clipboardProcessing || !clipboard) {
            return;
        }

        setClipboardProcessing(true);
        router.delete(
            destroyClipboardSession.url({
                clipboardSession: clipboardSessionId,
            }),
            clipboardMutationOptions('The clipboard could not be deleted.'),
        );
    };

    const deleteClipboardClip = (clipboardClipId: number) => {
        if (clipboardProcessing) {
            return;
        }

        setClipboardProcessing(true);
        router.delete(
            destroyClipboardClip.url({ clipboardClip: clipboardClipId }),
            clipboardMutationOptions('The clip could not be removed.'),
        );
    };

    const addSelectionToClipboard = (selection: ClipboardSelection) => {
        if (
            clipboardProcessing ||
            !activeSnippet ||
            !activeVariation ||
            selection.content.length === 0
        ) {
            return;
        }

        setClipboardProcessing(true);
        router.post(
            storeClipboardClip.url(),
            {
                clipboard_session_id: activeClipboardSession?.id ?? null,
                snippet_id: activeSnippet.id,
                snippet_variation_id: activeVariation.id,
                content: selection.content,
                representation:
                    effectiveEditorMode === 'preview' ? 'rendered' : 'source',
                line_start: selection.startLine,
                line_end: selection.endLine,
            },
            clipboardMutationOptions(
                'The selection could not be added to the clipboard.',
            ),
        );
    };

    const addPastedContentToClipboard = useCallback(
        (content: string) => {
            if (clipboardProcessing) {
                return;
            }

            if (!activeClipboardSession) {
                toast.error('Create or select a clipboard before pasting.');

                return;
            }

            const selection = createClipboardSelection(
                content,
                0,
                content.length,
            );

            if (!selection) {
                return;
            }

            setClipboardProcessing(true);
            router.post(
                storeClipboardClip.url(),
                {
                    clipboard_session_id: activeClipboardSession.id,
                    content: selection.content,
                    representation: 'source',
                    line_start: selection.startLine,
                    line_end: selection.endLine,
                },
                clipboardMutationOptions(
                    'The pasted content could not be added to the clipboard.',
                ),
            );
        },
        [activeClipboardSession, clipboardMutationOptions, clipboardProcessing],
    );

    useEffect(() => {
        const handlePaste = (event: ClipboardEvent) => {
            if (isEditablePasteTarget(event.target)) {
                return;
            }

            const content = readClipboardText(event.clipboardData);

            if (content.length === 0) {
                return;
            }

            event.preventDefault();
            addPastedContentToClipboard(content);
        };

        window.addEventListener('paste', handlePaste);

        return () => window.removeEventListener('paste', handlePaste);
    }, [addPastedContentToClipboard]);

    const selectVariation = (variation: SnippetVariation) => {
        if (!activeSnippet) {
            return;
        }

        setSelectedVariationIds((current) => ({
            ...current,
            [activeSnippet.id]: variation.id,
        }));
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
        changeEditorMode('source');
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

    const beginCreateClipboardFile = (clipboard: ClipboardSession) => {
        setCreateSnippetAfterWorkspace(false);
        setDialog({
            kind: 'create-snippet',
            project: null,
            folder: null,
            sourceClipboard: clipboard,
        });
    };

    const submitLibraryCategoryDialog = (
        payload: Record<string, FormDataConvertible>,
    ) => {
        if (!libraryCategoryDialog) {
            return;
        }

        setLibraryCategoryProcessing(true);
        setLibraryCategoryErrors({});
        const options = {
            preserveScroll: true,
            only: ['library_categories', 'projects'],
            onSuccess: () => {
                setLibraryCategoryDialog(null);
                setLibraryCategoryErrors({});
            },
            onError: (errors: Record<string, string>) =>
                setLibraryCategoryErrors(errors),
            onFinish: () => setLibraryCategoryProcessing(false),
        };

        if (libraryCategoryDialog.kind === 'create') {
            router.post(storeLibraryCategory.url(), payload, options);

            return;
        }

        if (libraryCategoryDialog.kind === 'rename') {
            router.patch(
                updateLibraryCategory.url({
                    libraryCategory: libraryCategoryDialog.category.id,
                }),
                payload,
                options,
            );

            return;
        }

        router.delete(
            destroyLibraryCategory.url({
                libraryCategory: libraryCategoryDialog.category.id,
            }),
            options,
        );
    };

    const submitFrameworkDialog = (
        payload: Record<string, FormDataConvertible>,
    ) => {
        setFrameworkProcessing(true);
        setFrameworkErrors({});

        router.post(storeFramework.url(), payload, {
            preserveScroll: true,
            only: ['frameworks'],
            onSuccess: () => {
                setFrameworkDialogOpen(false);
                setFrameworkErrors({});
            },
            onError: (errors: Record<string, string>) =>
                setFrameworkErrors(errors),
            onFinish: () => setFrameworkProcessing(false),
        });
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
                if (dialog.sourceClipboard) {
                    router.post(
                        ClipboardFileController.url({
                            clipboardSession: dialog.sourceClipboard.id,
                        }),
                        payload,
                        options,
                    );
                } else {
                    router.post(storeSnippet.url(), payload, options);
                }

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

    const renameEntityInline = (
        entity: ExplorerEntity,
        name: string,
        callbacks: InlineRenameCallbacks,
    ) => {
        const options = {
            preserveScroll: true,
            onSuccess: callbacks.onSuccess,
            onError: (errors: Record<string, string>) =>
                callbacks.onError(
                    errors.name ??
                        errors.filename ??
                        errors.title ??
                        'The item could not be renamed.',
                ),
            onFinish: callbacks.onFinish,
        };

        if (entity.type === 'project') {
            router.patch(
                updateProject.url({ project: entity.project.id }),
                {
                    name,
                    library_category_id: entity.project.library_category_id,
                    kind: entity.project.kind,
                    description: entity.project.description,
                    frameworks: entity.project.frameworks.map(
                        (framework) => framework.name,
                    ),
                },
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
                { name, parent_id: entity.folder.parent_id },
                options,
            );

            return;
        }

        router.patch(
            updateSnippet.url({ snippet: entity.snippet.id }),
            {
                ...buildSnippetPayload(entity.snippet),
                filename: name,
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

    const restoreTrashItem = (item: LibraryTrashItem) => {
        const url =
            item.type === 'project'
                ? restoreProject.url({ project: item.id })
                : item.type === 'folder'
                  ? restoreFolder.url({ folder: item.id })
                  : restoreSnippet.url({ snippet: item.id });

        router.patch(url, {}, { preserveScroll: true });
    };

    const permanentlyDeleteTrashItem = (item: LibraryTrashItem) => {
        if (
            !window.confirm(
                `Permanently delete “${item.name}”? This cannot be undone.`,
            )
        ) {
            return;
        }

        const url =
            item.type === 'project'
                ? forceDestroyProject.url({ project: item.id })
                : item.type === 'folder'
                  ? forceDestroyFolder.url({ folder: item.id })
                  : forceDestroySnippet.url({ snippet: item.id });

        router.delete(url, { preserveScroll: true });
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

        const remainingTabs = closeWorkspaceTabs(
            {
                openIds,
                activeId: activeSnippetId,
                pinnedIds,
            },
            idsToClose,
        );
        setOpenIds(remainingTabs.openIds);
        setActiveSnippetId(remainingTabs.activeId);
        setPinnedIds(remainingTabs.pinnedIds);
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

    const reorderWorkspaces = (projectIds: number[]) => {
        router.patch(
            reorderProjects.url(),
            { project_ids: projectIds },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) =>
                    toast.error(
                        errors.project_ids ??
                            'The workspace order could not be saved.',
                    ),
            },
        );
    };

    const openSearchResult = (result: SnippetSearchResult): boolean => {
        if (result.kind === 'section') {
            if (!openSnippet(result.snippet)) {
                return false;
            }

            setSelectedVariationIds((current) => ({
                ...current,
                [result.snippet.id]: result.variation.id,
            }));
            setSelectedSectionKeys((current) => ({
                ...current,
                [result.variation.id]: result.section.key,
            }));
            changeEditorMode('source', result.snippet);
            setPendingSectionSelection({
                snippetId: result.snippet.id,
                variationId: result.variation.id,
                sectionKey: result.section.key,
            });

            return true;
        }

        if (result.kind === 'snippet') {
            if (!openSnippet(result.snippet)) {
                return false;
            }

            if (result.variationId !== null) {
                setSelectedVariationIds((current) => ({
                    ...current,
                    [result.snippet.id]: result.variationId,
                }));
            }

            return true;
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

        return true;
    };

    const openMegaSearchResult = (result: SnippetSearchResult): boolean => {
        const didOpen = openSearchResult(result);

        if (didOpen) {
            closeMegaSearch();
        }

        return didOpen;
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
            <Head
                title={
                    workspaceView === 'brain'
                        ? 'Second brain'
                        : 'Snippet workspace'
                }
            />
            {megaSearchOpen && (
                <WorkspaceMegaSearch
                    query={megaSearchQuery}
                    suggestions={megaSearchSuggestions}
                    results={megaSearchResults.items}
                    totalResults={megaSearchResults.total}
                    inputRef={megaSearchInputRef}
                    languageValue={megaSearchLanguage}
                    languageOptions={languageOptions}
                    categoryValue={megaSearchLibraryCategoryId}
                    categoryOptions={libraryCategories}
                    frameworkValue={megaSearchFrameworkId}
                    frameworkOptions={frameworks}
                    searchCode={megaSearchIncludesCode}
                    onQueryChange={setMegaSearchQuery}
                    onCaretChange={setMegaSearchCaretPosition}
                    onSuggestionAccept={(suggestion, caretPosition) =>
                        setMegaSearchQuery((current) =>
                            applySearchSuggestion(
                                current,
                                suggestion,
                                caretPosition ?? megaSearchCaretPosition,
                            ),
                        )
                    }
                    onLanguageChange={setMegaSearchLanguage}
                    onCategoryChange={setMegaSearchLibraryCategoryId}
                    onFrameworkChange={setMegaSearchFrameworkId}
                    onSearchCodeChange={setMegaSearchIncludesCode}
                    onOpen={openMegaSearchResult}
                    onClose={closeMegaSearch}
                />
            )}
            <div
                data-editor-only-mode={editorOnlyMode}
                className="snippet-workspace relative flex h-full min-h-0 overflow-hidden bg-code-canvas"
            >
                {!editorOnlyMode && (
                    <>
                        <WorkspaceActivityBar
                            activePanel={activePanel}
                            inspectorOpen={inspectorOpen}
                            secondBrainActive={workspaceView === 'brain'}
                            user={auth.user}
                            onMegaSearchOpen={openMegaSearch}
                            onPanelChange={(panel) => {
                                setWorkspaceView('editor');
                                setMobilePanelOpen((open) =>
                                    activePanel === panel ? !open : true,
                                );
                                setActivePanel(panel);
                            }}
                            onInspectorToggle={() =>
                                setInspectorOpen((open) => !open)
                            }
                            onSecondBrainOpen={() => {
                                setWorkspaceView('brain');
                                setMobilePanelOpen(false);
                                setInspectorOpen(false);
                            }}
                        />

                        {workspaceView !== 'brain' && (
                            <div
                                style={{
                                    width: `${displayedLibraryPanelWidth}px`,
                                    maxWidth: `${libraryPanelEffectiveMaxWidth}px`,
                                }}
                                className={cn(
                                    'absolute inset-y-0 left-12 z-30 min-h-0 shrink-0 shadow-2xl lg:static lg:z-auto lg:flex lg:shadow-none',
                                    mobilePanelOpen ? 'flex' : 'hidden',
                                )}
                            >
                                <WorkspaceSidePanel
                                    panel={activePanel}
                                    libraryCategories={libraryCategories}
                                    projects={projects}
                                    standaloneSnippets={standaloneSnippets}
                                    visibleSnippets={visibleSnippets}
                                    matchedProjectIds={matchedProjectIds}
                                    matchedFolderIds={matchedFolderIds}
                                    languageOptions={languageOptions}
                                    frameworks={frameworks}
                                    tags={tags}
                                    query={query}
                                    searchEntity={searchEntity}
                                    searchScope={searchScope}
                                    searchFrameworkId={searchFrameworkId}
                                    searchExcerptMode={searchExcerptMode}
                                    searchCodeMatches={searchCodeMatches}
                                    searchResultCount={searchResultCount}
                                    searchFiltering={isSearchFiltering}
                                    suggestions={suggestions}
                                    results={searchResults}
                                    inputRef={sidebarSearchInputRef}
                                    activeSnippetId={activeSnippetId}
                                    dirtySnippetIds={dirtySnippetIds}
                                    revealedProjectId={revealedProjectId}
                                    revealedFolderId={revealedFolderId}
                                    accountKey={auth.user.id}
                                    pinnedKeys={pinnedKeys}
                                    trash={trash}
                                    onQueryChange={setQuery}
                                    onSearchEntityChange={setSearchEntity}
                                    onSearchScopeChange={setSearchScope}
                                    onSearchFrameworkChange={
                                        setSearchFrameworkId
                                    }
                                    onSearchExcerptModeChange={
                                        setSearchExcerptMode
                                    }
                                    onSearchFocus={() => {
                                        setActivePanel('search');
                                        setMobilePanelOpen(true);
                                    }}
                                    onSuggestionAccept={acceptSuggestion}
                                    onSearchOpen={openSearchResult}
                                    onCopySection={copySearchSection}
                                    onOpenSnippet={openExplorerSnippet}
                                    onCreateSnippet={beginCreateSnippet}
                                    onNewProject={(category) => {
                                        setCreateSnippetAfterWorkspace(false);
                                        setDialog({
                                            kind: 'create-project',
                                            category: category ?? null,
                                        });
                                    }}
                                    onNewFramework={() => {
                                        setFrameworkErrors({});
                                        setFrameworkDialogOpen(true);
                                    }}
                                    onNewLibraryCategory={() => {
                                        setLibraryCategoryErrors({});
                                        setLibraryCategoryDialog({
                                            kind: 'create',
                                        });
                                    }}
                                    onRenameLibraryCategory={(
                                        category: LibraryCategory,
                                    ) => {
                                        setLibraryCategoryErrors({});
                                        setLibraryCategoryDialog({
                                            kind: 'rename',
                                            category,
                                        });
                                    }}
                                    onDeleteLibraryCategory={(
                                        category: LibraryCategory,
                                    ) => {
                                        setLibraryCategoryErrors({});
                                        setLibraryCategoryDialog({
                                            kind: 'delete',
                                            category,
                                        });
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
                                        setDialog(
                                            entity.type === 'snippet'
                                                ? {
                                                      kind: 'metadata',
                                                      snippet: entity.snippet,
                                                  }
                                                : { kind: 'rename', entity },
                                        )
                                    }
                                    onInlineRename={renameEntityInline}
                                    onDelete={(entity) =>
                                        setDialog({ kind: 'delete', entity })
                                    }
                                    onRestore={restoreTrashItem}
                                    onPermanentlyDelete={
                                        permanentlyDeleteTrashItem
                                    }
                                    onToggleFavourite={toggleFavourite}
                                    onTogglePin={togglePin}
                                    onMove={moveLibraryItem}
                                    onReorderProjects={reorderWorkspaces}
                                />
                                <WorkspaceResizeHandle
                                    label="Resize library sidebar"
                                    controls="workspace-library-panel"
                                    side="left"
                                    width={displayedLibraryPanelWidth}
                                    minWidth={libraryPanelMinWidth}
                                    maxWidth={libraryPanelEffectiveMaxWidth}
                                    onResize={setLibraryPanelWidth}
                                    onResizeEnd={(width) =>
                                        persistPanelWidth(
                                            libraryPanelWidthStorageKey,
                                            width,
                                        )
                                    }
                                />
                            </div>
                        )}
                    </>
                )}

                <section className="flex min-w-0 flex-1 flex-col bg-code-canvas">
                    {workspaceView === 'brain' ? (
                        <SecondBrain
                            libraryCategories={libraryCategories}
                            projects={projects}
                            standaloneSnippets={standaloneSnippets}
                            onClose={() => setWorkspaceView('editor')}
                            onOpenSnippet={(snippet) => {
                                openSnippet(snippet);
                            }}
                            onRevealProject={(projectId) => {
                                setRevealedProjectId(projectId);
                                setRevealedFolderId(null);
                                setActivePanel('explorer');
                                setWorkspaceView('editor');
                                setMobilePanelOpen(true);
                            }}
                            onRevealFolder={(projectId, folderId) => {
                                setRevealedProjectId(projectId);
                                setRevealedFolderId(folderId);
                                setActivePanel('explorer');
                                setWorkspaceView('editor');
                                setMobilePanelOpen(true);
                            }}
                            onBrowseFilter={(scope, value, frameworkId) => {
                                setSearchEntity('all');
                                setSearchScope(scope);
                                setSearchFrameworkId(
                                    scope === 'framework'
                                        ? (frameworkId ?? null)
                                        : null,
                                );
                                setQuery(value);
                                setActivePanel('search');
                                setWorkspaceView('editor');
                                setMobilePanelOpen(true);
                            }}
                        />
                    ) : activeSnippet && activeVariation ? (
                        <>
                            {multiFileMode || pinnedSnippetIds.size > 0 ? (
                                <EditorTabBar
                                    snippets={openSnippets}
                                    activeSnippetId={activeSnippet.id}
                                    dirtySnippetIds={dirtySnippetIds}
                                    pinnedSnippetIds={pinnedSnippetIds}
                                    multiFileMode={multiFileMode}
                                    editorOnlyMode={editorOnlyMode}
                                    editorOnlyModeShortcut={
                                        editorOnlyModeShortcut
                                    }
                                    onActivate={openSnippet}
                                    onClose={closeSnippet}
                                    onCloseAll={closeAllSnippets}
                                    onReorder={reorderTabs}
                                    onToggleFavourite={toggleFavourite}
                                    onTogglePinned={togglePinnedTab}
                                    onEditorOnlyModeToggle={
                                        toggleEditorOnlyMode
                                    }
                                />
                            ) : null}
                            <div className="relative flex min-h-0 flex-1">
                                <div className="flex min-w-0 flex-1 flex-col">
                                    <SnippetEditorToolbar
                                        snippet={activeSnippet}
                                        project={activeProject}
                                        folderPath={selectedFolderPath}
                                        activeVariation={activeVariation}
                                        variations={activeSnippet.variations}
                                        mode={effectiveEditorMode}
                                        dirty={activeDirty}
                                        saving={saving}
                                        multiFileMode={multiFileMode}
                                        sections={activeSections}
                                        activeSectionKey={
                                            activeSection?.key ?? null
                                        }
                                        copied={
                                            copiedText === activeSource ||
                                            (effectiveEditorMode ===
                                                'preview' &&
                                                copiedText === renderedSource)
                                        }
                                        onModeChange={changeEditorMode}
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
                                        onMultiFileModeToggle={
                                            toggleMultiFileMode
                                        }
                                    />
                                    {effectiveEditorMode === 'playback' &&
                                    activeSnippet.content_type === 'guide' ? (
                                        <GuidePlayback
                                            key={`${activeSnippet.id}-${activeVariation.id}`}
                                            title={activeSnippet.title}
                                            steps={activeGuideSteps}
                                            onCopyCode={(source, label) =>
                                                copyText(
                                                    source,
                                                    label,
                                                    'rendered',
                                                    'selection',
                                                    {
                                                        snippet: activeSnippet,
                                                        variation:
                                                            activeVariation,
                                                        presetId:
                                                            activeSelectedPreset?.id ??
                                                            null,
                                                    },
                                                )
                                            }
                                        />
                                    ) : (
                                        <SnippetEditor
                                            ref={editorRef}
                                            value={
                                                effectiveEditorMode ===
                                                'preview'
                                                    ? renderedSource
                                                    : activeSource
                                            }
                                            language={activeSnippet.language}
                                            activeClipboardName={
                                                activeClipboardSession?.name ??
                                                null
                                            }
                                            preview={
                                                effectiveEditorMode ===
                                                'preview'
                                            }
                                            readOnly={
                                                effectiveEditorMode ===
                                                'preview'
                                            }
                                            onChange={(value) => {
                                                if (
                                                    effectiveEditorMode ===
                                                    'preview'
                                                ) {
                                                    return;
                                                }

                                                setVariationDrafts(
                                                    (current) => ({
                                                        ...current,
                                                        [activeVariation.id]:
                                                            value,
                                                    }),
                                                );
                                            }}
                                            onSave={saveActiveSnippet}
                                            onAddToClipboard={
                                                clipboardProcessing
                                                    ? undefined
                                                    : addSelectionToClipboard
                                            }
                                            onCopy={(selectionLength, method) =>
                                                void recordCopy(
                                                    {
                                                        snippet: activeSnippet,
                                                        variation:
                                                            activeVariation,
                                                        presetId:
                                                            activeSelectedPreset?.id ??
                                                            null,
                                                    },
                                                    method,
                                                    effectiveEditorMode ===
                                                        'preview'
                                                        ? 'rendered'
                                                        : 'source',
                                                    selectionLength >=
                                                        (effectiveEditorMode ===
                                                        'preview'
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
                                    )}
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

                                {!editorOnlyMode && inspectorOpen && (
                                    <>
                                        <button
                                            type="button"
                                            aria-label="Close snippet details"
                                            onClick={() =>
                                                setInspectorOpen(false)
                                            }
                                            className="absolute inset-0 z-20 bg-black/35 xl:hidden"
                                        />
                                        <div
                                            style={{
                                                width: `${displayedInspectorPanelWidth}px`,
                                                maxWidth: `${inspectorPanelEffectiveMaxWidth}px`,
                                            }}
                                            className="absolute inset-y-0 right-0 z-30 flex min-h-0 shrink-0 shadow-2xl xl:static xl:z-auto xl:shadow-none"
                                        >
                                            <WorkspaceResizeHandle
                                                label="Resize snippet details sidebar"
                                                controls="workspace-inspector-panel"
                                                side="right"
                                                width={
                                                    displayedInspectorPanelWidth
                                                }
                                                minWidth={
                                                    inspectorPanelMinWidth
                                                }
                                                maxWidth={
                                                    inspectorPanelEffectiveMaxWidth
                                                }
                                                onResize={
                                                    setInspectorPanelWidth
                                                }
                                                onResizeEnd={(width) =>
                                                    persistPanelWidth(
                                                        inspectorPanelWidthStorageKey,
                                                        width,
                                                    )
                                                }
                                            />
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
                            inputRef={heroSearchInputRef}
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

                <ClipboardPanel
                    clipboards={clipboardSessions}
                    processing={clipboardProcessing}
                    onCreate={createClipboardSession}
                    onActivate={activateClipboardSession}
                    onRename={renameClipboardSession}
                    onCreateFile={beginCreateClipboardFile}
                    onDeleteClip={deleteClipboardClip}
                    onClear={clearClipboardSession}
                    onDelete={deleteClipboardSession}
                />
            </div>

            <WorkspaceDialog
                state={dialog}
                projects={projects}
                libraryCategories={libraryCategories}
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
            <LibraryCategoryDialog
                state={libraryCategoryDialog}
                processing={libraryCategoryProcessing}
                errors={libraryCategoryErrors}
                onClose={() => {
                    setLibraryCategoryDialog(null);
                    setLibraryCategoryErrors({});
                }}
                onSubmit={submitLibraryCategoryDialog}
            />
            <FrameworkDialog
                open={frameworkDialogOpen}
                processing={frameworkProcessing}
                errors={frameworkErrors}
                onClose={() => {
                    setFrameworkDialogOpen(false);
                    setFrameworkErrors({});
                }}
                onSubmit={submitFrameworkDialog}
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

function isEditablePasteTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    return (
        target instanceof HTMLInputElement ||
        target instanceof HTMLTextAreaElement ||
        target instanceof HTMLSelectElement ||
        target.isContentEditable ||
        target.closest('[contenteditable="true"]') !== null
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
        content_type: snippet.content_type,
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

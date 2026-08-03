export type TemplateVariableValues = Record<string, string>;

export type TemplateVariable = {
    name: string;
    defaultValue: string;
    occurrences: number;
};

export type ProjectKind = 'project' | 'bundle' | 'guide';

export type SnippetContentType = 'snippet' | 'guide';

export type LibraryCategory = {
    id: number;
    name: string;
    position: number;
};

export type Project = {
    id: number;
    library_category_id: number | null;
    name: string;
    kind: ProjectKind;
    description: string | null;
    is_pinned: boolean;
    frameworks: Framework[];
    folders: Folder[];
    snippets: Snippet[];
};

export type Folder = {
    id: number;
    project_id: number;
    parent_id: number | null;
    name: string;
    position: number;
};

export type Tag = {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    is_pinned: boolean;
};

export type Framework = {
    id: number;
    name: string;
    slug: string;
    color: string | null;
    is_pinned: boolean;
};

export type LanguageOption = {
    value: string;
    label: string;
    aliases: string[];
    syntax: string;
    extensions: string[];
    is_pinned: boolean;
};

export type SnippetUsage = {
    copies_30d: number;
    copies_total: number;
    last_copied_at: string | null;
    views_30d: number;
    views_total: number;
    last_viewed_at: string | null;
    weighted_score: number;
    relative_score: number;
    indicator: -1 | 0 | 1 | 2 | 3;
};

export type SnippetSection = {
    key: string;
    name: string;
    label: string;
    position: number;
    marker_line: number;
    start_line: number;
    end_line: number;
    content: string;
};

export type SnippetVariation = {
    id: number;
    name: string;
    content: string;
    position: number;
    is_default: boolean;
    updated_at: string;
    sections: SnippetSection[];
    guide_steps: GuideStep[];
};

export type GuideCodeBlock = {
    language: string;
    content: string;
    start_line: number;
    end_line: number;
};

export type GuideStep = {
    key: string;
    title: string;
    position: number;
    marker_line: number;
    start_line: number;
    end_line: number;
    instructions: string;
    code_blocks: GuideCodeBlock[];
};

export type VariablePreset = {
    id: number;
    name: string;
    values: TemplateVariableValues;
};

export type Snippet = {
    id: number;
    project_id: number | null;
    folder_id: number | null;
    title: string;
    filename: string;
    content_type: SnippetContentType;
    language: string;
    description: string | null;
    position: number;
    is_favourite: boolean;
    is_pinned: boolean;
    last_opened_at: string | null;
    updated_at: string;
    variations: SnippetVariation[];
    presets: VariablePreset[];
    tags: Tag[];
    frameworks: Framework[];
    usage: SnippetUsage;
};

export type WorkspacePins = {
    snippet_ids: number[];
    project_ids: number[];
    tag_ids: number[];
    language_values: string[];
    framework_ids: number[];
};

export type LibraryTrashItem = {
    type: 'project' | 'folder' | 'snippet';
    id: number;
    name: string;
    context: string;
    deleted_at: string;
};

export type LibraryTrash = {
    projects: LibraryTrashItem[];
    folders: LibraryTrashItem[];
    snippets: LibraryTrashItem[];
};

export type ClipboardClipSource = {
    snippet_id: number | null;
    variation_id: number | null;
    title: string;
    filename: string;
    project: string | null;
    folders: string[];
    variation: string;
    line_start: number;
    line_end: number;
};

export type ClipboardClip = {
    id: number;
    content: string;
    language: string;
    representation: 'source' | 'rendered';
    source: ClipboardClipSource;
    created_at: string;
};

export type ClipboardSession = {
    id: number;
    name: string;
    is_active: boolean;
    clips_count: number;
    clips: ClipboardClip[];
    created_at: string;
    updated_at: string;
};

export type SnippetWorkspaceProps = {
    library_categories: LibraryCategory[];
    projects: Project[];
    standalone_snippets: Snippet[];
    language_options: LanguageOption[];
    languages: string[];
    tags: Tag[];
    frameworks: Framework[];
    pins: WorkspacePins;
    trash: LibraryTrash;
    clipboard_sessions: ClipboardSession[];
};

export type SnippetProject = Project;
export type SnippetFolder = Folder;

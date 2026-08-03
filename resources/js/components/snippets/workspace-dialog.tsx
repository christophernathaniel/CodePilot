import type { FormDataConvertible } from '@inertiajs/core';
import {
    AlertTriangle,
    BookOpenText,
    Check,
    FilePlus2,
    FolderPlus,
    GitBranchPlus,
    PackagePlus,
    Save,
    Sparkles,
} from 'lucide-react';
import { useState } from 'react';
import type { ExplorerEntity } from '@/components/snippets/project-explorer';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { getClipboardFileDefaults } from '@/lib/snippets/clipboard-file';
import type {
    ClipboardSession,
    Framework,
    LanguageOption,
    LibraryCategory,
    Snippet,
    SnippetContentType,
    SnippetFolder,
    SnippetProject,
    SnippetVariation,
} from '@/types';

export type WorkspaceDialogState =
    | { kind: 'create-project'; category?: LibraryCategory | null }
    | {
          kind: 'create-folder';
          project: SnippetProject;
          parent: SnippetFolder | null;
      }
    | {
          kind: 'create-snippet';
          project: SnippetProject | null;
          folder: SnippetFolder | null;
          sourceClipboard?: ClipboardSession;
      }
    | { kind: 'create-variation'; snippet: Snippet; source: string }
    | {
          kind: 'rename-variation';
          snippet: Snippet;
          variation: SnippetVariation;
      }
    | { kind: 'rename'; entity: ExplorerEntity }
    | { kind: 'delete'; entity: ExplorerEntity }
    | { kind: 'metadata'; snippet: Snippet }
    | { kind: 'create-preset'; snippet: Snippet }
    | null;

export type WorkspaceDialogProps = {
    state: WorkspaceDialogState;
    projects: SnippetProject[];
    libraryCategories: LibraryCategory[];
    languageOptions: LanguageOption[];
    frameworks: Framework[];
    processing: boolean;
    errors: Record<string, string>;
    onClose: () => void;
    onSubmit: (payload: Record<string, FormDataConvertible>) => void;
};

export function WorkspaceDialog(props: WorkspaceDialogProps) {
    if (!props.state) {
        return null;
    }

    return (
        <WorkspaceDialogBody
            key={getDialogKey(props.state)}
            {...props}
            state={props.state}
        />
    );
}

type WorkspaceDialogBodyProps = Omit<WorkspaceDialogProps, 'state'> & {
    state: NonNullable<WorkspaceDialogState>;
};

function WorkspaceDialogBody({
    state,
    projects,
    libraryCategories,
    languageOptions,
    frameworks,
    processing,
    errors,
    onClose,
    onSubmit,
}: WorkspaceDialogBodyProps) {
    const [values, setValues] = useState<Record<string, string>>(() =>
        getInitialValues(state, languageOptions),
    );
    const [selectedFrameworkNames, setSelectedFrameworkNames] = useState<
        string[]
    >(() => getInitialFrameworkNames(state));

    const configuration = getDialogConfiguration(state);
    const isDelete = state.kind === 'delete';
    const isCreatingClipboardFile =
        state.kind === 'create-snippet' && Boolean(state.sourceClipboard);
    const selectedProject =
        state.kind === 'create-snippet'
            ? projects.find(
                  (project) => project.id === Number(values.project_id),
              )
            : null;
    const isSubmitDisabled = processing;
    const isCreatingGuide =
        state.kind === 'create-snippet' && values.content_type === 'guide';

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="border-code-border bg-code-raised text-code-text shadow-2xl sm:max-w-md">
                <DialogHeader>
                    <div className="mb-1 flex size-9 items-center justify-center rounded-lg border border-code-border bg-code-hover text-code-text">
                        {isDelete ? (
                            <AlertTriangle className="size-4 text-rose-300" />
                        ) : state.kind === 'create-project' ? (
                            <PackagePlus className="size-4" />
                        ) : isCreatingClipboardFile ? (
                            <FilePlus2 className="size-4 text-code-accent" />
                        ) : isCreatingGuide ? (
                            <BookOpenText className="size-4 text-sky-200" />
                        ) : state.kind === 'create-folder' ? (
                            <FolderPlus className="size-4" />
                        ) : state.kind === 'create-preset' ? (
                            <Sparkles className="size-4" />
                        ) : state.kind === 'create-variation' ||
                          state.kind === 'rename-variation' ? (
                            <GitBranchPlus className="size-4" />
                        ) : (
                            <Save className="size-4" />
                        )}
                    </div>
                    <DialogTitle>{configuration.title}</DialogTitle>
                    <DialogDescription className="text-code-muted">
                        {configuration.description}
                    </DialogDescription>
                </DialogHeader>

                {isDelete ? (
                    <p className="rounded-md border border-rose-400/12 bg-rose-400/5 px-3 py-3 text-xs leading-5 text-rose-100/80">
                        The selected item and anything stored inside it will
                        move to Trash. You can restore it later.
                    </p>
                ) : (
                    <form
                        id="workspace-dialog-form"
                        onSubmit={(event) => {
                            event.preventDefault();
                            onSubmit(
                                normalizePayload(
                                    state,
                                    values,
                                    selectedFrameworkNames,
                                ),
                            );
                        }}
                        className="flex flex-col gap-3"
                    >
                        {state.kind === 'create-project' && (
                            <>
                                <Field
                                    label="Name"
                                    name="name"
                                    value={values.name ?? ''}
                                    autoFocus
                                    error={errors.name}
                                    onChange={setValue(setValues, 'name')}
                                />
                                <LibraryCategoryField
                                    categories={libraryCategories}
                                    value={values.library_category_id ?? ''}
                                    error={errors.library_category_id}
                                    onChange={setValue(
                                        setValues,
                                        'library_category_id',
                                    )}
                                />
                                <label className="flex flex-col gap-1.5 text-xs text-code-muted">
                                    Type
                                    <select
                                        value={values.kind ?? 'project'}
                                        onChange={setValue(setValues, 'kind')}
                                        className={inputClassName}
                                    >
                                        <option value="project">Project</option>
                                        <option value="bundle">
                                            Snippet bundle
                                        </option>
                                        <option value="guide">
                                            Guide collection
                                        </option>
                                    </select>
                                </label>
                                <FrameworkField
                                    frameworks={frameworks}
                                    selectedNames={selectedFrameworkNames}
                                    error={errors.frameworks}
                                    onToggle={(frameworkName) =>
                                        setSelectedFrameworkNames((current) =>
                                            current.includes(frameworkName)
                                                ? current.filter(
                                                      (name) =>
                                                          name !==
                                                          frameworkName,
                                                  )
                                                : [...current, frameworkName],
                                        )
                                    }
                                />
                                <Field
                                    label="Description"
                                    name="description"
                                    value={values.description ?? ''}
                                    error={errors.description}
                                    onChange={setValue(
                                        setValues,
                                        'description',
                                    )}
                                />
                            </>
                        )}

                        {state.kind === 'create-folder' && (
                            <Field
                                label="Folder name"
                                name="name"
                                value={values.name ?? ''}
                                autoFocus
                                error={errors.name}
                                onChange={setValue(setValues, 'name')}
                            />
                        )}

                        {state.kind === 'create-snippet' && (
                            <>
                                {state.sourceClipboard ? (
                                    <div className="rounded-md border border-code-accent/20 bg-code-accent/6 px-3 py-2.5 text-[10px] leading-5 text-code-muted">
                                        <p className="font-medium text-code-text">
                                            {formatClipCount(
                                                state.sourceClipboard
                                                    .clips_count,
                                            )}{' '}
                                            from “{state.sourceClipboard.name}”
                                        </p>
                                        <p>
                                            {isCreatingGuide
                                                ? 'Each clip becomes a guide step with its source details, in the order shown.'
                                                : 'Clips are saved top-to-bottom as shown, separated by a blank line.'}{' '}
                                            The clipboard remains available.
                                        </p>
                                    </div>
                                ) : null}
                                <div className="grid grid-cols-2 gap-3">
                                    <label className="flex flex-col gap-1.5 text-xs text-code-muted">
                                        Location
                                        <select
                                            name="project_id"
                                            value={values.project_id ?? ''}
                                            onChange={(event) => {
                                                const project = projects.find(
                                                    (candidate) =>
                                                        candidate.id ===
                                                        Number(
                                                            event.target.value,
                                                        ),
                                                );
                                                const isGuideProject =
                                                    project?.kind === 'guide';

                                                setValues((current) => {
                                                    const currentContentType =
                                                        (current.content_type ??
                                                            'snippet') as SnippetContentType;
                                                    const nextContentType =
                                                        isGuideProject
                                                            ? 'guide'
                                                            : currentContentType;

                                                    if (state.sourceClipboard) {
                                                        const currentDefaults =
                                                            getClipboardFileDefaults(
                                                                state.sourceClipboard,
                                                                languageOptions,
                                                                currentContentType,
                                                            );
                                                        const nextDefaults =
                                                            getClipboardFileDefaults(
                                                                state.sourceClipboard,
                                                                languageOptions,
                                                                nextContentType,
                                                            );

                                                        return {
                                                            ...current,
                                                            project_id:
                                                                event.target
                                                                    .value,
                                                            folder_id: '',
                                                            content_type:
                                                                nextContentType,
                                                            language:
                                                                nextDefaults.language,
                                                            filename:
                                                                current.filename ===
                                                                currentDefaults.filename
                                                                    ? nextDefaults.filename
                                                                    : current.filename,
                                                        };
                                                    }

                                                    return {
                                                        ...current,
                                                        project_id:
                                                            event.target.value,
                                                        folder_id: '',
                                                        content_type:
                                                            nextContentType,
                                                        language:
                                                            isGuideProject &&
                                                            currentContentType !==
                                                                'guide'
                                                                ? 'markdown'
                                                                : current.language,
                                                        content:
                                                            isGuideProject &&
                                                            !current.content
                                                                ? guideStarterSource
                                                                : current.content,
                                                    };
                                                });
                                            }}
                                            className={inputClassName}
                                        >
                                            <option value="">
                                                Standalone · No collection
                                            </option>
                                            {projects.map((project) => (
                                                <option
                                                    key={project.id}
                                                    value={project.id}
                                                >
                                                    {project.name} ·{' '}
                                                    {getProjectKindLabel(
                                                        project.kind,
                                                    )}
                                                </option>
                                            ))}
                                        </select>
                                        <FieldError
                                            message={errors.project_id}
                                        />
                                    </label>
                                    <label className="flex flex-col gap-1.5 text-xs text-code-muted">
                                        Folder
                                        <select
                                            name="folder_id"
                                            value={values.folder_id ?? ''}
                                            onChange={setValue(
                                                setValues,
                                                'folder_id',
                                            )}
                                            disabled={!selectedProject}
                                            className={inputClassName}
                                        >
                                            <option value="">
                                                {selectedProject
                                                    ? 'Project root'
                                                    : 'Not available for standalone'}
                                            </option>
                                            {selectedProject &&
                                                [...selectedProject.folders]
                                                    .sort((left, right) =>
                                                        getFolderLabel(
                                                            selectedProject,
                                                            left.id,
                                                        ).localeCompare(
                                                            getFolderLabel(
                                                                selectedProject,
                                                                right.id,
                                                            ),
                                                        ),
                                                    )
                                                    .map((folder) => (
                                                        <option
                                                            key={folder.id}
                                                            value={folder.id}
                                                        >
                                                            {getFolderLabel(
                                                                selectedProject,
                                                                folder.id,
                                                            )}
                                                        </option>
                                                    ))}
                                        </select>
                                        <FieldError
                                            message={errors.folder_id}
                                        />
                                    </label>
                                </div>
                                <label className="flex flex-col gap-1.5 text-xs text-code-muted">
                                    File type
                                    <select
                                        name="content_type"
                                        value={values.content_type ?? 'snippet'}
                                        onChange={(event) => {
                                            const contentType = event.target
                                                .value as SnippetContentType;
                                            setValues((current) => {
                                                if (state.sourceClipboard) {
                                                    const currentContentType =
                                                        (current.content_type ??
                                                            'snippet') as SnippetContentType;
                                                    const currentDefaults =
                                                        getClipboardFileDefaults(
                                                            state.sourceClipboard,
                                                            languageOptions,
                                                            currentContentType,
                                                        );
                                                    const nextDefaults =
                                                        getClipboardFileDefaults(
                                                            state.sourceClipboard,
                                                            languageOptions,
                                                            contentType,
                                                        );

                                                    return {
                                                        ...current,
                                                        content_type:
                                                            contentType,
                                                        language:
                                                            nextDefaults.language,
                                                        filename:
                                                            current.filename ===
                                                            currentDefaults.filename
                                                                ? nextDefaults.filename
                                                                : current.filename,
                                                    };
                                                }

                                                return {
                                                    ...current,
                                                    content_type: contentType,
                                                    language:
                                                        contentType ===
                                                            'guide' &&
                                                        current.language ===
                                                            'javascript'
                                                            ? 'markdown'
                                                            : current.language,
                                                    content:
                                                        contentType ===
                                                            'guide' &&
                                                        !current.content
                                                            ? guideStarterSource
                                                            : current.content,
                                                };
                                            });
                                        }}
                                        disabled={
                                            selectedProject?.kind === 'guide'
                                        }
                                        className={inputClassName}
                                    >
                                        <option value="snippet">
                                            Code snippet
                                        </option>
                                        <option value="guide">
                                            Step-by-step guide
                                        </option>
                                    </select>
                                    <span className="text-[9px] leading-4 text-code-faint">
                                        {selectedProject?.kind === 'guide'
                                            ? 'Guide collections contain guide files.'
                                            : 'Guides use step markers and fenced Markdown code blocks.'}
                                    </span>
                                    <FieldError message={errors.content_type} />
                                </label>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field
                                        label={
                                            isCreatingGuide
                                                ? 'Guide title'
                                                : 'Snippet title'
                                        }
                                        name="title"
                                        value={values.title ?? ''}
                                        autoFocus
                                        error={errors.title}
                                        onChange={setValue(setValues, 'title')}
                                    />
                                    <Field
                                        label="File name"
                                        name="filename"
                                        value={values.filename ?? ''}
                                        error={errors.filename}
                                        onChange={setValue(
                                            setValues,
                                            'filename',
                                        )}
                                    />
                                </div>
                                <LanguageField
                                    languageOptions={languageOptions}
                                    value={values.language ?? ''}
                                    error={errors.language}
                                    disabled={
                                        isCreatingClipboardFile &&
                                        isCreatingGuide
                                    }
                                    onChange={setValue(setValues, 'language')}
                                />
                                <FrameworkField
                                    frameworks={frameworks}
                                    selectedNames={selectedFrameworkNames}
                                    error={errors.frameworks}
                                    onToggle={(frameworkName) =>
                                        setSelectedFrameworkNames((current) =>
                                            current.includes(frameworkName)
                                                ? current.filter(
                                                      (name) =>
                                                          name !==
                                                          frameworkName,
                                                  )
                                                : [...current, frameworkName],
                                        )
                                    }
                                />
                                {!state.sourceClipboard ? (
                                    <label className="flex flex-col gap-1.5 text-xs text-code-muted">
                                        {isCreatingGuide
                                            ? 'Guide source'
                                            : 'Starting code'}
                                        <textarea
                                            name="content"
                                            value={values.content ?? ''}
                                            onChange={setValue(
                                                setValues,
                                                'content',
                                            )}
                                            rows={7}
                                            spellCheck={false}
                                            placeholder={
                                                isCreatingGuide
                                                    ? guideStarterSource
                                                    : undefined
                                            }
                                            className={`${inputClassName} h-auto resize-y py-2 font-mono text-[11px] leading-5`}
                                        />
                                        <FieldError message={errors.content} />
                                    </label>
                                ) : null}
                                <Field
                                    label="Tags"
                                    hint="Comma separated"
                                    name="tags"
                                    value={values.tags ?? ''}
                                    error={errors.tags}
                                    onChange={setValue(setValues, 'tags')}
                                />
                            </>
                        )}

                        {state.kind === 'create-variation' && (
                            <>
                                <Field
                                    label="Variation name"
                                    hint="For example: Compact or With pagination"
                                    name="name"
                                    value={values.name ?? ''}
                                    autoFocus
                                    error={errors.name}
                                    onChange={setValue(setValues, 'name')}
                                />
                                <label className="flex flex-col gap-1.5 text-xs text-code-muted">
                                    Starting code
                                    <textarea
                                        name="content"
                                        value={values.content ?? ''}
                                        onChange={setValue(
                                            setValues,
                                            'content',
                                        )}
                                        rows={7}
                                        spellCheck={false}
                                        className={`${inputClassName} h-auto resize-y py-2 font-mono text-[11px] leading-5`}
                                    />
                                    <FieldError message={errors.content} />
                                </label>
                            </>
                        )}

                        {state.kind === 'rename-variation' && (
                            <Field
                                label="Variation name"
                                name="name"
                                value={values.name ?? ''}
                                autoFocus
                                error={errors.name}
                                onChange={setValue(setValues, 'name')}
                            />
                        )}

                        {state.kind === 'rename' &&
                            (state.entity.type === 'project' ? (
                                <>
                                    <Field
                                        label="Name"
                                        name="name"
                                        value={values.name ?? ''}
                                        autoFocus
                                        error={errors.name}
                                        onChange={setValue(setValues, 'name')}
                                    />
                                    <LibraryCategoryField
                                        categories={libraryCategories}
                                        value={values.library_category_id ?? ''}
                                        error={errors.library_category_id}
                                        onChange={setValue(
                                            setValues,
                                            'library_category_id',
                                        )}
                                    />
                                    <label className="flex flex-col gap-1.5 text-xs text-code-muted">
                                        Type
                                        <select
                                            value={values.kind ?? 'project'}
                                            onChange={setValue(
                                                setValues,
                                                'kind',
                                            )}
                                            className={inputClassName}
                                        >
                                            <option value="project">
                                                Project
                                            </option>
                                            <option value="bundle">
                                                Snippet bundle
                                            </option>
                                            <option value="guide">
                                                Guide collection
                                            </option>
                                        </select>
                                    </label>
                                    <FrameworkField
                                        frameworks={frameworks}
                                        selectedNames={selectedFrameworkNames}
                                        error={errors.frameworks}
                                        onToggle={(frameworkName) =>
                                            setSelectedFrameworkNames(
                                                (current) =>
                                                    current.includes(
                                                        frameworkName,
                                                    )
                                                        ? current.filter(
                                                              (name) =>
                                                                  name !==
                                                                  frameworkName,
                                                          )
                                                        : [
                                                              ...current,
                                                              frameworkName,
                                                          ],
                                            )
                                        }
                                    />
                                    <Field
                                        label="Description"
                                        name="description"
                                        value={values.description ?? ''}
                                        error={errors.description}
                                        onChange={setValue(
                                            setValues,
                                            'description',
                                        )}
                                    />
                                </>
                            ) : (
                                <Field
                                    label={
                                        state.entity.type === 'snippet'
                                            ? state.entity.snippet
                                                  .content_type === 'guide'
                                                ? 'Guide title'
                                                : 'Snippet title'
                                            : 'Name'
                                    }
                                    name={
                                        state.entity.type === 'snippet'
                                            ? 'title'
                                            : 'name'
                                    }
                                    value={
                                        state.entity.type === 'snippet'
                                            ? (values.title ?? '')
                                            : (values.name ?? '')
                                    }
                                    autoFocus
                                    error={
                                        state.entity.type === 'snippet'
                                            ? errors.title
                                            : errors.name
                                    }
                                    onChange={setValue(
                                        setValues,
                                        state.entity.type === 'snippet'
                                            ? 'title'
                                            : 'name',
                                    )}
                                />
                            ))}

                        {state.kind === 'metadata' && (
                            <>
                                <label className="flex flex-col gap-1.5 text-xs text-code-muted">
                                    File type
                                    <select
                                        name="content_type"
                                        value={values.content_type ?? 'snippet'}
                                        onChange={setValue(
                                            setValues,
                                            'content_type',
                                        )}
                                        className={inputClassName}
                                    >
                                        <option value="snippet">
                                            Code snippet
                                        </option>
                                        <option value="guide">
                                            Step-by-step guide
                                        </option>
                                    </select>
                                    <FieldError message={errors.content_type} />
                                </label>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field
                                        label="Title"
                                        name="title"
                                        value={values.title ?? ''}
                                        autoFocus
                                        error={errors.title}
                                        onChange={setValue(setValues, 'title')}
                                    />
                                    <Field
                                        label="File name"
                                        name="filename"
                                        value={values.filename ?? ''}
                                        error={errors.filename}
                                        onChange={setValue(
                                            setValues,
                                            'filename',
                                        )}
                                    />
                                </div>
                                <LanguageField
                                    languageOptions={languageOptions}
                                    value={values.language ?? ''}
                                    error={errors.language}
                                    onChange={setValue(setValues, 'language')}
                                />
                                <FrameworkField
                                    frameworks={frameworks}
                                    selectedNames={selectedFrameworkNames}
                                    error={errors.frameworks}
                                    onToggle={(frameworkName) =>
                                        setSelectedFrameworkNames((current) =>
                                            current.includes(frameworkName)
                                                ? current.filter(
                                                      (name) =>
                                                          name !==
                                                          frameworkName,
                                                  )
                                                : [...current, frameworkName],
                                        )
                                    }
                                />
                                <Field
                                    label="Tags"
                                    hint="Comma separated"
                                    name="tags"
                                    value={values.tags ?? ''}
                                    error={errors.tags}
                                    onChange={setValue(setValues, 'tags')}
                                />
                                <label className="flex flex-col gap-1.5 text-xs text-code-muted">
                                    Description
                                    <textarea
                                        name="description"
                                        value={values.description ?? ''}
                                        onChange={setValue(
                                            setValues,
                                            'description',
                                        )}
                                        rows={3}
                                        className={`${inputClassName} h-auto resize-y py-2`}
                                    />
                                    <FieldError message={errors.description} />
                                </label>
                            </>
                        )}

                        {state.kind === 'create-preset' && (
                            <Field
                                label="Preset name"
                                hint="For example: Staging or Client A"
                                name="name"
                                value={values.name ?? ''}
                                autoFocus
                                error={errors.name}
                                onChange={setValue(setValues, 'name')}
                            />
                        )}
                    </form>
                )}

                {Object.keys(errors).length > 0 && !isDelete && (
                    <p className="text-[10px] text-rose-300">
                        Check the highlighted fields and try again.
                    </p>
                )}

                <DialogFooter>
                    <button
                        type="button"
                        onClick={onClose}
                        className="h-9 rounded-md border border-code-border px-3 text-xs text-code-muted transition hover:bg-code-hover hover:text-code-text"
                    >
                        Cancel
                    </button>
                    <button
                        type={isDelete ? 'button' : 'submit'}
                        form={isDelete ? undefined : 'workspace-dialog-form'}
                        disabled={isSubmitDisabled}
                        onClick={isDelete ? () => onSubmit({}) : undefined}
                        className={
                            isDelete
                                ? 'h-9 rounded-md bg-rose-400 px-3 text-xs font-semibold text-[#251012] transition hover:bg-rose-300 disabled:opacity-50'
                                : 'h-9 rounded-md bg-code-accent px-3 text-xs font-semibold text-code-canvas transition hover:bg-white disabled:opacity-50'
                        }
                    >
                        {processing
                            ? 'Working…'
                            : isDelete
                              ? 'Move to Trash'
                              : configuration.action}
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function Field({
    label,
    hint,
    name,
    value,
    error,
    autoFocus = false,
    onChange,
}: {
    label: string;
    hint?: string;
    name: string;
    value: string;
    error?: string;
    autoFocus?: boolean;
    onChange: (event: React.ChangeEvent<HTMLInputElement>) => void;
}) {
    return (
        <label className="flex min-w-0 flex-col gap-1.5 text-xs text-code-muted">
            <span className="flex items-center justify-between gap-2">
                {label}
                {hint && (
                    <span className="text-[9px] text-code-faint">{hint}</span>
                )}
            </span>
            <input
                name={name}
                value={value}
                autoFocus={autoFocus}
                onChange={onChange}
                className={inputClassName}
            />
            <FieldError message={error} />
        </label>
    );
}

function LibraryCategoryField({
    categories,
    value,
    error,
    onChange,
}: {
    categories: LibraryCategory[];
    value: string;
    error?: string;
    onChange: (event: React.ChangeEvent<HTMLSelectElement>) => void;
}) {
    return (
        <label className="flex flex-col gap-1.5 text-xs text-code-muted">
            Category
            <select
                name="library_category_id"
                value={value}
                onChange={onChange}
                className={inputClassName}
            >
                <option value="">Uncategorised</option>
                {categories.map((category) => (
                    <option key={category.id} value={category.id}>
                        {category.name}
                    </option>
                ))}
            </select>
            <FieldError message={error} />
        </label>
    );
}

function LanguageField({
    languageOptions,
    value,
    error,
    disabled = false,
    onChange,
}: {
    languageOptions: LanguageOption[];
    value: string;
    error?: string;
    disabled?: boolean;
    onChange: (event: React.ChangeEvent<HTMLSelectElement>) => void;
}) {
    const hasCurrentLanguage = languageOptions.some(
        (language) => language.value === value,
    );

    return (
        <label className="flex min-w-0 flex-col gap-1.5 text-xs text-code-muted">
            Language
            <select
                name="language"
                value={value}
                disabled={disabled}
                onChange={onChange}
                className={inputClassName}
            >
                {!hasCurrentLanguage && value !== '' && (
                    <option value={value}>{value}</option>
                )}
                {languageOptions.map((language) => (
                    <option key={language.value} value={language.value}>
                        {language.label}
                    </option>
                ))}
            </select>
            <FieldError message={error} />
        </label>
    );
}

function FrameworkField({
    frameworks,
    selectedNames,
    error,
    onToggle,
}: {
    frameworks: Framework[];
    selectedNames: string[];
    error?: string;
    onToggle: (frameworkName: string) => void;
}) {
    const sortedFrameworks = [...frameworks].sort(
        (left, right) =>
            Number(Boolean(right.is_pinned)) -
                Number(Boolean(left.is_pinned)) ||
            left.name.localeCompare(right.name),
    );

    return (
        <fieldset className="flex min-w-0 flex-col gap-1.5 text-xs text-code-muted">
            <legend>Frameworks</legend>
            {sortedFrameworks.length > 0 ? (
                <div className="flex flex-wrap gap-1.5 rounded-md border border-code-border bg-code-canvas p-2">
                    {sortedFrameworks.map((framework) => {
                        const isSelected = selectedNames.includes(
                            framework.name,
                        );

                        return (
                            <button
                                key={framework.id}
                                type="button"
                                aria-pressed={isSelected}
                                onClick={() => onToggle(framework.name)}
                                className={`inline-flex h-7 items-center gap-1.5 rounded-md border px-2 text-[10px] font-medium transition ${
                                    isSelected
                                        ? 'bg-code-hover text-code-text'
                                        : 'border-code-border text-code-muted hover:bg-code-hover hover:text-code-text'
                                }`}
                                style={
                                    isSelected
                                        ? {
                                              borderColor:
                                                  framework.color ?? undefined,
                                              color:
                                                  framework.color ?? undefined,
                                          }
                                        : undefined
                                }
                            >
                                <span
                                    className="size-1.5 rounded-full"
                                    style={{
                                        backgroundColor:
                                            framework.color ?? '#64748b',
                                    }}
                                />
                                {framework.name}
                                {isSelected && <Check className="size-3" />}
                            </button>
                        );
                    })}
                </div>
            ) : (
                <p className="rounded-md border border-dashed border-code-border px-3 py-2 text-[10px] text-code-faint">
                    No frameworks are available yet.
                </p>
            )}
            <FieldError message={error} />
        </fieldset>
    );
}

function FieldError({ message }: { message?: string }) {
    return message ? (
        <span className="text-[10px] text-rose-300">{message}</span>
    ) : null;
}

const inputClassName =
    'h-9 w-full rounded-md border border-code-border bg-code-canvas px-3 text-xs text-code-text outline-none placeholder:text-code-faint focus:border-code-accent/60 disabled:cursor-not-allowed disabled:opacity-50';

const guideStarterSource = [
    '{!# guide-step: first-step | First step #!}',
    '',
    'Explain what to do and why it matters.',
    '',
    '```bash',
    '# Add the command or code for this step.',
    '```',
    '',
    '{!# guide-step: next-step | Next step #!}',
    '',
    'Continue the process here.',
].join('\n');

function getProjectKindLabel(kind: SnippetProject['kind']): string {
    if (kind === 'bundle') {
        return 'Bundle';
    }

    return kind === 'guide' ? 'Guide collection' : 'Project';
}

function getDialogKey(state: WorkspaceDialogState): string {
    if (!state) {
        return 'closed';
    }

    if (state.kind === 'create-project') {
        return `${state.kind}-${state.category?.id ?? 'uncategorised'}`;
    }

    if (state.kind === 'create-folder') {
        return `${state.kind}-${state.project.id}-${state.parent?.id ?? 'root'}`;
    }

    if (state.kind === 'create-snippet') {
        return `${state.kind}-${state.project?.id ?? 'choose'}-${state.folder?.id ?? 'root'}-${state.sourceClipboard?.id ?? 'blank'}`;
    }

    if (
        state.kind === 'create-preset' ||
        state.kind === 'metadata' ||
        state.kind === 'create-variation'
    ) {
        return `${state.kind}-${state.snippet.id}`;
    }

    if (state.kind === 'rename-variation') {
        return `${state.kind}-${state.variation.id}`;
    }

    return `${state.kind}-${getEntityName(state.entity)}`;
}

function getInitialValues(
    state: WorkspaceDialogState,
    languageOptions: LanguageOption[],
): Record<string, string> {
    if (!state) {
        return {};
    }

    if (state.kind === 'create-project') {
        return {
            name: '',
            library_category_id: String(state.category?.id ?? ''),
            kind: 'project',
            description: '',
        };
    }

    if (state.kind === 'create-folder') {
        return { name: '' };
    }

    if (state.kind === 'create-snippet') {
        if (state.sourceClipboard) {
            return {
                ...getClipboardFileDefaults(
                    state.sourceClipboard,
                    languageOptions,
                ),
                tags: '',
                project_id: String(state.project?.id ?? ''),
                folder_id: String(state.folder?.id ?? ''),
            };
        }

        const defaultLanguage =
            languageOptions.find((language) => language.value === 'javascript')
                ?.value ??
            languageOptions[0]?.value ??
            'plaintext';
        const contentType =
            state.project?.kind === 'guide' ? 'guide' : 'snippet';
        const guideLanguage =
            languageOptions.find((language) => language.value === 'markdown')
                ?.value ?? 'markdown';

        return {
            title: '',
            filename: '',
            content_type: contentType,
            language: contentType === 'guide' ? guideLanguage : defaultLanguage,
            content: contentType === 'guide' ? guideStarterSource : '',
            tags: '',
            project_id: String(state.project?.id ?? ''),
            folder_id: String(state.folder?.id ?? ''),
        };
    }

    if (state.kind === 'create-variation') {
        return { name: '', content: state.source };
    }

    if (state.kind === 'rename-variation') {
        return { name: state.variation.name };
    }

    if (state.kind === 'create-preset') {
        return { name: '' };
    }

    if (state.kind === 'metadata') {
        return {
            title: state.snippet.title,
            filename: state.snippet.filename,
            content_type: state.snippet.content_type,
            language: state.snippet.language,
            description: state.snippet.description ?? '',
            tags: state.snippet.tags.map((tag) => tag.name).join(', '),
        };
    }

    if (state.kind === 'rename') {
        if (state.entity.type === 'project') {
            return {
                name: state.entity.project.name,
                library_category_id: String(
                    state.entity.project.library_category_id ?? '',
                ),
                kind: state.entity.project.kind,
                description: state.entity.project.description ?? '',
            };
        }

        return state.entity.type === 'snippet'
            ? { title: state.entity.snippet.title }
            : { name: getEntityName(state.entity) };
    }

    return {};
}

function getInitialFrameworkNames(state: WorkspaceDialogState): string[] {
    if (state?.kind === 'metadata') {
        return (state.snippet.frameworks ?? []).map(
            (framework) => framework.name,
        );
    }

    if (state?.kind === 'rename' && state.entity.type === 'project') {
        return (state.entity.project.frameworks ?? []).map(
            (framework) => framework.name,
        );
    }

    return [];
}

function getDialogConfiguration(state: NonNullable<WorkspaceDialogState>) {
    switch (state.kind) {
        case 'create-project':
            return {
                title: 'New workspace',
                description:
                    'Create a project, focused snippet bundle, or guide collection.',
                action: 'Create workspace',
            };
        case 'create-folder':
            return {
                title: 'New folder',
                description: `Add a folder inside ${state.parent?.name ?? state.project.name}.`,
                action: 'Create folder',
            };
        case 'create-snippet':
            if (state.sourceClipboard) {
                return {
                    title: 'Create file from clipboard',
                    description:
                        'Choose where this clipboard belongs and save its clips as one editable file.',
                    action: 'Create file',
                };
            }

            if (state.project?.kind === 'guide') {
                return {
                    title: 'New guide',
                    description:
                        'Create one step-by-step guide in this collection.',
                    action: 'Create guide',
                };
            }

            return {
                title: 'New snippet',
                description:
                    'Create one code snippet and choose exactly where it belongs.',
                action: 'Create snippet',
            };
        case 'create-variation':
            return {
                title: 'New variation',
                description:
                    'Start with a copy of the open code, then edit it independently.',
                action: 'Create variation',
            };
        case 'rename-variation':
            return {
                title: 'Rename variation',
                description: `Change the name of ${state.variation.name}.`,
                action: 'Save name',
            };
        case 'rename':
            if (state.entity.type === 'project') {
                return {
                    title: 'Edit workspace',
                    description:
                        'Update its name, type, framework tags, and description.',
                    action: 'Save workspace',
                };
            }

            return {
                title: `Rename ${state.entity.type}`,
                description: `Change the display name for ${getEntityName(state.entity)}.`,
                action: 'Save name',
            };
        case 'delete':
            return {
                title: `Move ${getEntityName(state.entity)} to Trash?`,
                description:
                    'Deleted items stay recoverable until you permanently delete them from Trash.',
                action: 'Move to Trash',
            };
        case 'metadata':
            return {
                title:
                    state.snippet.content_type === 'guide'
                        ? 'Guide details'
                        : 'Snippet details',
                description:
                    state.snippet.content_type === 'guide'
                        ? 'File type, language, frameworks, and tags make this guide easier to find.'
                        : 'Language, frameworks, and tags make this snippet easier to find.',
                action: 'Save details',
            };
        case 'create-preset':
            return {
                title: 'Save variable preset',
                description:
                    'Store the current variable values under a reusable name.',
                action: 'Create preset',
            };
    }
}

function normalizePayload(
    state: NonNullable<WorkspaceDialogState>,
    values: Record<string, string>,
    frameworkNames: string[],
): Record<string, FormDataConvertible> {
    if (state.kind === 'create-project') {
        return {
            ...values,
            library_category_id: values.library_category_id
                ? Number(values.library_category_id)
                : null,
            frameworks: frameworkNames,
        };
    }

    if (state.kind === 'create-folder') {
        return { name: values.name, parent_id: state.parent?.id ?? null };
    }

    if (state.kind === 'create-snippet') {
        const payload: Record<string, FormDataConvertible> = {
            ...values,
            project_id: values.project_id ? Number(values.project_id) : null,
            folder_id: values.folder_id ? Number(values.folder_id) : null,
            tags: (values.tags ?? '')
                .split(',')
                .map((tag) => tag.trim())
                .filter(Boolean),
            frameworks: frameworkNames,
        };

        if (state.sourceClipboard) {
            delete payload.content;
        }

        return payload;
    }

    if (state.kind === 'rename-variation') {
        return {
            name: values.name,
            content: state.variation.content,
        };
    }

    if (state.kind === 'metadata') {
        return {
            ...values,
            tags: (values.tags ?? '')
                .split(',')
                .map((tag) => tag.trim())
                .filter(Boolean),
            frameworks: frameworkNames,
        };
    }

    if (state.kind === 'rename' && state.entity.type === 'project') {
        return {
            ...values,
            library_category_id: values.library_category_id
                ? Number(values.library_category_id)
                : null,
            frameworks: frameworkNames,
        };
    }

    return values;
}

function getEntityName(entity: ExplorerEntity): string {
    if (entity.type === 'project') {
        return entity.project.name;
    }

    if (entity.type === 'folder') {
        return entity.folder.name;
    }

    return entity.snippet.filename;
}

function getFolderLabel(project: SnippetProject, folderId: number): string {
    const foldersById = new Map(
        project.folders.map((folder) => [folder.id, folder]),
    );
    const path: string[] = [];
    const visited = new Set<number>();
    let currentId: number | null = folderId;

    while (currentId !== null && !visited.has(currentId)) {
        const folder = foldersById.get(currentId);

        if (!folder) {
            break;
        }

        visited.add(currentId);
        path.unshift(folder.name);
        currentId = folder.parent_id;
    }

    return path.join(' / ');
}

function formatClipCount(count: number): string {
    return `${count} ${count === 1 ? 'clip' : 'clips'}`;
}

function setValue(
    setValues: React.Dispatch<React.SetStateAction<Record<string, string>>>,
    key: string,
) {
    return (
        event:
            | React.ChangeEvent<HTMLInputElement>
            | React.ChangeEvent<HTMLTextAreaElement>
            | React.ChangeEvent<HTMLSelectElement>,
    ) => {
        setValues((current) => ({ ...current, [key]: event.target.value }));
    };
}

<?php

namespace App\Actions\Clipboards;

use App\Models\ClipboardClip;
use App\Models\ClipboardSession;
use App\Models\Folder;
use App\Models\Snippet;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateClipboardClip
{
    public function __construct(private CreateClipboardSession $createClipboardSession) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $user, array $attributes): ClipboardClip
    {
        return DB::transaction(function () use ($user, $attributes): ClipboardClip {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $clipboardSessionId = Arr::get($attributes, 'clipboard_session_id');
            $clipboardSessionId = $clipboardSessionId === null
                ? null
                : Arr::integer($attributes, 'clipboard_session_id');
            $snippetId = Arr::get($attributes, 'snippet_id');
            $clipAttributes = Arr::except($attributes, [
                'clipboard_session_id',
                'snippet_id',
                'snippet_variation_id',
            ]);

            $clipboardSession = $this->resolveClipboardSession($lockedUser, $clipboardSessionId);
            if ($snippetId === null) {
                return $clipboardSession->clips()->create([
                    ...$clipAttributes,
                    'snippet_id' => null,
                    'snippet_variation_id' => null,
                    'language' => 'text',
                    'source_title' => 'Pasted content',
                    'source_filename' => 'clipboard-paste.txt',
                    'source_project' => null,
                    'source_folders' => [],
                    'source_variation' => 'System clipboard',
                ]);
            }

            $snippetId = Arr::integer($attributes, 'snippet_id');
            $snippetVariationId = Arr::integer($attributes, 'snippet_variation_id');
            $snippet = $lockedUser->snippets()
                ->with('project')
                ->findOrFail($snippetId);
            $snippetVariation = $snippet->variations()->findOrFail($snippetVariationId);

            return $clipboardSession->clips()->create([
                ...$clipAttributes,
                'snippet_id' => $snippet->id,
                'snippet_variation_id' => $snippetVariation->id,
                'language' => $snippet->language,
                'source_title' => $snippet->title,
                'source_filename' => $snippet->filename,
                'source_project' => $snippet->project?->name,
                'source_folders' => $this->sourceFolders($snippet),
                'source_variation' => $snippetVariation->name,
            ]);
        }, attempts: 3);
    }

    private function resolveClipboardSession(User $user, ?int $clipboardSessionId): ClipboardSession
    {
        if ($clipboardSessionId !== null) {
            return $user->clipboardSessions()->findOrFail($clipboardSessionId);
        }

        $activeClipboardSession = $user->clipboardSessions()
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        return $activeClipboardSession ?? $this->createClipboardSession->handle($user, null);
    }

    /** @return list<string> */
    private function sourceFolders(Snippet $snippet): array
    {
        if ($snippet->project_id === null || $snippet->folder_id === null) {
            return [];
        }

        $folders = Folder::query()
            ->where('project_id', $snippet->project_id)
            ->get(['id', 'parent_id', 'name'])
            ->keyBy('id');
        $folderNames = [];
        $folderId = $snippet->folder_id;
        $visited = [];

        while ($folderId !== null && ! isset($visited[$folderId])) {
            $visited[$folderId] = true;
            /** @var Folder|null $folder */
            $folder = $folders->get($folderId);

            if ($folder === null) {
                break;
            }

            array_unshift($folderNames, $folder->name);
            $folderId = $folder->parent_id;
        }

        return $folderNames;
    }
}

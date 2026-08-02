<?php

namespace App\Support\Snippets;

class SnippetLocation
{
    public static function key(?int $projectId, ?int $folderId): string
    {
        if ($folderId !== null) {
            return 'folder:'.$folderId;
        }

        if ($projectId !== null) {
            return 'project:'.$projectId;
        }

        return 'standalone';
    }
}

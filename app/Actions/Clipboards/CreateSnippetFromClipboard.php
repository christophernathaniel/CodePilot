<?php

namespace App\Actions\Clipboards;

use App\Actions\Snippets\CreateSnippet;
use App\Models\ClipboardClip;
use App\Models\ClipboardSession;
use App\Models\Snippet;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateSnippetFromClipboard
{
    private const MAX_CONTENT_LENGTH = 5_000_000;

    public function __construct(private CreateSnippet $createSnippet) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $user, ClipboardSession $clipboardSession, array $attributes): Snippet
    {
        return DB::transaction(function () use ($user, $clipboardSession, $attributes): Snippet {
            $lockedClipboardSession = $user->clipboardSessions()
                ->whereKey($clipboardSession->id)
                ->lockForUpdate()
                ->firstOrFail();
            /** @var Collection<int, ClipboardClip> $clips */
            $clips = $lockedClipboardSession->clips()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            if ($clips->isEmpty()) {
                throw ValidationException::withMessages([
                    'clipboard' => __('The clipboard must contain at least one clip.'),
                ]);
            }

            $contentType = Arr::string($attributes, 'content_type');
            $content = $contentType === Snippet::CONTENT_TYPE_GUIDE
                ? $this->guideContent($clips)
                : $clips->pluck('content')->implode("\n\n");

            if (Str::length($content) > self::MAX_CONTENT_LENGTH) {
                throw ValidationException::withMessages([
                    'clipboard' => __('The generated file may not be greater than 5,000,000 characters.'),
                ]);
            }

            $attributes['content'] = $content;

            if ($contentType === Snippet::CONTENT_TYPE_GUIDE) {
                $attributes['language'] = 'markdown';
            }

            return $this->createSnippet->handle($user, $attributes);
        }, attempts: 3);
    }

    /** @param Collection<int, ClipboardClip> $clips */
    private function guideContent(Collection $clips): string
    {
        return $clips
            ->map(fn (ClipboardClip $clip): string => $this->guideStep($clip))
            ->implode("\n\n");
    }

    private function guideStep(ClipboardClip $clip): string
    {
        $title = Str::of($clip->source_title)
            ->replace(["\r", "\n", '#!}'], [' ', ' ', ''])
            ->squish()
            ->limit(255, '')
            ->toString();
        $title = $title !== '' ? $title : 'Clip '.$clip->id;
        $sourcePath = implode(' / ', $this->sourcePath($clip));
        $variation = Str::of($clip->source_variation)->squish()->toString();
        $lineLabel = $clip->line_start === $clip->line_end
            ? 'Line '.$clip->line_start
            : 'Lines '.$clip->line_start.'–'.$clip->line_end;
        $fence = $this->codeFence($clip->content);
        $closingLineBreak = preg_match('/(?:\r\n|\n|\r)\z/', $clip->content) === 1 ? '' : "\n";

        return "{!# guide-step: clip-{$clip->id} | {$title} #!}\n\n"
            ."Source: {$sourcePath}. Variation: {$variation}. {$lineLabel}.\n\n"
            ."{$fence}{$clip->language}\n{$clip->content}{$closingLineBreak}{$fence}";
    }

    /** @return list<string> */
    private function sourcePath(ClipboardClip $clip): array
    {
        $sourceFolders = $clip->getAttribute('source_folders');
        $folders = is_array($sourceFolders)
            ? array_values(array_filter($sourceFolders, is_string(...)))
            : [];
        $segments = [
            $clip->source_project ?? 'Standalone',
            ...$folders,
            $clip->source_filename,
        ];

        return array_map(
            fn (string $segment): string => Str::of($segment)->squish()->toString(),
            $segments,
        );
    }

    private function codeFence(string $content): string
    {
        preg_match_all('/`+/', $content, $matches);
        $longestRun = collect($matches[0])->max(fn (string $run): int => Str::length($run)) ?? 0;

        return str_repeat('`', max(3, $longestRun + 1));
    }
}

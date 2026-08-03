<?php

namespace Database\Factories;

use App\Models\ClipboardClip;
use App\Models\ClipboardSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClipboardClip>
 */
class ClipboardClipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clipboard_session_id' => ClipboardSession::factory(),
            'snippet_id' => null,
            'snippet_variation_id' => null,
            'content' => "const message = 'Hello, world!';",
            'language' => 'javascript',
            'representation' => 'source',
            'source_title' => 'Hello world',
            'source_filename' => 'hello-world.js',
            'source_project' => null,
            'source_folders' => [],
            'source_variation' => 'Default',
            'line_start' => 1,
            'line_end' => 1,
        ];
    }
}

<?php

use App\Support\Snippets\GuideStepParser;

test('files without complete line markers have no guide steps', function () {
    $parser = new GuideStepParser;

    expect($parser->parse('Use this {!# guide-step: inline | Inline #!} marker.'))
        ->toBe([])
        ->and($parser->parse("{!# guide-step: missing_title #!}\nInstructions"))
        ->toBe([])
        ->and($parser->parse("{!# guide-step: invalid key | Invalid #!}\nInstructions"))
        ->toBe([]);
});

test('it parses ordered guide steps with instructions and fenced code blocks', function () {
    $source = <<<'GUIDE'
{!# guide-step: install | Install the tools #!}
Install Docker Desktop, then verify the CLI is available.

```bash
docker --version
```

Continue only after the command succeeds.

{!# guide-step: configure-wordpress | Configure WordPress #!}
Create the WordPress configuration.

```php
<?php
define('WP_DEBUG', true);
```

```javascript
console.log('ready');
```
GUIDE;

    $steps = (new GuideStepParser)->parse($source);

    expect($steps)->toHaveCount(2)
        ->and($steps[0])->toMatchArray([
            'key' => 'install',
            'title' => 'Install the tools',
            'position' => 1,
            'marker_line' => 1,
            'start_line' => 2,
            'end_line' => 9,
            'instructions' => "Install Docker Desktop, then verify the CLI is available.\n\nContinue only after the command succeeds.",
        ])
        ->and($steps[0]['code_blocks'])->toBe([
            [
                'language' => 'bash',
                'content' => "docker --version\n",
                'start_line' => 5,
                'end_line' => 5,
            ],
        ])
        ->and($steps[1]['code_blocks'])->toHaveCount(2)
        ->and($steps[1]['code_blocks'][0])->toMatchArray([
            'language' => 'php',
            'content' => "<?php\ndefine('WP_DEBUG', true);\n",
            'start_line' => 14,
            'end_line' => 15,
        ])
        ->and($steps[1]['code_blocks'][1])->toMatchArray([
            'language' => 'javascript',
            'content' => "console.log('ready');\n",
            'start_line' => 19,
            'end_line' => 19,
        ]);
});

test('it supports tilde fences plaintext blocks crlf and duplicate keys', function () {
    $source = "{!# guide-step: Run | First run #!}\r\nDo it.\r\n~~~sql extra-metadata\r\nSELECT 1;\r\n~~~\r\n{!# guide-step: run | Run it again #!}\r\n````\r\nplain\r\n````\r\n";

    $steps = (new GuideStepParser)->parse($source);

    expect($steps)->toHaveCount(2)
        ->and($steps[0]['key'])->toBe('run')
        ->and($steps[0]['code_blocks'][0]['language'])->toBe('sql')
        ->and($steps[0]['code_blocks'][0]['content'])->toBe("SELECT 1;\r\n")
        ->and($steps[1]['key'])->toBe('run#2')
        ->and($steps[1]['code_blocks'][0]['language'])->toBe('plaintext')
        ->and($steps[1]['code_blocks'][0]['content'])->toBe("plain\r\n");
});

test('it leaves incomplete fences in the instructions', function () {
    $source = "{!# guide-step: review | Review the result #!}\nDo not lose this:\n```php\necho 'unfinished';";

    $step = (new GuideStepParser)->parse($source)[0];

    expect($step['code_blocks'])->toBe([])
        ->and($step['instructions'])->toBe("Do not lose this:\n```php\necho 'unfinished';");
});

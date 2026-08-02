<?php

use App\Support\Snippets\TemplateVariableParser;

test('it extracts template variable defaults without losing punctuation', function () {
    $variables = (new TemplateVariableParser)->parse(<<<'CODE'
const url = '{{{base_url:https://api.example.com:v1}}}';
const token = '{{{api_token:demo-token}}}';
const again = '{{{base_url:https://fallback.example.com}}}';
CODE);

    expect($variables)->toBe([
        'base_url' => 'https://api.example.com:v1',
        'api_token' => 'demo-token',
    ]);
});

test('it ignores malformed variables', function () {
    $variables = (new TemplateVariableParser)->parse(
        '{{{missing_default}}} {{{invalid-name:value}}} {{{valid_name:}}}',
    );

    expect($variables)->toBe(['valid_name' => '']);
});

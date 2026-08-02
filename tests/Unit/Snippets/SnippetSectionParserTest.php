<?php

use App\Support\Snippets\SnippetSectionParser;

test('files without complete line markers have no embedded snippets', function () {
    $parser = new SnippetSectionParser;

    expect($parser->parse("<?php\nreturn true; // {!# snippet: inline_marker #!}\n"))
        ->toBe([])
        ->and($parser->parse("const marker = '{!# snippet: string_marker #!}';"))
        ->toBe([])
        ->and($parser->parse("{!# snippet missing_colon #!}\nreturn true;"))
        ->toBe([]);
});

test('it accepts bare markers and language safe comment wrappers', function () {
    $source = <<<'SOURCE'
// {!# snippet: php_or_javascript #!}
return true;
# {!# snippet: yaml_or_dotenv #!}
enabled: true
/* {!# snippet: css #!} */
.card { display: grid; }
<!-- {!# snippet: html #!} -->
<article>Card</article>
{# {!# snippet: twig #!} #}
{{ card.title }}
-- {!# snippet: sql #!}
SELECT * FROM cards;
{!# snippet: bare #!}
plain text
SOURCE;

    $sections = (new SnippetSectionParser)->parse($source);

    expect(array_column($sections, 'name'))->toBe([
        'php_or_javascript',
        'yaml_or_dotenv',
        'css',
        'html',
        'twig',
        'sql',
        'bare',
    ])->and($sections[0]['content'])->toBe("return true;\n");
});

test('it parses multiple embedded snippets and preserves their exact bodies', function () {
    $source = <<<'SOURCE'
This preamble belongs to the file.

{!# snippet: theme_setup #!}
add_action('after_setup_theme', 'blueprint_setup');

{!# snippet: register-block.style #!}
register_block_style('core/button', ['name' => 'outline']);
SOURCE;

    $sections = (new SnippetSectionParser)->parse($source);

    expect($sections)->toHaveCount(2)
        ->and($sections[0])->toMatchArray([
            'key' => 'theme_setup',
            'name' => 'theme_setup',
            'label' => 'Theme Setup',
            'position' => 1,
            'marker_line' => 3,
            'start_line' => 4,
            'end_line' => 5,
            'content' => "add_action('after_setup_theme', 'blueprint_setup');\n\n",
        ])
        ->and($sections[1])->toMatchArray([
            'key' => 'register-block.style',
            'label' => 'Register Block Style',
            'position' => 2,
            'marker_line' => 6,
            'start_line' => 7,
            'end_line' => 7,
            'content' => "register_block_style('core/button', ['name' => 'outline']);",
        ]);
});

test('it handles crlf, unicode, adjacent markers, and duplicate names', function () {
    $source = "{!# snippet: card #!}\r\n<h2>Café</h2>\r\n{!# snippet: card #!}\r\n{!# snippet: empty #!}\r\n";

    $sections = (new SnippetSectionParser)->parse($source);

    expect($sections)->toHaveCount(3)
        ->and($sections[0]['key'])->toBe('card')
        ->and($sections[0]['content'])->toBe("<h2>Café</h2>\r\n")
        ->and($sections[0]['start_line'])->toBe(2)
        ->and($sections[0]['end_line'])->toBe(2)
        ->and($sections[1]['key'])->toBe('card#2')
        ->and($sections[1]['content'])->toBe('')
        ->and($sections[2]['key'])->toBe('empty')
        ->and($sections[2]['content'])->toBe('');
});

test('it uses stable labels for acronyms and camel case names', function () {
    $source = "{!# snippet: XMLParser #!}\nxml\n{!# snippet: apiURL #!}\nurl\n{!# snippet: FOO_BAR #!}\nfoo";

    expect(array_column((new SnippetSectionParser)->parse($source), 'label'))
        ->toBe(['XMLParser', 'Api URL', 'FOO BAR']);
});

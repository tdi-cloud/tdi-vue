<?php

use App\Support\HtmlSanitizer;

function sanitizeHtml(?string $html): string
{
    return (new HtmlSanitizer)->sanitize($html);
}

test('safe formatting tags are kept', function () {
    $html = '<h1>Title</h1><h2>Sub</h2><h3>Small</h3><p><strong>b</strong> <em>i</em> <u>u</u> <s>s</s><br></p>'
        .'<ul><li>one</li></ul><ol><li>two</li></ol><blockquote>quote</blockquote><pre>code block</pre><p><code>x</code></p>';

    expect(sanitizeHtml($html))->toBe($html);
});

test('scripts, styles and other dangerous elements are removed with their content', function () {
    expect(sanitizeHtml('<p>Hi</p><script>alert(1)</script><style>p{}</style><iframe src="https://x.test"></iframe><img src=x onerror=alert(1)>'))
        ->toBe('<p>Hi</p>');
});

test('event handler and other attributes are removed', function () {
    expect(sanitizeHtml('<p onclick="x()" class="foo" id="bar" data-x="1">Hi</p>'))->toBe('<p>Hi</p>');
});

test('unknown tags are unwrapped and legacy tags are renamed', function () {
    expect(sanitizeHtml('<font color="red"><b>bold</b> <i>it</i> <strike>gone</strike></font><div>line</div>'))
        ->toBe('<strong>bold</strong> <em>it</em> <s>gone</s><p>line</p>');
});

test('links keep only safe hrefs and always open in a new tab', function () {
    expect(sanitizeHtml('<a href="https://tesda.gov.ph" onclick="x()">site</a>'))
        ->toBe('<a href="https://tesda.gov.ph" target="_blank" rel="noopener noreferrer">site</a>')
        ->and(sanitizeHtml('<a href="mailto:tdi@tesda.gov.ph">mail</a>'))
        ->toBe('<a href="mailto:tdi@tesda.gov.ph" target="_blank" rel="noopener noreferrer">mail</a>')
        ->and(sanitizeHtml('<a href="javascript:alert(1)">bad</a>'))->toBe('bad')
        ->and(sanitizeHtml('<a href="jav&#x09;ascript:alert(1)">bad</a>'))->toBe('bad')
        ->and(sanitizeHtml('<a href="data:text/html;base64,PHNjcmlwdD4=">bad</a>'))->toBe('bad');
});

test('span color and highlight styles are kept while unsafe styles are dropped', function () {
    expect(sanitizeHtml('<span style="color: rgb(230, 0, 0); background-color: #ffff00; position: fixed">hi</span>'))
        ->toBe('<span style="color: rgb(230, 0, 0); background-color: #ffff00">hi</span>')
        ->and(sanitizeHtml('<span style="color: expression(alert(1)); background-color: url(javascript:x)">hi</span>'))
        ->toBe('<span>hi</span>');
});

test('quill alignment and indent classes become inline styles', function () {
    expect(sanitizeHtml('<p class="ql-align-center">c</p><p class="ql-indent-2 ql-align-right">r</p><h2 class="ql-align-justify">j</h2>'))
        ->toBe('<p style="text-align: center">c</p><p style="padding-left: 6em; text-align: right">r</p><h2 style="text-align: justify">j</h2>');
});

test('checklists keep the list style that hides bullets', function () {
    expect(sanitizeHtml('<ul style="list-style-type: none; padding-left: 4px"><li>☑ done</li></ul>'))
        ->toBe('<ul style="list-style-type: none; padding-left: 4px"><li>☑ done</li></ul>');
});

test('non-ascii text and emoji survive', function () {
    expect(sanitizeHtml('<p>Señor — 📌 “quoted”</p>'))->toBe('<p>Señor — 📌 “quoted”</p>');
});

test('empty input returns an empty string', function (?string $input) {
    expect(sanitizeHtml($input))->toBe('');
})->with([null, '', '   ']);

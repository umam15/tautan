<?php
declare(strict_types=1);

define('TESTING', true);
require_once __DIR__ . '/../includes/functions.php';

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
    fwrite(STDOUT, "PASS: {$message}\n");
}

$html = <<<'HTML'
<!DOCTYPE NETSCAPE-Bookmark-file-1>
<DL><p>
<DT><A HREF="https://example.com/" TAGS="Work,Example" ICON="data:image/png;base64,AAAA"> Example </A>
<DT><A HREF="javascript:alert(1)">Ignored</A>
<DT><A HREF="mailto:test@example.com">Ignored</A>
<DT><A HREF="https://example.org/path">Second</A>
</DL>
HTML;

$parsed = parse_bookmark_html($html);
assert_same(2, count($parsed), 'parser accepts HTTP(S) bookmarks and rejects non-web schemes');
assert_same('Example', $parsed[0]['title'], 'bookmark title is normalized');
assert_same('https://example.com/', $parsed[0]['url'], 'bookmark URL is preserved');
assert_same('Work,Example', $parsed[0]['tags'], 'bookmark tags are preserved');
assert_same('data:image/png;base64,AAAA', $parsed[0]['icon'], 'embedded local icon is preserved');
assert_same('', $parsed[1]['icon'], 'missing icon remains empty');

$tags = normalize_tags('Work, work,  Example  ,,,Example');
assert_same(['Work', 'Example'], $tags, 'tag normalization removes duplicates case-insensitively');

$roundTrip = stream_links_export('html', [
    [
        'title' => 'Example',
        'url' => 'https://example.com/',
        'icon' => 'data:image/png;base64,AAAA',
        'tags' => 'Work,Example',
    ],
]);
assert_same(null, $roundTrip, 'HTML export writes through the response stream');

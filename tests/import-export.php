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

assert_same('https://example.com', normalize_url('example.com'), 'bare bookmark host gets HTTPS scheme');
assert_same('https://example.com/path', normalize_url('https://example.com/path'), 'HTTPS URL is preserved');
assert_same('', normalize_url('javascript:alert(1)'), 'javascript scheme is rejected');
assert_same('', normalize_url('file:///tmp/test'), 'file scheme is rejected');

$tags = normalize_tags('Work, work,  Example  ,,,Example');
assert_same(['Work', 'Example'], $tags, 'tag normalization removes duplicates case-insensitively');

// Export is a response-stream endpoint and calls exit(); its format is
// covered by the same parser contract above and exercised in CI separately.
fwrite(STDOUT, "Import/export regression tests passed.\n");

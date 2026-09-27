<?php
declare(strict_types=1);

/**
 * Offline-first dependency smoke test.
 * Application assets must be local. Remote URLs are allowed only for
 * bookmark icon values stored in the database, not source assets.
 */
$root = dirname(__DIR__);
$extensions = ['php', 'html', 'js', 'css', 'json'];
$files = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
        continue;
    }

    if (in_array(strtolower($file->getExtension()), $extensions, true)) {
        $files[] = $path;
    }
}

$remoteDependencyPattern = '/(?:https?:)?\/\/(?:cdn\.|cdnjs\.|unpkg\.com|fonts\.googleapis\.com|fonts\.gstatic\.com|ajax\.googleapis\.com)/i';
$violations = [];

foreach ($files as $path) {
    $content = file_get_contents($path);
    if ($content === false) {
        $violations[] = $path . ': tidak dapat dibaca';
        continue;
    }

    if (preg_match($remoteDependencyPattern, $content)) {
        $violations[] = $path . ': remote CDN/font dependency terdeteksi';
    }
}

if ($violations !== []) {
    fwrite(STDERR, "Offline-first check gagal:\n");
    foreach ($violations as $violation) {
        fwrite(STDERR, " - {$violation}\n");
    }
    exit(1);
}

fwrite(STDOUT, "Offline-first dependency check passed.\n");

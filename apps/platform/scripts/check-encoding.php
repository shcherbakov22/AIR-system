<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$extensions = ['php', 'vue', 'ts', 'js', 'json', 'md', 'css', 'scss', 'html', 'yml', 'yaml', 'xml'];
$skipPaths = [
    DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'node_modules'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR,
    DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR,
];
$mojibakePatterns = [
    'replacement-char' => "\xEF\xBF\xBD",
    'cp1251-utf8-d0' => "\xC3\x90",
    'cp1251-utf8-d1' => "\xC3\x91",
    'cp1251-utf8-v-io' => "\xD0\xB2\xD0\x82",
    'latin1-utf8-smart-quote-close' => "\xC3\xA2\xE2\x82\xAC\xE2\x80\x9D",
    'latin1-utf8-smart-quote-open' => "\xC3\xA2\xE2\x82\xAC\xC5\x93",
    'latin1-utf8-euro-prefix' => "\xC3\xA2\xE2\x82\xAC",
    'latin1-utf8-c3' => "\xC3\x83",
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$issues = [];

foreach ($iterator as $file) {
    if (! $file instanceof SplFileInfo || ! $file->isFile()) {
        continue;
    }

    $path = $file->getPathname();

    foreach ($skipPaths as $skipPath) {
        if (str_contains($path, $skipPath)) {
            continue 2;
        }
    }

    $extension = strtolower($file->getExtension());

    if (! in_array($extension, $extensions, true)) {
        continue;
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        $issues[] = [$path, 'Unreadable file'];
        continue;
    }

    if (str_starts_with($contents, "\xEF\xBB\xBF")) {
        $issues[] = [$path, 'UTF-8 BOM present'];
    }

    if (! mb_check_encoding($contents, 'UTF-8')) {
        $issues[] = [$path, 'Invalid UTF-8'];
        continue;
    }

    foreach ($mojibakePatterns as $label => $pattern) {
        if (str_contains($contents, $pattern)) {
            $issues[] = [$path, sprintf('Suspicious mojibake pattern %s', $label)];
            break;
        }
    }
}

if ($issues === []) {
    fwrite(STDOUT, "Encoding check passed.\n");
    exit(0);
}

foreach ($issues as [$path, $message]) {
    fwrite(STDERR, sprintf("%s: %s\n", $path, $message));
}

exit(1);

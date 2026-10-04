<?php

/**
 * Blade comment linter.
 *
 * Blade has no nested {{-- --}} syntax: the first "--}}" closes the comment and
 * everything after it is compiled as live markup. When that leaked markup
 * happens to be another component tag, the component renders itself and the
 * request dies with a memory-exhaustion error instead of a readable one.
 *
 * Detects the pattern by counting comment delimiters per file and flagging any
 * file where an odd/unbalanced count suggests an early close.
 *
 * Usage: php tools/check-blade-comments.php
 */
$root = dirname(__DIR__);
$views = $root.'/resources/views';

$problems = [];
$files = 0;

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($views));
foreach ($it as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $files++;
    $path = $file->getPathname();
    $contents = file_get_contents($path);

    // Strip real {{-- --}} pairs that are correctly balanced, then look for
    // leftover comment-looking text.
    $stripped = preg_replace('/\{\{--(.*?)--\}\}/s', '', $contents);

    // Anything still matching an opening delimiter means a comment never closed
    // (which nested comments cause) or was written with raw @php-style syntax.
    if (preg_match_all('/\{\{--/', $stripped, $m)) {
        $problems[] = [
            'file' => str_replace($root.'/', '', $path),
            'count' => count($m[0]),
            'why' => 'unclosed {{-- comment (likely a nested {{-- --}})',
        ];

        continue;
    }

    // A comment body that itself contains "<x-" is the dangerous shape: it means
    // live component markup sat inside a docblock.
    if (preg_match_all('/\{\{--(.*?)--\}\}/s', $contents, $blocks)) {
        foreach ($blocks[1] as $i => $body) {
            if (str_contains($body, '<x-') || str_contains($body, '@if') || str_contains($body, '@foreach')) {
                // Only a problem if the body ALSO contains an inner comment.
                if (str_contains($body, '{{--')) {
                    $problems[] = [
                        'file' => str_replace($root.'/', '', $path),
                        'count' => 1,
                        'why' => 'component markup inside a docblock that also contains a comment',
                    ];
                }
            }
        }
    }
}

printf("scanned %d blade files\n", $files);
printf("problems: %d\n\n", count($problems));

foreach ($problems as $p) {
    printf("  %s\n    %s\n", $p['file'], $p['why']);
}

exit($problems ? 1 : 0);

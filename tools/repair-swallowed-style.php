<?php

/**
 * Repair pass for swallowed style attributes.
 *
 * An earlier revision of migrate-inline-styles.php rebuilt <i> tags without
 * closing the class attribute, producing markup like:
 *
 *     <i class="bi bi-funnel text-slate-300 style="display:block"></i>
 *
 * The browser then treats everything from `style="` onward as part of the class
 * value and the tag loses its intended styling. This script closes the class
 * attribute at the right place and normalises the value.
 *
 * Usage: php tools/repair-swallowed-style.php [--write]
 */
$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/resources/views')
);

$fixed = 0;
$files = 0;

foreach ($it as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $src = file_get_contents($path);

    // class="<tokens> style="<decls>"  ->  class="<tokens>" style="<decls>"
    $out = preg_replace_callback(
        '/class="([^"]*?)\s+style="([^"]*)"/',
        function (array $m) {
            $classes = trim($m[1]);

            return 'class="'.$classes.'" style="'.$m[2].'"';
        },
        $src
    );

    // Second damage shape: an <i> whose class attribute was never closed, with
    // no style attribute at all:
    //
    //     <i class="bi bi-funnel text-slate-300></i>
    //
    // The content class cannot contain a quote, which is what makes this safe:
    // well-formed tags have a closing quote before the ">" and simply do not
    // match.
    $out = preg_replace_callback(
        '/(<(?:i|x-icon)\s+class=")((?:[^">])+)(\s*\/?>)/',
        function (array $m) {
            return $m[1].trim($m[2]).'"'.$m[3];
        },
        $out ?? $src
    );

    if ($out !== null && $out !== $src) {
        $files++;
        $fixed += preg_match_all('/class="[^"]*\bstyle\s*=/', $src);
        printf("  %s\n", str_replace($root.'/', '', $path));
        if ($write) {
            file_put_contents($path, $out);
        }
    }
}

printf("\ntags repaired: %d in %d files\n", $fixed, $files);
echo $write ? "WRITTEN\n" : "DRY RUN — pass --write to apply\n";

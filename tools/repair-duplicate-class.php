<?php

/**
 * repair-duplicate-class.php — merge tags that ended up with more than one
 * class="..." attribute.
 *
 * An earlier revision of migrate-declarations.php only looked for a class
 * attribute *before* the style attribute, so elements that declared class after
 * style were given a second class="...". Browsers honour only the first, so the
 * converted utilities were silently dropped.
 *
 * Only a bare class attribute counts: Alpine's :class and Blade's {{ $class }}
 * are different attributes and must be left alone.
 *
 * Usage: php tools/repair-duplicate-class.php [--write]
 */
$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/resources/views')
);

$fixedTags = 0;
$files = 0;

/**
 * Merge every class="..." in one opening tag down to the first occurrence.
 *
 * @return array{0: string, 1: int} [possibly rewritten tag, merges performed]
 */
function mergeTagClasses(string $tag): array
{
    // (?<![:\w-]) keeps ":class" and "wire:class" out of the match.
    if (! preg_match_all('/(?<![:\w-])class="([^"]*)"/', $tag, $m, PREG_OFFSET_CAPTURE)) {
        return [$tag, 0];
    }

    if (count($m[0]) < 2) {
        return [$tag, 0];
    }

    $tokens = [];
    foreach ($m[1] as $entry) {
        foreach (preg_split('/\s+/', trim($entry[0])) as $token) {
            if ($token !== '') {
                $tokens[$token] = true;
            }
        }
    }

    $merged = 'class="'.implode(' ', array_keys($tokens)).'"';
    $firstAt = $m[0][0][1];
    $length = strlen($m[0][0][0]);

    // Replace the first occurrence, then delete the rest back-to-front so the
    // earlier offsets stay valid.
    $out = substr_replace($tag, $merged, $firstAt, $length);

    foreach (array_reverse(array_slice($m[0], 1)) as [$match, $at]) {
        // Drop the single leading space that separated the attributes.
        $out = substr_replace($out, '', $at, strlen($match) + 1);
    }

    return [$out, count($m[0]) - 1];
}

foreach ($it as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $src = file_get_contents($file->getPathname());
    $out = $src;
    $fixes = 0;

    // Walk opening tags, consuming quoted attribute values as a unit so a ">"
    // inside a Blade expression cannot end the match early.
    $pattern = '/<[a-zA-Z][\w:-]*\b(?:[^>"]|"[^"]*")*>/';

    $out = preg_replace_callback($pattern, function (array $m) use (&$fixes) {
        [$tag, $n] = mergeTagClasses($m[0]);
        $fixes += $n;

        return $tag;
    }, $src);

    if ($fixes > 0) {
        $files++;
        $fixedTags += $fixes;
        printf("  %s (%d)\n", str_replace($root.'/resources/views/', '', $file->getPathname()), $fixes);

        if ($write) {
            file_put_contents($file->getPathname(), $out);
        }
    }
}

printf("\ntags repaired: %d in %d files\n", $fixedTags, $files);
echo $write ? "WRITTEN\n" : "DRY RUN — pass --write to apply\n";

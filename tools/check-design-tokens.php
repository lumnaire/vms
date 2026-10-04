<?php

/**
 * Token coverage check.
 *
 * Scans every .css and .blade.php for var(--token) references and reports any
 * that are not defined in resources/css/app.css. Missing tokens silently fall
 * back to nothing, which is how a refactor like this quietly breaks.
 *
 * Usage: php tools/check-design-tokens.php
 */
$root = dirname(__DIR__);
$cssFile = $root.'/resources/css/app.css';
$css = file_get_contents($cssFile);

// 1. Collect every custom property defined in app.css.
$defined = [];
preg_match_all('/(--[a-z0-9-]+)\s*:/i', $css, $m);
foreach ($m[1] as $name) {
    $defined[strtolower($name)] = true;
}

// Tailwind v4 @theme emits utility classes from these names too, so include
// the slate/brand ramps Tailwind provides for free.
$tailwindPrefixes = ['--tw-', '--color-slate-', '--font-', '--spacing-', '--radius-', '--text-', '--shadow-'];

// 2. Scan all stylesheets and templates for references.
$targets = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources'));
foreach ($it as $file) {
    if ($file->isFile() && preg_match('/\.(css|blade\.php|js)$/', $file->getFilename())) {
        $targets[] = $file->getPathname();
    }
}

$used = [];
foreach ($targets as $path) {
    $contents = file_get_contents($path);
    if (! preg_match_all('/var\(\s*(--[a-z0-9-]+)/i', $contents, $m)) {
        continue;
    }
    foreach ($m[1] as $name) {
        $name = strtolower($name);
        $used[$name][] = $path;
    }
}

$missing = [];
foreach ($used as $name => $files) {
    if (isset($defined[$name])) {
        continue;
    }
    // Ignore Tailwind's generated runtime variables and its own palette.
    $isTailwind = false;
    foreach ($tailwindPrefixes as $prefix) {
        if (str_starts_with($name, $prefix)) {
            $isTailwind = true;
            break;
        }
    }
    if (! $isTailwind) {
        $missing[$name] = array_unique($files);
    }
}

printf("defined tokens : %d\n", count($defined));
printf("referenced     : %d\n", count($used));
printf("missing        : %d\n\n", count($missing));

foreach ($missing as $name => $files) {
    echo "MISSING {$name}\n";
    foreach ($files as $f) {
        echo '    '.str_replace($root.'/', '', $f)."\n";
    }
}

exit($missing ? 1 : 0);

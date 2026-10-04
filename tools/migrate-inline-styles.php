<?php

/**
 * Inline-style migration.
 *
 * Rewrites the mechanical part of the styling debt:
 *
 *   1. Bootstrap Icons sized with `style="font-size: Npx"` become <x-icon size="...">,
 *      so icon sizing is uniform.
 *   2. Hardcoded hex colours become the nearest design token, emitted as a
 *      Tailwind utility (e.g. #059669 -> text-success-600), so status colours
 *      can no longer drift.
 *
 * Colour matching is NOT hardcoded: the token list is parsed straight out of
 * resources/css/app.css and each hex is matched to the closest token by RGB
 * distance, restricted to the ramp that makes semantic sense for that hue.
 *
 * Anything it does not recognise is left untouched and reported, so a run is
 * always safe to inspect with `git diff`.
 *
 * Usage:
 *   php tools/migrate-inline-styles.php          # report only
 *   php tools/migrate-inline-styles.php --write  # apply
 */
$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);

// ── 1. Parse the token palette out of app.css ────────────────────────────────
$css = file_get_contents($root.'/resources/css/app.css');

preg_match_all('/(--color-([a-z]+)-(\d+))\s*:\s*(#[0-9a-fA-F]{3,8})\s*;/', $css, $m, PREG_SET_ORDER);

$ramp = ['navy' => [], 'brand' => [], 'slate' => [], 'success' => [], 'warning' => [], 'danger' => [], 'info' => []];
foreach ($m as $t) {
    $name = $t[1];
    $family = $t[2];
    $step = (int) $t[3];
    $hex = $t[4];

    if (! isset($ramp[$family])) {
        continue;
    }
    $ramp[$family][$name] = rgb($hex);
}

function rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }

    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}

function distance(array $a, array $b): float
{
    // Perceptual-ish weighting; green carries the most luminance.
    $dr = $a[0] - $b[0];
    $dg = $a[1] - $b[1];
    $db = $a[2] - $b[2];

    return sqrt(0.30 * $dr * $dr + 0.59 * $dg * $dg + 0.11 * $db * $db);
}

/** Hue in degrees, or null for greys (no meaningful hue). */
function hue(array $rgb): ?float
{
    [$r, $g, $b] = array_map(fn ($v) => $v / 255, $rgb);
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $d = $max - $min;

    if ($d == 0.0) {
        return null;
    }

    // Decide grey vs tinted by comparing against an equal-lightness grey rather
    // than by raw chroma. Both #94a3b8 and #1e293b are desaturated blues with
    // high *relative* chroma because they are light / very dark respectively,
    // yet both are plainly neutral.
    $lightness = ($max + $min) / 2;
    $grey = $lightness * 255;
    $neutralDistance = distance($rgb, [$grey, $grey, $grey]);

    if ($neutralDistance <= 22) {
        return null;
    }

    switch (true) {
        case $max === $r:
            $h = fmod(($g - $b) / $d, 6);
            break;
        case $max === $g:
            $h = ($b - $r) / $d + 2;
            break;
        default:
            $h = ($r - $g) / $d + 4;
            break;
    }

    return fmod($h * 60 + 360, 360);
}

/**
 * Which ramp can represent this colour at all?
 *
 * Choosing the family by hue first is essential. Picking "the first family with
 * a close enough step" mapped #ef4444 (red) onto warning-700 just because amber
 * happened to sit within tolerance, and recoloured a red icon amber.
 */
function familyFor(string $hex): array
{
    $rgb = rgb($hex);
    $h = hue($rgb);

    if ($h === null) {
        return ['slate', 'navy'];
    }

    if ($h >= 340 || $h < 15) {
        return ['danger', 'warning'];
    }
    if ($h < 45) {
        return ['warning', 'danger'];
    }
    if ($h < 70) {
        return ['warning'];
    }
    if ($h < 165) {
        return ['success'];
    }
    if ($h < 200) {
        return ['info', 'brand', 'success'];
    }
    if ($h < 260) {
        return ['brand', 'info'];
    }

    return ['brand', 'navy', 'info'];
}

/**
 * Map a hex to the nearest token, returning a Tailwind utility such as
 * "text-success-600". Returns null when nothing is close enough, so the caller
 * can leave the declaration alone rather than recolour it wrongly.
 *
 * $ramp is the FULL ramp table; the family is chosen internally by hue.
 */
function nearest(string $hex, array $ramp, string $property): ?string
{
    $prefix = match ($property) {
        'color' => 'text-',
        'background' => 'bg-',
        'border-color', 'border' => 'border-',
        'fill' => 'fill-',
        'stroke' => 'stroke-',
        default => null,
    };

    if ($prefix === null) {
        return null;
    }

    // Pure black/white are framework defaults rather than design decisions, so
    // they map to Tailwind's own text-white / text-black and never to a ramp
    // step that would silently shift the value.
    $lower = strtolower($hex);
    if ($lower === '#fff' || $lower === '#ffffff') {
        return $property === 'color' ? 'text-white' : null;
    }
    if ($lower === '#000' || $lower === '#000000') {
        return $property === 'color' ? 'text-black' : null;
    }

    $target = rgb($hex);

    foreach (familyFor($hex) as $family) {
        $best = null;
        $bestD = INF;

        foreach ($ramp[$family] ?? [] as $name => $candidate) {
            $d = distance($target, $candidate);
            if ($d < $bestD) {
                $bestD = $d;
                $best = $name;
            }
        }

        // 28 is "visually the same colour". Anything further is left alone
        // rather than silently recoloured — a slightly-off match is worse than
        // an explicit hardcoded value.
        if ($best !== null && $bestD <= 28) {
            // Token keys look like --color-success-500; the utility wants
            // success-500.
            return $prefix.preg_replace('/^--color-/', '', $best);
        }
    }

    return null;
}

// ── 1b. Self-test the colour mapping ────────────────────────────────────────
// Hue-restricted nearest-token matching is easy to get subtly wrong, so the
// mapping is pinned against known-correct expectations.
if (in_array('--selftest', $argv, true)) {
    $expect = [
        '#ef4444' => 'text-danger-500',     // exact token; must not fall to amber
        '#dc2626' => 'text-danger-600',     // exact token
        '#10b981' => 'text-success-500',    // exact token
        '#059669' => 'text-success-600',    // exact token
        '#d97706' => 'text-warning-600',    // exact token
        '#1d4ed8' => 'text-brand-600',      // exact token
        '#94a3b8' => 'text-slate-400',      // exact token
        '#cbd5e1' => 'text-slate-300',      // pale blue-grey, not brand
        '#1e293b' => 'text-slate-800',      // exact token
        '#ffffff' => 'text-white',          // framework default, not a ramp step
        '#000000' => 'text-black',
        '#8b5cf6' => null,                  // violet: outside every ramp
        '#0284c7' => null,                  // sky-600: no sky ramp
        '#16a34a' => 'text-success-600',    // green-600 ~ emerald-600
    ];

    $bad = 0;
    foreach ($expect as $hex => $want) {
        $got = nearest($hex, $ramp, 'color');
        $ok = $got === $want;
        $bad += $ok ? 0 : 1;
        printf("%s %-9s want %-18s got %s\n", $ok ? 'ok  ' : 'FAIL', $hex, var_export($want, true), var_export($got, true));
    }

    exit($bad ? 1 : 0);
}

// ── 2. Icon size scale ──────────────────────────────────────────────────────
$iconSizes = [
    '9' => '2xs', '10' => '2xs', '11' => 'xs', '12' => 'sm', '13' => 'md',
    '14' => 'md', '15' => 'base', '16' => 'base', '17' => 'lg', '18' => 'lg',
    '20' => 'xl', '22' => '2xl', '24' => '2xl', '26' => '2xl', '30' => '3xl',
    '36' => '4xl', '44' => '5xl',
];

$changedFiles = 0;
$changedIcons = 0;
$changedColors = 0;
$unmapped = [];

// ── 3. Walk the templates ───────────────────────────────────────────────────
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views'));
foreach ($it as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $rel = str_replace($root.'/', '', $path);
    $src = file_get_contents($path);
    $original = $src;

    // 3a. Icon tags: <i class="bi bi-name ..." style="..."></i>
    $src = preg_replace_callback(
        '/<i\s+class="(bi\s+bi-[a-z0-9-]+(?:\s+[^"]*)?)"\s*(?:style="([^"]*)")?\s*\/?>(?:<\/i>)?/i',
        function (array $mm) use ($iconSizes, $ramp, &$changedIcons, &$unmapped, $rel) {
            $classAttr = trim($mm[1]);
            $style = $mm[2] ?? '';

            // Split the icon name from any utility classes riding along.
            // "bi" is the icon font hook, not a utility, so it is dropped.
            $tokens = preg_split('/\s+/', $classAttr);
            $iconName = null;
            $utilities = [];
            foreach ($tokens as $tk) {
                if ($tk === 'bi') {
                    continue;
                }
                if (str_starts_with($tk, 'bi-') && $iconName === null) {
                    $iconName = $tk;
                } else {
                    $utilities[] = $tk;
                }
            }
            if ($iconName === null) {
                return $mm[0];
            }

            $size = null;
            $residual = [];
            foreach (array_filter(array_map('trim', explode(';', $style))) as $decl) {
                if (! str_contains($decl, ':')) {
                    continue;
                }
                [$prop, $value] = explode(':', $decl, 2);
                $prop = trim($prop);
                $value = trim($value);

                // font-size -> the icon scale
                if ($prop === 'font-size' && preg_match('/^(\d+(?:\.\d+)?)px$/', $value, $fs)) {
                    $px = (int) round((float) $fs[1]);
                    $key = (string) $px;
                    if (isset($iconSizes[$key])) {
                        $size = $iconSizes[$key];
                    } else {
                        // No scale step this small; keep the declaration.
                        $unmapped["font-size:{$px}px"][] = $rel;
                        $residual[] = $prop.':'.$value;
                    }

                    continue;
                }

                // color / background -> nearest token
                if (in_array($prop, ['color', 'background', 'background-color', 'border-color'], true)
                    && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value, $cm)) {
                    $hex = $cm[0];
                    $kind = $prop === 'color' ? 'color' : (str_contains($prop, 'border') ? 'border' : 'background');

                    $changedColors++;
                    $util = nearest($hex, $ramp, $kind);

                    if ($util) {
                        $utilities[] = $util;

                        continue;
                    }
                    $unmapped[$hex][] = $rel;
                }

                $residual[] = $prop.':'.$value;
            }

            // Fully consumed: emit the component.
            if (empty($residual) && ($size || $utilities)) {
                $changedIcons++;
                $attr = 'name="'.$iconName.'"';
                if ($size) {
                    $attr .= ' size="'.$size.'"';
                }
                if ($utilities) {
                    $attr .= ' class="'.implode(' ', array_unique($utilities)).'"';
                }

                return '<x-icon '.$attr.' />';
            }

            // Partially consumed: keep <i> but drop what we mapped. The icon
            // font hook has to stay for the glyph to render, and the class
            // attribute MUST be closed before the style attribute is opened.
            $out = '<i class="bi '.$iconName;
            if ($utilities) {
                $out .= ' '.implode(' ', array_unique($utilities));
            }
            $out .= '"';
            if ($residual) {
                $out .= ' style="'.implode('; ', $residual).'"';
            }

            return $out.'></i>';
        },
        $src
    );

    if ($src !== $original) {
        $changedFiles++;
        printf("  %s\n", $rel);
        if ($write) {
            file_put_contents($path, $src);
        }
    }
}

printf("\nfiles touched : %d\n", $changedFiles);
printf("icons replaced: %d\n", $changedIcons);

if ($unmapped) {
    printf("\nleft as-is (%d distinct, too far from any token to recolour safely):\n", count($unmapped));
    ksort($unmapped);
    foreach ($unmapped as $value => $files) {
        printf("  %-22s %s\n", $value, implode(', ', array_unique($files)));
    }
}

echo $write ? "\nWRITTEN\n" : "\nDRY RUN — pass --write to apply\n";

<?php

/**
 * migrate-declarations.php — replace recurring inline CSS *declarations* with
 * the Tailwind utilities already generated from the design system.
 *
 * migrate-inline-styles.php already removed the colours that had a matching
 * token. What remains is dominated by a small number of repeated declarations
 * (the card shadow appears 60 times, the uppercase overline 59 times, and so
 * on). This tool maps each declaration to a utility, folds the result into the
 * element's existing class attribute, and drops the style attribute once it is
 * empty.
 *
 * Only whole declarations are matched, and the values below come from the token
 * ramps in @theme, so nothing shifts visually. The one exception is called out
 * inline below.
 *
 * Usage:
 *   php tools/migrate-declarations.php --selftest
 *   php tools/migrate-declarations.php            # dry run
 *   php tools/migrate-declarations.php --write
 */

/**
 * Declaration => utility. Keys are normalised, so spacing and the trailing
 * semicolon do not matter.
 */
const RULES = [
    // ── Typography ────────────────────────────────────────────────────────
    'font-size:9px'                => 'text-[9px]',
    'font-size:10px'               => 'text-[10px]',
    'font-size:10.5px'             => 'text-[10.5px]',
    'font-size:11px'               => 'text-[11px]',
    'font-size:11.5px'             => 'text-[11.5px]',
    'font-size:12px'               => 'text-[12px]',
    'font-size:12.5px'             => 'text-[12.5px]',
    'font-size:13px'               => 'text-[13px]',
    'font-size:13.5px'             => 'text-[13.5px]',
    'font-size:14px'               => 'text-[14px]',
    'font-size:28px'               => 'text-[28px]',

    'line-height:1'                => 'leading-[1]',
    'line-height:1.3'              => 'leading-[1.3]',
    'line-height:1.5'              => 'leading-[1.5]',
    'line-height:1.6'              => 'leading-[1.6]',

    'text-transform:uppercase'     => 'uppercase',
    'text-transform:lowercase'     => 'lowercase',
    'text-transform:capitalize'    => 'capitalize',
    'letter-spacing:0.04em'        => 'tracking-[0.04em]',
    'letter-spacing:0.07em'        => 'tracking-[0.07em]',
    'letter-spacing:0.08em'        => 'tracking-[0.08em]',
    'font-weight:600'              => 'font-semibold',
    'font-weight:700'              => 'font-bold',

    'text-align:right'             => 'text-right',
    'text-align:center'            => 'text-center',
    'position:relative'            => 'relative',
    'position:absolute'            => 'absolute',
    'margin-top:1px'               => 'mt-px',
    'margin-top:2px'               => 'mt-0.5',
    'margin-bottom:1px'            => 'mb-px',
    'margin-bottom:20px'           => 'mb-5',

    // ── Elevation: exact matches for the tokens in @theme ─────────────────
    'box-shadow:0 1px 4px rgba(0,0,0,0.05)'  => 'shadow-card',
    'box-shadow:0 8px 25px rgba(0,0,0,0.08)' => 'shadow-card-hover',
    'box-shadow:0 24px 60px rgba(0,0,0,0.18)' => 'shadow-modal',
    'box-shadow:0 1px 4px rgba(0,0,0,0.06)'  => 'shadow-header',
    'box-shadow:4px 0 20px rgba(0,0,0,0.25)' => 'shadow-sidebar',

    // ── Surfaces: exact matches for the surface tokens in @theme ───────────
    'background:rgba(0,0,0,0.45)' => 'bg-black/45',
    'background:#f8fafc'          => 'bg-surface-subtle',
    'background:#f1f5f9'          => 'bg-surface-muted',
    'background:#ffffff'          => 'bg-white',
    'background:white'            => 'bg-white',

    // ── Palette: exact token values ───────────────────────────────────────
    'color:#e2e8f0'   => 'text-slate-200',
    'color:#94a3b8'   => 'text-slate-400',
    'color:#64748b'   => 'text-slate-500',
    'color:#475569'   => 'text-slate-600',
    'color:#334155'   => 'text-slate-700',
    'color:#1e293b'   => 'text-slate-800',
    'color:#0f172a'   => 'text-slate-900',

    'color:#eff6ff'   => 'text-brand-50',
    'color:#1d4ed8'   => 'text-brand-600',
    'color:#1e40af'   => 'text-brand-700',

    'color:#ecfdf5'   => 'text-success-50',
    'color:#059669'   => 'text-success-600',
    'color:#047857'   => 'text-success-700',

    'color:#fef3c7'   => 'text-warning-100',
    'color:#d97706'   => 'text-warning-600',
    'color:#b45309'   => 'text-warning-700',
    'color:#92400e'   => 'text-warning-800',

    'color:#fef2f2'   => 'text-danger-50',
    'color:#ef4444'   => 'text-danger-500',
    'color:#dc2626'   => 'text-danger-600',
    'color:#b91c1c'   => 'text-danger-700',
    'color:#991b1b'   => 'text-danger-800',

    'background:#059669'  => 'bg-success-600',
    'background:#d97706'  => 'bg-warning-600',
    'background:#dc2626'  => 'bg-danger-600',
    'background:#fef2f2'  => 'bg-danger-50',
    'background:#fecaca'  => 'bg-danger-200',
    'background:#fffbeb'  => 'bg-warning-50',
    'background:#fef3c7'  => 'bg-warning-100',
    'background:#ecfdf5'  => 'bg-success-50',
    'background:#d1fae5'  => 'bg-success-100',
    'background:#a7f3d0'  => 'bg-success-200',
    'background:#eff6ff'  => 'bg-brand-50',
    'background:#dbeafe'  => 'bg-brand-100',
    'background:#bfdbfe'  => 'bg-brand-200',
    'background:#f1f5f9'  => 'bg-slate-100',
    'background:#e2e8f0'  => 'bg-slate-200',
    'background:#cbd5e1'  => 'bg-slate-300',

    // #f0fdf4 is Tailwind's green-50, which predates this project's ramp.
    // Snapping it to the canonical success-50 is the point of the exercise.
    'background:#f0fdf4'  => 'bg-success-50',

    'border:1px solid #e2e8f0' => 'border border-slate-200',
    'border:1px solid #cbd5e1' => 'border border-slate-300',
    'border:1px solid #fecaca' => 'border border-danger-200',
    'border:1px solid #bfdbfe' => 'border border-brand-200',
    'border:1px solid #d1fae5' => 'border border-success-200',
    'border:1px solid #fde68a' => 'border border-warning-200',

    // ── Layout ────────────────────────────────────────────────────────────
    'left:12px'                 => 'left-3',
    'top:50%'                   => 'top-1/2',
    'transform:translateY(-50%)' => '-translate-y-1/2',
    'letter-spacing:.08em'      => 'tracking-[0.08em]',
    'letter-spacing:.04em'      => 'tracking-[0.04em]',
    'letter-spacing:.07em'      => 'tracking-[0.07em]',
    'margin-bottom:.25rem'      => 'mb-1',
    'margin-bottom:.5rem'       => 'mb-2',
    'display:none'              => 'hidden',
    'white-space:nowrap'        => 'whitespace-nowrap',
    'overflow:hidden'           => 'overflow-hidden',
];

/** Normalise a declaration for lookup: single spaces, no trailing semicolon. */
function normalise(string $declaration): string
{
    $declaration = trim($declaration);
    $declaration = preg_replace('#^([a-z-]+)\s*:\s*#i', '$1:', $declaration);

    // collapse runs of whitespace, and normalise "0 1px 4px" spacing
    return trim(preg_replace('/\s+/', ' ', $declaration));
}

/**
 * Convert one style attribute's contents into a class list plus whatever could
 * not be mapped.
 *
 * @return array{0: string, 1: string} [classes, remaining declarations]
 */
function convert(string $style): array
{
    $classes = [];
    $kept    = [];

    // `;` inside url() or quotes is not a real separator; none of the current
    // rules contain one, so a plain split is safe and the guard below keeps a
    // stray value rather than silently mangling it.
    foreach (explode(';', $style) as $declaration) {
        if (trim($declaration) === '') {
            continue;
        }

        $key = normalise($declaration);

        if (isset(RULES[$key])) {
            $classes[] = RULES[$key];
        } else {
            $kept[] = trim($declaration);
        }
    }

    return [implode(' ', $classes), implode('; ', $kept)];
}

/**
 * Merge a converted class list into an existing class attribute value,
 * discarding duplicates while keeping the original order.
 */
function mergeClasses(string $existing, string $classes): string
{
    $merged = trim($existing . ' ' . $classes);

    return implode(' ', array_values(array_unique(preg_split('/\s+/', $merged))));
}

/**
 * Rewrite a document, folding converted classes into each element's class
 * attribute and removing style attributes that become empty.
 *
 * The whole opening tag is matched so the existing class attribute is found
 * regardless of whether it sits before or after the style attribute, which
 * stops a second class="..." from being introduced. Quoted attribute values may
 * legally contain ">" (Blade conditions do), so quoted strings are consumed as
 * a unit rather than relying on ">" alone to delimit the tag.
 */
function migrate(string $src): string
{
    return preg_replace_callback(
        '/(<[a-zA-Z][\w:-]*\b)((?:[^>"]|"[^"]*")*?)(\sstyle="([^"]*)")((?:[^>"]|"[^"]*")*?)(\/?>)/',
        function (array $m): string {
            [$classes, $remaining] = convert($m[4]);

            if ($classes === '') {
                return $m[0];
            }

            $attrs = $m[2] . $m[5];

            if (preg_match('/\sclass="([^"]*)"/', $attrs, $cm, PREG_OFFSET_CAPTURE)) {
                $attrs = substr_replace(
                    $attrs,
                    ' class="' . mergeClasses($cm[1][0], $classes) . '"',
                    $cm[0][1],
                    strlen($cm[0][0])
                );
            } else {
                $attrs = ' class="' . $classes . '"' . $attrs;
            }

            if ($remaining !== '') {
                $attrs .= ' style="' . $remaining . '"';
            }

            return $m[1] . $attrs . $m[6];
        },
        $src
    );
}

if (in_array('--selftest', $argv, true)) {
    $cases = [
        // exact token shadow
        ['<div class="bg-white" style="box-shadow: 0 1px 4px rgba(0,0,0,0.05);">',
         '<div class="bg-white shadow-card">'],
        // no space after colon
        ['<div class="a" style="box-shadow:0 1px 4px rgba(0,0,0,0.05);">',
         '<div class="a shadow-card">'],
        // partial: keeps what it could not map, normalised (no trailing ";")
        ['<span class="a" style="font-size: 12px; color:#ff0000;">',
         '<span class="a text-[12px]" style="color:#ff0000">'],
        // typography triple, no existing class attribute
        ['<div style="font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.07em;">',
         '<div class="text-[10.5px] uppercase tracking-[0.07em]">'],
        // duplicates are collapsed
        ['<div class="text-[12px]" style="font-size: 12px;">',
         '<div class="text-[12px]">'],
        // unmapped declarations leave the attribute untouched
        ['<div class="a" style="color:#123456;">', '<div class="a" style="color:#123456;">'],

        // class AFTER style must be merged, never duplicated
        ['<div style="font-size:12px;" class="a">', '<div class="a text-[12px]">'],
        ['<div style="font-size:12px;" class="a" data-x="1">',
         '<div class="a text-[12px]" data-x="1">'],

        // other attributes between the tag name and style
        ['<span id="x" data-y="z" style="font-size:12px;">',
         '<span class="text-[12px]" id="x" data-y="z">'],

        // overlay + modal shadow together
        ['<div class="fixed" style="background: rgba(0,0,0,0.45); box-shadow: 0 24px 60px rgba(0,0,0,0.18);">',
         '<div class="fixed bg-black/45 shadow-modal">'],
    ];

    $failed = 0;
    foreach ($cases as $i => [$in, $want]) {
        $got = migrate($in);
        if ($got !== $want) {
            $failed++;
            printf("  case %d FAILED\n    in:   %s\n    want: %s\n    got:  %s\n", $i + 1, $in, $want, $got);
        }
    }

    printf("selftest: %d/%d passed\n", count($cases) - $failed, count($cases));
    exit($failed === 0 ? 0 : 1);
}

$root = dirname(__DIR__);
$write = in_array('--write', $argv, true);

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/resources/views')
);

$touchedFiles = 0;
$touchedAttrs = 0;

foreach ($it as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $src = file_get_contents($file->getPathname());
    $out = migrate($src);

    if ($out === $src) {
        continue;
    }

    $changed = preg_match_all('/\sstyle="([^"]*)"/', $src)
        - preg_match_all('/\sstyle="([^"]*)"/', $out);

    $touchedFiles++;
    $touchedAttrs += $changed;

    printf("  %-46s %3d attrs\n", str_replace($root . '/resources/views/', '', $file->getPathname()), $changed);

    if ($write) {
        file_put_contents($file->getPathname(), $out);
    }
}

printf("\nstyle attributes removed: %d across %d files\n", $touchedAttrs, $touchedFiles);
echo $write ? "WRITTEN\n" : "DRY RUN — pass --write to apply\n";

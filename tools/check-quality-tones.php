<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tones = collect(App\Models\FishType::QUALITY_CLASSES)
    ->mapWithKeys(fn ($q) => [
        $q => 'vpm-quality vpm-quality-' . str($q)->lower()->replace(' class', ''),
    ]);

echo $tones->toJson(JSON_PRETTY_PRINT), "\n";

// Every tone the map emits must exist in the stylesheet, otherwise the board
// would silently fall back to unstyled pills.
$css = file_get_contents(__DIR__ . '/../resources/css/app.css');

foreach ($tones as $class) {
    $slug = substr($class, strrpos($class, '-') + 1);
    $ok   = str_contains($css, ".vpm-quality-{$slug}");

    printf("  %-14s -> %-28s %s\n", $slug, $class, $ok ? 'defined' : 'MISSING IN CSS');
}

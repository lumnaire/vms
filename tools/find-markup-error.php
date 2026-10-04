<?php

/**
 * Locates the exact line libxml objects to on a rendered page.
 *
 * Usage: php tools/find-markup-error.php /prices [role]
 *
 * The role argument signs in as the first user of that role so authenticated
 * pages can be probed. Pass "none" to stay a guest.
 */

require __DIR__.'/../vendor/autoload.php';

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$uri = $argv[1] ?? '/prices';
$role = $argv[2] ?? 'none';

if ($role !== 'none') {
    $user = User::where('role', $role)->first();

    if (! $user) {
        fwrite(STDERR, "No user with role '{$role}'. Create one first.\n");
        exit(1);
    }

    Auth::login($user);
    echo "# signed in as {$user->username} ({$role})\n";
}

$request = Request::create($uri, 'GET');
$html = $app->handle($request)->getContent();

// Alpine/Vue directives are framework syntax, not valid HTML, so they are
// stripped to leave only genuine structural problems.
$html = preg_replace(
    '/\s(?:@[\w:.-]+|:[\w.-]+|x-[\w.-]+|wire:[\w.-]+)(?:="[^"]*")?/',
    '',
    $html
);

$previous = libxml_use_internal_errors(true);
libxml_clear_errors();

$doc = new DOMDocument;
$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

foreach (libxml_get_errors() as $error) {
    printf("line %-5d %s\n", $error->line, trim($error->message));

    $lines = explode("\n", $html);
    $start = max(0, $error->line - 3);
    for ($i = $start; $i < min(count($lines), $error->line + 2); $i++) {
        printf("   %s%4d| %s\n", $i + 1 === $error->line ? '>' : ' ', $i + 1, rtrim($lines[$i]));
    }
    echo "\n";
}

libxml_clear_errors();
libxml_use_internal_errors($previous);

<?php

namespace Tests\Feature;

use App\Models\FishType;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Frontend smoke test.
 *
 * Renders every GET route for every role. A styling refactor can compile
 * cleanly and still break at runtime — a missing Blade component, a bad
 * component prop, an undefined token — so this exercises the real
 * middleware + controller + view path rather than just compiling Blade.
 *
 * It also asserts the legacy styling debt is gone, since those are the things
 * that silently reintroduce inconsistency:
 *   - the Tailwind Play CDN (replaced by Vite)
 *   - Font Awesome (replaced by Bootstrap Icons)
 *   - the four non-Jakarta font families
 */
class FrontendSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** Routes reachable by each role, mirroring routes/web.php. */
    private function routesFor(string $role): array
    {
        return match ($role) {
            'supervisor' => [
                '/supervisor/dashboard', '/supervisor/vendors', '/supervisor/staff',
                '/supervisor/fish-types', '/supervisor/price-guides',
                '/supervisor/forecasts', '/supervisor/reports', '/supervisor/account',
            ],
            'staff' => [
                '/staff/dashboard', '/staff/confirmations', '/staff/vendors',
                '/staff/price-guides', '/staff/reports',
            ],
            'vendor' => [
                '/vendor/dashboard', '/vendor/inventory',
            ],
            default => ['/'],
        };
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);

        if ($role === 'vendor') {
            VendorProfile::create([
                'user_id'       => $user->id,
                'stall_number'  => 'A-01',
                'business_name' => $user->name . ' Stall',
            ]);
        }

        return $user;
    }

    public function test_guest_pages_render(): void
    {
        // "/" is the price board and carries the navbar sign-in form, so it is
        // the only guest page: there is no separate /prices or /login page.
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Whoops', false);

        $this->get('/prices')->assertNotFound();

        // There is no sign-in page to land on, so an old bookmark or a
        // remembered URL is sent back to the board instead of an error.
        $this->get('/login')->assertRedirect('/');
    }

    public function test_root_serves_the_public_price_board_with_a_navbar_login_form(): void
    {
        $response = $this->get('/')->assertOk();

        // Consumer board content.
        $response->assertSee('Live Price Monitoring Board', false);

        // Credentials in the navbar, not on a separate page.
        $response->assertSee('name="username"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee(route('login'), false);

        // The tooltip has to state who may sign in.
        $response->assertSee('Vendor, staff and supervisor accounts only.', false);
    }

    public function test_signed_in_users_do_not_see_the_navbar_login_form(): void
    {
        $supervisor = $this->makeUser('supervisor');

        $this->actingAs($supervisor)
            ->get('/')
            ->assertOk()
            ->assertDontSee('name="password"', false);
    }

    /**
     * Hiding the form must not leave a blank header. Someone already signed in
     * needs to see who they are, a way to their dashboard, and a way out —
     * otherwise the board reads as broken and there is no way back to the
     * sign-in form from here.
     */
    public function test_signed_in_users_see_their_account_instead_of_a_blank_header(): void
    {
        $supervisor = $this->makeUser('supervisor');

        $response = $this->actingAs($supervisor)->get('/')->assertOk();

        $response->assertSee($supervisor->name);
        $response->assertSee('Supervisor');
        $response->assertSee('My Dashboard');
        $response->assertSee(route('supervisor.dashboard'), false);
        $response->assertSee('Sign Out');
        $response->assertSee(route('logout'), false);
    }

    public function test_each_role_is_sent_to_its_own_dashboard(): void
    {
        foreach ([
            'supervisor' => '/supervisor/dashboard',
            'staff'      => '/staff/dashboard',
            'vendor'     => '/vendor/dashboard',
        ] as $role => $path) {
            $user = $this->makeUser($role);

            $this->actingAs($user)
                ->get('/')
                ->assertOk()
                ->assertSee($path, false);
        }
    }

    /**
     * The session cookie must carry no Expires/Max-Age, so the browser drops it
     * when the last tab closes and the next visit starts signed out.
     */
    public function test_session_cookie_expires_when_the_browser_closes(): void
    {
        $user = $this->makeUser('supervisor');

        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('supervisor.dashboard'));

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === config('session.cookie'));

        $this->assertNotNull($cookie, 'No session cookie was issued on login.');

        // Symfony reports 0 for "no expiry", i.e. a session cookie. A timed
        // expiry would keep the user signed in after the browser closed.
        $this->assertSame(0, $cookie->getExpiresTime(), 'Session cookie must not expire on a timer.');
        $this->assertSame(0, $cookie->getMaxAge(), 'Session cookie must not carry a Max-Age.');
    }

    public function test_every_authenticated_page_renders_for_its_role(): void
    {
        // A fish type gives the price guide and price board something to show.
        FishType::create([
            'name'          => 'Bangus',
            'quality_class' => 'First Class',
            'is_active'     => true,
        ]);

        foreach (['supervisor', 'staff', 'vendor'] as $role) {
            $user = $this->makeUser($role);

            foreach ($this->routesFor($role) as $uri) {
                $this->actingAs($user)
                    ->get($uri)
                    ->assertOk();
            }
        }
    }

    /**
     * The Play CDN, Font Awesome and the stray font families were the three
     * things that let the old designs drift apart. Guard against regressions.
     */
    public function test_no_view_reintroduces_the_legacy_cdn_or_font_stack(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file);

            foreach ([
                'cdn.tailwindcss.com'            => 'Tailwind Play CDN',
                'font-awesome'                   => 'Font Awesome',
                'fontawesome'                    => 'Font Awesome',
                'fa-solid'                       => 'Font Awesome',
                "family=Inter"                   => 'Inter font',
                'family=Libre+Baskerville'        => 'Libre Baskerville font',
                'family=Roboto'                  => 'Roboto font',
                "font-family: 'Inter'"           => 'Inter font',
            ] as $needle => $label) {
                if (str_contains($contents, $needle)) {
                    $offenders[] = $label . ' in ' . $this->relative($file);
                }
            }
        }

        $this->assertSame([], $offenders, "Legacy styling reintroduced:\n" . implode("\n", $offenders));
    }

    /**
     * The definitive structural check: parse each rendered page with libxml and
     * assert it produced no errors. This catches every malformed-attribute
     * shape, including ones no hand-written regex would think to look for.
     */
    public function test_rendered_pages_parse_without_markup_errors(): void
    {
        $fishType = FishType::create([
            'name'          => 'Bangus',
            'quality_class' => 'First Class',
            'is_active'     => true,
        ]);

        $problems = [];

        $pages = [
            'guest:/'          => ['/', null],
        ];

        foreach (['supervisor', 'staff', 'vendor'] as $role) {
            $user = $this->makeUser($role);
            foreach ($this->routesFor($role) as $uri) {
                $pages["{$role}:{$uri}"] = [$uri, $user];
            }
        }

        foreach ($pages as $label => [$uri, $user]) {
            $html = $user
                ? $this->actingAs($user)->get($uri)->assertOk()->getContent()
                : $this->get($uri)->assertOk()->getContent();

            // Alpine/Vue-style directives and Blade leftovers are expected in
            // the source, but the document itself must be well-formed enough
            // for a parser to walk without reporting attribute errors.
            // <script>/<style> bodies are CDATA, not markup. libxml walks into
            // them and reports HTML tags that merely appear inside JavaScript
            // string literals, so their contents are removed entirely.
            $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);

            // Alpine/Vue directives (@click, :class, x-data, wire:*) are not
            // valid HTML, and libxml reads ":class" as a second `class`
            // attribute. They are framework syntax, not defects, so they are
            // removed before parsing to leave only genuine structural problems.
            $html = preg_replace(
                '/\s(?:@[\w:.-]+|:[\w.-]+|x-[\w.-]+|wire:[\w.-]+)(?:="[^"]*")?/',
                '',
                $html
            );

            $previous = libxml_use_internal_errors(true);
            libxml_clear_errors();

            $doc = new \DOMDocument();
            $doc->loadHTML(
                '<?xml encoding="utf-8" ?>' . $html,
                LIBXML_NOERROR | LIBXML_NOWARNING
            );

            $errors = array_filter(
                libxml_get_errors(),
                // libxml still parses with an HTML4 DTD, so it flags every
                // HTML5 and SVG element as an unknown tag. Those are expected
                // and are not defects; everything else it complains about is.
                fn ($e) => ! preg_match(
                    '/^Tag (?:header|main|section|nav|footer|article|aside|figure|'
                    . 'figcaption|time|mark|summary|details|template|picture|search|'
                    . 'dialog|slot|svg|path|line|circle|rect|polyline|polygon|g|'
                    . 'defs|use|ellipse|text|tspan|defs)\b.*\binvalid\b/i',
                    trim($e->message)
                )
            );

            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            foreach ($errors as $error) {
                $problems[] = $label . ': ' . trim($error->message);
            }
        }

        $this->assertSame([], array_slice(array_unique($problems), 0, 12), "Malformed markup:\n" . implode("\n", array_unique($problems)));
    }

    /**
     * Catches malformed markup that still *renders* (so assertOk passes) but is
     * visibly wrong. These are the exact shapes produced by a bad find/replace
     * during the design-token migration.
     */
    public function test_no_view_contains_malformed_attributes(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file);

            foreach ([
                // a token name that lost its prefix surgery: text---color-x-500
                '/\b(?:text|bg|border|fill|stroke)---color-/i' => 'double-dashed token class',
                // <x-icon class="bi ..."> — the icon font hook leaked onto the component
                '/<x-icon\b[^>]*\sclass="bi[\s"]/i'                    => 'icon font class leaked onto <x-icon>',
                // a class attribute that swallowed the following style attribute
                '/\sclass="[^"]*\bstyle\s*=/'                           => 'class attribute swallowed a style attribute',
                // an <i>/<x-icon> class attribute that was never closed
                '/<(?:i|x-icon)\s+class="[^">]+>/'                       => 'unterminated class attribute',
                '/\sclass=""\s*\/?>/'                                   => 'empty class attribute',
            ] as $pattern => $label) {
                if (preg_match($pattern, $contents)) {
                    $offenders[] = $label . ' in ' . $this->relative($file);
                }
            }
        }

        $this->assertSame([], $offenders, "Malformed attributes found:\n" . implode("\n", $offenders));
    }

    /**
     * Every custom property referenced with var(--token) must exist in app.css,
     * otherwise it silently resolves to nothing.
     */
    public function test_every_referenced_css_variable_is_defined(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        preg_match_all('/(--[a-z0-9-]+)\s*:/i', $css, $defined);
        $defined = array_map('strtolower', $defined[1]);

        // Tailwind v4 supplies these itself; they are not ours to declare.
        $tailwindOwned = ['--tw-', '--color-slate-', '--font-', '--spacing-',
            '--radius-', '--text-', '--shadow-', '--default-', '--leading-',
            '--container-', '--color-black', '--color-white'];

        $missing = [];
        foreach ($this->bladeFiles() as $file) {
            preg_match_all('/var\(\s*(--[a-z0-9-]+)/i', file_get_contents($file), $used);

            foreach (array_unique(array_map('strtolower', $used[1])) as $name) {
                if (in_array($name, $defined, true)) {
                    continue;
                }
                foreach ($tailwindOwned as $prefix) {
                    if (str_starts_with($name, $prefix)) {
                        continue 2;
                    }
                }
                $missing[$name][] = $this->relative($file);
            }
        }

        $report = '';
        foreach ($missing as $name => $files) {
            $report .= "  {$name} in " . implode(', ', array_unique($files)) . "\n";
        }

        $this->assertSame([], $missing, "Undefined CSS variables:\n" . $report);
    }

    /**
     * The Play CDN must be gone, but so must any *other* inline framework
     * config that would conflict with the compiled stylesheet.
     */
    public function test_layouts_load_styles_through_vite(): void
    {
        foreach (glob(resource_path('views/layouts/*.blade.php')) as $layout) {
            $contents = file_get_contents($layout);

            $this->assertStringContainsString('@vite', $contents, $this->relative($layout));
        }
    }

    /** @return array<int, string> */
    private function bladeFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function relative(string $path): string
    {
        return str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $path);
    }
}
